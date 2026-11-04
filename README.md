# TermoPlast — V4.4

## Novedades de la V4.4 — cierre de la transición a IA

Completa el chatbot híbrido de V4.3 con lo que faltaba para que sea IA de verdad y esté listo para la defensa:

- **Memoria de conversación para el LLM**: el chat ahora pasa los últimos turnos al
  modelo, así sigue el hilo en preguntas de seguimiento ("¿y cuánto tardan?"). Los
  datos reales (pedidos, impacto) siguen siendo deterministas.
- **Autodiagnóstico** (`chatbot/probar_llm.py`): en un comando verificás que tu
  backend real (nube con API key u Ollama) funciona, con una prueba de conexión y
  preguntas de ejemplo. Ideal para chequear antes de la defensa.
- **RAG más preciso**: se agregaron sinónimos/claves a la base de conocimiento; la
  recuperación pasó a acertar 9/9 en las pruebas (antes fallaba varias).
- **Timeout correcto para el LLM**: el puente PHP esperaba solo 3 s (un LLM de nube
  tarda más y se cortaba). Ahora conecta rápido pero da hasta 20 s a la generación.
- **Indicador "escribiendo…"** animado en el widget mientras el asistente responde.

> Verificá tu backend con `python chatbot/probar_llm.py`. Cómo activar la nube
> (API key gratis) u Ollama local: `docs/Chatbot_LLM_NLP.md`.

> Al actualizar: reemplazá la carpeta, Ctrl+F5 y reiniciá el servicio del chatbot
> (`python app.py`). El esquema SQL no cambió.

---

## Novedades de la V4.3 — chatbot híbrido (NLP + LLM)

El chatbot da el salto a **IA conversacional**, manteniéndose **confiable** y
**acotado a TermoPlast**. Ahora combina tres capas:

1. **Clasificador** (TF-IDF + Regresión Logística) — rápido y offline; rutea la consulta.
2. **Recuperación semántica (RAG)** — busca los hechos más parecidos en una base de
   conocimiento (`chatbot/conocimiento.json`) por similitud coseno. **Sin descargar
   modelos** (funciona offline). Fundamenta la respuesta y ayuda a detectar temas ajenos.
3. **LLM generativo (opcional y pluggable)** — redacta en lenguaje natural **a partir
   del contexto recuperado**, con un *prompt* que lo limita a TermoPlast. Backends:
   `extractivo` (offline, por defecto), `openai` (nube compatible: OpenAI/Groq/…),
   `ollama` (local). Config en `chatbot/llm_config.json`.

**Garantía anti-alucinación:** las preguntas con **datos reales** (estado de pedido,
impacto, resumen de cuenta, estimaciones) las resuelve el PHP de forma **determinista**
contra la base; el LLM solo redacta respuestas **informativas** y siempre fundamentadas.
Si el LLM no está o falla, el chat **cae con elegancia** al modo extractivo y sigue
respondiendo.

> Cómo activar la nube con una API key **gratis** (Groq) o un LLM **local** (Ollama):
> ver `docs/Chatbot_LLM_NLP.md`. Por defecto arranca en `extractivo` (sin configurar nada).

> Al actualizar: reemplazá la carpeta y hacé **Ctrl+F5**. Reiniciá el servicio del
> chatbot (`python app.py`). El esquema SQL no cambió.

---

## Novedades de la V4.2 — correcciones de uso

Versión de correcciones sobre V4.1 (misma base, mismo esquema SQL).

**1) Visor de diseños SVG (antes daba 404).** El enlace "Ver SVG" apuntaba
directo al archivo: si el archivo no estaba (p. ej. en los datos de demo), se
rompía con **404**, y además cualquiera podía abrir el SVG de otro adivinando la
ruta. Ahora hay un **visor** (`pages/ver_diseno.php?id=…`) que:
- exige sesión y controla el acceso (el emprendedor ve **solo lo suyo**; el admin, todo),
- valida la ruta (anti *path traversal*) y **re-sanea** el SVG antes de mostrarlo,
- si falta el archivo, muestra un **aviso amable** en vez de un 404.
Se incluye además un SVG de muestra (`disenos_svg/demo.svg`) para que los datos
de demo tengan algo que mostrar.

