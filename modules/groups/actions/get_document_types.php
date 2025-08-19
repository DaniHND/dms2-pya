<?php
/*
 * modules/departments/actions/get_departments.php
 * API simple para obtener departamentos por empresa
 */

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/group_permissions.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();

try {
    // Obtener tipos de documentos permitidos
    $documentTypes = getUserAllowedDocumentTypes($currentUser['id']);

    echo json_encode([
        'success' => true,
        'document_types' => $documentTypes,
        'count' => count($documentTypes)
    ]);

} catch (Exception $e) {
    error_log("Error en get_document_types.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>