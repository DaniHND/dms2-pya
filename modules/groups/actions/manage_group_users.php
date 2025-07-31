<?php
/*
 * modules/groups/actions/manage_group_users.php
 * Acción para agregar/remover usuarios de grupos
 */

require_once '../../../config/session.php';
require_once '../../../config/database.php';

// Configurar respuesta JSON
header('Content-Type: application/json');

try {
    // Verificar sesión y permisos
    SessionManager::requireRole('admin');
    $currentUser = SessionManager::getCurrentUser();
    
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener parámetros
    $groupId = $_POST['group_id'] ?? null;
    $userId = $_POST['user_id'] ?? null;
    $action = $_POST['action'] ?? null;
    
    // Validar parámetros
    if (empty($groupId) || !is_numeric($groupId)) {
        throw new Exception('ID de grupo inválido');
    }
    
    if (empty($userId) || !is_numeric($userId)) {
        throw new Exception('ID de usuario inválido');
    }
    
    if (!in_array($action, ['add', 'remove'])) {
        throw new Exception('Acción inválida');
    }
    
    // Obtener conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Verificar que el grupo existe
    $groupQuery = "SELECT id, name, status FROM user_groups WHERE id = ?";
    $stmt = $pdo->prepare($groupQuery);
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        throw new Exception('Grupo no encontrado');
    }
    
    // Verificar que el usuario existe y está activo
    $userQuery = "SELECT id, username, first_name, last_name, status FROM users WHERE id = ? AND status != 'deleted'";
    $stmt = $pdo->prepare($userQuery);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado o inactivo');
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        if ($action === 'add') {
            // Verificar si ya está asignado
            $checkQuery = "SELECT id FROM user_group_members WHERE user_id = ? AND group_id = ?";
            $stmt = $pdo->prepare($checkQuery);
            $stmt->execute([$userId, $groupId]);
            
            if ($stmt->fetch()) {
                throw new Exception('El usuario ya está asignado a este grupo');
            }
            
            // Agregar usuario al grupo
            $insertQuery = "INSERT INTO user_group_members (user_id, group_id, assigned_by) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($insertQuery);
            $success = $stmt->execute([$userId, $groupId, $currentUser['id']]);
            
            if (!$success) {
                throw new Exception('Error al agregar usuario al grupo');
            }
            
            $message = "Usuario '{$user['username']}' agregado al grupo '{$group['name']}'";
            $logAction = 'user_added_to_group';
            
        } else { // remove
            // Verificar si está asignado
            $checkQuery = "SELECT id FROM user_group_members WHERE user_id = ? AND group_id = ?";
            $stmt = $pdo->prepare($checkQuery);
            $stmt->execute([$userId, $groupId]);
            
            if (!$stmt->fetch()) {
                throw new Exception('El usuario no está asignado a este grupo');
            }
            
            // Remover usuario del grupo
            $deleteQuery = "DELETE FROM user_group_members WHERE user_id = ? AND group_id = ?";
            $stmt = $pdo->prepare($deleteQuery);
            $success = $stmt->execute([$userId, $groupId]);
            
            if (!$success) {
                throw new Exception('Error al remover usuario del grupo');
            }
            
            $message = "Usuario '{$user['username']}' removido del grupo '{$group['name']}'";
            $logAction = 'user_removed_from_group';
        }
        
        // Registrar actividad
        $logQuery = "INSERT INTO activity_logs (user_id, action, module, details, ip_address) 
                     VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($logQuery);
        $stmt->execute([
            $currentUser['id'],
            $logAction,
            'groups',
            $message,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Confirmar transacción
        $pdo->commit();
        
        // Obtener estadísticas actualizadas del grupo
        $statsQuery = "SELECT 
                        COUNT(*) as total_members,
                        COUNT(CASE WHEN u.status = 'active' THEN 1 END) as active_members
                       FROM user_group_members ugm
                       JOIN users u ON ugm.user_id = u.id
                       WHERE ugm.group_id = ? AND u.status != 'deleted'";
        
        $stmt = $pdo->prepare($statsQuery);
        $stmt->execute([$groupId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => $message,
            'action' => $action,
            'group_stats' => $stats,
            'data' => [
                'group_id' => $groupId,
                'user_id' => $userId,
                'user_name' => $user['first_name'] . ' ' . $user['last_name'],
                'username' => $user['username']
            ]
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en manage_group_users.php: " . $e->getMessage());
    
    // Respuesta de error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GROUP_USER_MANAGEMENT_ERROR'
    ]);
}
?>