<?php
// modules/documents/download.php
// Manejo de descargas de documentos - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inbox.php?error=invalid_request');
    exit();
}

// Verificar que se envió el ID del documento
if (!isset($_POST['document_id']) || !is_numeric($_POST['document_id'])) {
    header('Location: inbox.php?error=invalid_document');
    exit();
}

$documentId = intval($_POST['document_id']);

try {
    // Función para verificar permisos de descarga del usuario
    function canUserDownload($userId) {
        $query = "SELECT download_enabled FROM users WHERE id = :id";
        $result = fetchOne($query, ['id' => $userId]);
        return $result ? ($result['download_enabled'] ?? true) : false;
    }

    // Verificar permisos de descarga
    if (!canUserDownload($currentUser['id'])) {
        header('Location: inbox.php?error=download_disabled');
        exit();
    }

    // Obtener información del documento
    $query = "SELECT d.*, c.name as company_name 
              FROM documents d
              LEFT JOIN companies c ON d.company_id = c.id
              WHERE d.id = :id AND d.status = 'active'";
    
    $params = ['id' => $documentId];
    
    // Si no es admin, verificar que el documento pertenezca a su empresa
    if ($currentUser['role'] !== 'admin') {
        $query .= " AND d.company_id = :company_id";
        $params['company_id'] = $currentUser['company_id'];
    }
    
    $document = fetchOne($query, $params);
    
    if (!$document) {
        header('Location: inbox.php?error=document_not_found');
        exit();
    }

    // Construir ruta completa del archivo
    $filePath = '../../' . $document['file_path'];
    
    // Verificar que el archivo existe
    if (!file_exists($filePath)) {
        error_log("Archivo no encontrado: $filePath para documento ID: $documentId");
        header('Location: inbox.php?error=file_not_found');
        exit();
    }

    // Registrar actividad de descarga
    try {
        logActivity($currentUser['id'], 'download', 'document', $documentId, 'Usuario descargó el documento: ' . $document['name']);
    } catch (Exception $logError) {
        error_log("Error logging download activity: " . $logError->getMessage());
        // Continuar con la descarga aunque falle el log
    }

    // Obtener información del archivo
    $fileSize = filesize($filePath);
    $originalName = $document['original_name'] ?: $document['name'];
    $mimeType = $document['mime_type'] ?: 'application/octet-stream';

    // Limpiar cualquier salida previa
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Configurar headers para descarga
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . basename($originalName) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $fileSize);

    // Enviar archivo
    readfile($filePath);
    exit();

} catch (Exception $e) {
    error_log("Error en descarga de documento ID $documentId: " . $e->getMessage());
    header('Location: inbox.php?error=download_failed');
    exit();
}
?>