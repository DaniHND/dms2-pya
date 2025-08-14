<?php
// Capturar cualquier output que pueda causar problemas
ob_start();

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/group_permissions.php';

// Limpiar cualquier output previo
ob_clean();

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Leer input
    $rawInput = file_get_contents('php://input');
    
    if (empty($rawInput)) {
        echo json_encode(['success' => false, 'message' => 'Sin datos de entrada']);
        exit;
    }
    
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
        exit;
    }
    
    $documentId = isset($input['document_id']) ? (int)$input['document_id'] : 0;
    $targetPath = isset($input['target_path']) ? trim($input['target_path']) : '';
    
    if (!$documentId) {
        echo json_encode(['success' => false, 'message' => 'ID de documento inválido']);
        exit;
    }

    $database = new Database();
    $pdo = $database->getConnection();

    // Verificar que el documento existe
    $checkQuery = "SELECT * FROM documents WHERE id = ? AND status = 'active'";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
        exit;
    }

    // Verificar permisos básicos - más simple
    if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'super_admin') {
        // Para usuarios normales, verificar permisos básicos
        try {
            $userPermissions = getUserGroupPermissions($currentUser['id']);
            if (!$userPermissions['permissions']['edit_files']) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos para mover documentos']);
                exit;
            }
        } catch (Exception $permError) {
            // Si hay error con permisos, permitir solo a admin
            echo json_encode(['success' => false, 'message' => 'Error verificando permisos']);
            exit;
        }
    }

    // Parsear path destino
    $pathParts = array_filter(explode('/', trim($targetPath, '/')));
    $newCompanyId = isset($pathParts[0]) ? (int)$pathParts[0] : $document['company_id'];
    $newDepartmentId = isset($pathParts[1]) ? (int)$pathParts[1] : $document['department_id'];
    $newFolderId = null;

    // Si hay carpeta en el path
    if (isset($pathParts[2]) && strpos($pathParts[2], 'folder_') === 0) {
        $newFolderId = (int)substr($pathParts[2], 7);
    }

    // Actualizar documento
    $updateQuery = "UPDATE documents SET 
                    company_id = ?, 
                    department_id = ?, 
                    folder_id = ?,
                    updated_at = NOW() 
                    WHERE id = ?";
    
    $stmt = $pdo->prepare($updateQuery);
    $success = $stmt->execute([$newCompanyId, $newDepartmentId, $newFolderId, $documentId]);

    if ($success) {
        // Log básico sin dependencias externas
        try {
            $description = "Documento movido: " . $document['name'];
            $logQuery = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
                         VALUES (?, 'move', 'documents', ?, ?, NOW())";
            $logStmt = $pdo->prepare($logQuery);
            $logStmt->execute([$currentUser['id'], $documentId, $description]);
        } catch (Exception $logError) {
            // Si el log falla, continuar igual
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Documento movido exitosamente',
            'document_id' => $documentId,
            'new_path' => $targetPath
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar documento en BD']);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// Terminar output buffering
ob_end_flush();
?>