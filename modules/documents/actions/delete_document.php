<?php
/**
 * modules/documents/actions/delete_document.php
 * Eliminación de documentos protegida por permisos de grupos
 */

require_once '../../../config/session.php';
require_once '../../../config/database.php';
require_once '../../../includes/group_permissions.php';
require_once '../../../includes/permission_check.php';

header('Content-Type: application/json');

// Verificar sesión
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();

// Verificar permisos de grupos
try {
    requireActiveGroups();
    requireDeletePermission();
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Leer datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['document_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de documento requerido']);
    exit;
}

$documentId = (int)$data['document_id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Obtener información del documento
    $query = "
        SELECT d.id, d.name, d.original_name, d.file_path, d.company_id, 
               d.department_id, d.document_type_id, d.user_id,
               c.name as company_name, dep.name as department_name,
               dt.name as document_type_name,
               CONCAT(u.first_name, ' ', u.last_name) as uploaded_by
        FROM documents d
        INNER JOIN companies c ON d.company_id = c.id
        INNER JOIN departments dep ON d.department_id = dep.id
        INNER JOIN document_types dt ON d.document_type_id = dt.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.id = ? AND d.status = 'active'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
        exit;
    }
    
    // Verificar acceso completo al documento
    try {
        requireDocumentAccess($documentId);
    } catch (Exception $e) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    
    // Verificar que el usuario puede eliminar este documento específico
    // Solo puede eliminar sus propios documentos, excepto los administradores
    if ($document['user_id'] != $currentUser['id'] && $currentUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Solo puede eliminar sus propios documentos']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Marcar documento como eliminado (soft delete)
    $deleteQuery = "
        UPDATE documents SET 
            status = 'deleted',
            deleted_at = NOW(),
            deleted_by = ?
        WHERE id = ?
    ";
    
    $deleteStmt = $pdo->prepare($deleteQuery);
    $result = $deleteStmt->execute([$currentUser['id'], $documentId]);
    
    if (!$result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al eliminar documento']);
        exit;
    }
    
    // Registrar actividad
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $logStmt->execute([
        $currentUser['id'],
        'document_deleted',
        'documents',
        $documentId,
        "Documento eliminado: {$document['name']} ({$document['company_name']} - {$document['department_name']})"
    ]);
    
    // Eliminar relaciones en inbox si existen
    try {
        $inboxDeleteQuery = "UPDATE inbox_records SET status = 'deleted' WHERE document_id = ?";
        $inboxDeleteStmt = $pdo->prepare($inboxDeleteQuery);
        $inboxDeleteStmt->execute([$documentId]);
    } catch (Exception $e) {
        // Log pero no fallar por esto
        error_log("Warning: Could not update inbox records: " . $e->getMessage());
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Documento eliminado correctamente',
        'document_name' => $document['name'],
        'deleted_by' => $currentUser['first_name'] . ' ' . $currentUser['last_name']
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error PDO en delete_document.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error en delete_document.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor'
    ]);
}
?>