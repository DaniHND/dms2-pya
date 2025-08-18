<?php
// modules/documents/api/move_document.php
require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Método no permitido',
        'debug' => 'Solo POST permitido'
    ]);
    exit;
}

// Verificar sesión
if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();

try {
    // Leer datos JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Datos JSON inválidos');
    }
    
    $documentId = (int)($data['document_id'] ?? 0);
    $folderId = (int)($data['folder_id'] ?? 0);
    
    if ($documentId <= 0 || $folderId <= 0) {
        throw new Exception("IDs inválidos: documento=$documentId, carpeta=$folderId");
    }
    
    // Conectar BD
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar documento
    $docStmt = $pdo->prepare("SELECT id, name, company_id, department_id, folder_id FROM documents WHERE id = ? AND status = 'active'");
    $docStmt->execute([$documentId]);
    $document = $docStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception("Documento ID $documentId no encontrado");
    }
    
    // Verificar carpeta
    $folderStmt = $pdo->prepare("SELECT id, name, company_id, department_id FROM document_folders WHERE id = ? AND is_active = 1");
    $folderStmt->execute([$folderId]);
    $folder = $folderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder) {
        throw new Exception("Carpeta ID $folderId no encontrada");
    }
    
    // Verificar compatibilidad
    if ($document['company_id'] != $folder['company_id']) {
        throw new Exception('Documento y carpeta de diferentes empresas');
    }
    
    if ($document['department_id'] != $folder['department_id']) {
        throw new Exception('Documento y carpeta de diferentes departamentos');
    }
    
    // Verificar si ya está en esa carpeta
    if ($document['folder_id'] == $folderId) {
        throw new Exception('El documento ya está en esa carpeta');
    }
    
    // Actualizar
    $updateStmt = $pdo->prepare("UPDATE documents SET folder_id = ?, updated_at = NOW() WHERE id = ?");
    $success = $updateStmt->execute([$folderId, $documentId]);
    
    if (!$success) {
        throw new Exception('Error al actualizar la base de datos');
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => "Documento '{$document['name']}' movido a '{$folder['name']}'",
        'data' => [
            'document_id' => $documentId,
            'document_name' => $document['name'],
            'folder_id' => $folderId,
            'folder_name' => $folder['name'],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
?>