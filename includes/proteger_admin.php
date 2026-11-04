<?php
// ============================================================
//  proteger_admin.php
//  Guardián de las páginas de administración.
//  Verifica sesión de admin + cierra por inactividad (timeout).
// ============================================================

require_once __DIR__ . '/sesion_segura.php';
require_once __DIR__ . '/roles.php';   // helpers de control por rol (admin_rol, requiere_rol, admin_menu)

$TIMEOUT_INACTIVIDAD = 1800; // 30 minutos

if (isset($_SESSION["admin_id"])) {
    if (isset($_SESSION['ultima_actividad']) &&
        (time() - $_SESSION['ultima_actividad'] > $TIMEOUT_INACTIVIDAD)) {
        session_unset();
        session_destroy();
        header("Location: " . BASE_URL . "/admin/admin_login.php?timeout=1");
        exit;
    }
    $_SESSION['ultima_actividad'] = time();
}

if (!isset($_SESSION["admin_id"])) {
    header("Location: " . BASE_URL . "/admin/admin_login.php");
    exit;
}

// SEGURIDAD: el panel admin no debe quedar cacheado en el navegador.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
