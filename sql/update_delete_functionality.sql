-- sql/update_delete_functionality.sql
-- Actualización para agregar funcionalidad de eliminación

-- Agregar columnas para soft delete en la tabla documents
ALTER TABLE documents 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
ADD COLUMN deleted_by INT NULL DEFAULT NULL AFTER deleted_at;

-- Agregar índice para consultas de documentos activos
CREATE INDEX idx_documents_status ON documents(status);

-- Agregar índice para documentos eliminados
CREATE INDEX idx_documents_deleted ON documents(deleted_at);

-- Agregar clave foránea para el usuario que eliminó
ALTER TABLE documents 
ADD CONSTRAINT fk_documents_deleted_by 
FOREIGN KEY (deleted_by) REFERENCES users(id) 
ON DELETE SET NULL;

-- Crear directorio para archivos eliminados (esto debe hacerse desde PHP)
-- mkdir uploads/deleted/ con permisos 755

-- Verificar la estructura actualizada
DESCRIBE documents;