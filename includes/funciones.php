<?php
// ============================================================
//  funciones.php
//  Cálculos COMPARTIDOS del sistema. Fuente única de fórmulas,
//  para que un mismo dato no se calcule distinto en cada página.
// ============================================================

/**
 * CO₂ evitado (en kg) de un emprendedor.
 * Usa el FACTOR REAL de cada material (materiales.co2_por_gramo),
 * el mismo criterio que la estimación al hacer el pedido.
 * Suma sobre los pedidos FINALIZADOS del emprendedor.
 */
function co2_evitado_kg(PDO $pdo, int $eid): float {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(p.gramos_totales * m.co2_por_gramo), 0)
         FROM pedidos p
         INNER JOIN materiales m ON p.material_id = m.id
         WHERE p.emprendedor_id = ? AND p.estado = 'finalizado'"
    );
    $stmt->execute([$eid]);
    return round((float)$stmt->fetchColumn(), 1);
}

/**
 * Clase CSS del badge según el estado del pedido.
 * FUENTE ÚNICA: antes cada vista lo decidía por su cuenta y un mismo
 * estado (p. ej. "moldeado") se mostraba de distinto color en el panel
 * del admin y en el seguimiento del emprendedor.
 */
function clase_badge_pedido(?string $estado): string {
    switch ($estado) {
        case 'en_calentamiento': return 'badge-calentamiento';
        case 'moldeado':         return 'badge-moldeado';
        case 'finalizado':       return 'badge-finalizado';
        case 'cancelado':        return 'badge-cancelado';
        default:                 return 'badge-registrado';
    }
}

/**
 * TRAZABILIDAD — registra un cambio de estado de un pedido.
 * Deja una fila con fecha/hora en pedidos_historial. Es la base de la
 * "trazabilidad de producción": cada etapa queda registrada, no se pisa.
 *
 * @param string|null $nota   Texto corto que explica el cambio (opcional).
 * @param string|null $admin  Usuario admin que lo hizo; NULL si fue el
 *                            emprendedor o el propio sistema.
 */
