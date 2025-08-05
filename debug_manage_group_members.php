<?php
/*
 * debug_manage_group_members.php
 * Versión de diagnóstico para identificar problemas
 */

// Corregir la ruta del proyecto
$projectRoot = __DIR__;
// Buscar hacia arriba hasta encontrar config
for ($i = 0; $i < 5; $i++) {
    if (file_exists($projectRoot . '/config/session.php')) {
        break;
    }
    $projectRoot = dirname($projectRoot);
}

require_once $projectRoot . '/config/session.php';
require_once $projectRoot . '/config/database.php';

header('Content-Type: application/json');

// Verificar sesión y permisos
if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado', 'debug' => 'Usuario no loggeado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Sin permisos', 'debug' => 'Rol: ' . $currentUser['role']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido', 'debug' => 'Método: ' . $_SERVER['REQUEST_METHOD']]);
    exit;
}

// Leer datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode([
        'success' => false, 
        'message' => 'Datos JSON inválidos', 
        'debug' => [
            'json_error' => json_last_error_msg(),
            'input' => $input,
            'project_root' => $projectRoot
        ]
    ]);
    exit;
}

$groupId = (int)($data['group_id'] ?? 0);
$memberIds = $data['member_ids'] ?? [];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Debug: Verificar estructura de la tabla
    $tableInfo = $pdo->query("DESCRIBE user_group_members")->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar que el grupo existe
    $groupCheck = $pdo->prepare("SELECT id, name FROM user_groups WHERE id = ?");
    $groupCheck->execute([$groupId]);
    $group = $groupCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode([
            'success' => false, 
            'message' => 'Grupo no encontrado', 
            'debug' => [
                'group_id' => $groupId,
                'table_structure' => $tableInfo
            ]
        ]);
        exit;
    }
    
    // Test simple: intentar agregar solo el primer usuario
    if (!empty($memberIds)) {
        $firstUserId = $memberIds[0];
        
        // Verificar si el usuario existe
        $userCheck = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = ? AND status = 'active'");
        $userCheck->execute([$firstUserId]);
        $user = $userCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Intentar insertar (versión más simple)
            try {
                $insertStmt = $pdo->prepare("INSERT INTO user_group_members (group_id, user_id) VALUES (?, ?)");
                $result = $insertStmt->execute([$groupId, $firstUserId]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Test exitoso - Usuario agregado',
                    'debug' => [
                        'table_structure' => $tableInfo,
                        'group' => $group,
                        'user' => $user,
                        'insert_result' => $result,
                        'member_ids_received' => $memberIds
                    ]
                ]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error en inserción',
                    'debug' => [
                        'table_structure' => $tableInfo,
                        'pdo_error' => $e->getMessage(),
                        'pdo_code' => $e->getCode(),
                        'sql_state' => $e->errorInfo,
                        'group_id' => $groupId,
                        'user_id' => $firstUserId
                    ]
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Usuario no encontrado o inactivo',
                'debug' => [
                    'table_structure' => $tableInfo,
                    'user_id' => $firstUserId,
                    'user_found' => $user
                ]
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No hay usuarios para agregar',
            'debug' => [
                'table_structure' => $tableInfo,
                'member_ids' => $memberIds,
                'data_received' => $data
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error general',
        'debug' => [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'project_root' => $projectRoot
        ]
    ]);
}
?>