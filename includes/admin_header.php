<?php
// ============================================================
//  admin_header.php — Barra de navegación del panel de administración.
//  Arma el menú mostrando SOLO lo permitido para el rol logueado
//  (ver includes/roles.php). La variable $pagina_activa resalta el ítem.
//  Requiere que roles.php ya esté incluido.
// ============================================================
if (!function_exists('admin_menu')) { require_once __DIR__ . '/roles.php'; }
$pagina_activa = $pagina_activa ?? '';
$rol = admin_rol();
?>
<header class="topbar">
    <a href="<?php echo BASE_URL; ?>/admin/admin_panel.php" class="topbar-brand">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
        </svg>
        <span>TermoPlast Admin</span>
    </a>
    <button type="button" class="nav-toggle" aria-label="Abrir menú" aria-expanded="false">☰</button>
    <nav class="nav-links" id="navLinks">
        <?php foreach (admin_menu() as $clave => $item): ?>
            <?php if (in_array($rol, $item[2], true)): ?>
                <a href="<?php echo BASE_URL; ?>/admin/<?php echo $item[1]; ?>"
                   class="nav-item <?php echo ($pagina_activa === $clave) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($item[0]); ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <div class="user-menu">
        <span class="user-name" title="Rol">
            <strong><?php echo htmlspecialchars($_SESSION['admin_usuario'] ?? 'Admin'); ?></strong>
            <span class="badge badge-registrado" style="margin-left:6px;"><?php echo htmlspecialchars(rol_lindo($rol)); ?></span>
        </span>
        <button type="button" class="tema-toggle" aria-label="Cambiar tema">🌙</button>
        <a href="<?php echo BASE_URL; ?>/pages/panel.php" target="_blank" rel="noopener" class="nav-item" title="Ver la app como emprendedor">👁️</a>
        <form method="post" action="<?php echo BASE_URL; ?>/admin/admin_logout.php" style="display:inline; margin:0; padding:0; background:none; border:none; box-shadow:none;">
            <?php csrf_campo(); ?>
            <button type="submit" class="btn-logout">Salir</button>
        </form>
    </div>
</header>
<script src="<?php echo BASE_URL; ?>/assets/js/tema.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/anim.js"></script>
