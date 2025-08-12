<?php
// modules/documents/log_activity.php
// Registro de actividades de documentos - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Obtener el contenido JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['action']) || !isset($data['document_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit();
}

$action = $data['action'];
$documentId = intval($data['document_id']);

try {
    // Validar acción
    $validActions = ['view', 'download', 'share'];
    if (!in_array($action, $validActions)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit();
    }

    // Verificar que el documento existe y el usuario tiene acceso
    $query = "SELECT d.name, d.company_id 
              FROM documents d 
              WHERE d.id = :id AND d.status = 'active'";
    
    $params = ['id' => $documentId];
    
    // Si no es admin, verificar que el documento pertenezca a su empresa
    if ($currentUser['role'] !== 'admin') {
        $query .= " AND d.company_id = :company_id";
        $params['company_id'] = $currentUser['company_id'];
    }
    
    $document = fetchOne($query, $params);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['error' => 'Document not found']);
        exit();
    }

    // Crear descripción de la actividad
    $descriptions = [
        'view' => 'Usuario visualizó el documento: ' . $document['name'],
        'download' => 'Usuario descargó el documento: ' . $document['name'],
        'share' => 'Usuario compartió el documento: ' . $document['name']
    ];
    
    $description = $descriptions[$action] ?? "Usuario realizó acción '$action' en el documento: " . $document['name'];

    // Registrar actividad
    logActivity($currentUser['id'], $action, 'document', $documentId, $description);

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Activity logged successfully'
    ]);

} catch (Exception $e) {
    error_log("Error logging activity: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>