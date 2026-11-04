<?php
require __DIR__ . '/../includes/proteger.php';
require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';

// ------------------------------------------------------------
//  ELIMINAR UN DISEÑO
//  Solo el dueño, y SOLO si está 'rechazado'. Además borra el
//  archivo SVG del disco para no dejar basura.
// ------------------------------------------------------------
$mensaje = "";
$tipo_alerta = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['eliminar_diseno'])) {
    csrf_validar();
    $diseno_id = filter_input(INPUT_POST, 'diseno_id', FILTER_VALIDATE_INT);
    if ($diseno_id) {
        $q = $pdo->prepare(
            "SELECT archivo_svg FROM disenos
             WHERE id = ? AND emprendedor_id = ? AND estado = 'rechazado'"
        );
        $q->execute([$diseno_id, $_SESSION['emprendedor_id']]);
        $ruta = $q->fetchColumn();

        if ($ruta === false) {
            $mensaje = "No se pudo eliminar: el diseño no existe, no es tuyo o no está rechazado.";
            $tipo_alerta = "error";
        } else {
            $del = $pdo->prepare(
                "DELETE FROM disenos
                 WHERE id = ? AND emprendedor_id = ? AND estado = 'rechazado'"
            );
            $del->execute([$diseno_id, $_SESSION['emprendedor_id']]);
            if ($del->rowCount() > 0) {
                if (!empty($ruta) && is_file($ruta)) { @unlink($ruta); }
                $mensaje = "Diseño eliminado correctamente.";
                $tipo_alerta = "success";
            } else {
                $mensaje = "No se pudo eliminar el diseño.";
                $tipo_alerta = "error";
            }
        }
    }
}

$sql = "SELECT id, nombre, tipo_objeto, archivo_svg, ancho_mm, largo_mm, espesor_mm,
               estado, motivo_rechazo, fecha_subida
        FROM disenos WHERE emprendedor_id = ? ORDER BY fecha_subida DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['emprendedor_id']]);
$disenos = $stmt->fetchAll();

function claseBadgeDiseno($estado) {
    switch ($estado) {
        case 'aprobado':  return 'badge-finalizado';
        case 'rechazado': return 'badge-cancelado';
        default:          return 'badge-calentamiento';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Diseños - TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <?php $pagina_activa = "disenos"; include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Mis Diseños 🎨</h1>
            <p>Acá ves todos los diseños que subiste y en qué estado está cada uno.</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_alerta === 'success' ? 'success' : 'error'; ?> fade-in">
                <?php echo ($tipo_alerta === 'success' ? '✅ ' : '⚠️ ') . htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if (count($disenos) === 0): ?>
            <div class="card empty-state fade-in">
                <p style="font-size: 2rem; margin-bottom: 8px;">🎨</p>
                <h3>Todavía no subiste diseños</h3>
                <p>Subí tu primer vector SVG para empezar a fabricar.</p>
                <a href="<?php echo BASE_URL; ?>/pages/subir_diseno.php" class="btn" style="margin-top: 12px;">Subir un diseño →</a>
            </div>
        <?php else: ?>

            <div class="modules-grid">
                <?php foreach ($disenos as $d): ?>
                    <div class="card module-card fade-in">
                        <div style="display:flex; justify-content:space-between; align-items:start; gap:8px; margin-bottom:8px;">
                            <h3 style="margin:0;"><?php echo htmlspecialchars($d['nombre']); ?></h3>
                            <span class="badge <?php echo claseBadgeDiseno($d['estado']); ?>"><?php echo $ESTADOS_DISENO[$d['estado']] ?? $d['estado']; ?></span>
                        </div>
                        <p style="font-size:0.85rem; margin-bottom:4px;">Tipo: <strong><?php echo $NOMBRE_LINDO[$d['tipo_objeto']] ?? $d['tipo_objeto']; ?></strong></p>
                        <p style="font-size:0.85rem; margin-bottom:4px;">Medidas: <?php echo $d['ancho_mm']; ?> × <?php echo $d['largo_mm']; ?> × <?php echo $d['espesor_mm']; ?> mm</p>
                        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:12px;">Subido el <?php echo date("d/m/Y", strtotime($d['fecha_subida'])); ?></p>

                        <?php if ($d['estado'] === 'rechazado'): ?>
                            <?php if (!empty($d['motivo_rechazo'])): ?>
                                <div class="alert alert-error" style="margin:0 0 10px 0; font-size:0.85rem;">
                                    <strong>Motivo del rechazo:</strong><br><?php echo htmlspecialchars($d['motivo_rechazo']); ?>
                                </div>
                            <?php endif; ?>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <a href="<?php echo BASE_URL; ?>/pages/subir_diseno.php" class="btn btn-secondary" style="font-size:0.82rem;">Subir corregido →</a>
                                <form method="post" action="" style="display:inline; padding:0; background:none; border:none; box-shadow:none; margin:0;"
                                      data-confirm="¿Eliminar el diseño? No se puede deshacer.">
                                    <?php csrf_campo(); ?>
                                    <input type="hidden" name="diseno_id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" name="eliminar_diseno" class="btn btn-danger" style="font-size:0.82rem;">Eliminar</button>
                                </form>
                            </div>
                        <?php elseif ($d['estado'] === 'aprobado'): ?>
                            <a href="<?php echo BASE_URL; ?>/pages/hacer_pedido.php" class="btn btn-secondary" style="font-size:0.85rem;">Hacer un pedido →</a>
                        <?php elseif ($d['estado'] === 'en_revision'): ?>
                            <p style="font-size:0.82rem; color:var(--text-muted);">⏳ Esperando revisión del taller.</p>
                        <?php endif; ?>

                        <?php if (!empty($d['archivo_svg'])): ?>
                            <p style="margin-top:10px;"><a href="<?php echo BASE_URL . "/pages/ver_diseno.php?id=" . (int)$d['id']; ?>" target="_blank" rel="noopener" style="font-size:0.82rem;">🔍 Ver archivo SVG</a></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>