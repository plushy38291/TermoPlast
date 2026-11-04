# ============================================================
#  motor.py — El "cerebro" del chatbot TermoPlast.
#  Clasifica la INTENCIÓN de una pregunta combinando:
#   - TF-IDF de palabras (1-2 gramas): capta el significado.
#   - TF-IDF de caracteres (3-5): tolera errores de tipeo.
#  Clasificador: Regresión Logística balanceada (da confianza).
# ============================================================
import json, os, unicodedata
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import Pipeline, FeatureUnion

RUTA = os.path.join(os.path.dirname(__file__), "intents.json")
RUTA_CONOCIMIENTO = os.path.join(os.path.dirname(__file__), "conocimiento.json")


def normalizar(t: str) -> str:
    # minúsculas + sin acentos: "cuándo" y "cuando" cuentan igual.
    t = (t or "").lower().strip()
    t = unicodedata.normalize("NFKD", t)
    return "".join(c for c in t if not unicodedata.combining(c))


def construir_modelo() -> Pipeline:
    # MISMO modelo que usa la evaluación, para que la métrica sea fiel.
    # Capacidad ampliada (~3x): más n-gramas y más features, para captar
    # frases más largas y más variantes de tipeo/jerga.
    caracteristicas = FeatureUnion([
        ("palabra",  TfidfVectorizer(analyzer="word",    ngram_range=(1, 3),
                                      sublinear_tf=True, max_features=30000)),
        ("caracter", TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5),
                                      sublinear_tf=True, max_features=60000)),
    ])
    return Pipeline([
        ("features", caracteristicas),
        ("clf", LogisticRegression(max_iter=5000, C=10.0, class_weight="balanced")),
    ])


import random


class MotorIntenciones:
    def __init__(self, ruta=RUTA):
        with open(ruta, encoding="utf-8") as f:
            self.datos = json.load(f)
        self.umbral = float(self.datos["config"].get("umbral_confianza", 0.40))
        # Banda de DUDA: si la mejor intención no llega a este nivel y hay una
        # segunda parecida, el chat pide aclaración en vez de arriesgar.
        self.umbral_duda = float(self.datos["config"].get("umbral_duda", 0.58))
        self.respuestas = self.datos.get("respuestas", {})
        self._entrenar()
        self._indexar_conocimiento()

    def _entrenar(self):
        X, y = [], []
        for intencion, frases in self.datos["intenciones"].items():
            for frase in frases:
                X.append(normalizar(frase)); y.append(intencion)
        self.modelo = construir_modelo()
        self.modelo.fit(X, y)
        self.clases = list(self.modelo.classes_)

    def _indexar_conocimiento(self):
        """Indexa la base de conocimiento para RECUPERACIÓN (RAG) por similitud.
        Usa TF-IDF (word + char) sin ningún modelo externo: funciona offline.
        El vector es L2-normalizado, así el producto punto = similitud coseno."""
        self.conocimiento = []
        try:
            with open(RUTA_CONOCIMIENTO, encoding="utf-8") as f:
                self.conocimiento = json.load(f).get("entradas", [])
        except (OSError, ValueError):
            self.conocimiento = []
        if not self.conocimiento:
            self.rag_vec = None
            self.rag_matriz = None
            return
        # Indexamos tema + CLAVES (sinónimos, con más peso al repetirlas) + texto.
        corpus = [normalizar(
            e.get("tema", "") + ". "
            + (e.get("claves", "") + " ") * 2
            + e.get("texto", "")
        ) for e in self.conocimiento]
        self.rag_vec = FeatureUnion([
            ("palabra",  TfidfVectorizer(analyzer="word",    ngram_range=(1, 2), sublinear_tf=True)),
            ("caracter", TfidfVectorizer(analyzer="char_wb", ngram_range=(3, 5), sublinear_tf=True)),
        ])
        self.rag_matriz = self.rag_vec.fit_transform(corpus)   # (n_docs, n_feats), L2-normalizado

    def recuperar(self, texto: str, k: int = 3, umbral: float = 0.08):
        """Devuelve [{id, tema, texto, score}] de los fragmentos más parecidos a la
        consulta, por similitud coseno. Lista vacía si nada supera el umbral
        (señal útil de que la pregunta puede estar FUERA del dominio)."""
        if self.rag_matriz is None:
            return []
        q = normalizar(texto)
        if not q:
            return []
        qv = self.rag_vec.transform([q])                 # (1, n_feats)
        sims = (self.rag_matriz @ qv.T).toarray().ravel()  # coseno (ambos L2)
        orden = np.argsort(-sims)[:k]
        out = []
        for i in orden:
            s = float(sims[i])
            if s < umbral:
                continue
            e = self.conocimiento[i]
            out.append({"id": e.get("id"), "tema": e.get("tema"), "texto": e.get("texto"), "score": round(s, 3)})
        return out

    def _top(self, texto: str, k: int = 3):
        """Lista [(intencion, prob), ...] ordenada de mayor a menor."""
        t = normalizar(texto)
        if not t:
            return []
        probas = self.modelo.predict_proba([t])[0]
        pares = sorted(zip(self.clases, map(float, probas)), key=lambda x: -x[1])
        return pares[:k]

    def analizar(self, texto: str):
        """Devuelve (intencion, confianza). Confianza baja -> 'fallback'."""
        top = self._top(texto, 1)
        if not top:
            return "fallback", 0.0
        intencion, confianza = top[0]
        if confianza < self.umbral:
            return "fallback", confianza
        return intencion, confianza

    def analizar_detalle(self, texto: str):
        """
        Versión con más info para 'razonar':
          - intencion, confianza
          - dudosa: True si la confianza está en la banda de duda
          - alternativas: top intenciones con su probabilidad
        """
        top = self._top(texto, 3)
        if not top:
            return {"intencion": "fallback", "confianza": 0.0, "dudosa": False, "alternativas": []}
        intencion, confianza = top[0]
        if confianza < self.umbral:
            return {"intencion": "fallback", "confianza": confianza, "dudosa": False,
                    "alternativas": [{"intencion": i, "prob": round(p, 3)} for i, p in top]}
        # ¿Está en zona de duda y la segunda opción es competitiva?
        dudosa = False
        if confianza < self.umbral_duda and len(top) > 1:
            segunda = top[1][1]
            if segunda >= confianza - 0.18:
                dudosa = True
        return {
            "intencion": intencion,
            "confianza": confianza,
            "dudosa": dudosa,
            "alternativas": [{"intencion": i, "prob": round(p, 3)} for i, p in top],
        }

    def respuesta_estatica(self, intencion: str):
        """Devuelve UNA respuesta (al azar si hay varias variantes)."""
        r = self.respuestas.get(intencion)
        if isinstance(r, list):
            return random.choice(r) if r else None
        return r


if __name__ == "__main__":
    m = MotorIntenciones()
    pruebas = [
        "hola buenas", "che cuando va a estar lista mi pieza?",
        "komo cargo un vetor",              # con errores de tipeo
        "de que plastico estan echas",      # con error
        "adonde lo voy a buscar",
        "cuanto co2 ahorre", "que medida maxima soporta",
        "quiero cancelar el pedido", "cuantos dias tarda",
        "que podes hacer", "gracias crack", "cual es la capital de francia",
    ]
    for p in pruebas:
        intn, c = m.analizar(p)
        print(f"[{c:.2f}] {intn:18s} <- {p}")
