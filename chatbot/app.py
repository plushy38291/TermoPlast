# ============================================================
#  app.py — Microservicio del chatbot TermoPlast (Flask).
#  Expone el motor por HTTP para que PHP lo consulte.
#  Ejecutar:  python app.py   (queda escuchando en el puerto 5001)
#
#  Endpoints:
#   GET  /salud      estado del servicio + backend LLM
#   POST /analizar   (compat) clasificación de intención
#   POST /responder  clasificación + RAG + (opcional) generación con LLM
# ============================================================
import re, html, os, tempfile
from flask import Flask, request, jsonify
from motor import MotorIntenciones
import llm
import ingestar

app = Flask(__name__)
motor = MotorIntenciones()   # entrena una sola vez, al arrancar

# Tope de tamaño de subida (8 MB) para no cargar archivos enormes.
app.config["MAX_CONTENT_LENGTH"] = 8 * 1024 * 1024

# Intenciones que necesitan DATOS REALES del usuario: las resuelve el PHP de
# forma determinista (el LLM NUNCA inventa estados, números ni kg).
DATOS = {"estado_pedido", "resumen_cuenta", "impacto", "diseno_estado",
         "estimacion", "cancelar_pedido", "modificar_pedido"}
# Intenciones de control/sociales: respuesta fija y acotada (no van al LLM).
CONTROL = {"saludo", "despedida", "afirmar", "negar", "fuera_de_alcance"}


def _limpiar(t):
    """Quita etiquetas HTML de las respuestas estáticas para usarlas como contexto."""
    return html.unescape(re.sub(r"<[^>]+>", "", t or "")).strip()


@app.get("/salud")
def salud():
    return jsonify(estado="ok", intenciones=len(motor.clases),
                   conocimiento=len(getattr(motor, "conocimiento", [])),
                   llm=llm.estado())


@app.post("/analizar")
def analizar():
    datos = request.get_json(silent=True) or {}
    d = motor.analizar_detalle(datos.get("texto", ""))
    return jsonify(
        intencion=d["intencion"], confianza=round(d["confianza"], 3),
        dudosa=d["dudosa"], alternativas=d["alternativas"],
        respuesta_estatica=motor.respuesta_estatica(d["intencion"]),
    )


@app.post("/responder")
def responder():
    datos = request.get_json(silent=True) or {}
    texto = datos.get("texto", "")
    historial = datos.get("historial", []) or []
    d = motor.analizar_detalle(texto)
    intent = d["intencion"]
    estatica = motor.respuesta_estatica(intent)
    contexto = motor.recuperar(texto, k=3)

    es_dato = intent in DATOS
    generada, fuente = None, "off"

    # El LLM/RAG solo entra para intenciones INFORMATIVAS o para dudas con
    # contexto relevante. Datos reales, control y fuera_de_alcance NO pasan por el LLM.
    informativa = intent not in DATOS and intent not in CONTROL
    hay_contexto_fuerte = bool(contexto) and contexto[0]["score"] >= 0.12
    if informativa and (intent != "fallback" or hay_contexto_fuerte):
        # Garantizamos el hecho correcto en el contexto: sumamos la respuesta
        # estática de la intención (si existe) como primer fragmento.
        ctx = list(contexto)
        if estatica and intent != "fallback":
            ctx.insert(0, {"tema": intent, "texto": _limpiar(estatica), "score": 1.0})
        generada = llm.generar(texto, ctx, historial=historial)
        if generada:
            fuente = (llm.cargar_config().get("backend") or "off")

    return jsonify(
        intencion=intent, confianza=round(d["confianza"], 3),
        dudosa=d["dudosa"], alternativas=d["alternativas"],
        respuesta_estatica=estatica,
        es_dato=es_dato, generada=generada, fuente=fuente,
        contexto=[{"id": c["id"], "score": c["score"]} for c in contexto],
    )


# ============================================================
#  CONOCIMIENTO (RAG) — gestión de documentos subidos por el admin.
#  Propuesta 1.1: "chatbot que procesa archivos cargados".
#  El PHP del panel (protegido) llama a estos endpoints vía cURL.
# ============================================================
@app.get("/conocimiento")
def conocimiento_listar():
    """Lista las fuentes (documentos) ya ingestadas al RAG."""
    try:
        return jsonify(ok=True,
                       fuentes=ingestar.listar_fuentes(),
                       base=sum(1 for e in motor.conocimiento if not e.get("fuente")),
                       total=len(motor.conocimiento))
    except Exception as e:
        return jsonify(ok=False, error=str(e)), 500


@app.post("/ingestar")
def conocimiento_ingestar():
    """Recibe un archivo (multipart 'archivo') o texto (JSON {nombre, texto}),
    lo trocea y lo agrega a la base de conocimiento. Reindexa el motor."""
    try:
        # A) Archivo subido (multipart/form-data)
        if "archivo" in request.files:
            f = request.files["archivo"]
            nombre = os.path.basename(f.filename or "documento")
            ext = os.path.splitext(nombre)[1].lower()
            if ext not in ingestar.EXT_OK:
                return jsonify(ok=False,
                               error=f"Tipo no soportado ({ext}). Usá {', '.join(ingestar.EXT_OK)}."), 400
            # Guardamos a un temporal, extraemos y limpiamos.
            fd, tmp = tempfile.mkstemp(suffix=ext)
            os.close(fd)
            try:
                f.save(tmp)
                texto = ingestar.extraer_texto(tmp)
            finally:
                try: os.remove(tmp)
                except OSError: pass
        else:
            # B) Texto pegado (JSON)
            datos = request.get_json(silent=True) or {}
            nombre = (datos.get("nombre") or "documento.txt").strip()
            texto = datos.get("texto", "")

        res = ingestar.ingestar(nombre, texto)
        motor._indexar_conocimiento()   # recarga el índice RAG en caliente
        return jsonify(ok=True, **res, total=len(motor.conocimiento))
    except (ValueError, RuntimeError) as e:
        return jsonify(ok=False, error=str(e)), 400
    except Exception as e:
        return jsonify(ok=False, error=str(e)), 500


@app.post("/eliminar_conocimiento")
def conocimiento_eliminar():
    """Elimina todos los fragmentos de una fuente (documento). Reindexa."""
    datos = request.get_json(silent=True) or {}
    fuente = (datos.get("fuente") or "").strip()
    if not fuente:
        return jsonify(ok=False, error="Falta la fuente a eliminar."), 400
    try:
        n = ingestar.eliminar_fuente(fuente)
        motor._indexar_conocimiento()
        return jsonify(ok=True, eliminados=n, total=len(motor.conocimiento))
    except Exception as e:
        return jsonify(ok=False, error=str(e)), 500


if __name__ == "__main__":
    # host 127.0.0.1: solo accesible desde la misma máquina (lo llama PHP).
    app.run(host="127.0.0.1", port=5001)
