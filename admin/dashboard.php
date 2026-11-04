<?php
// ============================================================
//  dashboard.php — Panel analítico (requisito 4) · V4.1
//  Presenta los datos con la "forma mockup": stat-tiles con
//  variación, flujo tipo embudo, anillos (gauges) y gráficos,
//  todo con datos REALES. Dos series salen de STORED PROCEDURES.
// ============================================================
require __DIR__ . '/../includes/proteger_admin.php';
require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';   // $ESTADOS_PEDIDO, $PESO_TAPITA_G

function filas(PDO $pdo, string $sql, array $p = []): array {
    $st = $pdo->prepare($sql); $st->execute($p); $r = $st->fetchAll(); $st->closeCursor(); return $r;
}
// Variación % entre el valor actual y el anterior (para los deltas).
function delta(float $cur, float $prev): array {
    if ($prev <= 0) return $cur > 0 ? ['+nuevo', 'pos'] : ['—', 'mut'];
    $p = ($cur - $prev) / $prev * 100;
    $cls = $p > 0.05 ? 'pos' : ($p < -0.05 ? 'neg' : 'mut');
    return [($p >= 0 ? '+' : '') . number_format($p, 1) . '%', $cls];
}

try {
    // ---- KPIs base ----
    $kpi_pedidos = (int)$pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
    $kpi_kg = round((float)$pdo->query("SELECT COALESCE(SUM(gramos_totales),0)/1000 FROM pedidos WHERE estado='finalizado'")->fetchColumn(), 1);
    $kpi_co2 = round((float)$pdo->query("SELECT COALESCE(SUM(p.gramos_totales*m.co2_por_gramo),0) FROM pedidos p JOIN materiales m ON p.material_id=m.id WHERE p.estado='finalizado'")->fetchColumn(), 1);
    $kpi_emp = (int)$pdo->query("SELECT COUNT(*) FROM emprendedores WHERE activo=1")->fetchColumn();
    $dis_total = (int)$pdo->query("SELECT COUNT(*) FROM disenos")->fetchColumn();
    $dis_aprob = (int)$pdo->query("SELECT COUNT(*) FROM disenos WHERE estado='aprobado'")->fetchColumn();
    $ped_fin = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado='finalizado'")->fetchColumn();
    $tapitas = $PESO_TAPITA_G > 0 ? (int)round(($kpi_kg * 1000) / $PESO_TAPITA_G) : 0;

    // ---- Series mensuales ----
    $mesPedLbl = []; $mesPedVal = [];
    foreach (filas($pdo, "CALL sp_pedidos_por_mes()") as $r) { $mesPedLbl[] = $r['mes']; $mesPedVal[] = (int)$r['cantidad']; }
    $mesKgLbl = []; $mesKgVal = [];
    foreach (filas($pdo, "SELECT DATE_FORMAT(fecha,'%Y-%m') mes, ROUND(SUM(gramos_totales)/1000,2) kg FROM pedidos WHERE estado='finalizado' AND fecha>=(CURRENT_DATE - INTERVAL 12 MONTH) GROUP BY mes ORDER BY mes") as $r) { $mesKgLbl[] = $r['mes']; $mesKgVal[] = (float)$r['kg']; }
    $mesCo2Val = [];
    foreach (filas($pdo, "SELECT DATE_FORMAT(p.fecha,'%Y-%m') mes, ROUND(SUM(p.gramos_totales*m.co2_por_gramo),2) co2 FROM pedidos p JOIN materiales m ON p.material_id=m.id WHERE p.estado='finalizado' AND p.fecha>=(CURRENT_DATE - INTERVAL 12 MONTH) GROUP BY mes ORDER BY mes") as $r) { $mesCo2Val[] = (float)$r['co2']; }

    // ---- Deltas (último mes vs anterior) ----
    $n = count($mesPedVal);
    [$dPed, $dPedCls] = delta($n ? $mesPedVal[$n-1] : 0, $n > 1 ? $mesPedVal[$n-2] : 0);
    $nk = count($mesKgVal);
    [$dKg, $dKgCls] = delta($nk ? $mesKgVal[$nk-1] : 0, $nk > 1 ? $mesKgVal[$nk-2] : 0);
    $nc = count($mesCo2Val);
    [$dCo2, $dCo2Cls] = delta($nc ? $mesCo2Val[$nc-1] : 0, $nc > 1 ? $mesCo2Val[$nc-2] : 0);

    // ---- Pedidos por estado (flujo + donut) ----
    $porEstado = [];
    foreach (filas($pdo, "SELECT estado, COUNT(*) c FROM pedidos GROUP BY estado") as $r) { $porEstado[$r['estado']] = (int)$r['c']; }
    $donLbl = []; $donVal = [];
    foreach ($ESTADOS_PEDIDO as $k => $t) { if (!empty($porEstado[$k])) { $donLbl[] = $t; $donVal[] = $porEstado[$k]; } }

    // ---- Top emprendedores (SP) ----
    $topLbl = []; $topVal = [];
    foreach (filas($pdo, "CALL sp_top_emprendedores(5)") as $r) { $topLbl[] = $r['emprendedor']; $topVal[] = (float)$r['kg']; }

    // ---- Diseños por categoría (N-N) ----
    $catLbl = []; $catVal = [];
    foreach (filas($pdo, "SELECT e.nombre, COUNT(de.diseno_id) c FROM etiquetas e LEFT JOIN diseno_etiquetas de ON de.etiqueta_id=e.id WHERE e.activo=1 GROUP BY e.id,e.nombre ORDER BY c DESC") as $r) { $catLbl[] = $r['nombre']; $catVal[] = (int)$r['c']; }

    // ---- Materiales (nº de pedidos por material) ----
    $matLbl = []; $matVal = [];
    foreach (filas($pdo, "SELECT m.nombre, COUNT(p.id) c FROM materiales m LEFT JOIN pedidos p ON p.material_id=m.id WHERE m.activo=1 GROUP BY m.id,m.nombre ORDER BY c DESC") as $r) { $matLbl[] = $r['nombre']; $matVal[] = (int)$r['c']; }
} catch (PDOException $e) {
    $kpi_pedidos=$kpi_kg=$kpi_co2=$kpi_emp=$dis_total=$dis_aprob=$ped_fin=$tapitas=0;
    $mesPedLbl=$mesPedVal=$mesKgLbl=$mesKgVal=$mesCo2Val=$donLbl=$donVal=$topLbl=$topVal=$catLbl=$catVal=$matLbl=$matVal=[];
    $dPed=$dKg=$dCo2='—'; $dPedCls=$dKgCls=$dCo2Cls='mut';
}

