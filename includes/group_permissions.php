<?php
/**
 * includes/group_permissions.php
 * Sistema de permisos de grupos - VERSIÓN UNIFICADA Y LIMPIA
 * Los grupos tienen prioridad sobre los roles individuales
 * REGLA: Sin restricciones configuradas = SIN ACCESO (excepto administradores)
 */

// ===================================================================
// FUNCIÓN PRINCIPAL: getUserGroupPermissions
// ===================================================================

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

        // ===== ADMINISTRADORES: ACCESO TOTAL =====
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

        // ===== OBTENER GRUPOS DEL USUARIO =====
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

        // ===== SI NO TIENE GRUPOS = SIN ACCESO =====
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

        // ===== COMBINAR PERMISOS Y RESTRICCIONES DE TODOS LOS GRUPOS =====
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

        // ===== VALIDACIÓN CRÍTICA: VERIFICAR QUE TENGA RESTRICCIONES CONFIGURADAS =====
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
// FUNCIONES DE VALIDACIÓN DE ACCESO CON VERIFICACIÓN DE ADMIN
// ===================================================================

function canUserAccessCompany($userId, $companyId) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Verificar si es administrador PRIMERO
        $userQuery = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && ($user['role'] === 'admin' || $user['role'] === 'super_admin')) {
            return true; // ADMINISTRADORES: ACCESO TOTAL
        }
        
        // Para usuarios normales, aplicar restricciones
        $permissions = getUserGroupPermissions($userId);
        
        if (!$permissions['has_groups']) {
            return false;
        }
        
        $allowedCompanies = $permissions['restrictions']['companies'];
        
        // Si no hay restricciones de empresas = SIN ACCESO
        if (empty($allowedCompanies)) {
            return false;
        }
        
        return in_array((int)$companyId, $allowedCompanies);
    } catch (Exception $e) {
        error_log("Error en canUserAccessCompany: " . $e->getMessage());
        return false;
    }
}

function canUserAccessDepartment($userId, $departmentId) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Verificar si es administrador PRIMERO
        $userQuery = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && ($user['role'] === 'admin' || $user['role'] === 'super_admin')) {
            return true; // ADMINISTRADORES: ACCESO TOTAL
        }
        
        // Para usuarios normales, aplicar restricciones
        $permissions = getUserGroupPermissions($userId);
        
        if (!$permissions['has_groups']) {
            return false;
        }
        
        $allowedDepartments = $permissions['restrictions']['departments'];
        
        // Si no hay restricciones de departamentos = SIN ACCESO
        if (empty($allowedDepartments)) {
            return false;
        }
        
        return in_array((int)$departmentId, $allowedDepartments);
    } catch (Exception $e) {
        error_log("Error en canUserAccessDepartment: " . $e->getMessage());
        return false;
    }
}

function canUserAccessDocumentType($userId, $documentTypeId) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Verificar si es administrador PRIMERO
        $userQuery = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && ($user['role'] === 'admin' || $user['role'] === 'super_admin')) {
            return true; // ADMINISTRADORES: ACCESO TOTAL
        }
        
        // Para usuarios normales, aplicar restricciones
        $permissions = getUserGroupPermissions($userId);
        
        if (!$permissions['has_groups']) {
            return false;
        }
        
        $allowedDocTypes = $permissions['restrictions']['document_types'];
        
        // Si no hay restricciones de tipos configuradas = SIN ACCESO
        if (empty($allowedDocTypes)) {
            return false;
        }
        
        return in_array((int)$documentTypeId, $allowedDocTypes);
    } catch (Exception $e) {
        error_log("Error en canUserAccessDocumentType: " . $e->getMessage());
        return false;
    }
}

// ===================================================================
// FUNCIONES AUXILIARES PARA OBTENER LISTAS PERMITIDAS
// ===================================================================

