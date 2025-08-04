<?php
/*
 * modules/groups/actions/update_group_members.php
 * Actualización masiva de miembros de grupos
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
$memberIds = $data['member_ids'] ?? [];

if (!$groupId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de grupo requerido']);
    exit;
}

// Validar que member_ids sea un array
if (!is_array($memberIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'member_ids debe ser un array']);
    exit;
}

// Limpiar y validar IDs de usuarios
$memberIds = array_filter(array_map('intval', $memberIds));

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
    
    $pdo->beginTransaction();
    
    // Obtener miembros actuales
    $currentMembersQuery = "SELECT user_id FROM user_group_members WHERE group_id = ?";
    $stmt = $pdo->prepare($currentMembersQuery);
    $stmt->execute([$groupId]);
    $currentMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Determinar cambios
    $toAdd = array_diff($memberIds, $currentMembers);
    $toRemove = array_diff($currentMembers, $memberIds);
    
    $addedCount = 0;
    $removedCount = 0;
    $errors = [];
    
    // Remover usuarios que ya no están seleccionados
    if (!empty($toRemove)) {
        $placeholders = str_repeat('?,', count($toRemove) - 1) . '?';
        $deleteStmt = $pdo->prepare("DELETE FROM user_group_members WHERE group_id = ? AND user_id IN ($placeholders)");
        $params = array_merge([$groupId], $toRemove);
        
        if ($deleteStmt->execute($params)) {
            $removedCount = $deleteStmt->rowCount();
        }
    }
    
    // Agregar nuevos usuarios
    if (!empty($toAdd)) {
        // Verificar que todos los usuarios existen y están activos
        $placeholders = str_repeat('?,', count($toAdd) - 1) . '?';
        $userCheckQuery = "SELECT id, first_name, last_name FROM users WHERE id IN ($placeholders) AND status = 'active'";
        $userCheckStmt = $pdo->prepare($userCheckQuery);
        $userCheckStmt->execute($toAdd);
        $validUsers = $userCheckStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $validUserIds = array_column($validUsers, 'id');
        $invalidUsers = array_diff($toAdd, $validUserIds);
        
        if (!empty($invalidUsers)) {
            $errors[] = "Usuarios no válidos o inactivos: " . implode(', ', $invalidUsers);
        }
        
        // Insertar usuarios válidos
        if (!empty($validUserIds)) {
            $insertStmt = $pdo->prepare("
                INSERT INTO user_group_members (group_id, user_id, added_by, added_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            foreach ($validUserIds as $userId) {
                if ($insertStmt->execute([$groupId, $userId, $currentUser['id']])) {
                    $addedCount++;
                }
            }
        }
    }
    
    // Registrar actividad
    if (function_exists('logActivity')) {
        $changes = [];
        if ($addedCount > 0) {
            $changes[] = "Agregados: $addedCount usuarios";
        }
        if ($removedCount > 0) {
            $changes[] = "Removidos: $removedCount usuarios";
        }
        
        if (!empty($changes)) {
            $description = "Miembros actualizados en grupo '{$group['name']}': " . implode(', ', $changes);
            logActivity(
                $currentUser['id'], 
                'update_group_members', 
                'user_group_members', 
                $groupId, 
                $description
            );
        }
    }
    
    $pdo->commit();
    
    // Respuesta exitosa
    $response = [
        'success' => true,
        'message' => 'Miembros actualizados correctamente',
        'group' => [
            'id' => $groupId,
            'name' => $group['name']
        ],
        'changes' => [
            'added' => $addedCount,
            'removed' => $removedCount,
            'total_members' => count($memberIds) - count($invalidUsers ?? [])
        ]
    ];
    
    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error en update_group_members: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor',
        'debug' => $e->getMessage() // Solo para desarrollo
    ]);
}
?>