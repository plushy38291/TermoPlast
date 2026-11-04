# ============================================================
#  llm.py — Capa LLM PLUGGABLE y ACOTADA del chatbot TermoPlast.
#
#  Genera respuestas en lenguaje natural PERO:
#   · SOLO sobre TermoPlast (prompt de sistema acotado),
#   · fundamentadas en el CONTEXTO recuperado (RAG) — no inventa,
#   · nunca datos privados de la cuenta (eso lo resuelve el PHP).
#
#  Backends (config en llm_config.json):
#   - "off"        : no usa LLM (el sistema cae a la respuesta estática).
#   - "extractivo" : SIN modelo ni internet. Arma la respuesta con el
#                    mejor fragmento del contexto (RAG). Útil offline.
#   - "openai"     : API compatible con OpenAI /chat/completions.
#                    Sirve para la NUBE (OpenAI, Groq, Together, OpenRouter…)
#                    y también para OLLAMA local (base_url .../v1).
#
#  Se usa solo la librería estándar (urllib): no agrega dependencias.
# ============================================================
import os, json, urllib.request, urllib.error

RUTA_CONFIG = os.path.join(os.path.dirname(__file__), "llm_config.json")

CONFIG_DEFECTO = {
    "backend": "extractivo",          # off | extractivo | openai
    "base_url": "https://api.openai.com/v1",
    "model": "gpt-4o-mini",
    "api_key": "",
    "temperature": 0.2,
    "max_tokens": 320,
    "timeout": 12,
    # Nombre del asistente (lo elegís vos). Se usa en el prompt del LLM.
    "nombre_asistente": "Asistente TermoPlast"
}


def system_prompt(nombre="Asistente TermoPlast"):
    """Prompt de sistema ACOTADO a TermoPlast, con el nombre del asistente.
    Pide respuestas más completas pero igual breves y fundamentadas."""
    return (
        f"Te llamás {nombre}, el asistente virtual de TermoPlast, una plataforma que "
        "fabrica piezas en plástico reciclado (HDPE y PP) a partir de tapitas, para "
        "emprendedores. Si te preguntan tu nombre, respondé con el tuyo.\n"
        "Reglas estrictas:\n"
        "1) Respondé ÚNICAMENTE con la información del CONTEXTO. Si el contexto no alcanza, "
        "decí que no tenés ese dato y ofrecé los temas que sí manejás.\n"
        "2) Hablá SOLO de TermoPlast (pedidos, diseños, materiales, impacto, retiro, tiempos, "
        "cuenta). Si te preguntan algo ajeno, aclarás con amabilidad que solo ayudás con TermoPlast.\n"
        "3) NUNCA inventes datos de la cuenta del usuario (estados de pedidos, números, kg): "
        "esos los entrega el sistema, no vos.\n"
        "4) Tono cercano y argentino (voseo). Respuesta COMPLETA pero concisa: explicá el "
        "'por qué' o el 'cómo' cuando aporte, en 3 a 5 oraciones. Podés cerrar con una "
        "pregunta o sugerencia útil. Sin markdown ni listas largas."
    )


def cargar_config():
    cfg = dict(CONFIG_DEFECTO)
    try:
        with open(RUTA_CONFIG, encoding="utf-8") as f:
            cfg.update({k: v for k, v in json.load(f).items() if v is not None})
    except (OSError, ValueError):
        pass
    # La variable de entorno pisa la clave del archivo (más seguro para no versionarla).
    cfg["api_key"] = os.environ.get("TERMOPLAST_LLM_KEY", cfg.get("api_key", ""))
    return cfg


def _contexto_a_texto(contexto):
    partes = []
    for c in (contexto or []):
        if isinstance(c, dict):
            partes.append(f"- {c.get('tema','')}: {c.get('texto','')}".strip())
        elif c:
            partes.append(f"- {c}")
    return "\n".join(partes)


def _historial_a_mensajes(historial, max_turnos=6):
    """Convierte el historial [{rol, texto}] en mensajes del formato OpenAI.
    Se acota a los últimos turnos para no inflar el prompt."""
    msgs = []
    for h in (historial or [])[-max_turnos:]:
        if not isinstance(h, dict):
            continue
        rol = "assistant" if h.get("rol") == "assistant" else "user"
        txt = (h.get("texto") or "").strip()
        if txt:
            msgs.append({"role": rol, "content": txt})
    return msgs


def _generar_openai(cfg, pregunta, contexto_txt, historial=None):
    """Llama a un endpoint compatible con OpenAI. Devuelve texto o None."""
    url = cfg["base_url"].rstrip("/") + "/chat/completions"
    mensajes = [{"role": "system", "content": system_prompt(cfg.get("nombre_asistente", "Asistente TermoPlast"))}]
    mensajes += _historial_a_mensajes(historial)   # contexto de la charla (hilo)
    mensajes.append({"role": "user",
                     "content": f"CONTEXTO:\n{contexto_txt}\n\nPREGUNTA DEL USUARIO:\n{pregunta}"})
    cuerpo = {
        "model": cfg["model"],
        "temperature": cfg.get("temperature", 0.2),
        "max_tokens": cfg.get("max_tokens", 220),
        "messages": mensajes,
    }
    datos = json.dumps(cuerpo).encode("utf-8")
    req = urllib.request.Request(url, data=datos, method="POST")
    req.add_header("Content-Type", "application/json")
    if cfg.get("api_key"):
        req.add_header("Authorization", "Bearer " + cfg["api_key"])
    try:
        with urllib.request.urlopen(req, timeout=cfg.get("timeout", 12)) as r:
            d = json.loads(r.read().decode("utf-8"))
        return (d["choices"][0]["message"]["content"] or "").strip() or None
    except (urllib.error.URLError, KeyError, IndexError, ValueError, TimeoutError, OSError):
        return None   # cualquier problema -> el caller cae a la respuesta estática


def _generar_extractivo(pregunta, contexto):
    """SIN modelo: devuelve el fragmento más relevante del contexto (RAG).
    Es la respuesta offline por defecto cuando no hay LLM configurado."""
    if not contexto:
        return None
    c0 = contexto[0]
    return c0.get("texto") if isinstance(c0, dict) else str(c0)


def generar(pregunta, contexto, cfg=None, historial=None):
    """Punto de entrada. Devuelve una respuesta de texto o None (para que el
    sistema caiga a la respuesta estática/determinista)."""
    cfg = cfg or cargar_config()
    backend = (cfg.get("backend") or "off").lower()
    if backend == "off":
        return None
    if backend == "extractivo":
        return _generar_extractivo(pregunta, contexto)
    if backend in ("openai", "ollama", "nube"):
        txt = _generar_openai(cfg, pregunta, _contexto_a_texto(contexto), historial)
        # Si la nube falla, intentamos no dejar al usuario sin respuesta:
        return txt if txt else _generar_extractivo(pregunta, contexto)
    return None


def estado():
    """Resumen para /salud (sin exponer la clave)."""
    cfg = cargar_config()
    return {"backend": cfg.get("backend"), "model": cfg.get("model"),
            "con_clave": bool(cfg.get("api_key")),
            "nombre_asistente": cfg.get("nombre_asistente", "Asistente TermoPlast")}
