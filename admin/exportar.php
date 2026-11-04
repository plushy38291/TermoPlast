<?php
// ============================================================
//  exportar.php — Exportación de listados (requisito 5).
//  Formatos: CSV (Excel-friendly, con BOM) y PDF (generador propio).
//  Tipos:    pedidos | emprendedores
//  Acceso:   administrador y operador.
// ============================================================
require __DIR__ . '/../includes/proteger_admin.php';
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';   // $ESTADOS_PEDIDO, $NOMBRE_LINDO

requiere_rol('administrador', 'operador');

$tipo    = $_GET['tipo'] ?? 'pedidos';
$formato = ($_GET['formato'] ?? 'csv') === 'pdf' ? 'pdf' : 'csv';
if (!in_array($tipo, ['pedidos', 'emprendedores'], true)) { $tipo = 'pedidos'; }

// ------------------------------------------------------------
//  Armamos encabezados + filas según el tipo pedido.
// ------------------------------------------------------------
if ($tipo === 'pedidos') {
    $titulo  = 'TermoPlast — Listado de pedidos';
    $headers = ['#', 'Emprendedor', 'Email', 'Tipo', 'Material', 'Piezas', 'Gramos', 'Estado', 'Fecha', 'Codigo'];
    $sql = "SELECT p.id, COALESCE(e.nombre,'(baja)') nom, COALESCE(e.email,'') email,
                   COALESCE(d.tipo_objeto,'-') tipo, COALESCE(m.nombre,'-') material,
                   p.cantidad, p.gramos_totales, p.estado, p.fecha, COALESCE(p.codigo,'') codigo
            FROM pedidos p
            LEFT JOIN emprendedores e ON p.emprendedor_id=e.id
            LEFT JOIN disenos d       ON p.diseno_id=d.id
            LEFT JOIN materiales m    ON p.material_id=m.id
            ORDER BY p.id DESC";
    $filas = [];
    foreach ($pdo->query($sql) as $r) {
        $filas[] = [
            $r['id'], $r['nom'], $r['email'],
            $NOMBRE_LINDO[$r['tipo']] ?? $r['tipo'],
            $r['material'], $r['cantidad'], $r['gramos_totales'],
            $ESTADOS_PEDIDO[$r['estado']] ?? $r['estado'],
            date('d/m/Y', strtotime($r['fecha'])), $r['codigo'],
        ];
    }
} else {
    $titulo  = 'TermoPlast — Listado de emprendedores';
    $headers = ['#', 'Nombre', 'Email', 'Estado', 'Alta'];
    $filas = [];
    foreach ($pdo->query("SELECT id, nombre, email, activo, fecha_registro FROM emprendedores ORDER BY nombre ASC") as $r) {
        $filas[] = [
            $r['id'], $r['nombre'], $r['email'],
            ((int)$r['activo'] === 1 ? 'Activo' : 'Baja'),
            date('d/m/Y', strtotime($r['fecha_registro'])),
        ];
    }
}

$fecha = date('Ymd_Hi');
$nombreArch = "termoplast_{$tipo}_{$fecha}";

// ------------------------------------------------------------
//  Salida CSV
// ------------------------------------------------------------
if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$nombreArch}.csv\"");
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM: Excel abre el UTF-8 con tildes correctas
    // El 5º parámetro ($escape) se pasa explícito: en PHP 8.4 su default
    // quedó deprecado. Vacío = CSV estándar (sin escape con backslash).
    fputcsv($out, $headers, ',', '"', '');
    foreach ($filas as $f) { fputcsv($out, $f, ',', '"', ''); }
    fclose($out);
    exit;
}

// ------------------------------------------------------------
//  Salida PDF
// ------------------------------------------------------------
require __DIR__ . '/../includes/pdf_simple.php';
// Pedidos tiene muchas columnas → hoja apaisada para que entre cómodo.
$pdf = new PDFSimple($titulo, $tipo === 'pedidos');
$bytes = $pdf->tabla($headers, $filas);

header('Content-Type: application/pdf');
header("Content-Disposition: attachment; filename=\"{$nombreArch}.pdf\"");
header('Content-Length: ' . strlen($bytes));
echo $bytes;
exit;
