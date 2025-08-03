<?php
/*
 * modules/groups/actions/toggle_group_status.php
 * Cambiar estado de un grupo (activo/inactivo) - COMPATIBLE CON ESTRUCTURA ACTUAL
 */

// Configurar manejo de errores y output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Limpiar cualquier buffer de salida anterior
if (ob_get_level()) {
    ob_end_clean();
}

// Headers necesarios
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Función para logging seguro
function logDebug($message) {
    error_log("[TOGGLE_GROUP_STATUS] " . $message);
}

try {
    // Obtener rutas absolutas
    $projectRoot = dirname(dirname(dirname(__DIR__)));
    
    // Verificar archivos requeridos
    $requiredFiles = [
        $projectRoot . '/config/database.php',
        $projectRoot . '/config/session.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            throw new Exception("Archivo requerido no encontrado: " . basename($file));
        }
        require_once $file;
    }
    
    // Cargar functions.php si existe
    if (file_exists($projectRoot . '/includes/functions.php')) {
        require_once $projectRoot . '/includes/functions.php';
    }
    
    logDebug("Archivos cargados correctamente");
    
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método HTTP no permitido. Se esperaba POST, se recibió: " . $_SERVER['REQUEST_METHOD']);
    }
    
    logDebug("Método POST verificado");
    
    // Verificar autenticación
    if (!class_exists('SessionManager')) {
        throw new Exception("Clase SessionManager no encontrada");
    }
    
    if (!SessionManager::isLoggedIn()) {
        throw new Exception("Usuario no autenticado");
    }
    
    $currentUser = SessionManager::getCurrentUser();
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        throw new Exception("Permisos insuficientes. Se requiere rol de administrador");
    }
    
    logDebug("Usuario autenticado: " . $currentUser['id'] . " (" . $currentUser['role'] . ")");
    
    // Obtener y validar datos del formulario
    $groupId = $_POST['group_id'] ?? null;
    $newStatus = $_POST['status'] ?? null;
    
    logDebug("Datos recibidos - Group ID: " . var_export($groupId, true) . ", Status: " . var_export($newStatus, true));
    
    // Validaciones de entrada
    if (!$groupId || !is_numeric($groupId)) {
        throw new Exception("ID de grupo inválido");
    }
    
    if (!in_array($newStatus, ['active', 'inactive'])) {
        throw new Exception("Estado inválido. Debe ser 'active' o 'inactive'");
    }
    
    $groupId = (int)$groupId;
    
    // Conectar a la base de datos
    if (!class_exists('Database')) {
        throw new Exception("Clase Database no encontrada");
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    logDebug("Conexión a BD establecida");
    
    // IMPORTANTE: Consulta SIN deleted_at porque la columna no existe en tu estructura
    $checkQuery = "
        SELECT id, name, status, is_system_group, created_at
        FROM user_groups 
        WHERE id = :group_id
    ";
    
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $group = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        throw new Exception("Grupo no encontrado");
    }
    
    logDebug("Grupo encontrado: " . $group['name'] . " (Status actual: " . $group['status'] . ")");
    
    // Verificar si es un grupo del sistema
    if ($group['is_system_group'] == 1) {
        throw new Exception("No se puede modificar el estado de un grupo del sistema");
    }
    
    // Verificar si el estado ya es el mismo
    if ($group['status'] === $newStatus) {
        echo json_encode([
            'success' => true,
            'message' => 'El grupo ya tiene este estado',
            'new_status' => $newStatus,
            'group_name' => $group['name']
        ]);
        exit;
    }
    
    // Actualizar estado del grupo
    $updateQuery = "
        UPDATE user_groups 
        SET status = :status, updated_at = NOW() 
        WHERE id = :group_id
    ";
    
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->bindParam(':status', $newStatus);
    $updateStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Error al ejecutar la actualización en la base de datos");
    }
    
    $rowsAffected = $updateStmt->rowCount();
    if ($rowsAffected === 0) {
        throw new Exception("No se pudo actualizar el grupo. Posiblemente ya tiene este estado");
    }
    
    logDebug("Grupo actualizado exitosamente. Filas afectadas: " . $rowsAffected);
    
    // Registrar actividad si la función existe
    if (function_exists('logActivity')) {
        $action = $newStatus === 'active' ? 'activó' : 'desactivó';
        logActivity(
            $currentUser['id'], 
            'toggle_group_status', 
            'groups', 
            $groupId, 
            "Usuario {$action} el grupo: {$group['name']}"
        );
        logDebug("Actividad registrada");
    }
    
    // Respuesta exitosa
    $statusText = $newStatus === 'active' ? 'activado' : 'desactivado';
    
    $response = [
        'success' => true,
        'message' => "Grupo '{$group['name']}' {$statusText} exitosamente",
        'new_status' => $newStatus,
        'group_id' => $groupId,
        'group_name' => $group['name']
    ];
    
    logDebug("Operación completada exitosamente");
    echo json_encode($response);
    
} catch (PDOException $e) {
    logDebug("Error PDO: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage(),
        'error_code' => 'DB_ERROR'
    ]);
    
} catch (Exception $e) {
    logDebug("Error general: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GENERAL_ERROR'
    ]);
}
?>