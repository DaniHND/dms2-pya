-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 15-08-2025 a las 17:46:32
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `document_folders`
--
ALTER TABLE `document_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `system_config`
--
ALTER TABLE `system_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_groups`
--
ALTER TABLE `user_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_group_members`
--
ALTER TABLE `user_group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
