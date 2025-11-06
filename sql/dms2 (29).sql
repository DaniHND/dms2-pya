-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 05-11-2025 a las 16:36:06
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `dms2`
--
CREATE DATABASE IF NOT EXISTS `dms2` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `dms2`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `companies`
--

DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ruc` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `companies`
--

INSERT INTO `companies` (`id`, `name`, `description`, `ruc`, `address`, `phone`, `email`, `contact_person`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Empresa Ejemplo SA', 'Empresa principal del grupo empresarial', '1234567890123', 'Av. Principal 123, Ciudad', '555-0001', 'contacto@ejemplo.com', 'Juan Pérez', 'active', '2025-07-24 17:26:06', '2025-08-02 15:44:45'),
(2, 'Corporación Demo SRL', 'Corporación especializada en servicios', '9876543210987', 'Calle Secundaria 456, Ciudad', '555-0002', 'info@demo.com', 'María González', 'active', '2025-07-24 17:26:06', '2025-08-02 15:44:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departments`
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `departments`
--

INSERT INTO `departments` (`id`, `company_id`, `manager_id`, `name`, `description`, `parent_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Administración', 'Departamento de Administración General', NULL, 'active', '2025-07-24 17:26:06', '2025-07-24 17:26:06'),
(4, 2, NULL, 'Ventas', 'Departamento de Ventas', NULL, 'active', '2025-07-24 17:26:06', '2025-07-24 17:26:06'),
(5, 2, NULL, 'Marketing', 'Departamento de Marketing', 4, 'active', '2025-07-24 17:26:06', '2025-07-24 17:26:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documents`
--

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `document_type_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `status` enum('active','archived','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `documents`
--

INSERT INTO `documents` (`id`, `company_id`, `department_id`, `folder_id`, `document_type_id`, `user_id`, `name`, `original_name`, `file_path`, `file_size`, `mime_type`, `description`, `tags`, `status`, `created_at`, `updated_at`, `deleted_at`, `deleted_by`) VALUES
(153, 1, 1, 27, 4, 1, 'diagrama de caso', 'dms2_diagrama_casos_uso.png', 'uploads/documents/diagrama_de_caso.png', 56503, 'image/png', '', NULL, 'active', '2025-08-21 17:16:57', '2025-08-21 17:17:42', NULL, NULL),
(154, 1, 1, NULL, 4, 1, 'DMS2_UseCase', 'DMS2_UseCase.png', 'uploads/documents/DMS2_UseCase.png', 284663, 'image/png', '', NULL, 'deleted', '2025-08-21 17:17:18', '2025-08-21 17:17:24', '2025-08-21 17:17:24', 1),
(155, 1, 1, 28, 4, 1, 'imagen defesa', 'WIN_20241119_16_58_37_Pro.jpg', 'uploads/documents/imagen_defesa.jpg', 131329, 'image/jpeg', '', NULL, 'active', '2025-11-05 05:20:26', '2025-11-05 05:20:26', NULL, NULL),
(156, 1, 1, 27, 1, 1, 'roblox', 'RobloxScreenShot20241224_222018416.png', 'uploads/documents/roblox.png', 589225, 'image/png', '', NULL, 'deleted', '2025-11-05 05:21:06', '2025-11-05 05:21:19', '2025-11-05 05:21:19', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_folders`
--

DROP TABLE IF EXISTS `document_folders`;
CREATE TABLE `document_folders` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `parent_folder_id` int(11) DEFAULT NULL,
  `folder_color` varchar(20) DEFAULT '#3498db',
  `folder_icon` varchar(30) DEFAULT 'folder',
  `folder_path` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `document_folders`
--

INSERT INTO `document_folders` (`id`, `name`, `description`, `company_id`, `department_id`, `parent_folder_id`, `folder_color`, `folder_icon`, `folder_path`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(27, 'cas', '', 1, 1, NULL, '#34495e', 'folder', '/Empresa Ejemplo SA/Administración/cas', 1, 1, '2025-08-21 17:17:35', '2025-08-21 17:17:35'),
(28, 'Defensa', '', 1, 1, NULL, '#e74c3c', 'file-text', '/Empresa Ejemplo SA/Administración/Defensa', 1, 1, '2025-11-05 05:18:11', '2025-11-05 05:18:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_types`
--

DROP TABLE IF EXISTS `document_types`;
CREATE TABLE `document_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'file-text',
  `color` varchar(7) DEFAULT '#6b7280',
  `extensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extensions`)),
  `max_size` int(11) DEFAULT 10485760,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `document_types`
--

INSERT INTO `document_types` (`id`, `name`, `description`, `icon`, `color`, `extensions`, `max_size`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Facturas', 'Facturas y comprobantes fiscales', 'file-text', '#6b7280', '[\"pdf\", \"jpg\", \"png\"]', 10485760, 'active', '2025-07-24 17:26:06', '2025-07-30 19:47:17'),
(2, 'Contratos', 'Contratos y acuerdos legales', 'file-text', '#6b7280', '[\"pdf\", \"doc\", \"docx\"]', 20971520, 'active', '2025-07-24 17:26:06', '2025-07-30 19:47:17'),
(4, 'Imágenes', 'Archivos de imagen', 'file-text', '#6b7280', '[\"jpg\", \"jpeg\", \"png\", \"gif\"]', 5242880, 'active', '2025-07-24 17:26:06', '2025-07-30 19:47:17');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `group_stats`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `group_stats`;
CREATE TABLE `group_stats` (
`id` int(11)
,`name` varchar(150)
,`description` text
,`status` enum('active','inactive')
,`is_system_group` tinyint(1)
,`module_permissions` longtext
,`access_restrictions` longtext
,`download_limit_daily` int(11)
,`upload_limit_daily` int(11)
,`total_members` bigint(21)
,`active_members` bigint(21)
,`companies_represented` bigint(21)
,`departments_represented` bigint(21)
,`created_at` timestamp
,`created_by_name` varchar(201)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inbox_records`
--

DROP TABLE IF EXISTS `inbox_records`;
CREATE TABLE `inbox_records` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `recipient_user_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `read_status` enum('unread','read') DEFAULT 'unread',
  `read_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','deleted','archived') DEFAULT 'active',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `action_url` varchar(500) DEFAULT NULL,
  `action_text` varchar(100) DEFAULT NULL,
  `read_status` enum('unread','read') DEFAULT 'unread',
  `read_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tokens de recuperación de contraseña con expiración de 1 hora';

--
-- Volcado de datos para la tabla `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `email`, `token`, `expires_at`, `used`, `used_at`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 18, 'danihon89@gmail.com', '697c7a64809ee5618265d5ef029ebe2696b6de1e4ffd2997febbf92018d14330', '2025-11-05 12:45:03', 1, '2025-11-05 12:45:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 12:42:40'),
(2, 18, 'danihon89@gmail.com', 'f148bfda70626dfcea248dbc17809b34b797b229b12ae7a4651213357c02b64a', '2025-11-05 12:50:21', 1, '2025-11-05 12:50:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 12:45:03'),
(3, 18, 'danihon89@gmail.com', '9de5063cd4dee61d12427c22387aca9674f554135626b4703e365a77fef4e98f', '2025-11-05 12:51:55', 1, '2025-11-05 12:51:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 12:50:21'),
(4, 18, 'danihon89@gmail.com', '6a6f43cb5cc45f71c6c81745df80198a9fa3470e04e1a71f89ffcc56a3e9802e', '2025-11-05 12:54:32', 1, '2025-11-05 12:54:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 12:51:55'),
(5, 18, 'danihon89@gmail.com', '2002061b1ed270b63bd25997192d70fad71d473cb32e439ec6b3c4f1580f5e7f', '2025-11-05 12:55:30', 1, '2025-11-05 12:55:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 12:54:32'),
(6, 18, 'danihon89@gmail.com', '75374bf392c3eb825c94c5ed94347f2109a7ff0c69817bbc3c0f812c5f130091', '2025-11-05 12:58:42', 1, '2025-11-05 12:58:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 12:55:30'),
(7, 18, 'danihon89@gmail.com', '8f1c44d030ebc9f5c47b3442573b19631ad3ebbb5bdde3a8b703e9afbc23f805', '2025-11-05 13:00:57', 1, '2025-11-05 13:00:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 12:58:42'),
(8, 18, 'danihon89@gmail.com', 'b56c48ce28bc8cd3ba54088bfdc4378ea0c524df38d91e7c3cb293e5dbe090e9', '2025-11-05 13:02:37', 1, '2025-11-05 13:02:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 13:00:57'),
(9, 18, 'danihon89@gmail.com', '3d7a9ea4c61cc26076844356846ceef4ca6c0b07aeff560cfb6357c2ac2fb2ea', '2025-11-05 13:05:01', 1, '2025-11-05 13:05:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 13:02:37'),
(10, 18, 'danihon89@gmail.com', '474595eb5473c02c5c45413771ad50159781349a606780ea17261b30de37ba92', '2025-11-05 13:08:02', 1, '2025-11-05 13:08:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 13:05:01'),
(11, 18, 'danihon89@gmail.com', '52980d62599ba52b0eb643d1edff7d169966e4b6ce41a01b2803175c515af1f1', '2025-11-05 13:15:21', 1, '2025-11-05 13:15:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 13:08:02'),
(12, 18, 'danihon89@gmail.com', '7fe3f18f7ccfa798caf32c0c15f174aa2440685d97971de562268f29ad40377d', '2025-11-05 13:16:07', 1, '2025-11-05 13:16:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 13:15:21'),
(13, 18, 'danihon89@gmail.com', '193c301adaf317eb224b26013af543053ca539a6f338a987ee2c28bcd063f713', '2025-11-05 13:21:42', 1, '2025-11-05 13:21:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 13:21:01'),
(14, 18, 'danihon89@gmail.com', '5942480a0bf0ebe0c32280896e8d9731b84d69960097709f780cf2119b738357', '2025-11-05 14:19:57', 1, '2025-11-05 14:19:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-05 14:19:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `security_groups`
--

DROP TABLE IF EXISTS `security_groups`;
CREATE TABLE `security_groups` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `security_groups`
--

INSERT INTO `security_groups` (`id`, `company_id`, `department_id`, `name`, `description`, `permissions`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Administradores', 'Acceso total al sistema', '{\"read\": true, \"write\": true, \"delete\": true, \"admin\": true}', 'active', '2025-07-24 17:26:06', '2025-07-24 17:26:06'),
(3, 2, 4, 'Usuarios Ventas', 'Acceso a documentos', '{\"read\": true, \"write\": true, \"delete\": false, \"admin\": false}', 'active', '2025-07-24 17:26:06', '2025-08-08 22:23:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_config`
--

DROP TABLE IF EXISTS `system_config`;
CREATE TABLE `system_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `system_config`
--

INSERT INTO `system_config` (`id`, `config_key`, `config_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'system_name', 'DMS2 - Sistema Gestor de Documentos', 'Nombre del sistema', '2025-07-24 17:26:06', '2025-07-24 17:26:06'),
(2, 'max_file_size', '20971520', 'Tamaño máximo de archivo en bytes (20MB)', '2025-07-24 17:26:06', '2025-07-24 17:26:06'),
(3, 'allowed_extensions', '[\"pdf\", \"doc\", \"docx\", \"xlsx\", \"jpg\", \"jpeg\", \"png\", \"gif\"]', 'Extensiones permitidas', '2025-07-24 17:26:06', '2025-07-24 17:26:06'),
(4, 'session_timeout', '3600', 'Tiempo de sesión en segundos (1 hora)', '2025-07-24 17:26:06', '2025-07-24 17:26:06'),
(5, 'backup_enabled', 'true', 'Habilitar respaldos automáticos', '2025-07-24 17:26:06', '2025-07-24 17:26:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `role` enum('admin','user','viewer','super_admin') DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `download_enabled` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `first_name`, `last_name`, `company_id`, `department_id`, `group_id`, `role`, `status`, `download_enabled`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$12$uBMGCppPSyb6myMpERoVYeSvKDOQbA0qyfcLQskil4SXLcM30jMDi', 'admin@dms2.com', 'Administrador', 'Sistema', 1, 1, 1, 'admin', 'active', 1, '2025-11-05 15:05:50', '2025-07-24 17:26:06', '2025-11-05 15:05:50'),
(2, 'jperez', '$2y$12$6G3Xz47idAv49Dg7Wt1kiuiYgOVBg66L9.ZxmgJ7JrxKCn6jWLbtm', 'jperez@ejemplo.com', 'Juan', 'Pérez', 1, NULL, NULL, 'user', 'active', 0, '2025-11-05 06:43:39', '2025-07-24 17:26:06', '2025-11-05 06:43:39'),
(15, 'uprueba', '$2y$10$zup62OnOTczZOak.tb9nCutk7VZh1IphMIMG8L/FF1eWRfs.TUNou', 'prueba@prueba.com', 'Prueba', 'Prueba', NULL, NULL, NULL, 'user', 'active', 1, '2025-08-19 15:05:48', '2025-08-16 16:24:38', '2025-08-19 15:05:48'),
(16, 'uprueba2', '$2y$10$gx7Vz2Dy5/FH1ImFKzB4EuxFxDC8hD8yXr.To0M6JVDoM9RIpoVf2', 'prueb@xamp.com', 'Preuba2', 'Prueba2', NULL, NULL, NULL, 'user', 'active', 1, NULL, '2025-08-16 16:25:47', '2025-08-16 16:25:47'),
(17, 'visual', '$2y$10$qAQlTT89b11vXD3rIwcVB.u/lpWQ01lETiJkgpeX6FG1Sn/ImuLZm', 'visual@prueba.com', 'Pruebav', 'Visualizador', 1, NULL, NULL, 'viewer', 'active', 1, NULL, '2025-08-21 17:12:50', '2025-08-21 17:12:50'),
(18, 'amartinezo', '$2y$10$uZfbmKVV446jYpfZg.hZ.uv3iNHF7HEQ5wQrNUzxUDhYHcLIj74xW', 'danihon89@gmail.com', 'Allan Daniel', 'Martinez Oviedo', 2, NULL, NULL, 'user', 'active', 0, '2025-11-05 14:20:07', '2025-11-05 12:39:37', '2025-11-05 14:20:07');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `user_access_summary`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `user_access_summary`;
CREATE TABLE `user_access_summary` (
`user_id` int(11)
,`username` varchar(50)
,`first_name` varchar(100)
,`last_name` varchar(100)
,`company_id` int(11)
,`department_id` int(11)
,`groups` mediumtext
,`group_ids` mediumtext
,`total_groups` bigint(21)
,`access_status` varchar(18)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_groups`
--

DROP TABLE IF EXISTS `user_groups`;
CREATE TABLE `user_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `module_permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' COMMENT 'Permisos específicos: upload_files, view_files, create_folders, download_files, delete_files' CHECK (json_valid(`module_permissions`)),
  `access_restrictions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' COMMENT 'Restricciones de acceso: companies[], departments[], document_types[]' CHECK (json_valid(`access_restrictions`)),
  `download_limit_daily` int(11) DEFAULT NULL COMMENT 'Límite de descargas diarias (NULL = sin límite)',
  `upload_limit_daily` int(11) DEFAULT NULL COMMENT 'Límite de subidas diarias (NULL = sin límite)',
  `status` enum('active','inactive') DEFAULT 'active',
  `is_system_group` tinyint(1) DEFAULT 0 COMMENT 'Grupo creado por el sistema (no editable)',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grupos de usuarios con permisos granulares de 5 acciones específicas';

--
-- Volcado de datos para la tabla `user_groups`
--

INSERT INTO `user_groups` (`id`, `name`, `description`, `module_permissions`, `access_restrictions`, `download_limit_daily`, `upload_limit_daily`, `status`, `is_system_group`, `created_by`, `created_at`, `updated_at`, `deleted_at`, `deleted_by`) VALUES
(46, 'Visualizadores', '', '{\"upload_files\":true,\"view_files\":true,\"create_folders\":false,\"download_files\":false,\"delete_files\":false,\"move_files\":false}', '{\"companies\":[1],\"departments\":[1],\"document_types\":[]}', NULL, NULL, '', 0, 1, '2025-08-21 17:22:20', '2025-11-05 05:04:32', NULL, NULL),
(47, 'Prueba', 'prueba antes de Defensa', '{\"upload_files\":true,\"view_files\":false,\"create_folders\":false,\"download_files\":false,\"delete_files\":false,\"move_files\":false}', '{\"companies\":[2],\"departments\":[4],\"document_types\":[1]}', NULL, NULL, 'active', 0, 1, '2025-11-05 05:04:26', '2025-11-05 05:06:17', NULL, NULL);

--
-- Disparadores `user_groups`
--
DROP TRIGGER IF EXISTS `user_groups_before_insert`;
DELIMITER $$
CREATE TRIGGER `user_groups_before_insert` BEFORE INSERT ON `user_groups` FOR EACH ROW BEGIN
    -- Validar formato de permisos solo si no es NULL y no está vacío
    IF NEW.module_permissions IS NOT NULL 
       AND NEW.module_permissions != '' 
       AND NEW.module_permissions != '{}' THEN
        
        -- Intentar validar con la función personalizada
        IF NOT ValidateGroupPermissions(NEW.module_permissions) THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Formato de permisos inválido. Debe contener las claves requeridas: upload_files, view_files, create_folders, download_files, delete_files.';
        END IF;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `user_groups_before_update`;
DELIMITER $$
CREATE TRIGGER `user_groups_before_update` BEFORE UPDATE ON `user_groups` FOR EACH ROW BEGIN
    -- Validar formato de permisos solo si no es NULL y no está vacío
    IF NEW.module_permissions IS NOT NULL 
       AND NEW.module_permissions != '' 
       AND NEW.module_permissions != '{}' THEN
        
        -- Intentar validar con la función personalizada
        IF NOT ValidateGroupPermissions(NEW.module_permissions) THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Formato de permisos inválido. Debe contener las claves requeridas: upload_files, view_files, create_folders, download_files, delete_files.';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_groups_backup`
--

DROP TABLE IF EXISTS `user_groups_backup`;
CREATE TABLE `user_groups_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' COMMENT 'Permisos por módulo: {"users": {"read": true, "write": false}, "documents": {"read": true, "write": true}}' CHECK (json_valid(`module_permissions`)),
  `access_restrictions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' COMMENT 'Restricciones: {"companies": [1,2], "departments": [3], "document_types": [1,5,7], "allowed_paths": ["/contabilidad/"]}' CHECK (json_valid(`access_restrictions`)),
  `download_limit_daily` int(11) DEFAULT NULL COMMENT 'Límite de descargas diarias (NULL = sin límite)',
  `upload_limit_daily` int(11) DEFAULT NULL COMMENT 'Límite de subidas diarias (NULL = sin límite)',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `is_system_group` tinyint(1) DEFAULT 0 COMMENT 'Grupo creado por el sistema (no editable)',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `user_groups_backup`
--

INSERT INTO `user_groups_backup` (`id`, `name`, `description`, `module_permissions`, `access_restrictions`, `download_limit_daily`, `upload_limit_daily`, `status`, `is_system_group`, `created_by`, `created_at`, `updated_at`, `deleted_at`, `deleted_by`) VALUES
(22, 'Prueba504', 'Grupo de prueba con permisos de edición', '{\"view\":true,\"view_reports\":true,\"download\":true,\"export\":true,\"create\":true,\"edit\":false,\"delete\":true,\"delete_permanent\":true,\"manage_users\":true,\"system_config\":true}', '{\"companies\":[3],\"departments\":[10],\"document_types\":[16]}', NULL, NULL, 'active', 0, 1, '2025-08-03 21:08:31', '2025-08-09 16:51:52', NULL, NULL),
(23, 'Administradores', 'Administradores con acceso completo al sistema', '{\"view\":false,\"view_reports\":false,\"download\":false,\"export\":false,\"create\":false,\"edit\":false,\"delete\":false,\"delete_permanent\":false,\"manage_users\":false,\"system_config\":false}', '{\"companies\":[3],\"departments\":[1],\"document_types\":[4,16]}', NULL, NULL, 'inactive', 0, 7, '2025-08-07 04:16:43', '2025-08-09 16:15:22', NULL, NULL),
(32, 'Grupo de prueba', 'grupo para probar permisos', '{}', '{}', NULL, NULL, 'active', 0, 1, '2025-08-10 03:45:48', '2025-08-10 03:45:48', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_group_members`
--

DROP TABLE IF EXISTS `user_group_members`;
CREATE TABLE `user_group_members` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `added_by` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relación muchos-a-muchos entre usuarios y grupos';

--
-- Volcado de datos para la tabla `user_group_members`
--

INSERT INTO `user_group_members` (`id`, `user_id`, `group_id`, `added_by`, `added_at`) VALUES
(59, 2, 47, 1, '2025-11-05 05:04:51');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_folders_complete`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `v_folders_complete`;
CREATE TABLE `v_folders_complete` (
`id` int(11)
,`name` varchar(150)
,`description` text
,`company_id` int(11)
,`department_id` int(11)
,`parent_folder_id` int(11)
,`folder_color` varchar(20)
,`folder_icon` varchar(30)
,`folder_path` text
,`is_active` tinyint(1)
,`created_by` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`company_name` varchar(255)
,`department_name` varchar(255)
,`parent_folder_name` varchar(150)
,`created_by_name` varchar(201)
,`document_count` bigint(21)
,`folder_level` int(1)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_group_permissions_summary`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `v_group_permissions_summary`;
CREATE TABLE `v_group_permissions_summary` (
`id` int(11)
,`name` varchar(150)
,`description` text
,`status` enum('active','inactive')
,`is_system_group` tinyint(1)
,`can_upload` int(1)
,`can_view` int(1)
,`can_create_folders` int(1)
,`can_download` int(1)
,`can_delete` int(1)
,`restricted_companies_count` int(10)
,`restricted_departments_count` int(10)
,`restricted_document_types_count` int(10)
,`total_members` bigint(21)
,`active_members` bigint(21)
,`created_at` timestamp
,`updated_at` timestamp
,`created_by_name` varchar(201)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `group_stats`
--
DROP TABLE IF EXISTS `group_stats`;

DROP VIEW IF EXISTS `group_stats`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `group_stats`  AS SELECT `ug`.`id` AS `id`, `ug`.`name` AS `name`, `ug`.`description` AS `description`, `ug`.`status` AS `status`, `ug`.`is_system_group` AS `is_system_group`, `ug`.`module_permissions` AS `module_permissions`, `ug`.`access_restrictions` AS `access_restrictions`, `ug`.`download_limit_daily` AS `download_limit_daily`, `ug`.`upload_limit_daily` AS `upload_limit_daily`, count(distinct `ugm`.`user_id`) AS `total_members`, count(distinct case when `u`.`status` = 'active' then `ugm`.`user_id` end) AS `active_members`, count(distinct `u`.`company_id`) AS `companies_represented`, count(distinct `u`.`department_id`) AS `departments_represented`, `ug`.`created_at` AS `created_at`, concat(`creator`.`first_name`,' ',`creator`.`last_name`) AS `created_by_name` FROM (((`user_groups` `ug` left join `user_group_members` `ugm` on(`ug`.`id` = `ugm`.`group_id`)) left join `users` `u` on(`ugm`.`user_id` = `u`.`id` and `u`.`status` <> 'deleted')) left join `users` `creator` on(`ug`.`created_by` = `creator`.`id`)) GROUP BY `ug`.`id`, `ug`.`name`, `ug`.`description`, `ug`.`status`, `ug`.`is_system_group`, `ug`.`created_at`, `creator`.`first_name`, `creator`.`last_name` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `user_access_summary`
--
DROP TABLE IF EXISTS `user_access_summary`;

DROP VIEW IF EXISTS `user_access_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_access_summary`  AS SELECT `u`.`id` AS `user_id`, `u`.`username` AS `username`, `u`.`first_name` AS `first_name`, `u`.`last_name` AS `last_name`, `u`.`company_id` AS `company_id`, `u`.`department_id` AS `department_id`, group_concat(distinct `ug`.`name` order by `ug`.`name` ASC separator ', ') AS `groups`, group_concat(distinct `ug`.`id` order by `ug`.`id` ASC separator ',') AS `group_ids`, count(distinct `ugm`.`group_id`) AS `total_groups`, CASE WHEN `u`.`status` = 'active' AND count(`ugm`.`group_id`) > 0 THEN 'active_with_groups' WHEN `u`.`status` = 'active' AND count(`ugm`.`group_id`) = 0 THEN 'active_no_groups' ELSE `u`.`status` END AS `access_status` FROM ((`users` `u` left join `user_group_members` `ugm` on(`u`.`id` = `ugm`.`user_id`)) left join `user_groups` `ug` on(`ugm`.`group_id` = `ug`.`id` and `ug`.`status` = 'active')) WHERE `u`.`status` <> 'deleted' GROUP BY `u`.`id`, `u`.`username`, `u`.`first_name`, `u`.`last_name`, `u`.`company_id`, `u`.`department_id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_folders_complete`
--
DROP TABLE IF EXISTS `v_folders_complete`;

DROP VIEW IF EXISTS `v_folders_complete`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_folders_complete`  AS SELECT `f`.`id` AS `id`, `f`.`name` AS `name`, `f`.`description` AS `description`, `f`.`company_id` AS `company_id`, `f`.`department_id` AS `department_id`, `f`.`parent_folder_id` AS `parent_folder_id`, `f`.`folder_color` AS `folder_color`, `f`.`folder_icon` AS `folder_icon`, `f`.`folder_path` AS `folder_path`, `f`.`is_active` AS `is_active`, `f`.`created_by` AS `created_by`, `f`.`created_at` AS `created_at`, `f`.`updated_at` AS `updated_at`, `c`.`name` AS `company_name`, `d`.`name` AS `department_name`, `pf`.`name` AS `parent_folder_name`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `created_by_name`, count(`doc`.`id`) AS `document_count`, CASE WHEN `f`.`parent_folder_id` is null THEN 0 ELSE 1 END AS `folder_level` FROM (((((`document_folders` `f` left join `companies` `c` on(`f`.`company_id` = `c`.`id`)) left join `departments` `d` on(`f`.`department_id` = `d`.`id`)) left join `document_folders` `pf` on(`f`.`parent_folder_id` = `pf`.`id`)) left join `users` `u` on(`f`.`created_by` = `u`.`id`)) left join `documents` `doc` on(`f`.`id` = `doc`.`folder_id` and `doc`.`status` = 'active')) WHERE `f`.`is_active` = 1 GROUP BY `f`.`id`, `f`.`name`, `f`.`description`, `f`.`company_id`, `f`.`department_id`, `f`.`parent_folder_id`, `f`.`folder_color`, `f`.`folder_icon`, `f`.`folder_path`, `f`.`is_active`, `f`.`created_by`, `f`.`created_at`, `f`.`updated_at`, `c`.`name`, `d`.`name`, `pf`.`name`, `u`.`first_name`, `u`.`last_name` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_group_permissions_summary`
--
DROP TABLE IF EXISTS `v_group_permissions_summary`;

DROP VIEW IF EXISTS `v_group_permissions_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_group_permissions_summary`  AS SELECT `ug`.`id` AS `id`, `ug`.`name` AS `name`, `ug`.`description` AS `description`, `ug`.`status` AS `status`, `ug`.`is_system_group` AS `is_system_group`, CASE WHEN json_unquote(json_extract(`ug`.`module_permissions`,'$.upload_files')) = 'true' THEN 1 ELSE 0 END AS `can_upload`, CASE WHEN json_unquote(json_extract(`ug`.`module_permissions`,'$.view_files')) = 'true' THEN 1 ELSE 0 END AS `can_view`, CASE WHEN json_unquote(json_extract(`ug`.`module_permissions`,'$.create_folders')) = 'true' THEN 1 ELSE 0 END AS `can_create_folders`, CASE WHEN json_unquote(json_extract(`ug`.`module_permissions`,'$.download_files')) = 'true' THEN 1 ELSE 0 END AS `can_download`, CASE WHEN json_unquote(json_extract(`ug`.`module_permissions`,'$.delete_files')) = 'true' THEN 1 ELSE 0 END AS `can_delete`, ifnull(json_length(`ug`.`access_restrictions`,'$.companies'),0) AS `restricted_companies_count`, ifnull(json_length(`ug`.`access_restrictions`,'$.departments'),0) AS `restricted_departments_count`, ifnull(json_length(`ug`.`access_restrictions`,'$.document_types'),0) AS `restricted_document_types_count`, count(distinct `ugm`.`user_id`) AS `total_members`, count(distinct case when `u`.`status` = 'active' then `ugm`.`user_id` end) AS `active_members`, `ug`.`created_at` AS `created_at`, `ug`.`updated_at` AS `updated_at`, concat(ifnull(`creator`.`first_name`,''),' ',ifnull(`creator`.`last_name`,'')) AS `created_by_name` FROM (((`user_groups` `ug` left join `user_group_members` `ugm` on(`ug`.`id` = `ugm`.`group_id`)) left join `users` `u` on(`ugm`.`user_id` = `u`.`id`)) left join `users` `creator` on(`ug`.`created_by` = `creator`.`id`)) WHERE `ug`.`deleted_at` is null GROUP BY `ug`.`id`, `ug`.`name`, `ug`.`description`, `ug`.`status`, `ug`.`is_system_group`, `ug`.`module_permissions`, `ug`.`access_restrictions`, `ug`.`created_at`, `ug`.`updated_at`, `creator`.`first_name`, `creator`.`last_name` ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_logs_user` (`user_id`),
  ADD KEY `idx_activity_logs_date` (`created_at`);

--
-- Indices de la tabla `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ruc` (`ruc`);

--
-- Indices de la tabla `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indices de la tabla `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_documents_company` (`company_id`),
  ADD KEY `idx_documents_type` (`document_type_id`),
  ADD KEY `idx_documents_user` (`user_id`),
  ADD KEY `idx_documents_status` (`status`),
  ADD KEY `idx_documents_deleted_at` (`deleted_at`),
  ADD KEY `idx_documents_company_status` (`company_id`,`status`),
  ADD KEY `idx_documents_user_status` (`user_id`,`status`),
  ADD KEY `fk_documents_deleted_by` (`deleted_by`),
  ADD KEY `idx_documents_folder` (`folder_id`);

--
-- Indices de la tabla `document_folders`
--
ALTER TABLE `document_folders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_folder_per_dept` (`name`,`department_id`,`parent_folder_id`),
  ADD KEY `idx_folders_company` (`company_id`),
  ADD KEY `idx_folders_department` (`department_id`),
  ADD KEY `idx_folders_parent` (`parent_folder_id`),
  ADD KEY `idx_folders_active` (`is_active`),
  ADD KEY `idx_folders_created` (`created_at`),
  ADD KEY `fk_folders_creator` (`created_by`);

--
-- Indices de la tabla `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `inbox_records`
--
ALTER TABLE `inbox_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient` (`recipient_user_id`),
  ADD KEY `idx_sender` (`sender_user_id`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_read_status` (`read_status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_composite_inbox` (`recipient_user_id`,`status`,`read_status`);

--
-- Indices de la tabla `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_read_status` (`read_status`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indices de la tabla `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_used` (`used`),
  ADD KEY `idx_token_valid` (`token`,`used`,`expires_at`);

--
-- Indices de la tabla `security_groups`
--
ALTER TABLE `security_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indices de la tabla `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_company` (`company_id`);

--
-- Indices de la tabla `user_groups`
--
ALTER TABLE `user_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_status_system` (`status`,`is_system_group`),
  ADD KEY `idx_user_groups_deleted_at` (`deleted_at`),
  ADD KEY `fk_user_groups_deleted_by` (`deleted_by`),
  ADD KEY `idx_user_groups_status_active` (`status`,`is_system_group`),
  ADD KEY `idx_user_groups_created_updated` (`created_at`,`updated_at`),
  ADD KEY `idx_user_groups_name_status` (`name`,`status`);

--
-- Indices de la tabla `user_group_members`
--
ALTER TABLE `user_group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_group` (`user_id`,`group_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_group_id` (`group_id`),
  ADD KEY `idx_assigned_by` (`added_by`),
  ADD KEY `idx_group_user` (`group_id`,`user_id`),
  ADD KEY `idx_user_group_members_group_user` (`group_id`,`user_id`),
  ADD KEY `idx_user_group_members_user_added` (`user_id`,`added_at`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de la tabla `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;

--
-- AUTO_INCREMENT de la tabla `document_folders`
--
ALTER TABLE `document_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `inbox_records`
--
ALTER TABLE `inbox_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `security_groups`
--
ALTER TABLE `security_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `system_config`
--
ALTER TABLE `system_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `user_groups`
--
ALTER TABLE `user_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT de la tabla `user_group_members`
--
ALTER TABLE `user_group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `departments_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `documents_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_documents_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_documents_document_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_documents_folder` FOREIGN KEY (`folder_id`) REFERENCES `document_folders` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `document_folders`
--
ALTER TABLE `document_folders`
  ADD CONSTRAINT `document_folders_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_folders_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_folders_ibfk_3` FOREIGN KEY (`parent_folder_id`) REFERENCES `document_folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_folders_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_folders_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_folders_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_folders_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_folders_parent` FOREIGN KEY (`parent_folder_id`) REFERENCES `document_folders` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `security_groups`
--
ALTER TABLE `security_groups`
  ADD CONSTRAINT `security_groups_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `security_groups_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`group_id`) REFERENCES `security_groups` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `user_groups`
--
ALTER TABLE `user_groups`
  ADD CONSTRAINT `fk_user_groups_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_groups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `user_group_members`
--
ALTER TABLE `user_group_members`
  ADD CONSTRAINT `user_group_members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_group_members_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_group_members_ibfk_3` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
