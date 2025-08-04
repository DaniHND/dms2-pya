<?php
/*
 * modules/groups/actions/toggle_group_status.php
 * Cambiar estado activo/inactivo de un grupo
 */

$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/session.php';
require_once $projectRoot . '/config/database.php';

header('Content-Type: application/json');

// Verificar sesión y permisos
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$groupId = (int)($_POST['group_id'] ?? 0);

if (!$groupId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de grupo requerido']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Obtener información actual del grupo
    $groupQuery = "SELECT id, name, status, is_system_group FROM user_groups WHERE id = ?";
    $stmt = $pdo->prepare($groupQuery);
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Grupo no encontrado']);
        exit;
    }
    
    // Determinar nuevo estado
    $newStatus = $group['status'] === 'active' ? 'inactive' : 'active';
    
    // Actualizar estado
    $updateQuery = "UPDATE user_groups SET status = ?, updated_at = NOW() WHERE id = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    $result = $updateStmt->execute([$newStatus, $groupId]);
    
    if ($result) {
        // Registrar actividad
        if (function_exists('logActivity')) {
            $action = $newStatus === 'active' ? 'activate_group' : 'deactivate_group';
            $description = "Grupo '{$group['name']}' " . ($newStatus === 'active' ? 'activado' : 'desactivado');
            
            logActivity(
                $currentUser['id'], 
                $action, 
                'user_groups', 
                $groupId, 
                $description
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Estado del grupo actualizado correctamente',
            'group' => [
                'id' => $groupId,
                'name' => $group['name'],
                'old_status' => $group['status'],
                'new_status' => $newStatus
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado del grupo']);
    }
    
} catch (Exception $e) {
    error_log('Error en toggle_group_status: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor'
    ]);
}
?>