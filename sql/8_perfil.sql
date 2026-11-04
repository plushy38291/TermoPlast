-- ============================================================
--  8_perfil.sql  —  Ampliación del perfil del emprendedor (V4.5)
--
--  Suma datos del EMPRENDIMIENTO (marca, rubro, descripción,
--  contacto y localidad) a la tabla `emprendedores`, sin tocar
--  los campos de cuenta ni la seguridad existentes.
--
--  Usa "ADD COLUMN IF NOT EXISTS" (MariaDB 10.0.2+ / XAMPP) para
--  que correr el script dos veces no rompa nada (idempotente).
--  En MySQL "puro" (que no soporta IF NOT EXISTS en ADD COLUMN),
--  quitá los "IF NOT EXISTS" al aplicarlo la primera vez.
--
--  Cómo aplicarlo:  phpMyAdmin → base `termoplast` → Importar
--                   (o)  mysql -u root termoplast < sql/8_perfil.sql
-- ============================================================

ALTER TABLE `emprendedores`
    ADD COLUMN IF NOT EXISTS `marca`       VARCHAR(120) NULL AFTER `email`,
    ADD COLUMN IF NOT EXISTS `rubro`       VARCHAR(80)  NULL AFTER `marca`,
    ADD COLUMN IF NOT EXISTS `descripcion` VARCHAR(500) NULL AFTER `rubro`,
    ADD COLUMN IF NOT EXISTS `telefono`    VARCHAR(40)  NULL AFTER `descripcion`,
    ADD COLUMN IF NOT EXISTS `web`         VARCHAR(150) NULL AFTER `telefono`,
    ADD COLUMN IF NOT EXISTS `localidad`   VARCHAR(120) NULL AFTER `web`;

-- Nada de datos de ejemplo: los emprendedores existentes quedan
-- con estos campos en NULL y los completan desde "Mi perfil".
