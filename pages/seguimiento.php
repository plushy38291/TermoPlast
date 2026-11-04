<?php
require __DIR__ . '/../includes/proteger.php';
require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';
require __DIR__ . '/../includes/funciones.php';   // historial_pedido(), registrar_historial()

// ------------------------------------------------------------
//  CANCELAR UN PEDIDO
//  Solo lo puede cancelar SU dueño, y SOLO si todavía está en
//  "registrado" (una vez que entra en fabricación, ya no se cancela).
// ------------------------------------------------------------
$mensaje = "";
$tipo_alerta = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['cancelar_pedido'])) {
    csrf_validar();
    $pedido_id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
    if ($pedido_id) {
        // El WHERE es la seguridad: acota a SU pedido y al estado cancelable.
        $stmtC = $pdo->prepare(
            "UPDATE pedidos SET estado = 'cancelado'
             WHERE id = ? AND emprendedor_id = ? AND estado = 'registrado'"
        );
        $stmtC->execute([$pedido_id, $_SESSION['emprendedor_id']]);
        if ($stmtC->rowCount() > 0) {
            // TRAZABILIDAD: dejamos la cancelación registrada en la línea de tiempo.
            registrar_historial($pdo, $pedido_id, 'cancelado', 'Cancelado por el emprendedor');
            $mensaje = "Pedido #{$pedido_id} cancelado correctamente.";
            $tipo_alerta = "success";
        } else {
            $mensaje = "No se pudo cancelar: el pedido ya está en fabricación o no te pertenece.";
            $tipo_alerta = "error";
        }
    }
}

$sql = "SELECT p.id, p.cantidad, p.gramos_totales, p.estado, p.fecha, p.codigo,
               d.nombre AS diseno_nombre, d.tipo_objeto, m.nombre AS material_nombre
        FROM pedidos p
        INNER JOIN disenos d    ON p.diseno_id   = d.id
        INNER JOIN materiales m ON p.material_id = m.id
        WHERE p.emprendedor_id = ? ORDER BY p.fecha DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['emprendedor_id']]);
$pedidos = $stmt->fetchAll();

$listos_retiro = [];
$en_proceso    = [];
foreach ($pedidos as $p) {
    if ($p['estado'] === 'finalizado') $listos_retiro[] = $p;
    elseif ($p['estado'] !== 'cancelado') $en_proceso[] = $p;
}

$PASO_POR_ESTADO = ['registrado' => 1, 'en_calentamiento' => 2, 'moldeado' => 3, 'finalizado' => 4, 'cancelado' => 0];
$PASOS = [1 => 'Registrado', 2 => 'En calentamiento', 3 => 'Moldeado', 4 => 'Finalizado'];

// Base ABSOLUTA del sistema para los QR. Si entrás por localhost, el
// helper intenta usar la IP de red para que el QR se pueda escanear
// desde otro dispositivo (ver includes/funciones.php y config URL_PUBLICA).
$HOST_BASE = url_publica_base();
$qr_aviso  = qr_no_escaneable($HOST_BASE);

