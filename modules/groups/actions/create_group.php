<?php
/*
 * modules/groups/actions/create_group.php
 * Acción para crear un nuevo grupo - VERSIÓN ULTRA LIMPIA
 */

// Evitar cualquier output
error_reporting(0);
ini_set('display_errors', 0);

// Limpiar cualquier buffer de salida
while (ob_get_level()) {
    ob_end_clean();
}

// Iniciar buffer limpio
ob_start();

// Usar rutas absolutas
$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/config/session.php';

// Limpiar cualquier output de los includes
ob_clean();

// Configurar headers ANTES de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Verificar autenticación y permisos
if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Obtener datos del formulario
    $groupName = trim($_POST['group_name'] ?? '');
    $groupDescription = trim($_POST['group_description'] ?? '');
    $groupStatus = $_POST['group_status'] ?? 'active';
    
    // Validaciones
    if (empty($groupName)) {
        echo json_encode(['success' => false, 'message' => 'El nombre del grupo es obligatorio']);
        exit;
    }
    
    if (strlen($groupName) > 150) {
        echo json_encode(['success' => false, 'message' => 'El nombre del grupo no puede exceder 150 caracteres']);
        exit;
    }
    
    if (!in_array($groupStatus, ['active', 'inactive'])) {
        $groupStatus = 'active';
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Verificar si ya existe un grupo con el mismo nombre
    $checkQuery = "SELECT id FROM user_groups WHERE name = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$groupName]);
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya existe un grupo con este nombre']);
        exit;
    }
    
    // Insertar nuevo grupo
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
        ) VALUES (
            ?, 
            ?, 
            '{}',
            '{}',
            ?, 
            0, 
            ?, 
            NOW(),
            NOW()
        )
    ";
    
    $insertStmt = $pdo->prepare($insertQuery);
    $result = $insertStmt->execute([
        $groupName,
        $groupDescription,
        $groupStatus,
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
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['success' => false, 'message' => 'Ya existe un grupo con este nombre']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

// Limpiar y enviar
ob_end_flush();
exit;
?>

// Verificar autenticación y permisos
if (!SessionManager::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Permisos insuficientes'
    ]);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Obtener datos del formulario
    $groupName = trim($_POST['group_name'] ?? '');
    $groupDescription = trim($_POST['group_description'] ?? '');
    $groupStatus = $_POST['group_status'] ?? 'active';
    
    // Validaciones
    if (empty($groupName)) {
        echo json_encode([
            'success' => false,
            'message' => 'El nombre del grupo es obligatorio'
        ]);
        exit;
    }
    
    if (strlen($groupName) > 150) {
        echo json_encode([
            'success' => false,
            'message' => 'El nombre del grupo no puede exceder 150 caracteres'
        ]);
        exit;
    }
    
    if (!in_array($groupStatus, ['active', 'inactive'])) {
        $groupStatus = 'active';
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Verificar si ya existe un grupo con el mismo nombre
    $checkQuery = "SELECT id FROM user_groups WHERE name = :name";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindParam(':name', $groupName);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe un grupo con este nombre'
        ]);
        exit;
    }
    
    // Insertar nuevo grupo
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
        ) VALUES (
            :name, 
            :description, 
            '{}',
            '{}',
            :status, 
            0, 
            :created_by, 
            NOW(),
            NOW()
        )
    ";
    
    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->bindParam(':name', $groupName);
    $insertStmt->bindParam(':description', $groupDescription);
    $insertStmt->bindParam(':status', $groupStatus);
    $insertStmt->bindParam(':created_by', $currentUser['id']);
    
    if ($insertStmt->execute()) {
        $groupId = $pdo->lastInsertId();
        
        // Registrar actividad
        if (function_exists('logActivity')) {
            logActivity(
                $currentUser['id'], 
                'create_group', 
                'groups', 
                $groupId, 
                "Usuario creó el grupo: {$groupName}"
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Grupo creado exitosamente',
            'group_id' => $groupId
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al crear el grupo'
        ]);
    }
    
} catch (PDOException $e) {
    error_log('Error PDO en create_group.php: ' . $e->getMessage());
    
    // Verificar si es error de duplicación
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe un grupo con este nombre'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error de base de datos al crear el grupo'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Error general en create_group.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?><?php
/*
 * modules/groups/actions/create_group.php
 * Acción para crear un nuevo grupo
 */

require_once '../../../config/database.php';
require_once '../../../config/session.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

// Verificar autenticación y permisos
if (!SessionManager::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Permisos insuficientes'
    ]);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Obtener datos del formulario
    $groupName = trim($_POST['group_name'] ?? '');
    $groupDescription = trim($_POST['group_description'] ?? '');
    $groupStatus = $_POST['group_status'] ?? 'active';
    
    // Validaciones
    if (empty($groupName)) {
        echo json_encode([
            'success' => false,
            'message' => 'El nombre del grupo es obligatorio'
        ]);
        exit;
    }
    
    if (strlen($groupName) > 100) {
        echo json_encode([
            'success' => false,
            'message' => 'El nombre del grupo no puede exceder 100 caracteres'
        ]);
        exit;
    }
    
    if (!in_array($groupStatus, ['active', 'inactive'])) {
        $groupStatus = 'active';
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar si ya existe un grupo con el mismo nombre
    $checkQuery = "SELECT id FROM user_groups WHERE name = :name AND deleted_at IS NULL";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindParam(':name', $groupName);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe un grupo con este nombre'
        ]);
        exit;
    }
    
    // Insertar nuevo grupo
    $insertQuery = "
        INSERT INTO user_groups (
            name, 
            description, 
            status, 
            is_system_group, 
            created_by, 
            created_at,
            updated_at
        ) VALUES (
            :name, 
            :description, 
            :status, 
            0, 
            :created_by, 
            NOW(),
            NOW()
        )
    ";
    
    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->bindParam(':name', $groupName);
    $insertStmt->bindParam(':description', $groupDescription);
    $insertStmt->bindParam(':status', $groupStatus);
    $insertStmt->bindParam(':created_by', $currentUser['id']);
    
    if ($insertStmt->execute()) {
        $groupId = $pdo->lastInsertId();
        
        // Registrar actividad
        if (function_exists('logActivity')) {
            logActivity(
                $currentUser['id'], 
                'create_group', 
                'groups', 
                $groupId, 
                "Usuario creó el grupo: {$groupName}"
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Grupo creado exitosamente',
            'group_id' => $groupId
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al crear el grupo'
        ]);
    }
    
} catch (PDOException $e) {
    error_log('Error en create_group.php: ' . $e->getMessage());
    
    // Verificar si es error de duplicación
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe un grupo con este nombre'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error de base de datos al crear el grupo'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Error general en create_group.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>