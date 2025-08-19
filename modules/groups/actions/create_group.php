<?php
/*
 * modules/groups/actions/create_group.php
 * Acción para crear nuevos grupos de usuarios - VERSIÓN CORREGIDA
 */

$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/session.php';
require_once $projectRoot . '/config/database.php';

// Configurar headers para JSON
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en output JSON

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

// Log para debug
error_log("=== CREATE GROUP DEBUG ===");
error_log("Name: " . $name);
error_log("Description: " . $description);
error_log("Status: " . $status);
error_log("User ID: " . $currentUser['id']);

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'El nombre del grupo es obligatorio']);
    exit;
}

// CORRECCIÓN: Cambiar a 150 caracteres según la base de datos
if (strlen($name) > 150) {
    echo json_encode(['success' => false, 'message' => 'El nombre del grupo no puede exceder 150 caracteres']);
    exit;
}

// Validar status
if (!in_array($status, ['active', 'inactive'])) {
    $status = 'active';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    error_log("✅ Conexión a BD exitosa");
    
    // Verificar que no exista un grupo con el mismo nombre
    $checkQuery = "SELECT id FROM user_groups WHERE name = ? AND deleted_at IS NULL";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$name]);
    
    if ($checkStmt->rowCount() > 0) {
        error_log("❌ Ya existe un grupo con ese nombre");
        echo json_encode(['success' => false, 'message' => 'Ya existe un grupo con ese nombre']);
        exit;
    }
    
    error_log("✅ Validación de nombre duplicado pasada");
    
    // CORRECCIÓN: Usar la estructura exacta de la base de datos
    $insertQuery = "
        INSERT INTO user_groups (
            name, 
            description, 
            module_permissions,
            access_restrictions,
            download_limit_daily,
            upload_limit_daily,
            status, 
            is_system_group,
            created_by, 
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";
    
    // Preparar valores por defecto
    $defaultPermissions = json_encode([
        'upload_files' => false,
        'view_files' => false,
        'create_folders' => false,
        'download_files' => false,
        'delete_files' => false,
        'move_files' => false
    ]);
    
    $defaultRestrictions = json_encode([
        'companies' => [],
        'departments' => [],
        'document_types' => []
    ]);
    
    $stmt = $pdo->prepare($insertQuery);
    
    $params = [
        $name,                      // name
        $description,               // description  
        $defaultPermissions,        // module_permissions
        $defaultRestrictions,       // access_restrictions
        null,                       // download_limit_daily
        null,                       // upload_limit_daily
        $status,                    // status
        0,                          // is_system_group (false)
        $currentUser['id']          // created_by
    ];
    
    error_log("Ejecutando INSERT con parámetros: " . json_encode($params));
    
    $result = $stmt->execute($params);
    
    if ($result) {
        $groupId = $pdo->lastInsertId();
        
        error_log("✅ Grupo creado exitosamente con ID: $groupId");
        
        echo json_encode([
            'success' => true,
            'message' => 'Grupo creado exitosamente',
            'group_id' => $groupId
        ]);
    } else {
        error_log("❌ Error al ejecutar INSERT");
        echo json_encode(['success' => false, 'message' => 'Error al crear el grupo']);
    }
    
} catch (PDOException $e) {
    error_log('❌ Error PDO creando grupo: ' . $e->getMessage());
    error_log('Código de error: ' . $e->getCode());
    error_log('SQL State: ' . $e->errorInfo[0] ?? 'N/A');
    
    // Manejar errores específicos de base de datos
    if ($e->getCode() == 23000) { // Constraint violation
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo json_encode(['success' => false, 'message' => 'Ya existe un grupo con ese nombre']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error de integridad en la base de datos']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    error_log('❌ Error general creando grupo: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>