<?php
/*
 * includes/PermissionManager.php
 * Sistema de gestión y verificación de permisos basado en grupos
 */

class PermissionManager {
    private $pdo;
    private $userPermissions = null;
    private $userRestrictions = null;
    private $currentUser = null;
    
    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            $database = new Database();
            $this->pdo = $database->getConnection();
        }
        
        if (SessionManager::isLoggedIn()) {
            $this->currentUser = SessionManager::getCurrentUser();
            $this->loadUserPermissions();
        }
    }
    
    /**
     * Cargar permisos del usuario actual basados en sus grupos
     */
    private function loadUserPermissions() {
        if (!$this->currentUser) {
            return;
        }
        
        // Si es admin, tiene todos los permisos
        if ($this->currentUser['role'] === 'admin') {
            $this->userPermissions = [
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
            ];
            $this->userRestrictions = [
                'companies' => [],
                'departments' => [],
                'document_types' => []
            ];
            return;
        }
        
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
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$this->currentUser['id']]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combinar permisos de todos los grupos (OR lógico)
        $this->userPermissions = [
            'view' => false,
            'view_reports' => false,
            'download' => false,
            'export' => false,
            'create' => false,
            'edit' => false,
            'delete' => false,
            'delete_permanent' => false,
            'manage_users' => false,
            'system_config' => false,
            'download_limit' => null,
            'upload_limit' => null
        ];
        
        $this->userRestrictions = [
            'companies' => [],
            'departments' => [],
            'document_types' => []
        ];
        
        foreach ($groups as $group) {
            // Combinar permisos
            if ($group['module_permissions']) {
                $permissions = json_decode($group['module_permissions'], true);
                if ($permissions) {
                    foreach ($permissions as $key => $value) {
                        if (isset($this->userPermissions[$key])) {
                            $this->userPermissions[$key] = $this->userPermissions[$key] || $value;
                        }
                    }
                }
            }
            
            // Combinar restricciones (cualquier grupo sin restricciones = sin restricciones)
            if ($group['access_restrictions']) {
                $restrictions = json_decode($group['access_restrictions'], true);
                if ($restrictions) {
                    foreach (['companies', 'departments', 'document_types'] as $type) {
                        if (empty($restrictions[$type])) {
                            // Si un grupo no tiene restricciones, el usuario tampoco
                            $this->userRestrictions[$type] = [];
                        } else if (empty($this->userRestrictions[$type])) {
                            // Si no hay restricciones previas, usar las de este grupo
                            $this->userRestrictions[$type] = $restrictions[$type];
                        } else {
                            // Combinar restricciones (unión)
                            $existing = array_column($this->userRestrictions[$type], 'id');
                            foreach ($restrictions[$type] as $item) {
                                if (!in_array($item['id'], $existing)) {
                                    $this->userRestrictions[$type][] = $item;
                                }
                            }
                        }
                    }
                }
            }
            
            // Límites (usar el más permisivo)
            if ($group['download_limit_daily']) {
                $limit = (int)$group['download_limit_daily'];
                if (!$this->userPermissions['download_limit'] || $limit > $this->userPermissions['download_limit']) {
                    $this->userPermissions['download_limit'] = $limit;
                }
            }
            
            if ($group['upload_limit_daily']) {
                $limit = (int)$group['upload_limit_daily'];
                if (!$this->userPermissions['upload_limit'] || $limit > $this->userPermissions['upload_limit']) {
                    $this->userPermissions['upload_limit'] = $limit;
                }
            }
        }
    }
    
    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function hasPermission($permission) {
        if (!$this->userPermissions) {
            return false;
        }
        
        return $this->userPermissions[$permission] ?? false;
    }
    
    /**
     * Verificar si el usuario puede acceder a una empresa específica
     */
    public function canAccessCompany($companyId) {
        if (!$this->userRestrictions || empty($this->userRestrictions['companies'])) {
            return true; // Sin restricciones = acceso total
        }
        
        $allowedCompanies = array_column($this->userRestrictions['companies'], 'id');
        return in_array($companyId, $allowedCompanies);
    }
    
    /**
     * Verificar si el usuario puede acceder a un departamento específico
     */
    public function canAccessDepartment($departmentId) {
        if (!$this->userRestrictions || empty($this->userRestrictions['departments'])) {
            return true; // Sin restricciones = acceso total
        }
        
        $allowedDepartments = array_column($this->userRestrictions['departments'], 'id');
        return in_array($departmentId, $allowedDepartments);
    }
    
    /**
     * Verificar si el usuario puede acceder a un tipo de documento específico
     */
    public function canAccessDocumentType($documentTypeId) {
        if (!$this->userRestrictions || empty($this->userRestrictions['document_types'])) {
            return true; // Sin restricciones = acceso total
        }
        
        $allowedTypes = array_column($this->userRestrictions['document_types'], 'id');
        return in_array($documentTypeId, $allowedTypes);
    }
    
    /**
     * Verificar si el usuario puede acceder a un documento específico
     */
    public function canAccessDocument($documentId) {
        if (!$this->hasPermission('view')) {
            return false;
        }
        
        // Obtener información del documento
        $query = "
            SELECT 
                d.id,
                d.company_id,
                d.department_id,
                d.document_type_id,
                dt.name as document_type_name
            FROM documents d
            LEFT JOIN document_types dt ON d.document_type_id = dt.id
            WHERE d.id = ?
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$documentId]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$document) {
            return false;
        }
        
        // Verificar restricciones
        if ($document['company_id'] && !$this->canAccessCompany($document['company_id'])) {
            return false;
        }
        
        if ($document['department_id'] && !$this->canAccessDepartment($document['department_id'])) {
            return false;
        }
        
        if ($document['document_type_id'] && !$this->canAccessDocumentType($document['document_type_id'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar si el usuario puede descargar un documento
     */
    public function canDownloadDocument($documentId) {
        if (!$this->hasPermission('download')) {
            return false;
        }
        
        return $this->canAccessDocument($documentId);
    }
    
    /**
     * Verificar límites de descarga diaria
     */
    public function checkDownloadLimit() {
        if (!$this->userPermissions['download_limit']) {
            return true; // Sin límite
        }
        
        // Contar descargas de hoy
        $query = "
            SELECT COUNT(*) as downloads_today
            FROM user_activity_logs
            WHERE user_id = ? 
            AND action = 'download_document'
            AND DATE(created_at) = CURDATE()
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$this->currentUser['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $downloadsToday = $result['downloads_today'] ?? 0;
        
        return $downloadsToday < $this->userPermissions['download_limit'];
    }
    
    /**
     * Obtener consulta SQL con restricciones aplicadas para documentos
     */
    public function getDocumentQuery($baseQuery = "SELECT * FROM documents WHERE 1=1") {
        $conditions = [];
        $params = [];
        
        // Aplicar restricciones de empresa
        if (!empty($this->userRestrictions['companies'])) {
            $companyIds = array_column($this->userRestrictions['companies'], 'id');
            $placeholders = str_repeat('?,', count($companyIds) - 1) . '?';
            $conditions[] = "company_id IN ($placeholders)";
            $params = array_merge($params, $companyIds);
        }
        
        // Aplicar restricciones de departamento
        if (!empty($this->userRestrictions['departments'])) {
            $departmentIds = array_column($this->userRestrictions['departments'], 'id');
            $placeholders = str_repeat('?,', count($departmentIds) - 1) . '?';
            $conditions[] = "department_id IN ($placeholders)";
            $params = array_merge($params, $departmentIds);
        }
        
        // Aplicar restricciones de tipo de documento
        if (!empty($this->userRestrictions['document_types'])) {
            $typeIds = array_column($this->userRestrictions['document_types'], 'id');
            $placeholders = str_repeat('?,', count($typeIds) - 1) . '?';
            $conditions[] = "document_type_id IN ($placeholders)";
            $params = array_merge($params, $typeIds);
        }
        
        if (!empty($conditions)) {
            $baseQuery .= " AND (" . implode(' OR ', $conditions) . ")";
        }
        
        return ['query' => $baseQuery, 'params' => $params];
    }
    
    /**
     * Obtener todos los permisos del usuario actual
     */
    public function getUserPermissions() {
        return $this->userPermissions;
    }
    
    /**
     * Obtener todas las restricciones del usuario actual
     */
    public function getUserRestrictions() {
        return $this->userRestrictions;
    }
    
    /**
     * Verificar si el usuario actual es administrador
     */
    public function isAdmin() {
        return $this->currentUser && $this->currentUser['role'] === 'admin';
    }
    
    /**
     * Obtener información resumida de permisos para mostrar en UI
     */
    public function getPermissionsSummary() {
        if (!$this->userPermissions) {
            return [
                'level' => 'Sin permisos',
                'description' => 'Usuario sin grupos asignados',
                'capabilities' => []
            ];
        }
        
        $capabilities = [];
        
        if ($this->userPermissions['view']) $capabilities[] = 'Ver documentos';
        if ($this->userPermissions['download']) $capabilities[] = 'Descargar';
        if ($this->userPermissions['create']) $capabilities[] = 'Crear';
        if ($this->userPermissions['edit']) $capabilities[] = 'Editar';
        if ($this->userPermissions['delete']) $capabilities[] = 'Eliminar';
        if ($this->userPermissions['manage_users']) $capabilities[] = 'Gestionar usuarios';
        
        $level = 'Básico';
        if ($this->isAdmin()) {
            $level = 'Administrador';
        } elseif ($this->userPermissions['manage_users']) {
            $level = 'Supervisor';
        } elseif ($this->userPermissions['create'] && $this->userPermissions['edit']) {
            $level = 'Editor';
        } elseif ($this->userPermissions['download']) {
            $level = 'Usuario';
        }
        
        return [
            'level' => $level,
            'description' => implode(', ', $capabilities),
            'capabilities' => $capabilities,
            'restrictions' => [
                'companies' => count($this->userRestrictions['companies'] ?? []),
                'departments' => count($this->userRestrictions['departments'] ?? []),
                'document_types' => count($this->userRestrictions['document_types'] ?? [])
            ]
        ];
    }
}

// Función helper para usar en cualquier parte del sistema
function getPermissionManager() {
    static $instance = null;
    if ($instance === null) {
        $instance = new PermissionManager();
    }
    return $instance;
}

// Funciones helper para verificaciones rápidas
function hasPermission($permission) {
    return getPermissionManager()->hasPermission($permission);
}

function canAccessDocument($documentId) {
    return getPermissionManager()->canAccessDocument($documentId);
}

function canDownloadDocument($documentId) {
    return getPermissionManager()->canDownloadDocument($documentId);
}

function isAdmin() {
    return getPermissionManager()->isAdmin();
}
?>