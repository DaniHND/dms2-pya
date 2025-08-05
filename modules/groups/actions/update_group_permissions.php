<?php
/*
 * modules/groups/actions/update_group_permissions.php
 * Actualización de permisos y restricciones de grupos - Versión corregida
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
    $groupCheck = $pdo->prepare("SELECT id, name, is_system_group FROM user_groups WHERE id = ?");
    $groupCheck->execute([$groupId]);
    $group = $groupCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Grupo no encontrado']);
        exit;
    }
    
    // Validar restricciones
    $validatedRestrictions = [
        'companies' => [],
        'departments' => [],
        'document_types' => []
    ];
    
    // Validar empresas
    if (!empty($restrictions['companies']) && is_array($restrictions['companies'])) {
        $companyIds = array_map('intval', $restrictions['companies']);
        if (!empty($companyIds)) {
            $companyPlaceholders = str_repeat('?,', count($companyIds) - 1) . '?';
            $companyCheck = $pdo->prepare("SELECT id FROM companies WHERE id IN ($companyPlaceholders) AND status = 'active'");
            $companyCheck->execute($companyIds);
            $validatedRestrictions['companies'] = $companyCheck->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    
    // Validar departamentos
    if (!empty($restrictions['departments']) && is_array($restrictions['departments'])) {
        $departmentIds = array_map('intval', $restrictions['departments']);
        if (!empty($departmentIds)) {
            $departmentPlaceholders = str_repeat('?,', count($departmentIds) - 1) . '?';
            $departmentCheck = $pdo->prepare("SELECT id FROM departments WHERE id IN ($departmentPlaceholders) AND status = 'active'");
            $departmentCheck->execute($departmentIds);
            $validatedRestrictions['departments'] = $departmentCheck->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    
    // Validar tipos de documento
    if (!empty($restrictions['document_types']) && is_array($restrictions['document_types'])) {
        $docTypeIds = array_map('intval', $restrictions['document_types']);
        if (!empty($docTypeIds)) {
            $docTypePlaceholders = str_repeat('?,', count($docTypeIds) - 1) . '?';
            $docTypeCheck = $pdo->prepare("SELECT id FROM document_types WHERE id IN ($docTypePlaceholders) AND status = 'active'");
            $docTypeCheck->execute($docTypeIds);
            $validatedRestrictions['document_types'] = $docTypeCheck->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    
    // Preparar permisos para almacenamiento
    $validatedPermissions = [];
    $allowedPermissions = [
        'view', 'view_reports', 'download', 'export', 'create', 
        'edit', 'delete', 'delete_permanent', 'manage_users', 'system_config'
    ];
    
    foreach ($allowedPermissions as $permission) {
        $validatedPermissions[$permission] = isset($permissions[$permission]) && $permissions[$permission] === true;
    }
    
    $pdo->beginTransaction();
    
    // Actualizar el grupo
    $updateStmt = $pdo->prepare("
        UPDATE user_groups 
        SET 
            module_permissions = ?,
            access_restrictions = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $success = $updateStmt->execute([
        json_encode($validatedPermissions),
        json_encode($validatedRestrictions),
        $groupId
    ]);
    
    if (!$success) {
        throw new Exception('Error al actualizar el grupo');
    }
    
    // Registrar actividad (opcional - solo si existe la función)
    if (function_exists('logActivity')) {
        $description = "Permisos actualizados para el grupo '{$group['name']}'";
        logActivity(
            $currentUser['id'], 
            'update_group_permissions', 
            'user_groups', 
            $groupId, 
            $description
        );
    }
    
    $pdo->commit();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Permisos actualizados correctamente',
        'group' => [
            'id' => $groupId,
            'name' => $group['name']
        ],
        'updated' => [
            'permissions' => $validatedPermissions,
            'restrictions' => $validatedRestrictions
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error PDO en update_group_permissions: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error en update_group_permissions: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>