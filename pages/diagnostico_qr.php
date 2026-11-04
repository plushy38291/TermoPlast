<?php
// ============================================================
//  diagnostico_qr.php — Ayuda para que el QR funcione desde el celular.
//  Muestra TODAS las IPs de la PC servidor, marca las virtuales (que el
//  celular NO alcanza), indica cuál usa hoy el QR y da los pasos de
//  firewall/Apache. No expone datos sensibles.
// ============================================================
require __DIR__ . '/../includes/proteger.php';            // emprendedor logueado
require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';
require __DIR__ . '/../includes/funciones.php';

$base    = url_publica_base();
$ips     = ips_locales();
$elegida = defined('URL_PUBLICA') && URL_PUBLICA !== '' ? null : mejor_ip_lan();
$puerto  = '';
if (preg_match('/:(\d+)$/', $_SERVER['HTTP_HOST'] ?? '', $m) && $m[1] !== '80') { $puerto = ':' . $m[1]; }
$ruta_app = BASE_URL . '/pages/panel.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de QR — TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>
    <?php $pagina_activa = "seguimiento"; include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Diagnóstico de QR 🔧</h1>
            <p>Si al escanear el QR desde el celular no abre, acá está por qué y cómo resolverlo.</p>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">1 · Qué dirección usa el QR ahora</h2>
            <p>En este momento los QR apuntan a:</p>
            <p><code style="font-size:1.05rem;"><?php echo htmlspecialchars($base); ?></code></p>
            <?php if (qr_no_escaneable($base)): ?>
                <div class="alert alert-error">⚠️ Está usando <strong>localhost</strong>: desde otro celular no se puede abrir. Elegí abajo una IP de red y configurala.</div>
            <?php elseif (defined('URL_PUBLICA') && URL_PUBLICA !== ''): ?>
                <div class="alert alert-success">✅ Está usando la <code>URL_PUBLICA</code> que configuraste en <code>config/catalogo.php</code>.</div>
            <?php else: ?>
                <div class="alert alert-success">✅ Está usando la IP de red detectada automáticamente (<code><?php echo htmlspecialchars((string)$elegida); ?></code>). Si el celular igual no abre, probá otra IP de la lista o seguí los pasos 3 y 4.</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">2 · IPs de esta PC — probá cuál funciona</h2>
            <p>Abrí estas direcciones <strong>desde el navegador del celular</strong> (con el celular en la misma Wi-Fi). La que cargue la app es la correcta: usá esa IP en el paso 3. Evitá las marcadas como <em>virtuales</em>.</p>
            <?php if (!$ips): ?>
                <p>No se pudieron leer las interfaces de red. Usá <code>ipconfig</code> en Windows para ver tu “Dirección IPv4”.</p>
            <?php else: ?>
            <div style="overflow-x:auto;"><table class="tabla-cards">
                <thead><tr><th>Interfaz</th><th>IP</th><th>Tipo</th><th>Probar desde el celular</th></tr></thead>
                <tbody>
                <?php foreach ($ips as $x): ?>
                    <?php $url = 'http://' . $x['ip'] . $puerto . $ruta_app; ?>
                    <tr>
                        <td data-label="Interfaz"><?php echo htmlspecialchars($x['interfaz']); ?></td>
                        <td data-label="IP"><code><?php echo htmlspecialchars($x['ip']); ?></code></td>
                        <td data-label="Tipo">
                            <?php if ($x['virtual']): ?><span class="badge badge-cancelado">Virtual (no sirve)</span>
                            <?php elseif ($x['privada']): ?><span class="badge badge-finalizado">LAN (probá esta)</span>
                            <?php else: ?><span class="badge badge-registrado">Pública/otra</span><?php endif; ?>
                        </td>
                        <td data-label="Probar"><code style="font-size:.8rem;"><?php echo htmlspecialchars($url); ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">3 · Fijar la IP correcta (recomendado)</h2>
            <p>Cuando sepas cuál IP funciona, abrí <code>config/catalogo.php</code> y escribí:</p>
            <pre style="background:var(--surface-2); padding:12px 14px; border-radius:8px; overflow-x:auto;"><code>define('URL_PUBLICA', 'http://TU_IP<?php echo htmlspecialchars($puerto); ?><?php echo htmlspecialchars(BASE_URL); ?>');</code></pre>
            <p style="color:var(--text-soft); font-size:.9rem;">Ej.: <code>define('URL_PUBLICA', 'http://192.168.0.15<?php echo htmlspecialchars(BASE_URL); ?>');</code>. Después, todos los QR usan esa dirección.</p>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">4 · Si la IP es correcta pero igual no abre</h2>
            <p>Suele ser el <strong>Firewall de Windows</strong> bloqueando Apache. Probá, en orden:</p>
            <ol style="margin:0; padding-left:20px; line-height:1.9;">
                <li>Cuando Windows pregunte “¿Permitir Apache HTTP Server en redes…?”, tildá <strong>Redes privadas</strong> y aceptá.</li>
                <li>Panel de control → <em>Firewall de Windows Defender</em> → <em>Permitir una aplicación</em> → buscá <code>httpd.exe</code> (carpeta <code>xampp\apache\bin</code>) y habilitá <strong>Privada</strong>.</li>
                <li>Verificá que Apache escuche en todas las interfaces: en <code>xampp\apache\conf\httpd.conf</code> debe decir <code>Listen 80</code> (no <code>Listen 127.0.0.1:80</code>).</li>
                <li>Probá apagar un momento el firewall para confirmar que es eso; si funciona, agregá la regla del punto 2 en vez de dejarlo apagado.</li>
                <li>Algunas redes (Wi-Fi de “invitados”) aíslan los dispositivos entre sí (<em>AP isolation</em>): probá con otra red o el hotspot del celular.</li>
            </ol>
        </div>

        <p><a href="<?php echo BASE_URL; ?>/pages/seguimiento.php" class="btn btn-secondary">← Volver a Seguimiento</a></p>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
