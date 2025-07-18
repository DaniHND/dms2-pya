<?php
// modules/users/actions/get_user_details.php
// Obtener detalles completos de un usuario - DMS2

require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

SessionManager::requireLogin();
SessionManager::requireRole('admin');

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
    
    // Obtener datos básicos del usuario
    $query = "SELECT u.*, c.name as company_name 
              FROM users u 
              LEFT JOIN companies c ON u.company_id = c.id 
              WHERE u.id = ?";
    
    $user = fetchOne($query, [$userId]);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Remover campos sensibles
    unset($user['password']);
    
    // Obtener estadísticas del usuario
    $stats = [];
    
    // Total de actividades
    $result = fetchOne("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ?", [$userId]);
    $stats['total_activities'] = $result['total'] ?? 0;
    
    // Total de documentos subidos
    $result = fetchOne("SELECT COUNT(*) as total FROM documents WHERE user_id = ?", [$userId]);
    $stats['total_documents'] = $result['total'] ?? 0;
    
    // Total de descargas
    $result = fetchOne("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ? AND action = 'download'", [$userId]);
    $stats['total_downloads'] = $result['total'] ?? 0;
    
    // Días desde el último login
    $stats['days_since_last_login'] = 'N/A';
    if ($user['last_login']) {
        $lastLogin = new DateTime($user['last_login']);
        $now = new DateTime();
        $diff = $now->diff($lastLogin);
        $stats['days_since_last_login'] = $diff->days;
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>