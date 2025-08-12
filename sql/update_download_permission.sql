-- sql/update_download_permission.sql
-- Actualización para agregar permisos de descarga

-- Agregar columna download_enabled a la tabla users
ALTER TABLE users ADD COLUMN download_enabled BOOLEAN DEFAULT TRUE AFTER status;

-- Actualizar comentario de la tabla
ALTER TABLE users COMMENT = 'Tabla de usuarios con permisos de descarga';

-- Crear índice para consultas rápidas
CREATE INDEX idx_users_download_enabled ON users(download_enabled);

-- Actualizar usuarios existentes (por defecto habilitados)
UPDATE users SET download_enabled = TRUE WHERE download_enabled IS NULL;

-- Ejemplo: Deshabilitar descarga para usuario de prueba (opcional)
-- UPDATE users SET download_enabled = FALSE WHERE username = 'jperez';

-- Verificar cambios
SELECT id, username, first_name, last_name, role, download_enabled, status 
FROM users 
ORDER BY id;