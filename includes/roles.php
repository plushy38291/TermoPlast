<?php
// ============================================================
//  roles.php — Control de acceso por ROL (requisito 3).
//
//  El sistema maneja TRES roles:
//    · administrador  → acceso total (gestión de usuarios, seguridad).
//    · operador       → tareas operativas (pedidos, catálogos, export,
//                       dashboard) PERO no gestiona usuarios ni seguridad.
//    · usuario básico → el EMPRENDEDOR (su propia área, otro login).
//
//  Estas funciones se usan en las páginas de /admin para mostrar u
//  ocultar opciones y para bloquear el acceso directo por URL.
// ============================================================

// Rol del admin logueado (por defecto 'administrador' para no romper
// sesiones viejas creadas antes de esta versión).
function admin_rol(): string {
    return $_SESSION['admin_rol'] ?? 'administrador';
}

// ¿El admin logueado es administrador pleno?
function es_administrador(): bool {
    return admin_rol() === 'administrador';
}

// Etiqueta linda del rol (para mostrarlo en pantalla).
function rol_lindo(string $rol): string {
    return [
        'administrador' => 'Administrador',
        'operador'      => 'Operador',
    ][$rol] ?? $rol;
}

// Corta el acceso si el rol actual no está entre los permitidos.
// Uso: requiere_rol('administrador');  (al inicio de una página sensible)
function requiere_rol(string ...$roles): void {
    if (!in_array(admin_rol(), $roles, true)) {
        http_response_code(403);
        $base = defined('BASE_URL') ? BASE_URL : '';
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
           . '<link rel="stylesheet" href="' . htmlspecialchars($base) . '/assets/css/estilos.css"></head><body>'
           . '<div class="auth-wrapper"><div class="auth-card fade-in">'
           . '<h1 class="auth-title">Acceso restringido 🔒</h1>'
           . '<p class="auth-subtitle">Esta sección es solo para administradores. '
           . 'Tu rol actual es <strong>' . htmlspecialchars(rol_lindo(admin_rol())) . '</strong>.</p>'
           . '<a class="btn btn-block" href="' . htmlspecialchars($base) . '/admin/admin_panel.php">← Volver al panel</a>'
           . '</div></div></body></html>';
        exit;
    }
}

// ------------------------------------------------------------
//  MENÚ del panel de administración.
//  Cada ítem declara qué roles pueden verlo. El menú se arma solo
//  mostrando únicamente lo permitido para el rol actual.
// ------------------------------------------------------------
function admin_menu(): array {
    // [clave => [texto, archivo, roles_permitidos]]
    return [
        'panel'     => ['Panel',          'admin_panel.php',     ['administrador', 'operador']],
        'dashboard' => ['Dashboard',      'dashboard.php',       ['administrador', 'operador']],
        'materiales'=> ['Materiales',     'abm.php?e=materiales',['administrador', 'operador']],
        'etiquetas' => ['Etiquetas',      'abm.php?e=etiquetas', ['administrador', 'operador']],
        'puntos'    => ['Puntos de retiro','abm.php?e=puntos',   ['administrador', 'operador']],
        'emprendedores' => ['Emprendedores','abm.php?e=emprendedores', ['administrador', 'operador']],
        'usuarios'  => ['Usuarios',       'abm.php?e=usuarios',  ['administrador']],   // solo admin
        'seguridad' => ['Seguridad',      'admin_seguridad.php', ['administrador']],   // solo admin
        'conocimiento' => ['Conocimiento IA', 'conocimiento.php', ['administrador']],  // solo admin
    ];
}
