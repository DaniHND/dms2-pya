<?php
/*
 * modules/groups/actions/toggle_group_status.php
 * Acción para cambiar el estado de un grupo
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
    
    // Obtener estado actual del grupo
    $currentStatusQuery = "SELECT status, is_system_group, name FROM user_groups WHERE id = ?";
    $stmt = $pdo->prepare($currentStatusQuery);
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Grupo no encontrado']);
        exit;
    }
    
    // Determinar nuevo estado
    $newStatus = ($group['status'] === 'active') ? 'inactive' : 'active';
    
    // Actualizar estado
    $updateQuery = "UPDATE user_groups SET status = ?, updated_at = NOW() WHERE id = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    $result = $updateStmt->execute([$newStatus, $groupId]);
    
    if ($result) {
        $statusText = ($newStatus === 'active') ? 'activado' : 'desactivado';
        echo json_encode([
            'success' => true,
            'message' => "Grupo '{$group['name']}' $statusText exitosamente",
            'new_status' => $newStatus
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al cambiar el estado del grupo']);
    }
    
} catch (Exception $e) {
    error_log('Error cambiando estado de grupo: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}