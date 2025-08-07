<?php
/*
 * move_document.php
 * API para mover documentos a carpetas
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once '../../config/session.php';
require_once '../../config/database.php';

try {
    // Verificar sesión
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();
    
    // Obtener datos JSON del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
        exit;
    }
    
    $documentId = intval($input['document_id'] ?? 0);
    $folderId = intval($input['folder_id'] ?? 0);
    
    // Validar datos
    if ($documentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de documento inválido']);
        exit;
    }
    
    if ($folderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de carpeta inválido']);
        exit;
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar que el documento existe y que el usuario tiene permisos
    $docQuery = "
        SELECT d.id, d.name, d.company_id, d.department_id, d.user_id,
               c.name as company_name
        FROM documents d
        INNER JOIN companies c ON d.company_id = c.id
        WHERE d.id = ? AND d.status = 'active'
    ";
    $docStmt = $pdo->prepare($docQuery);
    $docStmt->execute([$documentId]);
    $document = $docStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
        exit;
    }
    
    // Verificar permisos: admin, dueño del documento, o miembro de grupos con permisos de edición
    $hasPermission = false;
    
    if ($currentUser['role'] === 'admin') {
        $hasPermission = true;
    } elseif ($document['user_id'] == $currentUser['id']) {
        $hasPermission = true;
    } else {
        // Verificar permisos de grupo
        $permQuery = "
            SELECT ug.module_permissions, ug.access_restrictions
            FROM user_groups ug
            INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
            WHERE ugm.user_id = ? AND ug.status = 'active'
        ";
        $permStmt = $pdo->prepare($permQuery);
        $permStmt->execute([$currentUser['id']]);
        $permissions = $permStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($permissions as $perm) {
            $permData = json_decode($perm['module_permissions'] ?: '{}', true);
            $restrictions = json_decode($perm['access_restrictions'] ?: '{}', true);
            
            // Verificar si tiene permiso de editar
            if ($permData['edit'] ?? false) {
                // Verificar restricciones de empresa
                $companyRestriction = $restrictions['companies'] ?? [];
                if (empty($companyRestriction) || in_array($document['company_id'], $companyRestriction)) {
                    $hasPermission = true;
                    break;
                }
            }
        }
    }
    
    if (!$hasPermission) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para mover este documento']);
        exit;
    }
    
    // Verificar que la carpeta existe y pertenece a la misma empresa
    $folderQuery = "
        SELECT f.id, f.name, f.company_id, f.department_id,
               d.name as department_name
        FROM document_folders f
        INNER JOIN departments d ON f.department_id = d.id
        WHERE f.id = ? AND f.is_active = 1
    ";
    $folderStmt = $pdo->prepare($folderQuery);
    $folderStmt->execute([$folderId]);
    $folder = $folderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder) {
        echo json_encode(['success' => false, 'message' => 'Carpeta no encontrada']);
        exit;
    }
    
    // Verificar que la carpeta pertenece a la misma empresa que el documento
    if ($folder['company_id'] != $document['company_id']) {
        echo json_encode(['success' => false, 'message' => 'No se puede mover el documento a una carpeta de otra empresa']);
        exit;
    }
    
    // Mover el documento a la carpeta
    $updateQuery = "
        UPDATE documents 
        SET folder_id = ?, 
            department_id = ?, 
            updated_at = NOW() 
        WHERE id = ?
    ";
    $updateStmt = $pdo->prepare($updateQuery);
    $result = $updateStmt->execute([$folderId, $folder['department_id'], $documentId]);
    
    if ($result) {
        // Log de actividad
        $logQuery = "
            INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
            VALUES (?, 'move', 'documents', ?, ?, NOW())
        ";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->execute([
            $currentUser['id'],
            $documentId,
            "Documento '{$document['name']}' movido a carpeta '{$folder['name']}' en {$folder['department_name']}"
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Documento movido exitosamente',
            'document_id' => $documentId,
            'folder_id' => $folderId,
            'folder_name' => $folder['name'],
            'department_name' => $folder['department_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al mover el documento']);
    }
    
} catch (Exception $e) {
    error_log("Error moving document: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>