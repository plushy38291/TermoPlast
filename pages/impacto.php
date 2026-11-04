<?php
require __DIR__ . '/../includes/proteger.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';
require __DIR__ . '/../includes/funciones.php';

$eid = $_SESSION["emprendedor_id"];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(gramos_totales),0)/1000 FROM pedidos WHERE emprendedor_id = ? AND estado = 'finalizado'");
$stmt->execute([$eid]);
$kg_reciclados = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT p.cantidad, d.tipo_objeto FROM pedidos p INNER JOIN disenos d ON p.diseno_id = d.id WHERE p.emprendedor_id = ? AND p.estado = 'finalizado'");
$stmt->execute([$eid]);
$horas_trabajo = 0;
foreach ($stmt->fetchAll() as $fila) {
    $horas_trabajo += ($HORAS_POR_TIPO[$fila['tipo_objeto']] ?? 0) * $fila['cantidad'];
}
$horas_trabajo = round($horas_trabajo, 1);

$tapitas_recuperadas = $PESO_TAPITA_G > 0 ? round(($kg_reciclados * 1000) / $PESO_TAPITA_G) : 0;
$co2_evitado = co2_evitado_kg($pdo, (int)$eid);
$energia_ahorrada_kwh = round($kg_reciclados * 5.2, 1);

