# ============================================================
#  ingestar.py — Ingesta de DOCUMENTOS a la base de conocimiento (RAG).
#
#  Flujo (propuesta 1.1 "chatbot que procesa archivos cargados"):
#    El administrador sube un .txt / .md / .pdf desde el panel →
#    se EXTRAE el texto, se TROCEA en fragmentos y se AGREGAN a
#    conocimiento.json con una 'fuente' (el nombre del documento).
#    El motor RAG los indexa junto al conocimiento base, así el
#    asistente puede fundamentar respuestas con ese material.
#
#  Diseño:
#   · NO toca la base de datos ni el LLM: solo lee/escribe el JSON.
#   · Idempotente por fuente: re-subir un documento REEMPLAZA sus
#     fragmentos anteriores (no duplica).
#   · El conocimiento BASE (sin 'fuente') nunca se borra desde acá.
#   · Sin dependencias para .txt/.md; para .pdf usa pdfminer.six o
#     PyPDF2 si están instalados (si no, avisa con un mensaje claro).
# ============================================================
import os, re, json, unicodedata

RUTA_CONOCIMIENTO = os.path.join(os.path.dirname(__file__), "conocimiento.json")

# Límites defensivos (un documento no debería inundar el índice).
MAX_CHARS_FRAGMENTO = 600      # tamaño objetivo de cada fragmento
SOLAPAMIENTO        = 80       # caracteres de solape entre fragmentos
MAX_FRAGMENTOS_DOC  = 120      # tope de fragmentos por documento
EXT_OK              = (".txt", ".md", ".markdown", ".csv", ".pdf")


# ---------- utilidades ----------
def _slug(nombre: str) -> str:
    """Convierte 'Guía de Materiales.pdf' -> 'guia_de_materiales'."""
    base = os.path.splitext(os.path.basename(nombre or "doc"))[0]
    base = unicodedata.normalize("NFKD", base.lower())
    base = "".join(c for c in base if not unicodedata.combining(c))
    base = re.sub(r"[^a-z0-9]+", "_", base).strip("_")
    return base or "doc"


def _limpiar_texto(t: str) -> str:
    """Normaliza espacios y saltos de línea sin perder los párrafos."""
    t = (t or "").replace("\r\n", "\n").replace("\r", "\n")
    t = re.sub(r"[ \t]+", " ", t)
    t = re.sub(r"\n{3,}", "\n\n", t)          # máx. una línea en blanco
    return t.strip()


# ---------- extracción de texto ----------
def extraer_texto(ruta: str) -> str:
    """Devuelve el texto plano de un archivo soportado.
    Lanza ValueError si la extensión no se admite y RuntimeError si
    falta una librería para PDF."""
    ext = os.path.splitext(ruta)[1].lower()
    if ext not in EXT_OK:
        raise ValueError(f"Tipo de archivo no soportado: {ext or 'desconocido'}. "
                         f"Usá {', '.join(EXT_OK)}.")
    if ext == ".pdf":
        return _limpiar_texto(_extraer_pdf(ruta))
    # Texto plano (txt/md/csv): leemos tolerando codificación.
    with open(ruta, "r", encoding="utf-8", errors="ignore") as f:
        return _limpiar_texto(f.read())


def _extraer_pdf(ruta: str) -> str:
    # 1) pdfminer.six (mejor calidad de texto)
    try:
        from pdfminer.high_level import extract_text  # type: ignore
        return extract_text(ruta) or ""
    except ImportError:
        pass
    # 2) PyPDF2 como alternativa
    try:
        from PyPDF2 import PdfReader  # type: ignore
        lector = PdfReader(ruta)
        return "\n".join((p.extract_text() or "") for p in lector.pages)
    except ImportError:
        raise RuntimeError(
            "Para leer PDF instalá pdfminer.six (pip install pdfminer.six) "
            "o PyPDF2. También podés subir el texto como .txt.")


