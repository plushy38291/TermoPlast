<?php
/**
 * catalogo.php
 * FUENTE ÚNICA de la configuración del sistema. Productos, pesos, horas,
 * estados, LÍMITES de la máquina y puntos de retiro: todo se lee DESDE ACÁ.
 * Si algo cambia, se toca en UN solo lugar.
 */

// Tipos oficiales. Claves SIEMPRE en minúscula.
$TIPOS_OBJETO = ['maceta', 'portavasos', 'llavero'];

// Gramos de plástico por UNIDAD (⚠️ ajustar con datos reales de tu máquina).
$GRAMOS_POR_TIPO = [
    'maceta'     => 460,
    'portavasos' => 320,
    'llavero'    => 85,
];

// Horas de taller por UNIDAD (para el KPI de trabajo inclusivo).
$HORAS_POR_TIPO = [
    'maceta'     => 1.4,
    'portavasos' => 0.3,
    'llavero'    => 0.9,
];

// Nombre "lindo" del tipo para mostrar en pantalla.
$NOMBRE_LINDO = [
    'maceta'     => 'Maceta',
    'portavasos' => 'Portavasos',
    'llavero'    => 'Llavero',
];

// Estados de un PEDIDO (clave interna => texto para el usuario).
$ESTADOS_PEDIDO = [
    'registrado'       => 'Registrado',
    'en_calentamiento' => 'En calentamiento',
    'moldeado'         => 'Moldeado',
    'finalizado'       => 'Finalizado',
    'cancelado'        => 'Cancelado',
];

// Estados de un DISEÑO.
$ESTADOS_DISENO = [
    'en_revision' => 'En revisión',
    'aprobado'    => 'Aprobado',
    'rechazado'   => 'Rechazado',
];

// ------------------------------------------------------------
// LÍMITES FÍSICOS DE LA MÁQUINA (área de trabajo).
// La máquina real mide 80 x 50 cm, con ~20 cm de altura tolerable.
// Lo guardamos en MILÍMETROS porque el formulario trabaja en mm.
// ⚠️ Si tu máquina fuera de otra escala, cambiás SOLO estos tres números.
// ------------------------------------------------------------
$LIMITES = [
    'ancho_mm'  => 800,   // 80 cm
    'largo_mm'  => 500,   // 50 cm
    'altura_mm' => 200,   // 20 cm (espesor/alto)
];

// Peso promedio de una tapita (para estimar cuántas se necesitan).
$PESO_TAPITA_G = 2.5;

// Puntos de retiro disponibles (por ahora, uno solo).
$PUNTOS_RETIRO = [
    'Escuela Técnica N° 3 Roberto Arlt',
];

// ------------------------------------------------------------
//  URL PÚBLICA (para los códigos QR)
//  Los QR deben apuntar a una dirección que OTRO dispositivo (un
//  celular) pueda abrir. "localhost" NO sirve: desde el celular
//  apunta al propio celular y da "conexión rechazada".
//
//  Dejala VACÍA ('') y el sistema intenta usar solo la IP de red de
//  la PC servidor. O, mejor y más estable, ESCRIBÍ acá la base
//  pública, por ejemplo la IP de tu PC en la red Wi-Fi:
//      define('URL_PUBLICA', 'http://192.168.0.15/TermoPlast');
//  (en Windows la ves con  ipconfig  → "Dirección IPv4").
//  Si algún día lo subís a un hosting con dominio:
//      define('URL_PUBLICA', 'https://termoplast.midominio.com');
//
//  Requisitos para escanear desde otro celular:
//   · el celular y la PC en la MISMA red Wi-Fi;
//   · el Firewall de Windows debe permitir Apache (puerto 80).
// ------------------------------------------------------------
if (!defined('URL_PUBLICA')) {
    // IP de red Wi-Fi de la PC servidor (ipconfig → "Dirección IPv4").
    // Si cambiás de red y la IP cambia, actualizá esta línea.
    define('URL_PUBLICA', 'http://192.168.1.66/TermoPlast');
}