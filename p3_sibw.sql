-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: db
-- Tiempo de generación: 27-04-2026 a las 06:27:03
-- Versión del servidor: 8.0.45
-- Versión de PHP: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `p3_sibw`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comentarios`
--

CREATE TABLE `comentarios` (
  `id` int NOT NULL,
  `id_noticia` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `texto` text NOT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `comentarios`
--

INSERT INTO `comentarios` (`id`, `id_noticia`, `nombre`, `email`, `texto`, `fecha`) VALUES
(1, 1, 'Luis G.', 'luis@mail.com', 'Cuidado en el ALBAICÍN.', '2026-04-13 14:00:00'),
(2, 1, 'Elena M.', 'elena@mail.com', 'Totalmente de acuerdo, la iluminación en esas calles es escasa.', '2026-04-13 14:05:00'),
(3, 2, 'Carlos Ruiz', 'cruiz@mail.com', '¿Alguien sabe si el evento de la Plaza Mayor sigue en pie?', '2026-04-13 14:15:30'),
(4, 3, 'Ana Silvia', 'ana.sil@mail.com', 'Excelente reportaje sobre la historia del barrio.', '2026-04-13 15:00:10'),
(5, 4, 'Javier T.', 'javier99@mail.com', 'He visto más patrullas hoy por la zona, parece que están avisados.', '2026-04-13 16:20:00'),
(6, 5, 'Beatriz', 'bea_v@mail.com', 'Me encanta el Albaicín, pero hay que ir con mil ojos.', '2026-04-13 17:45:00'),
(7, 4, 'Pepe', 'correodepepe@gmail.com', 'Me alegro de que hayan arreglado esta calle de MONTELUZ', '2026-04-26 06:47:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `imagenes`
--

CREATE TABLE `imagenes` (
  `id` int NOT NULL,
  `id_noticia` int NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `descripcion_alt` varchar(255) DEFAULT NULL,
  `principal` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `imagenes`
--

INSERT INTO `imagenes` (`id`, `id_noticia`, `ruta_archivo`, `descripcion_alt`, `principal`) VALUES
(1, 1, 'noticia1.png', 'Casa derrumbada', 1),
(2, 1, 'noticia1_1.png', 'Bomberos en la casa derrumbada', 0),
(3, 1, 'noticia1_2.png', 'Casa derrumbada vallada', 0),
(4, 2, 'noticia2.png', 'Socavón en la calle principal', 1),
(5, 2, 'noticia2_1.png', 'Socavón arreglado', 0),
(6, 2, 'noticia2_2.png', 'Socavón arreglado después de 1 mes', 0),
(7, 3, 'noticia3.png', 'Banco Roto en el Parque Central', 1),
(8, 3, 'noticia3_1.png', 'Banco arreglado por el equipo de mantenimiento', 0),
(9, 3, 'noticia3_2.png', 'Banco arreglado tras su mantemiento', 0),
(10, 4, 'noticia4.png', 'Levantamiento de acera por un árbol', 1),
(11, 4, 'noticia4_1.png', 'Acera siendo arreglada', 0),
(12, 4, 'noticia4_2.png', 'Acera arreglada', 0),
(13, 5, 'noticia5.png', 'Contendor quemado en la calle Amapola', 1),
(14, 5, 'noticia5_1.png', 'Contendor quemado siendo retirado', 0),
(15, 5, 'noticia5_2.png', 'Contendor nuevo colocado', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lugar`
--

CREATE TABLE `lugar` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `lugar`
--

INSERT INTO `lugar` (`id`, `nombre`) VALUES
(1, 'FONSECA'),
(2, 'MONTELUZ'),
(3, 'CUEVAS'),
(4, 'CENTRO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `noticia`
--

CREATE TABLE `noticia` (
  `id` int NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  `tipo` varchar(50) DEFAULT NULL,
  `concejalia` varchar(100) DEFAULT NULL,
  `personas` text,
  `lugar_id` int DEFAULT NULL,
  `descripcion` text,
  `gravedad` enum('Leve','Normal','Grave','Crítico') DEFAULT 'Normal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `noticia`
--

INSERT INTO `noticia` (`id`, `titulo`, `fecha`, `tipo`, `concejalia`, `personas`, `lugar_id`, `descripcion`, `gravedad`) VALUES
(1, 'Derrumbe en vivienda antigua', '2026-04-13 08:00:00', 'Urgencia', 'Urbanismo', 'Bomberos', 1, 'Grietas estructurales detectadas.', 'Crítico'),
(2, 'Socavón por lluvia', '2026-04-13 09:00:00', 'Vía Pública', 'Mantenimiento', 'Técnicos', 3, 'Hundimiento en el asfalto.', 'Grave'),
(3, 'Banco roto en Parque Central', '2026-04-19 16:03:31', 'Mobiliario Urbano', 'Mantenimiento / Vía Pública', 'Reportado por ciudadano', 1, 'Se ha encontrado un banco de madera y forja completamente destrozado en la zona central del parque. Las tablas están sueltas y representan un peligro.', 'Normal'),
(4, 'Levantamiento de acera por raíces', '2026-04-20 10:30:00', 'Mantenimiento', 'Medio Ambiente', 'Técnicos de Parques y Jardines', 2, 'Raíces de árbol de gran porte han fracturado y levantado el pavimento, provocando riesgo de tropezones.', 'Grave'),
(5, 'Contenedor de papel quemado por vandalismo', '2026-04-20 16:30:00', 'Vandalismo', 'Medio Ambiente', 'Reportado por ciudadano', 3, 'Contenedor gris con tapa azul para reciclaje de papel y cartón ha sufrido graves daños por fuego en la parte superior. La tapa azul está parcialmente derretida y deformada. Se requiere sustitución urgente.', 'Grave');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `comentarios`
--
ALTER TABLE `comentarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `noticia_id` (`id_noticia`);

--
-- Indices de la tabla `imagenes`
--
ALTER TABLE `imagenes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `noticia_id` (`id_noticia`);

--
-- Indices de la tabla `lugar`
--
ALTER TABLE `lugar`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `noticia`
--
ALTER TABLE `noticia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lugar_id` (`lugar_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `comentarios`
--
ALTER TABLE `comentarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `imagenes`
--
ALTER TABLE `imagenes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `lugar`
--
ALTER TABLE `lugar`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `noticia`
--
ALTER TABLE `noticia`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `comentarios`
--
ALTER TABLE `comentarios`
  ADD CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`id_noticia`) REFERENCES `noticia` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `imagenes`
--
ALTER TABLE `imagenes`
  ADD CONSTRAINT `imagenes_ibfk_1` FOREIGN KEY (`id_noticia`) REFERENCES `noticia` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `noticia`
--
ALTER TABLE `noticia`
  ADD CONSTRAINT `noticia_ibfk_1` FOREIGN KEY (`lugar_id`) REFERENCES `lugar` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
