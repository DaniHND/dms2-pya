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
            
            // Permisos específicos del sistema (5 acciones principales)
            $permissions = [
                'upload_files' => false,      // 1. Subir archivo
                'view_files' => false,        // 2. Ver archivos (inbox)
                'create_folders' => false,    // 3. Crear carpetas
                'download_files' => false,    // 4. Descargar
                'delete_files' => false       // 5. Eliminar archivos
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
        $userPerms = $this->getUserEffectivePermissions($userId);
        return isset($userPerms['permissions'][$action]) && $userPerms['permissions'][$action] === true;
    }
    
    /**
     * Verifica si un usuario puede acceder a una empresa específica
     */
    public function canUserAccessCompany($userId, $companyId) {
        $userPerms = $this->getUserEffectivePermissions($userId);
        
        if (!$userPerms['has_groups']) {
            return false; // Sin grupos = sin acceso
        }
        
        // Si no hay restricciones de empresa, se permite acceso
        if (empty($userPerms['restrictions']['companies'])) {
            return true;
        }
        
        return in_array((int)$companyId, $userPerms['restrictions']['companies']);
    }
    
    /**
     * Verifica si un usuario puede acceder a un departamento específico
     */
    public function canUserAccessDepartment($userId, $departmentId) {
        $userPerms = $this->getUserEffectivePermissions($userId);
        
        if (!$userPerms['has_groups']) {
            return false;
        }
        
        if (empty($userPerms['restrictions']['departments'])) {
            return true;
        }
        
        return in_array((int)$departmentId, $userPerms['restrictions']['departments']);
    }
    
    /**
     * Verifica si un usuario puede acceder a un tipo de documento específico
     */
    public function canUserAccessDocumentType($userId, $documentTypeId) {
        $userPerms = $this->getUserEffectivePermissions($userId);
        
        if (!$userPerms['has_groups']) {
            return false;
        }
        
        if (empty($userPerms['restrictions']['document_types'])) {
            return true;
        }
        
        return in_array((int)$documentTypeId, $userPerms['restrictions']['document_types']);
    }
    
    /**
     * Obtiene las empresas permitidas para un usuario
     */
    public function getUserAllowedCompanies($userId) {
        $userPerms = $this->getUserEffectivePermissions($userId);
        
        if (!$userPerms['has_groups']) {
            return [];
        }
        
        if (empty($userPerms['restrictions']['companies'])) {
            // Sin restricciones = todas las empresas activas
            $query = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Con restricciones = solo las permitidas
        $companyIds = $userPerms['restrictions']['companies'];
        if (empty($companyIds)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($companyIds) - 1) . '?';
        $query = "SELECT id, name FROM companies WHERE id IN ($placeholders) AND status = 'active' ORDER BY name";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($companyIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene los departamentos permitidos para un usuario
     */
    public function getUserAllowedDepartments($userId, $companyId = null) {
        $userPerms = $this->getUserEffectivePermissions($userId);
        
        if (!$userPerms['has_groups']) {
            return [];
        }
        
        $query = "
            SELECT d.id, d.name, c.name as company_name, d.company_id
            FROM departments d
            INNER JOIN companies c ON d.company_id = c.id
            WHERE d.status = 'active' AND c.status = 'active'
        ";
        $params = [];
        
        // Filtrar por empresa si se especifica
        if ($companyId) {
            $query .= " AND d.company_id = ?";
            $params[] = $companyId;
        }
        
        // Aplicar restricciones de departamento
        if (!empty($userPerms['restrictions']['departments'])) {
            $deptIds = $userPerms['restrictions']['departments'];
            $placeholders = str_repeat('?,', count($deptIds) - 1) . '?';
            $query .= " AND d.id IN ($placeholders)";
            $params = array_merge($params, $deptIds);
        }
        
        // Aplicar restricciones de empresa
        if (!empty($userPerms['restrictions']['companies'])) {
            $companyIds = $userPerms['restrictions']['companies'];
            $placeholders = str_repeat('?,', count($companyIds) - 1) . '?';
            $query .= " AND c.id IN ($placeholders)";
            $params = array_merge($params, $companyIds);
        }
        
        $query .= " ORDER BY c.name, d.name";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene los tipos de documentos permitidos para un usuario
     */
    public function getUserAllowedDocumentTypes($userId) {
        $userPerms = $this->getUserEffectivePermissions($userId);
        
        if (!$userPerms['has_groups']) {
            return [];
        }
        
        if (empty($userPerms['restrictions']['document_types'])) {
            // Sin restricciones = todos los tipos activos
            $query = "SELECT id, name, description FROM document_types WHERE status = 'active' ORDER BY name";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Con restricciones = solo los permitidos
        $docTypeIds = $userPerms['restrictions']['document_types'];
        if (empty($docTypeIds)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($docTypeIds) - 1) . '?';
        $query = "SELECT id, name, description FROM document_types WHERE id IN ($placeholders) AND status = 'active' ORDER BY name";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($docTypeIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Construye WHERE clause para filtrar documentos según permisos
     */
    public function buildDocumentFilterClause($userId, $alias = 'd') {
        $userPerms = $this->getUserEffectivePermissions($userId);
        
        if (!$userPerms['has_groups']) {
            return "1 = 0"; // Sin grupos = sin acceso
        }
        
        $conditions = [];
        $params = [];
        
        // Filtrar por empresas permitidas
        if (!empty($userPerms['restrictions']['companies'])) {
            $companyIds = $userPerms['restrictions']['companies'];
            $placeholders = str_repeat('?,', count($companyIds) - 1) . '?';
            $conditions[] = "{$alias}.company_id IN ($placeholders)";
            $params = array_merge($params, $companyIds);
        }
        
        // Filtrar por departamentos permitidos
        if (!empty($userPerms['restrictions']['departments'])) {
            $deptIds = $userPerms['restrictions']['departments'];
            $placeholders = str_repeat('?,', count($deptIds) - 1) . '?';
            $conditions[] = "{$alias}.department_id IN ($placeholders)";
            $params = array_merge($params, $deptIds);
        }
        
        // Filtrar por tipos de documentos permitidos
        if (!empty($userPerms['restrictions']['document_types'])) {
            $docTypeIds = $userPerms['restrictions']['document_types'];
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
    private function getDefaultPermissions() {
        return [
            'has_groups' => false,
            'permissions' => [
                'upload_files' => false,
                'view_files' => false,
                'create_folders' => false,
                'download_files' => false,
                'delete_files' => false
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