// La lógica de color del badge vive en funciones.php (clase_badge_pedido),
// para que admin y emprendedor muestren SIEMPRE el mismo color por estado.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Pedidos - TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <?php $pagina_activa = "seguimiento"; include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Seguimiento de Pedidos 📋</h1>
            <p>Monitoreá el procesamiento de tus piezas dentro de la celda de moldeo térmico.</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_alerta === 'success' ? 'success' : 'error'; ?> fade-in">
                <?php echo ($tipo_alerta === 'success' ? '✅ ' : '⚠️ ') . htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if ($qr_aviso && count($en_proceso) > 0): ?>
            <div class="alert alert-error fade-in" style="margin-bottom:16px;">
                ⚠️ Los códigos QR apuntan a <strong>localhost</strong>, así que no se van a poder escanear
                desde otro celular. Para habilitarlos, definí <code>URL_PUBLICA</code> en
                <code>config/catalogo.php</code> con la IP de red de esta PC (ej:
                <code>http://192.168.0.15/TermoPlast</code>) o entrá a la app por esa IP.
            </div>
        <?php endif; ?>

        <?php if (count($pedidos) === 0): ?>
            <div class="card empty-state fade-in">
                <p style="font-size: 2rem; margin-bottom: 8px;">📭</p>
                <h3>Todavía no tenés pedidos</h3>
                <p>Cuando hagas tu primer pedido, vas a poder seguir su estado acá.</p>
                <a href="<?php echo BASE_URL; ?>/pages/hacer_pedido.php" class="btn" style="margin-top: 12px;">Hacer un pedido →</a>
            </div>
        <?php else: ?>

            <?php if (count($listos_retiro) > 0): ?>
                <div class="retiro-banner fade-in">
                    <div class="retiro-header">
                        <span class="retiro-icon">📦</span>
                        <div>
                            <h2>¡Tenés <?php echo count($listos_retiro); ?> pedido<?php echo count($listos_retiro) > 1 ? 's' : ''; ?> listo<?php echo count($listos_retiro) > 1 ? 's' : ''; ?> para retirar!</h2>
                            <p class="retiro-lugar">📍 Retiralo en: <strong><?php echo htmlspecialchars($PUNTOS_RETIRO[0]); ?></strong></p>
                        </div>
                    </div>
                    <div class="retiro-lista">
                        <?php foreach ($listos_retiro as $p): ?>
                            <div class="retiro-item">
                                <div><strong>#<?php echo $p['id']; ?></strong> — <?php echo htmlspecialchars($p['diseno_nombre']); ?> (<?php echo $NOMBRE_LINDO[$p['tipo_objeto']] ?? $p['tipo_objeto']; ?>) × <?php echo $p['cantidad']; ?></div>
                                <span class="badge badge-finalizado">Listo para retirar</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (count($en_proceso) > 0): ?>
                <h2 class="section-title">En proceso de fabricación (<?php echo count($en_proceso); ?>)</h2>
                <?php foreach ($en_proceso as $p): ?>
                    <?php $paso_actual = $PASO_POR_ESTADO[$p['estado']] ?? 1; ?>
                    <div class="card fade-in">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                            <h3 style="margin:0;">Pedido #<?php echo $p['id']; ?> — <?php echo htmlspecialchars($p['diseno_nombre']); ?> (<?php echo $NOMBRE_LINDO[$p['tipo_objeto']] ?? $p['tipo_objeto']; ?>) × <?php echo $p['cantidad']; ?></h3>
                            <span class="badge <?php echo clase_badge_pedido($p['estado']); ?>"><?php echo $ESTADOS_PEDIDO[$p['estado']] ?? $p['estado']; ?></span>
                        </div>
                        <p style="font-size:0.85rem; margin-bottom:24px;">Material: <?php echo htmlspecialchars($p['material_nombre']); ?></p>
                        <div class="stepper-container">
                            <div class="stepper">
                                <?php foreach ($PASOS as $num => $label): ?>
                                    <?php $clase = ''; if ($num < $paso_actual) $clase = 'completed'; elseif ($num == $paso_actual) $clase = 'active'; ?>
                                    <div class="step <?php echo $clase; ?>"><?php echo $num; ?><span class="step-label"><?php echo $label; ?></span></div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php // QR (req 6): se autogenera en el navegador y lleva a la ficha pública del pedido. ?>
                        <?php $ficha_url = $HOST_BASE . '/pages/ficha_pedido.php?c=' . urlencode($p['codigo'] ?? ''); ?>
                        <div class="qr-flow">
                            <div class="qr-box" data-qr="<?php echo htmlspecialchars($ficha_url); ?>" aria-label="Código QR del pedido"></div>
                            <div class="qr-text">
                                <strong>Seguimiento con QR</strong>
                                <p>Escaneá este código para ver el estado de producción del pedido #<?php echo $p['id']; ?> desde el celular, sin iniciar sesión.</p>
                                <a href="<?php echo htmlspecialchars($ficha_url); ?>" target="_blank" rel="noopener">Abrir ficha pública →</a>
                                <p class="qr-url">El QR apunta a: <code><?php echo htmlspecialchars($ficha_url); ?></code></p>
                                <a href="<?php echo BASE_URL; ?>/pages/diagnostico_qr.php" class="qr-diag">¿El QR no abre en el celular? Diagnóstico →</a>
                            </div>
                        </div>

                        <?php // TRAZABILIDAD: línea de tiempo real con fechas de cada etapa. ?>
                        <?php $hist = historial_pedido($pdo, (int)$p['id']); ?>
                        <?php if (count($hist) > 0): ?>
                            <details class="trazabilidad">
                                <summary>📍 Ver trazabilidad del pedido (<?php echo count($hist); ?> <?php echo count($hist) === 1 ? 'etapa' : 'etapas'; ?>)</summary>
                                <ol class="timeline">
                                    <?php foreach ($hist as $h): ?>
                                        <li class="timeline-item">
                                            <span class="timeline-dot <?php echo clase_badge_pedido($h['estado']); ?>"></span>
                                            <div class="timeline-body">
                                                <strong><?php echo htmlspecialchars($ESTADOS_PEDIDO[$h['estado']] ?? $h['estado']); ?></strong>
                                                <span class="timeline-fecha"><?php echo date('d/m/Y H:i', strtotime($h['fecha'])); ?> hs</span>
                                                <?php if (!empty($h['nota'])): ?>
                                                    <p class="timeline-nota"><?php echo htmlspecialchars($h['nota']); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($h['cambiado_por'])): ?>
                                                    <small class="timeline-por">Operador: <?php echo htmlspecialchars($h['cambiado_por']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </details>
                        <?php endif; ?>

                        <?php if ($p['estado'] === 'registrado'): ?>
                            <div style="text-align:right; margin-top:14px;">
                                <form method="post" action="" style="display:inline; padding:0; background:none; border:none; box-shadow:none; margin:0;"
                                      data-confirm="¿Cancelar el pedido #<?php echo $p['id']; ?>? No se puede deshacer.">
                                    <?php csrf_campo(); ?>
                                    <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" name="cancelar_pedido" class="btn btn-danger" style="font-size:0.82rem;">Cancelar pedido</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="card fade-in" style="padding: 0; overflow: hidden; margin-top: 8px;">
                <div style="padding: 20px 24px; border-bottom: 1px solid var(--border);">
                    <h2 style="margin: 0;">Historial completo de solicitudes</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table class="tabla-cards">
                        <thead>
                            <tr><th>Código</th><th>Diseño / Tipo</th><th>Cantidad</th><th>Material</th><th>Fecha</th><th>Estado</th><th>Acción</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $p): ?>
                                <tr>
                                    <td data-label="Código"><strong>#<?php echo $p['id']; ?></strong></td>
                                    <td data-label="Diseño / Tipo"><?php echo htmlspecialchars($p['diseno_nombre']); ?><br><small><?php echo $NOMBRE_LINDO[$p['tipo_objeto']] ?? $p['tipo_objeto']; ?></small></td>
                                    <td data-label="Cantidad"><?php echo $p['cantidad']; ?> un.</td>
                                    <td data-label="Material"><?php echo htmlspecialchars($p['material_nombre']); ?></td>
                                    <td data-label="Fecha"><?php echo date("d/m/Y", strtotime($p['fecha'])); ?></td>
                                    <td data-label="Estado"><span class="badge <?php echo clase_badge_pedido($p['estado']); ?>"><?php echo $ESTADOS_PEDIDO[$p['estado']] ?? $p['estado']; ?></span></td>
                                    <td data-label="Acción"><a href="<?php echo BASE_URL; ?>/pages/hacer_pedido.php?rehacer=<?php echo $p['id']; ?>" style="font-size:0.82rem;">Pedir de nuevo →</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>
    </main>

    <?php // QR: librería local + inicializador (ambos 'self', cumplen la CSP). ?>
    <script src="<?php echo BASE_URL; ?>/assets/js/vendor/qrcode.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/qr.js"></script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>