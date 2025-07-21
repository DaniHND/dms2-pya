<?php
// modules/users/actions/get_user.php
// Obtener datos de un usuario para edición - DMS2 (CORREGIDO)

require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

// Verificar sesión y permisos
try {
    SessionManager::requireLogin();
    SessionManager::requireRole('admin');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $userId = intval($_GET['id'] ?? 0);
    
    if ($userId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    // Verificar conexión a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    $query = "SELECT u.*, c.name as company_name 
              FROM users u 
              LEFT JOIN companies c ON u.company_id = c.id 
              WHERE u.id = ? AND u.status != 'deleted'";
    
    $user = fetchOne($query, [$userId]);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Remover campos sensibles
    unset($user['password']);
    
    // Asegurar que los campos booleanos se conviertan correctamente
    $user['download_enabled'] = (bool) $user['download_enabled'];
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log("Error getting user: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>