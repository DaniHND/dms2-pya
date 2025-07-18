<?php
// modules/users/actions/toggle_user_status.php
// Cambiar estado de usuario (activo/inactivo) - DMS2 (CORREGIDO)

require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

SessionManager::requireLogin();
SessionManager::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $currentUser = SessionManager::getCurrentUser();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos inválidos');
    }
    
    $userId = intval($input['user_id'] ?? 0);
    $newStatus = $input['status'] ?? '';
    
    if ($userId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    if (!in_array($newStatus, ['active', 'inactive'])) {
        throw new Exception('Estado inválido');
    }
    
    // Verificar que el usuario existe
    $user = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // No permitir desactivar al propio usuario
    if ($userId == $currentUser['id'] && $newStatus === 'inactive') {
        throw new Exception('No puede desactivar su propia cuenta');
    }
    
    // Actualizar estado usando updateRecord
    $result = updateRecord('users', ['status' => $newStatus], 'id = :id', ['id' => $userId]);
    
    if ($result) {
        // Registrar actividad
        $action = $newStatus === 'active' ? 'activar' : 'desactivar';
        logActivity($currentUser['id'], 'toggle_user_status', 'users', $userId, 
                   "Cambió el estado del usuario {$user['first_name']} {$user['last_name']} a {$newStatus}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado exitosamente',
            'new_status' => $newStatus
        ]);
    } else {
        throw new Exception('Error al actualizar el estado');
    }
    
} catch (Exception $e) {
    error_log("Error toggling user status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>