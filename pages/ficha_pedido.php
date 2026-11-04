<?php
// ============================================================
//  ficha_pedido.php — FICHA PÚBLICA de seguimiento (destino del QR).
//  Se abre con ?c=CODIGO (el código único del pedido, de 10 hex).
//  NO requiere iniciar sesión: es una página de "tracking" que
//  muestra SOLO información de producción (estado + línea de tiempo),
//  sin datos personales del emprendedor. El código actúa como token
//  no adivinable.
// ============================================================
require_once __DIR__ . '/../includes/sesion_segura.php';   // cabeceras + CSP
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';
require __DIR__ . '/../includes/funciones.php';

$codigo = trim($_GET['c'] ?? '');
$pedido = null;

// El código es alfanumérico de 10; validamos antes de consultar.
if ($codigo !== '' && preg_match('/^[A-Za-z0-9]{6,16}$/', $codigo)) {
    $st = $pdo->prepare(
        "SELECT p.id, p.cantidad, p.estado, p.fecha, p.codigo,
                d.tipo_objeto, d.nombre AS diseno_nombre
         FROM pedidos p
         LEFT JOIN disenos d ON p.diseno_id = d.id
         WHERE p.codigo = ? LIMIT 1"
    );
    $st->execute([$codigo]);
    $pedido = $st->fetch() ?: null;
}

$HOST_BASE = url_publica_base();
$self_url  = $HOST_BASE . '/pages/ficha_pedido.php?c=' . urlencode($codigo);

// Punto de retiro activo (desde la tabla; si no hubiera, cae al catálogo).
$retiro = null;
try {
    $retiro = $pdo->query("SELECT nombre, direccion FROM puntos_retiro WHERE activo=1 ORDER BY id LIMIT 1")->fetch() ?: null;
} catch (PDOException $e) {}
if (!$retiro && !empty($PUNTOS_RETIRO[0])) { $retiro = ['nombre' => $PUNTOS_RETIRO[0], 'direccion' => '']; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de pedido — TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>
    <div class="ficha-wrap">
        <div class="ficha-card card fade-in">
            <div class="auth-brand" style="justify-content:center; margin-bottom:10px;">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span>TermoPlast</span>
            </div>

            <?php if (!$pedido): ?>
                <h1 class="auth-title" style="text-align:center;">Pedido no encontrado 🔍</h1>
                <p class="auth-subtitle" style="text-align:center;">El código no corresponde a ningún pedido. Verificá el QR o el enlace.</p>
                <a class="btn btn-block" href="<?php echo BASE_URL; ?>/index.php">Ir al inicio</a>
            <?php else: ?>
                <?php
                    $tipoLindo = $NOMBRE_LINDO[$pedido['tipo_objeto']] ?? $pedido['tipo_objeto'];
                    $estadoTxt = $ESTADOS_PEDIDO[$pedido['estado']] ?? $pedido['estado'];
                    $hist = historial_pedido($pdo, (int)$pedido['id']);
                ?>
                <h1 class="auth-title" style="text-align:center; margin-bottom:4px;">Pedido #<?php echo (int)$pedido['id']; ?></h1>
                <p class="auth-subtitle" style="text-align:center;">
                    <?php echo htmlspecialchars($tipoLindo); ?> · <?php echo (int)$pedido['cantidad']; ?> unidad<?php echo (int)$pedido['cantidad'] === 1 ? '' : 'es'; ?>
                </p>

                <div style="text-align:center; margin:14px 0;">
                    <span class="badge <?php echo clase_badge_pedido($pedido['estado']); ?>" style="font-size:1rem; padding:8px 16px;">
                        <?php echo htmlspecialchars($estadoTxt); ?>
                    </span>
                </div>

                <?php if ($hist): ?>
                    <h2 class="section-title" style="margin-top:10px;">Trazabilidad</h2>
                    <ol class="timeline">
                        <?php foreach ($hist as $h): ?>
                            <li class="timeline-item">
                                <span class="timeline-dot <?php echo clase_badge_pedido($h['estado']); ?>"></span>
                                <div class="timeline-body">
                                    <strong><?php echo htmlspecialchars($ESTADOS_PEDIDO[$h['estado']] ?? $h['estado']); ?></strong>
                                    <span class="timeline-fecha"><?php echo date('d/m/Y H:i', strtotime($h['fecha'])); ?> hs</span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <?php if ($pedido['estado'] === 'finalizado' && $retiro): ?>
                    <div class="alert alert-success" style="margin-top:14px;">
                        📦 Listo para retirar en <strong><?php echo htmlspecialchars($retiro['nombre']); ?></strong>
                        <?php if (!empty($retiro['direccion'])): ?><br><small><?php echo htmlspecialchars($retiro['direccion']); ?></small><?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="qr-flow" style="justify-content:center; margin-top:18px;">
                    <div class="qr-box" data-qr="<?php echo htmlspecialchars($self_url); ?>" aria-label="QR de esta ficha"></div>
                    <div class="qr-text"><small>Código del pedido<br><strong><?php echo htmlspecialchars($pedido['codigo']); ?></strong></small></div>
                </div>
            <?php endif; ?>
        </div>
        <p style="text-align:center; margin-top:14px;">
            <button type="button" class="tema-toggle" aria-label="Cambiar tema">🌙</button>
        </p>
    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/vendor/qrcode.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/qr.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/tema.js"></script>
</body>
</html>
