<?php
/**
 * includes/group_permissions.php
 * Sistema de permisos de grupos con prioridad absoluta
 * Los grupos tienen prioridad sobre los roles individuales
 */

class GroupPermissionManager {
    private $pdo;
    private $cache = [];
    
    public function __construct() {
        $database = new Database();
        $this->pdo = $database->getConnection();
    }
    
    /**
     * Obtiene permisos efectivos del usuario basado en sus grupos
     * PRIORIDAD: Grupos > Permisos individuales de rol
     */
    public function getUserEffectivePermissions($userId) {
        // Cache para optimizar rendimiento
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }
        
        try {
            // Obtener usuario básico
            $userQuery = "SELECT id, username, role, company_id, department_id, status FROM users WHERE id = ?";
            $userStmt = $this->pdo->prepare($userQuery);
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || $user['status'] !== 'active') {
                return $this->getDefaultPermissions();
            }
            
            // Obtener grupos activos del usuario
            $groupQuery = "
                SELECT ug.id, ug.name, ug.module_permissions, ug.access_restrictions
                FROM user_groups ug
                INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
                WHERE ugm.user_id = ? AND ug.status = 'active'
                ORDER BY ug.created_at ASC
            ";
            
            $groupStmt = $this->pdo->prepare($groupQuery);
            $groupStmt->execute([$userId]);
            $groups = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Permisos específicos del sistema (6 acciones principales - ACTUALIZADO)
            $permissions = [
                'upload_files' => false,      // 1. Subir archivo
                'view_files' => false,        // 2. Ver archivos (inbox)
                'create_folders' => false,    // 3. Crear carpetas
                'download_files' => false,    // 4. Descargar
                'delete_files' => false,      // 5. Eliminar archivos
                'move_files' => false         // 6. Mover archivos (NUEVO)
            ];
            
            $restrictions = [
                'companies' => [],
                'departments' => [],
                'document_types' => []
            ];
            
            if (!empty($groups)) {
                // Usuario tiene grupos - PRIORIDAD ABSOLUTA
                foreach ($groups as $group) {
                    $groupPerms = json_decode($group['module_permissions'] ?: '{}', true);
                    $groupRestrictions = json_decode($group['access_restrictions'] ?: '{}', true);
                    
                    // Combinar permisos (OR lógico - si algún grupo permite, se permite)
                    foreach ($permissions as $key => $current) {
                        if (isset($groupPerms[$key]) && $groupPerms[$key] === true) {
                            $permissions[$key] = true;
                        }
                    }
                    
                    // Combinar restricciones (UNION - todas las permitidas)
                    foreach (['companies', 'departments', 'document_types'] as $type) {
                        if (isset($groupRestrictions[$type]) && is_array($groupRestrictions[$type])) {
                            $restrictions[$type] = array_unique(array_merge(
                                $restrictions[$type], 
                                $groupRestrictions[$type]
                            ));
                        }
                    }
                }
                
                $result = [
                    'has_groups' => true,
                    'permissions' => $permissions,
                    'restrictions' => $restrictions,
                    'user_role' => $user['role'], // Solo informativo, no afecta permisos
                    'user_company_id' => $user['company_id'],
                    'user_department_id' => $user['department_id']
                ];
            } else {
                // Usuario sin grupos - SIN ACCESO por seguridad
                $result = [
                    'has_groups' => false,
                    'permissions' => $permissions, // Todos en false
                    'restrictions' => $restrictions, // Vacías
                    'user_role' => $user['role'],
                    'user_company_id' => $user['company_id'],
                    'user_department_id' => $user['department_id']
                ];
            }
            
