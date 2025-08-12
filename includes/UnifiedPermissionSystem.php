<?php
/**
 * UnifiedPermissionSystem.php - VERSIÓN TOTALMENTE UNIFICADA CORREGIDA
 * v3.0.2 - Sistema 100% basado en grupos - SIN ERRORES
 */

class UnifiedPermissionSystem {
    private static $instance = null;
    private $cache = [];
    private $pdo = null;
    
    private function __construct() {
        $database = new Database();
        $this->pdo = $database->getConnection();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getUserEffectivePermissions($userId) {
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }
        
        try {
            $userQuery = "SELECT id, username, role, status FROM users WHERE id = ?";
            $stmt = $this->pdo->prepare($userQuery);
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || $user['status'] !== 'active') {
                return $this->getDefaultDeniedPermissions();
            }
            
            // 🔴 ADMINISTRADORES: Acceso total
            if ($user['role'] === 'admin') {
                $result = [
                    'permissions' => [
                        'upload_files' => true,
                        'view_files' => true,
                        'create_folders' => true,
                        'download_files' => true,
                        'delete_files' => true,
                        'view_reports' => true,
                        'manage_users' => true,
                        'manage_companies' => true,
                        'manage_groups' => true,
                        'system_admin' => true,
                        'access_admin_panel' => true
                    ],
                    'restrictions' => [
                        'companies' => [],
                        'departments' => [],
                        'document_types' => []
                    ],
                    'is_admin' => true,
                    'has_groups' => false,
                    'user_role' => 'admin',
                    'priority_level' => 'ADMIN',
                    'system_type' => 'TOTALMENTE_UNIFICADO'
                ];
                
                $this->cache[$userId] = $result;
                return $result;
            }
            
            // 🟢 VERIFICAR GRUPOS
            $groupQuery = "
                SELECT ug.id, ug.name, ug.module_permissions, ug.access_restrictions
                FROM user_groups ug
                INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
                WHERE ugm.user_id = ? AND ug.status = 'active'
            ";
            
            $stmt = $this->pdo->prepare($groupQuery);
            $stmt->execute([$userId]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($groups)) {
                $result = $this->processGroupPermissions($groups, $user);
                $result['priority_level'] = 'GROUPS';
                $result['system_type'] = 'TOTALMENTE_UNIFICADO';
                $this->cache[$userId] = $result;
                return $result;
            }
            
            // 🟡 SIN GRUPOS: Sin acceso
            $result = [
                'permissions' => [
                    'upload_files' => false,
                    'view_files' => false,
                    'create_folders' => false,
                    'download_files' => false,
                    'delete_files' => false,
                    'view_reports' => false,
                    'manage_users' => false,
                    'manage_companies' => false,
                    'manage_groups' => false,
                    'system_admin' => false,
                    'access_admin_panel' => false
                ],
                'restrictions' => [
                    'companies' => [],
                    'departments' => [],
                    'document_types' => []
                ],
                'is_admin' => false,
                'has_groups' => false,
                'user_role' => $user['role'],
                'priority_level' => 'NO_GROUPS',
                'system_type' => 'TOTALMENTE_UNIFICADO',
                'message' => 'Usuario debe ser asignado a un grupo',
                'warning' => true
            ];
            
            $this->cache[$userId] = $result;
            return $result;
            
        } catch (Exception $e) {
            error_log('Error in getUserEffectivePermissions: ' . $e->getMessage());
            return $this->getDefaultDeniedPermissions();
        }
    }
    
    private function processGroupPermissions($groups, $user) {
        $combinedPermissions = [
            'upload_files' => false,
            'view_files' => false,
            'create_folders' => false,
            'download_files' => false,
            'delete_files' => false,
            'view_reports' => false,
            'manage_users' => false,
            'manage_companies' => false,
            'manage_groups' => false,
            'system_admin' => false,
            'access_admin_panel' => false
        ];
        
        $combinedRestrictions = [
            'companies' => [],
            'departments' => [],
            'document_types' => []
        ];
        
        foreach ($groups as $group) {
            $groupPerms = json_decode($group['module_permissions'] ?: '{}', true);
            
            if (is_array($groupPerms)) {
                foreach ($combinedPermissions as $perm => $value) {
                    if ($perm === 'access_admin_panel') {
                        continue; // Solo admin real
                    }
                    
                    if (isset($groupPerms[$perm]) && $groupPerms[$perm]) {
                        $combinedPermissions[$perm] = true;
                    }
                }
            }
            
            $groupRestrictions = json_decode($group['access_restrictions'] ?: '{}', true);
            
            if (is_array($groupRestrictions)) {
                foreach (['companies', 'departments', 'document_types'] as $type) {
                    if (isset($groupRestrictions[$type]) && is_array($groupRestrictions[$type])) {
                        $combinedRestrictions[$type] = array_unique(
                            array_merge($combinedRestrictions[$type], $groupRestrictions[$type])
                        );
                    }
                }
            }
        }
        
        return [
            'permissions' => $combinedPermissions,
            'restrictions' => $combinedRestrictions,
            'is_admin' => false,
            'has_groups' => true,
            'group_count' => count($groups),
            'user_role' => $user['role'],
            'groups_info' => array_column($groups, 'name')
        ];
    }
    
    private function getDefaultDeniedPermissions() {
        return [
            'permissions' => [
                'upload_files' => false,
                'view_files' => false,
                'create_folders' => false,
                'download_files' => false,
                'delete_files' => false,
                'view_reports' => false,
                'manage_users' => false,
                'manage_companies' => false,
                'manage_groups' => false,
                'system_admin' => false,
                'access_admin_panel' => false
            ],
            'restrictions' => [
                'companies' => [],
                'departments' => [],
                'document_types' => []
            ],
            'is_admin' => false,
            'has_groups' => false,
            'user_role' => 'denied',
            'priority_level' => 'DENIED',
            'system_type' => 'TOTALMENTE_UNIFICADO',
            'error' => true
        ];
    }
    
    public function hasPermission($userId, $permission) {
        $userPerms = $this->getUserEffectivePermissions($userId);
        return isset($userPerms['permissions'][$permission]) && $userPerms['permissions'][$permission] === true;
    }
    
    public function canAccessResource($userId, $resourceType, $resourceId) {
        $userPerms = $this->getUserEffectivePermissions($userId);
        
        if ($userPerms['is_admin']) {
            return true;
        }
        
        $restrictionKey = $resourceType === 'company' ? 'companies' :
                         ($resourceType === 'department' ? 'departments' : 'document_types');
        
        $allowedResources = $userPerms['restrictions'][$restrictionKey] ?? [];
        
        if (empty($allowedResources)) {
            return false;
        }
        
        return in_array((int)$resourceId, $allowedResources);
    }
    
    public function getQueryRestrictions($userId, $tableAlias = 'd') {
        $userPerms = $this->getUserEffectivePermissions($userId);
        
        if ($userPerms['is_admin']) {
            return ['where' => '', 'params' => []];
        }
        
        $conditions = [];
        $params = [];
        
        if (!empty($userPerms['restrictions']['companies'])) {
            $placeholders = str_repeat('?,', count($userPerms['restrictions']['companies']) - 1) . '?';
            $conditions[] = "{$tableAlias}.company_id IN ($placeholders)";
            $params = array_merge($params, $userPerms['restrictions']['companies']);
        }
        
        return [
            'where' => empty($conditions) ? '1=0' : implode(' AND ', $conditions),
            'params' => $params
        ];
    }
    
    public function clearCache($userId = null) {
        if ($userId === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$userId]);
        }
    }
    
    public function getUnifiedSystemStats() {
        try {
            $stats = [];
            
            // Administradores
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ? AND status = ?");
                $stmt->execute(['admin', 'active']);
                $stats['admins'] = (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                $stats['admins'] = 0;
            }
            
            // Usuarios con grupos
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(DISTINCT u.id) 
                    FROM users u 
                    INNER JOIN user_group_members ugm ON u.id = ugm.user_id 
                    INNER JOIN user_groups ug ON ugm.group_id = ug.id 
                    WHERE u.status = ? AND ug.status = ?
                ");
                $stmt->execute(['active', 'active']);
                $stats['users_with_groups'] = (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                $stats['users_with_groups'] = 0;
            }
            
            // Usuarios sin grupos
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) FROM users u 
                    WHERE u.status = ? AND u.role != ? 
                    AND u.id NOT IN (
                        SELECT DISTINCT ugm.user_id FROM user_group_members ugm 
                        INNER JOIN user_groups ug ON ugm.group_id = ug.id 
                        WHERE ug.status = ?
                    )
                ");
                $stmt->execute(['active', 'admin', 'active']);
                $stats['users_without_groups'] = (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                $stats['users_without_groups'] = 0;
            }
            
            // Grupos activos
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_groups WHERE status = ?");
                $stmt->execute(['active']);
                $stats['active_groups'] = (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                $stats['active_groups'] = 0;
            }
            
            return $stats;
            
        } catch (Exception $e) {
            return [
                'admins' => 0,
                'users_with_groups' => 0,
                'users_without_groups' => 0,
                'active_groups' => 0,
                'error' => true
            ];
        }
    }
}

