-- ============================================================
--  6_requisitos_minimos.sql
--  Completa los requisitos mínimos del proyecto sobre la base ya
--  existente, SIN romper nada de lo anterior:
--    · Relación N-N  (etiquetas  <->  disenos)
--    · Baja lógica   (columna `activo` en varias tablas)
--    · 3er rol       (columna `rol` en administradores)
--    · Puntos de retiro como TABLA (para el ABM y el mapa)
--    · Código público por pedido (para el flujo QR)
--    · Tabla de historial de diseños (trazabilidad de diseños)
--    · TRIGGERS y STORED PROCEDURES
--
--  Importar DESPUÉS de 1..5. Es idempotente donde se puede
--  (IF NOT EXISTS / DROP IF EXISTS en triggers y procedimientos).
-- ============================================================

-- Aseguramos codificación UTF-8 en la importación: así las tildes y la ñ
-- de las semillas (etiquetas, puntos de retiro) se guardan correctamente,
-- sin importar el charset por defecto del cliente de importación.
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1) BAJA LÓGICA + 3er ROL  (columnas nuevas)
--    `activo` = 1 visible / 0 dado de baja (NO se borra físicamente).
-- ------------------------------------------------------------
ALTER TABLE `administradores`
  ADD COLUMN `rol`    ENUM('administrador','operador') NOT NULL DEFAULT 'administrador',
  ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE `materiales`
  ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE `emprendedores`
  ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1;

-- Código público (corto) por pedido: lo usa el flujo QR para la ficha
-- de seguimiento. Único y autogenerado por un TRIGGER (ver más abajo).
ALTER TABLE `pedidos`
  ADD COLUMN `codigo` CHAR(10) DEFAULT NULL,
  ADD UNIQUE KEY `uq_pedidos_codigo` (`codigo`);