// ---- Datos para los GRÁFICOS del emprendedor ----
// 1) Piezas finalizadas por tipo de objeto (doughnut).
$piezas_tipo = [];
$st = $pdo->prepare(
    "SELECT d.tipo_objeto AS tipo, COALESCE(SUM(p.cantidad),0) AS u
     FROM pedidos p INNER JOIN disenos d ON p.diseno_id = d.id
     WHERE p.emprendedor_id = ? AND p.estado = 'finalizado'
     GROUP BY d.tipo_objeto ORDER BY u DESC");
$st->execute([$eid]);
foreach ($st->fetchAll() as $r) {
    $piezas_tipo[] = ['lbl' => ($NOMBRE_LINDO[$r['tipo']] ?? $r['tipo']), 'u' => (int)$r['u']];
}
// 2) Evolución mensual de kg reciclados (últimos 12 meses, línea).
$kg_mes = [];
$st = $pdo->prepare(
    "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, ROUND(SUM(gramos_totales)/1000, 2) AS kg
     FROM pedidos WHERE emprendedor_id = ? AND estado = 'finalizado'
       AND fecha >= (CURRENT_DATE - INTERVAL 12 MONTH)
     GROUP BY mes ORDER BY mes ASC");
$st->execute([$eid]);
foreach ($st->fetchAll() as $r) { $kg_mes[] = ['mes' => $r['mes'], 'kg' => (float)$r['kg']]; }

$GRAF = [
    'tipos'  => ['labels' => array_column($piezas_tipo, 'lbl'), 'data' => array_column($piezas_tipo, 'u')],
    'meses'  => ['labels' => array_column($kg_mes, 'mes'),      'data' => array_column($kg_mes, 'kg')],
];
$hay_datos_graf = !empty($piezas_tipo) || !empty($kg_mes);

// Progreso hacia la meta de 50 kg (anillo tipo gauge, forma mockup).
$gMeta = (int)round(min(100, $kg_reciclados / 50 * 100));
// Colores de leyenda del doughnut (coherentes con la paleta turquesa).
$paletaLeg = ['#0e8f86','#15b3a6','#2bc0b0','#57d6c6','#73e8cd'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impacto Ambiental - TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <?php $pagina_activa = "impacto"; include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Panel de Impacto Ecológico ♻️</h1>
            <p>Métricas consolidadas del material desviado de basurales a través de tus pedidos finalizados.</p>
        </div>

        <div class="dash12">
            <!-- Stat-tiles con íconos (forma mockup) -->
            <div class="card reveal" style="grid-column:span 8;">
                <div class="card-h">Tus números de impacto</div>
                <div class="stat3">
                    <div class="stat">
                        <div class="ico">♻</div>
                        <div class="big-num"><span class="count" data-to="<?php echo $kg_reciclados; ?>" data-dec="1"><?php echo number_format($kg_reciclados,1); ?></span><small> kg</small></div>
                        <div class="l">Plástico recuperado</div>
                    </div>
                    <div class="stat">
                        <div class="ico">🧴</div>
                        <div class="big-num"><span class="count" data-to="<?php echo $tapitas_recuperadas; ?>"><?php echo number_format($tapitas_recuperadas); ?></span></div>
                        <div class="l">Tapitas desviadas</div>
                    </div>
                    <div class="stat">
                        <div class="ico">🌱</div>
                        <div class="big-num"><span class="count" data-to="<?php echo $co2_evitado; ?>" data-dec="1"><?php echo $co2_evitado; ?></span><small> kg</small></div>
                        <div class="l">CO₂ evitado</div>
                    </div>
                </div>
                <div class="rowh" style="margin-top:12px;border-top:1px solid var(--border);padding-top:12px;">
                    <div class="stat" style="flex:1;">
                        <div class="ico">⏱️</div>
                        <div class="big-num" style="font-size:1.5rem;"><span class="count" data-to="<?php echo $horas_trabajo; ?>" data-dec="1"><?php echo $horas_trabajo; ?></span><small> hs</small></div>
                        <div class="l">Horas de trabajo inclusivo generadas</div>
                    </div>
                </div>
            </div>

            <!-- Anillo de progreso hacia la meta (gauge) -->
            <div class="card reveal d1" style="grid-column:span 4;">
                <div class="card-h">Meta: Semilla Eco 🌿</div>
                <div class="gauges" style="justify-content:center;">
                    <div class="gauge"><div class="gwrap" style="width:150px;height:150px;">
                        <canvas id="gMeta" height="150"></canvas>
                        <div class="gtxt"><div class="gv"><?php echo $gMeta; ?>%</div><div class="gl">de 50 kg</div></div>
                    </div></div>
                </div>
                <p style="text-align:center;color:var(--text-muted);font-size:.8rem;margin:6px 0 0;">
                    <?php echo number_format($kg_reciclados,1); ?> kg de 50 kg para el sello circular.
                </p>
            </div>

            <?php if ($hay_datos_graf): ?>
            <!-- Doughnut: piezas por tipo + leyenda vertical -->
            <div class="card reveal d2" style="grid-column:span 5;">
                <div class="card-h">Piezas fabricadas por tipo</div>
                <div class="rowh" style="flex:1;align-items:center;">
                    <div style="width:160px;height:160px;"><canvas id="chTipos" height="160"></canvas></div>
                    <div class="legv">
                        <?php foreach ($GRAF['tipos']['labels'] as $i => $lb): ?>
                            <span><i class="dotv" style="background:<?php echo $paletaLeg[$i % count($paletaLeg)]; ?>"></i> <?php echo htmlspecialchars($lb); ?> · <?php echo $GRAF['tipos']['data'][$i]; ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($GRAF['tipos']['labels'])): ?><span>Aún sin piezas finalizadas</span><?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Área: kg por mes -->
            <div class="card reveal d3" style="grid-column:span 7;">
                <div class="rowh"><div class="card-h">Plástico reciclado por mes (kg)</div><span style="font-size:12px;color:var(--text-muted)">últimos 12 meses</span></div>
                <div class="ch-sm"><canvas id="chMeses" height="170"></canvas></div>
            </div>
            <?php else: ?>
            <div class="card reveal d2" style="grid-column:span 12;">
                <p style="margin:0;">Cuando tengas pedidos <strong>finalizados</strong>, acá vas a ver tus gráficos de piezas por tipo y de plástico reciclado por mes. 📈</p>
            </div>
            <?php endif; ?>
        </div>

        <h2 class="section-title" style="margin-top: 36px;">Equivalencias Ecológicas Directas</h2>
        <div class="modules-grid">
            <div class="card module-card impact-card fade-in">
                <div class="impact-icon">🌳</div>
                <h3>Árboles Preservados</h3>
                <p class="impact-number">~<?php echo ceil($co2_evitado / 21); ?> árboles</p>
                <p>Capacidad de absorción anual de CO₂ de arbolado urbano.</p>
            </div>
            <div class="card module-card impact-card fade-in">
                <div class="impact-icon">💡</div>
                <h3>Consumo Eléctrico</h3>
                <p class="impact-number">~<?php echo round($energia_ahorrada_kwh / 0.8); ?> horas</p>
                <p>De iluminación LED alimentada con la energía mitigada.</p>
            </div>
            <div class="card module-card impact-card fade-in">
                <div class="impact-icon">🛢️</div>
                <h3>Petróleo Mitigado</h3>
                <p class="impact-number">~<?php echo round($kg_reciclados * 2, 1); ?> litros</p>
                <p>De petróleo crudo sin extraer gracias al reciclaje mecánico.</p>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/assets/js/vendor/chart.umd.js"></script>
    <script nonce="<?php echo csp_nonce(); ?>">
    (function () {
        var G = <?php echo json_encode($GRAF); ?>;
        if (typeof Chart === 'undefined') return;
        var VERDE = '#15b3a6', TEAL = '#0e8f86';
        var paleta = ['#0e8f86','#15b3a6','#2bc0b0','#57d6c6','#73e8cd'];
        var gridc = 'rgba(127,160,152,.18)';
        Chart.defaults.color = getComputedStyle(document.body).color;
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.plugins.legend.display = false;
        // Mutar subpropiedades en vez de reasignar el objeto (ver dashboard.php):
        // reemplazarlo rompe la estructura interna de Chart.js 4 y no dibuja.
        Chart.defaults.animation.duration = 1100;
        Chart.defaults.animation.easing = 'easeOutQuart';
        var surface = getComputedStyle(document.body).getPropertyValue('--surface').trim() || '#fff';
        function grad(c,a,b){ var ar=c.chart.chartArea; if(!ar) return a; var g=c.chart.ctx.createLinearGradient(0,ar.top,0,ar.bottom); g.addColorStop(0,a); g.addColorStop(1,b); return g; }
        function mk(id,cfg){ try{ var el=document.getElementById(id); if(el) return new Chart(el,cfg); }catch(e){} return null; }

        if (G.tipos.data.length) mk('chTipos',{
            type: 'doughnut',
            data: { labels: G.tipos.labels, datasets: [{ data: G.tipos.data, backgroundColor: paleta, borderWidth: 3, borderColor: surface }] },
            options: { cutout: '64%', plugins: { legend: { display:false } } }
        });

        if (G.meses.data.length) mk('chMeses',{
            type: 'line',
            data: { labels: G.meses.labels, datasets: [{ data: G.meses.data,
                borderColor: VERDE, borderWidth:2.5, tension:.4, pointRadius:4, pointBackgroundColor:TEAL, fill: true,
                backgroundColor:function(c){return grad(c,'rgba(21,179,166,.28)','rgba(21,179,166,0)');} }] },
            options: { plugins: { legend: { display: false } }, scales: { x:{grid:{display:false},border:{display:false}}, y: { beginAtZero: true, grid:{color:gridc}, border:{display:false}, ticks:{maxTicksLimit:5} } } }
        });

        mk('gMeta',{type:'doughnut',
            data:{datasets:[{data:[<?php echo $gMeta; ?>, <?php echo 100 - $gMeta; ?>],backgroundColor:[VERDE, gridc],borderWidth:0}]},
            options:{cutout:'78%',plugins:{legend:{display:false},tooltip:{enabled:false}}}});
    })();
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
