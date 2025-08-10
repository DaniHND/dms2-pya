<?php
/**
 * permission_functions.php - Sistema de permisos reparado
 * Versión corregida para manejar administradores y usuarios correctamente
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Obtener permisos completos de un usuario
 */
function getUserPermissions($userId = null) {
    static $cache = [];
    
    if ($userId === null) {
        $userId = SessionManager::getUserId();
    }
    
    if (!$userId) {
        return getDefaultPermissions();
    }
    
    // Usar cache para evitar consultas repetidas
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Obtener información básica del usuario
        $userQuery = "SELECT role, company_id, department_id FROM users WHERE id = ? AND status = 'active'";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return getDefaultPermissions();
        }
        
        // SI ES ADMIN = ACCESO TOTAL
        if ($user['role'] === 'admin') {
            $adminPermissions = [
                'permissions' => [
                    'view' => true,
                    'view_reports' => true,
                    'download' => true,
                    'export' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                    'delete_permanent' => true,
                    'manage_users' => true,
                    'system_config' => true
                ],
                'restrictions' => [
                    'companies' => [],      // Vacío = acceso a todas
                    'departments' => [],    // Vacío = acceso a todos
                    'document_types' => []  // Vacío = acceso a todos
                ],
                'limits' => [
                    'download_daily' => null,
                    'upload_daily' => null
                ]
            ];
            
            $cache[$userId] = $adminPermissions;
            return $adminPermissions;
        }
        
        // PARA USUARIOS NORMALES: Combinar permisos de grupos
        $permissions = [
            'view' => false,
            'view_reports' => false,  // Solo hasta reportes
            'download' => false,
            'export' => false,
            'create' => false,
            'edit' => false,
            'delete' => false,
            'delete_permanent' => false,
            'manage_users' => false,  // No pueden gestionar usuarios
            'system_config' => false // No pueden configurar sistema
        ];
        
        $restrictions = [
            'companies' => [],
            'departments' => [],
            'document_types' => []
        ];
        
        $limits = [
            'download_daily' => null,
            'upload_daily' => null
        ];
        
        // Obtener permisos de grupos del usuario
        $groupQuery = "
            SELECT ug.module_permissions, ug.access_restrictions, 
                   ug.download_limit_daily, ug.upload_limit_daily
            FROM user_groups ug
            INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
            WHERE ugm.user_id = ? AND ug.status = 'active'
        ";
        
        $groupStmt = $pdo->prepare($groupQuery);
        $groupStmt->execute([$userId]);
        $groups = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si tiene grupos, combinar permisos
        if (!empty($groups)) {
            foreach ($groups as $group) {
                // Combinar permisos (OR lógico)
                $groupPerms = json_decode($group['module_permissions'] ?: '{}', true);
                foreach ($permissions as $key => $current) {
                    if (isset($groupPerms[$key]) && $groupPerms[$key] === true) {
                        $permissions[$key] = true;
                    }
                }
                
                // Combinar restricciones (UNION)
                $groupRestrictions = json_decode($group['access_restrictions'] ?: '{}', true);
                foreach (['companies', 'departments', 'document_types'] as $type) {
                    if (!empty($groupRestrictions[$type])) {
                        $restrictions[$type] = array_unique(array_merge(
                            $restrictions[$type], 
                            $groupRestrictions[$type]
                        ));
                    }
                }
                
                // Aplicar límites más restrictivos
                if ($group['download_limit_daily'] !== null) {
                    $limits['download_daily'] = min(
                        $limits['download_daily'] ?: PHP_INT_MAX,
                        $group['download_limit_daily']
                    );
                }
                
                if ($group['upload_limit_daily'] !== null) {
                    $limits['upload_daily'] = min(
                        $limits['upload_daily'] ?: PHP_INT_MAX,
                        $group['upload_limit_daily']
                    );
                }
            }
        } else {
            // Si no tiene grupos, permisos mínimos + restricción a su empresa
            $permissions['view'] = true;
            $permissions['view_reports'] = true; // Solo hasta reportes
            $permissions['download'] = true;
            
            if ($user['company_id']) {
                $restrictions['companies'] = [$user['company_id']];
            }
            if ($user['department_id']) {
                $restrictions['departments'] = [$user['department_id']];
            }
        }
        
        $result = [
            'permissions' => $permissions,
            'restrictions' => $restrictions,
            'limits' => $limits
        ];
        
        $cache[$userId] = $result;
        return $result;
        
    } catch (Exception $e) {
        error_log('Error getting user permissions: ' . $e->getMessage());
        return getDefaultPermissions();
    }
}

