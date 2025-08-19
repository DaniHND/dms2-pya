<?php
/**
 * includes/group_permissions.php
 * Sistema de permisos de grupos con prioridad absoluta
 * Los grupos tienen prioridad sobre los roles individuales
 * VERSIÓN CORREGIDA: Sin restricciones configuradas = SIN ACCESO
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
            
            // Permisos específicos del sistema (6 acciones principales)
            $permissions = [
                'upload_files' => false,      // 1. Subir archivo
                'view_files' => false,        // 2. Ver archivos (inbox)
                'create_folders' => false,    // 3. Crear carpetas
                'download_files' => false,    // 4. Descargar
                'delete_files' => false,      // 5. Eliminar archivos
                'move_files' => false         // 6. Mover archivos
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
     * CAMBIO: Sin restricciones configuradas = SIN ACCESO
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
        
        // CAMBIO: Si no hay restricciones de empresas = SIN ACCESO
        if (empty($allowedCompanies)) {
            return false;
        }
        
        // Verificar si la empresa está en la lista de permitidas
        return in_array((int)$companyId, $allowedCompanies);
    }
    
    /**
     * Verifica si un usuario puede acceder a un departamento específico
     * CAMBIO: Sin restricciones configuradas = SIN ACCESO
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
        
        // CAMBIO: Si no hay restricciones de departamentos = SIN ACCESO
        if (empty($allowedDepartments)) {
            return false;
        }
        
        // Verificar si el departamento está en la lista de permitidos
        return in_array((int)$departmentId, $allowedDepartments);
    }
    
    /**
     * Verifica si un usuario puede acceder a un tipo de documento específico
     * CAMBIO: Sin restricciones configuradas = SIN ACCESO
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
        
        // CAMBIO: Si no hay restricciones de tipos de documento = SIN ACCESO
        if (empty($allowedDocTypes)) {
            return false;
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
    private function getDefaultPermissions() {
        return [
            'has_groups' => false,
            'permissions' => [
                'upload_files' => false,
                'view_files' => false,
                'create_folders' => false,
                'download_files' => false,
                'delete_files' => false,
                'move_files' => false
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
 * VERSIÓN CORREGIDA: Sin restricciones configuradas = SIN ACCESO
 */

function getUserGroupPermissions($userId)
{
    if (!$userId) {
        return [
            'has_groups' => false,
            'permissions' => [
                'upload_files' => false,
                'view_files' => false,
                'create_folders' => false,
                'download_files' => false,
                'delete_files' => false,
                'move_files' => false
            ],
            'restrictions' => [
                'companies' => [],
                'departments' => [],
                'document_types' => []
            ]
        ];
    }

    try {
        $database = new Database();
        $pdo = $database->getConnection();

        // Obtener información del usuario
        $userQuery = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Usuario no encontrado o inactivo");
        }

        // ADMINISTRADORES: ACCESO TOTAL (sin cambios)
        if ($user['role'] === 'admin' || $user['role'] === 'super_admin') {
            return [
                'has_groups' => true, // Simular que tiene grupos para mantener compatibilidad
                'permissions' => [
                    'upload_files' => true,
                    'view_files' => true,
                    'create_folders' => true,
                    'download_files' => true,
                    'delete_files' => true,
                    'move_files' => true
                ],
                'restrictions' => [
                    'companies' => [], // Vacío = acceso a todas
                    'departments' => [],
                    'document_types' => []
                ]
            ];
        }

        // Obtener grupos del usuario
        $groupsQuery = "
            SELECT ug.id, ug.name, ug.module_permissions, ug.access_restrictions
            FROM user_groups ug
            INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
            WHERE ugm.user_id = ? AND ug.status = 'active'
            ORDER BY ug.name
        ";

        $groupsStmt = $pdo->prepare($groupsQuery);
        $groupsStmt->execute([$userId]);
        $groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Si NO tiene grupos = SIN ACCESO
        if (empty($groups)) {
            return [
                'has_groups' => false,
                'permissions' => [
                    'upload_files' => false,
                    'view_files' => false,
                    'create_folders' => false,
                    'download_files' => false,
                    'delete_files' => false,
                    'move_files' => false
                ],
                'restrictions' => [
                    'companies' => [],
                    'departments' => [],
                    'document_types' => []
                ]
            ];
        }

        // Combinar permisos y restricciones de todos los grupos
        $finalPermissions = [
            'upload_files' => false,
            'view_files' => false,
            'create_folders' => false,
            'download_files' => false,
            'delete_files' => false,
            'move_files' => false
        ];

        $finalRestrictions = [
            'companies' => [],
            'departments' => [],
            'document_types' => []
        ];

        foreach ($groups as $group) {
            // Combinar permisos (OR lógico)
            $groupPermissions = json_decode($group['module_permissions'] ?: '{}', true);
            foreach ($finalPermissions as $key => $current) {
                if (isset($groupPermissions[$key]) && $groupPermissions[$key] === true) {
                    $finalPermissions[$key] = true;
                }
            }

            // Combinar restricciones (UNION de IDs permitidos)
            $groupRestrictions = json_decode($group['access_restrictions'] ?: '{}', true);
            foreach (['companies', 'departments', 'document_types'] as $type) {
                if (isset($groupRestrictions[$type]) && is_array($groupRestrictions[$type])) {
                    $finalRestrictions[$type] = array_unique(array_merge(
                        $finalRestrictions[$type],
                        $groupRestrictions[$type]
                    ));
                }
            }
        }

        // ===================================================================
        // CAMBIO CRÍTICO: VALIDAR QUE TENGA RESTRICCIONES CONFIGURADAS
        // Si tiene grupos pero NO tiene restricciones = SIN ACCESO
        // ===================================================================
        
        $hasValidRestrictions = false;
        
        // Verificar si al menos una restricción está configurada (no vacía)
        if (!empty($finalRestrictions['companies']) || 
            !empty($finalRestrictions['departments']) || 
            !empty($finalRestrictions['document_types'])) {
            $hasValidRestrictions = true;
        }

        // Si tiene grupos pero ninguna restricción configurada = SIN ACCESO
        if (!$hasValidRestrictions) {
            error_log("🚫 Usuario $userId tiene grupos pero sin restricciones configuradas - bloqueando acceso");
            
            return [
                'has_groups' => true, // Sí tiene grupos
                'permissions' => [
                    'upload_files' => false,  // Pero sin permisos
                    'view_files' => false,
                    'create_folders' => false,
                    'download_files' => false,
                    'delete_files' => false,
                    'move_files' => false
                ],
                'restrictions' => [
                    'companies' => [],        // Y sin acceso a nada
                    'departments' => [],
                    'document_types' => []
                ]
            ];
        }

        // Si llegamos aquí, tiene grupos Y restricciones válidas
        error_log("✅ Usuario $userId: grupos válidos con restricciones configuradas");

        return [
            'has_groups' => true,
            'permissions' => $finalPermissions,
            'restrictions' => $finalRestrictions
        ];

    } catch (Exception $e) {
        error_log("Error getting user group permissions: " . $e->getMessage());
        return [
            'has_groups' => false,
            'permissions' => [
                'upload_files' => false,
                'view_files' => false,
                'create_folders' => false,
                'download_files' => false,
                'delete_files' => false,
                'move_files' => false
            ],
            'restrictions' => [
                'companies' => [],
                'departments' => [],
                'document_types' => []
            ]
        ];
    }
}