**2) Chatbot acotado a TermoPlast.** Antes, preguntas ajenas (p. ej. *"cómo puedo
programar"*) recibían una respuesta forzada del sistema. Se agregó la intención
**`fuera_de_alcance`** (203 frases off-topic): ahora esas consultas reciben un
aviso acotado ("solo puedo ayudarte con TermoPlast…") **sin** botón de acción. El
clasificador pasó a **34 intenciones / ~6.296 frases**; verificado que las
consultas legítimas se siguen respondiendo igual (sin regresión).

**3) Smoke tests de fin de sesión** (ya versionados en V4.1, `tools/smoke_test.php`):
se reforzaron con un chequeo del visor de SVG. Verifican en pocos minutos que todo
responde y no hay errores de PHP.

> Al actualizar: reemplazá la carpeta y hacé **Ctrl+F5**. El esquema SQL no cambió.
> Si usás los datos de demo, reimportá `sql/7_datos_demo.sql` (ahora apunta al SVG de muestra).

---

## Novedades de la V4.1 — identidad turquesa + "forma mockup" en todos los datos

V4.1 es una versión de refinamiento visual sobre la V4.0 (misma base, mismo
esquema SQL). Dos cambios grandes:

**1) Nueva identidad turquesa/menta en TODA la app** (público, emprendedor y
panel de administración), en claro y oscuro. Se cambió la paleta en los *tokens*
del sistema de diseño, así que se propaga a todas las pantallas. Las **tablas de
datos mantienen su misma forma** (cabecera, filas, zebra) — solo cambió la
paleta, la estructura no se tocó.

**2) "Forma mockup" aplicada a TODOS nuestros datos** (no solo al dashboard): los
números ahora se muestran como en el mockup de referencia — *stat-tiles* con
ícono circular y variación mensual (+/-), listas tipo **embudo** para el flujo de
producción, **anillos (gauges)** de porcentaje, y una composición de tarjetas en
grilla de 12 columnas. Esto cubre:
- **Dashboard del admin** (`admin/dashboard.php`): resumen con deltas, flujo de
  producción, donut por estado, área de kg por mes, gauges de aprobación y
  finalizados, top emprendedores, materiales y CO₂.
- **Inicio del emprendedor** (`pages/panel.php`): resumen de impacto con deltas
  reales mes a mes, *sparkline* de kg y anillo de progreso hacia la meta de 50 kg.
- **Impacto del emprendedor** (`pages/impacto.php`): stat-tiles, anillo de meta,
  doughnut de piezas por tipo y área de kg por mes.

**Más animaciones de interacción** en todos los componentes (tarjetas que se
elevan con borde turquesa, íconos que reaccionan al hover, botones con onda, menú
con subrayado animado, contadores *count-up* y aparición al scroll). Todo respeta
`prefers-reduced-motion`.

### Corrección importante (gráficos en blanco)
Al pasar a la forma mockup, los **canvas de Chart.js quedaban en blanco**. La
causa era reemplazar el objeto completo `Chart.defaults.animation = {…}`, lo que
rompe la estructura interna de Chart.js 4 (`this._fn is not a function`) y aborta
el dibujo. **Solución:** mutar las subpropiedades
(`Chart.defaults.animation.duration` / `.easing`) en vez de reasignar el objeto.
Aplicado en `dashboard.php`, `impacto.php` y `panel.php`.

### Pulido V4.1 — auditoría integral
Se revisó toda la app (todas las vistas, 3 roles, claro y oscuro, escritorio y
celular) y se corrigieron los defectos encontrados:
- **`crear_admin.php` seguro y con identidad**: antes era una página sin estilo
  (verde, sin tema) y, peor, **reseteaba la contraseña del admin en cada visita**
  (cualquiera en la red podía pisarla). Ahora solo crea el **primer** admin cuando
  no existe ninguno; si ya hay admin, queda **inerte** (no toca nada) y muestra un
  aviso con la estética turquesa (claro/oscuro).
- **Responsive de la grilla mockup**: en celular el dashboard y la vista de
  impacto se desbordaban horizontalmente; se agregó el apilado a una columna por
  debajo de 880px (las tarjetas pasan a ancho completo). Sin scroll horizontal.
- **Tokens de color**: se eliminaron referencias a un token inexistente
  (`--border-color` → `--border`); ahora **todas** las variables `var(--…)` del
  proyecto resuelven a un token definido.