/**
 * Permisos por defecto para usuarios sin configuración
 */
function getDefaultPermissions() {
    return [
        'permissions' => [
            'view' => true,
            'view_reports' => true,
            'download' => true,
            'export' => false,
            'create' => false,
            'edit' => false,
            'delete' => false,
            'delete_permanent' => false,
            'manage_users' => false,
            'system_config' => false
        ],
        'restrictions' => [
            'companies' => [],
            'departments' => [],
            'document_types' => []
        ],
        'limits' => [
            'download_daily' => null,
            'upload_daily' => null
        ]
    ];
}

/**
 * Verificar si el usuario tiene un permiso específico
 */
function hasUserPermission($permission, $userId = null) {
    $perms = getUserPermissions($userId);
    return isset($perms['permissions'][$permission]) && $perms['permissions'][$permission] === true;
}

/**
 * Verificar acceso a empresa
 */
function canAccessCompany($companyId, $userId = null) {
    if ($userId === null) {
        $userId = SessionManager::getUserId();
    }
    
    // Admin puede acceder a todo
    if (SessionManager::isAdmin()) {
        return true;
    }
    
    $perms = getUserPermissions($userId);
    
    // Si no hay restricciones de empresa, puede acceder a todas
    if (empty($perms['restrictions']['companies'])) {
        return true;
    }
    
    // Verificar si está en la lista permitida
    return in_array($companyId, $perms['restrictions']['companies']);
}

/**
 * Verificar acceso a departamento
 */
function canAccessDepartment($departmentId, $userId = null) {
    if ($userId === null) {
        $userId = SessionManager::getUserId();
    }
    
    // Admin puede acceder a todo
    if (SessionManager::isAdmin()) {
        return true;
    }
    
    $perms = getUserPermissions($userId);
    
    // Si no hay restricciones de departamento, puede acceder a todos
    if (empty($perms['restrictions']['departments'])) {
        return true;
    }
    
    // Verificar si está en la lista permitida
    return in_array($departmentId, $perms['restrictions']['departments']);
}

/**
 * Verificar acceso a tipo de documento
 */
function canAccessDocumentType($documentTypeId, $userId = null) {
    if ($userId === null) {
        $userId = SessionManager::getUserId();
    }
    
    // Admin puede acceder a todo
    if (SessionManager::isAdmin()) {
        return true;
    }
    
    $perms = getUserPermissions($userId);
    
    // Si no hay restricciones de tipo, puede acceder a todos
    if (empty($perms['restrictions']['document_types'])) {
        return true;
    }
    
    // Verificar si está en la lista permitida
    return in_array($documentTypeId, $perms['restrictions']['document_types']);
}

/**
 * Construir condiciones WHERE para consultas SQL con restricciones
 */
function buildAccessConditions($tableAlias = 'd', $userId = null) {
    if ($userId === null) {
        $userId = SessionManager::getUserId();
    }
    
    // Admin no tiene restricciones
    if (SessionManager::isAdmin()) {
        return [
            'conditions' => [],
            'params' => []
        ];
    }
    
    $perms = getUserPermissions($userId);
    $conditions = [];
    $params = [];
    
    // Aplicar restricciones de empresa
    if (!empty($perms['restrictions']['companies'])) {
        $placeholders = str_repeat('?,', count($perms['restrictions']['companies']) - 1) . '?';
        $conditions[] = "{$tableAlias}.company_id IN ($placeholders)";
        $params = array_merge($params, $perms['restrictions']['companies']);
    }
    
    // Aplicar restricciones de departamento
    if (!empty($perms['restrictions']['departments'])) {
        $placeholders = str_repeat('?,', count($perms['restrictions']['departments']) - 1) . '?';
        $conditions[] = "{$tableAlias}.department_id IN ($placeholders)";
        $params = array_merge($params, $perms['restrictions']['departments']);
    }
    
    // Aplicar restricciones de tipo de documento
    if (!empty($perms['restrictions']['document_types'])) {
        $placeholders = str_repeat('?,', count($perms['restrictions']['document_types']) - 1) . '?';
        $conditions[] = "{$tableAlias}.document_type_id IN ($placeholders)";
        $params = array_merge($params, $perms['restrictions']['document_types']);
    }
    
    return [
        'conditions' => $conditions,
        'params' => $params
    ];
}

