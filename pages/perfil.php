<?php
// ============================================================
//  perfil.php - Perfil del emprendedor
//  Permite editar TODOS los datos (nombre, email) y la contraseña,
//  con medidas de seguridad:
//   - Token CSRF en cada formulario.
//   - Re-autenticación: para cambiar datos sensibles hay que
//     confirmar la contraseña actual.
//   - Email validado y verificado que no esté en uso por otro.
// ============================================================
require __DIR__ . '/../includes/proteger.php';
require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../includes/seguridad.php';

$mensaje = "";
$tipo_alerta = "";

// ¿La base ya tiene las columnas del perfil ampliado?
// (migración sql/8_perfil.sql). Si no se aplicó, la página sigue
// funcionando igual que antes: solo muestra nombre/email.
$perfil_ampliado = false;
try {
    $pdo->query("SELECT marca, rubro, descripcion, telefono, web, localidad FROM emprendedores LIMIT 0");
    $perfil_ampliado = true;
} catch (PDOException $e) {
    $perfil_ampliado = false;
}

// ------------------------------------------------------------
// A) ACTUALIZAR DATOS DE LA CUENTA (nombre + email + emprendimiento)
//    Requiere confirmar la contraseña actual (re-autenticación).
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['guardar_datos'])) {
    csrf_validar();

    $nombre    = trim($_POST['nombre'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $confirmar = $_POST['password_confirmar'] ?? '';

    // Datos del emprendimiento (solo si la base los soporta).
    $marca       = trim($_POST['marca'] ?? '');
    $rubro       = trim($_POST['rubro'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $telefono    = trim($_POST['telefono'] ?? '');
    $web         = trim($_POST['web'] ?? '');
    $localidad   = trim($_POST['localidad'] ?? '');

    // Traemos el hash actual para re-autenticar.
    $q = $pdo->prepare("SELECT password FROM emprendedores WHERE id = ?");
    $q->execute([$_SESSION['emprendedor_id']]);
    $hash_actual = $q->fetchColumn();

    if (!password_verify($confirmar, $hash_actual)) {
        $mensaje = "Para cambiar tus datos, confirmá tu contraseña actual correctamente.";
        $tipo_alerta = "error";
    } elseif ($nombre === "" || mb_strlen($nombre) > 100) {
        $mensaje = "El nombre no puede estar vacío ni superar los 100 caracteres.";
        $tipo_alerta = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "El email no tiene un formato válido.";
        $tipo_alerta = "error";
    } elseif ($perfil_ampliado && mb_strlen($descripcion) > 500) {
        $mensaje = "La descripción no puede superar los 500 caracteres.";
        $tipo_alerta = "error";
    } elseif ($web !== "" && !filter_var($web, FILTER_VALIDATE_URL) && !preg_match('#^[\w.-]+\.[a-z]{2,}#i', $web)) {
        $mensaje = "El sitio web no tiene un formato válido (ej.: https://mimarca.com).";
        $tipo_alerta = "error";
    } else {
        // ¿El email ya lo usa OTRO emprendedor?
        $dup = $pdo->prepare("SELECT id FROM emprendedores WHERE email = ? AND id <> ?");
        $dup->execute([$email, $_SESSION['emprendedor_id']]);
        if ($dup->fetch()) {
            $mensaje = "Ese email ya está registrado por otra cuenta.";
            $tipo_alerta = "error";
        } else {
            if ($perfil_ampliado) {
                $upd = $pdo->prepare(
                    "UPDATE emprendedores
                        SET nombre = ?, email = ?, marca = ?, rubro = ?,
                            descripcion = ?, telefono = ?, web = ?, localidad = ?
                      WHERE id = ?"
                );
                $upd->execute([
                    $nombre, $email,
                    ($marca === '' ? null : $marca),
                    ($rubro === '' ? null : $rubro),
                    ($descripcion === '' ? null : $descripcion),
                    ($telefono === '' ? null : $telefono),
                    ($web === '' ? null : $web),
                    ($localidad === '' ? null : $localidad),
                    $_SESSION['emprendedor_id']
                ]);
            } else {
                $upd = $pdo->prepare("UPDATE emprendedores SET nombre = ?, email = ? WHERE id = ?");
                $upd->execute([$nombre, $email, $_SESSION['emprendedor_id']]);
            }
            // Reflejamos el nombre nuevo en la sesión (lo usa el header).
            $_SESSION['emprendedor_nombre'] = $nombre;
            registrar_evento($pdo, 'cambio_datos', "Actualizó datos de cuenta/emprendimiento", $email);
            $mensaje = "¡Datos actualizados correctamente!";
            $tipo_alerta = "success";
        }
    }
}

// ------------------------------------------------------------
// B) CAMBIAR CONTRASEÑA
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['cambiar_password'])) {
    csrf_validar();

    $actual  = $_POST['password_actual']  ?? '';
    $nueva   = $_POST['password_nueva']   ?? '';
    $repetir = $_POST['password_repetir'] ?? '';

    $q = $pdo->prepare("SELECT password FROM emprendedores WHERE id = ?");
    $q->execute([$_SESSION['emprendedor_id']]);
    $hash_actual = $q->fetchColumn();

    if (!password_verify($actual, $hash_actual)) {
        $mensaje = "La contraseña actual no es correcta.";
        $tipo_alerta = "error";
    } elseif (strlen($nueva) < 8) {
        $mensaje = "La nueva contraseña debe tener al menos 8 caracteres.";
        $tipo_alerta = "error";
    } elseif (fuerza_password($nueva) < 2) {
        // Mismo criterio que el registro y el reseteo (política única).
        $mensaje = "La nueva contraseña es demasiado débil. Combiná mayúsculas, minúsculas, números o símbolos.";
        $tipo_alerta = "error";
    } elseif ($nueva !== $repetir) {
        $mensaje = "La nueva contraseña y su repetición no coinciden.";
        $tipo_alerta = "error";
    } elseif ($nueva === $actual) {
        $mensaje = "La nueva contraseña debe ser distinta de la actual.";
        $tipo_alerta = "error";
    } else {
        $nuevo_hash = password_hash($nueva, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE emprendedores SET password = ? WHERE id = ?");
        $upd->execute([$nuevo_hash, $_SESSION['emprendedor_id']]);
        registrar_evento($pdo, 'cambio_password', "El emprendedor cambió su contraseña", (string)$_SESSION['emprendedor_id']);
        $mensaje = "¡Contraseña actualizada correctamente!";
        $tipo_alerta = "success";
    }
}

// ------------------------------------------------------------
// C) ACTIVAR / DESACTIVAR verificación en dos pasos (2FA)
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['toggle_2fa'])) {
    csrf_validar();
    $activar = ($_POST['toggle_2fa'] === 'activar') ? 1 : 0;
    $upd = $pdo->prepare("UPDATE emprendedores SET dosfa = ? WHERE id = ?");
    $upd->execute([$activar, $_SESSION['emprendedor_id']]);
    registrar_evento($pdo, '2fa_config',
        $activar ? "Activó verificación en dos pasos" : "Desactivó verificación en dos pasos",
        (string)$_SESSION['emprendedor_id']);
    $mensaje = $activar
        ? "Verificación en dos pasos ACTIVADA. La próxima vez que entres te pediremos un código."
        : "Verificación en dos pasos desactivada.";
    $tipo_alerta = "success";
}

// Traemos los datos actuales para mostrarlos en el formulario.
$cols = $perfil_ampliado
    ? "nombre, email, dosfa, marca, rubro, descripcion, telefono, web, localidad"
    : "nombre, email, dosfa";
$stmt = $pdo->prepare("SELECT $cols FROM emprendedores WHERE id = ?");
$stmt->execute([$_SESSION['emprendedor_id']]);
$yo = $stmt->fetch();

// Mini-resumen de actividad e impacto (solo lectura). Con fallback a 0.
$mi_kg = 0.0; $mi_co2 = 0.0; $mi_piezas = 0; $mi_pedidos = 0;
try {
    $eid = $_SESSION['emprendedor_id'];

    $q = $pdo->prepare("SELECT COALESCE(SUM(gramos_totales),0)/1000 FROM pedidos WHERE emprendedor_id = ? AND estado = 'finalizado'");
    $q->execute([$eid]);
    $mi_kg = (float)$q->fetchColumn();

    $q = $pdo->prepare(
        "SELECT COALESCE(SUM(p.gramos_totales * m.co2_por_gramo),0)
           FROM pedidos p INNER JOIN materiales m ON p.material_id = m.id
          WHERE p.emprendedor_id = ? AND p.estado = 'finalizado'"
    );
    $q->execute([$eid]);
    $mi_co2 = (float)$q->fetchColumn();

    $q = $pdo->prepare("SELECT COALESCE(SUM(cantidad),0) FROM pedidos WHERE emprendedor_id = ? AND estado = 'finalizado'");
    $q->execute([$eid]);
    $mi_piezas = (int)$q->fetchColumn();

    $q = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE emprendedor_id = ?");
    $q->execute([$eid]);
    $mi_pedidos = (int)$q->fetchColumn();
} catch (PDOException $e) { /* dejamos los ceros */ }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <?php $pagina_activa = ""; include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Mi Perfil 👤</h1>
            <p>Editá los datos de tu cuenta y tu emprendimiento, revisá tu impacto y gestioná tu seguridad.</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_alerta === 'success' ? 'success' : 'error'; ?> fade-in">
                <?php echo ($tipo_alerta === 'success' ? '✅ ' : '⚠️ ') . htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <div class="order-grid">
            <!-- Resumen de actividad e impacto (solo lectura) -->
            <div class="card">
                <h3 style="margin-top:0;">Tu actividad e impacto 🌱</h3>
                <p style="font-size:0.9rem; color:var(--text-muted); margin-top:0;">
                    Un vistazo rápido a lo que ya produjiste con TermoPlast.
                </p>
                <div class="perfil-stats">
                    <div class="perfil-stat">
                        <div class="ps-num"><?php echo number_format($mi_kg, 1); ?><small> kg</small></div>
                        <div class="ps-lbl">Plástico recuperado</div>
                    </div>
                    <div class="perfil-stat">
                        <div class="ps-num"><?php echo number_format($mi_co2, 1); ?><small> kg</small></div>
                        <div class="ps-lbl">CO₂ evitado</div>
                    </div>
                    <div class="perfil-stat">
                        <div class="ps-num"><?php echo number_format($mi_piezas); ?></div>
                        <div class="ps-lbl">Piezas fabricadas</div>
                    </div>
                    <div class="perfil-stat">
                        <div class="ps-num"><?php echo number_format($mi_pedidos); ?></div>
                        <div class="ps-lbl">Pedidos en total</div>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>/pages/impacto.php" class="btn btn-secondary btn-block" style="margin-top:1rem;">Ver mi impacto en detalle →</a>
            </div>

            <!-- A) Datos de la cuenta y del emprendimiento -->
            <form method="post" action="" class="card">
                <h3 style="margin-top:0;">Datos de la cuenta<?php echo $perfil_ampliado ? ' y emprendimiento' : ''; ?></h3>
                <?php csrf_campo(); ?>
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" maxlength="100" required
                           value="<?php echo htmlspecialchars($yo['nombre']); ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required
                           value="<?php echo htmlspecialchars($yo['email']); ?>">
                </div>

                <?php if ($perfil_ampliado): ?>
                <hr style="border:none; border-top:1px solid var(--border); margin:1.2rem 0;">
                <p style="font-size:0.85rem; color:var(--text-muted); margin:0 0 .8rem;">
                    Contanos sobre tu emprendimiento. Estos datos nos ayudan a acompañarte mejor y son opcionales.
                </p>
                <div class="form-group">
                    <label>Marca / nombre del emprendimiento</label>
                    <input type="text" name="marca" maxlength="120"
                           value="<?php echo htmlspecialchars($yo['marca'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Rubro</label>
                    <input type="text" name="rubro" maxlength="80" placeholder="Ej.: deco, bijouterie, gastronomía…"
                           value="<?php echo htmlspecialchars($yo['rubro'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Descripción (hasta 500 caracteres)</label>
                    <textarea name="descripcion" maxlength="500" rows="3"
                              placeholder="¿Qué hacés y qué buscás producir en plástico reciclado?"><?php echo htmlspecialchars($yo['descripcion'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Teléfono / WhatsApp</label>
                    <input type="text" name="telefono" maxlength="40"
                           value="<?php echo htmlspecialchars($yo['telefono'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Sitio web o red social</label>
                    <input type="text" name="web" maxlength="150" placeholder="https://… o @tumarca"
                           value="<?php echo htmlspecialchars($yo['web'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Localidad</label>
                    <input type="text" name="localidad" maxlength="120"
                           value="<?php echo htmlspecialchars($yo['localidad'] ?? ''); ?>">
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Confirmá tu contraseña actual para guardar</label>
                    <input type="password" name="password_confirmar" autocomplete="current-password" required>
                </div>
                <button type="submit" name="guardar_datos" class="btn btn-block">Guardar cambios</button>
            </form>

            <!-- B) Cambiar contraseña -->
            <form method="post" action="" class="card">
                <h3 style="margin-top:0;">Cambiar contraseña</h3>
                <?php csrf_campo(); ?>
                <div class="form-group">
                    <label>Contraseña actual</label>
                    <input type="password" name="password_actual" autocomplete="current-password" required>
                </div>
                <div class="form-group">
                    <label>Nueva contraseña (mínimo 8 caracteres)</label>
                    <input type="password" name="password_nueva" class="pw-fuerza" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label>Repetir nueva contraseña</label>
                    <input type="password" name="password_repetir" minlength="8" autocomplete="new-password" required>
                </div>
                <button type="submit" name="cambiar_password" class="btn btn-block">Actualizar contraseña</button>
            </form>

            <div class="card">
                <h3 style="margin-top:0;">Verificación en dos pasos (2FA)</h3>
                <p style="font-size:0.9rem; color:var(--text-muted);">
                    Suma un código de un solo uso al iniciar sesión. Estado actual:
                    <strong style="color:<?php echo ((int)$yo['dosfa'] === 1) ? 'var(--success-text)' : 'var(--warning-text)'; ?>;">
                        <?php echo ((int)$yo['dosfa'] === 1) ? 'ACTIVADA' : 'Desactivada'; ?>
                    </strong>.
                </p>
                <form method="post" action="">
                    <?php csrf_campo(); ?>
                    <?php if ((int)$yo['dosfa'] === 1): ?>
                        <button type="submit" name="toggle_2fa" value="desactivar" class="btn btn-secondary btn-block">Desactivar 2FA</button>
                    <?php else: ?>
                        <button type="submit" name="toggle_2fa" value="activar" class="btn btn-block">Activar 2FA</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="<?php echo BASE_URL; ?>/assets/js/password_fuerza.js"></script>
</body>
</html>
