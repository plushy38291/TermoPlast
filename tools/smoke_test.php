<?php
/**
 * smoke_test.php — Prueba de humo de TermoPlast (sin dependencias).
 *
 * Verifica, contra un servidor en marcha, que:
 *   · las páginas PÚBLICAS responden 200,
 *   · las páginas PROTEGIDAS redirigen (302) si no hay sesión,
 *   · los assets (css/js) responden 200,
 *   · ninguna respuesta contiene errores de PHP (Fatal/Parse/Warning/Notice),
 *   · opcionalmente, si se pasan credenciales, entra y revisa las vistas internas.
 *
 * USO (desde la carpeta del proyecto, con Apache/XAMPP corriendo):
 *   php tools/smoke_test.php                         # usa http://localhost/TermoPlast
 *   php tools/smoke_test.php http://localhost/TermoPlast
 *   php tools/smoke_test.php http://localhost/TermoPlast admin:CLAVE gero1@gmail.com:CLAVE
 *
 * Devuelve código de salida 0 si todo pasó, 1 si hubo fallos (útil para CI).
 */

$BASE = rtrim($argv[1] ?? 'http://localhost/TermoPlast', '/');
$adminArg = $argv[2] ?? '';   // usuario:clave  (admin)
$empArg   = $argv[3] ?? '';   // email:clave    (emprendedor)

$JAR = tempnam(sys_get_temp_dir(), 'tp_');
$fail = 0; $pass = 0;

function req(string $url, array $opt = []): array {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,     // queremos ver los 302
        CURLOPT_COOKIEJAR => $JAR,
        CURLOPT_COOKIEFILE => $JAR,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,     // certificado autofirmado en HTTPS local
    ] + $opt);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsz  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $body = $resp === false ? '' : substr($resp, $hsz);
    return ['code' => (int)$code, 'body' => $body, 'ok' => $resp !== false];
}

function tiene_error_php(string $html): bool {
    return (bool)preg_match('/(Fatal error|Parse error|Uncaught|Warning:|Deprecated:|Notice:|Undefined )/i', $html);
}

function check(string $label, string $url, array $expect): void {
    global $fail, $pass;
    $r = req($url);
    $okCode = in_array($r['code'], $expect, true);
    // El escaneo de errores PHP es solo para páginas HTML: un .js/.css minificado
    // puede contener literales como "Warning:" o "Notice:" y daría falso positivo.
    $esAsset = (bool)preg_match('/\.(js|css|map|png|jpg|svg|ico|woff2?)(\?|$)/i', $url);
    $noErr  = $esAsset ? true : !tiene_error_php($r['body']);
    $ok = $r['ok'] && $okCode && $noErr;
    printf("  [%s] %-42s -> %s %s%s\n",
        $ok ? 'OK ' : 'XX ', $label, $r['code'],
        $okCode ? '' : '(esperaba ' . implode('/', $expect) . ') ',
        $noErr ? '' : '*** ERROR PHP EN LA PÁGINA ***');
    $ok ? $pass++ : $fail++;
}

function csrf(string $html): string {
    return preg_match('/name="csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

echo "== TermoPlast · smoke test ==\n   BASE = $BASE\n\n";

echo "Públicas (esperado 200):\n";
foreach (['index.php','auth/login.php','auth/registro_emprendedor.php','auth/recuperar.php'] as $p)
    check($p, "$BASE/$p", [200]);

echo "\nAssets (esperado 200):\n";
foreach (['assets/css/estilos.css','assets/js/anim.js','assets/js/vendor/chart.umd.js','assets/js/tema.js'] as $p)
    check($p, "$BASE/$p", [200]);

echo "\nProtegidas sin sesión (esperado 302 redirección a login):\n";
foreach (['pages/panel.php','pages/impacto.php','pages/seguimiento.php','admin/dashboard.php','admin/admin_panel.php'] as $p)
    check($p, "$BASE/$p", [302]);

// ---- Chequeo profundo opcional (si se pasan credenciales) ----
function login_form(string $base, string $path, array $campos): bool {
    $page = req("$base/$path");
    $tok = csrf($page['body']);
    if ($tok === '') return false;
    $campos['csrf'] = $tok;
    $r = req("$base/$path", [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($campos)]);
    return in_array($r['code'], [200, 302], true);
}

if ($adminArg) {
    [$u, $c] = array_pad(explode(':', $adminArg, 2), 2, '');
    echo "\nAdmin logueado ($u):\n";
    if (login_form($BASE, 'admin/admin_login.php', ['usuario' => $u, 'password' => $c])) {
        foreach (['admin/dashboard.php','admin/admin_panel.php','admin/admin_seguridad.php',
                  'admin/abm.php?e=materiales','admin/abm.php?e=etiquetas','admin/abm.php?e=puntos',
                  'admin/abm.php?e=emprendedores','admin/abm.php?e=usuarios',
                  'admin/exportar.php?tipo=pedidos&formato=csv'] as $p)
            check($p, "$BASE/$p", [200]);
    } else { echo "  XX no se pudo iniciar sesión admin\n"; $fail++; }
}

if ($empArg) {
    [$e, $c] = array_pad(explode(':', $empArg, 2), 2, '');
    echo "\nEmprendedor logueado ($e):\n";
    if (login_form($BASE, 'auth/login.php', ['email' => $e, 'password' => $c])) {
        foreach (['pages/panel.php','pages/impacto.php','pages/seguimiento.php','pages/hacer_pedido.php',
                  'pages/subir_diseno.php','pages/mis_disenos.php','pages/perfil.php','pages/mapa.php',
                  'pages/ver_diseno.php?id=1'] as $p)   // visor de SVG (debe dar 200, nunca 404)
            check($p, "$BASE/$p", [200]);
    } else { echo "  XX no se pudo iniciar sesión emprendedor\n"; $fail++; }
}

@unlink($JAR);
echo "\n== Resultado: $pass OK · $fail fallos ==\n";
exit($fail > 0 ? 1 : 0);
