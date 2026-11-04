<?php
// ============================================================
//  admin_panel.php
//  A) Aprobar / rechazar diseños (el rechazo ahora exige un motivo).
//  B) Administrar la cola de pedidos y avanzar su estado.
// ============================================================

require __DIR__ . '/../includes/proteger_admin.php';
require_once __DIR__ . '/../includes/sesion_segura.php';   // deja las funciones csrf_* visibles para el editor
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';   // $ESTADOS_PEDIDO, $ESTADOS_DISENO, $NOMBRE_LINDO
require __DIR__ . '/../includes/funciones.php';  // registrar_historial()

$mensaje = "";

// Anti-CSRF: validamos el token antes de procesar cualquiera de los dos formularios.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validar();
}

// ------------------------------------------------------------
// A) APROBAR / RECHAZAR UN DISEÑO
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["actualizar_diseno"])) {

    $diseno_id     = filter_input(INPUT_POST, "diseno_id", FILTER_VALIDATE_INT);
    $estado_diseno = trim($_POST["estado_diseno"] ?? "");
    $motivo        = trim($_POST["motivo_rechazo"] ?? "");

    if ($diseno_id && array_key_exists($estado_diseno, $ESTADOS_DISENO)) {

        // Si se RECHAZA, el motivo es obligatorio.
        if ($estado_diseno === 'rechazado' && $motivo === "") {
            $mensaje = "Para rechazar un diseño tenés que escribir un motivo.";
        } else {
            // Si se aprueba, el motivo queda en NULL (por si venía de un rechazo previo).
            $motivo_guardar = ($estado_diseno === 'rechazado') ? $motivo : null;
            try {
                $stmt = $pdo->prepare("UPDATE disenos SET estado = ?, motivo_rechazo = ? WHERE id = ?");
                $stmt->execute([$estado_diseno, $motivo_guardar, $diseno_id]);
                $mensaje = "Diseño #{$diseno_id} actualizado a '{$ESTADOS_DISENO[$estado_diseno]}'.";
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar el diseño.";
            }
        }
    } else {
        $mensaje = "Estado de diseño no válido.";
    }
}