# ---------- troceado ----------
def trocear(texto: str, max_chars: int = MAX_CHARS_FRAGMENTO,
            solapamiento: int = SOLAPAMIENTO) -> list:
    """Parte el texto en fragmentos de ~max_chars respetando párrafos y
    oraciones. Agrega un pequeño solape para no cortar ideas al medio."""
    texto = _limpiar_texto(texto)
    if not texto:
        return []
    # Unidades naturales: párrafos y, dentro, oraciones.
    unidades = []
    for parrafo in texto.split("\n\n"):
        parrafo = parrafo.strip()
        if not parrafo:
            continue
        # cortes por oración (sin romper números como "1.5")
        oraciones = re.split(r"(?<=[\.\!\?])\s+", parrafo)
        for o in oraciones:
            o = o.strip()
            if o:
                unidades.append(o)

    fragmentos, actual = [], ""
    for u in unidades:
        if len(u) > max_chars:
            # Oración larguísima: la cortamos "duro" por tamaño.
            if actual:
                fragmentos.append(actual.strip()); actual = ""
            for i in range(0, len(u), max_chars):
                fragmentos.append(u[i:i + max_chars].strip())
            continue
        if len(actual) + len(u) + 1 <= max_chars:
            actual = (actual + " " + u).strip()
        else:
            if actual:
                fragmentos.append(actual.strip())
            # arrancamos el nuevo con un solape del final del anterior
            cola = actual[-solapamiento:] if solapamiento and actual else ""
            actual = (cola + " " + u).strip()
    if actual:
        fragmentos.append(actual.strip())

    # Limpieza final y tope.
    fragmentos = [f for f in fragmentos if len(f) >= 20]
    return fragmentos[:MAX_FRAGMENTOS_DOC]


# ---------- lectura/escritura del conocimiento ----------
def _cargar(ruta=RUTA_CONOCIMIENTO) -> dict:
    with open(ruta, encoding="utf-8") as f:
        return json.load(f)


def _guardar(data: dict, ruta=RUTA_CONOCIMIENTO):
    # Escritura atómica: primero a un tmp y luego reemplazo.
    tmp = ruta + ".tmp"
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    os.replace(tmp, ruta)


def listar_fuentes(ruta=RUTA_CONOCIMIENTO) -> list:
    """Resumen de documentos subidos: [{fuente, fragmentos}]. No cuenta el
    conocimiento base (las entradas sin 'fuente')."""
    data = _cargar(ruta)
    cont = {}
    for e in data.get("entradas", []):
        fu = e.get("fuente")
        if fu:
            cont[fu] = cont.get(fu, 0) + 1
    return [{"fuente": k, "fragmentos": v} for k, v in sorted(cont.items())]


def eliminar_fuente(fuente: str, ruta=RUTA_CONOCIMIENTO) -> int:
    """Borra todos los fragmentos de un documento. Devuelve cuántos sacó.
    Nunca toca el conocimiento base (entradas sin 'fuente')."""
    data = _cargar(ruta)
    antes = data.get("entradas", [])
    quedan = [e for e in antes if e.get("fuente") != fuente]
    data["entradas"] = quedan
    _guardar(data, ruta)
    return len(antes) - len(quedan)


def ingestar(nombre: str, texto: str, ruta=RUTA_CONOCIMIENTO) -> dict:
    """Trocea 'texto' y agrega los fragmentos a conocimiento.json bajo la
    'fuente' = nombre del documento. Si la fuente ya existía, la REEMPLAZA.
    Devuelve {fuente, fragmentos}."""
    fuente = os.path.basename(nombre or "documento")
    slug = _slug(fuente)
    fragmentos = trocear(texto)
    if not fragmentos:
        raise ValueError("No se pudo extraer texto útil del documento.")

    data = _cargar(ruta)
    # Sacamos una versión anterior de la misma fuente (re-ingesta limpia).
    data["entradas"] = [e for e in data.get("entradas", []) if e.get("fuente") != fuente]

    tema = os.path.splitext(fuente)[0]
    for i, frag in enumerate(fragmentos, 1):
        data["entradas"].append({
            "id": f"doc_{slug}_{i}",
            "tema": f"{tema} (documento)",
            "claves": tema,
            "texto": frag,
            "fuente": fuente,
        })
    _guardar(data, ruta)
    return {"fuente": fuente, "fragmentos": len(fragmentos)}


# ---------- prueba rápida por línea de comandos ----------
if __name__ == "__main__":
    import sys
    if len(sys.argv) < 2:
        print("Uso: python ingestar.py <archivo.txt|.md|.pdf>")
        sys.exit(1)
    ruta_doc = sys.argv[1]
    txt = extraer_texto(ruta_doc)
    res = ingestar(os.path.basename(ruta_doc), txt)
    print(f"OK: {res['fuente']} -> {res['fragmentos']} fragmentos.")
    print("Fuentes actuales:", listar_fuentes())