/**
 * Obtener empresas accesibles para el usuario
 */
function getAccessibleCompanies($userId = null) {
    if ($userId === null) {
        $userId = SessionManager::getUserId();
    }
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Admin ve todas las empresas
        if (SessionManager::isAdmin()) {
            $query = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $perms = getUserPermissions($userId);
        
        // Si no hay restricciones, ve todas
        if (empty($perms['restrictions']['companies'])) {
            $query = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Solo empresas permitidas
        $placeholders = str_repeat('?,', count($perms['restrictions']['companies']) - 1) . '?';
        $query = "SELECT id, name FROM companies WHERE id IN ($placeholders) AND status = 'active' ORDER BY name";
        $stmt = $pdo->prepare($query);
        $stmt->execute($perms['restrictions']['companies']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log('Error getting accessible companies: ' . $e->getMessage());
        return [];
    }
}

/**
 * Obtener departamentos accesibles para el usuario
 */
function getAccessibleDepartments($companyId = null, $userId = null) {
    if ($userId === null) {
        $userId = SessionManager::getUserId();
    }
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $conditions = ["status = 'active'"];
        $params = [];
        
        // Filtro por empresa si se especifica
        if ($companyId) {
            $conditions[] = "company_id = ?";
            $params[] = $companyId;
        }
        
        // Admin ve todos los departamentos
        if (SessionManager::isAdmin()) {
            $whereClause = implode(' AND ', $conditions);
            $query = "SELECT id, name, company_id FROM departments WHERE $whereClause ORDER BY name";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $perms = getUserPermissions($userId);
        
        // Aplicar restricciones de departamento
        if (!empty($perms['restrictions']['departments'])) {
            $placeholders = str_repeat('?,', count($perms['restrictions']['departments']) - 1) . '?';
            $conditions[] = "id IN ($placeholders)";
            $params = array_merge($params, $perms['restrictions']['departments']);
        }
        
        // Aplicar restricciones de empresa
        if (!empty($perms['restrictions']['companies'])) {
            $placeholders = str_repeat('?,', count($perms['restrictions']['companies']) - 1) . '?';
            $conditions[] = "company_id IN ($placeholders)";
            $params = array_merge($params, $perms['restrictions']['companies']);
        }
        
        $whereClause = implode(' AND ', $conditions);
        $query = "SELECT id, name, company_id FROM departments WHERE $whereClause ORDER BY name";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log('Error getting accessible departments: ' . $e->getMessage());
        return [];
    }
}

/**
 * Limpiar cache de permisos
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
 * Generar mensaje informativo sobre las restricciones del usuario
 */
function getRestrictionsMessage($userId = null) {
    if (SessionManager::isAdmin()) {
        return null;
    }
    
    $perms = getUserPermissions($userId);
    $messages = [];
    
    if (!empty($perms['restrictions']['companies'])) {
        $count = count($perms['restrictions']['companies']);
        $messages[] = "Limitado a $count empresa(s)";
    }
    
    if (!empty($perms['restrictions']['departments'])) {
        $count = count($perms['restrictions']['departments']);
        $messages[] = "Limitado a $count departamento(s)";
    }
    
    if (!empty($perms['restrictions']['document_types'])) {
        $count = count($perms['restrictions']['document_types']);
        $messages[] = "Limitado a $count tipo(s) de documento";
    }
    
    return !empty($messages) ? implode(', ', $messages) : null;
}

?>