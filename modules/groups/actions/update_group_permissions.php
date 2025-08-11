<?php
/*
 * modules/groups/actions/update_group_permissions.php
 * Actualizar permisos y restricciones de grupos
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
    echo json_encode(['success' => false, 'message' => 'Sin permisos de administrador']);
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
$permissions = $data['permissions'] ?? [];
$restrictions = $data['restrictions'] ?? [];

if (!$groupId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de grupo requerido']);
    exit;
}

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
    
    // Obtener permisos y restricciones actuales
    $currentQuery = "SELECT module_permissions, access_restrictions FROM user_groups WHERE id = ?";
    $stmt = $pdo->prepare($currentQuery);
    $stmt->execute([$groupId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $currentPermissions = json_decode($current['module_permissions'] ?: '{}', true);
    $currentRestrictions = json_decode($current['access_restrictions'] ?: '{}', true);
    
    // Mergear permisos (solo actualizar los que se envían)
    $updatedPermissions = $currentPermissions;
    foreach ($permissions as $key => $value) {
        $updatedPermissions[$key] = (bool)$value;
    }
    
    // Mergear restricciones (solo actualizar las que se envían)
    $updatedRestrictions = $currentRestrictions;
    foreach ($restrictions as $key => $value) {
        if (is_array($value)) {
            // Limpiar y validar IDs
            $updatedRestrictions[$key] = array_values(array_filter(array_map('intval', $value)));
        }
    }
    
    // Actualizar en base de datos
    $updateQuery = "UPDATE user_groups SET 
                    module_permissions = ?, 
                    access_restrictions = ?, 
                    updated_at = NOW() 
                    WHERE id = ?";
    
    $stmt = $pdo->prepare($updateQuery);
    $result = $stmt->execute([
        json_encode($updatedPermissions),
        json_encode($updatedRestrictions),
        $groupId
    ]);
    
    if (!$result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al actualizar grupo']);
        exit;
    }
    
   // Registrar actividad
    $changes = [];
    
    // Detectar cambios en permisos
    foreach ($permissions as $key => $value) {
        $oldValue = $currentPermissions[$key] ?? false;
        $newValue = (bool)$value;
        if ($oldValue !== $newValue) {
            $changes[] = "Permiso '{$key}': " . ($newValue ? 'ACTIVADO' : 'DESACTIVADO');
        }
    }
    
    // Detectar cambios en restricciones
    foreach ($restrictions as $key => $value) {
        $oldCount = isset($currentRestrictions[$key]) ? count($currentRestrictions[$key]) : 0;
        $newCount = is_array($value) ? count($value) : 0;
        if ($oldCount !== $newCount) {
            $changes[] = "Restricciones '{$key}': {$newCount} elementos seleccionados";
        }
    }
    
    if (!empty($changes)) {
        $logDetails = "Grupo '{$group['name']}' actualizado: " . implode(', ', $changes);
        
        $logStmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([
            $currentUser['id'],
            'group_permissions_updated',
            'user_groups',
            $groupId,
            $logDetails
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuración actualizada exitosamente',
        'updated_permissions' => $updatedPermissions,
        'updated_restrictions' => $updatedRestrictions,
        'changes_count' => count($changes)
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error PDO en update_group_permissions.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error en update_group_permissions.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>