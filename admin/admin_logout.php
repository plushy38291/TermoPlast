<?php
// ============================================================
//  admin_logout.php
//  Destruye la sesión del administrador y redirige al login.
// ============================================================

require_once __DIR__ . '/../includes/sesion_segura.php';

// SEGURIDAD: cierre de sesión solo por POST con token CSRF (anti-CSRF logout).
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();
    unset($_SESSION["admin_id"]);
    unset($_SESSION["admin_usuario"]);
    header("Location: " . BASE_URL . "/admin/admin_login.php");
    exit;
}

// GET u otro método: no cerramos sesión.
header("Location: " . (isset($_SESSION["admin_id"]) ? "admin_panel.php" : "admin_login.php"));
exit;
?>