- Verificado: *lint* de todo el PHP, **0 warnings/notices** en las páginas,
  exportación CSV y PDF válidas, página destino del QR OK, **chatbot** de punta a
  punta (33 intenciones, datos reales, caída elegante si el servicio Python no
  está), y todos los `canvas` dibujando en claro y oscuro.

### Datos de demostración (opcional)
Para que los gráficos luzcan con tendencias reales en la defensa, se incluye
`sql/7_datos_demo.sql`: carga diseños aprobados y **~34 pedidos repartidos en 8
meses** (varios materiales, tipos y estados) y pone nombres distintos a los
emprendedores para que el ranking se lea bien. Es **opcional** e **idempotente**
(se puede volver a ejecutar sin duplicar). Para cargarlo:
`mysql -u root termoplast < sql/7_datos_demo.sql` (o importándolo desde phpMyAdmin).

> Al actualizar: reemplazá la carpeta y hacé **Ctrl+F5**. El esquema SQL no cambió.

---

## Novedades de la V4.0

- **Chatbot a ~6.000 frases** (6.093) y **33 intenciones** (+8 nuevas: tipos de
  piezas, cantidad mínima, aportar tapitas, formato de archivo, envío, calidad,
  sustentabilidad, colores). Más lógica: **memoria del último pedido** (podés
  preguntar "¿y cuándo estará?" sin repetir el número) y **tiempo de taller
  estimado** en la estimación de material. Exactitud 98,5% (validación cruzada).
- **Interfaz más animada y fluida**: números con **contadores animados**
  (count-up), **aparición al hacer scroll** (reveal), y micro-interacciones en
  tarjetas, botones, menú y barras de progreso. Todo respeta `prefers-reduced-motion`.
- **Datos del emprendedor más atractivos**: la página de **Impacto** ahora trae
  **gráficos** (piezas por tipo y plástico reciclado por mes) y contadores; el
  dashboard del admin también usa contadores.
- **QR**: `URL_PUBLICA` queda fijada a la IP de red (`http://192.168.1.66/TermoPlast`).

> Al actualizar: reemplazá la carpeta y hacé **Ctrl+F5**. El esquema SQL no cambió
> respecto de V3.0 (si venís de antes, importá igual `sql/1..6`).

---

## Novedades de la V3.0

- **QR robusto**: el QR ya no apunta a `localhost`. El sistema detecta la **IP de
  red real** descartando adaptadores virtuales (VirtualBox/VMware/Docker/WSL),
  muestra en pantalla **la URL a la que apunta** y suma una página de
  **Diagnóstico de QR** (en Seguimiento) que lista las IPs candidatas y la guía de
  Firewall/Apache. También podés fijar la dirección con `URL_PUBLICA` en
  `config/catalogo.php`.
