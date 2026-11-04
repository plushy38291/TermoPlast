<?php
require __DIR__ . '/../includes/proteger.php';
require_once __DIR__ . '/../includes/sesion_segura.php';   // deja las funciones csrf_* visibles para el editor
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';
require __DIR__ . '/../includes/funciones.php';

$mensaje = "";
$tipo_alerta = "";

$stmtD = $pdo->prepare(
    "SELECT id, nombre, tipo_objeto FROM disenos
     WHERE emprendedor_id = ? AND estado = 'aprobado' ORDER BY fecha_subida DESC"
);
$stmtD->execute([$_SESSION['emprendedor_id']]);
$disenos_aprobados = $stmtD->fetchAll();

$materiales = $pdo->query("SELECT id, nombre, co2_por_gramo FROM materiales ORDER BY nombre")->fetchAll();

// --- RE-PEDIR: pre-llenar el formulario desde un pedido anterior ---
// Llega por GET (?rehacer=ID). Validamos que ese pedido sea del usuario.
$pre_diseno = "";
$pre_material = "";
$pre_cantidad = 1;
$rehacer_id = filter_input(INPUT_GET, 'rehacer', FILTER_VALIDATE_INT);
if ($rehacer_id) {
    $rp = $pdo->prepare(
        "SELECT p.diseno_id, p.material_id, p.cantidad, d.estado AS diseno_estado
         FROM pedidos p
         INNER JOIN disenos d ON p.diseno_id = d.id
         WHERE p.id = ? AND p.emprendedor_id = ?"
    );
    $rp->execute([$rehacer_id, $_SESSION['emprendedor_id']]);
    $ant = $rp->fetch();
    if ($ant) {
        $pre_material = $ant['material_id'];
        $pre_cantidad = $ant['cantidad'];
        // Solo pre-seleccionamos el diseño si TODAVÍA está aprobado.
        if ($ant['diseno_estado'] === 'aprobado') {
            $pre_diseno = $ant['diseno_id'];
        } else {
            $mensaje = "El diseño de ese pedido ya no está aprobado. Elegí otro diseño para repetir el pedido.";
            $tipo_alerta = "error";
        }
    }
}

