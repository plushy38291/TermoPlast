<?php
// ============================================================
//  chatbot_responder.php
//  Puente entre el chat (front) y el microservicio de IA (Python).
//  1) Recibe el mensaje del usuario.
//  2) Le pide al servicio Python que ANALICE la intención.
//  3) Según la intención, responde con datos REALES de la base
//     (estado de pedidos, impacto) o con una respuesta de FAQ.
// ============================================================
require __DIR__ . '/../includes/proteger.php';        // solo usuarios logueados
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/catalogo.php';
require __DIR__ . '/../includes/funciones.php';

header("Content-Type: application/json; charset=utf-8");

// Anti-CSRF (el widget envía el token).
csrf_validar();

$mensaje = trim($_POST["mensaje"] ?? "");
$mensaje = mb_substr($mensaje, 0, 1000);   // tope de seguridad (igual que el input)
if ($mensaje === "") {
    echo json_encode(["respuesta" => "Escribime tu consulta 🙂", "intencion" => "vacio"]);
    exit;
}

// --- 1) Consultar al microservicio de IA ---
// Pasamos también el HISTORIAL breve de la charla, para que el LLM pueda seguir
// el hilo en preguntas de seguimiento ("¿y cuánto tarda?").
// Timeouts: conexión corta (detecta rápido si el servicio está caído) pero
// respuesta amplia (un LLM en la nube puede tardar varios segundos).
function analizar_intencion(string $texto, array $historial = []): ?array {
    $payload = json_encode(["texto" => $texto, "historial" => $historial]);
    if (function_exists("curl_init")) {
        $ch = curl_init("http://127.0.0.1:5001/responder");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 2,   // si no está levantado, falla enseguida
            CURLOPT_TIMEOUT => 20,         // pero damos tiempo a que el LLM genere
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(["http" => [
            "method" => "POST", "header" => "Content-Type: application/json",
            "content" => $payload, "timeout" => 20,
        ]]);
        $resp = @file_get_contents("http://127.0.0.1:5001/responder", false, $ctx);
    }
    if ($resp === false || $resp === null) return null;
    $d = json_decode($resp, true);
    return is_array($d) ? $d : null;
}

// Minúsculas sin tildes (para detectar el tipo de pieza en la estimación).
function quita_tildes_min(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u']);
}

// RAZONAMIENTO: extrae [tipo, cantidad] de un mensaje para la estimación.
// Devuelve null en cada campo que no aparezca. Reutilizable para el
// "slot-filling" (completar lo que falta en el siguiente mensaje).
function extraer_pieza_cant(string $mensaje): array {
    $t = quita_tildes_min($mensaje);
    $tipo = null;
    if (strpos($t, 'llavero') !== false) $tipo = 'llavero';
    elseif (strpos($t, 'maceta') !== false || strpos($t, 'macetero') !== false) $tipo = 'maceta';
    elseif (preg_match('/porta ?vaso|posa ?vaso/u', $t)) $tipo = 'portavasos';
    // Número "suelto" (no pegado a letras ni a otros dígitos, p. ej. el 2 de "co2").
    $cant = preg_match('/(?<![\p{L}\d])(\d{1,5})(?![\p{L}\d])/u', $mensaje, $cm) ? (int)$cm[1] : null;
    return [$tipo, $cant];
}

$historial = $_SESSION['cb_historial'] ?? [];   // turnos previos (para el hilo del LLM)
$analisis = analizar_intencion($mensaje, $historial);

// Si el servicio de IA no responde, avisamos con elegancia.
if ($analisis === null) {
    echo json_encode([
        "respuesta" => "El asistente no está disponible en este momento. Probá más tarde o revisá las secciones del menú.",
        "intencion" => "servicio_caido",
    ]);
    exit;
}

$intencion = $analisis["intencion"] ?? "fallback";
$confianza = $analisis["confianza"] ?? 0;
$dudosa    = !empty($analisis["dudosa"]);
$alts      = $analisis["alternativas"] ?? [];
$eid = (int)$_SESSION["emprendedor_id"];

