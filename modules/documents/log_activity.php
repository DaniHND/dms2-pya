<?php
// modules/documents/log_activity.php
// Endpoint para registrar actividades de documentos

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

// Solo aceptar POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit();
}

$currentUser = SessionManager::getCurrentUser();
$action = $input['action'] ?? '';
$documentId = $input['document_id'] ?? null;

// Validar acción
$validActions = ['view', 'download', 'share'];
if (!in_array($action, $validActions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Acción inválida']);
    exit();
}

// Validar document_id si es necesario
if ($documentId && !is_numeric($documentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de documento inválido']);
    exit();
}

// Verificar que el documento existe y el usuario tiene acceso
if ($documentId) {
    if ($currentUser['role'] === 'admin') {
        $query = "SELECT id FROM documents WHERE id = :id AND status = 'active'";
        $params = ['id' => $documentId];
    } else {
        $query = "SELECT id FROM documents WHERE id = :id AND company_id = :company_id AND status = 'active'";
        $params = ['id' => $documentId, 'company_id' => $currentUser['company_id']];
    }
    
    $document = fetchOne($query, $params);
    if (!$document) {
        http_response_code(404);
        echo json_encode(['error' => 'Documento no encontrado']);
        exit();
    }
}

// Generar descripción según la acción
$descriptions = [
    'view' => 'Usuario visualizó documento',
    'download' => 'Usuario descargó documento',
    'share' => 'Usuario compartió documento'
];

$description = $descriptions[$action] ?? 'Acción en documento';

// Registrar actividad
$success = logActivity(
    $currentUser['id'],
    $action,
    'documents',
    $documentId,
    $description
);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Actividad registrada']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Error al registrar actividad']);
}
?>