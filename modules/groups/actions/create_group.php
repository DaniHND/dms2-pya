<?php
/*
 * modules/groups/actions/create_group.php
 * Crear nuevo grupo de usuarios
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

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El nombre del grupo es requerido']);
    exit;
}

// Validar longitud del nombre
if (strlen($name) > 150) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El nombre del grupo no puede exceder 150 caracteres']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar si ya existe un grupo con ese nombre
    $checkQuery = "SELECT id FROM user_groups WHERE name = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$name]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya existe un grupo con ese nombre']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Crear el grupo
    $insertQuery = "
        INSERT INTO user_groups (
            name, 
            description, 
            module_permissions, 
            access_restrictions, 
            status, 
            is_system_group, 
            created_by, 
            created_at, 
            updated_at
        ) VALUES (?, ?, '{}', '{}', 'active', 0, ?, NOW(), NOW())
    ";
    
    $insertStmt = $pdo->prepare($insertQuery);
    $result = $insertStmt->execute([
        $name,
        $description,
        $currentUser['id']
    ]);
    
    if ($result) {
        $groupId = $pdo->lastInsertId();
        
        // Registrar actividad
        if (function_exists('logActivity')) {
            logActivity(
                $currentUser['id'], 
                'create_group', 
                'user_groups', 
                $groupId, 
                "Grupo '$name' creado"
            );
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Grupo creado correctamente',
            'group' => [
                'id' => $groupId,
                'name' => $name,
                'description' => $description
            ]
        ]);
        
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al crear el grupo']);
    }
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error en create_group: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor'
    ]);
}
?>