<?php
// modules/documents/delete.php - REPARACIÓN COMPLETA
// Manejador para eliminar documentos - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// DIAGNÓSTICO: Verificar que lleguen los datos
error_log("DELETE.PHP - Iniciando eliminación");
error_log("DELETE.PHP - Método: " . $_SERVER['REQUEST_METHOD']);
error_log("DELETE.PHP - POST data: " . print_r($_POST, true));

// Solo aceptar POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("DELETE.PHP - ERROR: Método no es POST");
    header('Location: inbox.php?error=invalid_request');
    exit();
}

// Obtener ID del documento
$documentId = $_POST['document_id'] ?? '';

if (empty($documentId) || !is_numeric($documentId)) {
    error_log("DELETE.PHP - ERROR: ID documento inválido: " . $documentId);
    header('Location: inbox.php?error=invalid_document_id');
    exit();
}

error_log("DELETE.PHP - ID documento válido: " . $documentId);

try {
    // Obtener información del documento y verificar permisos
    if ($currentUser['role'] === 'admin') {
        error_log("DELETE.PHP - Usuario es admin, puede eliminar cualquier documento");
        
        $query = "SELECT d.*, c.name as company_name, u.first_name, u.last_name 
                  FROM documents d
                  LEFT JOIN companies c ON d.company_id = c.id
                  LEFT JOIN users u ON d.user_id = u.id
                  WHERE d.id = :id AND d.status = 'active'";
        $params = ['id' => $documentId];
    } else {
        error_log("DELETE.PHP - Usuario normal, verificando permisos");
        
        $query = "SELECT d.*, c.name as company_name, u.first_name, u.last_name 
                  FROM documents d
                  LEFT JOIN companies c ON d.company_id = c.id
                  LEFT JOIN users u ON d.user_id = u.id
                  WHERE d.id = :id AND d.company_id = :company_id AND d.status = 'active'";
        $params = [
            'id' => $documentId, 
            'company_id' => $currentUser['company_id']
        ];
        
        // Verificar si es dueño del documento O si es admin
        $document_check = fetchOne($query, $params);
        if (!$document_check) {
            // Si no pertenece a su empresa, verificar si es dueño
            $query = "SELECT d.*, c.name as company_name, u.first_name, u.last_name 
                      FROM documents d
                      LEFT JOIN companies c ON d.company_id = c.id
                      LEFT JOIN users u ON d.user_id = u.id
                      WHERE d.id = :id AND d.user_id = :user_id AND d.status = 'active'";
            $params = [
                'id' => $documentId, 
                'user_id' => $currentUser['id']
            ];
        }
    }

    $document = fetchOne($query, $params);

    if (!$document) {
        error_log("DELETE.PHP - ERROR: Documento no encontrado o sin permisos");
        header('Location: inbox.php?error=document_not_found');
        exit();
    }

    error_log("DELETE.PHP - Documento encontrado: " . $document['name']);

    // REPARACIÓN: Verificar archivo físico con múltiples rutas posibles
    $possiblePaths = [
        '../../' . $document['file_path'],                    // Ruta original
        $document['file_path'],                               // Ruta directa
        '../../uploads/documents/' . basename($document['file_path']) // Ruta reconstruida
    ];
    
    $filePath = null;
    $fileExists = false;
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $filePath = $path;
            $fileExists = true;
            error_log("DELETE.PHP - Archivo encontrado en: " . $path);
            break;
        }
    }
    
    if (!$fileExists) {
        error_log("DELETE.PHP - WARNING: Archivo físico no encontrado en ninguna ruta");
    }

    // Crear conexión para transacción
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        error_log("DELETE.PHP - ERROR: No se pudo conectar a la base de datos");
        throw new Exception('Error de conexión a la base de datos');
    }

    // Iniciar transacción
    $conn->beginTransaction();
    
    try {
        error_log("DELETE.PHP - Marcando documento como eliminado");
        
        // Marcar documento como eliminado (soft delete)
        $updateQuery = "UPDATE documents SET 
                        status = 'deleted', 
                        updated_at = NOW()";
        
        // Agregar campos adicionales si existen
        $checkDeleted = $conn->query("SHOW COLUMNS FROM documents LIKE 'deleted_at'");
        if ($checkDeleted->rowCount() > 0) {
            $updateQuery .= ", deleted_at = NOW(), deleted_by = :deleted_by";
            $updateParams = ['deleted_by' => $currentUser['id'], 'id' => $documentId];
        } else {
            $updateParams = ['id' => $documentId];
        }
        
        $updateQuery .= " WHERE id = :id";
        
        $updateStmt = $conn->prepare($updateQuery);
        $updateResult = $updateStmt->execute($updateParams);
        
        if (!$updateResult) {
            error_log("DELETE.PHP - ERROR: No se pudo actualizar el documento");
            throw new Exception('Error al marcar documento como eliminado');
        }
        
        error_log("DELETE.PHP - Documento marcado como eliminado exitosamente");
        
        // REPARACIÓN: Manejar archivo físico de manera segura
        if ($fileExists && $filePath) {
            error_log("DELETE.PHP - Procesando archivo físico");
            
            // Crear directorio de eliminados
            $deletedDir = '../../uploads/deleted/';
            if (!is_dir($deletedDir)) {
                if (mkdir($deletedDir, 0755, true)) {
                    error_log("DELETE.PHP - Creado directorio: " . $deletedDir);
                }
            }
            
            // Generar nombre único para archivo eliminado
            $timestamp = time();
            $originalBasename = basename($document['file_path']);
            $deletedFileName = $documentId . '_' . $timestamp . '_' . $originalBasename;
            $deletedFilePath = $deletedDir . $deletedFileName;
            
            error_log("DELETE.PHP - Moviendo archivo de: " . $filePath . " a: " . $deletedFilePath);
            
            // OPCIÓN 1: Mover archivo (recomendado para recuperación)
            if (rename($filePath, $deletedFilePath)) {
                error_log("DELETE.PHP - Archivo movido exitosamente a carpeta de eliminados");
                
                // Actualizar ruta en base de datos si es posible
                if ($checkDeleted->rowCount() > 0) {
                    $updatePathQuery = "UPDATE documents SET file_path = :new_path WHERE id = :id";
                    $updatePathStmt = $conn->prepare($updatePathQuery);
                    $updatePathStmt->execute([
                        'new_path' => 'uploads/deleted/' . $deletedFileName,
                        'id' => $documentId
                    ]);
                    error_log("DELETE.PHP - Ruta actualizada en BD");
                }
            } 
            // OPCIÓN 2: Eliminar completamente si mover falla
            else if (unlink($filePath)) {
                error_log("DELETE.PHP - Archivo eliminado completamente del servidor");
            } else {
                error_log("DELETE.PHP - WARNING: No se pudo mover ni eliminar el archivo físico");
            }
        }
        
        // Registrar actividad
        $activityQuery = "INSERT INTO activity_logs 
                         (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at) 
                         VALUES (:user_id, :action, :table_name, :record_id, :description, :ip_address, :user_agent, NOW())";
        
        $activityStmt = $conn->prepare($activityQuery);
        $activityStmt->execute([
            'user_id' => $currentUser['id'],
            'action' => 'delete',
            'table_name' => 'documents',
            'record_id' => $documentId,
            'description' => 'Usuario eliminó documento: ' . $document['name'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Confirmar transacción
        $conn->commit();
        error_log("DELETE.PHP - Transacción confirmada exitosamente");
        
        // Redirigir con mensaje de éxito
        $redirectUrl = 'inbox.php?success=document_deleted&name=' . urlencode($document['name']);
        error_log("DELETE.PHP - Redirigiendo a: " . $redirectUrl);
        
        header('Location: ' . $redirectUrl);
        exit();
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        error_log("DELETE.PHP - ERROR en transacción: " . $e->getMessage());
        throw $e;
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("DELETE.PHP - ERROR GENERAL: " . $e->getMessage());
    
    // Redirigir con mensaje de error
    header('Location: inbox.php?error=delete_failed');
    exit();
}
?>