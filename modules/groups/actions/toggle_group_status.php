<?php
/*
 * modules/groups/actions/toggle_group_status.php
 * Cambiar estado de un grupo (activo/inactivo) - VERSIÓN CON RUTAS ABSOLUTAS
 */

// Usar rutas absolutas basadas en __DIR__
$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/config/session.php';

// Cargar functions.php si existe
if (file_exists($projectRoot . '/includes/functions.php')) {
    require_once $projectRoot . '/includes/functions.php';
}

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
    $groupId = $_POST['group_id'] ?? null;
    $newStatus = $_POST['status'] ?? null;
    
    // Validaciones
    if (!$groupId || !is_numeric($groupId)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de grupo inválido'
        ]);
        exit;
    }
    
    if (!in_array($newStatus, ['active', 'inactive'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Estado inválido'
        ]);
        exit;
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar que el grupo existe y obtener datos actuales
    $checkQuery = "
        SELECT id, name, status, is_system_group 
        FROM user_groups 
        WHERE id = :group_id AND deleted_at IS NULL
    ";
    
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $group = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode([
            'success' => false,
            'message' => 'Grupo no encontrado'
        ]);
        exit;
    }
    
    // Verificar si es un grupo del sistema
    if ($group['is_system_group'] == 1) {
        echo json_encode([
            'success' => false,
            'message' => 'No se puede modificar el estado de un grupo del sistema'
        ]);
        exit;
    }
    
    // Verificar si el estado ya es el mismo
    if ($group['status'] === $newStatus) {
        echo json_encode([
            'success' => true,
            'message' => 'El grupo ya tiene este estado'
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
    
    if ($updateStmt->execute()) {
        // Registrar actividad
        if (function_exists('logActivity')) {
            $action = $newStatus === 'active' ? 'activó' : 'desactivó';
            logActivity(
                $currentUser['id'], 
                'toggle_group_status', 
                'groups', 
                $groupId, 
                "Usuario {$action} el grupo: {$group['name']}"
            );
        }
        
        $statusText = $newStatus === 'active' ? 'activado' : 'desactivado';
        
        echo json_encode([
            'success' => true,
            'message' => "Grupo {$statusText} exitosamente",
            'new_status' => $newStatus
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al cambiar el estado del grupo'
        ]);
    }
    
} catch (PDOException $e) {
    error_log('Error en toggle_group_status.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al cambiar el estado'
    ]);
    
} catch (Exception $e) {
    error_log('Error general en toggle_group_status.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>