<?php
// ============================================================
//  crear_admin.php
//  Script de arranque: crea el PRIMER administrador con una
//  contraseña aleatoria segura, UNA sola vez.
//
//  SEGURO POR DEFECTO: si ya existe algún administrador, este
//  script queda INERTE (no toca ninguna contraseña), así que
//  aunque quede en el servidor, nadie puede usarlo para pisar
//  las credenciales del admin. De todas formas, conviene
//  borrarlo o renombrarlo luego de crear el primer admin.
// ============================================================

require __DIR__ . '/../includes/sesion_segura.php';   // BASE_URL + cabeceras + CSP
require __DIR__ . '/../config/conexion.php';

$usuario_admin = "admin";
$estado = 'ok';          // ok | existe | error
$clave_plana = '';
$msg_error = '';

try {
    // Aseguramos que la tabla exista (no borra datos si ya está).
    $pdo->exec("CREATE TABLE IF NOT EXISTS administradores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL
    )");

    // ¿Ya hay administradores cargados? → el script queda inerte.
    $hay = (int)$pdo->query("SELECT COUNT(*) FROM administradores")->fetchColumn();

    if ($hay > 0) {
        $estado = 'existe';
    } else {
        // Primer arranque: creamos el admin con clave aleatoria.
        $clave_plana = bin2hex(random_bytes(5));                 // 10 caracteres
        $clave_hash  = password_hash($clave_plana, PASSWORD_BCRYPT);
        $st = $pdo->prepare("INSERT INTO administradores (usuario, password) VALUES (?, ?)");
        $st->execute([$usuario_admin, $clave_hash]);
        $estado = 'ok';
    }
} catch (PDOException $e) {
    $estado = 'error';
    $msg_error = $e->getMessage();
    error_log("crear_admin: " . $msg_error);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear administrador — TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
    <script src="<?php echo BASE_URL; ?>/assets/js/tema.js" defer></script>
</head>
<body>
    <main class="main-content" style="max-width:640px;margin-inline:auto;">
        <div class="page-header">
            <h1>Arranque de administrador 🔐</h1>
            <p>Creación del primer usuario administrador del sistema.</p>
        </div>

        <?php if ($estado === 'ok'): ?>
            <div class="card reveal">
                <div class="card-h">¡Administrador creado con credenciales únicas!</div>
                <p style="margin:.4rem 0;"><strong>Usuario:</strong> <?php echo htmlspecialchars($usuario_admin); ?></p>
                <p style="margin:.4rem 0;"><strong>Contraseña (se muestra una sola vez):</strong></p>
                <p><code style="font-size:1.25rem;letter-spacing:.5px;background:var(--primary-soft);color:var(--primary);padding:.3rem .6rem;border-radius:8px;"><?php echo htmlspecialchars($clave_plana); ?></code></p>
                <hr style="border:none;border-top:1px solid var(--border);margin:1rem 0;">
                <p>⚠️ Anotá esta contraseña ahora: <strong>no se vuelve a mostrar</strong>. Después podés cambiarla.</p>
                <p>🔒 Por seguridad, <strong>eliminá o renombrá</strong> <code>crear_admin.php</code>. Igual este script ya quedó inerte: no puede pisar la contraseña de un admin existente.</p>
                <a class="btn" href="<?php echo BASE_URL; ?>/admin/admin_login.php">Ir a iniciar sesión como Admin →</a>
            </div>

        <?php elseif ($estado === 'existe'): ?>
            <div class="card reveal">
                <div class="card-h">Este script está deshabilitado ✅</div>
                <p>Ya existe al menos un administrador en el sistema, así que <strong>crear_admin.php no hace nada</strong> (no toca ninguna contraseña). Es el comportamiento esperado y seguro.</p>
                <p>Si olvidaste la contraseña, cambiala desde la base de datos o desde otro admin; este script no sirve para recuperarla, justamente para que nadie pueda usarlo con ese fin.</p>
                <p>🔒 Recomendado: <strong>eliminá o renombrá</strong> <code>crear_admin.php</code> del servidor.</p>
                <a class="btn" href="<?php echo BASE_URL; ?>/admin/admin_login.php">Ir a iniciar sesión como Admin →</a>
            </div>

        <?php else: ?>
            <div class="card reveal">
                <div class="card-h">No se pudo crear el administrador</div>
                <p>Ocurrió un error al preparar el administrador. Revisá que la base de datos esté disponible.</p>
                <?php if (!empty($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1','::1'], true)): ?>
                    <p style="color:var(--danger);font-family:monospace;font-size:.85rem;"><?php echo htmlspecialchars($msg_error); ?></p>
                <?php endif; ?>
                <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/index.php">Volver al inicio</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
