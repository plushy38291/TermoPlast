<?php
// ============================================================
//  mapa.php — Mapa de puntos de retiro (requisito 8, Leaflet).
//  Leaflet está EMBEBIDO localmente (assets/leaflet/). Lo único
//  externo son los "tiles" (fondo) de OpenStreetMap, así que SOLO
//  en esta página ampliamos la CSP para permitir esas imágenes.
// ============================================================
require __DIR__ . '/../includes/proteger.php';            // emprendedor logueado
require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';

// --- CSP ampliada SOLO acá: permite los tiles de OpenStreetMap ---
// (reemplaza la cabecera que puso sesion_segura; el resto queda igual).
if (!headers_sent()) {
    $n = csp_nonce();
    header("Content-Security-Policy: default-src 'self'; "
         . "img-src 'self' data: https://*.tile.openstreetmap.org https://*.basemaps.cartocdn.com; "
         . "style-src 'self' 'unsafe-inline'; "
         . "script-src 'self' 'nonce-$n'; "
         . "object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
}

// Puntos activos desde la base (tabla puntos_retiro, con lat/lng).
$puntos = [];
try {
    foreach ($pdo->query("SELECT nombre, direccion, lat, lng FROM puntos_retiro WHERE activo=1 ORDER BY id") as $r) {
        $puntos[] = [
            'nombre'    => $r['nombre'],
            'direccion' => $r['direccion'],
            'lat'       => (float)$r['lat'],
            'lng'       => (float)$r['lng'],
        ];
    }
} catch (PDOException $e) { $puntos = []; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntos de retiro — TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/leaflet/leaflet.css">
</head>
<body>
    <?php $pagina_activa = "mapa"; include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Puntos de retiro 📍</h1>
            <p>Dónde retirar tus piezas terminadas. Tocá un marcador para ver la dirección.</p>
        </div>

        <?php if (!$puntos): ?>
            <div class="card"><p style="margin:0;">Todavía no hay puntos de retiro activos cargados.</p></div>
        <?php else: ?>
            <div class="mapa-wrap">
                <div id="mapa" class="mapa-box"></div>
                <div id="mapa-offline" class="mapa-offline" hidden>
                    <div class="mapa-offline-card">
                        <span class="mapa-offline-ic">🛰️</span>
                        <strong>Mapa sin conexión</strong>
                        <p>No se pudo cargar el mapa (sin internet). Igual podés ver las direcciones en la lista de abajo 👇</p>
                    </div>
                </div>
            </div>
            <div class="puntos-lista">
                <?php foreach ($puntos as $pt): ?>
                    <div class="punto-item">
                        <span class="punto-pin">📍</span>
                        <div>
                            <strong><?php echo htmlspecialchars($pt['nombre']); ?></strong>
                            <?php if (!empty($pt['direccion'])): ?><br><small><?php echo htmlspecialchars($pt['direccion']); ?></small><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <?php if ($puntos): ?>
    <script src="<?php echo BASE_URL; ?>/assets/leaflet/leaflet.js"></script>
    <script nonce="<?php echo csp_nonce(); ?>">
    (function () {
        var BASE = <?php echo json_encode(BASE_URL); ?>;
        var PUNTOS = <?php echo json_encode($puntos); ?>;
        if (typeof L === 'undefined' || !PUNTOS.length) return;

        // Ícono por defecto con las imágenes LOCALES (evita el bug de marcadores
        // invisibles cuando Leaflet no detecta solo la ruta de las imágenes).
        var icono = L.icon({
            iconUrl:       BASE + '/assets/leaflet/images/marker-icon.png',
            iconRetinaUrl: BASE + '/assets/leaflet/images/marker-icon-2x.png',
            shadowUrl:     BASE + '/assets/leaflet/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        });

        var mapa = L.map('mapa');

        // Degradado elegante: si NINGÚN tile carga (sin internet), mostramos un
        // aviso sobre el mapa y nos apoyamos en la lista de puntos de abajo.
        // Se cuenta tile por tile: con que cargue uno, ocultamos el aviso (así no
        // molesta en redes lentas o con algún tile caído).
        var offline = document.getElementById('mapa-offline');
        var ok = 0, err = 0;
        function mostrarOffline(){ if (offline && ok === 0) offline.hidden = false; }
        function ocultarOffline(){ if (offline) offline.hidden = true; }
        var capa = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        });
        capa.on('tileload',  function(){ ok++;  ocultarOffline(); });
        capa.on('tileerror', function(){ err++; mostrarOffline(); });
        capa.addTo(mapa);
        // Red tipo portal cautivo / timeout: si en 5 s no cargó ningún tile, avisamos.
        setTimeout(mostrarOffline, 5000);

        var grupo = [];
        PUNTOS.forEach(function (p) {
            var m = L.marker([p.lat, p.lng], { icon: icono }).addTo(mapa);
            m.bindPopup('<strong>' + p.nombre + '</strong>' + (p.direccion ? '<br>' + p.direccion : ''));
            grupo.push([p.lat, p.lng]);
        });

        if (grupo.length === 1) {
            mapa.setView(grupo[0], 14);
        } else {
            mapa.fitBounds(grupo, { padding: [40, 40] });
        }
    })();
    </script>
    <?php endif; ?>
</body>
</html>
