<?php
// modules/users/actions/delete_user.php
// Eliminar usuario - DMS2

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $userId = intval($_POST['user_id'] ?? 0);
    
    if ($userId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    // Verificar conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Verificar que el usuario existe
    $checkQuery = "SELECT id, first_name, last_name, username FROM users WHERE id = ? AND status != 'deleted'";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // No permitir que se elimine a sí mismo
    $currentUser = SessionManager::getCurrentUser();
    if ($userId == $currentUser['id']) {
        throw new Exception('No puedes eliminar tu propia cuenta');
    }
    
    // Verificar si es el último administrador
    $adminCountQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active' AND id != ?";
    $stmt = $pdo->prepare($adminCountQuery);
    $stmt->execute([$userId]);
    $adminCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $userRoleQuery = "SELECT role FROM users WHERE id = ?";
    $stmt = $pdo->prepare($userRoleQuery);
    $stmt->execute([$userId]);
    $userRole = $stmt->fetch(PDO::FETCH_ASSOC)['role'];
    
    if ($userRole === 'admin' && $adminCount == 0) {
        throw new Exception('No se puede eliminar el último administrador del sistema');
    }
    
    // Marcar como eliminado en lugar de eliminar físicamente
    $deleteQuery = "UPDATE users SET status = 'deleted', username = CONCAT(username, '_deleted_', UNIX_TIMESTAMP()), email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP()), updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($deleteQuery);
    $success = $stmt->execute([$userId]);
    
    if ($success) {
        // Registrar actividad
        logActivity(
            $currentUser['id'], 
            'user_deleted', 
            'users', 
            $userId, 
            "Usuario {$user['first_name']} {$user['last_name']} ({$user['username']}) eliminado del sistema"
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario eliminado correctamente'
        ]);
    } else {
        throw new Exception('Error al eliminar el usuario');
    }
    
} catch (Exception $e) {
    error_log("Error deleting user: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>