// Nombre "lindo" de cada intención (para pedir aclaración en lenguaje natural).
$INTENT_LABEL = [
    'estado_pedido'     => 'el estado de un pedido',
    'diseno_estado'     => 'el estado de tus diseños',
    'resumen_cuenta'    => 'un resumen de tu cuenta',
    'como_subir_diseno' => 'cómo subir un diseño',
    'como_pedir'        => 'cómo hacer un pedido',
    'cancelar_pedido'   => 'cancelar un pedido',
    'materiales'        => 'los materiales',
    'punto_retiro'      => 'el punto de retiro',
    'impacto'           => 'tu impacto ambiental',
    'limites_maquina'   => 'los límites de tamaño',
    'tiempos'           => 'los tiempos de fabricación',
    'cuenta'            => 'tu cuenta',
    'que_es'            => 'qué es TermoPlast',
    'contacto'          => 'el contacto',
    'ayuda'             => 'la ayuda general',
    'estimacion'        => 'una estimación de material',
    'costos'            => 'los costos',
    'horarios'          => 'los horarios de atención/retiro',
    'modificar_pedido'  => 'modificar un pedido',
    'registro_ayuda'    => 'cómo crear una cuenta',
    'seguridad_cuenta'  => 'la seguridad de tu cuenta (2FA/contraseña)',
    'tipos_piezas'      => 'qué piezas se pueden fabricar',
    'cantidad_minima'   => 'la cantidad mínima',
    'aportar_tapitas'   => 'cómo se aportan las tapitas',
    'formato_archivo'   => 'el formato del archivo',
    'envio'             => 'envíos y retiro',
    'calidad_piezas'    => 'la calidad de las piezas',
    'sustentabilidad'   => 'el impacto ambiental',
    'colores'           => 'los colores de las piezas',
];

// Acciones (botones de navegación) que el bot ofrece según la intención.
// La URL se arma con BASE_URL para funcionar en cualquier carpeta.
$ACCIONES = [
    'como_pedir'        => ['Ir a Hacer Pedido',  '/pages/hacer_pedido.php'],
    'estimacion'        => ['Hacer el pedido',    '/pages/hacer_pedido.php'],
    'como_subir_diseno' => ['Subir un diseño',    '/pages/subir_diseno.php'],
    'estado_pedido'     => ['Ver Seguimiento',    '/pages/seguimiento.php'],
    'cancelar_pedido'   => ['Ir a Seguimiento',   '/pages/seguimiento.php'],
    'diseno_estado'     => ['Ver Mis Diseños',    '/pages/mis_disenos.php'],
    'impacto'           => ['Ver Impacto',        '/pages/impacto.php'],
    'resumen_cuenta'    => ['Ver Impacto',        '/pages/impacto.php'],
    'cuenta'            => ['Ir a Mi Perfil',     '/pages/perfil.php'],
    'punto_retiro'      => ['Ver Seguimiento',    '/pages/seguimiento.php'],
    'horarios'          => ['Ver Puntos de retiro','/pages/mapa.php'],
    'modificar_pedido'  => ['Ir a Seguimiento',   '/pages/seguimiento.php'],
    'seguridad_cuenta'  => ['Ir a Mi Perfil',     '/pages/perfil.php'],
    'registro_ayuda'    => ['Ir al acceso',       '/auth/login.php'],
    'tipos_piezas'      => ['Hacer un pedido',    '/pages/hacer_pedido.php'],
    'cantidad_minima'   => ['Hacer un pedido',    '/pages/hacer_pedido.php'],
    'formato_archivo'   => ['Subir un diseño',    '/pages/subir_diseno.php'],
    'envio'             => ['Ver Puntos de retiro','/pages/mapa.php'],
    'aportar_tapitas'   => ['Ver Puntos de retiro','/pages/mapa.php'],
    'sustentabilidad'   => ['Ver Impacto',        '/pages/impacto.php'],
];

// --- RAZONAMIENTO 1: entidad "número de pedido" ---
// Detectamos "pedido 12", "orden N° 7", "#5" o un mensaje que es SOLO un número
// (follow-up del tipo: "¿estado de mi pedido?" → "12").
$num_pedido = null;
$ctx_previo = $_SESSION['cb_ctx'] ?? '';
$pend_estim = $_SESSION['cb_estim'] ?? null;          // estimación a medias (tipo/cant)
[$tipo_msg, $cant_msg] = extraer_pieza_cant($mensaje); // sirve para estimación y su slot-filling

