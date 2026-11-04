<?php
// ============================================================
//  verificar_2fa.php
//  Segundo paso del login cuando el emprendedor tiene 2FA activo.
//  Pide el código de 6 dígitos generado en el login.
//  MODO DEMO: el código se muestra en pantalla (en producción iría por email).
// ============================================================
require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../includes/seguridad.php';

// Si ya está logueado, no hay nada que verificar.
if (isset($_SESSION["emprendedor_id"])) {
    header("Location: " . BASE_URL . "/pages/panel.php");
    exit;
}
// Si no hay un login pendiente de 2FA, volvemos al login normal.
if (!isset($_SESSION["2fa_pendiente"])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

$p = $_SESSION["2fa_pendiente"];
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();

    if (time() > $p["exp"]) {
        // Código vencido: limpiamos y mandamos a empezar de nuevo.
        unset($_SESSION["2fa_pendiente"], $_SESSION["2fa_demo"]);
        registrar_evento($pdo, '2fa_vencido', "Código 2FA vencido", $p["email"]);
        header("Location: " . BASE_URL . "/auth/login.php?timeout=1");
        exit;
    }

    // SEGURIDAD: tope de intentos. Un código de 6 dígitos sin límite sería
    // fuerza-bruteable. Contamos cada intento y, si se pasa, invalidamos el
    // código y obligamos a iniciar sesión de nuevo.
    $MAX_INTENTOS = 5;
    $p["intentos"] = ($p["intentos"] ?? 0) + 1;
    $_SESSION["2fa_pendiente"] = $p;          // persistimos el contador
    if ($p["intentos"] > $MAX_INTENTOS) {
        unset($_SESSION["2fa_pendiente"], $_SESSION["2fa_demo"]);
        registrar_evento($pdo, '2fa_bloqueado', "Demasiados intentos de código 2FA", $p["email"]);
        header("Location: " . BASE_URL . "/auth/login.php?err=2fa");
        exit;
    }

    $codigo = trim($_POST["codigo"] ?? "");
    if (password_verify($codigo, $p["hash"])) {
        // ¡Código correcto! Completamos el login.
        registrar_evento($pdo, '2fa_ok', "Verificación 2FA correcta", $p["email"]);
        registrar_evento($pdo, 'login_ok', "Emprendedor #{$p['id']} (2FA)", $p["email"]);

        session_regenerate_id(true);
        $_SESSION["emprendedor_id"]     = $p["id"];
        $_SESSION["emprendedor_nombre"] = $p["nombre"];
        $_SESSION["ultima_actividad"]   = time();
        unset($_SESSION["2fa_pendiente"], $_SESSION["2fa_demo"]);

        header("Location: " . BASE_URL . "/pages/panel.php");
        exit;
    } else {
        registrar_evento($pdo, '2fa_fallido', "Código 2FA incorrecto", $p["email"]);
        $restantes = $MAX_INTENTOS - $p["intentos"];
        $mensaje = "El código no es correcto. Te queda" . ($restantes === 1 ? "" : "n")
                 . " {$restantes} intento" . ($restantes === 1 ? "" : "s") . ".";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación en dos pasos - TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <div class="auth-wrapper">
        <div class="auth-card fade-in">

            <div style="display:flex; justify-content:flex-end; margin-bottom:-8px;">
                <button type="button" class="tema-toggle" aria-label="Cambiar tema">🌙</button>
            </div>
            <div class="auth-brand">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span>TermoPlast</span>
            </div>
            <h1 class="auth-title">Verificación en dos pasos 🔐</h1>
            <p class="auth-subtitle">Ingresá el código de 6 dígitos para completar tu acceso.</p>

            <?php if (isset($_SESSION["2fa_demo"])): ?>
                <div class="info-box" style="margin-bottom:16px;">
                    🧪 <strong>Modo demostración:</strong> tu código es
                    <code style="font-size:1.3rem; letter-spacing:3px;"><?php echo htmlspecialchars($_SESSION["2fa_demo"]); ?></code>
                    <br><small>En producción, este código llegaría a tu email y no se mostraría acá.</small>
                </div>
            <?php endif; ?>

            <?php if ($mensaje !== ""): ?>
                <div class="alert alert-error fade-in">⚠️ <?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php csrf_campo(); ?>
                <div class="form-group">
                    <label>Código de verificación</label>
                    <input type="text" name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6"
                           placeholder="______" autocomplete="one-time-code" required
                           style="letter-spacing:6px; text-align:center; font-size:1.3rem;">
                </div>
                <button type="submit" class="btn btn-block">Verificar y entrar →</button>
            </form>

            <p class="auth-footer"><a href="<?php echo BASE_URL; ?>/auth/login.php">← Cancelar y volver</a></p>

        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/tema.js"></script>
</body>
</html>
