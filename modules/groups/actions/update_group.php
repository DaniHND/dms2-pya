<?php
/*
 * modules/groups/actions/update_group.php
 * Actualizar datos de un grupo existente - VERSIÓN CON RUTAS ABSOLUTAS
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
    $groupName = trim($_POST['group_name'] ?? '');
    $groupDescription = trim($_POST['group_description'] ?? '');
    $groupStatus = $_POST['group_status'] ?? 'active';
    
    // Validaciones
    if (!$groupId || !is_numeric($groupId)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de grupo inválido'
        ]);
        exit;
    }
    
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
    
    // Verificar que el grupo existe y obtener datos actuales
    $checkQuery = "
        SELECT id, name, status, is_system_group 
        FROM user_groups 
        WHERE id = :group_id AND deleted_at IS NULL
    ";
    
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $currentGroup = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentGroup) {
        echo json_encode([
            'success' => false,
            'message' => 'Grupo no encontrado'
        ]);
        exit;
    }
    
    // Verificar si es un grupo del sistema
    if ($currentGroup['is_system_group'] == 1) {
        echo json_encode([
            'success' => false,
            'message' => 'No se puede modificar un grupo del sistema'
        ]);
        exit;
    }
    
    // Verificar si ya existe otro grupo con el mismo nombre (excluyendo el actual)
    $duplicateQuery = "
        SELECT id 
        FROM user_groups 
        WHERE name = :name AND id != :group_id AND deleted_at IS NULL
    ";
    
    $duplicateStmt = $pdo->prepare($duplicateQuery);
    $duplicateStmt->bindParam(':name', $groupName);
    $duplicateStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    $duplicateStmt->execute();
    
    if ($duplicateStmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe otro grupo con este nombre'
        ]);
        exit;
    }
    
    // Actualizar datos del grupo
    $updateQuery = "
        UPDATE user_groups 
        SET 
            name = :name,
            description = :description,
            status = :status,
            updated_at = NOW()
        WHERE id = :group_id
    ";
    
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->bindParam(':name', $groupName);
    $updateStmt->bindParam(':description', $groupDescription);
    $updateStmt->bindParam(':status', $groupStatus);
    $updateStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    
    if ($updateStmt->execute()) {
        // Registrar actividad
        if (function_exists('logActivity')) {
            $changes = [];
            if ($currentGroup['name'] !== $groupName) {
                $changes[] = "nombre de '{$currentGroup['name']}' a '{$groupName}'";
            }
            if ($currentGroup['status'] !== $groupStatus) {
                $changes[] = "estado a '{$groupStatus}'";
            }
            
            $changeDescription = !empty($changes) 
                ? 'Usuario actualizó ' . implode(', ', $changes) . " en el grupo: {$groupName}"
                : "Usuario actualizó el grupo: {$groupName}";
                
            logActivity(
                $currentUser['id'], 
                'update_group', 
                'groups', 
                $groupId, 
                $changeDescription
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Grupo actualizado exitosamente'
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar el grupo'
        ]);
    }
    
} catch (PDOException $e) {
    error_log('Error en update_group.php: ' . $e->getMessage());
    
    // Verificar si es error de duplicación
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe otro grupo con este nombre'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error de base de datos al actualizar el grupo'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Error general en update_group.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>