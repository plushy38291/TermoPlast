<?php
// ============================================================
//  proteger.php
//  Guardián de las páginas privadas del emprendedor.
//  Verifica sesión + cierra por inactividad (timeout).
// ============================================================

require_once __DIR__ . '/sesion_segura.php';

$TIMEOUT_INACTIVIDAD = 1800; // 30 minutos sin actividad → se cierra la sesión

if (isset($_SESSION["emprendedor_id"])) {
    if (isset($_SESSION['ultima_actividad']) &&
        (time() - $_SESSION['ultima_actividad'] > $TIMEOUT_INACTIVIDAD)) {
        session_unset();
        session_destroy();
        header("Location: " . BASE_URL . "/auth/login.php?timeout=1");
        exit;
    }
    $_SESSION['ultima_actividad'] = time();   // renovamos el reloj de inactividad
}

if (!isset($_SESSION["emprendedor_id"])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

// SEGURIDAD: las páginas privadas no deben quedar en caché del navegador.
// Así, tras cerrar sesión, el botón "atrás" no muestra datos de la cuenta.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