// ------------------------------------------------------------
// B) ACTUALIZAR EL ESTADO DE UN PEDIDO
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["actualizar_estado"])) {

    $pedido_id    = filter_input(INPUT_POST, "pedido_id", FILTER_VALIDATE_INT);
    $nuevo_estado = trim($_POST["nuevo_estado"] ?? "");

    if ($pedido_id && array_key_exists($nuevo_estado, $ESTADOS_PEDIDO)) {
        try {
            // Primero miramos el estado actual: solo registramos en el
            // historial si REALMENTE cambia (evita filas repetidas si el
            // admin aprieta "Guardar" sin tocar el selector).
            $chk = $pdo->prepare("SELECT estado FROM pedidos WHERE id = ?");
            $chk->execute([$pedido_id]);
            $estado_previo = $chk->fetchColumn();

            if ($estado_previo === false) {
                $mensaje = "El pedido #{$pedido_id} no existe.";
            } elseif ($estado_previo === $nuevo_estado) {
                $mensaje = "El pedido #{$pedido_id} ya estaba en '{$ESTADOS_PEDIDO[$nuevo_estado]}'.";
            } else {
                // UPDATE + registro de trazabilidad como una sola operación.
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
                $stmt->execute([$nuevo_estado, $pedido_id]);
                registrar_historial(
                    $pdo, $pedido_id, $nuevo_estado, null,
                    $_SESSION["admin_usuario"] ?? 'Administrador'
                );
                $pdo->commit();
                $mensaje = "¡Pedido #{$pedido_id} actualizado a '{$ESTADOS_PEDIDO[$nuevo_estado]}'!";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $mensaje = "Error al actualizar el estado del pedido.";
        }
    } else {
        $mensaje = "Estado de pedido no válido.";
    }
}

// ------------------------------------------------------------
// KPIs DEL SISTEMA
// ------------------------------------------------------------
try {
    $sqlKg = "SELECT COALESCE(SUM(gramos_totales), 0) / 1000 AS total_kg
              FROM pedidos WHERE estado = 'finalizado'";
    $totalKg = number_format($pdo->query($sqlKg)->fetchColumn(), 2);

    $sqlActivos = "SELECT COUNT(*) FROM pedidos
                   WHERE estado IN ('registrado', 'en_calentamiento', 'moldeado')";
    $pedidosActivos = $pdo->query($sqlActivos)->fetchColumn();

    $sqlFinalizados = "SELECT COUNT(*) FROM pedidos WHERE estado = 'finalizado'";
    $pedidosFinalizados = $pdo->query($sqlFinalizados)->fetchColumn();
} catch (PDOException $e) {
    $totalKg = "0.00";
    $pedidosActivos = 0;
    $pedidosFinalizados = 0;
}

// ------------------------------------------------------------
// CONSULTA 1: Diseños pendientes de aprobación
// ------------------------------------------------------------
$sqlDisenos = "SELECT d.id, d.nombre, d.tipo_objeto, d.archivo_svg,
                      d.ancho_mm, d.largo_mm, d.espesor_mm,
                      d.estado, d.fecha_subida,
                      e.nombre AS emprendedor_nombre
               FROM disenos d
               INNER JOIN emprendedores e ON d.emprendedor_id = e.id
               WHERE d.estado = 'en_revision'
               ORDER BY d.fecha_subida ASC";
try {
    $disenos_pendientes = $pdo->query($sqlDisenos)->fetchAll();
} catch (PDOException $e) {
    $disenos_pendientes = [];
    $mensaje = "Error al consultar los diseños pendientes.";
}

// ------------------------------------------------------------
// FILTROS DE LA COLA (vienen por GET, con lista blanca)
// ------------------------------------------------------------
$f_estado = $_GET['estado'] ?? '';
if (!array_key_exists($f_estado, $ESTADOS_PEDIDO)) $f_estado = '';   // solo estados válidos
$f_q = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($f_estado !== '') { $where[] = "p.estado = ?"; $params[] = $f_estado; }
if ($f_q !== '') {
    // Si es numérico, también busca por # de pedido; siempre por nombre/email.
    if (ctype_digit($f_q)) {
        $where[] = "(p.id = ? OR e.nombre LIKE ? OR e.email LIKE ?)";
        $params[] = (int)$f_q; $params[] = "%$f_q%"; $params[] = "%$f_q%";
    } else {
        $where[] = "(e.nombre LIKE ? OR e.email LIKE ?)";
        $params[] = "%$f_q%"; $params[] = "%$f_q%";
    }
}
$sqlWhere = count($where) ? (" WHERE " . implode(" AND ", $where)) : "";

// ------------------------------------------------------------
// CONSULTA 2: Lista general de pedidos (con filtros aplicados)
// ------------------------------------------------------------
$sqlPedidos = "SELECT
                    p.id AS pedido_id, p.cantidad, p.gramos_totales, p.estado,
                    COALESCE(e.nombre, '(emprendedor eliminado)') AS emprendedor_nombre,
                    COALESCE(e.email, '') AS emprendedor_email,
                    d.id AS diseno_id,
                    COALESCE(d.tipo_objeto, 'desconocido') AS tipo_objeto, d.ancho_mm, d.largo_mm, d.archivo_svg,
                    COALESCE(m.nombre, '(material eliminado)') AS material_nombre
               FROM pedidos p
               LEFT JOIN emprendedores e ON p.emprendedor_id = e.id
               LEFT JOIN disenos d       ON p.diseno_id = d.id
               LEFT JOIN materiales m     ON p.material_id = m.id"
             . $sqlWhere .
             " ORDER BY p.id DESC";
try {
    $stmtPed = $pdo->prepare($sqlPedidos);
    $stmtPed->execute($params);
    $pedidos = $stmtPed->fetchAll();
} catch (PDOException $e) {
    $pedidos = [];
    $mensaje = "Error al consultar los pedidos en la base de datos."
             . (!empty($DEV) ? " [DEBUG: " . $e->getMessage() . "]" : "");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <?php $pagina_activa = 'panel'; include __DIR__ . '/../includes/admin_header.php'; ?>

    <main class="main-content">
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Plástico Procesado</div>
            <div class="kpi-value"><?php echo $totalKg; ?> Kg</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Pedidos en Cola</div>
            <div class="kpi-value"><?php echo $pedidosActivos; ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Moldes Finalizados</div>
            <div class="kpi-value"><?php echo $pedidosFinalizados; ?></div>
        </div>
    </div>

    <!-- SECCIÓN A: DISEÑOS PENDIENTES -->
    <h1>Diseños pendientes de aprobación 🎨</h1>

    <?php if (count($disenos_pendientes) === 0): ?>
        <div class="card"><p style="margin: 0;">No hay diseños esperando revisión. ✅</p></div>
    <?php else: ?>
        <div style="overflow-x:auto;"><table>
            <thead>
                <tr>
                    <th># Diseño</th><th>Emprendedor</th><th>Nombre / Tipo</th>
                    <th>Dimensiones (mm)</th><th>Archivo</th><th>Fecha</th><th>Decisión</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($disenos_pendientes as $d): ?>
                    <tr>
                        <td><strong>#<?php echo $d['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($d['emprendedor_nombre']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($d['nombre']); ?></strong><br>
                            <small><?php echo htmlspecialchars($NOMBRE_LINDO[$d['tipo_objeto']] ?? $d['tipo_objeto']); ?></small>
                        </td>
                        <td><?php echo $d['ancho_mm']; ?> x <?php echo $d['largo_mm']; ?> x <?php echo $d['espesor_mm']; ?></td>
                        <td>
                            <?php if (!empty($d['archivo_svg'])): ?>
                                <a href="<?php echo BASE_URL . "/pages/ver_diseno.php?id=" . (int)$d['id']; ?>" target="_blank" rel="noopener">🔍 Ver SVG</a>
                            <?php else: ?><small>Sin archivo</small><?php endif; ?>
                        </td>
                        <td><?php echo date("d/m/Y", strtotime($d['fecha_subida'])); ?></td>
                        <td>
                            <!-- data-diseno guarda el id para que el JS sepa a cuál abrirle el motivo -->
                            <form method="post" action="" class="form-decision"
                                  style="display:flex; flex-direction:column; gap:8px; padding:0; background:none; border:none; box-shadow:none; margin:0; max-width:none;">
                                <?php csrf_campo(); ?>
                                <input type="hidden" name="diseno_id" value="<?php echo $d['id']; ?>">
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <select name="estado_diseno" class="select-decision" style="padding:6px 10px; font-size:0.85rem;">
                                        <option value="aprobado">Aprobar</option>
                                        <option value="rechazado">Rechazar</option>
                                    </select>
                                    <button type="submit" name="actualizar_diseno" style="padding:6px 14px; font-size:0.85rem; width:auto;">Aplicar</button>
                                </div>
                                <!-- El campo de motivo aparece solo si se elige "Rechazar" -->
                                <input type="text" name="motivo_rechazo" class="input-motivo"
                                       placeholder="Motivo del rechazo..."
                                       style="display:none; padding:6px 10px; font-size:0.85rem;">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>

    <!-- SECCIÓN B: COLA DE PEDIDOS -->
    <h1 style="margin-top: 36px;">Cola de Fabricación de Pedidos 🛠️</h1>

    <!-- Exportación del listado (requisito 5) -->
    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
        <span style="align-self:center; color:var(--text-soft); font-size:0.9rem;">Exportar pedidos:</span>
        <a class="btn btn-secondary" style="width:auto; padding:7px 14px;" href="<?php echo BASE_URL; ?>/admin/exportar.php?tipo=pedidos&formato=csv">⬇️ CSV</a>
        <a class="btn btn-secondary" style="width:auto; padding:7px 14px;" href="<?php echo BASE_URL; ?>/admin/exportar.php?tipo=pedidos&formato=pdf">⬇️ PDF</a>
    </div>

    <form method="get" action="" class="filtros-bar">
        <div class="campo">
            <label for="fEstado">Estado</label>
            <select name="estado" id="fEstado">
                <option value="">Todos</option>
                <?php foreach ($ESTADOS_PEDIDO as $clave => $texto): ?>
                    <option value="<?php echo $clave; ?>" <?php echo ($f_estado === $clave) ? 'selected' : ''; ?>><?php echo $texto; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="fQ">Buscar (# de pedido o emprendedor)</label>
            <input type="text" name="q" id="fQ" value="<?php echo htmlspecialchars($f_q); ?>" placeholder="Ej: 12 o Juana">
        </div>
        <button type="submit" class="btn">Filtrar</button>
        <?php if ($f_estado !== '' || $f_q !== ''): ?>
            <a href="<?php echo BASE_URL; ?>/admin/admin_panel.php" class="btn btn-secondary">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php if (count($pedidos) === 0): ?>
        <div class="card"><p style="margin: 0;"><?php echo ($f_estado !== '' || $f_q !== '') ? 'No hay pedidos que coincidan con el filtro.' : 'No hay pedidos registrados en el sistema.'; ?></p></div>
    <?php else: ?>
        <div style="overflow-x:auto;"><table class="tabla-cards">
            <thead>
                <tr>
                    <th># Pedido</th><th>Emprendedor</th><th>Diseño / Molde</th>
                    <th>Dimensiones</th><th>Material</th><th>Piezas</th>
                    <th>Peso Total</th><th>Estado Actual</th><th>Acciones</th><th>Trazabilidad</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $p): ?>
                    <?php
                        $estadoActual = $p['estado'] ?? 'registrado';
                        $claseBadge = clase_badge_pedido($estadoActual);  // mismo criterio que el seguimiento del emprendedor
                        $estadoTexto = $ESTADOS_PEDIDO[$estadoActual] ?? $estadoActual;
                    ?>
                    <tr>
                        <td data-label="# Pedido"><strong>#<?php echo $p['pedido_id']; ?></strong></td>
                        <td data-label="Emprendedor">
                            <strong><?php echo htmlspecialchars($p['emprendedor_nombre']); ?></strong><br>
                            <small><?php echo htmlspecialchars($p['emprendedor_email']); ?></small>
                        </td>
                        <td data-label="Diseño / Molde">
                            <?php echo htmlspecialchars($NOMBRE_LINDO[$p['tipo_objeto']] ?? $p['tipo_objeto']); ?><br>
                            <?php if (!empty($p['archivo_svg']) && !empty($p['diseno_id'])): ?>
                                <a href="<?php echo BASE_URL . "/pages/ver_diseno.php?id=" . (int)$p['diseno_id']; ?>" target="_blank" rel="noopener">🔍 Ver SVG</a>
                            <?php endif; ?>
                        </td>
                        <td data-label="Dimensiones"><?php echo $p['ancho_mm']; ?> x <?php echo $p['largo_mm']; ?> mm</td>
                        <td data-label="Material"><?php echo htmlspecialchars($p['material_nombre']); ?></td>
                        <td data-label="Piezas"><?php echo $p['cantidad']; ?> u.</td>
                        <td data-label="Peso Total"><?php echo $p['gramos_totales']; ?> g</td>
                        <td data-label="Estado"><span class="badge <?php echo $claseBadge; ?>"><?php echo htmlspecialchars($estadoTexto); ?></span></td>
                        <td data-label="Acciones">
                            <form method="post" action="" style="display:flex; gap:8px; align-items:center; max-width:none; padding:0; background:none; border:none; box-shadow:none; margin:0;">
                                <?php csrf_campo(); ?>
                                <input type="hidden" name="pedido_id" value="<?php echo $p['pedido_id']; ?>">
                                <select name="nuevo_estado" style="padding:6px 10px; font-size:0.85rem;">
                                    <?php foreach ($ESTADOS_PEDIDO as $clave => $texto): ?>
                                        <option value="<?php echo $clave; ?>" <?php echo ($estadoActual === $clave) ? 'selected' : ''; ?>>
                                            <?php echo $texto; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="actualizar_estado" style="padding:6px 14px; font-size:0.85rem; width:auto;">Guardar</button>
                            </form>
                        </td>
                        <td data-label="Trazabilidad">
                            <?php $hist = historial_pedido($pdo, (int)$p['pedido_id']); ?>
                            <?php if (count($hist) > 0): ?>
                                <details class="trazabilidad" style="margin:0; border:none; padding:0;">
                                    <summary><?php echo count($hist); ?> etapa<?php echo count($hist) === 1 ? '' : 's'; ?></summary>
                                    <ol class="timeline" style="min-width:210px;">
                                        <?php foreach ($hist as $h): ?>
                                            <li class="timeline-item">
                                                <span class="timeline-dot <?php echo clase_badge_pedido($h['estado']); ?>"></span>
                                                <div class="timeline-body">
                                                    <strong><?php echo htmlspecialchars($ESTADOS_PEDIDO[$h['estado']] ?? $h['estado']); ?></strong>
                                                    <span class="timeline-fecha"><?php echo date('d/m/Y H:i', strtotime($h['fecha'])); ?> hs</span>
                                                    <?php if (!empty($h['cambiado_por'])): ?>
                                                        <small class="timeline-por">por <?php echo htmlspecialchars($h['cambiado_por']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                </details>
                            <?php else: ?><small>—</small><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>

    </main>

    <!-- TOASTS -->
    <script nonce="<?php echo csp_nonce(); ?>">
    function showToast(mensaje, tipo = 'success') {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `toast ${tipo}`;
        const icono = tipo === 'error' ? '⚠️' : '✅';
        toast.innerHTML = `
            <div style="display:flex; align-items:center; gap:8px;">
                <span>${icono}</span><span>${mensaje}</span>
            </div>
            <span class="toast-close">✕</span>`;
        container.appendChild(toast);
        toast.querySelector('.toast-close').addEventListener('click', function(){ toast.remove(); });
        setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4000);
    }

    // Mostramos/ocultamos el campo de motivo según la decisión elegida.
    document.querySelectorAll('.form-decision').forEach(form => {
        const select = form.querySelector('.select-decision');
        const motivo = form.querySelector('.input-motivo');
        select.addEventListener('change', () => {
            if (select.value === 'rechazado') {
                motivo.style.display = 'block';
                motivo.focus();
            } else {
                motivo.style.display = 'none';
                motivo.value = '';
            }
        });
    });
    </script>

    <?php if (!empty($mensaje)): ?>
        <?php
            $esError = (stristr($mensaje, 'error') !== false || stristr($mensaje, 'no válido') !== false || stristr($mensaje, 'tenés que') !== false);
            $tipoToast = $esError ? 'error' : 'success';
        ?>
        <script nonce="<?php echo csp_nonce(); ?>">
            document.addEventListener('DOMContentLoaded', () => {
                showToast("<?php echo htmlspecialchars($mensaje); ?>", "<?php echo $tipoToast; ?>");
            });
        </script>
    <?php endif; ?>

</body>
</html>