function registrar_historial(PDO $pdo, int $pedido_id, string $estado, ?string $nota = null, ?string $admin = null): void {
    $stmt = $pdo->prepare(
        "INSERT INTO pedidos_historial (pedido_id, estado, nota, cambiado_por)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$pedido_id, $estado, $nota, $admin]);
}

/**
 * IP de red (LAN) del servidor. Truco: "conectamos" un socket UDP hacia
 * afuera (no envía nada) y el sistema operativo nos dice qué IP local
 * eligió para salir — esa es la IP de la máquina en la red. Devuelve
 * NULL si no se pudo determinar.
 */
function ip_lan_servidor(): ?string {
    try {
        $s = @stream_socket_client('udp://8.8.8.8:53', $err, $errstr, 1);
        if ($s) {
            $local = @stream_socket_get_name($s, false);   // "IP:puerto" local
            fclose($s);
            if ($local) {
                $ip = preg_replace('/:\d+$/', '', $local);
                if ($ip && $ip !== '0.0.0.0' && $ip !== '127.0.0.1') return $ip;
            }
        }
    } catch (\Throwable $e) { /* sin red: devolvemos null */ }
    return null;
}

/** ¿La IP está en un rango privado (LAN)? (10/8, 172.16/12, 192.168/16) */
function es_ip_privada(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

/**
 * ¿La interfaz/IP parece de un adaptador VIRTUAL? (VirtualBox, VMware,
 * Docker, WSL, Hyper-V) — esas IPs el celular NO las alcanza.
 */
function es_interfaz_virtual(string $nombre, string $ip): bool {
    $n = strtolower($nombre);
    foreach (['virtualbox', 'vmware', 'vethernet', 'hyper-v', 'docker', 'wsl', 'vboxnet', 'veth', 'br-', 'tap', 'tun', 'loopback'] as $k) {
        if (strpos($n, $k) !== false) return true;
    }
    if (strpos($ip, '192.168.56.') === 0) return true;   // VirtualBox Host-Only por defecto
    if (preg_match('/^172\.(1[7-9]|2\d|3[01])\./', $ip)) return true; // rangos típicos de Docker
    return false;
}

/**
 * Lista de IPs IPv4 locales (no loopback), con su interfaz y una marca
 * de si parece virtual y si es privada. La usa la página de diagnóstico.
 */
function ips_locales(): array {
    $out = [];
    if (!function_exists('net_get_interfaces')) return $out;
    $ifs = @net_get_interfaces();
    if (!is_array($ifs)) return $out;
    foreach ($ifs as $nombre => $info) {
        foreach (($info['unicast'] ?? []) as $u) {
            if (($u['family'] ?? null) !== 2) continue;          // solo IPv4
            $ip = $u['address'] ?? '';
            if ($ip === '' || strpos($ip, '127.') === 0) continue; // sin loopback
            $out[] = [
                'interfaz' => $nombre,
                'ip'       => $ip,
                'privada'  => es_ip_privada($ip),
                'virtual'  => es_interfaz_virtual($nombre, $ip),
            ];
        }
    }
    return $out;
}

/**
 * Mejor IP de LAN para el QR: prioriza la del "route por defecto" (la que
 * usa la PC para salir a la red) si es privada y no virtual; si no, la
 * primera IP privada no-virtual de la lista. NULL si no hay candidata.
 */
function mejor_ip_lan(): ?string {
    $ruta = ip_lan_servidor();
    if ($ruta && es_ip_privada($ruta) && !es_interfaz_virtual('', $ruta)) {
        return $ruta;
    }
    // Fallback: recorremos las interfaces y elegimos la mejor privada real.
    $cands = array_filter(ips_locales(), fn($c) => $c['privada'] && !$c['virtual']);
    // Preferimos 192.168.x, después 10.x, después el resto.
    usort($cands, function ($a, $b) {
        $p = fn($ip) => strpos($ip, '192.168.') === 0 ? 0 : (strpos($ip, '10.') === 0 ? 1 : 2);
        return $p($a['ip']) <=> $p($b['ip']);
    });
    if ($cands) return $cands[0]['ip'];
    // Último intento: la del route, aunque no pasara los filtros.
    return $ruta;
}

/**
 * Base ABSOLUTA del sitio para los QR (esquema + host + ruta).
 * Prioridad:
 *   1) la constante URL_PUBLICA (config/catalogo.php), si está cargada;
 *   2) si entrás por localhost, se reemplaza por la mejor IP de LAN real
 *      (descartando adaptadores virtuales), para que el QR se pueda
 *      escanear desde otro dispositivo;
 *   3) en cualquier otro caso, el host por el que entraste.
 */
function url_publica_base(): string {
    if (defined('URL_PUBLICA') && URL_PUBLICA !== '') {
        return rtrim(URL_PUBLICA, '/');
    }
    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $soloHost = preg_replace('/:\d+$/', '', $host);

    if (in_array($soloHost, ['localhost', '127.0.0.1', '::1'], true)) {
        $ip = mejor_ip_lan();
        if ($ip) {
            $puerto = '';
            if (preg_match('/:(\d+)$/', $host, $m) && $m[1] !== '80') { $puerto = ':' . $m[1]; }
            $host = $ip . $puerto;
        }
    }
    $base = defined('BASE_URL') ? BASE_URL : '';
    return ($esHttps ? 'https' : 'http') . '://' . $host . $base;
}

/**
 * ¿La base de los QR sigue siendo localhost? (para avisar que no se
 * podrá escanear desde otro dispositivo).
 */
function qr_no_escaneable(string $base): bool {
    return (bool)preg_match('#^https?://(localhost|127\.0\.0\.1|\[::1\])#i', $base);
}

/**
 * TRAZABILIDAD — devuelve la línea de tiempo completa de un pedido,
 * de la etapa más vieja a la más nueva.
 */
function historial_pedido(PDO $pdo, int $pedido_id): array {
    $stmt = $pdo->prepare(
        "SELECT estado, nota, cambiado_por, fecha
         FROM pedidos_historial
         WHERE pedido_id = ?
         ORDER BY fecha ASC, id ASC"
    );
    $stmt->execute([$pedido_id]);
    return $stmt->fetchAll();
}
