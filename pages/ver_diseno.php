<?php
// ============================================================
//  ver_diseno.php — Visor seguro de diseños SVG.
//  Reemplaza el enlace directo al archivo (que daba 404 si el
//  archivo no estaba, y dejaba ver el SVG de cualquiera por la
//  ruta). Acá:
//   · se exige sesión (emprendedor o admin),
//   · el emprendedor SOLO ve sus propios diseños; el admin, todos,
//   · se valida la ruta (anti path traversal) y se RE-SANEA el SVG,
//   · si el archivo no está, se muestra un aviso amable (no un 404).
// ============================================================
require_once __DIR__ . '/../includes/sesion_segura.php';   // sesión + BASE_URL + RAIZ + CSP
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';                // $NOMBRE_LINDO, $ESTADOS_PEDIDO
require __DIR__ . '/../includes/svg_seguro.php';

$aid = $_SESSION['admin_id'] ?? null;
$eid = $_SESSION['emprendedor_id'] ?? null;
if (!$aid && !$eid) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}
$es_admin = (bool)$aid;
$volver = $es_admin ? BASE_URL . '/admin/admin_panel.php' : BASE_URL . '/pages/mis_disenos.php';

$id = (int)($_GET['id'] ?? 0);
$dis = null;
if ($id > 0) {
    if ($es_admin) {
        $st = $pdo->prepare("SELECT d.*, e.nombre AS emp_nombre FROM disenos d
                             LEFT JOIN emprendedores e ON e.id = d.emprendedor_id WHERE d.id = ?");
        $st->execute([$id]);
    } else {
        $st = $pdo->prepare("SELECT d.*, NULL AS emp_nombre FROM disenos d
                             WHERE d.id = ? AND d.emprendedor_id = ?");
        $st->execute([$id, $eid]);
    }
    $dis = $st->fetch();
}

// --- Resolver y validar el archivo (anti path traversal) ---
$svg_inline = null; $motivo = '';
if ($dis) {
    $rel  = (string)$dis['archivo_svg'];
    // Validamos la ruta como TEXTO (no con realpath, que falla si el archivo
    // no existe): debe estar directamente dentro de disenos_svg/, terminar en
    // .svg y no contener "..". Así distinguimos "ruta inválida" de "falta el archivo".
    $rutaValida = $rel !== '' && strpos($rel, '..') === false
                  && preg_match('#^disenos_svg/[^/\\\\]+\.svg$#i', $rel);
    $full = RAIZ . '/' . $rel;
    if ($rel === '') {
        $motivo = 'Este diseño no tiene un archivo asociado.';
    } elseif (!$rutaValida) {
        $motivo = 'La ruta del archivo no es válida.';
    } elseif (!is_file($full)) {
        $motivo = 'El archivo del diseño no está disponible en el servidor.';
    } else {
        $limpio = svg_validar_y_limpiar($full);          // re-saneo defensivo
        if ($limpio === false) {
            $motivo = 'El archivo no pudo validarse como un SVG seguro.';
        } else {
            // Quitamos el prólogo XML / DOCTYPE para incrustarlo inline en HTML.
            $svg_inline = preg_replace('/^\s*<\?xml.*?\?>\s*/is', '', $limpio);
            $svg_inline = preg_replace('/<!DOCTYPE.*?>/is', '', $svg_inline);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $dis ? 'Diseño: ' . htmlspecialchars($dis['nombre']) : 'Diseño no encontrado'; ?> — TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
    <script src="<?php echo BASE_URL; ?>/assets/js/tema.js" defer></script>
</head>
<body>
    <main class="main-content" style="max-width:760px;margin-inline:auto;">
        <div class="page-header">
            <h1>Visor de diseño 🔍</h1>
            <p><a href="<?php echo $volver; ?>">← Volver</a></p>
        </div>

        <?php if (!$dis): ?>
            <div class="card reveal">
                <div class="card-h">Diseño no encontrado</div>
                <p>No existe ese diseño o no tenés permiso para verlo.</p>
                <a class="btn" href="<?php echo $volver; ?>">Volver</a>
            </div>
        <?php else: ?>
            <div class="card reveal">
                <div class="rowh" style="flex-wrap:wrap;gap:8px;">
                    <div class="card-h" style="margin:0;"><?php echo htmlspecialchars($dis['nombre']); ?></div>
                    <?php
                      // Misma convención de badges que mis_disenos.php.
                      $cls = $dis['estado']==='aprobado' ? 'badge-finalizado'
                           : ($dis['estado']==='rechazado' ? 'badge-cancelado' : 'badge-calentamiento');
                      $estadoLindo = $ESTADOS_DISENO[$dis['estado']] ?? ucfirst(str_replace('_',' ',$dis['estado']));
                    ?>
                    <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($estadoLindo); ?></span>
                </div>
                <p style="color:var(--text-muted);font-size:.9rem;margin:.4rem 0 0;">
                    <?php echo htmlspecialchars($NOMBRE_LINDO[$dis['tipo_objeto']] ?? $dis['tipo_objeto']); ?>
                    · <?php echo $dis['ancho_mm']; ?> × <?php echo $dis['largo_mm']; ?> × <?php echo $dis['espesor_mm']; ?> mm
                    <?php if ($es_admin && !empty($dis['emp_nombre'])): ?> · <?php echo htmlspecialchars($dis['emp_nombre']); ?><?php endif; ?>
                </p>

                <div class="svg-visor">
                    <?php if ($svg_inline !== null): ?>
                        <?php echo $svg_inline; /* ya saneado por svg_validar_y_limpiar() */ ?>
                    <?php else: ?>
                        <div class="svg-visor-aviso">
                            <span style="font-size:2rem;">🗂️</span>
                            <p><?php echo htmlspecialchars($motivo); ?></p>
                            <small>Si el diseño es tuyo, podés volver a subirlo desde «¿Tenés una idea?».</small>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($dis['motivo_rechazo']) && $dis['estado']==='rechazado'): ?>
                    <p style="color:var(--danger);font-size:.88rem;margin-top:12px;"><strong>Motivo de rechazo:</strong> <?php echo htmlspecialchars($dis['motivo_rechazo']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
