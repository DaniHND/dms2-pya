<?php
/**
 * delete.php - Funcionalidad de eliminación de documentos corregida
 * Ubicación: modules/documents/delete.php
 */

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/group_permissions.php';

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inbox.php?error=invalid_request');
    exit;
}

// Verificar document_id
if (!isset($_POST['document_id']) || !is_numeric($_POST['document_id'])) {
    header('Location: inbox.php?error=invalid_document_id');
    exit;
}

$documentId = intval($_POST['document_id']);
$returnPath = $_POST['return_path'] ?? '';

error_log("DELETE.PHP - Eliminando documento ID: $documentId para usuario: " . $currentUser['username']);

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // ===== VERIFICACIÓN DE PERMISOS =====
    $hasDeletePermission = false;

    if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'super_admin') {
        $hasDeletePermission = true;
    } else {
        // Verificar permisos de grupo
        $groupPermissions = getUserGroupPermissions($currentUser['id']);
        if ($groupPermissions['has_groups']) {
            $permissions = $groupPermissions['permissions'];
            $hasDeletePermission = isset($permissions['delete_files']) && $permissions['delete_files'] === true;
        }
    }

    if (!$hasDeletePermission) {
        error_log("DELETE.PHP - ERROR: Usuario sin permisos de eliminación");
        header('Location: inbox.php?error=no_delete_permission&path=' . urlencode($returnPath));
        exit;
    }

    // ===== OBTENER DOCUMENTO =====
    $query = "SELECT d.*, c.name as company_name, u.first_name, u.last_name
              FROM documents d
              LEFT JOIN companies c ON d.company_id = c.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE d.id = ? AND d.status = 'active'";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        error_log("DELETE.PHP - ERROR: Documento no encontrado o no activo");
        header('Location: inbox.php?error=document_not_found&path=' . urlencode($returnPath));
        exit;
    }

    // ===== VERIFICAR PROPIEDAD DEL DOCUMENTO =====
    if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'super_admin' && $document['user_id'] != $currentUser['id']) {
        error_log("DELETE.PHP - ERROR: Usuario no es propietario del documento");
        header('Location: inbox.php?error=not_document_owner&path=' . urlencode($returnPath));
        exit;
    }

    // ===== ELIMINAR DOCUMENTO =====
    $pdo->beginTransaction();

    try {
        // Verificar si existe columna deleted_at
        $checkColumns = $pdo->query("SHOW COLUMNS FROM documents LIKE 'deleted_at'");
        $hasDeletedAtColumn = $checkColumns->rowCount() > 0;

        // HARD DELETE - Eliminar completamente de la base de datos
        $updateQuery = "DELETE FROM documents WHERE id = ?";
        $updateParams = [$documentId];

        $stmt = $pdo->prepare($updateQuery);
        $result = $stmt->execute($updateParams);

        if (!$result) {
            throw new Exception('Error al marcar documento como eliminado');
        }

        // ===== MANEJAR ARCHIVO FÍSICO =====
        // ===== ELIMINAR ARCHIVO FÍSICO COMPLETAMENTE =====
        $filePath = '../../' . $document['file_path'];
        
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                error_log("DELETE.PHP - Archivo eliminado completamente del servidor");
            } else {
                error_log("DELETE.PHP - Warning: No se pudo eliminar el archivo físico");
            }
        }

        // ===== REGISTRAR ACTIVIDAD =====
        try {
            $logQuery = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
                         VALUES (?, ?, ?, ?, ?, NOW())";
            $logStmt = $pdo->prepare($logQuery);
            $logStmt->execute([
                $currentUser['id'],
                'document_deleted',
                'documents',
                $documentId,
                "Eliminó documento: {$document['name']} ({$document['company_name']})"
            ]);
        } catch (Exception $e) {
            // Log de actividad falló, pero no cancelar la eliminación
            error_log("DELETE.PHP - Warning: No se pudo registrar actividad: " . $e->getMessage());
        }

        // Confirmar transacción
        $pdo->commit();
        error_log("DELETE.PHP - Documento eliminado exitosamente");

        // ===== REDIRECCIÓN =====
        $redirectUrl = 'inbox.php?success=document_deleted';
        
        if (!empty($returnPath)) {
            $redirectUrl .= '&path=' . urlencode($returnPath);
        }
        
        $redirectUrl .= '&deleted_name=' . urlencode($document['name']);

        header('Location: ' . $redirectUrl);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("DELETE.PHP - Error en transacción: " . $e->getMessage());
        header('Location: inbox.php?error=delete_failed&path=' . urlencode($returnPath));
        exit;
    }

} catch (Exception $e) {
    error_log("DELETE.PHP - Error general: " . $e->getMessage());
    header('Location: inbox.php?error=system_error&path=' . urlencode($returnPath));
    exit;
}
?>