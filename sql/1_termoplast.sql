-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-09-2026 a las 04:40:26
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `termoplast`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administradores`
--

CREATE TABLE `administradores` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `administradores`
--

INSERT INTO `administradores` (`id`, `usuario`, `password`) VALUES
(1, 'admin', '$2y$10$.hvc0XV6MtXNeqoCRvvrleG8vmPk1JRMLx3U1gFd3Kz.eZVzqWdIq');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disenos`
--

CREATE TABLE `disenos` (
  `id` int(11) NOT NULL,
  `emprendedor_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL DEFAULT '',
  `archivo_svg` varchar(255) NOT NULL,
  `tipo_objeto` varchar(50) NOT NULL,
  `ancho_mm` decimal(6,2) NOT NULL,
  `largo_mm` decimal(6,2) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'en_revision',
  `motivo_rechazo` varchar(255) DEFAULT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp(),
  `espesor_mm` decimal(6,2) NOT NULL DEFAULT 0.00,
  `observaciones` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `disenos`
--

INSERT INTO `disenos` (`id`, `emprendedor_id`, `nombre`, `archivo_svg`, `tipo_objeto`, `ancho_mm`, `largo_mm`, `estado`, `motivo_rechazo`, `fecha_subida`, `espesor_mm`, `observaciones`) VALUES
(1, 1, 'llavero', 'disenos_svg/diseno_1_1789704529.svg', 'llavero', 12.00, 23.00, 'rechazado', NULL, '2026-09-18 01:08:49', 2.00, 'awdwadad'),
(2, 1, 'llavero', 'disenos_svg/diseno_1_1789704573.svg', 'portavasos', 232.00, 23.00, 'aprobado', NULL, '2026-09-18 01:09:33', 22.00, 'awdawdawd'),
(3, 1, 'llavero', 'disenos_svg/diseno_1_1789707021.svg', 'llavero', 1.30, 22.00, 'rechazado', NULL, '2026-09-18 01:50:21', 3.00, 'awdasd'),
(4, 1, 'llavero', 'disenos_svg/diseno_1_1790130190.svg', 'llavero', 1.40, 1.40, 'aprobado', NULL, '2026-09-22 23:23:10', 1.30, 'asda3wd');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `emprendedores`
--

CREATE TABLE `emprendedores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `emprendedores`
--

INSERT INTO `emprendedores` (`id`, `nombre`, `email`, `password`, `fecha_registro`) VALUES
(1, 'gero', 'gero1@gmail.com', '$2y$10$Ga.C2DsjF0R.joIYaOylLe0ztrA4M0FS1yfl6D1r.HLqbkJ5rSL96', '2026-09-17 18:07:29'),
(3, 'gero', 'gero2@gmail.com', '$2y$10$P/eE3yG9pmij8YLfYKFlXuzYpSvAKCkEZYLbI3DrI.Rvj9eDaY52W', '2026-09-17 18:25:44'),
(4, 'gero', 'gero3@gmail.com', '$2y$10$EqgEpurVnvbpn0Syct.m7uDcwPDAH6m1xyDA5uzftun5egKPrvEmC', '2026-09-18 02:35:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materiales`
--

CREATE TABLE `materiales` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `co2_por_gramo` decimal(6,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `materiales`
--

INSERT INTO `materiales` (`id`, `nombre`, `co2_por_gramo`) VALUES
(1, 'HDPE', 0.0027),
(2, 'PP', 0.0025);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `emprendedor_id` int(11) NOT NULL,
  `diseno_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `gramos_totales` decimal(8,2) NOT NULL DEFAULT 0.00,
  `estado` varchar(30) NOT NULL DEFAULT 'registrado',
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `emprendedor_id`, `diseno_id`, `material_id`, `cantidad`, `gramos_totales`, `estado`, `fecha`) VALUES
(1, 1, 2, 2, 7, 2240.00, 'finalizado', '2026-09-18 01:52:22'),
(2, 1, 2, 2, 1, 320.00, 'en_calentamiento', '2026-09-18 01:53:42'),
(3, 1, 2, 2, 2, 640.00, 'finalizado', '2026-09-18 01:59:33'),
(4, 1, 4, 1, 3, 255.00, 'en_calentamiento', '2026-09-22 23:24:54');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `administradores`
--
ALTER TABLE `administradores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Indices de la tabla `disenos`
--
ALTER TABLE `disenos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `emprendedor_id` (`emprendedor_id`);

--
-- Indices de la tabla `emprendedores`
--
ALTER TABLE `emprendedores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `materiales`
--
ALTER TABLE `materiales`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `emprendedor_id` (`emprendedor_id`),
  ADD KEY `diseno_id` (`diseno_id`),
  ADD KEY `material_id` (`material_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `administradores`
--
ALTER TABLE `administradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `disenos`
--
ALTER TABLE `disenos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `emprendedores`
--
ALTER TABLE `emprendedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `materiales`
--
ALTER TABLE `materiales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `disenos`
--
ALTER TABLE `disenos`
  ADD CONSTRAINT `disenos_ibfk_1` FOREIGN KEY (`emprendedor_id`) REFERENCES `emprendedores` (`id`);

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`emprendedor_id`) REFERENCES `emprendedores` (`id`),
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`diseno_id`) REFERENCES `disenos` (`id`),
  ADD CONSTRAINT `pedidos_ibfk_3` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
