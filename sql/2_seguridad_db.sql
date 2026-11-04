-- ============================================================
--  seguridad_db.sql  —  Tablas para Seguridad N2/N3
--  Importar en la base `termoplast` (phpMyAdmin → Importar).
-- ============================================================

-- Historial de intentos de login (para bloqueo por fuerza bruta).
CREATE TABLE IF NOT EXISTS `intentos_login` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `identidad` VARCHAR(150) NOT NULL,          -- email o usuario intentado
  `ip` VARCHAR(45) NOT NULL,
  `exito` TINYINT(1) NOT NULL DEFAULT 0,
  `intento_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_identidad` (`identidad`),
  KEY `idx_fecha` (`intento_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Auditoría de eventos de seguridad (login ok/fallido, bloqueos, cambios).
CREATE TABLE IF NOT EXISTS `eventos_seguridad` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tipo` VARCHAR(50) NOT NULL,                -- login_ok | login_fallido | bloqueo | cambio_password | ...
  `detalle` VARCHAR(255) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `usuario` VARCHAR(150) DEFAULT NULL,
  `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
