<?php
// ============================================================
//  admin_login.php - Acceso de administradores
//  Seguridad: CSRF + bloqueo por intentos fallidos + auditoría.
// ============================================================

require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../includes/seguridad.php';

if (isset($_SESSION["admin_id"])) {
    header("Location: " . BASE_URL . "/admin/admin_panel.php");
    exit;
}

$mensaje = "";
if (isset($_GET['timeout'])) {
    $mensaje = "Tu sesión se cerró por inactividad. Volvé a entrar.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();

    $usuario = trim($_POST["usuario"]);
    $pass    = trim($_POST["password"]);
    $identidad = "admin:" . $usuario;   // etiqueta separada para el bloqueo

    $espera = segundos_bloqueo($pdo, $identidad);
    if ($espera > 0) {
        $min = ceil($espera / 60);
        $mensaje = "Demasiados intentos fallidos. Probá de nuevo en ~{$min} min.";
        registrar_evento($pdo, 'bloqueo', "Login admin bloqueado ({$usuario})", $usuario);
    } elseif ($usuario === "" || $pass === "") {
        $mensaje = "Completá usuario y contraseña.";
    } else {
        $sql = "SELECT * FROM administradores WHERE usuario = ?";
        $consulta = $pdo->prepare($sql);
        $consulta->execute([$usuario]);
        $admin = $consulta->fetch();

        // Si la cuenta está dada de baja (activo = 0), no deja entrar.
        $cuenta_activa = !isset($admin["activo"]) || (int)$admin["activo"] === 1;

        if ($admin && $cuenta_activa && password_verify($pass, $admin["password"])) {
            registrar_intento($pdo, $identidad, true);
            registrar_evento($pdo, 'login_ok', "Admin #{$admin['id']}", $usuario);

            session_regenerate_id(true);
            $_SESSION["admin_id"]         = $admin["id"];
            $_SESSION["admin_usuario"]    = $admin["usuario"];
            // Rol para el control de acceso (req 3). Compatibilidad: si la
            // columna no existiera aún, asumimos 'administrador'.
            $_SESSION["admin_rol"]        = $admin["rol"] ?? 'administrador';
            $_SESSION["ultima_actividad"] = time();

            header("Location: " . BASE_URL . "/admin/admin_panel.php");
            exit;
        } elseif ($admin && !$cuenta_activa && password_verify($pass, $admin["password"])) {
            registrar_intento($pdo, $identidad, false);
            registrar_evento($pdo, 'login_fallido', "Cuenta admin dada de baja", $usuario);
            $mensaje = "Esa cuenta está dada de baja. Pedile a un administrador que la reactive.";
        } else {
            registrar_intento($pdo, $identidad, false);
            registrar_evento($pdo, 'login_fallido', "Credenciales admin inválidas", $usuario);
            $mensaje = "Usuario o contraseña de administrador incorrectos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso del taller - TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <div class="auth-wrapper">
        <div class="auth-card admin-variant fade-in">

            <div style="display:flex; justify-content:flex-end; margin-bottom:-8px;">
                <button type="button" class="tema-toggle" aria-label="Cambiar tema">🌙</button>
            </div>
            <a href="<?php echo BASE_URL; ?>/auth/login.php" class="auth-back">← Volver al acceso de emprendedores</a>

            <div class="auth-brand">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span>TermoPlast</span>
            </div>
            <h1 class="auth-title">Acceso del taller 🛠️</h1>
            <p class="auth-subtitle">Gestión de producción de TermoPlast</p>

            <?php if ($mensaje !== ""): ?>
                <div class="alert alert-error fade-in">⚠️ <?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <form method="post" action="" autocomplete="off">
                <?php csrf_campo(); ?>
                <div class="form-group">
                    <label>Usuario Admin</label>
                    <!-- autocomplete off + name propio: el panel admin NO debe heredar
                         las credenciales de emprendedor guardadas en el navegador. -->
                    <input type="text" name="usuario" placeholder="admin" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <!-- "new-password" evita que el gestor autocomplete la clave guardada. -->
                    <input type="password" name="password" placeholder="••••••••" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-block">Ingresar al Panel de Control →</button>
            </form>

        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/tema.js"></script>
</body>
</html>