- **Calidad visual x2**: sistema de diseño elevado (acento "calor del
  termoformado", profundidad, tipografía y jerarquía) coherente en **claro y
  oscuro** y en **todo el panel de administración** (dashboard, ABM, panel).
- **Chatbot a ~4.600 frases** (25 intenciones, +5 nuevas: horarios, modificar
  pedido, registro, seguridad de cuenta) con **slot-filling multi-turno** en la
  estimación (podés decir "estimación de llaveros" y después solo "20").
- **Documentación**: resumen de proyecto (por versión mayor), objetivos para la
  próxima sesión, **metodología ágil** (backlog + estimación + sprints + tablero)
  y la **documentación mínima** (investigación, usuarios, benchmark, modelo de
  datos, arquitectura, manual de usuario y manual técnico), todo en `docs/`.
- **Fix de codificación**: las semillas con tildes/ñ se importan con
  `SET NAMES utf8mb4` (los gráficos ya no muestran "DecoraciÃ³n").

> Al actualizar: reemplazá la carpeta e importá `sql/6_requisitos_minimos.sql`
> (si ya lo tenías, reimportá para tomar el `SET NAMES utf8mb4`). Hacé **Ctrl+F5**.

---

## Corrección v2.1.1 — QR escaneables desde otro celular

Antes el QR codificaba `http://localhost/...` y, al escanearlo desde otro
teléfono, "localhost" apuntaba al propio teléfono → *conexión rechazada*. Ahora:

- El QR usa una **URL pública** del sistema. Si entrás por `localhost`, el
  sistema **autodetecta la IP de red** de la PC servidor y la usa en el QR
  (ej: `http://192.168.0.15/TermoPlast/...`).
- Podés fijarla a mano (más estable) en `config/catalogo.php`:
  `define('URL_PUBLICA', 'http://TU_IP/TermoPlast');` (tu IP la ves con
  `ipconfig` → "Dirección IPv4").
- Si aun así quedara en `localhost`, Seguimiento muestra un **aviso** explicando
  cómo habilitarlo.

> Para escanear desde otro celular: el celular y la PC deben estar en la **misma
> red Wi-Fi**, y el **Firewall de Windows** debe permitir Apache (puerto 80).

---

# TermoPlast — v2.1

## Novedades de la v2.1 — requisitos mínimos del proyecto completos

Esta versión cierra los **requisitos mínimos** de la consigna. Resumen de cómo
los cumple (todo probado contra una base MariaDB real):

**1) Modelo de datos.** 13 entidades, FKs, normalización 3FN, **relación N-N**
(`disenos ↔ etiquetas` vía `diseno_etiquetas`), **tablas de historial/auditoría**
(`pedidos_historial`, `disenos_historial`, `eventos_seguridad`), **2 triggers**
(`trg_pedidos_bi_codigo` autogenera el código del QR; `trg_disenos_au_historial`
registra cambios de estado de diseños) y **2 stored procedures**
(`sp_top_emprendedores`, `sp_pedidos_por_mes`). Entregables en `docs/`:
**DER** (PNG/SVG/PDF), **diccionario de datos** (HTML/PDF) y los **scripts SQL**
en `sql/` (importar `1` → `6`).

**2) ABM (×5, completos).** Un motor genérico (`admin/abm.php`) da **Alta,
Modificación, Baja lógica, Listado, Búsqueda y Filtros** a: Materiales,
Etiquetas, Puntos de retiro, Emprendedores y Usuarios del sistema.

**3) Autenticación + 3 roles.** Registro, login y recuperación de contraseña
(ya existían) + **tres roles**: Administrador y Operador (tabla
`administradores.rol`) y Usuario básico (el emprendedor). El menú y las secciones
**se muestran u ocultan según el rol** (p. ej. "Usuarios" y "Seguridad" son solo
para administrador).

**4) Dashboard.** `admin/dashboard.php` con **4 indicadores** y **4 gráficos**
(evolución mensual, pedidos por estado, top emprendedores, diseños por categoría)
usando **Chart.js embebido localmente** (respeta la CSP).

**5) Exportación.** `admin/exportar.php` baja listados en **CSV** (compatible con
Excel) y **PDF** (generador propio, sin librerías externas).

**6) QR.** Cada pedido tiene un **código único autogenerado** (por trigger). En
Seguimiento se dibuja un **QR** (librería local) que abre la **ficha pública**
`pages/ficha_pedido.php?c=CÓDIGO` — una URL del sistema para ver el estado sin
iniciar sesión.

**7) IA.** El **chatbot** (clasificador de intenciones TF-IDF + regresión
logística) cumple la integración de IA (clasificación de texto, no generación de
código).

**8) Mapas / Geolocalización.** `pages/mapa.php` con **Leaflet embebido** muestra
los puntos de retiro (lat/lng desde la base). La CSP se amplía **solo en esa
página** para permitir los tiles de OpenStreetMap.

**9) Interfaz y UX.** 19 pantallas implementadas y navegables (mínimo: 5). En
`docs/` está el **mapa de navegación** (PNG/PDF) y el **inventario de pantallas**
(`UX_Pantallas_y_Navegacion`), listo para reproducir el prototipo en Figma.

> **Importante al actualizar:** reemplazá la carpeta e **importá el nuevo script
> `sql/6_requisitos_minimos.sql`** (después de 1→5). Hacé **Ctrl+F5** la primera
> vez para recargar CSS/JS. Usuario operador de ejemplo: `operador` / `operador123`.

---

# TermoPlast — v2.0

## Novedades de la v2.0 — el asistente pasa a ser una IA de verdad

El chatbot deja de ser un "contestador de FAQ" y pasa a **razonar, estimar y
accionar** dentro de la app. Tres mejoras concretas, todas defendibles:

- **Más conocimiento (clasificador reentrenado)**: el dataset creció de ~1.671 a
  **2.983 frases** repartidas en **21 intenciones** (sin frases repetidas y sin
  frases ambiguas entre dos temas). En validación cruzada (5 particiones) el
  modelo acierta la intención el **98,5 %** de las veces, y en frases nuevas que
  nunca vio acertó 14/14 en la prueba. Las intenciones nuevas son:
  - `estimación` → razonamiento con números (ver abajo),
  - `costos` → explica que no se paga con plata sino con tapitas,
  - `afirmar` / `negar` → entiende un "sí/dale" o un "no" sueltos para seguir un flujo.

- **Razonamiento con datos reales — estimación de material**: si le preguntás
  *"¿cuántas tapitas para 20 llaveros?"*, el asistente **extrae la pieza y la
  cantidad** del mensaje y calcula, con **las mismas fórmulas del sistema**
  (catálogo + factor de CO₂ de la base), las **tapitas**, los **kg de plástico**
  y el **CO₂ evitado**. Si le falta un dato (la pieza o el número) lo pide. Es
  robusto: no confunde el "2" de *"co₂"* con una cantidad.

  > Ej.: *"20 llaveros"* → 85 g × 20 = **1.700 g** → **680 tapitas** · **1,7 kg**.

- **Acciona dentro de la app (botones de acceso directo)**: cuando la respuesta
  tiene un siguiente paso natural, el asistente ofrece un **botón** que te lleva
  a la vista justa (p. ej. *"Ir a Hacer Pedido →"*, *"Ver Seguimiento →"*,
  *"Subir un diseño →"*). Y **recuerda la oferta**: si después respondés *"sí"*,
  te vuelve a dar el acceso directo. Los botones los arma el servidor con una
  lista fija de rutas (`BASE_URL`), así que **respetan la CSP** y no hay riesgo
  de inyección.

> Técnico (para defender): el clasificador es TF-IDF (palabras 1–3 + caracteres
> 2–5) + Regresión Logística balanceada, servido por un microservicio Flask en
> `127.0.0.1:5001`; el puente PHP agrega el razonamiento (entidades, duda,
> contexto, estimación con la base) y decide la acción. El front dibuja el botón
> con `textContent`/`href` (nunca `innerHTML`).


## Novedades de la v1.9.1
- **3D en vivo según las medidas**: en «¿Tenés una idea?», al cambiar
  ancho/largo/espesor (mm) la pieza 3D se ajusta en tiempo real (la proporción
  y el espesor siguen tus números), así podés dimensionar la pieza con precisión.


## Novedades de la v1.9
- **Previsualización 3D del producto**: en «¿Tenés una idea?», botón **2D/3D**
  que extruye tu SVG en un sólido rotable (así se vería la pieza moldeada), con
  three.js **embebido localmente** (funciona offline y respeta la CSP).
- **Animación de tablas**: las filas entran escalonadas y el hover resalta con
  un acento lateral (se desactiva en impresión y con reduce-motion).
- **Nivel de estilos**: acentos degradados en KPIs y títulos de sección, y
  relieve sutil en los botones (identidad eco-moderno, claro y oscuro).


## Correcciones de la v1.8.1
- **Vista previa de SVG arreglada**: usaba una URL `blob:` que la CSP bloqueaba
  ("no se pudo cargar la vista previa"). Ahora usa `data:image/svg+xml`, que la
  CSP ya permite — renderiza cualquier SVG válido sin relajar la seguridad.
- **Tipografías locales**: la CSP bloqueaba el `@import` de Google Fonts (las
  letras Fraunces/Manrope no cargaban en la app real). Ahora están embebidas en
  `assets/fonts/` (woff2) — se ven siempre, **también sin internet**.



Plataforma web para fabricar piezas en plástico reciclado (HDPE/PP/PET) para
emprendedores, con **impacto socioambiental medible por pieza**, **carga de
diseño propio** del emprendedor y **trazabilidad de producción**.

Proyecto de tesis — 7° año, E.E.S.T. N°3 "Roberto Arlt". Autor: Mateo Porcel.

---

## Novedades de la v1.8

**Proyecto reorganizado por carpetas (según responsabilidad):**

