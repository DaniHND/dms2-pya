<?php
/*
 * modules/folders/api/move_document.php
 * API para mover documentos a carpetas via drag & drop
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

require_once '../../../config/session.php';
require_once '../../../config/database.php';

// Verificar sesión
try {
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);
$documentId = $input['document_id'] ?? null;
$folderId = $input['folder_id'] ?? null;

// Validar entrada
if (!$documentId || !is_numeric($documentId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de documento inválido']);
    exit;
}

// folder_id puede ser null para mover a "sin carpeta"
if ($folderId !== null && !is_numeric($folderId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de carpeta inválido']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // ==========================================
    // VERIFICAR PERMISOS DEL DOCUMENTO
    // ==========================================
    $documentQuery = "
        SELECT d.*, c.name as company_name, dep.name as department_name
        FROM documents d
        LEFT JOIN companies c ON d.company_id = c.id
        LEFT JOIN departments dep ON d.department_id = dep.id
        WHERE d.id = :document_id AND d.status = 'active'
    ";
    
    $stmt = $pdo->prepare($documentQuery);
    $stmt->execute(['document_id' => $documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
        exit;
    }
    
    // Verificar permisos: Admin o mismo usuario o misma empresa
    $canEdit = false;
    
    if ($currentUser['role'] === 'admin') {
        $canEdit = true;
    } elseif ($document['user_id'] == $currentUser['id']) {
        $canEdit = true;
    } elseif ($document['company_id'] == $currentUser['company_id']) {
        $canEdit = true; // Mismo company puede mover documentos
    }
    
    if (!$canEdit) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para mover este documento']);
        exit;
    }
    
    // ==========================================
    // VERIFICAR CARPETA DESTINO (si no es null)
    // ==========================================
    if ($folderId !== null) {
        $folderQuery = "
            SELECT f.*, c.name as company_name, d.name as department_name
            FROM document_folders f
            LEFT JOIN companies c ON f.company_id = c.id
            LEFT JOIN departments d ON f.department_id = d.id
            WHERE f.id = :folder_id AND f.is_active = 1
        ";
        
        $stmt = $pdo->prepare($folderQuery);
        $stmt->execute(['folder_id' => $folderId]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$folder) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Carpeta no encontrada']);
            exit;
        }
        
        // Verificar permisos de carpeta: Admin o misma empresa
        $canAccessFolder = false;
        
        if ($currentUser['role'] === 'admin') {
            $canAccessFolder = true;
        } elseif ($folder['company_id'] == $currentUser['company_id']) {
            $canAccessFolder = true;
        }
        
        if (!$canAccessFolder) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para acceder a esta carpeta']);
            exit;
        }
        
        // Verificar compatibilidad empresa/departamento
        if ($document['company_id'] != $folder['company_id']) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'No puedes mover documentos entre diferentes empresas'
            ]);
            exit;
        }
    }
    
    // ==========================================
    // MOVER DOCUMENTO
    // ==========================================
    $updateQuery = "UPDATE documents SET folder_id = :folder_id, updated_at = CURRENT_TIMESTAMP WHERE id = :document_id";
    $stmt = $pdo->prepare($updateQuery);
    
    $updateParams = [
        'folder_id' => $folderId, // null es válido para "sin carpeta"
        'document_id' => $documentId
    ];
    
    if ($stmt->execute($updateParams)) {
        // ==========================================
        // REGISTRAR ACTIVIDAD
        // ==========================================
        try {
            $activityQuery = "
                INSERT INTO activity_logs (user_id, action, description, document_id, created_at)
                VALUES (:user_id, :action, :description, :document_id, NOW())
            ";
            
            $folderName = $folderId ? ($folder['name'] ?? 'Carpeta desconocida') : 'Sin carpeta';
            $description = "Movió documento '{$document['name']}' a: $folderName";
            
            $activityStmt = $pdo->prepare($activityQuery);
            $activityStmt->execute([
                'user_id' => $currentUser['id'],
                'action' => 'document_moved',
                'description' => $description,
                'document_id' => $documentId
            ]);
        } catch (Exception $e) {
            // Error en log no es crítico, continuar
            error_log("Error registrando actividad: " . $e->getMessage());
        }
        
        // ==========================================
        // RESPUESTA EXITOSA
        // ==========================================
        $response = [
            'success' => true,
            'message' => 'Documento movido exitosamente',
            'data' => [
                'document_id' => $documentId,
                'folder_id' => $folderId,
                'document_name' => $document['name'],
                'folder_name' => $folderId ? ($folder['name'] ?? null) : null,
                'moved_by' => trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))
            ]
        ];
        
        echo json_encode($response);
        
    } else {
        throw new Exception("Error actualizando documento en la base de datos");
    }
    
} catch (Exception $e) {
    error_log("Error en move_document.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>