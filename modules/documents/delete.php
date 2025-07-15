<?php
// modules/documents/delete.php
// Manejador para eliminar documentos - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Solo aceptar POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inbox.php?error=invalid_request');
    exit();
}

// Obtener ID del documento
$documentId = $_POST['document_id'] ?? '';

if (empty($documentId) || !is_numeric($documentId)) {
    header('Location: inbox.php?error=invalid_document_id');
    exit();
}

// Obtener información del documento y verificar permisos
if ($currentUser['role'] === 'admin') {
    // Admin puede eliminar cualquier documento
    $query = "SELECT d.*, c.name as company_name, u.first_name, u.last_name 
              FROM documents d
              LEFT JOIN companies c ON d.company_id = c.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE d.id = :id AND d.status = 'active'";
    $params = ['id' => $documentId];
} else {
    // Usuario normal: verificar que sea de su empresa Y que sea el propietario
    $query = "SELECT d.*, c.name as company_name, u.first_name, u.last_name 
              FROM documents d
              LEFT JOIN companies c ON d.company_id = c.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE d.id = :id AND d.company_id = :company_id AND d.user_id = :user_id AND d.status = 'active'";
    $params = [
        'id' => $documentId, 
        'company_id' => $currentUser['company_id'],
        'user_id' => $currentUser['id']
    ];
}

$document = fetchOne($query, $params);

if (!$document) {
    header('Location: inbox.php?error=document_not_found');
    exit();
}

// Verificar que el archivo físico existe
$filePath = '../../' . $document['file_path'];
$fileExists = file_exists($filePath);

try {
    // Iniciar transacción
    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();
    
    // Marcar documento como eliminado (soft delete)
    $updateQuery = "UPDATE documents SET status = 'deleted', deleted_at = NOW(), deleted_by = :deleted_by WHERE id = :id";
    $updateStmt = $conn->prepare($updateQuery);
    $updateResult = $updateStmt->execute([
        'deleted_by' => $currentUser['id'],
        'id' => $documentId
    ]);
    
    if (!$updateResult) {
        throw new Exception('Error al marcar documento como eliminado');
    }
    
    // Mover archivo físico a carpeta de eliminados (opcional)
    if ($fileExists) {
        $deletedDir = '../../uploads/deleted/';
        if (!is_dir($deletedDir)) {
            mkdir($deletedDir, 0755, true);
        }
        
        $deletedFileName = $documentId . '_' . time() . '_' . basename($document['file_path']);
        $deletedFilePath = $deletedDir . $deletedFileName;
        
        // Intentar mover el archivo
        if (rename($filePath, $deletedFilePath)) {
            // Actualizar la ruta en la base de datos
            $updatePathQuery = "UPDATE documents SET file_path = :new_path WHERE id = :id";
            $updatePathStmt = $conn->prepare($updatePathQuery);
            $updatePathStmt->execute([
                'new_path' => 'uploads/deleted/' . $deletedFileName,
                'id' => $documentId
            ]);
        }
        // Si no se puede mover, continuar (el documento queda marcado como eliminado)
    }
    
    // Registrar actividad
    $activityQuery = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address, user_agent) 
                      VALUES (:user_id, 'delete', 'documents', :record_id, :description, :ip_address, :user_agent)";
    $activityStmt = $conn->prepare($activityQuery);
    $activityStmt->execute([
        'user_id' => $currentUser['id'],
        'record_id' => $documentId,
        'description' => 'Usuario eliminó documento: ' . $document['name'],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Confirmar transacción
    $conn->commit();
    
    // Redirigir con mensaje de éxito
    header('Location: inbox.php?success=document_deleted&name=' . urlencode($document['name']));
    exit();
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log del error
    error_log("Error al eliminar documento ID $documentId: " . $e->getMessage());
    
    // Redirigir con mensaje de error
    header('Location: inbox.php?error=delete_failed');
    exit();
}
?>