// --- DESDE EL ASISTENTE: pre-llenar por TIPO de pieza y CANTIDAD ---
// El chatbot, tras hacer una estimación, puede ofrecer un acceso directo
// (?tipo=llavero&cantidad=20). Pre-seleccionamos el primer diseño aprobado
// de ese tipo y la cantidad. Es solo una ayuda: el usuario puede cambiar todo.
if (!$rehacer_id) {
    $tipo_get = strtolower((string) filter_input(INPUT_GET, 'tipo'));
    $cant_get = filter_input(INPUT_GET, 'cantidad', FILTER_VALIDATE_INT);
    $tipos_ok = ['llavero', 'maceta', 'portavasos'];
    if (in_array($tipo_get, $tipos_ok, true)) {
        // Buscamos entre los diseños ya cargados uno de ese tipo.
        foreach ($disenos_aprobados as $d) {
            if (($d['tipo_objeto'] ?? '') === $tipo_get) { $pre_diseno = $d['id']; break; }
        }
        if ($pre_diseno === "" && $mensaje === "") {
            $mensaje = "No tenés un diseño aprobado de tipo «" . htmlspecialchars($NOMBRE_LINDO[$tipo_get] ?? $tipo_get)
                     . "» todavía. Subí uno desde «¿Tenés una idea?» y, cuando lo aprueben, repetí este acceso.";
            $tipo_alerta = "error";
        }
    }
    if ($cant_get && $cant_get >= 1 && $cant_get <= 500) {
        $pre_cantidad = $cant_get;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['guardar_pedido'])) {

    csrf_validar();   // Anti-CSRF: verificamos el token antes de seguir.

    $diseno_id   = filter_input(INPUT_POST, 'diseno_id',   FILTER_VALIDATE_INT);
    $material_id = filter_input(INPUT_POST, 'material_id', FILTER_VALIDATE_INT);
    $cantidad    = filter_input(INPUT_POST, 'cantidad',    FILTER_VALIDATE_INT);

    if (!$diseno_id || !$material_id || !$cantidad || $cantidad < 1 || $cantidad > 500) {
        $mensaje = "Revisá los datos: elegí diseño, material y una cantidad entre 1 y 500.";
        $tipo_alerta = "error";
    } else {
        $chk = $pdo->prepare("SELECT tipo_objeto FROM disenos WHERE id = ? AND emprendedor_id = ? AND estado = 'aprobado'");
        $chk->execute([$diseno_id, $_SESSION['emprendedor_id']]);
        $diseno = $chk->fetch();

        $chkM = $pdo->prepare("SELECT id FROM materiales WHERE id = ?");
        $chkM->execute([$material_id]);
        $material_ok = $chkM->fetch();

        if (!$diseno) {
            $mensaje = "El diseño seleccionado no es válido o no está aprobado.";
            $tipo_alerta = "error";
        } elseif (!$material_ok) {
            $mensaje = "El material seleccionado no es válido.";
            $tipo_alerta = "error";
        } else {
            $gramos_unit = $GRAMOS_POR_TIPO[$diseno['tipo_objeto']] ?? 0;
            $gramos_totales = $gramos_unit * $cantidad;

            $sql = "INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado)
                    VALUES (?, ?, ?, ?, ?, 'registrado')";
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_SESSION['emprendedor_id'], $diseno_id, $material_id, $cantidad, $gramos_totales]);
                // TRAZABILIDAD: sembramos la primera etapa de la línea de tiempo.
                registrar_historial($pdo, (int)$pdo->lastInsertId(), 'registrado', 'Pedido creado por el emprendedor');
                $mensaje = "¡Pedido registrado correctamente! Ya podés hacerle seguimiento.";
                $tipo_alerta = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al registrar el pedido en la base de datos.";
                $tipo_alerta = "error";
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
    <title>Hacer Pedido - TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>

    <?php $pagina_activa = "pedido"; include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Nueva Solicitud de Fabricación 🛠️</h1>
            <p>Elegí uno de tus diseños aprobados, el material y la cantidad. Calculamos las tapitas necesarias y el CO₂ evitado.</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_alerta === 'success' ? 'success' : 'error'; ?> fade-in">
                <?php echo $tipo_alerta === 'success' ? '✅ ' : '⚠️ '; ?>
                <?php echo $mensaje; ?>
                <?php if ($tipo_alerta === 'success'): ?><a href="<?php echo BASE_URL; ?>/pages/seguimiento.php">Ver mis pedidos →</a><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (count($disenos_aprobados) === 0): ?>
            <div class="card empty-state fade-in">
                <p style="font-size: 2rem; margin-bottom: 8px;">🎨</p>
                <h3>Todavía no tenés diseños aprobados</h3>
                <p>Para hacer un pedido, primero subí un diseño y esperá a que el taller lo apruebe.</p>
                <a href="<?php echo BASE_URL; ?>/pages/subir_diseno.php" class="btn" style="margin-top: 12px;">Subir un diseño →</a>
            </div>
        <?php else: ?>

        <div class="order-grid">
            <form action="<?php echo BASE_URL; ?>/pages/hacer_pedido.php" method="POST" class="order-form-container">
                <?php csrf_campo(); ?>
                <div class="form-group">
                    <label>1. Elegí tu diseño aprobado</label>
                    <select name="diseno_id" id="selectDiseno" required>
                        <option value="" disabled <?php echo $pre_diseno === "" ? "selected" : ""; ?>>-- Seleccioná un diseño --</option>
                        <?php foreach ($disenos_aprobados as $d): ?>
                            <?php $g = $GRAMOS_POR_TIPO[$d['tipo_objeto']] ?? 0; ?>
                            <option value="<?php echo $d['id']; ?>" data-gramos="<?php echo $g; ?>" <?php echo ($pre_diseno == $d['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['nombre']); ?>
                                (<?php echo $NOMBRE_LINDO[$d['tipo_objeto']] ?? $d['tipo_objeto']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>2. Material (tapitas recicladas)</label>
                    <select name="material_id" id="selectMaterial" required>
                        <?php foreach ($materiales as $m): ?>
                            <option value="<?php echo $m['id']; ?>" data-co2="<?php echo $m['co2_por_gramo']; ?>" <?php echo ($pre_material == $m['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>3. Cantidad de unidades</label>
                    <input type="number" name="cantidad" id="inputCantidad" min="1" max="500" value="<?php echo (int)$pre_cantidad; ?>" required>
                </div>

                <div class="form-group">
                    <label>4. Punto de retiro</label>
                    <div class="readonly-field">📍 <?php echo htmlspecialchars($PUNTOS_RETIRO[0]); ?></div>
                </div>

                <button type="submit" name="guardar_pedido" class="btn btn-block">Confirmar y Enviar Pedido →</button>
            </form>

            <div class="card summary-card">
                <h3>Resumen de Fabricación</h3>
                <hr class="divider">
                <div class="summary-item"><span>Peso aproximado:</span><strong id="resPeso">0 g</strong></div>
                <div class="summary-item"><span>Tapitas necesarias:</span><strong id="resTapitas" style="color: var(--primary); font-size: 1.2rem;">0 tapitas</strong></div>
                <div class="summary-item"><span>CO₂ evitado:</span><strong id="resCO2">0 kg</strong></div>
                <div class="info-box">
                    💡 <strong>Cálculo estimado:</strong> cada tapita equivale en promedio a <?php echo $PESO_TAPITA_G; ?> g de plástico recuperado.
                </div>
            </div>
        </div>

        <script nonce="<?php echo csp_nonce(); ?>">
        const PESO_TAPITA = <?php echo $PESO_TAPITA_G; ?>;
        function calcularImpacto() {
            const selDiseno   = document.getElementById('selectDiseno');
            const selMaterial = document.getElementById('selectMaterial');
            const cantidad    = parseInt(document.getElementById('inputCantidad').value) || 0;
            const optD = selDiseno.options[selDiseno.selectedIndex];
            const optM = selMaterial.options[selMaterial.selectedIndex];
            const gramosUnit = optD ? parseFloat(optD.getAttribute('data-gramos') || 0) : 0;
            const co2PorG    = optM ? parseFloat(optM.getAttribute('data-co2') || 0) : 0;
            const pesoTotal = gramosUnit * cantidad;
            const tapitas   = Math.round(pesoTotal / PESO_TAPITA);
            const co2Kg     = (pesoTotal * co2PorG).toFixed(2);
            document.getElementById('resPeso').innerText    = pesoTotal + ' g';
            document.getElementById('resTapitas').innerText = tapitas.toLocaleString() + ' tapitas';
            document.getElementById('resCO2').innerText     = co2Kg + ' kg CO₂';
        }
        document.getElementById('selectDiseno').addEventListener('change', calcularImpacto);
        document.getElementById('selectMaterial').addEventListener('change', calcularImpacto);
        document.getElementById('inputCantidad').addEventListener('input', calcularImpacto);
        calcularImpacto();
        </script>

        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>