```
TermoPlast/
├── index.php            ← página pública de entrada
├── config/              ← conexión a la base y catálogo (configuración)
├── includes/            ← código compartido: seguridad, sesión, header, footer, widget
├── auth/                ← login, registro, logout, recuperar/restablecer, 2FA
├── admin/               ← panel, auditoría y login de administrador
├── pages/               ← vistas del emprendedor (panel, pedido, seguimiento, etc.)
├── api/                 ← endpoint del chatbot (recibe los mensajes)
├── assets/
│   ├── css/             ← estilos.css
│   └── js/              ← tema.js, chatbot.js, password_fuerza.js
├── chatbot/             ← microservicio de IA en Python (+ instaladores Windows)
├── sql/                 ← los 5 scripts de base de datos (importar en orden)
├── docs/                ← PDFs de propuesta y presupuesto
├── disenos_svg/ · uploads/   ← archivos subidos (SVG)
└── .htaccess, README.md
```

Cómo se logró sin romper nada:
- Los `require`/`include` usan rutas basadas en `__DIR__` (no dependen de la
  carpeta de trabajo).
- Los enlaces y recursos usan una constante **`BASE_URL`** que se calcula sola
  (funciona igual en `localhost/TermoPlast/` o donde la pongas).
- `config/` e `includes/` tienen un `.htaccess` que **impide abrirlos por URL**
  (solo se incluyen desde el código).

Todo verificado: 31 PHP sin errores, cada `require` resuelve, y un smoke-test
con `php -S` confirmó que las páginas cargan con sus recursos correctos.

> Si venías de una versión anterior, reemplazá la carpeta completa. Hacé
> **Ctrl+F5** la primera vez para forzar la recarga del CSS/JS.

---

## Qué trae (resumen general)

**Seguridad**: contraseñas con `password_hash`, PDO preparado, CSRF en todos los
formularios, CSP con nonce, sanitización de SVG, bloqueo por fuerza bruta,
auditoría, 2FA con límite de intentos, recuperación de contraseña, logout por
POST+CSRF, cabeceras COOP/CORP/HSTS, `no-store` en páginas privadas, `.htaccess`
en raíz, subidas, `config/` e `includes/`, y límite de tamaño de SVG (2 MB).

**Interfaz**: diseño eco-moderno con tokens, modo oscuro, navegación responsive,
tablas en fichas en el celular, impresión de reportes, anti doble-submit.

**Trazabilidad**: historial de estados por pedido (emprendedor + admin).

**IA**: chatbot con clasificador de intenciones (**2983 frases, 21 intenciones**)
+ microservicio Flask + puente PHP, con **razonamiento** sobre entidades (nº de
pedido, pieza y cantidad para estimar material), manejo de **duda**, **contexto**
de la charla y **acciones** (botones que llevan a la vista correcta).

---

## Instalación (XAMPP)

1. Copiá la carpeta `TermoPlast` dentro de `xampp/htdocs/`.
2. Iniciá Apache y MySQL.
3. En phpMyAdmin importá, **en orden**, los archivos de `sql/`:
   `1_termoplast.sql` → `2_seguridad_db.sql` → `3_dosfa.sql` →
   `4_trazabilidad.sql` → `5_password_reset.sql` → `6_requisitos_minimos.sql`.
4. Revisá `config/conexion.php` por si hay que ajustar usuario/clave de MySQL.
5. Abrí `http://localhost/TermoPlast/`.

### Crear el administrador
1. Entrá **una sola vez** a `http://localhost/TermoPlast/admin/crear_admin.php`.
2. **Borrá `admin/crear_admin.php`** del servidor apenas lo uses.

### Chatbot (opcional)
En Windows, entrá a la carpeta `chatbot` y doble clic en
`instalar_windows.bat`; después `iniciar_servicio.bat`. (Ver
`chatbot/INSTALAR_WINDOWS.txt` para el detalle y el plan B.)

---

## Antes de subir a GitHub
- No subas `credenciales.txt`, los SVG de `disenos_svg/`/`uploads/`, ni la
  carpeta `chatbot/venv/`. Ya están en `.gitignore`.
- Borrá `admin/crear_admin.php` después de crear el admin.
- En un servidor real, verificá `AllowOverride All` para que los `.htaccess`
  se apliquen.
