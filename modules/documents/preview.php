<?php
// modules/documents/preview.php - DEBUG COMPLETO
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en pantalla, solo en JSON

header('Content-Type: application/json');

$debug_info = [];
$debug_info['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
$debug_info['get_params'] = $_GET;
$debug_info['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$debug_info['timestamp'] = date('Y-m-d H:i:s');

try {
    $debug_info['step'] = 'iniciando';
    
    // Verificar archivos requeridos
    $sessionFile = '../../config/session.php';
    $databaseFile = '../../config/database.php';
    $groupPermissionsFile = '../../includes/group_permissions.php';
    
    $debug_info['files_check'] = [
        'session.php' => file_exists($sessionFile),
        'database.php' => file_exists($databaseFile),
        'group_permissions.php' => file_exists($groupPermissionsFile)
    ];
    
    if (!file_exists($sessionFile)) {
        throw new Exception("session.php no encontrado");
    }
    
    if (!file_exists($databaseFile)) {
        throw new Exception("database.php no encontrado");
    }
    
    $debug_info['step'] = 'incluyendo archivos';
    require_once $sessionFile;
    require_once $databaseFile;
    
    if (file_exists($groupPermissionsFile)) {
        require_once $groupPermissionsFile;
    }
    
    $debug_info['step'] = 'verificando clases';
    
    if (!class_exists('SessionManager')) {
        throw new Exception("Clase SessionManager no encontrada");
    }
    
    $debug_info['step'] = 'verificando sesión';
    
    if (!SessionManager::isLoggedIn()) {
        echo json_encode([
            'success' => false, 
            'message' => 'No autorizado',
            'debug' => $debug_info
        ]);
        exit;
    }

    $debug_info['step'] = 'obteniendo usuario';
    $currentUser = SessionManager::getCurrentUser();
    
    if (!$currentUser) {
        throw new Exception("No se pudo obtener el usuario actual");
    }
    
    $debug_info['current_user'] = [
        'id' => $currentUser['id'] ?? 'unknown',
        'role' => $currentUser['role'] ?? 'unknown'
    ];
    
    $debug_info['step'] = 'validando documento ID';
    $documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$documentId) {
        echo json_encode([
            'success' => false, 
            'message' => 'ID de documento requerido',
            'debug' => $debug_info
        ]);
        exit;
    }
    
    $debug_info['document_id'] = $documentId;
    $debug_info['step'] = 'conectando base de datos';
    
    if (!class_exists('Database')) {
        throw new Exception("Clase Database no encontrada");
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    $debug_info['step'] = 'ejecutando query';
    
    // Query simplificada
    $query = "SELECT id, name, file_path, file_size, mime_type, description, created_at FROM documents WHERE id = ? AND status = 'active'";
    
    $stmt = $pdo->prepare($query);
    if (!$stmt) {
        throw new Exception("Error al preparar query: " . implode(', ', $pdo->errorInfo()));
    }
    
    $result = $stmt->execute([$documentId]);
    if (!$result) {
        throw new Exception("Error al ejecutar query: " . implode(', ', $stmt->errorInfo()));
    }
    
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        echo json_encode([
            'success' => false, 
            'message' => 'Documento no encontrado',
            'debug' => $debug_info
        ]);
        exit;
    }
    
    $debug_info['document_found'] = true;
    $debug_info['step'] = 'verificando permisos';
    
    // Permisos simplificados para debug
    $canView = true;
    $canDownload = true;
    
    // Si es admin, siempre permitir
    if (isset($currentUser['role']) && $currentUser['role'] === 'admin') {
        $canView = true;
        $canDownload = true;
        $debug_info['admin_override'] = true;
    }
    
    // Intentar usar getUserGroupPermissions si existe
    if (function_exists('getUserGroupPermissions')) {
        try {
            $userPermissions = getUserGroupPermissions($currentUser['id']);
            $canView = $userPermissions['permissions']['view'] ?? $canView;
            $canDownload = $userPermissions['permissions']['download'] ?? $canDownload;
            $debug_info['group_permissions_used'] = true;
        } catch (Exception $e) {
            $debug_info['group_permissions_error'] = $e->getMessage();
        }
    } else {
        $debug_info['group_permissions_function'] = 'not found';
    }
    
    if (!$canView) {
        echo json_encode([
            'success' => false, 
            'message' => 'Sin permisos para ver este documento',
            'debug' => $debug_info
        ]);
        exit;
    }
    
    $debug_info['step'] = 'determinando tipo de archivo';
    
    // Determinar tipo de archivo
    $fileType = 'other';
    if ($document['file_path']) {
        $extension = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $fileType = 'image';
        } elseif ($extension === 'pdf') {
            $fileType = 'pdf';
        } elseif (in_array($extension, ['txt', 'md'])) {
            $fileType = 'text';
        }
    }
    
    $debug_info['file_type'] = $fileType;
    $debug_info['step'] = 'preparando respuesta';
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'document' => [
            'id' => $document['id'],
            'name' => $document['name'],
            'file_path' => $document['file_path'],
            'file_size' => $document['file_size'],
            'mime_type' => $document['mime_type'] ?? '',
            'file_type' => $fileType,
            'document_type' => 'Debug Document',
            'company_name' => 'Debug Company',
            'department_name' => 'Debug Department',
            'description' => $document['description'],
            'created_at' => $document['created_at'],
            'uploaded_by_name' => 'Debug User'
        ],
        'permissions' => [
            'can_view' => $canView,
            'can_download' => $canDownload
        ],
        'debug' => $debug_info
    ]);

} catch (Exception $e) {
    $debug_info['error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    error_log("Error en preview.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor',
        'debug' => $debug_info
    ]);
}
?>