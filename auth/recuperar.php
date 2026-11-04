<?php
// ============================================================
//  recuperar.php — "Olvidé mi contraseña" (paso 1: pedir enlace)
//
//  Seguridad:
//   - CSRF + cabeceras endurecidas (sesion_segura.php).
//   - ANTI-ENUMERACIÓN: la respuesta es SIEMPRE la misma, exista o
//     no el email. Nunca confirmamos si la cuenta está registrada.
//   - El token se genera hasheado, con vencimiento y un solo uso.
//   - MODO DEMO: como no hay servidor de mail (SMTP) en XAMPP,
//     mostramos el enlace en pantalla, igual que el código 2FA.
//     En producción, esto se enviaría por correo y la pantalla
//     mostraría solo el mensaje genérico.
// ============================================================

require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../includes/seguridad.php';

// Si ya está logueado, no tiene sentido recuperar.
if (isset($_SESSION["emprendedor_id"])) {
    header("Location: " . BASE_URL . "/pages/panel.php");
    exit;
}

reset_limpiar($pdo);   // higiene: borramos tokens vencidos/usados

$enviado      = false;   // ya procesamos un pedido (muestra el mensaje genérico)
$demo_link    = "";      // SOLO modo demo: enlace a mostrar en pantalla
$mensaje_err  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();

    $email = trim($_POST["email"] ?? "");

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje_err = "Ingresá un email con formato válido.";
    } else {
        // Buscamos la cuenta, pero NO cambiamos la respuesta según exista o no.
        $st = $pdo->prepare("SELECT id FROM emprendedores WHERE email = ?");
        $st->execute([$email]);
        $emp = $st->fetch();

        if ($emp) {
            if (reset_demasiados_pedidos($pdo, (int)$emp["id"])) {
                // Aunque esté limitado, mostramos el mismo mensaje genérico.
                registrar_evento($pdo, 'reset_limitado', "Demasiados pedidos de reset", $email);
            } else {
                $token = reset_crear_token($pdo, (int)$emp["id"]);
                registrar_evento($pdo, 'reset_solicitado', "Enlace de recuperación generado", $email);

                // Armamos el enlace (misma carpeta que esta página).
                $demo_link = BASE_URL . "/auth/restablecer.php?token=" . $token;
            }
        } else {
            // Email inexistente: registramos el intento, pero NO lo delatamos.
            registrar_evento($pdo, 'reset_desconocido', "Pedido para email inexistente", $email);
        }

        $enviado = true;   // pase lo que pase, llegamos acá con el mismo mensaje
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña - TermoPlast</title>
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
            <h1 class="auth-title">Recuperar contraseña</h1>
            <p class="auth-subtitle">Te enviamos un enlace para crear una nueva.</p>

            <?php if ($mensaje_err !== ""): ?>
                <div class="alert alert-error fade-in">⚠️ <?php echo htmlspecialchars($mensaje_err); ?></div>
            <?php endif; ?>

            <?php if ($enviado): ?>
                <div class="alert alert-success fade-in">
                    ✅ Si ese email está registrado, te enviamos un enlace para restablecer la contraseña. Revisá tu correo (el enlace vence en <?php echo RESET_MINUTOS; ?> minutos).
                </div>

                <?php if ($demo_link !== ""): ?>
                    <div class="info-box" style="margin-top:0;">
                        🧪 <strong>Modo demostración (sin servidor de correo):</strong> en un sistema en producción este enlace llegaría por email. Para la defensa, lo mostramos acá:
                        <p style="margin:10px 0 0; word-break:break-all;">
                            <a href="<?php echo htmlspecialchars($demo_link); ?>"><?php echo htmlspecialchars($demo_link); ?></a>
                        </p>
                    </div>
                <?php endif; ?>

                <p class="auth-footer" style="margin-top:18px;"><a href="<?php echo BASE_URL; ?>/auth/login.php">← Volver a iniciar sesión</a></p>
            <?php else: ?>
                <form method="post" action="">
                    <?php csrf_campo(); ?>
                    <div class="form-group">
                        <label>Email de tu cuenta</label>
                        <input type="email" name="email" placeholder="tucorreo@ejemplo.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="username" required>
                    </div>
                    <button type="submit" class="btn btn-block">Enviar enlace de recuperación →</button>
                </form>

                <p class="auth-footer">¿Te acordaste? <a href="<?php echo BASE_URL; ?>/auth/login.php">Volver a iniciar sesión</a></p>
            <?php endif; ?>

        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/tema.js"></script>
</body>
</html>
