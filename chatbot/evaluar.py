# ============================================================
#  evaluar.py — Mide qué tan bien aprende el modelo.
#  Usa el MISMO modelo que motor.py (construir_modelo), separa
#  75% entrenamiento / 25% prueba y reporta exactitud + matriz.
#  El resultado se muestra en pantalla y se guarda en
#  'evaluacion.txt' (útil como evidencia para la carpeta).
# ============================================================
import json, os
from motor import construir_modelo, normalizar
from sklearn.model_selection import train_test_split, cross_val_score, StratifiedKFold
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix

lineas = []
def linea(txt=""):
    print(txt)
    lineas.append(txt)

d = json.load(open(os.path.join(os.path.dirname(__file__), "intents.json"), encoding="utf-8"))
X, y = [], []
for intn, frases in d["intenciones"].items():
    for f in frases:
        X.append(normalizar(f)); y.append(intn)

# Validación cruzada (5 particiones) = estimación robusta.
cv = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)
scores = cross_val_score(construir_modelo(), X, y, cv=cv)
linea("=== EVALUACIÓN DEL CHATBOT TermoPlast ===")
linea(f"Ejemplos: {len(X)}  |  Intenciones: {len(set(y))}")
linea(f"EXACTITUD (validación cruzada 5-fold): {scores.mean():.1%}  (±{scores.std():.1%})")
linea("")

# Split único para ver el detalle por intención y la matriz.
Xtr, Xte, ytr, yte = train_test_split(X, y, test_size=0.25, random_state=42, stratify=y)
modelo = construir_modelo(); modelo.fit(Xtr, ytr); pred = modelo.predict(Xte)
linea(f"Detalle en un split de prueba ({len(Xte)} ejemplos), exactitud {accuracy_score(yte, pred):.1%}:")
linea(classification_report(yte, pred, zero_division=0))

linea("Matriz de confusión (filas = real, columnas = predicho):")
et = sorted(set(y)); cm = confusion_matrix(yte, pred, labels=et); w = max(len(e) for e in et)
linea(" " * (w + 1) + " ".join(f"{i:>3}" for i in range(len(et))))
for i, fila in enumerate(cm):
    linea(f"{et[i]:<{w}} " + " ".join(f"{v:>3}" for v in fila) + f"  = {et[i]}")

# Guardar el reporte a un archivo (evidencia para la carpeta).
ruta_txt = os.path.join(os.path.dirname(__file__), "evaluacion.txt")
with open(ruta_txt, "w", encoding="utf-8") as f:
    f.write("\n".join(lineas))
linea("")
linea(f"✔ Reporte guardado en: {ruta_txt}")

# Pausa para que la ventana NO se cierre al hacer doble clic en Windows.
try:
    input("\nPresioná ENTER para cerrar...")
except EOFError:
    pass
