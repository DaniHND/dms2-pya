<?php
// modules/users/actions/get_user_details.php
// Obtener detalles completos de un usuario

header('Content-Type: application/json');
require_once '../../../config/session.php';
require_once '../../../config/database.php';

try {
    SessionManager::requireLogin();
    SessionManager::requireRole('admin');
    
    $userId = intval($_GET['id'] ?? 0);
    if ($userId <= 0) {
        throw new Exception('ID de usuario no válido');
    }
    
    // Obtener datos del usuario
    $user = fetchOne("
        SELECT u.*, c.name as company_name 
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        WHERE u.id = ?
    ", [$userId]);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Remover campos sensibles
    unset($user['password']);
    
    // Obtener estadísticas
    $stats = [];
    
    // Total de documentos subidos
    $result = fetchOne("SELECT COUNT(*) as total FROM documents WHERE user_id = ?", [$userId]);
    $stats['total_documents'] = $result['total'] ?? 0;
    
    // Total de actividades
    $result = fetchOne("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ?", [$userId]);
    $stats['total_activities'] = $result['total'] ?? 0;
    
    // Total de descargas
    $result = fetchOne("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ? AND action LIKE '%download%'", [$userId]);
    $stats['total_downloads'] = $result['total'] ?? 0;
    
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