-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 26-06-2025 a las 17:31:44
-- Versión del servidor: 10.11.10-MariaDB
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u618126694_reminiscencia`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`u618126694_admin`@`127.0.0.1` PROCEDURE `aprobar_cliente_prospecto` (IN `p_prospecto_id` INT)   BEGIN
    DECLARE v_nombre VARCHAR(100);
    DECLARE v_email VARCHAR(100);
    DECLARE v_telefono VARCHAR(20);
    DECLARE v_password VARCHAR(255);
    DECLARE v_foto_path VARCHAR(255);

    -- Obtener los datos del prospecto
    SELECT nombre, email, telefono, password, foto_path
    INTO v_nombre, v_email, v_telefono, v_password, v_foto_path
    FROM prospectos_clientes
    WHERE id = p_prospecto_id AND estado = 'pendiente';

    -- Verificar si el prospecto existe y está pendiente
    IF v_nombre IS NOT NULL THEN
        -- Insertar en la tabla de usuarios
        INSERT INTO usuarios (nombre, email, telefono, password, rol, activo, foto_id)
        VALUES (v_nombre, v_email, v_telefono, v_password, 'cliente', 1, v_foto_path);

        -- Actualizar el estado del prospecto
        UPDATE prospectos_clientes
        SET estado = 'aprobado'
        WHERE id = p_prospecto_id;
    END IF;
END$$

CREATE DEFINER=`u618126694_admin`@`127.0.0.1` PROCEDURE `aprobar_colaborador_prospecto` (IN `p_prospecto_id` INT)   BEGIN
    DECLARE v_nombre VARCHAR(100);
    DECLARE v_email VARCHAR(100);
    DECLARE v_telefono VARCHAR(20);
    DECLARE v_password VARCHAR(255);
    DECLARE v_tipo_colaborador ENUM('fotografo','videografo','auxiliar','');
    DECLARE v_rango_colaborador ENUM('I','II','III','');
    DECLARE v_foto_path VARCHAR(255);

    -- Obtener los datos del prospecto
    SELECT nombre, email, telefono, password, tipo_colaborador, rango_colaborador, foto_path
    INTO v_nombre, v_email, v_telefono, v_password, v_tipo_colaborador, v_rango_colaborador, v_foto_path
    FROM prospectos_colaboradores
    WHERE id = p_prospecto_id AND estado = 'pendiente';

    -- Verificar si el prospecto existe y está pendiente
    IF v_nombre IS NOT NULL THEN
        -- Insertar en la tabla de usuarios
        INSERT INTO usuarios (nombre, email, telefono, password, rol, tipo_colaborador, rango_colaborador, activo, foto_id)
        VALUES (v_nombre, v_email, v_telefono, v_password, 'colaborador', v_tipo_colaborador, v_rango_colaborador, 1, v_foto_path);

        -- Actualizar el estado del prospecto
        UPDATE prospectos_colaboradores
        SET estado = 'aprobado'
        WHERE id = p_prospecto_id;
    END IF;
END$$

CREATE DEFINER=`u618126694_admin`@`127.0.0.1` PROCEDURE `rechazar_cliente_prospecto` (IN `p_prospecto_id` INT)   BEGIN
    UPDATE prospectos_clientes
    SET estado = 'rechazado'
    WHERE id = p_prospecto_id AND estado = 'pendiente';
END$$

CREATE DEFINER=`u618126694_admin`@`127.0.0.1` PROCEDURE `rechazar_colaborador_prospecto` (IN `p_prospecto_id` INT)   BEGIN
    UPDATE prospectos_colaboradores
    SET estado = 'rechazado'
    WHERE id = p_prospecto_id AND estado = 'pendiente';
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `eventos`
--

CREATE TABLE `eventos` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `colaborador_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `fecha_evento` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time DEFAULT NULL,
  `lugar` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `personas_estimadas` int(11) DEFAULT NULL,
  `estado` enum('pendiente','confirmado','cancelado','completado') DEFAULT 'pendiente',
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `eventos`
--

