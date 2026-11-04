<?php
// ============================================================
//  admin/conocimiento.php
//  Propuesta 1.1 — "Chatbot que procesa archivos cargados".
//  El administrador sube un documento (.txt/.md/.pdf) y su texto
//  se agrega a la base de conocimiento (RAG) del asistente, para
//  que pueda fundamentar respuestas con ese material.
//
//  No toca la base de datos: habla con el microservicio Flask
//  (mismo patrón cURL que api/chatbot_responder.php) que es quien
//  trocea, guarda en conocimiento.json y reindexa el RAG.
// ============================================================
require __DIR__ . '/../includes/proteger_admin.php';
require __DIR__ . '/../config/conexion.php';

// Solo el administrador gestiona el conocimiento del asistente.
requiere_rol('administrador');

define('CB_API', 'http://127.0.0.1:5001');

/** POST JSON a Flask. Devuelve [ok, datos|error, offline]. */
function cb_post_json(string $ruta, array $cuerpo): array {
    if (!function_exists('curl_init')) return [false, 'cURL no disponible en PHP.', false];
    $ch = curl_init(CB_API . $ruta);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($cuerpo),
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_errno($ch);
    curl_close($ch);
    if ($err || $resp === false) return [false, 'El servicio del asistente no está activo.', true];
    $j = json_decode($resp, true);
    if (!is_array($j)) return [false, 'Respuesta inválida del servicio.', false];
    return [!empty($j['ok']), $j, false];
}

$mensaje = ""; $tipo_alerta = ""; $offline = false;

