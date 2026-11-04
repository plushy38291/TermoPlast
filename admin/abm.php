<?php
// ============================================================
//  abm.php — MOTOR DE ABM genérico (requisito 2).
//  Un solo controlador maneja los 5 ABM a partir de una CONFIG
//  declarativa ($ENTIDADES). Cada entidad obtiene:
//    Alta · Modificación · Baja lógica · Listado · Búsqueda · Filtros
//
//  Seguridad: los nombres de tabla/columna salen SIEMPRE de la config
//  del servidor (nunca del usuario), por eso es seguro interpolarlos;
//  los VALORES van siempre por prepared statements.
// ============================================================
require __DIR__ . '/../includes/proteger_admin.php';   // sesión admin + roles
require_once __DIR__ . '/../includes/sesion_segura.php';
require __DIR__ . '/../config/conexion.php';

// ------------------------------------------------------------
//  CONFIG de las entidades administrables.
//  'rol'   = roles que pueden entrar a ese ABM.
//  'campos'= columnas editables; 'buscable' entra en la búsqueda.
//  'filtro'= (opcional) filtro por una columna con opciones fijas.
// ------------------------------------------------------------
$ENTIDADES = [
    'materiales' => [
        'titulo' => 'Materiales', 'singular' => 'material',
        'tabla'  => 'materiales', 'rol' => ['administrador', 'operador'],
        'orden'  => 'nombre ASC',
        'campos' => [
            'nombre'        => ['label' => 'Nombre', 'tipo' => 'text', 'req' => true, 'buscable' => true, 'max' => 50],
            'co2_por_gramo' => ['label' => 'CO₂ por gramo (kg)', 'tipo' => 'number', 'step' => '0.0001', 'req' => true],
        ],
    ],
    'etiquetas' => [
        'titulo' => 'Etiquetas', 'singular' => 'etiqueta',
        'tabla'  => 'etiquetas', 'rol' => ['administrador', 'operador'],
        'orden'  => 'nombre ASC',
        'campos' => [
            'nombre' => ['label' => 'Nombre', 'tipo' => 'text', 'req' => true, 'buscable' => true, 'max' => 50],
        ],
    ],
    'puntos' => [
        'titulo' => 'Puntos de retiro', 'singular' => 'punto de retiro',
        'tabla'  => 'puntos_retiro', 'rol' => ['administrador', 'operador'],
        'orden'  => 'nombre ASC',
        'campos' => [
            'nombre'    => ['label' => 'Nombre', 'tipo' => 'text', 'req' => true, 'buscable' => true, 'max' => 120],
            'direccion' => ['label' => 'Dirección', 'tipo' => 'text', 'req' => false, 'buscable' => true, 'max' => 200],
            'lat'       => ['label' => 'Latitud', 'tipo' => 'number', 'step' => '0.0000001', 'req' => true],
            'lng'       => ['label' => 'Longitud', 'tipo' => 'number', 'step' => '0.0000001', 'req' => true],
        ],
    ],
    'emprendedores' => [
        'titulo' => 'Emprendedores', 'singular' => 'emprendedor',
        'tabla'  => 'emprendedores', 'rol' => ['administrador', 'operador'],
        'orden'  => 'nombre ASC',
        'campos' => [
            'nombre' => ['label' => 'Nombre', 'tipo' => 'text', 'req' => true, 'buscable' => true, 'max' => 100],
            'email'  => ['label' => 'Email', 'tipo' => 'email', 'req' => true, 'buscable' => true, 'max' => 150, 'unico' => true],
        ],
    ],
    'usuarios' => [
        'titulo' => 'Usuarios del sistema', 'singular' => 'usuario',
        'tabla'  => 'administradores', 'rol' => ['administrador'],   // SOLO administrador
        'orden'  => 'usuario ASC',
        'campos' => [
            'usuario'  => ['label' => 'Usuario', 'tipo' => 'text', 'req' => true, 'buscable' => true, 'max' => 50, 'unico' => true],
            'rol'      => ['label' => 'Rol', 'tipo' => 'select', 'req' => true,
                           'opciones' => ['administrador' => 'Administrador', 'operador' => 'Operador']],
            'password' => ['label' => 'Contraseña', 'tipo' => 'password', 'req' => true, 'solo_alta_opcional' => true],
        ],
        'filtro' => ['col' => 'rol', 'label' => 'Rol',
                     'opciones' => ['administrador' => 'Administrador', 'operador' => 'Operador']],
    ],
];

