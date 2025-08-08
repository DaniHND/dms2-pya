<?php
/**
 * includes/permission_functions.php
 * Funciones globales para aplicar permisos de grupos en toda la aplicación - VERSIÓN SIMPLIFICADA
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/PermissionManager.php';

/**
 * Obtener permisos y restricciones consolidados del usuario
 */
function getUserPermissions($userId = null) {
    static $cache = [];
    
    if ($userId === null) {
        $currentUser = SessionManager::getCurrentUser();
        $userId = $currentUser['id'] ?? null;
    }
    
    if (!$userId) {
        return [
            'permissions' => [
                'view' => false,
                'download' => false,
                'create' => false,
                'edit' => false,
                'delete' => false
            ],
            'restrictions' => ['companies' => [], 'departments' => [], 'document_types' => []]
        ];
    }
    
    // Usar cache para evitar consultas repetidas
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    
    try {
        $permissionManager = new PermissionManager($userId);
        
        $result = [
            'permissions' => $permissionManager->getAllPermissions(),
            'restrictions' => $permissionManager->getAllRestrictions()
        ];
        
        $cache[$userId] = $result;
        return $result;
        
    } catch (Exception $e) {
        error_log('Error obteniendo permisos de usuario: ' . $e->getMessage());
        return [
            'permissions' => [
                'view' => false,
                'download' => false,
                'create' => false,
                'edit' => false,
                'delete' => false
            ],
            'restrictions' => ['companies' => [], 'departments' => [], 'document_types' => []]
        ];
    }
}

/**
 * Verificar si el usuario tiene un permiso específico
 */
function hasUserPermission($permission, $userId = null) {
    try {
        $permissionManager = getPermissionManager($userId);
        return $permissionManager->hasPermission($permission);
    } catch (Exception $e) {
        error_log('Error verificando permiso de usuario: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener empresas accesibles para el usuario
 */
function getAccessibleCompanies($userId = null) {
    try {
        $permissionManager = getPermissionManager($userId);
        return $permissionManager->getAccessibleCompanies();
    } catch (Exception $e) {
        error_log('Error obteniendo empresas accesibles: ' . $e->getMessage());
        return [];
    }
}

/**
 * Obtener departamentos accesibles para el usuario
 */
function getAccessibleDepartments($companyId = null, $userId = null) {
    try {
        $permissionManager = getPermissionManager($userId);
        return $permissionManager->getAccessibleDepartments($companyId);
    } catch (Exception $e) {
        error_log('Error obteniendo departamentos accesibles: ' . $e->getMessage());
        return [];
    }
}

/**
 * Obtener tipos de documentos accesibles para el usuario
 */
function getAccessibleDocumentTypes($userId = null) {
    $userPerms = getUserPermissions($userId);
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $query = "SELECT id, name, icon, color FROM document_types WHERE status = 'active'";
        $params = [];
        
        // Si hay restricciones de tipo de documento, aplicarlas
        if (!empty($userPerms['restrictions']['document_types'])) {
            $placeholders = str_repeat('?,', count($userPerms['restrictions']['document_types']) - 1) . '?';
            $query .= " AND id IN ($placeholders)";
            $params = $userPerms['restrictions']['document_types'];
        }
        
        $query .= " ORDER BY name";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log('Error obteniendo tipos de documentos accesibles: ' . $e->getMessage());
        return [];
    }
}

/**
 * Verificar si el usuario puede acceder a un documento específico
 */
function canAccessDocument($documentId, $userId = null) {
    try {
        $permissionManager = getPermissionManager($userId);
        return $permissionManager->canAccessDocument($documentId);
    } catch (Exception $e) {
        error_log('Error verificando acceso a documento: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtener condiciones WHERE para filtrar documentos según permisos
 */
function getDocumentFilterConditions($userId = null) {
    $userPerms = getUserPermissions($userId);
    $conditions = [];
    $params = [];
    
    // Excluir documentos eliminados si no tiene permisos
    if (!hasUserPermission('delete', $userId)) {
        $conditions[] = "d.status != 'deleted'";
    }
    
    // Aplicar restricciones de empresa
    if (!empty($userPerms['restrictions']['companies'])) {
        $placeholders = str_repeat('?,', count($userPerms['restrictions']['companies']) - 1) . '?';
        $conditions[] = "d.company_id IN ($placeholders)";
        $params = array_merge($params, $userPerms['restrictions']['companies']);
    }
    
    // Aplicar restricciones de departamento
    if (!empty($userPerms['restrictions']['departments'])) {
        $placeholders = str_repeat('?,', count($userPerms['restrictions']['departments']) - 1) . '?';
        $conditions[] = "d.department_id IN ($placeholders)";
        $params = array_merge($params, $userPerms['restrictions']['departments']);
    }
    
    // Aplicar restricciones de tipo de documento
    if (!empty($userPerms['restrictions']['document_types'])) {
        $placeholders = str_repeat('?,', count($userPerms['restrictions']['document_types']) - 1) . '?';
        $conditions[] = "d.document_type_id IN ($placeholders)";
        $params = array_merge($params, $userPerms['restrictions']['document_types']);
    }
    
    return [
        'conditions' => $conditions,
        'params' => $params
    ];
}

/**
 * Crear mensaje informativo sobre restricciones activas
 */
function getRestrictionsMessage($userId = null) {
    $userPerms = getUserPermissions($userId);
    $messages = [];
    
    if (!empty($userPerms['restrictions']['companies'])) {
        $count = count($userPerms['restrictions']['companies']);
        $messages[] = "Limitado a $count empresa(s)";
    }
    
    if (!empty($userPerms['restrictions']['departments'])) {
        $count = count($userPerms['restrictions']['departments']);
        $messages[] = "Limitado a $count departamento(s)";
    }
    
    if (!empty($userPerms['restrictions']['document_types'])) {
        $count = count($userPerms['restrictions']['document_types']);
        $messages[] = "Limitado a $count tipo(s) de documento";
    }
    
    return !empty($messages) ? implode(', ', $messages) : null;
}

/**
 * Limpiar cache de permisos (útil cuando se actualizan los grupos)
 */
function clearPermissionsCache($userId = null) {
    static $cache = [];
    
    if ($userId) {
        unset($cache[$userId]);
    } else {
        $cache = [];
    }
}

/**
 * Verificar si un usuario puede ver una empresa específica
 */
function canUserAccessCompany($companyId, $userId = null) {
    try {
        $permissionManager = getPermissionManager($userId);
        return $permissionManager->canAccessCompany($companyId);
    } catch (Exception $e) {
        error_log('Error verificando acceso a empresa: ' . $e->getMessage());
        return false;
    }
}

/**
 * Verificar si un usuario puede ver un departamento específico
 */
function canUserAccessDepartment($departmentId, $userId = null) {
    try {
        $permissionManager = getPermissionManager($userId);
        return $permissionManager->canAccessDepartment($departmentId);
    } catch (Exception $e) {
        error_log('Error verificando acceso a departamento: ' . $e->getMessage());
        return false;
    }
}
?>