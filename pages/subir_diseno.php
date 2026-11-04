<?php
require __DIR__ . '/../includes/proteger.php';
require_once __DIR__ . '/../includes/sesion_segura.php';   // deja las funciones csrf_* visibles para el editor
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';
require_once __DIR__ . '/../includes/svg_seguro.php';

$mensaje = "";
$tipo_alerta = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    csrf_validar();   // Anti-CSRF: verificamos el token antes de seguir.

    $nombre_diseno = trim($_POST['nombre_diseno'] ?? '');
    $tipo_objeto   = trim($_POST['tipo_objeto'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    $ancho   = (float)($_POST['ancho'] ?? 0);
    $largo   = (float)($_POST['largo'] ?? 0);
    $espesor = (float)($_POST['espesor'] ?? 0);

    if ($nombre_diseno === "" || $tipo_objeto === "") {
        $mensaje = "Completá el nombre y el tipo de objeto."; $tipo_alerta = "error";
    } elseif (!in_array($tipo_objeto, $TIPOS_OBJETO, true)) {
        $mensaje = "El tipo de objeto elegido no es válido."; $tipo_alerta = "error";
    } elseif ($ancho <= 0 || $largo <= 0 || $espesor <= 0) {
        $mensaje = "Las dimensiones tienen que ser mayores a 0."; $tipo_alerta = "error";
    } elseif ($ancho > $LIMITES['ancho_mm'] || $largo > $LIMITES['largo_mm'] || $espesor > $LIMITES['altura_mm']) {
        $mensaje = "Las dimensiones superan el área de trabajo disponible (máximo "
                 . $LIMITES['ancho_mm'] . " × " . $LIMITES['largo_mm'] . " × " . $LIMITES['altura_mm']
                 . " mm). Ajustá el diseño e intentá de nuevo."; $tipo_alerta = "error";
    } elseif (!isset($_FILES['archivo_svg']) || $_FILES['archivo_svg']['error'] !== UPLOAD_ERR_OK) {
        $mensaje = "Por favor, seleccioná un archivo SVG válido."; $tipo_alerta = "error";
    } elseif ($_FILES['archivo_svg']['size'] > 2 * 1024 * 1024) {
        // SEGURIDAD: tope de tamaño para evitar abuso de almacenamiento / DoS.
        $mensaje = "El archivo es demasiado grande (máximo 2 MB)."; $tipo_alerta = "error";
    } else {
        $ext = strtolower(pathinfo($_FILES['archivo_svg']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'svg') {
            $mensaje = "Únicamente se admiten archivos en formato .SVG."; $tipo_alerta = "error";
        } else {
            // Ruta EN DISCO (absoluta, relativa a este archivo) y ruta WEB
            // (relativa a la raíz) que es la que guardamos en la base.
            $carpeta_fs = __DIR__ . '/../disenos_svg/';
            if (!is_dir($carpeta_fs)) { mkdir($carpeta_fs, 0755, true); }
            $nombre_guardado = "diseno_" . $_SESSION["emprendedor_id"] . "_" . time() . ".svg";
            $ruta_fs  = $carpeta_fs . $nombre_guardado;        // dónde se escribe
            $ruta_web = "disenos_svg/" . $nombre_guardado;     // lo que va a la BD

            // SEGURIDAD: validamos que sea un SVG real y lo LIMPIAMOS de
            // scripts y manejadores de eventos ANTES de guardarlo. Nunca
            // escribimos en disco el archivo original crudo.
            $svg_limpio = false;
            if (is_uploaded_file($_FILES['archivo_svg']['tmp_name'])) {
                $svg_limpio = svg_validar_y_limpiar($_FILES['archivo_svg']['tmp_name']);
            }

            if ($svg_limpio === false) {
                $mensaje = "El archivo no es un SVG válido o fue rechazado por seguridad."; $tipo_alerta = "error";
            } elseif (file_put_contents($ruta_fs, $svg_limpio) === false) {
                $mensaje = "No se pudo guardar el archivo en el servidor."; $tipo_alerta = "error";
            } else {
                $sql = "INSERT INTO disenos (emprendedor_id, nombre, archivo_svg, tipo_objeto, ancho_mm, largo_mm, espesor_mm, observaciones)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $_SESSION["emprendedor_id"], $nombre_diseno, $ruta_web, $tipo_objeto,
                        $ancho, $largo, $espesor, ($observaciones === "" ? null : $observaciones)
                    ]);
                    $mensaje = "¡Diseño <strong>" . htmlspecialchars($nombre_diseno) . "</strong> recibido! Queda en revisión para su aprobación.";
                    $tipo_alerta = "success";
                } catch (PDOException $e) {
                    $mensaje = "Error al guardar el diseño en la base de datos."; $tipo_alerta = "error";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Diseño SVG - TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <?php $pagina_activa = "idea"; include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>¿Tenés una idea? Cargar Vector SVG 🎨</h1>
            <p>Cargá tu archivo vectorial SVG para evaluar el mecanizado de una matriz de moldeo a medida.</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_alerta === 'success' ? 'success' : 'error'; ?> fade-in">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="order-grid">
            <form action="<?php echo BASE_URL; ?>/pages/subir_diseno.php" method="POST" enctype="multipart/form-data" class="order-form-container">
                <?php csrf_campo(); ?>
                <div class="form-group">
                    <label>Nombre del Proyecto / Pieza</label>
                    <input type="text" name="nombre_diseno" placeholder="Ej: Llavero institucional v1" required>
                </div>
                <div class="form-group">
                    <label>Tipo de Objeto (matriz)</label>
                    <select name="tipo_objeto" required>
                        <option value="" disabled selected>-- Elegí el tipo --</option>
                        <?php foreach ($TIPOS_OBJETO as $clave): ?>
                            <option value="<?php echo $clave; ?>"><?php echo $NOMBRE_LINDO[$clave]; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Archivo Vectorial (.SVG únicamente)</label>
                    <div class="file-dropzone">
                        <input type="file" name="archivo_svg" id="archivoSvg" accept=".svg,image/svg+xml" required>
                        <div class="dropzone-text">
                            <span style="font-size: 2rem;">📄</span>
                            <p>Haz clic o arrastrá tu archivo <strong>.SVG</strong> aquí</p>
                            <small>Formato exclusivo soportado: SVG 1.1 (Vectores planos)</small>
                        </div>
                    </div>
                </div>
                <div id="svgPreviewContainer" class="card" style="display: none; background: #ffffff; text-align: center; border: 1px dashed var(--primary);">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
                        <label style="margin:0; color: var(--primary);">Vista previa del vector</label>
                        <div class="preview-toggle" role="group" aria-label="Modo de vista previa">
                            <button type="button" id="btnPrev2D" class="btn-toggle activo">2D</button>
                            <button type="button" id="btnPrev3D" class="btn-toggle">3D ✨</button>
                        </div>
                    </div>
                    <div id="svgPreview" style="max-height: 200px; display: flex; justify-content: center; align-items: center;"></div>
                    <div id="svg3dMount" style="display:none; height:240px; cursor:grab;"></div>
                    <div id="dim3dLabel" style="display:none; font-weight:700; color:var(--primary); font-size:.9rem; margin-top:4px;"></div>
                    <small id="prev3dHint" style="display:none; color:var(--text-muted);">Arrastrá para rotar · rueda para acercar · cambiá las medidas abajo y la pieza se ajusta en vivo</small>
                </div>
                <div class="form-group">
                    <label>Dimensiones Estimadas del Molde (en mm)</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                        <div><small>Ancho (mm)</small><input type="number" id="dimAncho" name="ancho" placeholder="Ej: 60" min="1" step="0.1" required></div>
                        <div><small>Largo (mm)</small><input type="number" id="dimLargo" name="largo" placeholder="Ej: 40" min="1" step="0.1" required></div>
                        <div><small>Espesor (mm)</small><input type="number" id="dimEspesor" name="espesor" placeholder="Ej: 8" min="1" step="0.1" required></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Observaciones (Opcional)</label>
                    <input type="text" name="observaciones" placeholder="Ej: acabado liso en caras exteriores.">
                </div>
                <button type="submit" class="btn btn-block">Enviar Vector SVG para Evaluación →</button>
            </form>

            <div class="card summary-card">
                <h3>Requisitos del Vector SVG</h3>
                <hr class="divider">
                <ul class="svg-requirements-list">
                    <li>✔ <strong>Formato único:</strong> Debe ser un archivo estricto <code>.svg</code>.</li>
                    <li>✔ <strong>Tamaño máximo:</strong> 2 MB por archivo.</li>
                    <li>✔ <strong>Trazos cerrados:</strong> Geometrías cerradas (sin líneas sueltas).</li>
                    <li>✔ <strong>Escala 1:1:</strong> Diseñá en milímetros con las medidas reales.</li>
                    <li>✔ <strong>Dimensiones máximas:</strong> <?php echo $LIMITES['ancho_mm']; ?> × <?php echo $LIMITES['largo_mm']; ?> × <?php echo $LIMITES['altura_mm']; ?> mm (ancho × largo × alto).</li>
                    <li>✔ <strong>Sin degradados:</strong> Colores sólidos para definir caras o profundidades.</li>
                </ul>
                <div class="info-box" style="margin-top: 24px;">
                    ⚙️ <strong>Proceso Técnico:</strong> Convertimos tu vector SVG en trayectorias de herramienta para maquinar la matriz usada en la celda térmica.
                </div>
            </div>
        </div>
    </main>

    <script nonce="<?php echo csp_nonce(); ?>">
    let ultimoSVG = null;      // último SVG leído, para la vista 3D
    let modo3dActivo = false;  // si estamos viendo el 3D (para actualizar en vivo)

    // Lee las medidas del molde desde los inputs (en mm).
    function leerDims() {
        const n = id => parseFloat(document.getElementById(id).value) || 0;
        return { ancho: n('dimAncho'), largo: n('dimLargo'), espesor: n('dimEspesor') };
    }
    function pintarEtiquetaDims(d) {
        const lbl = document.getElementById('dim3dLabel');
        if (d.ancho && d.largo && d.espesor) {
            lbl.textContent = d.ancho + ' × ' + d.largo + ' × ' + d.espesor + ' mm';
            lbl.style.display = 'block';
        } else {
            lbl.textContent = 'Ingresá ancho, largo y espesor para ver la pieza a medida';
            lbl.style.display = 'block';
        }
    }

    function verModo(modo) {
        const p2 = document.getElementById('svgPreview');
        const m3 = document.getElementById('svg3dMount');
        const hint = document.getElementById('prev3dHint');
        const lbl = document.getElementById('dim3dLabel');
        const b2 = document.getElementById('btnPrev2D');
        const b3 = document.getElementById('btnPrev3D');
        if (modo === '3d') {
            modo3dActivo = true;
            p2.style.display = 'none'; m3.style.display = 'block'; hint.style.display = 'block';
            b3.classList.add('activo'); b2.classList.remove('activo');
            const dims = leerDims();
            pintarEtiquetaDims(dims);
            if (window.TP3D && ultimoSVG) {
                window.TP3D.render(ultimoSVG, m3, dims);
            } else if (ultimoSVG) {
                m3.innerHTML = '<p style="color:var(--text-muted); font-size:.85rem; padding:16px; margin:0;">Cargando motor 3D…</p>';
                setTimeout(function () { if (window.TP3D) window.TP3D.render(ultimoSVG, m3, leerDims()); }, 500);
            }
        } else {
            modo3dActivo = false;
            if (window.TP3D) window.TP3D.limpiar();
            m3.style.display = 'none'; hint.style.display = 'none'; lbl.style.display = 'none'; p2.style.display = 'flex';
            b2.classList.add('activo'); b3.classList.remove('activo');
        }
    }

    // En vivo: al cambiar una medida, si estamos en 3D, reescalamos la pieza.
    function onCambioDim() {
        if (!modo3dActivo) return;
        const dims = leerDims();
        pintarEtiquetaDims(dims);
        if (window.TP3D) window.TP3D.update(dims);
    }

    function previsualizarSVG(event) {
        const file = event.target.files[0];
        const container = document.getElementById('svgPreviewContainer');
        const preview = document.getElementById('svgPreview');
        preview.innerHTML = '';
        if (!file) { container.style.display = 'none'; return; }

        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (ext !== 'svg') {
            alert("Error: Únicamente podés cargar archivos con extensión .svg");
            event.target.value = ""; container.style.display = 'none'; return;
        }

        // Previsualizamos leyendo el SVG como TEXTO y mostrándolo en un <img>
        // con una URL "data:image/svg+xml". Usamos data: (no blob:) porque la
        // CSP del sitio permite data: y así NO hace falta relajar la política.
        // Renderiza cualquier SVG válido (incluso con declaración XML/DOCTYPE),
        // respeta la proporción y es seguro (como imagen no ejecuta scripts).
        const reader = new FileReader();
        reader.onload = function (e) {
            const svgText = e.target.result;
            const img = document.createElement('img');
            img.alt = 'Vista previa de ' + file.name;
            img.style.maxWidth = '100%';
            img.style.maxHeight = '180px';
            img.style.objectFit = 'contain';
            img.onerror = function () {
                preview.innerHTML = '<p style="color:var(--danger); font-size:.85rem; margin:0;">No se pudo generar la vista previa de este archivo, pero podés intentar subirlo igual.</p>';
            };
            img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgText);
            preview.appendChild(img);
            container.style.display = 'block';
            ultimoSVG = svgText;     // disponible para la vista 3D
            verModo('2d');           // cada archivo nuevo arranca en 2D
        };
        reader.onerror = function () {
            preview.innerHTML = '<p style="color:var(--danger); font-size:.85rem; margin:0;">No se pudo leer el archivo.</p>';
            container.style.display = 'block';
        };
        reader.readAsText(file);
    }
    document.getElementById('archivoSvg').addEventListener('change', previsualizarSVG);
    document.getElementById('btnPrev2D').addEventListener('click', function () { verModo('2d'); });
    document.getElementById('btnPrev3D').addEventListener('click', function () { verModo('3d'); });
    ['dimAncho', 'dimLargo', 'dimEspesor'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', onCambioDim);
    });
    </script>
    <script type="module" src="<?php echo BASE_URL; ?>/assets/js/preview3d.js"></script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>