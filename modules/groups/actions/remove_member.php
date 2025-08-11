<?php
/*
 * modules/groups/actions/remove_member.php
 * Acción para remover un miembro individual de un grupo
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

// Leer datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
    exit;
}

$groupId = (int)($data['group_id'] ?? 0);
$userId = (int)($data['user_id'] ?? 0);

if (!$groupId || !$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de grupo y usuario requeridos']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar que el grupo existe
    $groupCheck = $pdo->prepare("SELECT id, name FROM user_groups WHERE id = ?");
    $groupCheck->execute([$groupId]);
    $group = $groupCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Grupo no encontrado']);
        exit;
    }
    
    // Verificar que el usuario existe y está en el grupo
    $memberCheck = $pdo->prepare("
        SELECT ugm.id, u.username, u.first_name, u.last_name 
        FROM user_group_members ugm
        INNER JOIN users u ON ugm.user_id = u.id
        WHERE ugm.group_id = ? AND ugm.user_id = ?
    ");
    $memberCheck->execute([$groupId, $userId]);
    $member = $memberCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado en el grupo']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Remover usuario del grupo
    $deleteStmt = $pdo->prepare("DELETE FROM user_group_members WHERE group_id = ? AND user_id = ?");
    $result = $deleteStmt->execute([$groupId, $userId]);
    
    if (!$result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al remover usuario del grupo']);
        exit;
    }
    
    // Registrar actividad en logs - ESTRUCTURA CORREGIDA
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $logDetails = "Usuario '{$member['username']}' removido del grupo '{$group['name']}'";
    $logStmt->execute([
        $currentUser['id'],
        'user_removed_from_group',
        'user_group_members',
        $member['id'],
        $logDetails
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Usuario '{$member['first_name']} {$member['last_name']}' removido del grupo exitosamente"
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error PDO en remove_member.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error en remove_member.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>