if (preg_match('/(?:pedido|orden|n[°º\.]?)\s*#?\s*(\d{1,6})/iu', $mensaje, $mm)) {
    $num_pedido = (int)$mm[1];
} elseif (preg_match('/^\s*#?(\d{1,6})\s*$/', $mensaje, $mm)) {
    // Un número suelto. Si hay una estimación pendiente esperando la
    // cantidad, el número COMPLETA la estimación (no es un nº de pedido).
    if (is_array($pend_estim) && !empty($pend_estim['tipo']) && empty($pend_estim['cant'])) {
        $intencion = 'estimacion';
        $dudosa    = false;
        $cant_msg  = (int)$mm[1];
    } else {
        // En esta app un número suelto casi siempre refiere a un pedido.
        $num_pedido = (int)$mm[1];
        $intencion  = 'estado_pedido';
        $dudosa     = false;
    }
}

// Si el usuario, tras pedirle la PIEZA, responde solo "llaveros"/"macetas",
// también retomamos la estimación pendiente.
if (is_array($pend_estim) && empty($pend_estim['tipo']) && $tipo_msg !== null
    && $intencion !== 'estimacion' && $num_pedido === null) {
    $intencion = 'estimacion';
    $dudosa    = false;
}

// --- RAZONAMIENTO: follow-up sobre el ÚLTIMO pedido mencionado ---
// Si no vino un número y el mensaje es del tipo "¿y cuándo estará?",
// "¿cuánto falta?", "¿y ese?", reutilizamos el último pedido consultado.
$cb_last = $_SESSION['cb_last_pedido'] ?? null;
if ($num_pedido === null && $cb_last
    && preg_match('/\b(cu[aá]ndo|cuanto\s+falta|falta\s+mucho|y\s+ese|y\s+el\s+mismo|lo\s+mismo|ese\s+pedido|mi\s+pedido|el\s+anterior|est[aá]\s+listo|ya\s+est[aá])\b/iu', $mensaje)) {
    $num_pedido = (int)$cb_last;
    $intencion  = 'estado_pedido';
    $dudosa     = false;
}

// --- ACCIÓN / FLUJO: "sí" acepta lo último que ofreció el bot; "no" lo descarta ---
if ($intencion === 'afirmar' && $num_pedido === null) {
    $oferta = $_SESSION['cb_oferta'] ?? null;
    unset($_SESSION['cb_oferta']);
    $_SESSION['cb_ctx'] = 'afirmar';
    if ($oferta) {
        echo json_encode(["respuesta" => "¡Dale! Te dejo el acceso directo 👇", "intencion" => "afirmar",
                          "confianza" => $confianza, "accion" => $oferta]);
    } else {
        echo json_encode(["respuesta" => "¡Dale! ¿Sobre qué avanzamos? Puedo con pedidos, diseños, impacto, materiales y más.",
                          "intencion" => "afirmar", "confianza" => $confianza]);
    }
    exit;
}
if ($intencion === 'negar' && $num_pedido === null) {
    unset($_SESSION['cb_oferta'], $_SESSION['cb_estim']);
    $_SESSION['cb_ctx'] = 'negar';
    echo json_encode(["respuesta" => "Listo, sin problema. Si necesitás otra cosa, acá estoy 🙂",
                      "intencion" => "negar", "confianza" => $confianza]);
    exit;
}

// --- RAZONAMIENTO 2: pedir ACLARACIÓN cuando la IA duda entre dos temas ---
if ($dudosa && count($alts) >= 2 && $num_pedido === null) {
    $a = $alts[0]["intencion"] ?? ''; $b = $alts[1]["intencion"] ?? '';
    $la = $INTENT_LABEL[$a] ?? null;  $lb = $INTENT_LABEL[$b] ?? null;
    if ($la && $lb && $la !== $lb) {
        $_SESSION['cb_ctx'] = 'aclaracion';
        echo json_encode([
            "respuesta" => "Para no equivocarme: ¿me estás preguntando por <strong>{$la}</strong> o por <strong>{$lb}</strong>? Decímelo y te respondo al toque.",
            "intencion" => "aclaracion",
            "confianza" => $confianza,
        ]);
        exit;
    }
}

// --- 2) Respuestas DINÁMICAS (consultan la base) ---
$respuesta = null;
$handoff = null;   // datos para un acceso directo que pre-llena el pedido (1.3)

