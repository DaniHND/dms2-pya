<?php
/*
 * modules/groups/actions/update_group_permissions.php
 * Actualizar permisos y restricciones de un grupo
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

try {
    // Verificar autenticación
    if (!SessionManager::isLoggedIn()) {
        throw new Exception("Usuario no autenticado");
    }
    
    $currentUser = SessionManager::getCurrentUser();
    if ($currentUser['role'] !== 'admin') {
        throw new Exception("Permisos insuficientes. Se requiere rol de administrador");
    }
    
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método HTTP no permitido");
    }
    
    // Obtener datos JSON
    $permissionsJson = $_POST['permissions_data'] ?? null;
    
    if (!$permissionsJson) {
        throw new Exception("No se recibieron datos de permisos");
    }
    
    $permissionsData = json_decode($permissionsJson, true);
    
    if (!$permissionsData || !isset($permissionsData['group_id'])) {
        throw new Exception("Datos de permisos inválidos");
    }
    
    $groupId = $permissionsData['group_id'];
    $accessRestrictions = $permissionsData['access_restrictions'] ?? [];
    $modulePermissions = $permissionsData['module_permissions'] ?? [];
    
    if (!is_numeric($groupId)) {
        throw new Exception("ID de grupo inválido");
    }
    
    $groupId = (int)$groupId;
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Verificar que el grupo existe y no es del sistema
    $groupQuery = "SELECT id, name, is_system_group FROM user_groups WHERE id = ?";
    $groupStmt = $pdo->prepare($groupQuery);
    $groupStmt->execute([$groupId]);
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        throw new Exception("Grupo no encontrado");
    }
    
    if ($group['is_system_group'] == 1) {
        throw new Exception("No se puede modificar un grupo del sistema");
    }
    
    // Validar y procesar restricciones de acceso
    $processedRestrictions = [
        'companies' => [],
        'departments' => [],
        'document_types' => []
    ];
    
    // Procesar empresas
    if (isset($accessRestrictions['companies']) && is_array($accessRestrictions['companies'])) {
        foreach ($accessRestrictions['companies'] as $company) {
            if (isset($company['id']) && is_numeric($company['id'])) {
                $processedRestrictions['companies'][] = [
                    'id' => (int)$company['id'],
                    'name' => $company['name'] ?? ''
                ];
            }
        }
    }
    
    // Procesar departamentos
    if (isset($accessRestrictions['departments']) && is_array($accessRestrictions['departments'])) {
        foreach ($accessRestrictions['departments'] as $department) {
            if (isset($department['id']) && is_numeric($department['id'])) {
                $processedRestrictions['departments'][] = [
                    'id' => (int)$department['id'],
                    'name' => $department['name'] ?? ''
                ];
            }
        }
    }
    
    // Procesar tipos de documentos
    if (isset($accessRestrictions['document_types']) && is_array($accessRestrictions['document_types'])) {
        foreach ($accessRestrictions['document_types'] as $docType) {
            if (isset($docType['id']) && is_numeric($docType['id'])) {
                $processedRestrictions['document_types'][] = [
                    'id' => (int)$docType['id'],
                    'name' => $docType['name'] ?? ''
                ];
            }
        }
    }
    
    // Validar y procesar permisos de módulo
    $processedPermissions = [
        'view' => (bool)($modulePermissions['view'] ?? true),
        'view_reports' => (bool)($modulePermissions['view_reports'] ?? true),
        'download' => (bool)($modulePermissions['download'] ?? false),
        'export' => (bool)($modulePermissions['export'] ?? false),
        'create' => (bool)($modulePermissions['create'] ?? false),
        'edit' => (bool)($modulePermissions['edit'] ?? false),
        'delete' => (bool)($modulePermissions['delete'] ?? false),
        'delete_permanent' => (bool)($modulePermissions['delete_permanent'] ?? false),
        'manage_users' => (bool)($modulePermissions['manage_users'] ?? false),
        'system_config' => (bool)($modulePermissions['system_config'] ?? false)
    ];
    
    // Procesar límites
    $downloadLimit = null;
    $uploadLimit = null;
    
    if (isset($modulePermissions['download_limit']) && is_numeric($modulePermissions['download_limit'])) {
        $downloadLimit = (int)$modulePermissions['download_limit'];
    }
    
    if (isset($modulePermissions['upload_limit']) && is_numeric($modulePermissions['upload_limit'])) {
        $uploadLimit = (int)$modulePermissions['upload_limit'];
    }
    
    // Comenzar transacción
    $pdo->beginTransaction();
    
    try {
        // Actualizar permisos y restricciones en la base de datos
        $updateQuery = "
            UPDATE user_groups 
            SET 
                module_permissions = ?,
                access_restrictions = ?,
                download_limit_daily = ?,
                upload_limit_daily = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([
            json_encode($processedPermissions),
            json_encode($processedRestrictions),
            $downloadLimit,
            $uploadLimit,
            $groupId
        ]);
        
        if ($updateStmt->rowCount() === 0) {
            throw new Exception("No se pudo actualizar el grupo. Posiblemente no hubo cambios.");
        }
        
        // Confirmar transacción
        $pdo->commit();
        
        // Registrar actividad
        if (function_exists('logActivity')) {
            logActivity(
                $currentUser['id'], 
                'update_group_permissions', 
                'groups', 
                $groupId, 
                "Permisos actualizados para el grupo: {$group['name']}"
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Permisos del grupo actualizados exitosamente",
            'group_id' => $groupId,
            'group_name' => $group['name'],
            'updated_permissions' => $processedPermissions,
            'updated_restrictions' => $processedRestrictions,
            'download_limit' => $downloadLimit,
            'upload_limit' => $uploadLimit
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log('Error PDO en update_group_permissions.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage(),
        'error_code' => 'DB_ERROR'
    ]);
    
} catch (Exception $e) {
    error_log('Error en update_group_permissions.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GENERAL_ERROR'
    ]);
}
?>