// FUNCIONES GLOBALES
if (!function_exists('hasUserPermission')) {
    function hasUserPermission($permission, $userId = null) {
        if ($userId === null) {
            $userId = SessionManager::getUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        $permissionSystem = UnifiedPermissionSystem::getInstance();
        return $permissionSystem->hasPermission($userId, $permission);
    }
}

if (!function_exists('canAccessResource')) {
    function canAccessResource($resourceType, $resourceId, $userId = null) {
        if ($userId === null) {
            $userId = SessionManager::getUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        $permissionSystem = UnifiedPermissionSystem::getInstance();
        return $permissionSystem->canAccessResource($userId, $resourceType, $resourceId);
    }
}

if (!function_exists('getQueryRestrictions')) {
    function getQueryRestrictions($tableAlias = 'd', $userId = null) {
        if ($userId === null) {
            $userId = SessionManager::getUserId();
        }
        
        if (!$userId) {
            return ['where' => '1=0', 'params' => []];
        }
        
        $permissionSystem = UnifiedPermissionSystem::getInstance();
        return $permissionSystem->getQueryRestrictions($userId, $tableAlias);
    }
}

if (!function_exists('isSystemAdmin')) {
    function isSystemAdmin($userId = null) {
        if ($userId === null) {
            $userId = SessionManager::getUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        $permissionSystem = UnifiedPermissionSystem::getInstance();
        $userPerms = $permissionSystem->getUserEffectivePermissions($userId);
        return $userPerms['is_admin'];
    }
}

if (!function_exists('canAccessAdminPanel')) {
    function canAccessAdminPanel($userId = null) {
        return isSystemAdmin($userId);
    }
}

if (!function_exists('canViewFiles')) {
    function canViewFiles($userId = null) {
        return hasUserPermission('view_files', $userId);
    }
}

if (!function_exists('canUploadFiles')) {
    function canUploadFiles($userId = null) {
        return hasUserPermission('upload_files', $userId);
    }
}

if (!function_exists('canDownloadFiles')) {
    function canDownloadFiles($userId = null) {
        return hasUserPermission('download_files', $userId);
    }
}

if (!function_exists('getUserPermissionInfo')) {
    function getUserPermissionInfo($userId = null) {
        if ($userId === null) {
            $userId = SessionManager::getUserId();
        }
        
        if (!$userId) {
            return null;
        }
        
        $permissionSystem = UnifiedPermissionSystem::getInstance();
        return $permissionSystem->getUserEffectivePermissions($userId);
    }
}

?>