<?php
// modules/users/actions/toggle_user_status.php
// Cambiar estado de un usuario (activo/inactivo)

header('Content-Type: application/json');
require_once '../../../config/session.php';
require_once '../../../config/database.php';

try {
    SessionManager::requireLogin();
    SessionManager::requireRole('admin');
    
    $currentUser = SessionManager::getCurrentUser();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener datos
    $userId = intval($_POST['user_id'] ?? 0);
    $currentStatus = trim($_POST['current_status'] ?? '');
    
    if ($userId <= 0) {
        throw new Exception('ID de usuario no válido');
    }
    
    if (!in_array($currentStatus, ['active', 'inactive'])) {
        throw new Exception('Estado actual no válido');
    }
    
    // Verificar que el usuario existe
    $targetUser = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$targetUser) {
        throw new Exception('Usuario no encontrado');
    }
    
    // No permitir que un admin se desactive a sí mismo
    if ($userId == $currentUser['id']) {
        throw new Exception('No puedes cambiar tu propio estado');
    }
    
    // Verificar que no es el último admin activo
    if ($targetUser['role'] === 'admin' && $currentStatus === 'active') {
        $activeAdmins = fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'");
        if ($activeAdmins && $activeAdmins['count'] <= 1) {
            throw new Exception('No puedes desactivar el último administrador del sistema');
        }
    }
    
    // Determinar nuevo estado
    $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
    
    // Actualizar estado
    $stmt = executeQuery("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $userId]);
    
    if ($stmt) {
        // Registrar actividad
        $action = $newStatus === 'active' ? 'activated' : 'deactivated';
        logActivity($currentUser['id'], 'toggle_user_status', 'users', $userId, 
                   "Usuario {$action}: {$targetUser['first_name']} {$targetUser['last_name']} (@{$targetUser['username']})");
        
        echo json_encode([
            'success' => true,
            'message' => $newStatus === 'active' ? 'Usuario activado exitosamente' : 'Usuario desactivado exitosamente',
            'data' => [
                'user_id' => $userId,
                'old_status' => $currentStatus,
                'new_status' => $newStatus,
                'username' => $targetUser['username']
            ]
        ]);
    } else {
        throw new Exception('Error al actualizar el estado del usuario');
    }
    
} catch (Exception $e) {
    error_log("Error toggling user status: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>