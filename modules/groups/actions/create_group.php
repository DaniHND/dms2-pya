<?php
/*
 * modules/groups/actions/create_group.php
 * Crear nuevo grupo de usuarios - Versión corregida
 */

// Configurar headers y manejo de errores
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Obtener rutas absolutas
$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/config/session.php';

// Cargar functions.php si existe
if (file_exists($projectRoot . '/includes/functions.php')) {
    require_once $projectRoot . '/includes/functions.php';
}

function logDebug($message) {
    error_log("[CREATE_GROUP] " . $message);
}

try {
    logDebug("Iniciando creación de grupo");
    
    // Verificar autenticación
    if (!SessionManager::isLoggedIn()) {
        throw new Exception("Usuario no autenticado");
    }
    
    $currentUser = SessionManager::getCurrentUser();
    if ($currentUser['role'] !== 'admin') {
        throw new Exception("Permisos insuficientes. Se requiere rol de administrador");
    }
    
    logDebug("Usuario autenticado: " . $currentUser['id']);
    
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método HTTP no permitido");
    }
    
    // Obtener y validar datos
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $basicPermissions = $_POST['basic_permissions'] ?? '{}';
    
    logDebug("Datos recibidos - Nombre: '$name', Descripción: '$description', Estado: '$status'");
    
    // Validaciones
    if (empty($name)) {
        throw new Exception("El nombre del grupo es obligatorio");
    }
    
    if (strlen($name) > 150) {
        throw new Exception("El nombre del grupo no puede exceder 150 caracteres");
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception("Estado inválido");
    }
    
    // Procesar permisos básicos
    $permissions = [];
    try {
        $basicPerms = json_decode($basicPermissions, true);
        if ($basicPerms && is_array($basicPerms)) {
            $permissions = $basicPerms;
        }
    } catch (Exception $e) {
        logDebug("Error procesando permisos básicos: " . $e->getMessage());
    }
    
    // Asegurar que siempre tenga el permiso de ver
    $permissions['view'] = true;
    
    logDebug("Permisos procesados: " . json_encode($permissions));
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    logDebug("Conexión a BD establecida");
    
    // Verificar si ya existe un grupo con ese nombre
    $checkQuery = "SELECT id FROM user_groups WHERE name = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$name]);
    
    if ($checkStmt->fetch()) {
        throw new Exception("Ya existe un grupo con ese nombre");
    }
    
    logDebug("Nombre único verificado");
    
    // Crear el grupo
    $insertQuery = "
        INSERT INTO user_groups (
            name, 
            description, 
            status, 
            module_permissions, 
            access_restrictions,
            is_system_group,
            created_by, 
            created_at, 
            updated_at
        ) VALUES (?, ?, ?, ?, ?, 0, ?, NOW(), NOW())
    ";
    
    $accessRestrictions = json_encode([
        'companies' => [],
        'departments' => [],
        'document_types' => []
    ]);
    
    $insertStmt = $pdo->prepare($insertQuery);
    $success = $insertStmt->execute([
        $name,
        $description,
        $status,
        json_encode($permissions),
        $accessRestrictions,
        $currentUser['id']
    ]);
    
    if (!$success) {
        $errorInfo = $insertStmt->errorInfo();
        logDebug("Error SQL: " . implode(' - ', $errorInfo));
        throw new Exception("Error al crear el grupo en la base de datos");
    }
    
    $groupId = $pdo->lastInsertId();
    logDebug("Grupo creado con ID: $groupId");
    
    // Registrar actividad
    if (function_exists('logActivity')) {
        logActivity(
            $currentUser['id'], 
            'create_group', 
            'groups', 
            $groupId, 
            "Grupo creado: $name"
        );
        logDebug("Actividad registrada");
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => "Grupo '$name' creado exitosamente",
        'group_id' => (int)$groupId,
        'group' => [
            'id' => (int)$groupId,
            'name' => $name,
            'description' => $description,
            'status' => $status,
            'permissions' => $permissions
        ]
    ]);
    
    logDebug("Respuesta exitosa enviada");
    
} catch (PDOException $e) {
    logDebug("Error PDO: " . $e->getMessage());
    
    // Verificar si es error de duplicado
    if ($e->getCode() == 23000) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe un grupo con ese nombre',
            'error_code' => 'DUPLICATE_NAME'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error de base de datos al crear el grupo',
            'error_code' => 'DB_ERROR'
        ]);
    }
    
} catch (Exception $e) {
    logDebug("Error general: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GENERAL_ERROR'
    ]);
}
?>