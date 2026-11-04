<?php
// ============================================================
//  conexion.php
//  Conecta PHP con la base de datos MySQL "termoplast".
//  Este archivo lo van a REUTILIZAR todas las demas paginas.
// ============================================================

// --- 0) Modo de desarrollo -----------------------------------
// SEGURO POR DEFECTO: el modo desarrollo (errores en pantalla) se
// activa SOLO si estás entrando desde la propia máquina (localhost).
// En un servidor real, los visitantes llegan con otra IP → DEV queda
// en FALSE y nunca ven errores técnicos (se guardan en el log).
// Si querés forzarlo, cambiá esta línea por: $DEV = true;  (o false).
$DEV = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

if ($DEV) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');   // el usuario NUNCA ve errores tecnicos
    ini_set('log_errors', '1');       // pero quedan registrados para vos
}

// --- 1) Datos de la conexion ---
// En XAMPP, por defecto: servidor "localhost", usuario "root",
// contraseña VACIA ("").
$servidor    = "localhost";
$usuario     = "root";
$contrasena  = "";
$base_datos  = "termoplast";

// --- 2) Intentamos conectar ---
try {

    // charset=utf8mb4 asegura que tildes y ñ se guarden bien.
    $pdo = new PDO(
        "mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4",
        $usuario,
        $contrasena
    );

    // Si hay un error, que lance una excepcion (asi la atrapamos).
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Resultados como arreglos con nombres de columna (mas comodo).
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // SEGURIDAD: prepared statements REALES, no emulados. Refuerza
    // la defensa contra inyeccion SQL en casos borde.
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {

    // Guardamos el error REAL en el log del servidor (para vos)...
    error_log("Error de conexion a la base: " . $e->getMessage());

    // ...pero al usuario le mostramos un mensaje generico, sin
    // filtrar detalles tecnicos (nombre de la base, rutas, etc.).
    if ($DEV) {
        die("Error de conexion (modo desarrollo): " . $e->getMessage());
    }
    die("No se pudo conectar con el sistema en este momento. Probá de nuevo más tarde.");
}

// Si llegamos aca, la variable $pdo esta lista para usarse.
?>