if ($intencion === "estado_pedido") {
    if ($num_pedido !== null) {
        // Pregunta por UN pedido puntual: respondemos solo por ese (y solo si es suyo).
        $st = $pdo->prepare(
            "SELECT p.id, p.estado, d.nombre AS diseno
             FROM pedidos p LEFT JOIN disenos d ON p.diseno_id = d.id
             WHERE p.id = ? AND p.emprendedor_id = ?"
        );
        $st->execute([$num_pedido, $eid]);
        $p = $st->fetch();
        if (!$p) {
            $respuesta = "No encontré un pedido #{$num_pedido} en tu cuenta. ¿Querés que te liste tus últimos pedidos?";
        } else {
            $estadoLindo = $ESTADOS_PEDIDO[$p["estado"]] ?? $p["estado"];
            // Recordamos este pedido para follow-ups ("¿y cuándo estará?").
            $_SESSION['cb_last_pedido'] = (int)$p['id'];
            // Pista de la PRÓXIMA etapa según el flujo de producción.
            $flujo = ['registrado' => 'En calentamiento', 'en_calentamiento' => 'Moldeado', 'moldeado' => 'Finalizado'];
            $siguiente = $flujo[$p["estado"]] ?? null;
            $extra = $siguiente ? " La próxima etapa es <strong>{$siguiente}</strong>."
                   : ($p["estado"] === 'finalizado' ? " ¡Ya está listo para retirar! 📦" : "");
            $respuesta = "El pedido #{$p['id']} (" . htmlspecialchars($p["diseno"] ?? "diseño")
                       . ") está en <strong>{$estadoLindo}</strong>.{$extra} Podés ver la trazabilidad completa en «Seguimiento».";
        }
    } else {
        $st = $pdo->prepare(
            "SELECT p.id, p.estado, d.nombre AS diseno
             FROM pedidos p LEFT JOIN disenos d ON p.diseno_id = d.id
             WHERE p.emprendedor_id = ? ORDER BY p.id DESC LIMIT 3"
        );
        $st->execute([$eid]);
        $ped = $st->fetchAll();
        if (!$ped) {
            $respuesta = "Todavía no tenés pedidos registrados. Podés crear uno desde «Hacer Pedido».";
        } else {
            $partes = [];
            foreach ($ped as $p) {
                $estadoLindo = $ESTADOS_PEDIDO[$p["estado"]] ?? $p["estado"];
                $partes[] = "#{$p['id']} " . htmlspecialchars($p["diseno"] ?? "diseño") . " → <strong>{$estadoLindo}</strong>";
            }
            $respuesta = "Estos son tus últimos pedidos: " . implode(" · ", $partes)
                       . ". Si querés el detalle de uno, pasame su número (ej: «pedido " . $ped[0]["id"] . "»).";
        }
    }
} elseif ($intencion === "impacto") {
    $st = $pdo->prepare("SELECT COALESCE(SUM(gramos_totales),0)/1000 FROM pedidos WHERE emprendedor_id = ? AND estado = 'finalizado'");
    $st->execute([$eid]);
    $kg = round((float)$st->fetchColumn(), 1);
    $co2 = co2_evitado_kg($pdo, $eid);
    $respuesta = "Hasta ahora reciclaste <strong>{$kg} kg</strong> de plástico y evitaste <strong>{$co2} kg</strong> de CO₂. Mirá el detalle en «Impacto».";
} elseif ($intencion === "diseno_estado") {
    // Resumen del estado de los diseños del emprendedor (datos reales).
    $st = $pdo->prepare("SELECT estado, COUNT(*) AS c FROM disenos WHERE emprendedor_id = ? GROUP BY estado");
    $st->execute([$eid]);
    $conteo = [];
    foreach ($st->fetchAll() as $r) { $conteo[$r["estado"]] = (int)$r["c"]; }

    if (!$conteo) {
        $respuesta = "Todavía no subiste ningún diseño. Podés hacerlo desde «¿Tenés una idea?».";
    } else {
        $aprob = $conteo["aprobado"] ?? 0;
        $rev   = $conteo["en_revision"] ?? 0;
        $rech  = $conteo["rechazado"] ?? 0;
        $partes = [];
        if ($aprob) $partes[] = "<strong>{$aprob}</strong> aprobado" . ($aprob === 1 ? "" : "s");
        if ($rev)   $partes[] = "<strong>{$rev}</strong> en revisión";
        if ($rech)  $partes[] = "<strong>{$rech}</strong> rechazado" . ($rech === 1 ? "" : "s");
        $respuesta = "Tus diseños: " . implode(" · ", $partes) . ".";
        // Si hay rechazos, sumamos el motivo del último (ayuda real al emprendedor).
        if ($rech) {
            $m = $pdo->prepare(
                "SELECT motivo_rechazo FROM disenos
                 WHERE emprendedor_id = ? AND estado = 'rechazado' AND motivo_rechazo IS NOT NULL
                 ORDER BY id DESC LIMIT 1"
            );
            $m->execute([$eid]);
            $motivo = $m->fetchColumn();
            if ($motivo) { $respuesta .= " Último motivo de rechazo: «" . htmlspecialchars($motivo) . "»."; }
        }
        $respuesta .= " Detalle en «Mis Diseños».";
    }
} elseif ($intencion === "resumen_cuenta") {
    // Un vistazo general de la cuenta, todo con datos reales.
    $q = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE emprendedor_id = ?");
    $q->execute([$eid]); $tot = (int)$q->fetchColumn();
    $q = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE emprendedor_id = ? AND estado = 'finalizado'");
    $q->execute([$eid]); $fin = (int)$q->fetchColumn();
    $q = $pdo->prepare("SELECT COUNT(*) FROM disenos WHERE emprendedor_id = ? AND estado = 'aprobado'");
    $q->execute([$eid]); $da = (int)$q->fetchColumn();
    $q = $pdo->prepare("SELECT COALESCE(SUM(gramos_totales),0)/1000 FROM pedidos WHERE emprendedor_id = ? AND estado = 'finalizado'");
    $q->execute([$eid]); $kg = round((float)$q->fetchColumn(), 1);
    $co2 = co2_evitado_kg($pdo, $eid);
    $respuesta = "Resumen de tu cuenta: <strong>{$tot}</strong> pedido" . ($tot === 1 ? "" : "s")
               . " ({$fin} finalizado" . ($fin === 1 ? "" : "s") . "), <strong>{$da}</strong> diseño" . ($da === 1 ? "" : "s")
               . " aprobado" . ($da === 1 ? "" : "s") . ", <strong>{$kg} kg</strong> reciclados y <strong>{$co2} kg</strong> de CO₂ evitado.";
} elseif ($intencion === "estimacion") {
    // RAZONAMIENTO + SLOT-FILLING: combinamos lo que dijo AHORA con lo que
    // quedó pendiente de un mensaje anterior (pieza y/o cantidad).
    $tipo = $tipo_msg ?? ($pend_estim['tipo'] ?? null);
    $cant = $cant_msg ?? ($pend_estim['cant'] ?? null);

    if ($tipo === null) {
        // Guardamos lo que ya tengamos (la cantidad, si vino) y pedimos la pieza.
        $_SESSION['cb_estim'] = ['tipo' => null, 'cant' => $cant];
        $respuesta = "¿De qué pieza querés la estimación: <strong>maceta</strong>, <strong>portavasos</strong> o <strong>llavero</strong>?"
                   . ($cant ? " (para {$cant} unidades)" : " Y decime cuántas unidades (ej: «20 llaveros»).");
    } elseif ($cant === null || $cant < 1) {
        // Tenemos la pieza; falta la cantidad. La recordamos.
        $_SESSION['cb_estim'] = ['tipo' => $tipo, 'cant' => null];
        $respuesta = "¿Cuántas unidades de <strong>" . ($NOMBRE_LINDO[$tipo] ?? $tipo) . "</strong>? Pasame el número y te estimo tapitas, plástico y CO₂.";
    } else {
        unset($_SESSION['cb_estim']);   // estimación completa: limpiamos el pendiente
        // 1.3 — "más implicación": guardamos los datos para ofrecer un acceso
        // directo que PRE-LLENA el formulario de pedido con esta pieza y cantidad.
        $handoff = ['tipo' => $tipo, 'cant' => (int)$cant];
        $g_unit  = $GRAMOS_POR_TIPO[$tipo] ?? 0;
        $g_total = $g_unit * $cant;
        $tapitas = $PESO_TAPITA_G > 0 ? (int)round($g_total / $PESO_TAPITA_G) : 0;
        $kg      = round($g_total / 1000, 2);
        $co2g    = (float)$pdo->query("SELECT AVG(co2_por_gramo) FROM materiales")->fetchColumn();
        $co2     = round($g_total * $co2g, 2);
        $nl      = mb_strtolower($NOMBRE_LINDO[$tipo] ?? $tipo, 'UTF-8');
        // Tiempo de taller estimado (horas por tipo × cantidad).
        $hs      = round(($HORAS_POR_TIPO[$tipo] ?? 0) * $cant, 1);
        $tiempo  = $hs > 0 ? " · <strong>~{$hs} h</strong> de taller" : "";
        $respuesta = "Para <strong>{$cant} {$nl}" . ($cant === 1 ? "" : "s") . "</strong> (estimado): "
                   . "<strong>" . number_format($tapitas) . " tapitas</strong> · "
                   . "<strong>{$kg} kg</strong> de plástico · "
                   . "<strong>~{$co2} kg</strong> de CO₂ evitado{$tiempo}. "
                   . "El CO₂ final depende del material que elijas al pedir.";
    }
}