-- ------------------------------------------------------------
-- 2) RELACIÓN N-N:  disenos  <->  etiquetas
--    Un diseño puede tener muchas etiquetas y una etiqueta puede
--    estar en muchos diseños. La tabla intermedia modela el N-N.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `etiquetas` (
  `id`     INT(11)      NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(50)  NOT NULL,
  `activo` TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etiqueta_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `diseno_etiquetas` (
  `diseno_id`   INT(11) NOT NULL,
  `etiqueta_id` INT(11) NOT NULL,
  PRIMARY KEY (`diseno_id`, `etiqueta_id`),          -- clave primaria COMPUESTA
  KEY `idx_de_etiqueta` (`etiqueta_id`),
  CONSTRAINT `de_ibfk_diseno`
      FOREIGN KEY (`diseno_id`)   REFERENCES `disenos` (`id`)   ON DELETE CASCADE,
  CONSTRAINT `de_ibfk_etiqueta`
      FOREIGN KEY (`etiqueta_id`) REFERENCES `etiquetas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 3) PUNTOS DE RETIRO como tabla (antes era un arreglo en PHP).
--    Lleva lat/lng para dibujarlos en el mapa (Leaflet).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `puntos_retiro` (
  `id`        INT(11)       NOT NULL AUTO_INCREMENT,
  `nombre`    VARCHAR(120)  NOT NULL,
  `direccion` VARCHAR(200)  NOT NULL DEFAULT '',
  `lat`       DECIMAL(10,7) NOT NULL,
  `lng`       DECIMAL(10,7) NOT NULL,
  `activo`    TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 4) HISTORIAL DE DISEÑOS (segunda tabla de historial, mantenida
--    AUTOMÁTICAMENTE por un TRIGGER cuando cambia el estado).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `disenos_historial` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `diseno_id` INT(11)      NOT NULL,
  `estado`    VARCHAR(30)  NOT NULL,
  `motivo`    VARCHAR(255) DEFAULT NULL,
  `fecha`     DATETIME     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dh_diseno` (`diseno_id`),
  CONSTRAINT `dh_ibfk_diseno`
      FOREIGN KEY (`diseno_id`) REFERENCES `disenos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 5) TRIGGERS
-- ============================================================

-- 5.a) BEFORE INSERT en pedidos: autogenera el `codigo` público si no
--      vino (10 hex de un UUID). Así el QR de cada pedido es único y no
--      hay que tocar el PHP que inserta el pedido.
DROP TRIGGER IF EXISTS `trg_pedidos_bi_codigo`;
DELIMITER $$
CREATE TRIGGER `trg_pedidos_bi_codigo`
BEFORE INSERT ON `pedidos`
FOR EACH ROW
BEGIN
  IF NEW.`codigo` IS NULL OR NEW.`codigo` = '' THEN
    SET NEW.`codigo` = SUBSTRING(REPLACE(UUID(), '-', ''), 1, 10);
  END IF;
END$$
DELIMITER ;

-- 5.b) AFTER UPDATE en disenos: si cambió el estado, deja una fila en
--      disenos_historial. La trazabilidad del diseño queda garantizada
--      por la BASE, sin depender de que el PHP se acuerde de registrarla.
DROP TRIGGER IF EXISTS `trg_disenos_au_historial`;
DELIMITER $$
CREATE TRIGGER `trg_disenos_au_historial`
AFTER UPDATE ON `disenos`
FOR EACH ROW
BEGIN
  IF NEW.`estado` <> OLD.`estado` THEN
    INSERT INTO `disenos_historial` (`diseno_id`, `estado`, `motivo`)
    VALUES (NEW.`id`, NEW.`estado`, NEW.`motivo_rechazo`);
  END IF;
END$$
DELIMITER ;

-- ============================================================
-- 6) STORED PROCEDURES  (los usa el Dashboard)
-- ============================================================

-- 6.a) Ranking de emprendedores por kg reciclado (pedidos finalizados).
DROP PROCEDURE IF EXISTS `sp_top_emprendedores`;
DELIMITER $$
CREATE PROCEDURE `sp_top_emprendedores`(IN p_limite INT)
BEGIN
  IF p_limite IS NULL OR p_limite < 1 THEN SET p_limite = 5; END IF;
  SELECT e.`nombre` AS emprendedor,
         ROUND(COALESCE(SUM(p.`gramos_totales`), 0) / 1000, 2) AS kg
  FROM `emprendedores` e
  LEFT JOIN `pedidos` p
         ON p.`emprendedor_id` = e.`id` AND p.`estado` = 'finalizado'
  GROUP BY e.`id`, e.`nombre`
  HAVING kg > 0
  ORDER BY kg DESC
  LIMIT p_limite;
END$$
DELIMITER ;

-- 6.b) Pedidos por mes (últimos 12 meses) para la curva de evolución.
DROP PROCEDURE IF EXISTS `sp_pedidos_por_mes`;
DELIMITER $$
CREATE PROCEDURE `sp_pedidos_por_mes`()
BEGIN
  SELECT DATE_FORMAT(`fecha`, '%Y-%m') AS mes,
         COUNT(*)                      AS cantidad
  FROM `pedidos`
  WHERE `fecha` >= (CURRENT_DATE - INTERVAL 12 MONTH)
  GROUP BY mes
  ORDER BY mes ASC;
END$$
DELIMITER ;

-- ============================================================
-- 7) DATOS SEMILLA
-- ============================================================

-- Operador de ejemplo (rol 'operador'); clave: operador123
INSERT INTO `administradores` (`usuario`, `password`, `rol`, `activo`) VALUES
('operador', '$2y$12$tqSacBEZvg1lhuFL/m35D.rwY4DDgEcQapb4DzlS88b8cOZsor4/q', 'operador', 1);

-- Etiquetas (categorías de diseño) — alimentan el N-N y el dashboard.
INSERT INTO `etiquetas` (`nombre`) VALUES
('Decoración'), ('Jardín'), ('Oficina'), ('Cocina'), ('Souvenir');

-- Vinculamos los diseños existentes con etiquetas (ejemplos del N-N).
INSERT IGNORE INTO `diseno_etiquetas` (`diseno_id`, `etiqueta_id`) VALUES
(1, 5),            -- llavero -> Souvenir
(2, 3), (2, 1),    -- portavasos -> Oficina + Decoración
(3, 5),            -- llavero -> Souvenir
(4, 2), (4, 1);    -- -> Jardín + Decoración

-- Puntos de retiro. El primero es el real (ajustá lat/lng exactas de tu
-- escuela si hace falta); los otros quedan inactivos como "próximamente",
-- para que el filtro de activos/inactivos tenga datos.
INSERT INTO `puntos_retiro` (`nombre`, `direccion`, `lat`, `lng`, `activo`) VALUES
('E.E.S.T. N°3 "Roberto Arlt"', 'Isidro Casanova, La Matanza, Buenos Aires', -34.7052000, -58.5880000, 1),
('Punto Verde CABA (próximamente)', 'Ciudad Autónoma de Buenos Aires', -34.6037000, -58.3816000, 0),
('Punto Verde La Plata (próximamente)', 'La Plata, Buenos Aires', -34.9214000, -57.9544000, 0);

-- Sembramos el código público de los pedidos que YA existían (el trigger
-- solo actúa sobre inserciones nuevas).
UPDATE `pedidos`
SET `codigo` = SUBSTRING(REPLACE(UUID(), '-', ''), 1, 10)
WHERE `codigo` IS NULL OR `codigo` = '';

-- Sembramos el historial de diseños con el estado actual de cada uno
-- (para los diseños previos a esta migración).
INSERT INTO `disenos_historial` (`diseno_id`, `estado`, `motivo`, `fecha`)
SELECT d.`id`, d.`estado`, d.`motivo_rechazo`, d.`fecha_subida`
FROM `disenos` d
WHERE NOT EXISTS (
    SELECT 1 FROM `disenos_historial` h WHERE h.`diseno_id` = d.`id`
);
