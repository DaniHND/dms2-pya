-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-08-2025 a las 22:25:07
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activity_logs`
--

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
(2, 'Corporación Demo SRL', 'Corporación especializada en servicios', '9876543210987', 'Calle Secundaria 456, Ciudad', '555-0002', 'info@demo.com', 'María González', 'active', '2025-07-24 17:26:06', '2025-08-02 15:44:45'),
(7, 'prueba', NULL, NULL, NULL, '98741256', 'prueba@prueba.com', NULL, 'active', '2025-08-12 21:34:39', '2025-08-12 21:34:39'),
(8, 'Perdomo y Asociados', NULL, NULL, NULL, '97414758', 'soporte@perdomoyasociados.com', NULL, 'active', '2025-08-12 22:53:10', '2025-08-12 22:53:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departments`
--

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
(5, 2, NULL, 'Marketing', 'Departamento de Marketing', 4, 'active', '2025-07-24 17:26:06', '2025-07-24 17:26:06'),
(30, 7, NULL, 'Prueba1', '', NULL, 'active', '2025-08-12 21:34:56', '2025-08-12 21:34:56'),
(31, 7, NULL, 'prueba2', '', NULL, 'active', '2025-08-12 21:35:08', '2025-08-12 21:35:08'),
(32, 8, NULL, 'Contabilidad', '', NULL, 'active', '2025-08-12 22:53:30', '2025-08-12 22:53:30'),
(33, 8, NULL, 'Tecnologia', '', NULL, 'active', '2025-08-12 22:53:44', '2025-08-12 22:53:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documents`
--

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
(81, 2, 5, NULL, 4, 1, 'pag_002', 'pag_002.jpg', 'uploads/documents/689ca689e63df_1755096713.jpg', 428937, 'image/jpeg', '', '[]', 'deleted', '2025-08-13 14:51:53', '2025-08-13 14:52:09', '2025-08-13 14:52:09', 1),
(82, 7, 30, NULL, 4, 1, 'pag_003', 'pag_003.jpg', 'uploads/documents/689ca72a38ad7_1755096874.jpg', 441904, 'image/jpeg', 'portada libro de cocina', '[]', 'deleted', '2025-08-13 14:54:34', '2025-08-13 21:28:15', '2025-08-13 21:28:15', 1),
(83, 2, 5, NULL, 19, 1, 'A.Wilson - Brownies', 'A.Wilson - Brownies.pdf', 'uploads/documents/689ca75d322f4_1755096925.pdf', 2528840, 'application/pdf', 'Recetas de Brownies', '[]', 'deleted', '2025-08-13 14:55:25', '2025-08-13 15:58:16', '2025-08-13 15:58:16', 1),
(84, 8, 32, NULL, 2, 1, 'RIFA COOP BARBER', 'RIFA COOP BARBER.xlsx', 'uploads/documents/689ca7977d2d3_1755096983.xlsx', 79819, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Rifa Cooperativa', '[]', 'active', '2025-08-13 14:56:23', '2025-08-13 14:56:23', NULL, NULL),
(85, 8, 33, NULL, 19, 1, 'C O N S T A N C I A MÉDICA.', 'C O N S T A N C I A MÉDICA..docx', 'uploads/documents/689ca7c357cba_1755097027.docx', 35674, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Constancia Medica', '[]', 'active', '2025-08-13 14:57:07', '2025-08-13 14:57:07', NULL, NULL),
(86, 7, 30, NULL, 4, 1, 'Pastelillos y panes', 'Pastelillos y panes.pdf', 'uploads/documents/689cb0552532d_1755099221.pdf', 1838084, 'application/pdf', '', '[]', 'deleted', '2025-08-13 15:33:41', '2025-08-13 22:30:02', '2025-08-13 22:30:02', 1),
(87, 8, 32, NULL, 4, 1, 'Panader_a_con_pasta_hojaldre', 'Panader_a_con_pasta_hojaldre.pdf', 'uploads/documents/689cb3b00f924_1755100080.pdf', 2646625, 'application/pdf', '', '[]', 'deleted', '2025-08-13 15:48:00', '2025-08-13 21:14:13', '2025-08-13 21:14:13', 1),
(88, 7, 30, NULL, 2, 1, 'Cuentos Cortos por Brizza Pavon', 'Cuentos Cortos por Brizza Pavon.docx', 'uploads/documents/689cb4ec85d1c_1755100396.docx', 17865, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '', '[]', 'deleted', '2025-08-13 15:53:16', '2025-08-13 22:15:02', '2025-08-13 22:15:02', 1),
(89, 1, 1, NULL, 2, 1, 'Pastelillos y panes', 'Pastelillos y panes.pdf', 'uploads/documents/689cb5f1e2af0_1755100657.pdf', 1838084, 'application/pdf', '', '[]', 'deleted', '2025-08-13 15:57:37', '2025-08-13 20:49:03', '2025-08-13 20:49:03', 1),
(90, 7, 30, NULL, 4, 1, 'Panader_a_con_pasta_hojaldre (1)', 'Panader_a_con_pasta_hojaldre.pdf', 'uploads/documents/689ce6c70e4ba_1755113159_0.pdf', 2646625, 'application/pdf', '', '[]', 'deleted', '2025-08-13 19:25:59', '2025-08-14 01:58:25', '2025-08-14 01:58:25', 1),
(91, 7, 30, NULL, 4, 1, 'Panader_a_con_pasta_hojaldre (2)', 'Pastelillos y panes.pdf', 'uploads/deleted/91_1755184397_689ce6c711b6e_1755113159_1.pdf', 1838084, 'application/pdf', '', '[]', 'deleted', '2025-08-13 19:25:59', '2025-08-14 15:13:17', '2025-08-14 15:13:17', 1),
(92, 7, 30, 21, 4, 1, 'Panader_a_con_pasta_hojaldre (3)', 'Sabrosas empanadas dulces y saladas.pdf', 'uploads/documents/689ce6c713508_1755113159_2.pdf', 5598268, 'application/pdf', '', '[]', 'active', '2025-08-13 19:25:59', '2025-08-14 19:23:32', NULL, NULL),
(93, 7, 30, NULL, 2, 1, 'pag_009', 'pag_009.jpg', 'uploads/documents/689ceb5e10bde_1755114334_0.jpg', 378762, 'image/jpeg', '', '[]', 'deleted', '2025-08-13 19:45:34', '2025-08-13 21:45:09', '2025-08-13 21:45:09', 1),
(94, 7, 30, NULL, 19, 1, 'pag_003 (1)', 'pag_003.jpg', 'uploads/documents/689d48b2ae7d9_1755138226_0.jpg', 441904, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 02:23:46', '2025-08-14 03:28:59', '2025-08-14 03:28:59', 1),
(95, 7, 30, NULL, 19, 1, 'pag_003 (2)', 'pag_004.jpg', 'uploads/documents/689d48b2b0e35_1755138226_1.jpg', 374632, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 02:23:46', '2025-08-14 03:37:19', '2025-08-14 03:37:19', 1),
(96, 7, 30, NULL, 19, 1, 'pag_003 (3)', 'pag_005.jpg', 'uploads/documents/689d48b2b43a3_1755138226_2.jpg', 534735, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 02:23:46', '2025-08-14 02:24:06', '2025-08-14 02:24:06', 1),
(97, 7, 30, NULL, 19, 1, 'pag_003 (4)', 'pag_006.jpg', 'uploads/documents/689d48b2b625a_1755138226_3.jpg', 375184, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 02:23:46', '2025-08-14 03:53:36', '2025-08-14 03:53:36', 1),
(98, 7, 30, NULL, 19, 1, 'pag_003 (5)', 'pag_007.jpg', 'uploads/documents/689d48b2b8518_1755138226_4.jpg', 424536, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 02:23:46', '2025-08-14 05:52:04', '2025-08-14 05:52:04', 1),
(99, 7, 30, NULL, 19, 1, 'pag_003 (6)', 'pag_008.jpg', 'uploads/documents/689d48b2baa70_1755138226_5.jpg', 534098, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 02:23:46', '2025-08-14 05:56:23', '2025-08-14 05:56:23', 1),
(100, 7, 30, NULL, 19, 1, 'pag_003 (7)', 'pag_009.jpg', 'uploads/deleted/100_1755184001_689d48b2bca78_1755138226_6.jpg', 378762, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 02:23:46', '2025-08-14 15:06:41', '2025-08-14 15:06:41', 1),
(101, 7, 30, NULL, 4, 1, 'pag_041', 'pag_041.jpg', 'uploads/deleted/101_1755199140_689dfe37a00bd_1755184695_0.jpg', 612501, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 15:18:15', '2025-08-14 19:19:00', '2025-08-14 19:19:00', 1),
(102, 7, 30, 21, 4, 1, 'Recetas (1)', 'pag_004.jpg', 'uploads/deleted/102_1755199663_689e36806dac4_1755199104_0.jpg', 374632, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 19:18:24', '2025-08-14 19:27:43', '2025-08-14 19:27:43', 1),
(105, 7, 30, NULL, 4, 1, 'Recetas (4)', 'pag_007.jpg', 'uploads/documents/689e368075d5a_1755199104_3.jpg', 424536, 'image/jpeg', '', '[]', 'active', '2025-08-14 19:18:24', '2025-08-14 19:18:24', NULL, NULL),
(106, 7, 30, NULL, 4, 1, 'Recetas (5)', 'pag_008.jpg', 'uploads/documents/689e368079fe2_1755199104_4.jpg', 534098, 'image/jpeg', '', '[]', 'active', '2025-08-14 19:18:24', '2025-08-14 19:18:24', NULL, NULL),
(107, 7, 30, NULL, 4, 1, 'Recetas (6)', 'pag_009.jpg', 'uploads/documents/689e36807b8e4_1755199104_5.jpg', 378762, 'image/jpeg', '', '[]', 'active', '2025-08-14 19:18:24', '2025-08-14 19:18:24', NULL, NULL),
(108, 7, 30, NULL, 4, 1, 'Recetas (7)', 'pag_010.jpg', 'uploads/documents/689e36807e779_1755199104_6.jpg', 592757, 'image/jpeg', '', '[]', 'active', '2025-08-14 19:18:24', '2025-08-14 19:18:24', NULL, NULL),
(109, 7, 30, NULL, 4, 1, 'Recetas (8)', 'pag_011.jpg', 'uploads/documents/689e368080c4b_1755199104_7.jpg', 532518, 'image/jpeg', '', '[]', 'active', '2025-08-14 19:18:24', '2025-08-14 19:18:24', NULL, NULL),
(110, 7, 30, NULL, 4, 1, 'Recetas (9)', 'pag_012.jpg', 'uploads/documents/689e36808267c_1755199104_8.jpg', 508154, 'image/jpeg', '', '[]', 'active', '2025-08-14 19:18:24', '2025-08-14 19:18:24', NULL, NULL),
(111, 7, 30, 21, 4, 1, 'Recetas (10)', 'pag_013.jpg', 'uploads/documents/689e3680849a3_1755199104_9.jpg', 488534, 'image/jpeg', '', '[]', 'active', '2025-08-14 19:18:24', '2025-08-14 19:27:53', NULL, NULL),
(112, 7, 30, 21, 4, 1, 'Recetas (11)', 'pag_014.jpg', 'uploads/documents/689e368087233_1755199104_10.jpg', 720695, 'image/jpeg', '', '[]', 'active', '2025-08-14 19:18:24', '2025-08-14 19:30:14', NULL, NULL),
(113, 7, 30, NULL, 4, 1, 'Recetas (12)', 'pag_015.jpg', 'uploads/deleted/113_1755199828_689e368089520_1755199104_11.jpg', 699826, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 19:18:24', '2025-08-14 19:30:28', '2025-08-14 19:30:28', 1),
(114, 7, 30, NULL, 4, 1, 'Recetas (13)', 'pag_016.jpg', 'uploads/deleted/114_1755200388_689e36808b097_1755199104_12.jpg', 700537, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 19:18:24', '2025-08-14 19:39:48', '2025-08-14 19:39:48', 1),
(115, 7, 30, NULL, 4, 1, 'Recetas (14)', 'pag_017.jpg', 'uploads/deleted/115_1755200393_689e36808c6b2_1755199104_13.jpg', 546414, 'image/jpeg', '', '[]', 'deleted', '2025-08-14 19:18:24', '2025-08-14 19:39:53', '2025-08-14 19:39:53', 1),
(116, 7, 30, 21, 4, 1, 'Recetas (15)', 'pag_018.jpg', 'uploads/documents/689e36808e2f9_1755199104_14.jpg', 660508, 'image/jpeg', '', '[]', 'active', '2025-08-14 19:18:24', '2025-08-14 19:39:57', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_folders`
--

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
(18, 'prueba carpeta', '', 1, 1, NULL, '#e74c3c', 'folder', 'prueba carpeta', 1, 1, '2025-08-14 03:24:44', NULL),
(19, 'Prueba', '', 1, 1, NULL, '#e74c3c', 'folder', 'Prueba', 1, 1, '2025-08-14 03:25:10', NULL),
(20, 'Prueba', '', 7, 30, NULL, '#e74c3c', 'folder', 'Prueba', 1, 1, '2025-08-14 05:48:49', NULL),
(21, 'prueba 2', '', 7, 30, NULL, '#e74c3c', 'folder', 'prueba 2', 1, 1, '2025-08-14 05:59:11', NULL),
(22, 'carpeta nueva', '', 7, 30, NULL, '#e74c3c', 'folder', '/prueba/Prueba1/carpeta nueva', 1, 1, '2025-08-14 16:10:49', '2025-08-14 16:10:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_types`
--

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
(4, 'Imágenes', 'Archivos de imagen', 'file-text', '#6b7280', '[\"jpg\", \"jpeg\", \"png\", \"gif\"]', 5242880, 'active', '2025-07-24 17:26:06', '2025-07-30 19:47:17'),
(19, 'prueba Documento', '', 'file-text', '#6b7280', NULL, 10485760, 'active', '2025-08-12 21:36:52', '2025-08-12 21:36:52');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `group_stats`
-- (Véase abajo para la vista actual)
--
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
-- Estructura de tabla para la tabla `security_groups`
--

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
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@dms2.com', 'Administrador', 'Sistema', 1, 1, 1, 'admin', 'active', 1, '2025-08-14 19:14:15', '2025-07-24 17:26:06', '2025-08-14 19:14:15'),
(2, 'jperez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'jperez@ejemplo.com', 'Juan', 'Pérez', 1, NULL, NULL, 'user', 'active', 0, '2025-08-11 19:47:47', '2025-07-24 17:26:06', '2025-08-11 19:47:47'),
(7, 'amartinezo', '$2y$10$.bRZF./Lp6h7yHiNLKU.me39qpXPpKuEYu2uPn.Ad4gYPEbLSQdPe', 'pasante01@perdomoyasociados.com', 'Allan Daniel', 'Martinez Oviedo', NULL, NULL, NULL, 'admin', 'active', 1, '2025-08-11 04:54:55', '2025-08-07 04:15:06', '2025-08-11 04:54:55'),
(13, 'prueba', '$2y$10$gyaE9Ol1ve/CcOD5c.dVfOzyHpWrdJyNIt.KWuOAbwMiIYuoZDHai', 'prueba@prueba.com', 'prueba', 'prueba', 2, NULL, NULL, 'user', 'active', 1, '2025-08-12 21:44:09', '2025-08-12 21:34:16', '2025-08-12 21:44:09'),
(14, 'Jurbina', '$2y$10$7.7z7MPPCVvOEWMS87k0H.jG8foBPwjI/PcrHafg0AUaNQN1gCUSW', 'jurbina@gmail.com', 'Jorge', 'Urbina', 7, NULL, NULL, 'user', 'active', 1, '2025-08-12 22:45:45', '2025-08-12 22:44:25', '2025-08-12 22:45:45');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `user_access_summary`
-- (Véase abajo para la vista actual)
--
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
(40, 'Prueba', '', '{\"upload_files\":true,\"view_files\":true,\"create_folders\":false,\"download_files\":true,\"delete_files\":false}', '{\"companies\":[7],\"departments\":[30],\"document_types\":[4,19]}', NULL, NULL, 'active', 0, 1, '2025-08-12 21:33:34', '2025-08-12 21:40:19', NULL, NULL),
(41, 'Prueba2', '', '{\"upload_files\":true,\"view_files\":true,\"create_folders\":false,\"download_files\":false,\"delete_files\":false}', '{\"companies\":[8],\"departments\":[33],\"document_types\":[]}', NULL, NULL, 'inactive', 0, 1, '2025-08-12 22:46:55', '2025-08-12 23:09:51', NULL, NULL);

--
-- Disparadores `user_groups`
--
DELIMITER $$
CREATE TRIGGER `user_groups_before_update` BEFORE UPDATE ON `user_groups` FOR EACH ROW BEGIN
    -- Validar formato de permisos solo si no es NULL
    IF NEW.module_permissions IS NOT NULL AND NEW.module_permissions != '' AND
       NOT ValidateGroupPermissions(NEW.module_permissions) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Formato de permisos inválido. Debe contener las 5 claves requeridas.';
    END IF;
    
    -- Asegurar que updated_at se actualice
    SET NEW.updated_at = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_groups_backup`
--

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
(51, 13, 40, 1, '2025-08-12 21:37:05'),
(53, 14, 41, 1, '2025-08-12 22:54:13');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_folders_complete`
-- (Véase abajo para la vista actual)
--
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

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `group_stats`  AS SELECT `ug`.`id` AS `id`, `ug`.`name` AS `name`, `ug`.`description` AS `description`, `ug`.`status` AS `status`, `ug`.`is_system_group` AS `is_system_group`, `ug`.`module_permissions` AS `module_permissions`, `ug`.`access_restrictions` AS `access_restrictions`, `ug`.`download_limit_daily` AS `download_limit_daily`, `ug`.`upload_limit_daily` AS `upload_limit_daily`, count(distinct `ugm`.`user_id`) AS `total_members`, count(distinct case when `u`.`status` = 'active' then `ugm`.`user_id` end) AS `active_members`, count(distinct `u`.`company_id`) AS `companies_represented`, count(distinct `u`.`department_id`) AS `departments_represented`, `ug`.`created_at` AS `created_at`, concat(`creator`.`first_name`,' ',`creator`.`last_name`) AS `created_by_name` FROM (((`user_groups` `ug` left join `user_group_members` `ugm` on(`ug`.`id` = `ugm`.`group_id`)) left join `users` `u` on(`ugm`.`user_id` = `u`.`id` and `u`.`status` <> 'deleted')) left join `users` `creator` on(`ug`.`created_by` = `creator`.`id`)) GROUP BY `ug`.`id`, `ug`.`name`, `ug`.`description`, `ug`.`status`, `ug`.`is_system_group`, `ug`.`created_at`, `creator`.`first_name`, `creator`.`last_name` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `user_access_summary`
--
DROP TABLE IF EXISTS `user_access_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_access_summary`  AS SELECT `u`.`id` AS `user_id`, `u`.`username` AS `username`, `u`.`first_name` AS `first_name`, `u`.`last_name` AS `last_name`, `u`.`company_id` AS `company_id`, `u`.`department_id` AS `department_id`, group_concat(distinct `ug`.`name` order by `ug`.`name` ASC separator ', ') AS `groups`, group_concat(distinct `ug`.`id` order by `ug`.`id` ASC separator ',') AS `group_ids`, count(distinct `ugm`.`group_id`) AS `total_groups`, CASE WHEN `u`.`status` = 'active' AND count(`ugm`.`group_id`) > 0 THEN 'active_with_groups' WHEN `u`.`status` = 'active' AND count(`ugm`.`group_id`) = 0 THEN 'active_no_groups' ELSE `u`.`status` END AS `access_status` FROM ((`users` `u` left join `user_group_members` `ugm` on(`u`.`id` = `ugm`.`user_id`)) left join `user_groups` `ug` on(`ugm`.`group_id` = `ug`.`id` and `ug`.`status` = 'active')) WHERE `u`.`status` <> 'deleted' GROUP BY `u`.`id`, `u`.`username`, `u`.`first_name`, `u`.`last_name`, `u`.`company_id`, `u`.`department_id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_folders_complete`
--
DROP TABLE IF EXISTS `v_folders_complete`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_folders_complete`  AS SELECT `f`.`id` AS `id`, `f`.`name` AS `name`, `f`.`description` AS `description`, `f`.`company_id` AS `company_id`, `f`.`department_id` AS `department_id`, `f`.`parent_folder_id` AS `parent_folder_id`, `f`.`folder_color` AS `folder_color`, `f`.`folder_icon` AS `folder_icon`, `f`.`folder_path` AS `folder_path`, `f`.`is_active` AS `is_active`, `f`.`created_by` AS `created_by`, `f`.`created_at` AS `created_at`, `f`.`updated_at` AS `updated_at`, `c`.`name` AS `company_name`, `d`.`name` AS `department_name`, `pf`.`name` AS `parent_folder_name`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `created_by_name`, count(`doc`.`id`) AS `document_count`, CASE WHEN `f`.`parent_folder_id` is null THEN 0 ELSE 1 END AS `folder_level` FROM (((((`document_folders` `f` left join `companies` `c` on(`f`.`company_id` = `c`.`id`)) left join `departments` `d` on(`f`.`department_id` = `d`.`id`)) left join `document_folders` `pf` on(`f`.`parent_folder_id` = `pf`.`id`)) left join `users` `u` on(`f`.`created_by` = `u`.`id`)) left join `documents` `doc` on(`f`.`id` = `doc`.`folder_id` and `doc`.`status` = 'active')) WHERE `f`.`is_active` = 1 GROUP BY `f`.`id`, `f`.`name`, `f`.`description`, `f`.`company_id`, `f`.`department_id`, `f`.`parent_folder_id`, `f`.`folder_color`, `f`.`folder_icon`, `f`.`folder_path`, `f`.`is_active`, `f`.`created_by`, `f`.`created_at`, `f`.`updated_at`, `c`.`name`, `d`.`name`, `pf`.`name`, `u`.`first_name`, `u`.`last_name` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_group_permissions_summary`
--
DROP TABLE IF EXISTS `v_group_permissions_summary`;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT de la tabla `document_folders`
--
ALTER TABLE `document_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `user_groups`
--
ALTER TABLE `user_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT de la tabla `user_group_members`
--
ALTER TABLE `user_group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

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
