<?php
// ============================================================
//  seguridad.php  —  Utilidades de seguridad (N2)
//  Bloqueo de login por fuerza bruta, auditoría de eventos y
//  medición de fuerza de contraseña (lado servidor).
//  Requiere una conexión $pdo ya disponible.
// ============================================================

// IP del visitante (para logs y bloqueos).
function ip_cliente(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Guarda un evento de seguridad. Nunca rompe el flujo si falla el log.
function registrar_evento(PDO $pdo, string $tipo, string $detalle = '', string $usuario = ''): void {
    try {
        $st = $pdo->prepare(
            "INSERT INTO eventos_seguridad (tipo, detalle, ip, usuario) VALUES (?, ?, ?, ?)"
        );
        $st->execute([$tipo, mb_substr($detalle, 0, 255), ip_cliente(), mb_substr($usuario, 0, 150)]);
    } catch (PDOException $e) { /* el log es best-effort */ }
}

// Registra un intento de login (exitoso o no).
function registrar_intento(PDO $pdo, string $identidad, bool $exito): void {
    try {
        $st = $pdo->prepare(
            "INSERT INTO intentos_login (identidad, ip, exito) VALUES (?, ?, ?)"
        );
        $st->execute([mb_substr($identidad, 0, 150), ip_cliente(), $exito ? 1 : 0]);
    } catch (PDOException $e) {}
}

// Devuelve los SEGUNDOS de bloqueo que faltan (0 = no está bloqueado).
// Regla: 5 intentos fallidos en 15 minutos bloquean por lo que resta de esa ventana.
function segundos_bloqueo(PDO $pdo, string $identidad, int $max = 5, int $ventana_min = 15): int {
    $ventana_min = (int)$ventana_min;   // se inyecta como entero controlado (no viene del usuario)
    try {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM intentos_login
             WHERE identidad = ? AND exito = 0
               AND intento_en > (NOW() - INTERVAL $ventana_min MINUTE)"
        );
        $st->execute([$identidad]);
        if ((int)$st->fetchColumn() < $max) return 0;

        $st2 = $pdo->prepare(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), (MAX(intento_en) + INTERVAL $ventana_min MINUTE))
             FROM intentos_login
             WHERE identidad = ? AND exito = 0
               AND intento_en > (NOW() - INTERVAL $ventana_min MINUTE)"
        );
        $st2->execute([$identidad]);
        return max(0, (int)$st2->fetchColumn());
    } catch (PDOException $e) {
        return 0;   // ante un error, no bloqueamos (mejor no dejar afuera a usuarios legítimos)
    }
}

// Puntúa la fuerza de una contraseña de 0 (débil) a 4 (fuerte).
function fuerza_password(string $p): int {
    $score = 0;
    if (strlen($p) >= 8)  $score++;
    if (strlen($p) >= 12) $score++;
    if (preg_match('/[A-Z]/', $p) && preg_match('/[a-z]/', $p)) $score++;
    if (preg_match('/\d/', $p) && preg_match('/[^A-Za-z0-9]/', $p)) $score++;
    return min(4, $score);
}

// Hash señuelo (bcrypt válido) para IGUALAR TIEMPOS de respuesta cuando el
// usuario no existe. Evita el "ataque de enumeración por tiempo": la respuesta
// tarda lo mismo exista o no la cuenta.
function hash_senuelo(): string {
    return '$2y$12$3HsYmapPcQ/.D11mDDaS0u6DnblAfpcbPaUtUOSEI0gQgScH.lgmu';
}

// ============================================================
//  RECUPERACIÓN DE CONTRASEÑA ("olvidé mi contraseña")
//  Reglas de seguridad:
//   - El token viaja al usuario; en la base guardamos SOLO su hash.
//   - Vence a los 30 minutos y es de UN SOLO USO.
//   - Al pedir uno nuevo, se anulan los anteriores sin usar.
// ============================================================

// Minutos de validez de un enlace de recuperación.
const RESET_MINUTOS = 30;

// Borra tokens vencidos o ya usados (higiene de la tabla). Best-effort.
function reset_limpiar(PDO $pdo): void {
    try {
        $pdo->query("DELETE FROM password_resets WHERE expira < NOW() OR usado = 1");
    } catch (PDOException $e) {}
}

// Crea un token para un emprendedor y devuelve el token EN CLARO
// (para armar el enlace). En la base queda solo su hash SHA-256.
function reset_crear_token(PDO $pdo, int $emprendedor_id): string {
    // Anulamos cualquier pedido anterior que siga vigente.
    $anular = $pdo->prepare("UPDATE password_resets SET usado = 1 WHERE emprendedor_id = ? AND usado = 0");
    $anular->execute([$emprendedor_id]);

    $token = bin2hex(random_bytes(32));            // 256 bits de aleatoriedad
    $hash  = hash('sha256', $token);               // lo que se guarda
    $exp   = date('Y-m-d H:i:s', time() + RESET_MINUTOS * 60);

    $st = $pdo->prepare(
        "INSERT INTO password_resets (emprendedor_id, token_hash, expira) VALUES (?, ?, ?)"
    );
    $st->execute([$emprendedor_id, $hash, $exp]);
    return $token;
}

// Devuelve ['id','emprendedor_id'] si el token es válido (existe, no usado
// y no vencido); NULL en cualquier otro caso.
function reset_token_valido(PDO $pdo, string $token): ?array {
    if ($token === '' || !ctype_xdigit($token)) return null;
    $hash = hash('sha256', $token);
    $st = $pdo->prepare(
        "SELECT id, emprendedor_id FROM password_resets
         WHERE token_hash = ? AND usado = 0 AND expira > NOW()
         LIMIT 1"
    );
    $st->execute([$hash]);
    $fila = $st->fetch();
    return $fila ?: null;
}

// Marca un token como consumido (un solo uso).
function reset_marcar_usado(PDO $pdo, int $reset_id): void {
    $st = $pdo->prepare("UPDATE password_resets SET usado = 1 WHERE id = ?");
    $st->execute([$reset_id]);
}

// Anti-abuso: ¿pidió demasiados enlaces en la ventana reciente?
function reset_demasiados_pedidos(PDO $pdo, int $emprendedor_id, int $max = 3, int $ventana_min = 15): bool {
    $ventana_min = (int)$ventana_min;
    try {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM password_resets
             WHERE emprendedor_id = ? AND creado_en > (NOW() - INTERVAL $ventana_min MINUTE)"
        );
        $st->execute([$emprendedor_id]);
        return (int)$st->fetchColumn() >= $max;
    } catch (PDOException $e) {
        return false;
    }
}
