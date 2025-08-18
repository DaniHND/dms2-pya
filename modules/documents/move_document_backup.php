<?php
/**
 * modules/documents/api/move_document.php
 * API para mover documentos entre carpetas - VERSIÓN FINAL
 */

// Headers para JSON y CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Método no permitido. Solo POST.',
        'received_method' => $_SERVER['REQUEST_METHOD']
    ]);
    exit;
}

try {
    // Determinar la ruta del proyecto automáticamente
    $currentDir = __DIR__;
    $projectRoot = dirname(dirname(dirname($currentDir)));
    
    // Rutas posibles para los archivos de configuración
    $possiblePaths = [
        $projectRoot . '/config',
        dirname($projectRoot) . '/config',
        $currentDir . '/../../../config',
        __DIR__ . '/../../../config'
    ];
    
    $configPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path . '/session.php') && file_exists($path . '/database.php')) {
            $configPath = $path;
            break;
        }
    }
    
    if (!$configPath) {
        throw new Exception('No se pudieron encontrar los archivos de configuración');
    }
    
    // Cargar archivos de configuración
    require_once $configPath . '/session.php';
    require_once $configPath . '/database.php';
    
    // Verificar sesión
    if (!SessionManager::isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Sesión requerida',
            'debug_info' => 'Usuario no autenticado'
        ]);
        exit;
    }
    
    $currentUser = SessionManager::getCurrentUser();
    if (!$currentUser || !isset($currentUser['id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Usuario no válido',
            'debug_info' => 'getCurrentUser() falló'
        ]);
        exit;
    }
    
    // Leer y validar datos JSON
    $input = file_get_contents('php://input');
    if (!$input) {
        throw new Exception('No se recibieron datos JSON en el request');
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }
    
    // Validar parámetros
    $documentId = isset($data['document_id']) ? (int)$data['document_id'] : 0;
    $folderId = isset($data['folder_id']) ? (int)$data['folder_id'] : 0;
    
    if ($documentId <= 0) {
        throw new Exception("ID de documento inválido: $documentId");
    }
    
    if ($folderId <= 0) {
        throw new Exception("ID de carpeta inválido: $folderId");
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error al conectar con la base de datos');
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // Verificar que el documento existe
    $docQuery = "SELECT id, name, company_id, department_id, folder_id, user_id, status 
                 FROM documents 
                 WHERE id = ? AND status = 'active'";
    $docStmt = $pdo->prepare($docQuery);
    $docStmt->execute([$documentId]);
    $document = $docStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception("Documento ID $documentId no encontrado o no disponible");
    }
    
    // Verificar que la carpeta destino existe
    $folderQuery = "SELECT id, name, company_id, department_id, is_active 
                    FROM document_folders 
                    WHERE id = ? AND is_active = 1";
    $folderStmt = $pdo->prepare($folderQuery);
    $folderStmt->execute([$folderId]);
    $folder = $folderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder) {
        throw new Exception("Carpeta ID $folderId no encontrada o no disponible");
    }
    
    // Verificar compatibilidad empresa/departamento
    if ($document['company_id'] != $folder['company_id']) {
        throw new Exception("No se puede mover a carpeta de diferente empresa (Doc: {$document['company_id']}, Carpeta: {$folder['company_id']})");
    }
    
    if ($document['department_id'] != $folder['department_id']) {
        throw new Exception("No se puede mover a carpeta de diferente departamento (Doc: {$document['department_id']}, Carpeta: {$folder['department_id']})");
    }
    
    // Verificar si no es movimiento redundante
    if ($document['folder_id'] == $folderId) {
        throw new Exception("El documento ya está en la carpeta '{$folder['name']}'");
    }
    
    // Actualizar ubicación del documento
    $updateQuery = "UPDATE documents 
                    SET folder_id = ?, updated_at = NOW() 
                    WHERE id = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateSuccess = $updateStmt->execute([$folderId, $documentId]);
    
    if (!$updateSuccess) {
        throw new Exception('Fallo al actualizar la ubicación del documento en la base de datos');
    }
    
    $rowsAffected = $updateStmt->rowCount();
    if ($rowsAffected === 0) {
        throw new Exception('No se actualizó ningún registro (posible problema de concurrencia)');
    }
    
    // Registrar actividad en log
    try {
        $logQuery = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logDescription = "Documento '{$document['name']}' (ID: $documentId) movido a carpeta '{$folder['name']}' (ID: $folderId) por usuario {$currentUser['id']}";
        $logStmt->execute([
            $currentUser['id'],
            'document_moved',
            'documents',
            $documentId,
            $logDescription
        ]);
    } catch (Exception $logError) {
        // Log de actividad falla, pero no debería parar el proceso
        error_log("Advertencia: No se pudo registrar en activity_logs: " . $logError->getMessage());
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => "Documento '{$document['name']}' movido exitosamente a '{$folder['name']}'",
        'data' => [
            'document_id' => $documentId,
            'document_name' => $document['name'],
            'folder_id' => $folderId,
            'folder_name' => $folder['name'],
            'moved_by' => $currentUser['id'],
            'moved_at' => date('Y-m-d H:i:s'),
            'rows_affected' => $rowsAffected
        ],
        'debug_info' => [
            'config_path_used' => $configPath,
            'user_id' => $currentUser['id'],
            'request_data' => $data
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback en caso de error
    if (isset($pdo) && $pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log detallado del error
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s'),
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    error_log("Error en move_document.php: " . json_encode($errorDetails));
    
    // Respuesta de error detallada
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al mover documento: ' . $e->getMessage(),
        'error_details' => $errorDetails,
        'debug_info' => [
            'php_version' => PHP_VERSION,
            'current_dir' => __DIR__,
            'config_paths_tried' => $possiblePaths ?? ['No intentadas aún']
        ]
    ]);
}
?>