// ------------------------------------------------------------
//  Selección de entidad + guardia por rol.
// ------------------------------------------------------------
$e = $_GET['e'] ?? $_POST['e'] ?? '';
if (!isset($ENTIDADES[$e])) {
    http_response_code(404);
    $e = 'materiales';   // por defecto, para no romper
}
$cfg = $ENTIDADES[$e];
requiere_rol(...$cfg['rol']);

$tabla  = $cfg['tabla'];
$campos = $cfg['campos'];
$self   = BASE_URL . '/admin/abm.php?e=' . urlencode($e);

// Pequeño helper: nombre "lindo" de un registro para los mensajes.
function etiqueta_registro(array $campos, array $fila): string {
    $k = array_key_first($campos);
    return (string)($fila[$k] ?? ('#' . ($fila['id'] ?? '')));
}

$errores = [];
$old = [];   // valores reingresados si falla la validación

// ============================================================
//  POST — guardar (alta/modificación), baja lógica, reactivar
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accion = $_POST['accion'] ?? '';
    $id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

    // ---- BAJA LÓGICA / REACTIVAR ----
    if ($accion === 'baja' || $accion === 'reactivar') {
        $nuevo = ($accion === 'reactivar') ? 1 : 0;
        // Regla: un usuario no puede darse de baja a sí mismo.
        if ($tabla === 'administradores' && $id === (int)($_SESSION['admin_id'] ?? 0) && $nuevo === 0) {
            header("Location: $self&msg=" . urlencode('No podés darte de baja a vos mismo.') . "&t=error");
            exit;
        }
        try {
            $st = $pdo->prepare("UPDATE `$tabla` SET `activo` = ? WHERE `id` = ?");
            $st->execute([$nuevo, $id]);
            $txt = $nuevo ? 'reactivado' : 'dado de baja';
            header("Location: $self&msg=" . urlencode("Registro #$id $txt.") . "&t=success");
        } catch (PDOException $ex) {
            header("Location: $self&msg=" . urlencode('No se pudo cambiar el estado.') . "&t=error");
        }
        exit;
    }

    // ---- ALTA / MODIFICACIÓN ----
    if ($accion === 'guardar') {
        $datos = [];
        foreach ($campos as $col => $def) {
            $val = trim((string)($_POST[$col] ?? ''));
            $old[$col] = $val;

            // Contraseña en edición: si queda vacía, se MANTIENE la actual.
            if ($def['tipo'] === 'password' && $id > 0 && $val === '') {
                continue;
            }
            // Requerido
            if (!empty($def['req']) && $val === '') {
                $errores[] = "«{$def['label']}» es obligatorio.";
                continue;
            }
            // Validaciones por tipo
            if ($def['tipo'] === 'email' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $errores[] = "El email no tiene un formato válido.";
            }
            if ($def['tipo'] === 'number' && $val !== '' && !is_numeric($val)) {
                $errores[] = "«{$def['label']}» debe ser un número.";
            }
            if ($def['tipo'] === 'select' && !empty($def['opciones']) && !array_key_exists($val, $def['opciones'])) {
                $errores[] = "Valor no válido para «{$def['label']}».";
            }
            if (!empty($def['max']) && mb_strlen($val) > $def['max']) {
                $errores[] = "«{$def['label']}» es demasiado largo (máx {$def['max']}).";
            }

            // Transformación de la contraseña → hash
            if ($def['tipo'] === 'password' && $val !== '') {
                $datos[$col] = password_hash($val, PASSWORD_BCRYPT);
            } else {
                $datos[$col] = $val;
            }
        }

        if (!$errores) {
            try {
                if ($id > 0) {
                    // UPDATE
                    $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($datos)));
                    $vals = array_values($datos);
                    $vals[] = $id;
                    $pdo->prepare("UPDATE `$tabla` SET $sets WHERE `id` = ?")->execute($vals);
                    $msg = "«" . etiqueta_registro($campos, $datos) . "» actualizado.";
                } else {
                    // INSERT (activo = 1 por defecto)
                    $cols = array_keys($datos);
                    $ph   = implode(', ', array_fill(0, count($cols), '?'));
                    $colsSql = implode(', ', array_map(fn($c) => "`$c`", $cols));
                    $pdo->prepare("INSERT INTO `$tabla` ($colsSql) VALUES ($ph)")->execute(array_values($datos));
                    $msg = "«" . etiqueta_registro($campos, $datos) . "» creado.";
                }
                header("Location: $self&msg=" . urlencode($msg) . "&t=success");
                exit;
            } catch (PDOException $ex) {
                // 23000 = violación de UNIQUE (nombre/email/usuario repetido)
                if ($ex->getCode() === '23000') {
                    $errores[] = "Ya existe un registro con ese valor único (nombre/email/usuario).";
                } else {
                    $errores[] = "No se pudo guardar el registro.";
                }
            }
        }
    }
}

