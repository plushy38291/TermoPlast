-- ============================================================
--  7_datos_demo.sql  ·  TermoPlast — DATOS DE DEMOSTRACIÓN
--  OPCIONAL. Carga diseños y pedidos de varios meses para que
--  los gráficos (dashboard e impacto) muestren tendencias ricas
--  en la defensa. Se puede volver a ejecutar sin duplicar:
--  borra primero sus propios datos (diseños con archivo demo.svg).
--  Requiere que existan los emprendedores 1, 3 y 4 y materiales 1 y 2.
-- ============================================================
SET NAMES utf8mb4;
START TRANSACTION;

-- Limpieza idempotente de datos demo previos.
DELETE p FROM pedidos p JOIN disenos d ON p.diseno_id = d.id WHERE d.archivo_svg = 'disenos_svg/demo.svg';
DELETE FROM disenos WHERE archivo_svg = 'disenos_svg/demo.svg';

-- Nombres distintos para que el ranking de emprendedores se lea bien.
-- (El login sigue siendo por email; el nombre es solo de presentación.)
UPDATE emprendedores SET nombre = 'Gero Pérez'    WHERE id = 1;
UPDATE emprendedores SET nombre = 'Luciana Gómez' WHERE id = 3;
UPDATE emprendedores SET nombre = 'Martín Díaz'   WHERE id = 4;

-- Diseños aprobados de demostración.
INSERT INTO disenos (emprendedor_id, nombre, archivo_svg, tipo_objeto, ancho_mm, largo_mm, espesor_mm, estado, fecha_subida)
  VALUES (1, 'Llavero logo', 'disenos_svg/demo.svg', 'llavero', 40.00, 20.00, 2, 'aprobado', DATE_SUB(NOW(), INTERVAL 9 MONTH));
SET @d0 = LAST_INSERT_ID();
INSERT INTO disenos (emprendedor_id, nombre, archivo_svg, tipo_objeto, ancho_mm, largo_mm, espesor_mm, estado, fecha_subida)
  VALUES (1, 'Portavasos hexagonal', 'disenos_svg/demo.svg', 'portavasos', 95.00, 95.00, 2, 'aprobado', DATE_SUB(NOW(), INTERVAL 9 MONTH));
SET @d1 = LAST_INSERT_ID();
INSERT INTO disenos (emprendedor_id, nombre, archivo_svg, tipo_objeto, ancho_mm, largo_mm, espesor_mm, estado, fecha_subida)
  VALUES (1, 'Maceta chica', 'disenos_svg/demo.svg', 'maceta', 120.00, 120.00, 3, 'aprobado', DATE_SUB(NOW(), INTERVAL 9 MONTH));
SET @d2 = LAST_INSERT_ID();
INSERT INTO disenos (emprendedor_id, nombre, archivo_svg, tipo_objeto, ancho_mm, largo_mm, espesor_mm, estado, fecha_subida)
  VALUES (3, 'Llavero redondo', 'disenos_svg/demo.svg', 'llavero', 40.00, 20.00, 2, 'aprobado', DATE_SUB(NOW(), INTERVAL 9 MONTH));
SET @d3 = LAST_INSERT_ID();
INSERT INTO disenos (emprendedor_id, nombre, archivo_svg, tipo_objeto, ancho_mm, largo_mm, espesor_mm, estado, fecha_subida)
  VALUES (3, 'Portavasos liso', 'disenos_svg/demo.svg', 'portavasos', 95.00, 95.00, 2, 'aprobado', DATE_SUB(NOW(), INTERVAL 9 MONTH));
SET @d4 = LAST_INSERT_ID();
INSERT INTO disenos (emprendedor_id, nombre, archivo_svg, tipo_objeto, ancho_mm, largo_mm, espesor_mm, estado, fecha_subida)
  VALUES (4, 'Maceta colgante', 'disenos_svg/demo.svg', 'maceta', 120.00, 120.00, 3, 'aprobado', DATE_SUB(NOW(), INTERVAL 9 MONTH));
