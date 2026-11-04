# -*- coding: utf-8 -*-
"""
probar_llm.py — Autodiagnóstico de la capa de IA del chatbot TermoPlast.

Sirve para VERIFICAR, en un solo comando, que tu backend de LLM funciona
(nube con API key, u Ollama local) ANTES de la defensa. No necesita Flask ni
la base de datos: usa directamente el motor (clasificador + RAG) y el cliente LLM.

USO (desde la carpeta chatbot/):
    python probar_llm.py

Muestra el backend activo, hace una conexión de prueba y responde algunas
preguntas de ejemplo, indicando de dónde salió cada respuesta.
"""
import sys, re, html
from motor import MotorIntenciones
import llm


def _limpiar(t):
    return html.unescape(re.sub(r"<[^>]+>", "", t or "")).strip()

# Mismas categorías que usa el servicio: los datos reales NO pasan por el LLM.
DATOS = {"estado_pedido", "resumen_cuenta", "impacto", "diseno_estado",
         "estimacion", "cancelar_pedido", "modificar_pedido"}
CONTROL = {"saludo", "despedida", "afirmar", "negar", "fuera_de_alcance"}

PREGUNTAS = [
    "de que estan hechas las piezas",
    "que tamaño maximo soporta la maquina",
    "puedo pagar con tarjeta",
    "las piezas aguantan agua caliente",
    "como subo mi diseño",
]


def _prueba_conexion(cfg):
    """Para backends de red: hace una llamada mínima y reporta ok/falla."""
    backend = (cfg.get("backend") or "off").lower()
    if backend not in ("openai", "ollama", "nube"):
        return None
    r = llm._generar_openai(cfg, "decime 'ok' y nada mas",
                            "Prueba de conexión del asistente de TermoPlast.")
    return r


def main():
    cfg = llm.cargar_config()
    print("=" * 56)
    print(" TermoPlast · Autodiagnóstico de la IA del chatbot")
    print("=" * 56)
    print(f" backend : {cfg.get('backend')}")
    print(f" modelo  : {cfg.get('model')}")
    print(f" base_url: {cfg.get('base_url')}")
    print(f" api_key : {'(cargada)' if cfg.get('api_key') else '(sin clave)'}")
    print("-" * 56)

    backend = (cfg.get("backend") or "off").lower()
    if backend in ("openai", "ollama", "nube"):
        print(" Probando conexión con el modelo…")
        r = _prueba_conexion(cfg)
        if r:
            print(f"   ✓ Conexión OK. Respondió: {r[:80]}")
        else:
            print("   ✗ No respondió. Revisá base_url, model y la API key")
            print("     (variable TERMOPLAST_LLM_KEY) o que Ollama esté corriendo.")
            print("     Mientras tanto, el chat usa el modo 'extractivo' (offline).")
    elif backend == "extractivo":
        print(" Backend 'extractivo' (offline): responde con el conocimiento recuperado.")
    else:
        print(" Backend 'off': solo respuestas fijas.")
    print("-" * 56)

    print(" Entrenando el motor (clasificador + RAG)…")
    m = MotorIntenciones()
    print(f"   {len(m.clases)} intenciones · {len(m.conocimiento)} entradas de conocimiento\n")

    for q in PREGUNTAS:
        d = m.analizar_detalle(q)
        intent = d["intencion"]
        if intent in DATOS:
            print(f" • «{q}»\n   → [datos reales: lo resuelve el sistema, no el LLM]\n")
            continue
        if intent in CONTROL:
            print(f" • «{q}»\n   → [{intent}] {m.respuesta_estatica(intent)}\n")
            continue
        ctx = m.recuperar(q, k=3)
        est = m.respuesta_estatica(intent)
        if est and intent != "fallback":
            ctx.insert(0, {"tema": intent, "texto": _limpiar(est), "score": 1.0})
        resp = llm.generar(q, ctx)
        fuente = cfg.get("backend") if resp else "estática"
        print(f" • «{q}»  [intención: {intent} · fuente: {fuente}]")
        print(f"   → {_limpiar(resp or est)}\n")

    print("=" * 56)
    print(" Listo. Si el backend es de nube/Ollama y la conexión dio ✓,")
    print(" el chatbot va a generar respuestas con el LLM.")
    print("=" * 56)


if __name__ == "__main__":
    sys.exit(main())