// --- 3) Respuesta GENERADA (LLM/RAG) para intenciones informativas, si el
//        servicio la produjo; si no, la respuesta ESTÁTICA (FAQ). Las intenciones
//        de datos ya resolvieron $respuesta arriba de forma determinista, así que
//        acá nunca pisamos un dato real con texto generado.
if ($respuesta === null) {
    $respuesta = $analisis["generada"] ?? $analisis["respuesta_estatica"] ?? null;
}
// Último recurso: fallback local.
if ($respuesta === null) {
    $respuesta = "No estoy seguro de haber entendido. Puedo ayudarte con estado de pedidos, estado de tus diseños, un resumen de tu cuenta, subir un diseño, cómo pedir, materiales, punto de retiro, impacto o tu cuenta.";
}

// --- ACCIÓN / ACCIONES: botones que el bot ofrece (1.3 "más implicación") ---
// Pueden ser varias: el acceso directo que PRE-LLENA el pedido (si hubo una
// estimación completa) y/o la navegación propia de la intención. El LLM nunca
// ejecuta nada: estas acciones son deterministas y las arma el PHP.
$acciones = [];
if (is_array($handoff)) {
    $acciones[] = [
        'label' => 'Cargar en un pedido con estos datos',
        'url'   => BASE_URL . '/pages/hacer_pedido.php?tipo=' . rawurlencode($handoff['tipo'])
                 . '&cantidad=' . (int)$handoff['cant'],
    ];
} elseif (isset($ACCIONES[$intencion])) {
    $acciones[] = ['label' => $ACCIONES[$intencion][0], 'url' => BASE_URL . $ACCIONES[$intencion][1]];
}

