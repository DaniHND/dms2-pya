<?php
// modules/users/actions/get_user.php
// Obtener datos de un usuario para edición

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
    
    // Convertir a booleano
    $user['download_enabled'] = (bool)$user['download_enabled'];
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>