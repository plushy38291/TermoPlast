<?php
// ============================================================
//  chatbot_widget.php — Widget flotante del asistente TermoPlast.
//  Se incluye al final de las páginas privadas (footer.php).
//  Requiere una sesión iniciada y las funciones csrf_* (sesion_segura).
// ============================================================
if (!function_exists("csrf_campo")) { return; }
if (!isset($_SESSION["emprendedor_id"])) { return; }   // solo emprendedores logueados

// Nombre del asistente (configurable en chatbot/llm_config.json → "nombre_asistente").
$cb_nombre = 'Asistente TermoPlast';
$cb_cfg = @json_decode(@file_get_contents(__DIR__ . '/../chatbot/llm_config.json'), true);
if (is_array($cb_cfg) && !empty($cb_cfg['nombre_asistente'])) { $cb_nombre = $cb_cfg['nombre_asistente']; }
?>
<button type="button" id="cb-toggle" class="cb-toggle" aria-label="Abrir asistente">💬</button>

<section id="cb-panel" class="cb-panel" aria-live="polite">
    <header class="cb-header">
        <span><?php echo htmlspecialchars($cb_nombre); ?></span>
        <button type="button" id="cb-cerrar" class="cb-cerrar" aria-label="Cerrar">✕</button>
    </header>
    <div id="cb-body" class="cb-body">
        <div class="cb-msg bot">¡Hola! 👋 Preguntame por el estado de tus pedidos, cómo subir un diseño, materiales, punto de retiro o tu impacto.</div>
    </div>
    <form id="cb-form" class="cb-form" autocomplete="off" data-ajax="1" data-endpoint="<?php echo BASE_URL; ?>/api/chatbot_responder.php">
        <input type="hidden" id="cb-csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">
        <input type="text" id="cb-input" class="cb-input" placeholder="Escribí tu consulta…" maxlength="1000" required>
        <button type="submit" class="btn" aria-label="Enviar">→</button>
    </form>
</section>
<script src="<?php echo BASE_URL; ?>/assets/js/chatbot.js"></script>