// Para un pedido puntual ya identificado, ofrecemos además repetirlo.
if ($intencion === 'estado_pedido' && $num_pedido !== null && isset($p) && $p) {
    $acciones[] = [
        'label' => "Repetir el pedido #{$num_pedido}",
        'url'   => BASE_URL . '/pages/hacer_pedido.php?rehacer=' . (int)$num_pedido,
    ];
}

// Compat: 'accion' (singular) = la primera; y la recordamos por si dice "sí".
$accion = $acciones[0] ?? null;
if ($accion) { $_SESSION['cb_oferta'] = $accion; }

// Si el usuario cambió de tema, descartamos cualquier estimación a medias
// (así un número posterior no se interpreta como cantidad de una vieja).
if ($intencion !== 'estimacion') { unset($_SESSION['cb_estim']); }

// --- RAZONAMIENTO 3: recordamos el tema para dar contexto al próximo mensaje ---
// (p. ej.: respondemos "estado de pedidos" y después el usuario tipea solo "12").
$_SESSION['cb_ctx'] = $intencion;

// --- HISTORIAL de la charla (para que el LLM siga el hilo) ---
// Guardamos texto plano (sin HTML) y lo acotamos a los últimos turnos.
$hist = $_SESSION['cb_historial'] ?? [];
$hist[] = ["rol" => "user", "texto" => $mensaje];
$hist[] = ["rol" => "assistant", "texto" => trim(html_entity_decode(strip_tags($respuesta)))];
$_SESSION['cb_historial'] = array_slice($hist, -6);   // 3 intercambios

$salida = ["respuesta" => $respuesta, "intencion" => $intencion, "confianza" => $confianza];
if (!empty($analisis["fuente"]) && $analisis["fuente"] !== "off") { $salida["fuente"] = $analisis["fuente"]; }
if ($accion)            { $salida["accion"]   = $accion; }   // compat
if (!empty($acciones))  { $salida["acciones"] = $acciones; } // 1.3: lista de accesos directos
echo json_encode($salida);
