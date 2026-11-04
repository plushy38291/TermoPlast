-- ============================================================
--  dosfa.sql  —  Activa el soporte de verificación en dos pasos (2FA)
--  Importar en la base `termoplast` (phpMyAdmin → Importar).
--  Agrega una columna a `emprendedores`: 0 = 2FA apagado, 1 = activo.
-- ============================================================
ALTER TABLE `emprendedores`
  ADD COLUMN `dosfa` TINYINT(1) NOT NULL DEFAULT 0;
