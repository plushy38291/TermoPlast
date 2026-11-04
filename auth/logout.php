<?php
// ============================================================
//  logout.php
//  Cierra la sesion (te "saca la pulsera") y te manda al login.
// ============================================================

require_once __DIR__ . '/../includes/sesion_segura.php';          // activamos la sesion para poder borrarla

// SEGURIDAD: el cierre de sesión se hace SOLO por POST con token CSRF.
// Así un enlace o imagen maliciosa (GET) no puede desloguearte sin tu acción.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();
    session_unset();          // borramos las variables de sesion
    session_destroy();        // destruimos la sesion por completo
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

// Si llega por GET (u otro método), no cerramos nada: volvemos adonde estaba.
header("Location: " . (isset($_SESSION["emprendedor_id"]) ? "panel.php" : "login.php"));
exit;
?>
