-- ============================================================
--  5_password_reset.sql
--  Soporte para "Olvidé mi contraseña".
--
--  Guardamos SOLO el hash (SHA-256) del token, nunca el token en
--  claro: si alguien viera la base, no podría usar los enlaces.
--  Cada token tiene vencimiento y es de UN SOLO USO.
--
--  Importar DESPUÉS de 1_termoplast.sql.
-- ============================================================

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`             INT(11)     NOT NULL AUTO_INCREMENT,
  `emprendedor_id` INT(11)     NOT NULL,
  `token_hash`     CHAR(64)    NOT NULL,   -- SHA-256 (hex) del token enviado
  `expira`         DATETIME    NOT NULL,
  `usado`          TINYINT(1)  NOT NULL DEFAULT 0,
  `creado_en`      DATETIME    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token_hash`),
  KEY `idx_emp`   (`emprendedor_id`),
  CONSTRAINT `reset_ibfk_1`
      FOREIGN KEY (`emprendedor_id`) REFERENCES `emprendedores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