            $this->cache[$userId] = $result;
            return $result;
            
        } catch (Exception $e) {
            error_log("Error getting user permissions: " . $e->getMessage());
            return $this->getDefaultPermissions();
        }
    }
    
    /**
     * Verifica si un usuario puede realizar una acción específica
     */
    public function canUserPerformAction($userId, $action) {
        $permissions = $this->getUserEffectivePermissions($userId);
        return $permissions['permissions'][$action] ?? false;
    }
    
    /**
     * Verifica si un usuario puede acceder a una empresa específica
     */
    public function canUserAccessCompany($userId, $companyId) {
        $permissions = $this->getUserEffectivePermissions($userId);
        
        // Si no tiene grupos, no puede acceder
        if (!$permissions['has_groups']) {
            return false;
        }
        
        // Si es admin, puede acceder a todo
        if ($permissions['user_role'] === 'admin') {
            return true;
        }
        
        $allowedCompanies = $permissions['restrictions']['companies'];
        
        // Si no hay restricciones de empresas, puede acceder a todas
        if (empty($allowedCompanies)) {
            return true;
        }
        
        // Verificar si la empresa está en la lista de permitidas
        return in_array((int)$companyId, $allowedCompanies);
    }
    
    /**
     * Verifica si un usuario puede acceder a un departamento específico
     */
    public function canUserAccessDepartment($userId, $departmentId) {
        $permissions = $this->getUserEffectivePermissions($userId);
        
        // Si no tiene grupos, no puede acceder
        if (!$permissions['has_groups']) {
            return false;
        }
        
        // Si es admin, puede acceder a todo
        if ($permissions['user_role'] === 'admin') {
            return true;
        }
        
        $allowedDepartments = $permissions['restrictions']['departments'];
        
        // Si no hay restricciones de departamentos, puede acceder a todos
        if (empty($allowedDepartments)) {
            return true;
        }
        
        // Verificar si el departamento está en la lista de permitidos
        return in_array((int)$departmentId, $allowedDepartments);
    }
    
    /**
     * Verifica si un usuario puede acceder a un tipo de documento específico
     */
    public function canUserAccessDocumentType($userId, $documentTypeId) {
        $permissions = $this->getUserEffectivePermissions($userId);
        
        // Si no tiene grupos, no puede acceder
        if (!$permissions['has_groups']) {
            return false;
        }
        
        // Si es admin, puede acceder a todo
        if ($permissions['user_role'] === 'admin') {
            return true;
        }
        
        $allowedDocTypes = $permissions['restrictions']['document_types'];
        
        // Si no hay restricciones de tipos de documento, puede acceder a todos
        if (empty($allowedDocTypes)) {
            return true;
        }
        
        // Verificar si el tipo de documento está en la lista de permitidos
        return in_array((int)$documentTypeId, $allowedDocTypes);
    }
    
    /**
     * Obtiene las empresas a las que el usuario tiene acceso
     */
    public function getUserAllowedCompanies($userId) {
        $permissions = $this->getUserEffectivePermissions($userId);
        
        if (!$permissions['has_groups']) {
            return [];
        }
        
        return $permissions['restrictions']['companies'];
    }
    
    /**
     * Obtiene los departamentos a los que el usuario tiene acceso
     */
    public function getUserAllowedDepartments($userId, $companyId = null) {
        $permissions = $this->getUserEffectivePermissions($userId);
        
        if (!$permissions['has_groups']) {
            return [];
        }
        
        return $permissions['restrictions']['departments'];
    }
    
    /**
     * Obtiene los tipos de documento a los que el usuario tiene acceso
     */
    public function getUserAllowedDocumentTypes($userId) {
        $permissions = $this->getUserEffectivePermissions($userId);
        
        if (!$permissions['has_groups']) {
            return [];
        }
        
        return $permissions['restrictions']['document_types'];
    }
    
    /**
     * Construye cláusula WHERE para filtrar documentos basado en permisos del usuario
     */
    public function buildDocumentFilterClause($userId, $alias = 'd') {
        $permissions = $this->getUserEffectivePermissions($userId);
        
        if (!$permissions['has_groups']) {
            return ['clause' => '1 = 0', 'params' => []]; // Sin acceso
        }
        
        // Si es admin, acceso total
        if ($permissions['user_role'] === 'admin') {
            return ['clause' => '1 = 1', 'params' => []];
        }
        
        $conditions = [];
        $params = [];
        
        // Filtrar por empresas
        $companyIds = $permissions['restrictions']['companies'];
        if (!empty($companyIds)) {
            $placeholders = str_repeat('?,', count($companyIds) - 1) . '?';
            $conditions[] = "{$alias}.company_id IN ($placeholders)";
            $params = array_merge($params, $companyIds);
        }
        
        // Filtrar por departamentos
        $departmentIds = $permissions['restrictions']['departments'];
        if (!empty($departmentIds)) {
            $placeholders = str_repeat('?,', count($departmentIds) - 1) . '?';
            $conditions[] = "{$alias}.department_id IN ($placeholders)";
            $params = array_merge($params, $departmentIds);
        }
        
        // Filtrar por tipos de documento
        $docTypeIds = $permissions['restrictions']['document_types'];
        if (!empty($docTypeIds)) {
            $placeholders = str_repeat('?,', count($docTypeIds) - 1) . '?';
            $conditions[] = "{$alias}.document_type_id IN ($placeholders)";
            $params = array_merge($params, $docTypeIds);
        }
        
        if (empty($conditions)) {
            return ['clause' => '1 = 1', 'params' => []];
        }
        
        return [
            'clause' => '(' . implode(' AND ', $conditions) . ')',
            'params' => $params
        ];
    }
    
    /**
     * Permisos por defecto (sin acceso)
     */
    // Cambiar los permisos por defecto:
private function getDefaultPermissions() {
    return [
        'has_groups' => false,
        'permissions' => [
            'upload_files' => false,
            'view_files' => false,
            'create_folders' => false,
            'download_files' => false,
            'delete_files' => false
            // Quitar: 'move_files' => false
        ],
        'restrictions' => [
            'companies' => [],
            'departments' => [],
            'document_types' => []
        ],
        'user_role' => null,
        'user_company_id' => null,
        'user_department_id' => null
    ];
}
    
    /**
     * Limpia la cache de permisos (útil después de cambios)
     */
    public function clearCache($userId = null) {
        if ($userId) {
            unset($this->cache[$userId]);
        } else {
            $this->cache = [];
        }
    }
}

// Instancia global para uso en toda la aplicación
$GLOBALS['groupPermissionManager'] = new GroupPermissionManager();

/**
 * Funciones helper para facilitar el uso
 */
function getUserGroupPermissions($userId) {
    return $GLOBALS['groupPermissionManager']->getUserEffectivePermissions($userId);
}

function canUserUploadFiles($userId) {
    return $GLOBALS['groupPermissionManager']->canUserPerformAction($userId, 'upload_files');
}

function canUserViewFiles($userId) {
    return $GLOBALS['groupPermissionManager']->canUserPerformAction($userId, 'view_files');
}

function canUserCreateFolders($userId) {
    return $GLOBALS['groupPermissionManager']->canUserPerformAction($userId, 'create_folders');
}

function canUserDownloadFiles($userId) {
    return $GLOBALS['groupPermissionManager']->canUserPerformAction($userId, 'download_files');
}

function canUserDeleteFiles($userId) {
    return $GLOBALS['groupPermissionManager']->canUserPerformAction($userId, 'delete_files');
}

// NUEVA FUNCIÓN AGREGADA

function canUserAccessCompany($userId, $companyId) {
    return $GLOBALS['groupPermissionManager']->canUserAccessCompany($userId, $companyId);
}

function canUserAccessDepartment($userId, $departmentId) {
    return $GLOBALS['groupPermissionManager']->canUserAccessDepartment($userId, $departmentId);
}

function canUserAccessDocumentType($userId, $documentTypeId) {
    return $GLOBALS['groupPermissionManager']->canUserAccessDocumentType($userId, $documentTypeId);
}

function getUserAllowedCompanies($userId) {
    return $GLOBALS['groupPermissionManager']->getUserAllowedCompanies($userId);
}

function getUserAllowedDepartments($userId, $companyId = null) {
    return $GLOBALS['groupPermissionManager']->getUserAllowedDepartments($userId, $companyId);
}

function getUserAllowedDocumentTypes($userId) {
    return $GLOBALS['groupPermissionManager']->getUserAllowedDocumentTypes($userId);
}

function getDocumentFilterForUser($userId, $alias = 'd') {
    return $GLOBALS['groupPermissionManager']->buildDocumentFilterClause($userId, $alias);
}
?>