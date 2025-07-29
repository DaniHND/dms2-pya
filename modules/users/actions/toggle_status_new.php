<?php
// modules/users/actions/toggle_status_new.php
// Acción para cambiar estado de usuario - con seguridad completa

header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

try {
    // Verificar autenticación
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }
    
    // Obtener usuario actual
    $currentUser = fetchOne("SELECT * FROM users WHERE id = ? AND status = 'active'", [$_SESSION['user_id']]);
    if (!$currentUser) {
        throw new Exception('Usuario no válido');
    }
    
    // Verificar permisos (solo admin puede cambiar estados)
    if ($currentUser['role'] !== 'admin') {
        throw new Exception('No tienes permisos para realizar esta acción');
    }
    
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener y validar datos
    $userId = intval($_POST['user_id'] ?? 0);
    $currentStatus = trim($_POST['current_status'] ?? '');
    
    if ($userId <= 0) {
        throw new Exception('ID de usuario no válido');
    }
    
    if (!in_array($currentStatus, ['active', 'inactive'])) {
        throw new Exception('Estado actual no válido');
    }
    
    // Verificar que el usuario a modificar existe
    $targetUser = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$targetUser) {
        throw new Exception('Usuario no encontrado');
    }
    
    // No permitir que un admin se desactive a sí mismo
    if ($userId == $currentUser['id']) {
        throw new Exception('No puedes cambiar tu propio estado');
    }
    
    // Verificar que es el último admin activo (si se va a desactivar un admin)
    if ($targetUser['role'] === 'admin' && $currentStatus === 'active') {
        $activeAdmins = fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'");
        if ($activeAdmins && $activeAdmins['count'] <= 1) {
            throw new Exception('No puedes desactivar el último administrador del sistema');
        }
    }
    
    // Determinar nuevo estado
    $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
    
    // Actualizar estado del usuario (sin updated_at si no existe la columna)
    $updateSql = "UPDATE users SET status = ? WHERE id = ?";
    $stmt = $pdo->prepare($updateSql);
    $result = $stmt->execute([$newStatus, $userId]);
    
    if ($result) {
        // Registrar actividad en el log
        $action = $newStatus === 'active' ? 'activated' : 'deactivated';
        $activityDescription = "Usuario {$action}: {$targetUser['first_name']} {$targetUser['last_name']} (@{$targetUser['username']})";
        
        // Intentar registrar actividad
        try {
            $logSql = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $logStmt = $pdo->prepare($logSql);
            $logStmt->execute([
                $currentUser['id'],
                'toggle_user_status',
                'users',
                $userId,
                $activityDescription,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $logError) {
            error_log("Error logging activity: " . $logError->getMessage());
        }
        
        // Si se desactivó un usuario, también cerrar sus sesiones activas (opcional)
        if ($newStatus === 'inactive') {
            try {
                // Si tienes una tabla de sesiones, eliminar las sesiones del usuario
                $sessionSql = "DELETE FROM user_sessions WHERE user_id = ?";
                $sessionStmt = $pdo->prepare($sessionSql);
                $sessionStmt->execute([$userId]);
            } catch (Exception $sessionError) {
                // No es crítico si esto falla
                error_log("Error removing user sessions: " . $sessionError->getMessage());
            }
        }
        
        // Respuesta exitosa
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
    // Log del error para debugging
    error_log("Error toggling user status: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
    
    // Respuesta de error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'TOGGLE_STATUS_ERROR'
    ]);
}
?>