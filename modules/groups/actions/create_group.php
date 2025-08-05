<?php
/*
 * modules/groups/actions/create_group.php
 * Acción para crear nuevos grupos de usuarios
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
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = $_POST['status'] ?? 'active';

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'El nombre del grupo es obligatorio']);
    exit;
}

if (strlen($name) > 100) {
    echo json_encode(['success' => false, 'message' => 'El nombre del grupo no puede exceder 100 caracteres']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar que no exista un grupo con el mismo nombre
    $checkQuery = "SELECT id FROM user_groups WHERE name = ? AND status != 'deleted'";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$name]);
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya existe un grupo con ese nombre']);
        exit;
    }
    
    // Insertar nuevo grupo
    $insertQuery = "
        INSERT INTO user_groups (
            name, 
            description, 
            status, 
            created_by, 
            created_at,
            is_system_group,
            module_permissions,
            access_restrictions
        ) VALUES (?, ?, ?, ?, NOW(), FALSE, '{}', '{}')
    ";
    
    $stmt = $pdo->prepare($insertQuery);
    $result = $stmt->execute([
        $name,
        $description,
        $status,
        $currentUser['id']
    ]);
    
    if ($result) {
        $groupId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Grupo creado exitosamente',
            'group_id' => $groupId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear el grupo']);
    }
    
} catch (Exception $e) {
    error_log('Error creando grupo: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}