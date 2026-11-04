<?php
// ============================================================
//  login.php - Inicio de sesión de emprendedores
//  Seguridad: CSRF + bloqueo por intentos fallidos + auditoría.
// ============================================================

require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../includes/seguridad.php';

if (isset($_SESSION["emprendedor_id"])) {
    header("Location: " . BASE_URL . "/pages/panel.php");
    exit;
}

$mensaje = "";
// Aviso si la sesión se cerró por inactividad.
if (isset($_GET['timeout'])) {
    $mensaje = "Tu sesión se cerró por inactividad. Volvé a entrar.";
}
// Aviso si se bloqueó la verificación en dos pasos por demasiados intentos.
if (isset($_GET['err']) && $_GET['err'] === '2fa') {
    $mensaje = "Demasiados intentos de verificación. Volvé a iniciar sesión.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();

    $email = trim($_POST["email"]);
    $pass  = trim($_POST["password"]);

    // ¿Está bloqueado por demasiados intentos fallidos?
    $espera = segundos_bloqueo($pdo, $email);
    if ($espera > 0) {
        $min = ceil($espera / 60);
        $mensaje = "Demasiados intentos fallidos. Probá de nuevo en ~{$min} min.";
        registrar_evento($pdo, 'bloqueo', "Login bloqueado para {$email}", $email);
    } elseif ($email === "" || $pass === "") {
        $mensaje = "Completá email y contraseña.";
    } else {
        $sql = "SELECT * FROM emprendedores WHERE email = ?";
        $consulta = $pdo->prepare($sql);
        $consulta->execute([$email]);
        $emprendedor = $consulta->fetch();

        if (!$emprendedor) {
            // Anti-enumeración: verificamos contra un hash señuelo para no
            // delatar por el tiempo si el email existe o no.
            password_verify($pass, hash_senuelo());
            registrar_intento($pdo, $email, false);
            registrar_evento($pdo, 'login_fallido', "Email inexistente", $email);
            $mensaje = "Email o contraseña incorrectos.";
        } elseif (password_verify($pass, $emprendedor["password"])) {
            registrar_intento($pdo, $email, true);

            // ¿Tiene activada la verificación en dos pasos (2FA)?
            if ((int)($emprendedor["dosfa"] ?? 0) === 1) {
                // Generamos un código de 6 dígitos, lo guardamos HASHEADO y con
                // vencimiento (5 min). La sesión queda "pendiente" hasta verificar.
                $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION["2fa_pendiente"] = [
                    "id"     => $emprendedor["id"],
                    "nombre" => $emprendedor["nombre"],
                    "hash"   => password_hash($codigo, PASSWORD_DEFAULT),
                    "exp"    => time() + 300,
                    "email"  => $email,
                ];
                // MODO DEMO: en vez de mandar un email, mostramos el código en
                // pantalla. En producción, acá se enviaría por correo (SMTP).
                $_SESSION["2fa_demo"] = $codigo;
                registrar_evento($pdo, '2fa_enviado', "Código 2FA generado", $email);

                header("Location: " . BASE_URL . "/auth/verificar_2fa.php");
                exit;
            }

            // Sin 2FA: ingreso directo.
            registrar_evento($pdo, 'login_ok', "Emprendedor #{$emprendedor['id']}", $email);
            session_regenerate_id(true);
            $_SESSION["emprendedor_id"]     = $emprendedor["id"];
            $_SESSION["emprendedor_nombre"] = $emprendedor["nombre"];
            $_SESSION["ultima_actividad"]   = time();

            header("Location: " . BASE_URL . "/pages/panel.php");
            exit;
        } else {
            registrar_intento($pdo, $email, false);
            registrar_evento($pdo, 'login_fallido', "Credenciales inválidas", $email);
            $mensaje = "Email o contraseña incorrectos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - TermoPlast</title>
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
            <h1 class="auth-title">Iniciar sesión</h1>
            <p class="auth-subtitle">Accedé a tu cuenta de emprendedor</p>

            <?php if ($mensaje !== ""): ?>
                <div class="alert alert-error fade-in">⚠️ <?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php csrf_campo(); ?>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="tucorreo@ejemplo.com" autocomplete="username" required>
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-block">Entrar →</button>
            </form>

            <p class="auth-footer" style="margin-top:14px;"><a href="<?php echo BASE_URL; ?>/auth/recuperar.php">¿Olvidaste tu contraseña?</a></p>
            <p class="auth-footer">¿No tenés cuenta? <a href="<?php echo BASE_URL; ?>/auth/registro_emprendedor.php">Registrate acá</a></p>

            <hr class="auth-divider">
            <p class="auth-footer">
                <small>¿Sos operador del horno? <a href="<?php echo BASE_URL; ?>/admin/admin_login.php">Ingresar como administrador 🛠️</a></small>
            </p>

        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/tema.js"></script>
</body>
</html>
