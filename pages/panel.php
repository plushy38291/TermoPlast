<?php
require __DIR__ . '/../includes/proteger.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';
require __DIR__ . '/../includes/funciones.php';

$eid = $_SESSION["emprendedor_id"];

// Variación % mes actual vs anterior (misma lógica que el dashboard admin).
function delta_e(float $cur, float $prev): array {
    if ($prev <= 0) return $cur > 0 ? ['+nuevo', 'pos'] : ['—', 'mut'];
    $p = ($cur - $prev) / $prev * 100;
    $cls = $p > 0.05 ? 'pos' : ($p < -0.05 ? 'neg' : 'mut');
    return [($p >= 0 ? '+' : '') . number_format($p, 1) . '%', $cls];
}

$stmt = $pdo->prepare("SELECT COALESCE(SUM(gramos_totales),0)/1000 FROM pedidos WHERE emprendedor_id = ? AND estado = 'finalizado'");
$stmt->execute([$eid]); $kg_reciclados = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE emprendedor_id = ?");
$stmt->execute([$eid]); $total_pedidos = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE emprendedor_id = ? AND estado = 'finalizado'");
$stmt->execute([$eid]); $pedidos_finalizados = (int)$stmt->fetchColumn();
$pedidos_en_proceso = $total_pedidos - $pedidos_finalizados;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM disenos WHERE emprendedor_id = ? AND estado = 'aprobado'");
$stmt->execute([$eid]); $disenos_aprobados = (int)$stmt->fetchColumn();

$tapitas = $PESO_TAPITA_G > 0 ? round(($kg_reciclados * 1000) / $PESO_TAPITA_G) : 0;
$co2_evitado = co2_evitado_kg($pdo, (int)$eid);

