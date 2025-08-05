<?php
/**
 * includes/permission_functions.php
 * Funciones globales para aplicar permisos de grupos en toda la aplicación
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

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
            'permissions' => [],
            'restrictions' => ['companies' => [], 'departments' => [], 'document_types' => []]
        ];
    }
    
    // Usar cache para evitar consultas repetidas
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Obtener todos los grupos del usuario con sus permisos
        $query = "
            SELECT 
                ug.module_permissions,
                ug.access_restrictions,
                ug.download_limit_daily,
                ug.upload_limit_daily
            FROM user_group_members ugm
            INNER JOIN user_groups ug ON ugm.group_id = ug.id
            WHERE ugm.user_id = ? AND ug.status = 'active'
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Consolidar permisos (si el usuario está en múltiples grupos)
        $combinedPermissions = [];
        $combinedRestrictions = ['companies' => [], 'departments' => [], 'document_types' => []];
        $minDownloadLimit = null;
        $minUploadLimit = null;
        
        foreach ($groups as $group) {
            // Decodificar permisos
            $permissions = json_decode($group['module_permissions'] ?: '{}', true);
            foreach ($permissions as $perm => $value) {
                if ($value === true) {
                    $combinedPermissions[$perm] = true;
                }
            }
            
            // Consolidar restricciones (unión de todas las restricciones)
            $restrictions = json_decode($group['access_restrictions'] ?: '{}', true);
            if (!empty($restrictions['companies'])) {
                $combinedRestrictions['companies'] = array_merge(
                    $combinedRestrictions['companies'], 
                    $restrictions['companies']
                );
            }
            if (!empty($restrictions['departments'])) {
                $combinedRestrictions['departments'] = array_merge(
                    $combinedRestrictions['departments'], 
                    $restrictions['departments']
                );
            }
            if (!empty($restrictions['document_types'])) {
                $combinedRestrictions['document_types'] = array_merge(
                    $combinedRestrictions['document_types'], 
                    $restrictions['document_types']
                );
            }
            
            // Límites (tomar el más restrictivo)
            if ($group['download_limit_daily'] !== null) {
                $minDownloadLimit = $minDownloadLimit === null ? 
                    $group['download_limit_daily'] : 
                    min($minDownloadLimit, $group['download_limit_daily']);
            }
            if ($group['upload_limit_daily'] !== null) {
                $minUploadLimit = $minUploadLimit === null ? 
                    $group['upload_limit_daily'] : 
                    min($minUploadLimit, $group['upload_limit_daily']);
            }
        }
        
        // Remover duplicados en restricciones
        $combinedRestrictions['companies'] = array_unique($combinedRestrictions['companies']);
        $combinedRestrictions['departments'] = array_unique($combinedRestrictions['departments']);
        $combinedRestrictions['document_types'] = array_unique($combinedRestrictions['document_types']);
        
        $result = [
            'permissions' => $combinedPermissions,
            'restrictions' => $combinedRestrictions,
            'limits' => [
                'download' => $minDownloadLimit,
                'upload' => $minUploadLimit
            ]
        ];
        
        $cache[$userId] = $result;
        return $result;
        
    } catch (Exception $e) {
        error_log('Error obteniendo permisos de usuario: ' . $e->getMessage());
        return [
            'permissions' => [],
            'restrictions' => ['companies' => [], 'departments' => [], 'document_types' => []]
        ];
    }
}

/**
 * Verificar si el usuario tiene un permiso específico
 */
function hasUserPermission($permission, $userId = null) {
    $userPerms = getUserPermissions($userId);
    return isset($userPerms['permissions'][$permission]) && $userPerms['permissions'][$permission] === true;
}

/**
 * Obtener empresas accesibles para el usuario
 */
function getAccessibleCompanies($userId = null) {
    $userPerms = getUserPermissions($userId);
    $currentUser = SessionManager::getCurrentUser();
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $query = "SELECT id, name FROM companies WHERE status = 'active'";
        $params = [];
        
        // Si hay restricciones de empresa, aplicarlas
        if (!empty($userPerms['restrictions']['companies'])) {
            $placeholders = str_repeat('?,', count($userPerms['restrictions']['companies']) - 1) . '?';
            $query .= " AND id IN ($placeholders)";
            $params = $userPerms['restrictions']['companies'];
        }
        
        $query .= " ORDER BY name";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log('Error obteniendo empresas accesibles: ' . $e->getMessage());
        return [];
    }
}

/**
 * Obtener departamentos accesibles para el usuario
 */
function getAccessibleDepartments($companyId = null, $userId = null) {
    $userPerms = getUserPermissions($userId);
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $query = "
            SELECT d.id, d.name, c.name as company_name, d.company_id
            FROM departments d 
            LEFT JOIN companies c ON d.company_id = c.id 
            WHERE d.status = 'active' AND c.status = 'active'
        ";
        $params = [];
        
        // Filtrar por empresa si se especifica
        if ($companyId) {
            $query .= " AND d.company_id = ?";
            $params[] = $companyId;
        }
        
        // Si hay restricciones de empresa, aplicarlas
        if (!empty($userPerms['restrictions']['companies'])) {
            $placeholders = str_repeat('?,', count($userPerms['restrictions']['companies']) - 1) . '?';
            $query .= " AND d.company_id IN ($placeholders)";
            $params = array_merge($params, $userPerms['restrictions']['companies']);
        }
        
        // Si hay restricciones de departamento, aplicarlas
        if (!empty($userPerms['restrictions']['departments'])) {
            $placeholders = str_repeat('?,', count($userPerms['restrictions']['departments']) - 1) . '?';
            $query .= " AND d.id IN ($placeholders)";
            $params = array_merge($params, $userPerms['restrictions']['departments']);
        }
        
        $query .= " ORDER BY c.name, d.name";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
    $userPerms = getUserPermissions($userId);
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $query = "
            SELECT 
                d.id,
                d.company_id,
                d.department_id,
                d.document_type_id,
                d.status
            FROM documents d
            WHERE d.id = ?
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$documentId]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$document) {
            return false;
        }
        
        // Verificar estado del documento
        if ($document['status'] === 'deleted' && !hasUserPermission('delete_permanent', $userId)) {
            return false;
        }
        
        // Verificar restricciones de empresa
        if (!empty($userPerms['restrictions']['companies'])) {
            if (!in_array($document['company_id'], $userPerms['restrictions']['companies'])) {
                return false;
            }
        }
        
        // Verificar restricciones de departamento
        if (!empty($userPerms['restrictions']['departments'])) {
            if (!in_array($document['department_id'], $userPerms['restrictions']['departments'])) {
                return false;
            }
        }
        
        // Verificar restricciones de tipo de documento
        if (!empty($userPerms['restrictions']['document_types'])) {
            if (!in_array($document['document_type_id'], $userPerms['restrictions']['document_types'])) {
                return false;
            }
        }
        
        return true;
        
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
    if (!hasUserPermission('delete_permanent', $userId)) {
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
?>