// ============================================================
//  GET — datos para el listado (con búsqueda y filtros)
// ============================================================
$q        = trim($_GET['q'] ?? '');
$f_activo = $_GET['activo'] ?? '';                 // '', '1', '0'
$f_extra  = $_GET['f'] ?? '';                       // filtro por columna (si la entidad lo define)
$edit_id  = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;

$where = [];
$params = [];

// Búsqueda por campos 'buscable'
if ($q !== '') {
    $buscables = array_keys(array_filter($campos, fn($d) => !empty($d['buscable'])));
    if ($buscables) {
        $ors = array_map(fn($c) => "`$c` LIKE ?", $buscables);
        $where[] = '(' . implode(' OR ', $ors) . ')';
        foreach ($buscables as $_) { $params[] = "%$q%"; }
    }
}
// Filtro por estado (baja lógica)
if ($f_activo === '1' || $f_activo === '0') {
    $where[] = "`activo` = ?";
    $params[] = (int)$f_activo;
}
// Filtro por columna específica (p. ej. rol en usuarios)
if (!empty($cfg['filtro']) && $f_extra !== '' && array_key_exists($f_extra, $cfg['filtro']['opciones'])) {
    $where[] = "`{$cfg['filtro']['col']}` = ?";
    $params[] = $f_extra;
}
$sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

// Columnas a traer: id + campos (sin password) + activo
$colsLista = array_keys(array_filter($campos, fn($d) => $d['tipo'] !== 'password'));
$selCols = '`id`, ' . implode(', ', array_map(fn($c) => "`$c`", $colsLista)) . ', `activo`';
try {
    $st = $pdo->prepare("SELECT $selCols FROM `$tabla`" . $sqlWhere . " ORDER BY {$cfg['orden']}");
    $st->execute($params);
    $filas = $st->fetchAll();
} catch (PDOException $ex) {
    $filas = [];
    $errores[] = "No se pudo leer el listado.";
}

// Registro en edición (precarga del formulario)
$editando = null;
if ($edit_id > 0 && !$old) {
    $st = $pdo->prepare("SELECT * FROM `$tabla` WHERE `id` = ?");
    $st->execute([$edit_id]);
    $editando = $st->fetch() ?: null;
}

// Valor a mostrar en cada input (reingreso tras error > registro en edición > vacío)
function val_campo(string $col, array $old, ?array $editando) {
    if (array_key_exists($col, $old)) return $old[$col];
    if ($editando && array_key_exists($col, $editando)) return $editando[$col];
    return '';
}
$form_id = $old ? (int)($_POST['id'] ?? 0) : ($editando['id'] ?? 0);