INSERT INTO `eventos` (`id`, `titulo`, `cliente_id`, `colaborador_id`, `nombre`, `tipo`, `fecha_evento`, `hora_inicio`, `hora_fin`, `lugar`, `descripcion`, `personas_estimadas`, `estado`, `fecha_creacion`, `fecha_modificacion`) VALUES
(1, '', 3, 4, 'Bautizo de Karen', 'otro', '2026-02-07', '10:00:00', '18:35:00', 'Av. Siglo XXI 5031, El Riego, 20367 Aguascalientes, Ags.', 'Bautizo de Karen foto intima y video tierno', 100, 'confirmado', '2025-05-26 16:36:35', '2025-05-29 23:28:32'),
(2, '', 3, 2, 'XV Lucía', 'xv', '2026-07-30', '10:00:00', '01:37:00', 'Av. Siglo XXI 5031, El Riego, 20367 Aguascalientes, Ags.', 'XV de Lucía, fotos modernas y video con muchos cortes, capturar vals.', 600, 'completado', '2025-05-28 16:37:55', '2025-05-29 23:30:42'),
(3, '', 6, NULL, 'ITICS 2026', 'graduaciones', '2026-03-28', '18:00:00', '02:00:00', 'Vista Hermosa', 'Graduación Ing en Tics del Instituto Tecnológico de Ags', 500, 'pendiente', '2025-05-30 05:05:38', '2025-06-09 01:48:32'),
(5, '', 9, NULL, 'LAE 2025', 'graduaciones', '2025-08-20', '10:00:00', '02:42:00', 'Av. Siglo XXI 5031, El Riego, 20367 Aguascalientes, Ags.', 'Graduación Administración', 500, 'pendiente', '2025-06-08 17:42:43', '2025-06-08 17:46:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evento_colaborador`
--

CREATE TABLE `evento_colaborador` (
  `id` int(11) NOT NULL,
  `evento_id` int(11) NOT NULL,
  `colaborador_id` int(11) NOT NULL,
  `rol` enum('fotografo','videografo','auxiliar') NOT NULL,
  `disponibilidad` varchar(20) DEFAULT 'pendiente',
  `estado` enum('pendiente','activo','rechazado') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `evento_colaborador`
--

INSERT INTO `evento_colaborador` (`id`, `evento_id`, `colaborador_id`, `rol`, `disponibilidad`, `estado`) VALUES
(1, 1, 2, 'fotografo', 'confirmada', ''),
(2, 2, 4, 'fotografo', 'pendiente', ''),
(3, 2, 2, 'fotografo', 'pendiente', ''),
(4, 3, 7, 'fotografo', 'pendiente', ''),
(5, 3, 7, 'fotografo', 'pendiente', ''),
(6, 5, 10, 'fotografo', 'pendiente', ''),
(7, 3, 10, 'fotografo', 'pendiente', ''),
(8, 3, 2, 'fotografo', 'pendiente', ''),
(9, 5, 2, 'fotografo', 'pendiente', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evento_colaboradores`
--

CREATE TABLE `evento_colaboradores` (
  `evento_id` int(11) NOT NULL,
  `colaborador_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `evento_colaboradores`
--

INSERT INTO `evento_colaboradores` (`evento_id`, `colaborador_id`) VALUES
(1, 2),
(2, 2),
(2, 4),
(3, 2),
(3, 5),
(5, 10);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `llamadas`
--

CREATE TABLE `llamadas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `comentarios` text DEFAULT NULL,
  `estado` enum('pendiente','atendida','cancelada') DEFAULT 'pendiente',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `token_verificacion` varchar(64) DEFAULT NULL,
  `verificado` tinyint(1) DEFAULT 0,
  `ip_origen` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `documento_identidad` varchar(20) DEFAULT NULL COMMENT 'Número de documento de identidad del usuario',
  `tipo_documento` varchar(50) DEFAULT NULL COMMENT 'Tipo de documento: cedula, cedula_extranjeria, pasaporte, tarjeta_identidad',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Dirección IP desde donde se realizó la solicitud',
  `identidad_verificada` tinyint(1) DEFAULT 0 COMMENT 'Indica si la identidad del usuario ha sido verificada',
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora de creación del registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `llamadas`
--

INSERT INTO `llamadas` (`id`, `nombre`, `email`, `telefono`, `fecha`, `hora`, `comentarios`, `estado`, `fecha_registro`, `token_verificacion`, `verificado`, `ip_origen`, `user_agent`, `documento_identidad`, `tipo_documento`, `ip_address`, `identidad_verificada`, `fecha_creacion`) VALUES
(2, 'Antonio Cruz', 'CrossAntony@outlook.com', '4495675444', '2025-06-20', '10:20:00', 'Informes sobre paquetes boda', 'atendida', '2025-05-28 01:07:49', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, '2025-06-11 23:24:28'),
(3, 'Victor Guerrero', 'viktor2002_23@gmail.com', '4495001232', '2025-06-04', '10:30:00', 'Buen día, requiero información sobre video para una graduación', 'cancelada', '2025-05-30 04:51:28', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, '2025-06-11 23:24:28'),
(4, 'Carlos Fernandez', 'FerCar@gmail.com', '4491233443', '2025-06-14', '15:09:00', 'Requiero mas informacion sobre bodas', 'pendiente', '2025-06-10 20:06:28', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, '2025-06-11 23:24:28'),
(5, 'Fabián Suarez', 'FabSuarez223@outlook.com', '4499078104', '2025-06-27', '15:00:00', 'Mas info sobre sesion de foto', 'pendiente', '2025-06-11 06:10:41', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, '2025-06-11 23:24:28'),
(6, 'Dioni Galicia', 'wikigalicia750@gmail.com', '4499078204', '2025-06-14', '10:00:00', 'Sesion de fotos', 'pendiente', '2025-06-11 23:29:30', NULL, 0, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '0559126625036', 'tarjeta_identidad', '2806:103e:16:a93:5417:88ba:77db:65a9', 0, '2025-06-11 23:29:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `mensaje` text NOT NULL,
  `leido` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO `notificaciones` (`id`, `usuario_id`, `tipo`, `mensaje`, `leido`, `fecha_creacion`) VALUES
(1, 1, 'nuevo_evento', 'Nuevo evento creado por el cliente', 0, '2025-05-26 16:36:35'),
(2, 1, 'nuevo_evento', 'Nuevo evento creado por el cliente', 0, '2025-05-28 16:37:55'),
(3, 1, 'nuevo_evento', 'Nuevo evento creado por el cliente', 0, '2025-05-30 05:05:38'),
(4, 1, 'nuevo_evento', 'Nuevo evento creado por el cliente', 0, '2025-06-06 18:26:16'),
(5, 1, 'nuevo_evento', 'Nuevo evento creado por el cliente', 0, '2025-06-08 17:42:43'),
(6, 1, 'nuevo_evento', 'Nuevo evento creado por el cliente', 0, '2025-06-09 02:05:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prospectos_clientes`
--

CREATE TABLE `prospectos_clientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `foto_path` varchar(255) DEFAULT NULL COMMENT 'Ruta de la foto de identificación del cliente',
  `estado` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente' COMMENT 'Estado de la solicitud del cliente',
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `prospectos_clientes`
--

INSERT INTO `prospectos_clientes` (`id`, `nombre`, `email`, `telefono`, `password`, `foto_path`, `estado`, `fecha_registro`) VALUES
(1, 'Dionicio Alejandro Galicia Quiroz', 'wikigalicia75@gmail.com', '4498078204', '$2y$10$dZeK8X1M3L1uAJ8JAXtHWeawB6wQoJtpmZ2NBeu9zrnJI5/WmY1GG', 'uploads/fotos_id/foto_id_cliente_6851f434c7e9c.jpg', 'aprobado', '2025-06-17 23:03:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prospectos_colaboradores`
--

CREATE TABLE `prospectos_colaboradores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `tipo_colaborador` enum('fotografo','videografo','auxiliar','') DEFAULT NULL,
  `rango_colaborador` enum('I','II','III','') DEFAULT NULL,
  `foto_path` varchar(255) DEFAULT NULL COMMENT 'Ruta de la foto de identificación del colaborador',
  `estado` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente' COMMENT 'Estado de la solicitud del colaborador',
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `prospectos_colaboradores`
--

INSERT INTO `prospectos_colaboradores` (`id`, `nombre`, `email`, `telefono`, `password`, `tipo_colaborador`, `rango_colaborador`, `foto_path`, `estado`, `fecha_registro`) VALUES
(1, 'Zuriel Dávila', 'DavZur2002@gmail.com', '4491232323', '$2y$10$8SxHwAAFJmSgcb5iLmI4Wu/4Bqbm9tH0.h3EbvchW4KVrlEfUCHDK', 'fotografo', 'II', 'uploads/fotos_id/foto_id_colaborador_6851f500b30bb.jpg', 'aprobado', '2025-06-17 23:06:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_colaborador`
--

CREATE TABLE `solicitudes_colaborador` (
  `id` int(11) NOT NULL,
  `evento_id` int(11) NOT NULL,
  `colaborador_id` int(11) NOT NULL,
  `estado` enum('pendiente','aceptada','rechazada') DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `solicitudes_colaborador`
--

INSERT INTO `solicitudes_colaborador` (`id`, `evento_id`, `colaborador_id`, `estado`, `fecha_solicitud`) VALUES
(1, 1, 2, 'aceptada', '2025-05-29 06:06:44'),
(2, 1, 2, 'aceptada', '2025-05-29 06:12:59'),
(3, 2, 4, 'aceptada', '2025-05-29 07:22:37'),
(4, 2, 2, 'aceptada', '2025-05-29 23:31:11'),
(5, 3, 7, 'rechazada', '2025-05-30 05:42:16'),
(6, 3, 7, 'rechazada', '2025-05-30 05:42:19'),
(7, 3, 7, 'aceptada', '2025-05-30 05:42:22'),
(8, 3, 7, 'aceptada', '2025-05-30 05:42:28'),
(9, 3, 10, 'aceptada', '2025-06-08 17:45:02'),
(10, 5, 10, 'aceptada', '2025-06-08 17:45:05'),
(11, 3, 2, 'aceptada', '2025-06-09 02:07:13'),
(12, 5, 2, 'aceptada', '2025-06-10 20:27:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tokens_verificacion`
--

CREATE TABLE `tokens_verificacion` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `datos_llamada` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`datos_llamada`)),
  `verificado` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_expiracion` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tokens_verificacion`
--

INSERT INTO `tokens_verificacion` (`id`, `email`, `token`, `datos_llamada`, `verificado`, `fecha_creacion`, `fecha_expiracion`) VALUES
(1, 'wikigalicia750@gmail.com', '5f0bd5b39cfcfdbf3a4b10b255bf35b702ad0d5f57e31f1200e684258a4b7ca2', '{\"nombre\":\"Dionicio Galicia\",\"email\":\"wikigalicia750@gmail.com\",\"telefono\":\"4499078204\",\"fecha\":\"2025-06-13\",\"hora\":\"09:00\",\"comentarios\":\"Hola, quisiera mas informaci\\u00f3n sobre una sesion fotogr\\u00e1fica\",\"ip_origen\":\"2806:103e:16:a93:5417:88ba:77db:65a9\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\"}', 0, '2025-06-11 21:40:23', '2025-06-11 22:10:23'),
(2, 'wikigalicia750@gmail.com', '9a6e22fe48ee5f7e32bad6e788fabd1f6fcd8c81d966aebccf33989251b69b55', '{\"nombre\":\"Dionicio Galicia\",\"email\":\"wikigalicia750@gmail.com\",\"telefono\":\"4499078204\",\"fecha\":\"2025-06-13\",\"hora\":\"09:00\",\"comentarios\":\"Hola, quisiera mas informaci\\u00f3n sobre una sesion fotogr\\u00e1fica\",\"ip_origen\":\"2806:103e:16:a93:5417:88ba:77db:65a9\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/137.0.0.0 Safari\\/537.36\"}', 0, '2025-06-11 22:52:18', '2025-06-11 23:22:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','colaborador','cliente') NOT NULL,
  `tipo_colaborador` enum('fotografo','videografo','auxiliar','') DEFAULT NULL,
  `rango_colaborador` enum('I','II','III','') DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `activo` tinyint(1) DEFAULT 1,
  `foto_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `telefono`, `password`, `rol`, `tipo_colaborador`, `rango_colaborador`, `fecha_registro`, `activo`, `foto_id`) VALUES
(1, 'Administrador', 'admin@reminiscencia.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NULL, '2025-05-26 13:12:34', 1, NULL),
(2, 'Luis Pérez', '123455@gmail.com', '4499078204', '$2y$10$w3fSKNegZQEKsvuVpVxeX.2hG6aZ/BmLDIBnXSEvHxht4nN4XOxXK', 'colaborador', 'fotografo', 'III', '2025-05-26 16:25:05', 1, NULL),
(3, 'Amanda Ibañez', 'am_ibz2005@gmail.com', '4499078001', '$2y$10$37m/no11bFnZmb7u9IUEIu6c.at/1qBXEkPJJu4UdLo73hD5OynZ2', 'cliente', NULL, NULL, '2025-05-26 16:33:36', 0, NULL),
(4, 'Juan Algarra', 'Alg_Juan23223@gmail.com', '4499876776', '$2y$10$vPcT7RjaIDC4idWSrhx1x.S62GmzeqzV5ImhDMyw4Eke1RLNDoNFO', 'colaborador', 'videografo', 'II', '2025-05-28 17:18:02', 0, NULL),
(5, 'Zoé Valdéz', 'ZoeVal56_q@outlook.com', '4497869090', '$2y$10$HpVkmkp6X//ljmMW//7VeuNGXznHfEqQj1.NtSMIkfIqzXfx8/EYS', 'colaborador', 'videografo', 'III', '2025-05-30 04:47:49', 1, NULL),
(6, 'Dionicio Galicia', 'wikigalicia750@gmail.com', '4499078204', '$2y$10$U9HdM3U6KqDmQvGeRff3zOBHw/Prig1T79/a1CrXruyTwYqkaP6jS', 'cliente', NULL, NULL, '2025-05-30 05:04:04', 1, NULL),
(7, 'Alan Tenorio', 'alantenrdz@gmail.com', '5658583718', '$2y$10$COVvDfJ1yZBHny0I8SDg/elGsIXqLmfaB2O.fajMFtXBVmnoM/2K.', 'colaborador', 'auxiliar', 'III', '2025-05-30 05:41:04', 0, NULL),
(9, 'Hugo Martinez', 'HugoMar@hotmail.com', '4495001239', '$2y$10$FiqXLfXKAg4OGri9MbSrNOFstWLYFB3lgTGDB0cmLXX.pPSBUtZH.', 'cliente', NULL, NULL, '2025-06-08 17:41:45', 1, NULL),
(10, 'Carlos Valenzuela', 'carvalem@gmail.com', '4495001235', '$2y$10$xUa1UD3d5wDR4TP8/kYTjeuJoputadZ2DoTseoHKP902kEmJT.Q7K', 'colaborador', 'fotografo', 'III', '2025-06-08 17:44:38', 1, NULL),
(11, 'Dionicio Alejandro Galicia Quiroz', 'wikigalicia75@gmail.com', '4498078204', '$2y$10$dZeK8X1M3L1uAJ8JAXtHWeawB6wQoJtpmZ2NBeu9zrnJI5/WmY1GG', 'cliente', NULL, NULL, '2025-06-17 23:04:58', 1, 'uploads/fotos_id/foto_id_cliente_6851f434c7e9c.jpg'),
(12, 'Zuriel Dávila', 'DavZur2002@gmail.com', '4491232323', '$2y$10$8SxHwAAFJmSgcb5iLmI4Wu/4Bqbm9tH0.h3EbvchW4KVrlEfUCHDK', 'colaborador', 'fotografo', 'II', '2025-06-17 23:06:58', 1, 'uploads/fotos_id/foto_id_colaborador_6851f500b30bb.jpg');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `eventos`
--
ALTER TABLE `eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `fk_colaborador` (`colaborador_id`);

--
-- Indices de la tabla `evento_colaborador`
--
ALTER TABLE `evento_colaborador`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evento_id` (`evento_id`),
  ADD KEY `colaborador_id` (`colaborador_id`);

--
-- Indices de la tabla `evento_colaboradores`
--
ALTER TABLE `evento_colaboradores`
  ADD PRIMARY KEY (`evento_id`,`colaborador_id`),
  ADD KEY `colaborador_id` (`colaborador_id`);

--
-- Indices de la tabla `llamadas`
--
ALTER TABLE `llamadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_llamadas_fecha_hora` (`fecha`,`hora`),
  ADD KEY `idx_llamadas_email` (`email`),
  ADD KEY `idx_llamadas_telefono` (`telefono`),
  ADD KEY `idx_llamadas_ip` (`ip_address`),
  ADD KEY `idx_llamadas_estado` (`estado`),
  ADD KEY `idx_llamadas_verificacion` (`identidad_verificada`),
  ADD KEY `idx_llamadas_fecha_creacion` (`fecha_creacion`),
  ADD KEY `idx_llamadas_tiempo_usuario` (`email`,`telefono`,`fecha`,`hora`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `prospectos_clientes`
--
ALTER TABLE `prospectos_clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `prospectos_colaboradores`
--
ALTER TABLE `prospectos_colaboradores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `solicitudes_colaborador`
--
ALTER TABLE `solicitudes_colaborador`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evento_id` (`evento_id`),
  ADD KEY `colaborador_id` (`colaborador_id`);

--
-- Indices de la tabla `tokens_verificacion`
--
ALTER TABLE `tokens_verificacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expiracion` (`fecha_expiracion`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `eventos`
--
ALTER TABLE `eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `evento_colaborador`
--
ALTER TABLE `evento_colaborador`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `llamadas`
--
ALTER TABLE `llamadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `prospectos_clientes`
--
ALTER TABLE `prospectos_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `prospectos_colaboradores`
--
ALTER TABLE `prospectos_colaboradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `solicitudes_colaborador`
--
ALTER TABLE `solicitudes_colaborador`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `tokens_verificacion`
--
ALTER TABLE `tokens_verificacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `eventos`
--
ALTER TABLE `eventos`
  ADD CONSTRAINT `eventos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_eventos_cliente_id` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_eventos_colaborador_id` FOREIGN KEY (`colaborador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `evento_colaborador`
--
ALTER TABLE `evento_colaborador`
  ADD CONSTRAINT `evento_colaborador_ibfk_1` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`),
  ADD CONSTRAINT `evento_colaborador_ibfk_2` FOREIGN KEY (`colaborador_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_evento_colaborador_colaborador_id` FOREIGN KEY (`colaborador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evento_colaborador_evento_id` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `evento_colaboradores`
--
ALTER TABLE `evento_colaboradores`
  ADD CONSTRAINT `evento_colaboradores_ibfk_1` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evento_colaboradores_ibfk_2` FOREIGN KEY (`colaborador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_evento_colaboradores_colaborador_id` FOREIGN KEY (`colaborador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evento_colaboradores_evento_id` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_notificaciones_usuario_id` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `solicitudes_colaborador`
--
ALTER TABLE `solicitudes_colaborador`
  ADD CONSTRAINT `solicitudes_colaborador_ibfk_1` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_colaborador_ibfk_2` FOREIGN KEY (`colaborador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
