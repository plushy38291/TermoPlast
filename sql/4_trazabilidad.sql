-- ============================================================
--  4_trazabilidad.sql
--  Trazabilidad de producción: historial de estados por pedido.
--
--  Cada vez que un pedido cambia de etapa (registrado → en
--  calentamiento → moldeado → finalizado, o cancelado) se guarda
--  una fila ACÁ, con fecha/hora y quién lo cambió. El campo
--  `estado` de la tabla `pedidos` sigue siendo "el estado actual";
--  esta tabla es la HISTORIA completa que lo respalda.
--
--  Importar DESPUÉS de 1_termoplast.sql.
-- ============================================================

CREATE TABLE IF NOT EXISTS `pedidos_historial` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `pedido_id`    INT(11)      NOT NULL,
  `estado`       VARCHAR(30)  NOT NULL,
  `nota`         VARCHAR(255) DEFAULT NULL,
  `cambiado_por` VARCHAR(60)  DEFAULT NULL,  -- usuario admin, o NULL si lo hizo el emprendedor/sistema
  `fecha`        DATETIME     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pedido` (`pedido_id`),
  CONSTRAINT `historial_ibfk_1`
      FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Backfill: para los pedidos que YA existían antes de esta tabla,
-- sembramos una fila con su estado actual, usando la fecha de
-- creación del pedido. Así la línea de tiempo no arranca vacía.
-- (No duplica si ya hubiera historial para ese pedido.)
-- ------------------------------------------------------------
INSERT INTO `pedidos_historial` (`pedido_id`, `estado`, `nota`, `cambiado_por`, `fecha`)
SELECT p.`id`, p.`estado`, 'Estado inicial (importado)', NULL, p.`fecha`
FROM `pedidos` p
WHERE NOT EXISTS (
    SELECT 1 FROM `pedidos_historial` h WHERE h.`pedido_id` = p.`id`
);
