<?php
/**
 * modules/documents/actions/download_document.php
 * Descarga de documentos protegida por permisos de grupos
 */

require_once '../../../config/session.php';
require_once '../../../config/database.php';
require_once '../../../includes/group_permissions.php';
require_once '../../../includes/permission_check.php';

// Verificar sesión
SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Verificar permisos de grupos
requireActiveGroups();
requireDownloadPermission();

$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$documentId) {
    http_response_code(400);
    die('ID de documento requerido');
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Obtener información del documento con verificación de acceso
    $query = "
        SELECT d.id, d.name, d.original_name, d.file_path, d.file_size, d.mime_type,
               d.company_id, d.department_id, d.document_type_id, d.user_id,
               c.name as company_name, dep.name as department_name,
               dt.name as document_type_name
        FROM documents d
        INNER JOIN companies c ON d.company_id = c.id
        INNER JOIN departments dep ON d.department_id = dep.id
        INNER JOIN document_types dt ON d.document_type_id = dt.id
        WHERE d.id = ? AND d.status = 'active'
        AND c.status = 'active' AND dep.status = 'active'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        die('Documento no encontrado');
    }
    
    // Verificar acceso completo al documento
    requireDocumentAccess($documentId);
    
    // Verificar que el archivo existe físicamente
    $projectRoot = dirname(dirname(dirname(__DIR__)));
    $fullPath = $projectRoot . '/' . $document['file_path'];
    
    if (!file_exists($fullPath)) {
        http_response_code(404);
        die('Archivo no encontrado en el servidor');
    }
    
    // Registrar la descarga en el log de actividades
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([
            $currentUser['id'],
            'document_downloaded',
            'documents',
            $documentId,
            "Descarga de documento: {$document['name']} ({$document['company_name']} - {$document['department_name']})"
        ]);
    } catch (Exception $e) {
        // Log pero no fallar por esto
        error_log("Warning: Could not log download activity: " . $e->getMessage());
    }
    
    // Configurar headers para descarga
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
    header('Content-Length: ' . $document['file_size']);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    // Limpiar buffer de salida
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Enviar archivo
    readfile($fullPath);
    exit;
    
} catch (Exception $e) {
    error_log("Error en download_document.php: " . $e->getMessage());
    http_response_code(500);
    die('Error interno del servidor');
}
?>