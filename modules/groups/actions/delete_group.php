<?php
/*
 * modules/groups/actions/delete_group.php
 * Acción para eliminar grupos de usuarios
 */

$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/session.php';
require_once $projectRoot . '/config/database.php';

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar sesión y permisos
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
    exit;
}

// Validar datos de entrada
$groupId = (int)($_POST['group_id'] ?? 0);

if ($groupId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de grupo inválido']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar que el grupo existe y no es del sistema
    $checkQuery = "SELECT name, is_system_group FROM user_groups WHERE id = ? AND status != 'deleted'";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Grupo no encontrado']);
        exit;
    }
    
    if ($group['is_system_group']) {
        echo json_encode(['success' => false, 'message' => 'No se pueden eliminar grupos del sistema']);
        exit;
    }
    
    // Verificar si el grupo tiene miembros
    $membersQuery = "SELECT COUNT(*) as count FROM user_group_members WHERE group_id = ?";
    $membersStmt = $pdo->prepare($membersQuery);
    $membersStmt->execute([$groupId]);
    $memberCount = $membersStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($memberCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "No se puede eliminar el grupo porque tiene $memberCount miembro(s) asignado(s). Elimine primero los miembros."
        ]);
        exit;
    }
    
    // Eliminar grupo (marcar como eliminado)
    $deleteQuery = "UPDATE user_groups SET status = 'deleted', updated_at = NOW() WHERE id = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $result = $deleteStmt->execute([$groupId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => "Grupo '{$group['name']}' eliminado exitosamente"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el grupo']);
    }
    
} catch (Exception $e) {
    error_log('Error eliminando grupo: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}