// Mensaje por querystring (patrón PRG)
$flash = $_GET['msg'] ?? '';
$flash_t = ($_GET['t'] ?? 'success') === 'error' ? 'error' : 'success';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($cfg['titulo']); ?> — ABM — TermoPlast</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/estilos.css">
</head>
<body>
    <?php $pagina_activa = $e; include __DIR__ . '/../includes/admin_header.php'; ?>

    <main class="main-content">
        <h1><?php echo htmlspecialchars($cfg['titulo']); ?> <small style="font-weight:400; color:var(--text-soft);">· ABM</small></h1>

        <?php if ($errores): ?>
            <div class="alert alert-error fade-in">
                <strong>Revisá estos puntos:</strong>
                <ul style="margin:6px 0 0 18px;">
                    <?php foreach ($errores as $er): ?><li><?php echo htmlspecialchars($er); ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- ===================== FORMULARIO Alta / Modificación ===================== -->
        <section class="card" style="margin-bottom:22px;">
            <h2 style="margin-top:0;"><?php echo $form_id ? 'Editar ' . htmlspecialchars($cfg['singular']) . ' #' . (int)$form_id : 'Nuevo ' . htmlspecialchars($cfg['singular']); ?></h2>
            <form method="post" action="<?php echo $self; ?>" class="abm-form">
                <?php csrf_campo(); ?>
                <input type="hidden" name="e" value="<?php echo htmlspecialchars($e); ?>">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?php echo (int)$form_id; ?>">
                <div class="abm-grid">
                    <?php foreach ($campos as $col => $def): ?>
                        <div class="form-group">
                            <label><?php echo htmlspecialchars($def['label']); ?>
                                <?php if (!empty($def['req']) && !($def['tipo']==='password' && $form_id)): ?><span style="color:var(--danger)">*</span><?php endif; ?>
                            </label>
                            <?php $v = val_campo($col, $old, $editando); ?>
                            <?php if ($def['tipo'] === 'select'): ?>
                                <select name="<?php echo $col; ?>">
                                    <?php foreach ($def['opciones'] as $ov => $ol): ?>
                                        <option value="<?php echo htmlspecialchars($ov); ?>" <?php echo ((string)$v === (string)$ov) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ol); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($def['tipo'] === 'password'): ?>
                                <input type="password" name="<?php echo $col; ?>" autocomplete="new-password"
                                       placeholder="<?php echo $form_id ? 'Dejar vacío para no cambiarla' : '••••••••'; ?>">
                            <?php else: ?>
                                <input type="<?php echo $def['tipo'] === 'number' ? 'number' : ($def['tipo'] === 'email' ? 'email' : 'text'); ?>"
                                       name="<?php echo $col; ?>"
                                       value="<?php echo htmlspecialchars((string)$v); ?>"
                                       <?php echo isset($def['step']) ? 'step="' . htmlspecialchars($def['step']) . '"' : ''; ?>
                                       <?php echo !empty($def['max']) ? 'maxlength="' . (int)$def['max'] . '"' : ''; ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn"><?php echo $form_id ? 'Guardar cambios' : 'Crear'; ?></button>
                    <?php if ($form_id): ?>
                        <a href="<?php echo $self; ?>" class="btn btn-secondary">Cancelar edición</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- ===================== Búsqueda + Filtros ===================== -->
        <form method="get" action="<?php echo BASE_URL; ?>/admin/abm.php" class="filtros-bar">
            <input type="hidden" name="e" value="<?php echo htmlspecialchars($e); ?>">
            <div class="campo">
                <label for="q">Buscar</label>
                <input type="text" name="q" id="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Escribí para buscar…">
            </div>
            <div class="campo">
                <label for="activo">Estado</label>
                <select name="activo" id="activo">
                    <option value="">Todos</option>
                    <option value="1" <?php echo $f_activo === '1' ? 'selected' : ''; ?>>Activos</option>
                    <option value="0" <?php echo $f_activo === '0' ? 'selected' : ''; ?>>Dados de baja</option>
                </select>
            </div>
            <?php if (!empty($cfg['filtro'])): ?>
                <div class="campo">
                    <label for="f"><?php echo htmlspecialchars($cfg['filtro']['label']); ?></label>
                    <select name="f" id="f">
                        <option value="">Todos</option>
                        <?php foreach ($cfg['filtro']['opciones'] as $ov => $ol): ?>
                            <option value="<?php echo htmlspecialchars($ov); ?>" <?php echo $f_extra === (string)$ov ? 'selected' : ''; ?>><?php echo htmlspecialchars($ol); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn">Filtrar</button>
            <?php if ($q !== '' || $f_activo !== '' || $f_extra !== ''): ?>
                <a href="<?php echo $self; ?>" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </form>

        <!-- ===================== Listado ===================== -->
        <?php if (!$filas): ?>
            <div class="card"><p style="margin:0;">No hay registros que coincidan.</p></div>
        <?php else: ?>
        <div style="overflow-x:auto;"><table class="tabla-cards">
            <thead>
                <tr>
                    <th>#</th>
                    <?php foreach ($colsLista as $c): ?><th><?php echo htmlspecialchars($campos[$c]['label']); ?></th><?php endforeach; ?>
                    <th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $fila): ?>
                    <tr style="<?php echo (int)$fila['activo'] === 0 ? 'opacity:.55;' : ''; ?>">
                        <td data-label="#"><strong>#<?php echo (int)$fila['id']; ?></strong></td>
                        <?php foreach ($colsLista as $c): ?>
                            <td data-label="<?php echo htmlspecialchars($campos[$c]['label']); ?>">
                                <?php
                                    $valor = $fila[$c] ?? '';
                                    if ($campos[$c]['tipo'] === 'select' && isset($campos[$c]['opciones'][$valor])) {
                                        $valor = $campos[$c]['opciones'][$valor];
                                    }
                                    echo htmlspecialchars((string)$valor);
                                ?>
                            </td>
                        <?php endforeach; ?>
                        <td data-label="Estado">
                            <?php if ((int)$fila['activo'] === 1): ?>
                                <span class="badge badge-finalizado">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-cancelado">Baja</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Acciones">
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <a class="btn btn-secondary" style="padding:5px 12px; font-size:0.82rem;" href="<?php echo $self; ?>&edit=<?php echo (int)$fila['id']; ?>">Editar</a>
                                <?php if ((int)$fila['activo'] === 1): ?>
                                    <form method="post" action="<?php echo $self; ?>" style="margin:0; padding:0; background:none; border:none; box-shadow:none; max-width:none;"
                                          onsubmit="return confirm('¿Dar de baja este registro? (baja lógica, se puede reactivar)');">
                                        <?php csrf_campo(); ?>
                                        <input type="hidden" name="e" value="<?php echo htmlspecialchars($e); ?>">
                                        <input type="hidden" name="accion" value="baja">
                                        <input type="hidden" name="id" value="<?php echo (int)$fila['id']; ?>">
                                        <button type="submit" class="btn" style="padding:5px 12px; font-size:0.82rem; background:var(--danger); width:auto;">Baja</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?php echo $self; ?>" style="margin:0; padding:0; background:none; border:none; box-shadow:none; max-width:none;">
                                        <?php csrf_campo(); ?>
                                        <input type="hidden" name="e" value="<?php echo htmlspecialchars($e); ?>">
                                        <input type="hidden" name="accion" value="reactivar">
                                        <input type="hidden" name="id" value="<?php echo (int)$fila['id']; ?>">
                                        <button type="submit" class="btn" style="padding:5px 12px; font-size:0.82rem; width:auto;">Reactivar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
        <p style="color:var(--text-soft); font-size:0.85rem; margin-top:10px;"><?php echo count($filas); ?> registro<?php echo count($filas) === 1 ? '' : 's'; ?>.</p>
        <?php endif; ?>
    </main>

    <?php if ($flash !== ''): ?>
    <script nonce="<?php echo csp_nonce(); ?>">
        document.addEventListener('DOMContentLoaded', function () {
            var c = document.createElement('div'); c.className = 'toast-container';
            var t = document.createElement('div'); t.className = 'toast <?php echo $flash_t; ?>';
            t.textContent = <?php echo json_encode(($flash_t === 'error' ? '⚠️ ' : '✅ ') . $flash); ?>;
            c.appendChild(t); document.body.appendChild(c);
            setTimeout(function(){ t.remove(); }, 4000);
        });
    </script>
    <?php endif; ?>
</body>
</html>
