<?php
// ============================================================
//  admin_seguridad.php
//  Visor de AUDITORÍA para el administrador. Muestra los eventos
//  de seguridad registrados (logins, fallos, bloqueos) y un
//  resumen de las últimas 24 horas.
// ============================================================
require __DIR__ . '/../includes/proteger_admin.php';
require __DIR__ . '/../config/conexion.php';

// Resumen últimas 24 h
$resumen = [];
try {
    $r = $pdo->query(
        "SELECT tipo, COUNT(*) AS n FROM eventos_seguridad
         WHERE creado_en > (NOW() - INTERVAL 24 HOUR)
         GROUP BY tipo"
    )->fetchAll();
    foreach ($r as $fila) { $resumen[$fila['tipo']] = (int)$fila['n']; }
} catch (PDOException $e) { $resumen = []; }

// Últimos 100 eventos
try {
    $eventos = $pdo->query(
        "SELECT tipo, detalle, ip, usuario, creado_en
         FROM eventos_seguridad ORDER BY id DESC LIMIT 100"
    )->fetchAll();
} catch (PDOException $e) {
    $eventos = [];
}

function badgeEvento($tipo) {
    switch ($tipo) {
        case 'login_ok':        return 'badge-finalizado';
        case 'login_fallido':   return 'badge-calentamiento';
        case 'bloqueo':         return 'badge-cancelado';
        case 'cambio_password':
        case '2fa_ok':          return 'badge-moldeado';
        default:                return 'badge-registrado';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría de Seguridad - TermoPlast Admin</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <div class="topbar">
        <span class="topbar-brand">TermoPlast Admin</span>
        <div>
            <a href="<?php echo BASE_URL; ?>/admin/admin_panel.php">← Volver al panel</a> |
            <form method="post" action="<?php echo BASE_URL; ?>/admin/admin_logout.php" style="display:inline; margin:0; padding:0; background:none; border:none; box-shadow:none;">
                <?php csrf_campo(); ?>
                <button type="submit" class="btn-link">Cerrar sesión</button>
            </form>
        </div>
    </div>

    <main class="main-content">
        <div class="page-header">
            <h1>Auditoría de Seguridad 🛡️</h1>
            <p>Registro de eventos del sistema. Resumen de las últimas 24 horas y los 100 eventos más recientes.</p>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Ingresos correctos (24h)</div>
                <div class="kpi-value"><?php echo $resumen['login_ok'] ?? 0; ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Intentos fallidos (24h)</div>
                <div class="kpi-value"><?php echo $resumen['login_fallido'] ?? 0; ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Bloqueos (24h)</div>
                <div class="kpi-value"><?php echo $resumen['bloqueo'] ?? 0; ?></div>
            </div>
        </div>

        <div class="card fade-in" style="padding:0; overflow:hidden; margin-top:8px;">
            <div style="padding:20px 24px; border-bottom:1px solid var(--border);">
                <h2 style="margin:0;">Eventos recientes</h2>
            </div>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr><th>Fecha</th><th>Tipo</th><th>Usuario / Email</th><th>IP</th><th>Detalle</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($eventos) === 0): ?>
                            <tr><td colspan="5" style="text-align:center; padding:24px; color:var(--text-muted);">
                                Todavía no hay eventos registrados.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($eventos as $e): ?>
                                <tr>
                                    <td><?php echo date("d/m/Y H:i", strtotime($e['creado_en'])); ?></td>
                                    <td><span class="badge <?php echo badgeEvento($e['tipo']); ?>"><?php echo htmlspecialchars($e['tipo']); ?></span></td>
                                    <td><?php echo htmlspecialchars($e['usuario'] ?? ''); ?></td>
                                    <td><small><?php echo htmlspecialchars($e['ip'] ?? ''); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($e['detalle'] ?? ''); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="footer-bottom">TermoPlast · Panel de administración — acceso restringido</div>
</body>
</html>