function getUserAllowedDepartments($userId, $companyId = null) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Verificar si es administrador
        $userQuery = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && ($user['role'] === 'admin' || $user['role'] === 'super_admin')) {
            // ADMINISTRADORES: TODOS LOS DEPARTAMENTOS
            if ($companyId) {
                $query = "SELECT id FROM departments WHERE company_id = ? AND status = 'active'";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$companyId]);
            } else {
                $query = "SELECT id FROM departments WHERE status = 'active'";
                $stmt = $pdo->prepare($query);
                $stmt->execute();
            }
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        }
        
        // Para usuarios normales
        $permissions = getUserGroupPermissions($userId);
        
        if (!$permissions['has_groups']) {
            return [];
        }
        
        $allowedDepartments = $permissions['restrictions']['departments'];
        
        // Si no hay restricciones = array vacío (sin acceso)
        if (empty($allowedDepartments)) {
            return [];
        }
        
        // Si se especifica empresa, filtrar solo departamentos de esa empresa
        if ($companyId) {
            $placeholders = str_repeat('?,', count($allowedDepartments) - 1) . '?';
            $query = "SELECT id FROM departments WHERE id IN ($placeholders) AND company_id = ? AND status = 'active'";
            
            $params = array_merge($allowedDepartments, [$companyId]);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        }
        
        return $allowedDepartments;
    } catch (Exception $e) {
        error_log("Error en getUserAllowedDepartments: " . $e->getMessage());
        return [];
    }
}

function getUserAllowedCompanies($userId) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Verificar si es administrador
        $userQuery = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && ($user['role'] === 'admin' || $user['role'] === 'super_admin')) {
            // ADMINISTRADORES: TODAS LAS EMPRESAS
            $query = "SELECT id FROM companies WHERE status = 'active'";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        }
        
        // Para usuarios normales
        $permissions = getUserGroupPermissions($userId);
        
        if (!$permissions['has_groups']) {
            return [];
        }
        
        return $permissions['restrictions']['companies'];
    } catch (Exception $e) {
        error_log("Error en getUserAllowedCompanies: " . $e->getMessage());
        return [];
    }
}

function getUserAllowedDocumentTypes($userId) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Verificar si es administrador
        $userQuery = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && ($user['role'] === 'admin' || $user['role'] === 'super_admin')) {
            // ADMINISTRADORES: TODOS LOS TIPOS DE DOCUMENTOS
            $query = "SELECT id FROM document_types WHERE status = 'active'";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        }
        
        // Para usuarios normales
        $permissions = getUserGroupPermissions($userId);
        
        if (!$permissions['has_groups']) {
            return [];
        }
        
        return $permissions['restrictions']['document_types'];
    } catch (Exception $e) {
        error_log("Error en getUserAllowedDocumentTypes: " . $e->getMessage());
        return [];
    }
}

// ===================================================================
// FUNCIONES WRAPPER PARA FACILITAR EL USO
// ===================================================================

function canUserUploadFiles($userId) {
    $permissions = getUserGroupPermissions($userId);
    return $permissions['permissions']['upload_files'] ?? false;
}

function canUserViewFiles($userId) {
    $permissions = getUserGroupPermissions($userId);
    return $permissions['permissions']['view_files'] ?? false;
}

function canUserCreateFolders($userId) {
    $permissions = getUserGroupPermissions($userId);
    return $permissions['permissions']['create_folders'] ?? false;
}

function canUserDownloadFiles($userId) {
    $permissions = getUserGroupPermissions($userId);
    return $permissions['permissions']['download_files'] ?? false;
}

function canUserDeleteFiles($userId) {
    $permissions = getUserGroupPermissions($userId);
    return $permissions['permissions']['delete_files'] ?? false;
}

function canUserMoveFiles($userId) {
    $permissions = getUserGroupPermissions($userId);
    return $permissions['permissions']['move_files'] ?? false;
}

// ===================================================================
// FUNCIÓN PARA DEBUGGING
// ===================================================================

function debugUserPermissions($userId) {
    $permissions = getUserGroupPermissions($userId);
    
    error_log("=== DEBUG PERMISOS USUARIO $userId ===");
    error_log("Has Groups: " . ($permissions['has_groups'] ? 'TRUE' : 'FALSE'));
    error_log("Permissions: " . json_encode($permissions['permissions']));
    error_log("Companies: " . implode(',', $permissions['restrictions']['companies']));
    error_log("Departments: " . implode(',', $permissions['restrictions']['departments']));
    error_log("Document Types: " . implode(',', $permissions['restrictions']['document_types']));
    error_log("=== FIN DEBUG ===");
    
    return $permissions;
}

?>