// ---- Series mensuales (para sparkline + deltas) ----
$mesKgLbl = []; $mesKgVal = [];
$st = $pdo->prepare("SELECT DATE_FORMAT(fecha,'%Y-%m') mes, ROUND(SUM(gramos_totales)/1000,2) kg
    FROM pedidos WHERE emprendedor_id = ? AND estado='finalizado' AND fecha>=(CURRENT_DATE - INTERVAL 12 MONTH)
    GROUP BY mes ORDER BY mes");
$st->execute([$eid]);
foreach ($st->fetchAll() as $r) { $mesKgLbl[] = $r['mes']; $mesKgVal[] = (float)$r['kg']; }

$mesPedVal = [];
$st = $pdo->prepare("SELECT DATE_FORMAT(fecha,'%Y-%m') mes, COUNT(*) c
    FROM pedidos WHERE emprendedor_id = ? AND fecha>=(CURRENT_DATE - INTERVAL 12 MONTH)
    GROUP BY mes ORDER BY mes");
$st->execute([$eid]);
foreach ($st->fetchAll() as $r) { $mesPedVal[] = (int)$r['c']; }

$nk = count($mesKgVal);
[$dKg, $dKgCls] = delta_e($nk ? $mesKgVal[$nk-1] : 0, $nk > 1 ? $mesKgVal[$nk-2] : 0);
$np = count($mesPedVal);
[$dPed, $dPedCls] = delta_e($np ? $mesPedVal[$np-1] : 0, $np > 1 ? $mesPedVal[$np-2] : 0);

// Progreso hacia la meta de 50 kg (anillo tipo gauge).
$gMeta = (int)round(min(100, $kg_reciclados / 50 * 100));

$DATOS = [
  'kg'  => ['labels'=>$mesKgLbl, 'data'=>$mesKgVal],
];
$hay_serie = $nk > 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <?php $pagina_activa = "inicio"; include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <section class="hero-banner fade-in">
            <div class="hero-text">
                <span class="badge-hero">Economía Circular Activa</span>
                <h1>Transformá plástico reciclado en productos de alto valor</h1>
                <p>Gestioná la fabricación de tus piezas, cargá matrices en vector y medí el impacto ecológico de cada proyecto.</p>
                <div class="hero-actions">
                    <a href="<?php echo BASE_URL; ?>/pages/hacer_pedido.php" class="btn btn-hero">Hacer un Pedido →</a>
                    <a href="<?php echo BASE_URL; ?>/pages/subir_diseno.php" class="btn btn-hero-outline">Subir Vector SVG</a>
                </div>
            </div>
        </section>

        <h2 class="section-title">Tu actividad de un vistazo</h2>
        <div class="dash12">
            <!-- Resumen con íconos + deltas reales (forma mockup) -->
            <div class="card reveal" style="grid-column:span 8;">
                <div class="card-h">Resumen de tu impacto</div>
                <div class="stat3">
                    <div class="stat">
                        <div class="ico">♻</div>
                        <div class="big-num"><span class="count" data-to="<?php echo $kg_reciclados; ?>" data-dec="1"><?php echo number_format($kg_reciclados,1); ?></span><small> kg</small></div>
                        <div class="l">Plástico reciclado</div>
                        <span class="delta <?php echo $dKgCls; ?>"><?php echo $dKg; ?> este mes</span>
                    </div>
                    <div class="stat">
                        <div class="ico">📦</div>
                        <div class="big-num"><span class="count" data-to="<?php echo $total_pedidos; ?>"><?php echo $total_pedidos; ?></span></div>
                        <div class="l">Pedidos realizados</div>
                        <span class="delta <?php echo $dPedCls; ?>"><?php echo $dPed; ?> este mes</span>
                    </div>
                    <div class="stat">
                        <div class="ico">🌱</div>
                        <div class="big-num"><span class="count" data-to="<?php echo $co2_evitado; ?>" data-dec="1"><?php echo $co2_evitado; ?></span><small> kg</small></div>
                        <div class="l">CO₂ evitado</div>
                        <span class="delta mut">~<?php echo number_format($tapitas); ?> tapitas</span>
                    </div>
                </div>
                <div class="ch-sm" style="margin-top:14px;"><canvas id="chKg" height="90"></canvas></div>
            </div>

            <!-- Anillo de progreso hacia la meta (gauge) -->
            <div class="card reveal d1" style="grid-column:span 4;">
                <div class="card-h">Meta de reciclaje</div>
                <div class="gauges" style="justify-content:center;">
                    <div class="gauge"><div class="gwrap" style="width:140px;height:140px;">
                        <canvas id="gMeta" height="140"></canvas>
                        <div class="gtxt"><div class="gv"><?php echo $gMeta; ?>%</div><div class="gl">de 50 kg</div></div>
                    </div></div>
                </div>
                <p style="text-align:center;color:var(--text-muted);font-size:.82rem;margin:6px 0 0;">
                    Sello <strong>Semilla Eco 🌿</strong> al llegar a 50 kg.
                </p>
            </div>

            <!-- Diseños aprobados + pedidos -->
            <div class="card reveal d2" style="grid-column:span 4;">
                <div class="rowh"><div class="card-h">Diseños aprobados</div></div>
                <div class="big-num"><span class="count" data-to="<?php echo $disenos_aprobados; ?>"><?php echo $disenos_aprobados; ?></span></div>
                <p style="color:var(--text-muted);font-size:.82rem;margin:4px 0 0;">Listos para pedir</p>
            </div>
            <div class="card reveal d3" style="grid-column:span 4;">
                <div class="rowh"><div class="card-h">Pedidos entregados</div></div>
                <div class="big-num"><span class="count" data-to="<?php echo $pedidos_finalizados; ?>"><?php echo $pedidos_finalizados; ?></span></div>
                <p style="color:var(--text-muted);font-size:.82rem;margin:4px 0 0;"><?php echo $pedidos_en_proceso; ?> en proceso</p>
            </div>
            <div class="card reveal" style="grid-column:span 4;">
                <div class="rowh"><div class="card-h">Tapitas desviadas</div></div>
                <div class="big-num"><span class="count" data-to="<?php echo $tapitas; ?>"><?php echo number_format($tapitas); ?></span></div>
                <p style="color:var(--text-muted);font-size:.82rem;margin:4px 0 0;">Unidades post-consumo</p>
            </div>
        </div>

        <h2 class="section-title">¿Qué querés hacer hoy?</h2>
        <div class="modules-grid">
            <div class="card module-card fade-in">
                <div class="card-icon">🛠️</div>
                <h3>Hacer un Pedido</h3>
                <p>Elegí un diseño aprobado, el material y solicitá el moldeo en la celda térmica.</p>
                <a href="<?php echo BASE_URL; ?>/pages/hacer_pedido.php" class="btn btn-secondary">Crear solicitud →</a>
            </div>
            <div class="card module-card fade-in">
                <div class="card-icon">🎨</div>
                <h3>¿Tenés una idea?</h3>
                <p>Subí tu archivo SVG para evaluar la factibilidad de una matriz personalizada.</p>
                <a href="<?php echo BASE_URL; ?>/pages/subir_diseno.php" class="btn btn-secondary">Cargar archivo →</a>
            </div>
            <div class="card module-card fade-in">
                <div class="card-icon">📁</div>
                <h3>Mis Diseños</h3>
                <p>Revisá el estado de tus diseños subidos y el motivo si alguno fue rechazado.</p>
                <a href="<?php echo BASE_URL; ?>/pages/mis_disenos.php" class="btn btn-secondary">Ver mis diseños →</a>
            </div>
            <div class="card module-card fade-in">
                <div class="card-icon">📋</div>
                <h3>Seguimiento</h3>
                <p>Consultá la trazabilidad de tus pedidos dentro del flujo de producción del taller.</p>
                <a href="<?php echo BASE_URL; ?>/pages/seguimiento.php" class="btn btn-secondary">Ver historial →</a>
            </div>
        </div>

        <div class="card admin-link-card">
            <small>
                ¿Sos del equipo del taller?
                <a href="<?php echo BASE_URL; ?>/admin/admin_login.php" target="_blank" rel="noopener">Ir al acceso del taller ⚙️</a>
            </small>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/assets/js/vendor/chart.umd.js"></script>
    <script nonce="<?php echo csp_nonce(); ?>">
    (function(){
        var D = <?php echo json_encode($DATOS); ?>;
        if (typeof Chart === 'undefined') return;
        var TEAL='#15b3a6', DEEP='#0e8f86', MINT='#73e8cd';
        Chart.defaults.color = getComputedStyle(document.body).color;
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.plugins.legend.display = false;
        // Mutar subpropiedades (reasignar Chart.defaults.animation rompe Chart.js 4).
        Chart.defaults.animation.duration = 900;
        Chart.defaults.animation.easing = 'easeOutQuart';
        var gridc = 'rgba(127,160,152,.18)';
        function grad(c,a,b){ var ar=c.chart.chartArea; if(!ar) return a; var g=c.chart.ctx.createLinearGradient(0,ar.top,0,ar.bottom); g.addColorStop(0,a); g.addColorStop(1,b); return g; }
        function mk(id,cfg){ try{ var el=document.getElementById(id); if(el) return new Chart(el,cfg); }catch(e){} return null; }

        if (D.kg && D.kg.data && D.kg.data.length) mk('chKg',{type:'line',
            data:{labels:D.kg.labels,datasets:[{data:D.kg.data,borderColor:TEAL,borderWidth:2.5,tension:.4,pointRadius:3,pointBackgroundColor:DEEP,fill:true,
                backgroundColor:function(c){return grad(c,'rgba(21,179,166,.28)','rgba(21,179,166,0)');}}]},
            options:{plugins:{legend:{display:false}},scales:{x:{grid:{display:false},border:{display:false}},y:{beginAtZero:true,grid:{color:gridc},border:{display:false},ticks:{maxTicksLimit:4}}}}});

        mk('gMeta',{type:'doughnut',
            data:{datasets:[{data:[<?php echo $gMeta; ?>, <?php echo 100 - $gMeta; ?>],backgroundColor:[TEAL, gridc],borderWidth:0}]},
            options:{cutout:'78%',plugins:{legend:{display:false},tooltip:{enabled:false}}}});
    })();
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
