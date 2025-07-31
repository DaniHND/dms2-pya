<?php
/*
 * modules/groups/actions/toggle_group_status.php
 * Cambiar estado de un grupo (activar/desactivar)
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
    $newStatus = $_POST['status'] ?? null;
    
    // Validar parámetros
    if (empty($groupId) || !is_numeric($groupId)) {
        throw new Exception('ID de grupo inválido');
    }
    
    if (!in_array($newStatus, ['active', 'inactive'])) {
        throw new Exception('Estado inválido');
    }
    
    // Obtener conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Verificar que el grupo existe y obtener datos actuales
    $groupQuery = "SELECT id, name, status, is_system_group FROM user_groups WHERE id = ?";
    $stmt = $pdo->prepare($groupQuery);
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        throw new Exception('Grupo no encontrado');
    }
    
    // Verificar si ya tiene el estado solicitado
    if ($group['status'] === $newStatus) {
        throw new Exception("El grupo ya está $newStatus");
    }
    
    // Validaciones especiales para grupos del sistema
    if ($group['is_system_group'] && $newStatus === 'inactive') {
        // Verificar si hay usuarios que dependan únicamente de este grupo
        $dependentUsersQuery = "SELECT COUNT(DISTINCT u.id) as count
                               FROM users u
                               JOIN user_group_members ugm ON u.id = ugm.user_id
                               WHERE ugm.group_id = ? 
                               AND u.status = 'active'
                               AND u.id NOT IN (
                                   SELECT ugm2.user_id 
                                   FROM user_group_members ugm2 
                                   JOIN user_groups ug2 ON ugm2.group_id = ug2.id
                                   WHERE ug2.id != ? AND ug2.status = 'active'
                               )";
        
        $stmt = $pdo->prepare($dependentUsersQuery);
        $stmt->execute([$groupId, $groupId]);
        $dependentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($dependentCount > 0) {
            throw new Exception("No se puede desactivar este grupo del sistema porque $dependentCount usuario(s) dependen únicamente de él para acceder al sistema");
        }
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // Actualizar estado del grupo
        $updateQuery = "UPDATE user_groups SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $pdo->prepare($updateQuery);
        $success = $stmt->execute([$newStatus, $groupId]);
        
        if (!$success) {
            throw new Exception('Error al actualizar el estado del grupo');
        }
        
        // Registrar actividad
        $action = $newStatus === 'active' ? 'group_activated' : 'group_deactivated';
        $actionText = $newStatus === 'active' ? 'activado' : 'desactivado';
        
        $logQuery = "INSERT INTO activity_logs (user_id, action, module, details, ip_address) 
                     VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($logQuery);
        $stmt->execute([
            $currentUser['id'],
            $action,
            'groups',
            "Grupo '{$group['name']}' $actionText",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Si se desactiva el grupo, notificar a usuarios afectados (opcional)
        if ($newStatus === 'inactive') {
            // Obtener usuarios afectados
            $affectedUsersQuery = "SELECT 
                                    u.id, u.username, u.email, u.first_name, u.last_name
                                   FROM users u
                                   JOIN user_group_members ugm ON u.id = ugm.user_id
                                   WHERE ugm.group_id = ? AND u.status = 'active'";
            
            $stmt = $pdo->prepare($affectedUsersQuery);
            $stmt->execute([$groupId]);
            $affectedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log adicional para usuarios afectados
            if (!empty($affectedUsers)) {
                $usernames = array_column($affectedUsers, 'username');
                $logQuery = "INSERT INTO activity_logs (user_id, action, module, details, ip_address) 
                             VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($logQuery);
                $stmt->execute([
                    $currentUser['id'],
                    'group_users_affected',
                    'groups',
                    "Desactivación del grupo '{$group['name']}' afectó a " . count($affectedUsers) . " usuarios: " . implode(', ', $usernames),
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
        }
        
        // Confirmar transacción
        $pdo->commit();
        
        // Obtener estadísticas actualizadas
        $statsQuery = "SELECT 
                        COUNT(DISTINCT ugm.user_id) as total_members,
                        COUNT(DISTINCT CASE WHEN u.status = 'active' THEN ugm.user_id END) as active_members
                       FROM user_group_members ugm
                       LEFT JOIN users u ON ugm.user_id = u.id AND u.status != 'deleted'
                       WHERE ugm.group_id = ?";
        
        $stmt = $pdo->prepare($statsQuery);
        $stmt->execute([$groupId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => "Grupo '{$group['name']}' $actionText correctamente",
            'new_status' => $newStatus,
            'stats' => $stats,
            'data' => [
                'group_id' => $groupId,
                'group_name' => $group['name'],
                'old_status' => $group['status'],
                'new_status' => $newStatus,
                'affected_users' => isset($affectedUsers) ? count($affectedUsers) : 0
            ]
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en toggle_group_status.php: " . $e->getMessage());
    
    // Respuesta de error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GROUP_STATUS_TOGGLE_ERROR'
    ]);
}
?>