// Gauges (porcentajes reales)
$gAprob = $dis_total > 0 ? (int)round($dis_aprob / $dis_total * 100) : 0;
$gFin   = $kpi_pedidos > 0 ? (int)round($ped_fin / $kpi_pedidos * 100) : 0;

// Flujo de producción (orden del proceso) con conteo y % del total
$flujoOrden = ['finalizado','en_calentamiento','moldeado','registrado'];
$totalPed = max(1, $kpi_pedidos);

$DATOS = [
  'mesPed' => ['labels'=>$mesPedLbl, 'data'=>$mesPedVal],
  'mesKg'  => ['labels'=>$mesKgLbl,  'data'=>$mesKgVal],
  'co2'    => ['data'=>$mesCo2Val],
  'don'    => ['labels'=>$donLbl, 'data'=>$donVal],
  'top'    => ['labels'=>$topLbl, 'data'=>$topVal],
  'cat'    => ['labels'=>$catLbl, 'data'=>$catVal],
  'mat'    => ['labels'=>$matLbl, 'data'=>$matVal],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — TermoPlast Admin</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>
    <?php $pagina_activa = 'dashboard'; include __DIR__ . '/../includes/admin_header.php'; ?>

    <main class="main-content">
        <div class="page-header"><h1>Dashboard 📊</h1><p>Indicadores y tendencias del sistema en tiempo real.</p></div>

        <div class="dash12">
            <!-- Resumen de actividad (mini KPIs con ícono) -->
            <div class="card" style="grid-column:span 4;">
                <div class="card-h">Resumen de actividad</div>
                <div class="stat3">
                    <div class="stat"><div class="ico">📦</div><div class="v"><?php echo $dPed; ?></div><div class="l">Pedidos (mes)</div></div>
                    <div class="stat"><div class="ico">♻</div><div class="v"><?php echo $dKg; ?></div><div class="l">Reciclado (mes)</div></div>
                    <div class="stat"><div class="ico">🌱</div><div class="v"><?php echo $dCo2; ?></div><div class="l">CO₂ (mes)</div></div>
                </div>
            </div>

            <!-- Pedidos acumulados + línea -->
            <div class="card" style="grid-column:span 4;">
                <div class="rowh"><div class="card-h">Pedidos acumulados</div><span class="delta <?php echo $dPedCls; ?>"><?php echo $dPed; ?></span></div>
                <div class="big-num"><span class="count" data-to="<?php echo $kpi_pedidos; ?>"><?php echo $kpi_pedidos; ?></span></div>
                <div class="ch-xs"><canvas id="chPed" height="72"></canvas></div>
            </div>

            <!-- Flujo de producción (embudo) + categorías -->
            <div class="card" style="grid-column:span 4; grid-row:span 2;">
                <div class="card-h">Flujo de producción</div>
                <div class="flow">
                    <?php
                    $maxEstado = max(1, max($porEstado ?: [1]));
                    foreach ($flujoOrden as $k):
                        $c = $porEstado[$k] ?? 0; if ($c === 0 && $k !== 'finalizado') continue;
                        $w = 18 + (int)round(($c / $maxEstado) * 20);
                        $pct = round($c / $totalPed * 100);
                    ?>
                    <div class="flitem">
                        <div class="fbar" style="width:<?php echo $w; ?>px;"></div>
                        <div class="fmeta"><div class="n"><?php echo $c; ?></div><div class="t"><?php echo htmlspecialchars($ESTADOS_PEDIDO[$k] ?? $k); ?></div></div>
                        <div class="fdelta delta mut"><?php echo $pct; ?>%</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-h" style="margin-top:16px;">Diseños por categoría</div>
                <div class="ch-sm"><canvas id="chCat" height="150"></canvas></div>
            </div>

            <!-- Pedidos por estado (donut + leyenda) -->
            <div class="card" style="grid-column:span 4;">
                <div class="card-h">Pedidos por estado</div>
                <div class="rowh" style="flex:1;">
                    <div style="width:150px;height:150px;"><canvas id="chDon" height="150"></canvas></div>
                    <div class="legv">
                        <?php
                        $colEstado = ['Finalizado'=>'#0e8f86','Registrado'=>'#15b3a6','En calentamiento'=>'#2bc0b0','Moldeado'=>'#73e8cd','Cancelado'=>'#ef8468'];
                        foreach ($donLbl as $i => $lb): ?>
                            <span><i class="dotv" style="background:<?php echo $colEstado[$lb] ?? '#15b3a6'; ?>"></i> <?php echo htmlspecialchars($lb); ?> · <?php echo $donVal[$i]; ?></span>
                        <?php endforeach; ?>
                        <?php if (!$donLbl): ?><span>Sin pedidos aún</span><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tapitas desviadas + área -->
            <div class="card" style="grid-column:span 4;">
                <div class="rowh"><div class="card-h">Tapitas desviadas</div><span class="delta <?php echo $dKgCls; ?>"><?php echo $dKg; ?></span></div>
                <div class="big-num"><span class="count" data-to="<?php echo $tapitas; ?>"><?php echo number_format($tapitas); ?></span></div>
                <div class="ch-xs"><canvas id="chKgSm" height="72"></canvas></div>
            </div>

            <!-- Plástico reciclado por mes (área grande) -->
            <div class="card" style="grid-column:span 8;">
                <div class="rowh"><div class="card-h">Plástico reciclado por mes (kg)</div><span class="muted" style="font-size:12px;color:var(--text-muted)">últimos 12 meses</span></div>
                <div class="ch-sm"><canvas id="chKgBig" height="170"></canvas></div>
            </div>

            <!-- Indicadores clave (gauges) -->
            <div class="card" style="grid-column:span 4;">
                <div class="card-h">Indicadores clave</div>
                <div class="gauges">
                    <div class="gauge"><div class="gwrap"><canvas id="chG1" height="118"></canvas><div class="gtxt"><div class="gv"><?php echo $gAprob; ?>%</div><div class="gl">Aprobación</div></div></div></div>
                    <div class="gauge"><div class="gwrap"><canvas id="chG2" height="118"></canvas><div class="gtxt"><div class="gv"><?php echo $gFin; ?>%</div><div class="gl">Finalizados</div></div></div></div>
                </div>
            </div>

            <!-- Top emprendedores -->
            <div class="card" style="grid-column:span 5;">
                <div class="card-h">Top emprendedores (kg reciclados)</div>
                <div class="ch-sm"><canvas id="chTop" height="150"></canvas></div>
            </div>
            <!-- Materiales -->
            <div class="card" style="grid-column:span 4;">
                <div class="card-h">Materiales usados (pedidos)</div>
                <div class="ch-sm"><canvas id="chMat" height="140"></canvas></div>
            </div>
            <!-- CO2 evitado -->
            <div class="card" style="grid-column:span 3;">
                <div class="rowh"><div class="card-h">CO₂ evitado</div><span class="delta <?php echo $dCo2Cls; ?>"><?php echo $dCo2; ?></span></div>
                <div class="big-num"><span class="count" data-to="<?php echo $kpi_co2; ?>" data-dec="1"><?php echo number_format($kpi_co2,1); ?></span> <small>kg</small></div>
                <div class="ch-xs"><canvas id="chCo2" height="64"></canvas></div>
            </div>
        </div>

        <p style="color:var(--text-muted); font-size:0.82rem; margin-top:14px;">La evolución mensual y el ranking se calculan con <em>stored procedures</em> (<code>sp_pedidos_por_mes</code>, <code>sp_top_emprendedores</code>).</p>
    </main>

    <script src="<?php echo BASE_URL; ?>/assets/js/vendor/chart.umd.js"></script>
    <script nonce="<?php echo csp_nonce(); ?>">
    (function(){
        var D = <?php echo json_encode($DATOS); ?>;
        if (typeof Chart === 'undefined') return;
        var TEAL='#15b3a6', DEEP='#0e8f86', MID='#2bc0b0', MINT='#73e8cd', MINT2='#9fe6da', CORAL='#ef8468';
        var paleta=[DEEP,TEAL,MID,MINT,MINT2,CORAL];
        Chart.defaults.color = getComputedStyle(document.body).color;
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.plugins.legend.display = false;
        // Mutar las subpropiedades (NO reasignar el objeto entero: en Chart.js 4
        // reemplazar Chart.defaults.animation rompe su estructura interna y lanza
        // "this._fn is not a function", dejando los canvas en blanco).
        Chart.defaults.animation.duration = 900;
        Chart.defaults.animation.easing = 'easeOutQuart';
        var surface = getComputedStyle(document.body).getPropertyValue('--surface').trim() || '#fff';
        var gridc = 'rgba(127,160,152,.18)';
        function grad(c,a,b){ var ar=c.chart.chartArea; if(!ar) return a; var g=c.chart.ctx.createLinearGradient(0,ar.top,0,ar.bottom); g.addColorStop(0,a); g.addColorStop(1,b); return g; }
        var noG={grid:{display:false},border:{display:false}};
        var soft={grid:{color:gridc},border:{display:false},ticks:{maxTicksLimit:5}};
        function has(d){ return d && d.data && d.data.length; }
        // Crea un gráfico de forma segura: busca el canvas por id y captura errores
        // para que un gráfico sin datos no tire abajo a los demás.
        function mk(id, cfg){ try { var el=document.getElementById(id); if(el) return new Chart(el, cfg); } catch(e){} return null; }

        if (has(D.mesPed)) mk('chPed',{type:'line',data:{labels:D.mesPed.labels,datasets:[{data:D.mesPed.data,borderColor:TEAL,borderWidth:2.5,tension:.4,pointRadius:0,fill:true,backgroundColor:function(c){return grad(c,'rgba(21,179,166,.35)','rgba(21,179,166,0)');}}]},options:{plugins:{legend:{display:false}},scales:{x:{display:false},y:{display:false}}}});

        if (has(D.mesKg)) mk('chKgSm',{type:'line',data:{labels:D.mesKg.labels,datasets:[{data:D.mesKg.data,borderColor:DEEP,borderWidth:2.5,tension:.45,pointRadius:0,fill:true,backgroundColor:function(c){return grad(c,'rgba(14,143,134,.35)','rgba(14,143,134,0)');}}]},options:{plugins:{legend:{display:false}},scales:{x:{display:false},y:{display:false}}}});

        if (has(D.mesKg)) mk('chKgBig',{type:'line',data:{labels:D.mesKg.labels,datasets:[{label:'kg',data:D.mesKg.data,borderColor:TEAL,borderWidth:2.5,tension:.45,pointRadius:4,pointBackgroundColor:DEEP,fill:true,backgroundColor:function(c){return grad(c,'rgba(21,179,166,.30)','rgba(21,179,166,0)');}}]},options:{plugins:{legend:{display:false}},scales:{x:soft,y:Object.assign({beginAtZero:true},soft)}}});

        if (D.co2 && D.co2.data && D.co2.data.length) mk('chCo2',{type:'bar',data:{labels:D.co2.data.map(function(_,i){return i;}),datasets:[{data:D.co2.data,borderRadius:4,maxBarThickness:9,backgroundColor:function(c){return grad(c,MINT,TEAL);}}]},options:{plugins:{legend:{display:false}},scales:{x:{display:false},y:{display:false}}}});

        if (has(D.don)) mk('chDon',{type:'doughnut',data:{labels:D.don.labels,datasets:[{data:D.don.data,backgroundColor:paleta,borderWidth:3,borderColor:surface}]},options:{cutout:'66%',plugins:{legend:{display:false}}}});

        if (has(D.cat)) mk('chCat',{type:'bar',data:{labels:D.cat.labels,datasets:[{data:D.cat.data,borderRadius:6,maxBarThickness:26,backgroundColor:function(c){return grad(c,MINT,DEEP);}}]},options:{plugins:{legend:{display:false}},scales:{x:noG,y:{display:false}}}});

        if (has(D.top)) mk('chTop',{type:'bar',data:{labels:D.top.labels,datasets:[{data:D.top.data,borderRadius:6,maxBarThickness:18,backgroundColor:function(c){return grad(c,TEAL,DEEP);}}]},options:{indexAxis:'y',plugins:{legend:{display:false}},scales:{x:soft,y:noG}}});

        if (has(D.mat)) mk('chMat',{type:'bar',data:{labels:D.mat.labels,datasets:[{data:D.mat.data,borderRadius:6,maxBarThickness:36,backgroundColor:[DEEP,TEAL,MID,MINT]}]},options:{plugins:{legend:{display:false}},scales:{x:noG,y:{display:false}}}});

        function gauge(id,val,color){ mk(id,{type:'doughnut',data:{datasets:[{data:[val,100-val],backgroundColor:[color, gridc],borderWidth:0}]},options:{cutout:'78%',plugins:{legend:{display:false},tooltip:{enabled:false}}}}); }
        gauge('chG1', <?php echo $gAprob; ?>, TEAL); gauge('chG2', <?php echo $gFin; ?>, DEEP);
    })();
    </script>
</body>
</html>
