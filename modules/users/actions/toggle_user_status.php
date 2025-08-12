<?php
// modules/users/actions/toggle_user_status.php
// Cambiar estado de usuario - DMS2

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
    $newStatus = $_POST['new_status'] ?? '';
    
    if ($userId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    if (!in_array($newStatus, ['active', 'inactive', 'suspended'])) {
        throw new Exception('Estado inválido');
    }
    
    // Verificar conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Verificar que el usuario existe
    $checkQuery = "SELECT id, first_name, last_name, status FROM users WHERE id = ? AND status != 'deleted'";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // No permitir que se desactive a sí mismo
    $currentUser = SessionManager::getCurrentUser();
    if ($userId == $currentUser['id'] && $newStatus !== 'active') {
        throw new Exception('No puedes cambiar tu propio estado');
    }
    
    // Actualizar estado
    $updateQuery = "UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($updateQuery);
    $success = $stmt->execute([$newStatus, $userId]);
    
    if ($success) {
        // Registrar actividad
        logActivity(
            $currentUser['id'], 
            'user_status_changed', 
            'users', 
            $userId, 
            "Estado del usuario {$user['first_name']} {$user['last_name']} cambiado de {$user['status']} a {$newStatus}"
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Estado del usuario actualizado correctamente',
            'new_status' => $newStatus
        ]);
    } else {
        throw new Exception('Error al actualizar el estado del usuario');
    }
    
} catch (Exception $e) {
    error_log("Error toggling user status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>