// ===================================================================
// FUNCIONES AUXILIARES CORREGIDAS: Sin restricciones = SIN ACCESO
// ===================================================================

function canUserAccessCompany($userId, $companyId) {
    $permissions = getUserGroupPermissions($userId);
    
    if (!$permissions['has_groups']) {
        return false;
    }
    
    $allowedCompanies = $permissions['restrictions']['companies'];
    
    // CAMBIO: Si no hay restricciones de empresas configuradas = SIN ACCESO
    if (empty($allowedCompanies)) {
        return false;
    }
    
    return in_array((int)$companyId, $allowedCompanies);
}

function canUserAccessDepartment($userId, $departmentId) {
    $permissions = getUserGroupPermissions($userId);
    
    if (!$permissions['has_groups']) {
        return false;
    }
    
    $allowedDepartments = $permissions['restrictions']['departments'];
    
    // CAMBIO: Si no hay restricciones de departamentos configuradas = SIN ACCESO
    if (empty($allowedDepartments)) {
        return false;
    }
    
    return in_array((int)$departmentId, $allowedDepartments);
}

function canUserAccessDocumentType($userId, $documentTypeId) {
    $permissions = getUserGroupPermissions($userId);
    
    if (!$permissions['has_groups']) {
        return false;
    }
    
    $allowedDocTypes = $permissions['restrictions']['document_types'];
    
    // CAMBIO: Si no hay restricciones de tipos configuradas = SIN ACCESO
    if (empty($allowedDocTypes)) {
        return false;
    }
    
    return in_array((int)$documentTypeId, $allowedDocTypes);
}

function getUserAllowedDepartments($userId, $companyId) {
    $permissions = getUserGroupPermissions($userId);
    
    if (!$permissions['has_groups']) {
        return [];
    }
    
    $allowedDepartments = $permissions['restrictions']['departments'];
    
    // CAMBIO: Si no hay restricciones = array vacío (sin acceso)
    if (empty($allowedDepartments)) {
        return [];
    }
    
    // Si se especifica empresa, filtrar solo departamentos de esa empresa
    if ($companyId) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $placeholders = str_repeat('?,', count($allowedDepartments) - 1) . '?';
            $query = "SELECT id FROM departments WHERE id IN ($placeholders) AND company_id = ? AND status = 'active'";
            
            $params = array_merge($allowedDepartments, [$companyId]);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        } catch (Exception $e) {
            error_log("Error filtering departments by company: " . $e->getMessage());
            return [];
        }
    }
    
    return $allowedDepartments;
}

// ===================================================================
// FUNCIONES WRAPPER PARA COMPATIBILIDAD
// ===================================================================

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

function getUserAllowedCompanies($userId) {
    return $GLOBALS['groupPermissionManager']->getUserAllowedCompanies($userId);
}

function getUserAllowedDocumentTypes($userId) {
    return $GLOBALS['groupPermissionManager']->getUserAllowedDocumentTypes($userId);
}

function getDocumentFilterForUser($userId, $alias = 'd') {
    return $GLOBALS['groupPermissionManager']->buildDocumentFilterClause($userId, $alias);
}

?>