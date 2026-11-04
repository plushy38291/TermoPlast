<!-- footer.php -->
<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-col brand-col">
            <div class="topbar-brand">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span>TermoPlast</span>
            </div>
            <p>Tapitas plásticas (HDPE y PP) recicladas, transformadas en piezas de valor para emprendedores.</p>
        </div>

        <div class="footer-col">
            <h4>Plataforma</h4>
            <ul>
                <li><a href="<?php echo BASE_URL; ?>/pages/panel.php">Inicio</a></li>
                <li><a href="<?php echo BASE_URL; ?>/pages/hacer_pedido.php">Hacer Pedido</a></li>
                <li><a href="<?php echo BASE_URL; ?>/pages/seguimiento.php">Seguimiento</a></li>
                <li><a href="<?php echo BASE_URL; ?>/pages/subir_diseno.php">¿Tenés una idea?</a></li>
                <li><a href="<?php echo BASE_URL; ?>/pages/impacto.php">Impacto</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Punto de retiro</h4>
            <ul>
                <li><strong><?php echo htmlspecialchars($PUNTOS_RETIRO[0] ?? 'Escuela Técnica N° 3 Roberto Arlt'); ?></strong></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Materiales</h4>
            <p>HDPE y PP reciclados post-consumo</p>
            <p style="margin-top: 10px;"><strong>Formato admitido:</strong> SVG</p>
        </div>
    </div>

    <div class="footer-bottom">
        <p>© 2026 TermoPlast — Tesis de 7° año de educación técnica secundaria.</p>
    </div>
</footer>

<?php if (isset($_SESSION["emprendedor_id"])) include __DIR__ . '/chatbot_widget.php'; ?>