SET @d5 = LAST_INSERT_ID();
INSERT INTO disenos (emprendedor_id, nombre, archivo_svg, tipo_objeto, ancho_mm, largo_mm, espesor_mm, estado, fecha_subida)
  VALUES (4, 'Llavero corazón', 'disenos_svg/demo.svg', 'llavero', 40.00, 20.00, 2, 'aprobado', DATE_SUB(NOW(), INTERVAL 9 MONTH));
SET @d6 = LAST_INSERT_ID();

-- Pedidos repartidos en los últimos 8 meses (fechas relativas = siempre vigentes).
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d0, 1, 5, 75.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 8 MONTH) + INTERVAL 5 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d1, 2, 2, 170.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 8 MONTH) + INTERVAL 11 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (3, @d3, 1, 6, 90.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 8 MONTH) + INTERVAL 17 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d2, 1, 1, 320.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 7 MONTH) + INTERVAL 5 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (3, @d4, 2, 3, 255.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 7 MONTH) + INTERVAL 11 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d0, 1, 8, 120.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 7 MONTH) + INTERVAL 17 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (4, @d5, 1, 1, 320.00, 'cancelado', DATE_SUB(NOW(), INTERVAL 7 MONTH) + INTERVAL 23 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d1, 2, 4, 340.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 6 MONTH) + INTERVAL 5 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (3, @d3, 1, 5, 75.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 6 MONTH) + INTERVAL 11 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (4, @d6, 1, 7, 105.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 6 MONTH) + INTERVAL 17 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d0, 1, 6, 90.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 5 MONTH) + INTERVAL 5 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d2, 1, 2, 640.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 5 MONTH) + INTERVAL 11 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (3, @d4, 2, 4, 340.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 5 MONTH) + INTERVAL 17 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d1, 2, 3, 255.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 5 MONTH) + INTERVAL 23 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (4, @d5, 1, 2, 640.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 4 MONTH) + INTERVAL 5 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (3, @d3, 1, 8, 120.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 4 MONTH) + INTERVAL 11 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (4, @d6, 1, 5, 75.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 4 MONTH) + INTERVAL 17 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d0, 1, 7, 105.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 3 MONTH) + INTERVAL 5 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d1, 2, 5, 425.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 3 MONTH) + INTERVAL 11 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d2, 1, 1, 320.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 3 MONTH) + INTERVAL 17 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (3, @d4, 2, 6, 510.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 3 MONTH) + INTERVAL 23 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (3, @d3, 1, 4, 60.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 2 MONTH) + INTERVAL 5 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (4, @d5, 1, 1, 320.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 2 MONTH) + INTERVAL 11 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d0, 1, 9, 135.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 2 MONTH) + INTERVAL 17 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (4, @d6, 1, 3, 45.00, 'cancelado', DATE_SUB(NOW(), INTERVAL 2 MONTH) + INTERVAL 23 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d1, 2, 6, 510.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 1 MONTH) + INTERVAL 5 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d2, 1, 2, 640.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 1 MONTH) + INTERVAL 11 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (3, @d4, 2, 5, 425.00, 'finalizado', DATE_SUB(NOW(), INTERVAL 1 MONTH) + INTERVAL 17 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d0, 1, 4, 60.00, 'moldeado', DATE_SUB(NOW(), INTERVAL 1 MONTH) + INTERVAL 23 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (3, @d3, 1, 3, 45.00, 'en_calentamiento', DATE_SUB(NOW(), INTERVAL 0 MONTH) + INTERVAL 5 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d0, 1, 5, 75.00, 'registrado', DATE_SUB(NOW(), INTERVAL 0 MONTH) + INTERVAL 11 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (4, @d6, 1, 4, 60.00, 'en_calentamiento', DATE_SUB(NOW(), INTERVAL 0 MONTH) + INTERVAL 17 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (1, @d1, 2, 2, 170.00, 'moldeado', DATE_SUB(NOW(), INTERVAL 0 MONTH) + INTERVAL 23 DAY);
INSERT INTO pedidos (emprendedor_id, diseno_id, material_id, cantidad, gramos_totales, estado, fecha)
  VALUES (4, @d5, 1, 1, 320.00, 'registrado', DATE_SUB(NOW(), INTERVAL 0 MONTH) + INTERVAL 29 DAY);

COMMIT;
-- Fin de datos demo.