// ---------- Acciones ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    // A) Subir archivo
    if (isset($_POST['subir']) && !empty($_FILES['archivo']['name'])) {
        $f = $_FILES['archivo'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $permitidas = ['txt', 'md', 'markdown', 'csv', 'pdf'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $mensaje = "No se pudo subir el archivo (error #{$f['error']})."; $tipo_alerta = "error";
        } elseif ($f['size'] > 8 * 1024 * 1024) {
            $mensaje = "El archivo supera los 8 MB."; $tipo_alerta = "error";
        } elseif (!in_array($ext, $permitidas, true)) {
            $mensaje = "Tipo no permitido (.$ext). Usá: txt, md, csv o pdf."; $tipo_alerta = "error";
        } else {
            // Enviamos el archivo a Flask por multipart (CURLFile).
            $ch = curl_init(CB_API . '/ingestar');
            $cfile = new CURLFile($f['tmp_name'], mime_content_type($f['tmp_name']) ?: 'application/octet-stream', basename($f['name']));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ['archivo' => $cfile],
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 60,
            ]);
            $resp = curl_exec($ch); $err = curl_errno($ch); curl_close($ch);
            if ($err || $resp === false) {
                $mensaje = "El servicio del asistente no está activo. Levantá el chatbot (python app.py) e intentá de nuevo.";
                $tipo_alerta = "error"; $offline = true;
            } else {
                $j = json_decode($resp, true);
                if (!empty($j['ok'])) {
                    registrar_evento($pdo, 'kb_subida', "Documento al RAG: {$j['fuente']} ({$j['fragmentos']} frag.)", (string)($_SESSION['admin_id'] ?? ''));
                    $mensaje = "Documento «{$j['fuente']}» agregado: {$j['fragmentos']} fragmentos indexados.";
                    $tipo_alerta = "success";
                } else {
                    $mensaje = "No se pudo procesar: " . htmlspecialchars($j['error'] ?? 'error desconocido');
                    $tipo_alerta = "error";
                }
            }
        }
    }

    // B) Pegar texto
    elseif (isset($_POST['pegar'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $texto  = trim($_POST['texto'] ?? '');
        if ($nombre === '' || $texto === '') {
            $mensaje = "Completá un nombre y el texto a agregar."; $tipo_alerta = "error";
        } else {
            if (!preg_match('/\.(txt|md)$/i', $nombre)) $nombre .= '.txt';
            [$ok, $datos, $off] = cb_post_json('/ingestar', ['nombre' => $nombre, 'texto' => $texto]);
            $offline = $off;
            if ($ok) {
                registrar_evento($pdo, 'kb_subida', "Texto al RAG: {$datos['fuente']} ({$datos['fragmentos']} frag.)", (string)($_SESSION['admin_id'] ?? ''));
                $mensaje = "Texto «{$datos['fuente']}» agregado: {$datos['fragmentos']} fragmentos."; $tipo_alerta = "success";
            } else {
                $mensaje = is_array($datos) ? ("No se pudo procesar: " . htmlspecialchars($datos['error'] ?? '')) : htmlspecialchars((string)$datos);
                $tipo_alerta = "error";
            }
        }
    }

    // C) Eliminar fuente
    elseif (isset($_POST['eliminar'])) {
        $fuente = trim($_POST['fuente'] ?? '');
        [$ok, $datos, $off] = cb_post_json('/eliminar_conocimiento', ['fuente' => $fuente]);
        $offline = $off;
        if ($ok) {
            registrar_evento($pdo, 'kb_borrado', "Quitó del RAG: $fuente ({$datos['eliminados']} frag.)", (string)($_SESSION['admin_id'] ?? ''));
            $mensaje = "Documento «" . htmlspecialchars($fuente) . "» eliminado del conocimiento."; $tipo_alerta = "success";
        } else {
            $mensaje = "No se pudo eliminar (¿servicio activo?)."; $tipo_alerta = "error";
        }
    }
}

// ---------- Estado actual ----------
$fuentes = []; $base = 0; $total = 0; $servicio_ok = false;
if (function_exists('curl_init')) {
    $ch = curl_init(CB_API . '/conocimiento');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 5]);
    $resp = curl_exec($ch); $err = curl_errno($ch); curl_close($ch);
    if (!$err && $resp !== false) {
        $j = json_decode($resp, true);
        if (!empty($j['ok'])) {
            $servicio_ok = true;
            $fuentes = $j['fuentes'] ?? [];
            $base = (int)($j['base'] ?? 0);
            $total = (int)($j['total'] ?? 0);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conocimiento del asistente - TermoPlast Admin</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <?php $pagina_activa = 'conocimiento'; include __DIR__ . '/../includes/admin_header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Conocimiento del asistente 📚</h1>
            <p>Subí documentos (texto o PDF) para que el asistente pueda responder con ese material. El texto se trocea y se suma al conocimiento que ya tiene; nunca reemplaza los datos reales de pedidos.</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_alerta === 'success' ? 'success' : 'error'; ?> fade-in">
                <?php echo ($tipo_alerta === 'success' ? '✅ ' : '⚠️ ') . $mensaje; ?>
            </div>
        <?php endif; ?>

        <?php if (!$servicio_ok): ?>
            <div class="alert alert-error">
                ⚠️ El servicio del asistente (Flask, puerto 5001) no responde. Para gestionar el conocimiento, levantá el chatbot: <code>python app.py</code> dentro de la carpeta <code>chatbot/</code>.
            </div>
        <?php endif; ?>

        <div class="order-grid">
            <!-- Subir archivo -->
            <form method="post" action="" class="card" enctype="multipart/form-data">
                <h3 style="margin-top:0;">Subir un documento</h3>
                <?php csrf_campo(); ?>
                <div class="form-group">
                    <label>Archivo (.txt, .md, .csv o .pdf — hasta 8 MB)</label>
                    <input type="file" name="archivo" accept=".txt,.md,.markdown,.csv,.pdf" required>
                </div>
                <p style="font-size:.82rem; color:var(--text-muted);">El PDF se procesa como texto. Si un PDF es escaneado (imagen), conviene subir su contenido como .txt.</p>
                <button type="submit" name="subir" class="btn btn-block" <?php echo $servicio_ok ? '' : 'disabled'; ?>>Subir y agregar al conocimiento</button>
            </form>

            <!-- Pegar texto -->
            <form method="post" action="" class="card">
                <h3 style="margin-top:0;">O pegar texto</h3>
                <?php csrf_campo(); ?>
                <div class="form-group">
                    <label>Nombre (para poder borrarlo luego)</label>
                    <input type="text" name="nombre" maxlength="120" placeholder="Ej.: Política de garantía">
                </div>
                <div class="form-group">
                    <label>Texto</label>
                    <textarea name="texto" rows="6" placeholder="Pegá acá el contenido que querés que el asistente conozca…"></textarea>
                </div>
                <button type="submit" name="pegar" class="btn btn-block" <?php echo $servicio_ok ? '' : 'disabled'; ?>>Agregar texto al conocimiento</button>
            </form>
        </div>

        <!-- Documentos cargados -->
        <div class="card" style="margin-top:28px;">
            <h3 style="margin-top:0;">Documentos cargados</h3>
            <p style="font-size:.9rem; color:var(--text-muted);">
                Conocimiento base del sistema: <strong><?php echo $base; ?></strong> entradas ·
                Total indexado: <strong><?php echo $total; ?></strong>.
            </p>
            <?php if (empty($fuentes)): ?>
                <p style="color:var(--text-muted);">Todavía no subiste documentos. El asistente usa solo el conocimiento base.</p>
            <?php else: ?>
                <table class="tabla">
                    <thead><tr><th>Documento</th><th>Fragmentos</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($fuentes as $fu): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fu['fuente']); ?></td>
                            <td><?php echo (int)$fu['fragmentos']; ?></td>
                            <td style="text-align:right;">
                                <form method="post" action="" onsubmit="return confirm('¿Quitar este documento del conocimiento del asistente?');" style="display:inline; margin:0; padding:0; background:none; border:none; box-shadow:none;">
                                    <?php csrf_campo(); ?>
                                    <input type="hidden" name="fuente" value="<?php echo htmlspecialchars($fu['fuente']); ?>">
                                    <button type="submit" name="eliminar" class="btn btn-secondary" style="padding:6px 12px;">Quitar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
