<?php
/*
 * modules/groups/actions/get_group_users.php
 * Obtener usuarios actuales y disponibles para un grupo
 */

require_once '../../../config/session.php';
require_once '../../../config/database.php';

// Configurar respuesta JSON
header('Content-Type: application/json');

try {
    // Verificar sesión y permisos
    SessionManager::requirePermission('manage_users');
    $currentUser = SessionManager::getCurrentUser();
    
    // Verificar método GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener parámetros
    $groupId = $_GET['group_id'] ?? null;
    
    if (empty($groupId) || !is_numeric($groupId)) {
        throw new Exception('ID de grupo inválido');
    }
    
    // Obtener conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Verificar que el grupo existe
    $groupQuery = "SELECT id, name FROM user_groups WHERE id = ?";
    $stmt = $pdo->prepare($groupQuery);
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        throw new Exception('Grupo no encontrado');
    }
    
    // Obtener usuarios actuales del grupo
    $currentUsersQuery = "SELECT 
                            u.id,
                            u.username,
                            u.first_name,
                            u.last_name,
                            u.email,
                            u.status,
                            u.role,
                            c.name as company_name,
                            d.name as department_name,
                            ugm.assigned_at,
                            CONCAT(assigner.first_name, ' ', assigner.last_name) as assigned_by_name
                          FROM user_group_members ugm
                          JOIN users u ON ugm.user_id = u.id
                          LEFT JOIN companies c ON u.company_id = c.id
                          LEFT JOIN departments d ON u.department_id = d.id
                          LEFT JOIN users assigner ON ugm.assigned_by = assigner.id
                          WHERE ugm.group_id = ? AND u.status != 'deleted'
                          ORDER BY u.first_name, u.last_name";
    
    $stmt = $pdo->prepare($currentUsersQuery);
    $stmt->execute([$groupId]);
    $currentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener usuarios disponibles (que no están en el grupo)
    $availableUsersQuery = "SELECT 
                             u.id,
                             u.username,
                             u.first_name,
                             u.last_name,
                             u.email,
                             u.status,
                             u.role,
                             c.name as company_name,
                             d.name as department_name
                           FROM users u
                           LEFT JOIN companies c ON u.company_id = c.id
                           LEFT JOIN departments d ON u.department_id = d.id
                           WHERE u.status = 'active'
                           AND u.id NOT IN (
                               SELECT ugm.user_id 
                               FROM user_group_members ugm 
                               WHERE ugm.group_id = ?
                           )
                           ORDER BY u.first_name, u.last_name";
    
    $stmt = $pdo->prepare($availableUsersQuery);
    $stmt->execute([$groupId]);
    $availableUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas del grupo
    $statsQuery = "SELECT 
                    COUNT(*) as total_members,
                    COUNT(CASE WHEN u.status = 'active' THEN 1 END) as active_members,
                    COUNT(DISTINCT u.company_id) as companies_count,
                    COUNT(DISTINCT u.department_id) as departments_count
                   FROM user_group_members ugm
                   JOIN users u ON ugm.user_id = u.id
                   WHERE ugm.group_id = ? AND u.status != 'deleted'";
    
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute([$groupId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Formatear fechas para los usuarios actuales
    foreach ($currentUsers as &$user) {
        if ($user['assigned_at']) {
            $user['assigned_at_formatted'] = date('d/m/Y H:i', strtotime($user['assigned_at']));
        }
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'group' => $group,
        'current_users' => $currentUsers,
        'available_users' => $availableUsers,
        'stats' => $stats,
        'summary' => [
            'current_count' => count($currentUsers),
            'available_count' => count($availableUsers),
            'total_active_users' => count($currentUsers) + count($availableUsers)
        ]
    ]);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en get_group_users.php: " . $e->getMessage());
    
    // Respuesta de error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GROUP_USERS_ERROR'
    ]);
}
?>