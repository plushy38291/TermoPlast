<?php
// ============================================================
//  index.php — Landing pública de TermoPlast (la cara del producto)
//  Muestra estadísticas REALES de impacto agregadas de la base.
// ============================================================
require_once __DIR__ . '/includes/sesion_segura.php';   // cabeceras de seguridad + nonce CSP
require __DIR__ . '/config/conexion.php';
require __DIR__ . '/includes/funciones.php';

// --- Estadísticas globales reales (con fallback a 0 si algo falla) ---
$kg_total = 0; $co2_total = 0; $piezas_total = 0; $emprendedores_total = 0;
try {
    $kg_total = (float)$pdo->query(
        "SELECT COALESCE(SUM(gramos_totales),0)/1000 FROM pedidos WHERE estado='finalizado'"
    )->fetchColumn();
    $co2_total = (float)$pdo->query(
        "SELECT COALESCE(SUM(p.gramos_totales*m.co2_por_gramo),0)
         FROM pedidos p INNER JOIN materiales m ON p.material_id=m.id
         WHERE p.estado='finalizado'"
    )->fetchColumn();
    $piezas_total = (int)$pdo->query(
        "SELECT COALESCE(SUM(cantidad),0) FROM pedidos WHERE estado='finalizado'"
    )->fetchColumn();
    $emprendedores_total = (int)$pdo->query("SELECT COUNT(*) FROM emprendedores")->fetchColumn();
} catch (PDOException $e) { /* dejamos los ceros */ }

$logueado = isset($_SESSION["emprendedor_id"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TermoPlast — Plástico reciclado con impacto medible</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <nav class="lp-nav">
        <a href="<?php echo BASE_URL; ?>/index.php" class="topbar-brand">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
            </svg>
            <span>TermoPlast</span>
        </a>
        <div class="lp-nav-links">
            <button type="button" class="tema-toggle" aria-label="Cambiar tema">🌙</button>
            <?php if ($logueado): ?>
                <a href="<?php echo BASE_URL; ?>/pages/panel.php" class="btn">Ir a mi panel →</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/auth/login.php" class="nav-item">Iniciar sesión</a>
                <a href="<?php echo BASE_URL; ?>/auth/registro_emprendedor.php" class="btn">Crear cuenta</a>
            <?php endif; ?>
        </div>
    </nav>

    <header class="lp-hero">
        <div class="fade-in">
            <span class="lp-eyebrow">Economía circular · Hecho con tapitas</span>
            <h1>Convertí plástico reciclado en piezas con <span class="verde">impacto real</span>.</h1>
            <p class="lead">TermoPlast transforma tapitas de HDPE y PP en objetos para tu emprendimiento, y mide el impacto ambiental de cada pieza que fabricás. Subís tu diseño, nosotros lo moldeamos.</p>
            <div class="lp-cta-row">
                <a href="<?php echo BASE_URL; ?>/auth/registro_emprendedor.php" class="btn">Empezar gratis →</a>
                <a href="#como" class="btn btn-secondary">Cómo funciona</a>
            </div>
        </div>
        <div class="lp-hero-visual fade-in" aria-hidden="true">
            <span class="glyph">♻️</span>
        </div>
    </header>

    <section class="lp-stats">
        <div class="lp-stat"><div class="num"><?php echo number_format($kg_total, 1); ?> kg</div><div class="lbl">Plástico recuperado</div></div>
        <div class="lp-stat"><div class="num"><?php echo number_format($co2_total, 1); ?> kg</div><div class="lbl">CO₂ evitado</div></div>
        <div class="lp-stat"><div class="num"><?php echo number_format($piezas_total); ?></div><div class="lbl">Piezas fabricadas</div></div>
        <div class="lp-stat"><div class="num"><?php echo number_format($emprendedores_total); ?></div><div class="lbl">Emprendedores</div></div>
    </section>

    <section class="lp-section">
        <div class="lp-section-head">
            <h2>Lo que nos hace distintos</h2>
            <p>No es solo reciclar: es darte trazabilidad y números que podés mostrarle a tus clientes.</p>
        </div>
        <div class="lp-features">
            <div class="lp-feature"><div class="fi">📊</div><h3>Impacto medible por pieza</h3><p>Cada pedido calcula kilos de plástico recuperado y CO₂ evitado según el material real usado.</p></div>
            <div class="lp-feature"><div class="fi">🎨</div><h3>Tu diseño, tu matriz</h3><p>Subís tu vector SVG y lo revisamos para fabricar una pieza a tu medida, dentro de los límites de la máquina.</p></div>
            <div class="lp-feature"><div class="fi">📍</div><h3>Trazabilidad completa</h3><p>Seguí tu pedido en vivo: registrado, en calentamiento, moldeado y finalizado, hasta el punto de retiro.</p></div>
        </div>
    </section>

    <section class="lp-section" id="como">
        <div class="lp-section-head">
            <h2>Cómo funciona</h2>
            <p>De la idea a la pieza terminada en cuatro pasos.</p>
        </div>
        <div class="lp-steps">
            <div class="lp-step"><span class="np">1</span><h3>Registrate</h3><p>Creá tu cuenta de emprendedor en un minuto.</p></div>
            <div class="lp-step"><span class="np">2</span><h3>Subí tu diseño</h3><p>Cargá tu SVG; el taller lo aprueba o te sugiere ajustes.</p></div>
            <div class="lp-step"><span class="np">3</span><h3>Hacé tu pedido</h3><p>Elegí material y cantidad; verás el impacto estimado al instante.</p></div>
            <div class="lp-step"><span class="np">4</span><h3>Retiralo</h3><p>Seguí la fabricación y retirá tus piezas terminadas.</p></div>
        </div>
    </section>

    <section class="lp-cta-final fade-in">
        <h2>Empezá a fabricar con impacto</h2>
        <p>Sumate a los emprendedores que ya transforman residuos en productos de valor.</p>
        <a href="<?php echo BASE_URL; ?>/auth/registro_emprendedor.php" class="btn btn-hero">Crear mi cuenta →</a>
    </section>

    <footer class="footer-bottom">
        © 2026 TermoPlast — Tesis de 7° año, E.E.S.T. N° 3 Roberto Arlt.
        <span class="footer-sep">·</span>
        <a href="<?php echo BASE_URL; ?>/admin/admin_login.php" class="footer-staff">Acceso del taller</a>
    </footer>

    <script src="<?php echo BASE_URL; ?>/assets/js/tema.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/anim.js"></script>
</body>
</html>
