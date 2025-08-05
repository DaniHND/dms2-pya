<?php
/*
 * modules/groups/actions/manage_group_members.php
 * Actualización masiva de miembros de grupos - Versión final corregida
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
        foreach ($toRemove as $userIdToRemove) {
            try {
                $deleteStmt = $pdo->prepare("DELETE FROM user_group_members WHERE group_id = ? AND user_id = ?");
                if ($deleteStmt->execute([$groupId, $userIdToRemove])) {
                    $removedCount++;
                }
            } catch (PDOException $e) {
                $errors[] = "Error removiendo usuario $userIdToRemove: " . $e->getMessage();
            }
        }
    }
    
    // Agregar nuevos usuarios
    if (!empty($toAdd)) {
        // Verificar que todos los usuarios existen y están activos
        $userCheckQuery = "SELECT id FROM users WHERE status = 'active'";
        $userCheckStmt = $pdo->prepare($userCheckQuery);
        $userCheckStmt->execute();
        $allValidUsers = $userCheckStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Filtrar solo usuarios válidos que queremos agregar
        $usersToAdd = array_intersect($toAdd, $allValidUsers);
        $invalidUsers = array_diff($toAdd, $allValidUsers);
        
        if (!empty($invalidUsers)) {
            $errors[] = "Usuarios no válidos o inactivos: " . implode(', ', $invalidUsers);
        }
        
        // Insertar usuarios válidos (manejando duplicados correctamente)
        if (!empty($usersToAdd)) {
            foreach ($usersToAdd as $userId) {
                try {
                    // Usar INSERT IGNORE para evitar errores de duplicado
                    $insertStmt = $pdo->prepare("
                        INSERT IGNORE INTO user_group_members (group_id, user_id) 
                        VALUES (?, ?)
                    ");
                    
                    if ($insertStmt->execute([$groupId, $userId])) {
                        // Solo contar si realmente se insertó (no era duplicado)
                        if ($insertStmt->rowCount() > 0) {
                            $addedCount++;
                        }
                    }
                } catch (PDOException $e) {
                    $errors[] = "Error agregando usuario $userId: " . $e->getMessage();
                }
            }
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
            'total_members' => count($memberIds)
        ]
    ];
    
    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error PDO en manage_group_members: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error en manage_group_members: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>