<?php
// modules/users/actions/get_user_details.php
// Obtener detalles completos de un usuario - DMS2

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
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Obtener información completa del usuario
    $userQuery = "SELECT u.*, c.name as company_name,
                  COALESCE(u.download_enabled, 1) as download_enabled,
                  (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id) as document_count,
                  (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id) as activity_count,
                  (SELECT MAX(al.created_at) FROM activity_logs al WHERE al.user_id = u.id AND al.action = 'login') as last_login
                  FROM users u 
                  LEFT JOIN companies c ON u.company_id = c.id 
                  WHERE u.id = ? AND u.status != 'deleted'";
    
    $stmt = $pdo->prepare($userQuery);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Remover campos sensibles
    unset($user['password']);
    
    // Convertir campos booleanos
    $user['download_enabled'] = (bool) $user['download_enabled'];
    
    // Formatear números
    $user['document_count'] = (int) $user['document_count'];
    $user['activity_count'] = (int) $user['activity_count'];
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log("Error getting user details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>