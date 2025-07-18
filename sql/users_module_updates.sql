-- sql/users_module_updates.sql
-- Actualizaciones para el módulo de usuarios - DMS2

-- Agregar columna download_enabled a la tabla users si no existe
ALTER TABLE users ADD COLUMN IF NOT EXISTS download_enabled BOOLEAN DEFAULT TRUE AFTER status;

-- Agregar columna updated_at a la tabla users si no existe
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;

-- Crear índices para mejorar rendimiento
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_company ON users(company_id);
CREATE INDEX IF NOT EXISTS idx_users_download_enabled ON users(download_enabled);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

-- Actualizar usuarios existentes para habilitar descarga por defecto
UPDATE users SET download_enabled = TRUE WHERE download_enabled IS NULL;

-- Crear tabla de grupos de usuarios (para futuras implementaciones)
CREATE TABLE IF NOT EXISTS user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions JSON,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_group_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de relación usuario-grupo
CREATE TABLE IF NOT EXISTS user_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    UNIQUE KEY unique_user_group (user_id, group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de permisos de documentos por usuario
CREATE TABLE IF NOT EXISTS document_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    permission_type ENUM('view', 'download', 'edit', 'delete') NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id),
    UNIQUE KEY unique_document_user_permission (document_id, user_id, permission_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de sesiones de usuario (para control de sesiones)
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_sessions_user_id (user_id),
    INDEX idx_user_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar grupos predeterminados
INSERT IGNORE INTO user_groups (name, description, permissions) VALUES
('Administradores', 'Grupo con permisos completos del sistema', JSON_OBJECT(
    'users', JSON_ARRAY('create', 'read', 'update', 'delete'),
    'documents', JSON_ARRAY('create', 'read', 'update', 'delete', 'download'),
    'reports', JSON_ARRAY('read', 'export'),
    'system', JSON_ARRAY('configure', 'backup', 'restore')
)),
('Usuarios Estándar', 'Grupo con permisos básicos para usuarios regulares', JSON_OBJECT(
    'documents', JSON_ARRAY('create', 'read', 'update', 'download'),
    'reports', JSON_ARRAY('read')
)),
('Solo Lectura', 'Grupo con permisos de solo visualización', JSON_OBJECT(
    'documents', JSON_ARRAY('read'),
    'reports', JSON_ARRAY('read')
)),
('Sin Descarga', 'Grupo que puede ver documentos pero no descargarlos', JSON_OBJECT(
    'documents', JSON_ARRAY('read'),
    'reports', JSON_ARRAY('read')
));

-- Actualizar estructura de la tabla companies para mejorar relaciones
ALTER TABLE companies ADD COLUMN IF NOT EXISTS contact_email VARCHAR(255) AFTER phone;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS contact_person VARCHAR(100) AFTER contact_email;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS logo_path VARCHAR(500) AFTER contact_person;

-- Crear tabla de configuración del sistema
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    is_editable BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_settings_category (category),
    INDEX idx_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuraciones predeterminadas
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, category) VALUES
('max_file_size', '10485760', 'number', 'Tamaño máximo de archivo en bytes (10MB)', 'uploads'),
('allowed_extensions', '["pdf","doc","docx","xls","xlsx","jpg","jpeg","png","gif","txt","zip","rar"]', 'json', 'Extensiones de archivo permitidas', 'uploads'),
('session_timeout', '7200', 'number', 'Tiempo de sesión en segundos (2 horas)', 'security'),
('password_min_length', '6', 'number', 'Longitud mínima de contraseña', 'security'),
('require_email_verification', 'false', 'boolean', 'Requerir verificación de email', 'security'),
('enable_user_registration', 'false', 'boolean', 'Permitir registro de usuarios', 'security'),
('company_name', 'Perdomo y Asociados', 'string', 'Nombre de la empresa', 'general'),
('system_email', 'admin@perdomoyasociados.com', 'string', 'Email del sistema', 'general'),
('backup_retention_days', '30', 'number', 'Días de retención de backups', 'maintenance'),
('log_retention_days', '90', 'number', 'Días de retención de logs', 'maintenance');

-- Verificar y mostrar el estado de las tablas
SELECT 
    TABLE_NAME, 
    TABLE_ROWS, 
    DATA_LENGTH, 
    INDEX_LENGTH,
    CREATE_TIME,
    UPDATE_TIME
FROM 
    INFORMATION_SCHEMA.TABLES 
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('users', 'user_groups', 'user_group_members', 'document_permissions', 'user_sessions', 'system_settings')
ORDER BY 
    TABLE_NAME;

-- Mostrar índices creados
SHOW INDEX FROM users WHERE Key_name LIKE 'idx_%';

-- Comentarios finales
-- Este script actualiza la base de datos para soportar el módulo de usuarios avanzado
-- Incluye permisos de descarga, grupos de usuarios, permisos de documentos y configuración del sistema
-- Todas las operaciones son seguras y no afectan datos existentes