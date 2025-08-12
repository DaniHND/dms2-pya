<?php
/*
 * includes/PermissionManager.php
 * Sistema de verificación automática de permisos basado en grupos - VERSIÓN SIMPLIFICADA
 */

class PermissionManager {
    private $pdo;
    private $userId;
    private $userPermissions = null;
    private $userRestrictions = null;
    private $dailyLimits = null;
    
    public function __construct($userId = null) {
        $this->userId = $userId ?: ($_SESSION['user_id'] ?? null);
        
        if (!$this->userId) {
            throw new Exception('Usuario no especificado para PermissionManager');
        }
        
        try {
            $database = new Database();
            $this->pdo = $database->getConnection();
            $this->loadUserPermissions();
        } catch (Exception $e) {
            error_log('Error inicializando PermissionManager: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Cargar permisos del usuario basados en sus grupos
     */
    private function loadUserPermissions() {
        try {
            // Verificar si el usuario es admin
            $userQuery = "SELECT role FROM users WHERE id = ?";
            $userStmt = $this->pdo->prepare($userQuery);
            $userStmt->execute([$this->userId]);
            $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            // Si es admin, dar todos los permisos
            if ($userInfo && $userInfo['role'] === 'admin') {
                $this->userPermissions = [
                    'view' => true,
                    'download' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => true
                ];
                $this->userRestrictions = [
                    'companies' => [],
                    'departments' => [],
                    'document_types' => []
                ];
                $this->dailyLimits = ['download' => null, 'upload' => null];
                return;
            }
            
            $query = "
                SELECT 
                    ug.module_permissions,
                    ug.access_restrictions,
                    ug.download_limit_daily,
                    ug.upload_limit_daily
                FROM user_groups ug
                INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
                WHERE ugm.user_id = ? AND ug.status = 'active'
                ORDER BY ug.is_system_group ASC, ug.created_at ASC
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$this->userId]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Permisos básicos simplificados
            $combinedPermissions = [
                'view' => false,
                'download' => false,
                'create' => false,
                'edit' => false,
                'delete' => false
            ];
            
            $combinedRestrictions = [
                'companies' => [],
                'departments' => [],
                'document_types' => []
            ];
            
            $minDownloadLimit = null;
            $minUploadLimit = null;
            
            foreach ($groups as $group) {
                // Combinar permisos (OR lógico - el más permisivo gana)
                $groupPerms = json_decode($group['module_permissions'] ?: '{}', true);
                
                // Mapear permisos específicos a nuestros permisos básicos
                if (isset($groupPerms['documents_view']) && $groupPerms['documents_view'] === true) {
                    $combinedPermissions['view'] = true;
                }
                if (isset($groupPerms['documents_download']) && $groupPerms['documents_download'] === true) {
                    $combinedPermissions['download'] = true;
                }
                if (isset($groupPerms['documents_create']) && $groupPerms['documents_create'] === true) {
                    $combinedPermissions['create'] = true;
                }
                if (isset($groupPerms['documents_edit']) && $groupPerms['documents_edit'] === true) {
                    $combinedPermissions['edit'] = true;
                }
                if (isset($groupPerms['documents_delete']) && $groupPerms['documents_delete'] === true) {
                    $combinedPermissions['delete'] = true;
                }
                
                // Combinar restricciones (Union de todas las restricciones)
                $groupRestrictions = json_decode($group['access_restrictions'] ?: '{}', true);
                foreach (['companies', 'departments', 'document_types'] as $restrictionType) {
                    if (!empty($groupRestrictions[$restrictionType])) {
                        $combinedRestrictions[$restrictionType] = array_unique(
                            array_merge(
                                $combinedRestrictions[$restrictionType], 
                                $groupRestrictions[$restrictionType]
                            )
                        );
                    }
                }
                
                // Límites (el más restrictivo gana)
                if ($group['download_limit_daily'] !== null) {
                    $limit = (int)$group['download_limit_daily'];
                    $minDownloadLimit = $minDownloadLimit === null ? $limit : min($minDownloadLimit, $limit);
                }
                
                if ($group['upload_limit_daily'] !== null) {
                    $limit = (int)$group['upload_limit_daily'];
                    $minUploadLimit = $minUploadLimit === null ? $limit : min($minUploadLimit, $limit);
                }
            }
            
            $this->userPermissions = $combinedPermissions;
            $this->userRestrictions = $combinedRestrictions;
            $this->dailyLimits = [
                'download' => $minDownloadLimit,
                'upload' => $minUploadLimit
            ];
            
        } catch (Exception $e) {
            error_log('Error cargando permisos de usuario: ' . $e->getMessage());
            $this->userPermissions = [
                'view' => false,
                'download' => false,
                'create' => false,
                'edit' => false,
                'delete' => false
            ];
            $this->userRestrictions = ['companies' => [], 'departments' => [], 'document_types' => []];
            $this->dailyLimits = ['download' => null, 'upload' => null];
        }
    }
    
    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function hasPermission($permission) {
        return isset($this->userPermissions[$permission]) && $this->userPermissions[$permission] === true;
    }
    
    /**
     * Verificar múltiples permisos (AND lógico)
     */
    public function hasAllPermissions($permissions) {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Verificar si tiene al menos uno de los permisos (OR lógico)
     */
    public function hasAnyPermission($permissions) {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verificar acceso a un documento específico
     */
    public function canAccessDocument($documentId) {
        try {
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
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$documentId]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$document) {
                return false;
            }
            
            // Verificar estado del documento
            if ($document['status'] === 'deleted' && !$this->hasPermission('delete')) {
                return false;
            }
            
            // Verificar restricciones de empresa
            if (!empty($this->userRestrictions['companies'])) {
                if (!in_array($document['company_id'], $this->userRestrictions['companies'])) {
                    return false;
                }
            }
            
            // Verificar restricciones de departamento
            if (!empty($this->userRestrictions['departments'])) {
                if (!in_array($document['department_id'], $this->userRestrictions['departments'])) {
                    return false;
                }
            }
            
            // Verificar restricciones de tipo de documento
            if (!empty($this->userRestrictions['document_types'])) {
                if (!in_array($document['document_type_id'], $this->userRestrictions['document_types'])) {
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
     * Obtener documentos accesibles para el usuario con restricciones aplicadas
     */
    public function getAccessibleDocuments($additionalFilters = []) {
        try {
            $whereConditions = ["d.status != 'deleted'"];
            $params = [];
            
            // Aplicar restricciones de empresa
            if (!empty($this->userRestrictions['companies'])) {
                $placeholders = str_repeat('?,', count($this->userRestrictions['companies']) - 1) . '?';
                $whereConditions[] = "d.company_id IN ($placeholders)";
                $params = array_merge($params, $this->userRestrictions['companies']);
            }
            
            // Aplicar restricciones de departamento
            if (!empty($this->userRestrictions['departments'])) {
                $placeholders = str_repeat('?,', count($this->userRestrictions['departments']) - 1) . '?';
                $whereConditions[] = "d.department_id IN ($placeholders)";
                $params = array_merge($params, $this->userRestrictions['departments']);
            }
            
            // Aplicar restricciones de tipo de documento
            if (!empty($this->userRestrictions['document_types'])) {
                $placeholders = str_repeat('?,', count($this->userRestrictions['document_types']) - 1) . '?';
                $whereConditions[] = "d.document_type_id IN ($placeholders)";
                $params = array_merge($params, $this->userRestrictions['document_types']);
            }
            
            // Aplicar filtros adicionales
            foreach ($additionalFilters as $filter => $value) {
                if ($value !== null && $value !== '') {
                    switch ($filter) {
                        case 'company_id':
                            $whereConditions[] = "d.company_id = ?";
                            $params[] = $value;
                            break;
                        case 'department_id':
                            $whereConditions[] = "d.department_id = ?";
                            $params[] = $value;
                            break;
                        case 'document_type_id':
                            $whereConditions[] = "d.document_type_id = ?";
                            $params[] = $value;
                            break;
                        case 'search':
                            $whereConditions[] = "(d.name LIKE ? OR d.description LIKE ?)";
                            $params[] = "%$value%";
                            $params[] = "%$value%";
                            break;
                    }
                }
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $query = "
                SELECT 
                    d.*,
                    c.name as company_name,
                    dep.name as department_name,
                    dt.name as document_type_name,
                    dt.icon as document_type_icon,
                    dt.color as document_type_color,
                    CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                FROM documents d
                LEFT JOIN companies c ON d.company_id = c.id
                LEFT JOIN departments dep ON d.department_id = dep.id
                LEFT JOIN document_types dt ON d.document_type_id = dt.id
                LEFT JOIN users u ON d.user_id = u.id
                WHERE $whereClause
                ORDER BY d.created_at DESC
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error obteniendo documentos accesibles: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener empresas accesibles según restricciones
     */
    public function getAccessibleCompanies() {
        try {
            $query = "SELECT id, name FROM companies WHERE status = 'active'";
            $params = [];
            
            // Si hay restricciones de empresa, aplicarlas
            if (!empty($this->userRestrictions['companies'])) {
                $placeholders = str_repeat('?,', count($this->userRestrictions['companies']) - 1) . '?';
                $query .= " AND id IN ($placeholders)";
                $params = $this->userRestrictions['companies'];
            }
            
            $query .= " ORDER BY name";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error obteniendo empresas accesibles: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener departamentos accesibles según restricciones
     */
    public function getAccessibleDepartments($companyId = null) {
        try {
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
            if (!empty($this->userRestrictions['companies'])) {
                $placeholders = str_repeat('?,', count($this->userRestrictions['companies']) - 1) . '?';
                $query .= " AND d.company_id IN ($placeholders)";
                $params = array_merge($params, $this->userRestrictions['companies']);
            }
            
            // Si hay restricciones de departamento, aplicarlas
            if (!empty($this->userRestrictions['departments'])) {
                $placeholders = str_repeat('?,', count($this->userRestrictions['departments']) - 1) . '?';
                $query .= " AND d.id IN ($placeholders)";
                $params = array_merge($params, $this->userRestrictions['departments']);
            }
            
            $query .= " ORDER BY c.name, d.name";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error obteniendo departamentos accesibles: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verificar límites diarios
     */
    public function checkDownloadLimit() {
        if ($this->dailyLimits['download'] === null) {
            return true; // Sin límite
        }
        
        return $this->getCurrentUsage('download') < $this->dailyLimits['download'];
    }
    
    public function checkUploadLimit() {
        if ($this->dailyLimits['upload'] === null) {
            return true; // Sin límite
        }
        
        return $this->getCurrentUsage('upload') < $this->dailyLimits['upload'];
    }
    
    /**
     * Obtener uso actual del día
     */
    private function getCurrentUsage($type) {
        try {
            $today = date('Y-m-d');
            
            if ($type === 'download') {
                $query = "
                    SELECT COUNT(*) 
                    FROM activity_logs 
                    WHERE user_id = ? 
                    AND action = 'download_document' 
                    AND DATE(created_at) = ?
                ";
            } else {
                $query = "
                    SELECT COUNT(*) 
                    FROM documents 
                    WHERE user_id = ? 
                    AND DATE(created_at) = ?
                ";
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$this->userId, $today]);
            
            return (int)$stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Error obteniendo uso actual ($type): " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Verificar si el usuario puede acceder a una empresa específica
     */
    public function canAccessCompany($companyId) {
        if (empty($this->userRestrictions['companies'])) {
            return true; // Sin restricciones = acceso a todas
        }
        
        return in_array($companyId, $this->userRestrictions['companies']);
    }
    
    /**
     * Verificar si el usuario puede acceder a un departamento específico
     */
    public function canAccessDepartment($departmentId) {
        if (empty($this->userRestrictions['departments'])) {
            return true; // Sin restricciones = acceso a todos
        }
        
        return in_array($departmentId, $this->userRestrictions['departments']);
    }
    
    /**
     * Obtener todas las restricciones del usuario
     */
    public function getAllRestrictions() {
        return $this->userRestrictions;
    }
    
    /**
     * Obtener todos los permisos del usuario
     */
    public function getAllPermissions() {
        return $this->userPermissions;
    }
}

/**
 * Funciones globales para facilitar el uso
 */

function getPermissionManager($userId = null) {
    static $instance = null;
    static $lastUserId = null;
    
    $currentUserId = $userId ?: ($_SESSION['user_id'] ?? null);
    
    if ($instance === null || $lastUserId !== $currentUserId) {
        $instance = new PermissionManager($currentUserId);
        $lastUserId = $currentUserId;
    }
    
    return $instance;
}

function hasPermission($permission, $userId = null) {
    try {
        $pm = getPermissionManager($userId);
        return $pm->hasPermission($permission);
    } catch (Exception $e) {
        error_log('Error verificando permiso: ' . $e->getMessage());
        return false;
    }
}

function canAccessDocument($documentId, $userId = null) {
    try {
        $pm = getPermissionManager($userId);
        return $pm->canAccessDocument($documentId);
    } catch (Exception $e) {
        error_log('Error verificando acceso a documento: ' . $e->getMessage());
        return false;
    }
}

function getAccessibleDocuments($filters = [], $userId = null) {
    try {
        $pm = getPermissionManager($userId);
        return $pm->getAccessibleDocuments($filters);
    } catch (Exception $e) {
        error_log('Error obteniendo documentos accesibles: ' . $e->getMessage());
        return [];
    }
}
?>s