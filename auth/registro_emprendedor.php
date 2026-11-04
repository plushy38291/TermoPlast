<?php
// ============================================================
//  registro_emprendedor.php
//  Formulario de registro de emprendedores (maquetado + seguridad).
//  Al registrarse con éxito, inicia sesión y entra directo al panel.
// ============================================================

require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../includes/seguridad.php';

// SEGURIDAD: si ya está logueado, no tiene sentido registrarse de nuevo.
if (isset($_SESSION["emprendedor_id"])) {
    header("Location: " . BASE_URL . "/pages/panel.php");
    exit;
}

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    csrf_validar();   // Anti-CSRF: verificamos el token antes de seguir.

    // ANTI-ABUSO: limitamos los registros por red (IP) para frenar bots/scripts.
    // Reutilizamos el mismo bloqueo del login, con una etiqueta propia.
    $ident_reg = "registro:" . ip_cliente();
    $bloqueado_reg = segundos_bloqueo($pdo, $ident_reg) > 0;
    if (!$bloqueado_reg) { registrar_intento($pdo, $ident_reg, false); }

    // 1) Recibimos y limpiamos
    $nombre = trim($_POST["nombre"]);
    $email  = trim($_POST["email"]);
    $pass   = trim($_POST["password"]);

    // 2) Validaciones
    if ($bloqueado_reg) {
        $mensaje = "Hiciste demasiados intentos de registro desde esta red. Esperá unos minutos e intentá de nuevo.";
    } elseif ($nombre === "" || $email === "" || $pass === "") {
        $mensaje = "Por favor completá todos los campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Chequeo de que el email tenga formato válido
        $mensaje = "El email no tiene un formato válido.";
    } elseif (strlen($pass) < 8) {
        $mensaje = "La contraseña debe tener al menos 8 caracteres.";
    } elseif (fuerza_password($pass) < 2) {
        $mensaje = "La contraseña es demasiado débil. Combiná mayúsculas, minúsculas, números o símbolos.";
    } else {
        // 3) Encriptamos la contraseña (nunca se guarda en texto plano)
        $pass_segura = password_hash($pass, PASSWORD_DEFAULT);

        // 4) Consulta preparada (protección contra inyección SQL)
        $sql = "INSERT INTO emprendedores (nombre, email, password)
                VALUES (?, ?, ?)";
        try {
            $consulta = $pdo->prepare($sql);
            $consulta->execute([$nombre, $email, $pass_segura]);

            // 5) Registro exitoso: iniciamos sesión automáticamente.
            //    Regeneramos el ID de sesión (anti session fixation).
            session_regenerate_id(true);
            $_SESSION["emprendedor_id"]     = $pdo->lastInsertId();
            $_SESSION["emprendedor_nombre"] = $nombre;

            header("Location: " . BASE_URL . "/pages/panel.php");
            exit;

        } catch (PDOException $e) {
            // El email es UNIQUE: si ya existe, caemos acá.
            $mensaje = "Ese email ya está registrado. Probá iniciar sesión.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta - TermoPlast</title>
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
            <h1 class="auth-title">Crear cuenta</h1>
            <p class="auth-subtitle">Registrate para empezar a fabricar tus piezas</p>

            <?php if ($mensaje !== ""): ?>
                <div class="alert alert-error fade-in">⚠️ <?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php csrf_campo(); ?>
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" placeholder="Tu nombre o el de tu emprendimiento"
                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" autocomplete="name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="tucorreo@ejemplo.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="username" required>
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" class="pw-fuerza" placeholder="Mínimo 8 caracteres" minlength="8" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-block">Crear cuenta →</button>
            </form>

            <p class="auth-footer">¿Ya tenés cuenta? <a href="<?php echo BASE_URL; ?>/auth/login.php">Iniciá sesión</a></p>

        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/password_fuerza.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/tema.js"></script>
</body>
</html>