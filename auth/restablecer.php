<?php
// ============================================================
//  restablecer.php — "Olvidé mi contraseña" (paso 2: nueva clave)
//
//  Seguridad:
//   - El token se valida en GET y OTRA VEZ en POST (pudo vencer
//     o usarse en el medio).
//   - Token de UN SOLO USO: al cambiar la clave, se marca usado.
//   - Misma exigencia de fuerza que el registro (>=8 y fuerza>=2).
//   - La clave se guarda con password_hash (nunca en texto plano).
// ============================================================

require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../includes/seguridad.php';

if (isset($_SESSION["emprendedor_id"])) {
    header("Location: " . BASE_URL . "/pages/panel.php");
    exit;
}

reset_limpiar($pdo);

$token    = $_POST['token'] ?? $_GET['token'] ?? '';
$valido   = reset_token_valido($pdo, $token);   // null si no sirve
$mensaje  = "";
$ok_final = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();

    if (!$valido) {
        $mensaje = "El enlace no es válido o ya venció. Pedí uno nuevo.";
    } else {
        $pass  = trim($_POST["password"] ?? "");
        $pass2 = trim($_POST["password2"] ?? "");

        if ($pass === "" || $pass2 === "") {
            $mensaje = "Completá y repetí la nueva contraseña.";
        } elseif ($pass !== $pass2) {
            $mensaje = "Las contraseñas no coinciden.";
        } elseif (strlen($pass) < 8) {
            $mensaje = "La contraseña debe tener al menos 8 caracteres.";
        } elseif (fuerza_password($pass) < 2) {
            $mensaje = "La contraseña es demasiado débil. Combiná mayúsculas, minúsculas, números o símbolos.";
        } else {
            try {
                $pdo->beginTransaction();
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $up = $pdo->prepare("UPDATE emprendedores SET password = ? WHERE id = ?");
                $up->execute([$hash, (int)$valido["emprendedor_id"]]);
                reset_marcar_usado($pdo, (int)$valido["id"]);   // un solo uso
                $pdo->commit();

                registrar_evento($pdo, 'reset_completado',
                    "Contraseña restablecida (emprendedor #{$valido['emprendedor_id']})");
                $ok_final = true;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $mensaje = "No se pudo actualizar la contraseña. Probá de nuevo.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña - TermoPlast</title>
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
            <h1 class="auth-title">Nueva contraseña</h1>

            <?php if ($ok_final): ?>
                <div class="alert alert-success fade-in">✅ ¡Listo! Tu contraseña se actualizó. Ya podés iniciar sesión.</div>
                <p class="auth-footer" style="margin-top:18px;"><a href="<?php echo BASE_URL; ?>/auth/login.php">Ir a iniciar sesión →</a></p>

            <?php elseif (!$valido): ?>
                <div class="alert alert-error fade-in">⚠️ El enlace no es válido o ya venció. Los enlaces duran <?php echo RESET_MINUTOS; ?> minutos y se usan una sola vez.</div>
                <p class="auth-footer" style="margin-top:18px;"><a href="<?php echo BASE_URL; ?>/auth/recuperar.php">Pedir un enlace nuevo →</a></p>

            <?php else: ?>
                <p class="auth-subtitle">Elegí una contraseña nueva para tu cuenta.</p>

                <?php if ($mensaje !== ""): ?>
                    <div class="alert alert-error fade-in">⚠️ <?php echo htmlspecialchars($mensaje); ?></div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php csrf_campo(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label>Nueva contraseña</label>
                        <input type="password" name="password" class="pw-fuerza" placeholder="Mínimo 8 caracteres" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <label>Repetir contraseña</label>
                        <input type="password" name="password2" placeholder="Repetí la contraseña" minlength="8" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-block">Guardar nueva contraseña →</button>
                </form>
            <?php endif; ?>

        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/password_fuerza.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/tema.js"></script>
</body>
</html>
