<?php
// ============================================================
//  sesion_segura.php
//  Sesión endurecida + CABECERAS de seguridad (con CSP reforzada
//  por NONCE) + ayudas anti-CSRF.
// ============================================================

// --- RUTAS BASE de la app (para enlaces y recursos) ---
// RAIZ: carpeta raíz del proyecto en disco (este archivo vive en includes/).
if (!defined('RAIZ')) { define('RAIZ', dirname(__DIR__)); }
// BASE_URL: ruta web hasta la raíz de la app (p. ej. "/TermoPlast"), calculada
// desde el script actual sin importar en qué subcarpeta esté la página.
if (!defined('BASE_URL')) {
    $s = $_SERVER['SCRIPT_NAME'] ?? '';
    $s = preg_replace('#/(auth|pages|admin|api)/[^/]+\.php$#', '', $s);
    $s = preg_replace('#/[^/]+\.php$#', '', $s);
    define('BASE_URL', $s);
}

// --- NONCE de CSP: valor aleatorio ÚNICO por cada carga de página.
// Cada <script> en línea debe llevar este nonce para poder ejecutarse.
// Así, un script inyectado por un atacante (sin el nonce) NO corre.
if (!isset($GLOBALS['CSP_NONCE'])) {
    $GLOBALS['CSP_NONCE'] = base64_encode(random_bytes(16));
}
function csp_nonce(): string {
    return $GLOBALS['CSP_NONCE'];
}

// --- CABECERAS DE SEGURIDAD ---
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    // Aislamiento de origen: evita que otra ventana/origen tenga referencia a
    // esta página y limita quién puede cargar sus recursos.
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    // HSTS: solo tiene sentido sobre HTTPS (en XAMPP con http no se envía).
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    // CSP REFORZADA: los scripts solo corren si son del propio sitio ('self')
    // o traen el nonce de esta carga. Se quitó 'unsafe-inline' de script-src.
    // (style-src mantiene 'unsafe-inline' porque la app usa estilos en línea.)
    $n = csp_nonce();
    header("Content-Security-Policy: default-src 'self'; "
         . "img-src 'self' data:; "
         . "style-src 'self' 'unsafe-inline'; "
         . "script-src 'self' 'nonce-$n'; "
         . "object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
}

// --- Arranque endurecido de la sesión ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'path'     => '/',
    ]);
    session_start();
}

// --- Rotación periódica del ID de sesión ---
if (!isset($_SESSION['sesion_creada'])) {
    $_SESSION['sesion_creada'] = time();
} elseif (time() - $_SESSION['sesion_creada'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['sesion_creada'] = time();
}

// ------------------------------------------------------------
//  TOKEN ANTI-CSRF
// ------------------------------------------------------------
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_campo() {
    echo '<input type="hidden" name="csrf" value="'
       . htmlspecialchars($_SESSION['csrf']) . '">';
}
function csrf_validar() {
    $enviado = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $enviado)) {
        http_response_code(419);
        die('Sesión inválida o token vencido. Volvé atrás y reintentá.');
    }
}
