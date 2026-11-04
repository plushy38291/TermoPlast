<?php
// ============================================================
//  header.php - Barra de navegación compartida (emprendedor)
//  Se incluye en todas las páginas privadas con:
//<?php $pagina_activa = "..."; include __DIR__ . '/header.php';
//
//  La variable $pagina_activa marca qué ítem del menú resaltar.
// ============================================================

// Si la página que lo incluye no definió cuál está activa, usamos "".
$pagina_activa = $pagina_activa ?? '';

// Lista del menú: clave interna => [texto, archivo]

$MENU = [
    'inicio'      => ['Inicio',          'panel.php'],
    'pedido'      => ['Hacer Pedido',    'hacer_pedido.php'],
    'seguimiento' => ['Seguimiento',     'seguimiento.php'],
    'idea'        => ['¿Tenés una idea?', 'subir_diseno.php'],
    'disenos'     => ['Mis Diseños',     'mis_disenos.php'],
    'impacto'     => ['Impacto',         'impacto.php'],
    'mapa'        => ['Puntos de retiro', 'mapa.php'],
];
?>
<header class="topbar sidebar-nav">
    <a href="<?php echo BASE_URL; ?>/pages/panel.php" class="topbar-brand">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
        </svg>
        <span>TermoPlast</span>
    </a>
    <button type="button" class="nav-toggle" aria-label="Abrir menú" aria-expanded="false">☰</button>
    <nav class="nav-links" id="navLinks">
        <?php foreach ($MENU as $clave => $item): ?>
            <a href="<?php echo BASE_URL; ?>/pages/<?php echo $item[1]; ?>"
               class="nav-item <?php echo ($pagina_activa === $clave) ? 'active' : ''; ?>">
                <?php echo $item[0]; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="user-menu">
        <a href="<?php echo BASE_URL; ?>/pages/perfil.php" class="user-name" style="text-decoration:none; color:inherit;">Hola, <strong><?php echo htmlspecialchars($_SESSION["emprendedor_nombre"]); ?></strong></a>
        <button type="button" class="tema-toggle" aria-label="Cambiar tema">🌙</button>
        <form method="post" action="<?php echo BASE_URL; ?>/auth/logout.php" style="display:inline; margin:0; padding:0; background:none; border:none; box-shadow:none;">
            <?php csrf_campo(); ?>
            <button type="submit" class="btn-logout">Salir</button>
        </form>
    </div>
</header>
<script src="<?php echo BASE_URL; ?>/assets/js/tema.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/anim.js"></script>
