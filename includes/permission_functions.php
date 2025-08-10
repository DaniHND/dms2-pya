<?php
/**
 * includes/permission_functions.php
 * Funciones de validación de permisos mejoradas para el sistema de grupos
 * Prioriza la seguridad de grupos sobre permisos individuales
 */

/**
 * Obtiene los permisos efectivos de un usuario basado en sus grupos
 * PRIORIDAD: Grupos > Permisos individuales
 * 
 * @param int $userId ID del usuario
 * @return array Permisos y restricciones efectivas
 */
function getUserEffectivePermissions($userId) {
    static $cache = [];
    
    // Cache para evitar consultas repetidas
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Obtener información básica del usuario
        $userQuery = "SELECT id, username, role, company_id, department_id, status FROM users WHERE id = ?";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['status'] !== 'active') {
            return [
                'permissions' => [],
                'restrictions' => ['companies' => [], 'departments' => [], 'document_types' => []],
                'has_groups' => false
            ];
        }
        
        // Obtener grupos activos del usuario
        $groupQuery = "
            SELECT ug.id, ug.name, ug.module_permissions, ug.access_restrictions,
                   ug.download_limit_daily, ug.upload_limit_daily
            FROM user_groups ug
            INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
            WHERE ugm.user_id = ? AND ug.status = 'active'
            ORDER BY ug.is_system_group ASC, ug.created_at ASC
        ";
        
        $groupStmt = $pdo->prepare($groupQuery);
        $groupStmt->execute([$userId]);
        $groups = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Permisos específicos del nuevo sistema (5 opciones principales)
        $finalPermissions = [
            'upload_files' => false,      // 1. Subir archivo
            'view_files' => false,        // 2. Ver archivos
            'create_folders' => false,    // 3. Crear carpetas
            'download_files' => false,    // 4. Descargar
            'delete_files' => false       // 5. Eliminar archivo o documento
        ];
        
        $finalRestrictions = [
            'companies' => [],
            'departments' => [],
            'document_types' => []
        ];
        
        $limits = [
            'download_daily' => null,
            'upload_daily' => null
        ];
        
        if (!empty($groups)) {
            // El usuario tiene grupos - PRIORIDAD ABSOLUTA
            foreach ($groups as $group) {
                // Combinar permisos (OR lógico - el más permisivo gana)
                $groupPerms = json_decode($group['module_permissions'] ?: '{}', true);
                
                foreach ($finalPermissions as $key => $current) {
                    if (isset($groupPerms[$key]) && $groupPerms[$key] === true) {
                        $finalPermissions[$key] = true;
                    }
                }
                
                // Combinar restricciones (UNION de todas las permitidas)
                $groupRestrictions = json_decode($group['access_restrictions'] ?: '{}', true);
                foreach (['companies', 'departments', 'document_types'] as $type) {
                    if (isset($groupRestrictions[$type]) && is_array($groupRestrictions[$type])) {
                        $finalRestrictions[$type] = array_unique(array_merge(
                            $finalRestrictions[$type], 
                            $groupRestrictions[$type]
                        ));
                    }
                }
                
                // Aplicar límites más restrictivos
                if ($group['download_limit_daily'] !== null) {
                    $limits['download_daily'] = $limits['download_daily'] === null ? 
                        $group['download_limit_daily'] : 
                        min($limits['download_daily'], $group['download_limit_daily']);
                }
                
                if ($group['upload_limit_daily'] !== null) {
                    $limits['upload_daily'] = $limits['upload_daily'] === null ? 
                        $group['upload_limit_daily'] : 
                        min($limits['upload_daily'], $group['upload_limit_daily']);
                }
            }
        } else {
            // Usuario sin grupos - permisos mínimos y restricción a su contexto
            if ($user['role'] === 'admin') {
                // Administradores sin grupos tienen acceso completo
                foreach ($finalPermissions as $key => $value) {
                    $finalPermissions[$key] = true;
                }
            } else {
                // Usuarios normales sin grupos: solo lectura básica
                $finalPermissions['view_files'] = true;
                $finalPermissions['download_files'] = true;
            }
            
            // Restricción automática a su empresa/departamento
            if ($user['company_id']) {
                $finalRestrictions['companies'] = [(int)$user['company_id']];
            }
            if ($user['department_id']) {
                $finalRestrictions['departments'] = [(int)$user['department_id']];
            }
        }
        
        $result = [
            'permissions' => $finalPermissions,
            'restrictions' => $finalRestrictions,
            'limits' => $limits,
            'has_groups' => !empty($groups),
            'group_count' => count($groups),
            'user_role' => $user['role']
        ];
        
        $cache[$userId] = $result;
        return $result;
        
    } catch (Exception $e) {
        error_log('Error getting user effective permissions: ' . $e->getMessage());
        
        // En caso de error, retornar permisos mínimos por seguridad
        return [
            'permissions' => ['view_files' => false],
            'restrictions' => ['companies' => [], 'departments' => [], 'document_types' => []],
            'has_groups' => false,
            'error' => true
        ];
    }
}

/**
 * Verifica si un usuario tiene un permiso específico
 * 
 * @param int $userId ID del usuario
 * @param string $permission Permiso a verificar
 * @return bool
 */
function userHasPermission($userId, $permission) {
    $userPerms = getUserEffectivePermissions($userId);
    return isset($userPerms['permissions'][$permission]) && $userPerms['permissions'][$permission] === true;
}

/**
 * Verifica si un usuario puede acceder a un recurso específico
 * 
 * @param int $userId ID del usuario
 * @param string $resourceType Tipo de recurso (company, department, document_type)
 * @param int $resourceId ID del recurso
 * @return bool
 */
function userCanAccessResource($userId, $resourceType, $resourceId) {
    $userPerms = getUserEffectivePermissions($userId);
    $restrictions = $userPerms['restrictions'];
    
    // Mapear tipos de recursos
    $restrictionKey = $resourceType === 'company' ? 'companies' :
                     ($resourceType === 'department' ? 'departments' : 'document_types');
    
    // Si no hay restricciones definidas para este tipo, denegar acceso por seguridad
    if (!isset($restrictions[$restrictionKey]) || empty($restrictions[$restrictionKey])) {
        return false;
    }
    
    // Verificar si el recurso está en la lista de permitidos
    return in_array((int)$resourceId, $restrictions[$restrictionKey]);
}

/**
 * Obtiene la lista de empresas accesibles para un usuario
 * 
 * @param int $userId ID del usuario
 * @return array IDs de empresas accesibles
 */
function getUserAccessibleCompanies($userId) {
    $userPerms = getUserEffectivePermissions($userId);
    return $userPerms['restrictions']['companies'] ?? [];
}

/**
 * Obtiene la lista de departamentos accesibles para un usuario
 * 
 * @param int $userId ID del usuario
 * @return array IDs de departamentos accesibles
 */
function getUserAccessibleDepartments($userId) {
    $userPerms = getUserEffectivePermissions($userId);
    return $userPerms['restrictions']['departments'] ?? [];
}

/**
 * Obtiene la lista de tipos de documentos accesibles para un usuario
 * 
 * @param int $userId ID del usuario
 * @return array IDs de tipos de documentos accesibles
 */
function getUserAccessibleDocumentTypes($userId) {
    $userPerms = getUserEffectivePermissions($userId);
    return $userPerms['restrictions']['document_types'] ?? [];
}

/**
 * Verifica múltiples permisos a la vez
 * 
 * @param int $userId ID del usuario
 * @param array $permissions Array de permisos a verificar
 * @param bool $requireAll Si requiere todos los permisos (AND) o al menos uno (OR)
 * @return bool
 */
function userHasPermissions($userId, $permissions, $requireAll = true) {
    $userPerms = getUserEffectivePermissions($userId);
    $userPermissions = $userPerms['permissions'];
    
    if ($requireAll) {
        // Requiere TODOS los permisos
        foreach ($permissions as $permission) {
            if (!isset($userPermissions[$permission]) || !$userPermissions[$permission]) {
                return false;
            }
        }
        return true;
    } else {
        // Requiere AL MENOS UNO de los permisos
        foreach ($permissions as $permission) {
            if (isset($userPermissions[$permission]) && $userPermissions[$permission]) {
                return true;
            }
        }
        return false;
    }
}

/**
 * Construye una cláusula WHERE SQL para filtrar por restricciones de usuario
 * 
 * @param int $userId ID del usuario
 * @param string $tableAlias Alias de la tabla principal
 * @return array ['where' => string, 'params' => array]
 */
function buildUserRestrictionsSQL($userId, $tableAlias = 'd') {
    $userPerms = getUserEffectivePermissions($userId);
    $restrictions = $userPerms['restrictions'];
    
    $conditions = [];
    $params = [];
    
    // Restricción de empresas
    if (!empty($restrictions['companies'])) {
        $placeholders = str_repeat('?,', count($restrictions['companies']) - 1) . '?';
        $conditions[] = "{$tableAlias}.company_id IN ($placeholders)";
        $params = array_merge($params, $restrictions['companies']);
    } else {
        // Sin empresas permitidas = sin acceso
        $conditions[] = "1 = 0";
    }
    
    // Restricción de departamentos
    if (!empty($restrictions['departments'])) {
        $placeholders = str_repeat('?,', count($restrictions['departments']) - 1) . '?';
        $conditions[] = "{$tableAlias}.department_id IN ($placeholders)";
        $params = array_merge($params, $restrictions['departments']);
    } else {
        // Sin departamentos permitidos = sin acceso
        $conditions[] = "1 = 0";
    }
    
    // Restricción de tipos de documentos (si aplica)
    if (!empty($restrictions['document_types']) && strpos($tableAlias, 'doc') !== false) {
        $placeholders = str_repeat('?,', count($restrictions['document_types']) - 1) . '?';
        $conditions[] = "{$tableAlias}.document_type_id IN ($placeholders)";
        $params = array_merge($params, $restrictions['document_types']);
    }
    
    $whereClause = empty($conditions) ? "1 = 0" : implode(' AND ', $conditions);
    
    return [
        'where' => $whereClause,
        'params' => $params
    ];
}

/**
 * Valida si un usuario puede realizar una acción específica en un documento
 * 
 * @param int $userId ID del usuario
 * @param string $action Acción a realizar
 * @param array $documentInfo Información del documento
 * @return bool
 */
function canUserPerformDocumentAction($userId, $action, $documentInfo) {
    $userPerms = getUserEffectivePermissions($userId);
    
    // Verificar permiso básico
    $permissionMap = [
        'view' => 'view_files',
        'download' => 'download_files',
        'upload' => 'upload_files',
        'create_folder' => 'create_folders',
        'delete' => 'delete_files',
        'edit' => 'delete_files' // Reutilizamos delete_files para edición
    ];
    
    if (!isset($permissionMap[$action])) {
        return false;
    }
    
    $requiredPermission = $permissionMap[$action];
    if (!userHasPermission($userId, $requiredPermission)) {
        return false;
    }
    
    // Verificar restricciones de acceso
    if (isset($documentInfo['company_id']) && 
        !userCanAccessResource($userId, 'company', $documentInfo['company_id'])) {
        return false;
    }
    
    if (isset($documentInfo['department_id']) && 
        !userCanAccessResource($userId, 'department', $documentInfo['department_id'])) {
        return false;
    }
    
    if (isset($documentInfo['document_type_id']) && 
        !userCanAccessResource($userId, 'document_type', $documentInfo['document_type_id'])) {
        return false;
    }
    
    // Para eliminación, verificar si es el propietario (en reportes)
    if ($action === 'delete' && isset($documentInfo['user_id'])) {
        // Solo puede eliminar sus propios documentos, a menos que sea admin
        $userInfo = getUserEffectivePermissions($userId);
        if ($userInfo['user_role'] !== 'admin' && $documentInfo['user_id'] != $userId) {
            return false;
        }
    }
    
    return true;
}

/**
 * Obtiene estadísticas de permisos para un usuario
 * 
 * @param int $userId ID del usuario
 * @return array Estadísticas detalladas
 */
function getUserPermissionStats($userId) {
    $userPerms = getUserEffectivePermissions($userId);
    
    $activePermissions = array_filter($userPerms['permissions']);
    $restrictions = $userPerms['restrictions'];
    
    return [
        'has_groups' => $userPerms['has_groups'],
        'group_count' => $userPerms['group_count'] ?? 0,
        'active_permissions_count' => count($activePermissions),
        'active_permissions' => array_keys($activePermissions),
        'restricted_companies' => count($restrictions['companies'] ?? []),
        'restricted_departments' => count($restrictions['departments'] ?? []),
        'restricted_document_types' => count($restrictions['document_types'] ?? []),
        'security_level' => calculateUserSecurityLevel($userPerms),
        'limits' => $userPerms['limits'] ?? []
    ];
}

/**
 * Calcula el nivel de seguridad de un usuario
 * 
 * @param array $userPerms Permisos del usuario
 * @return string Nivel de seguridad
 */
function calculateUserSecurityLevel($userPerms) {
    $permissions = $userPerms['permissions'];
    $restrictions = $userPerms['restrictions'];
    
    $activePerms = count(array_filter($permissions));
    $totalRestrictions = count($restrictions['companies'] ?? []) + 
                        count($restrictions['departments'] ?? []) + 
                        count($restrictions['document_types'] ?? []);
    
    if (!$userPerms['has_groups']) {
        return 'default'; // Usuario sin grupos
    }
    
    if ($activePerms === 0) {
        return 'maximum'; // Sin permisos activos
    } elseif ($activePerms <= 2 && $totalRestrictions > 5) {
        return 'high'; // Pocos permisos, muchas restricciones
    } elseif ($activePerms <= 3 && $totalRestrictions > 2) {
        return 'medium'; // Permisos moderados
    } elseif ($totalRestrictions === 0) {
        return 'low'; // Sin restricciones
    } else {
        return 'medium'; // Caso general
    }
}

/**
 * Limpia la caché de permisos para un usuario específico
 * 
 * @param int $userId ID del usuario
 */
function clearUserPermissionsCache($userId = null) {
    static $cache = [];
    
    if ($userId === null) {
        $cache = []; // Limpiar toda la caché
    } else {
        unset($cache[$userId]); // Limpiar caché específica
    }
}

/**
 * Middleware para verificar permisos en controladores
 * 
 * @param int $userId ID del usuario
 * @param string $requiredPermission Permiso requerido
 * @param array $resourceInfo Información del recurso (opcional)
 * @throws Exception Si no tiene permisos
 */
function requirePermission($userId, $requiredPermission, $resourceInfo = []) {
    if (!userHasPermission($userId, $requiredPermission)) {
        throw new Exception("Acceso denegado: permiso '$requiredPermission' requerido");
    }
    
    // Verificar restricciones de recurso si se proporcionan
    foreach (['company_id', 'department_id', 'document_type_id'] as $field) {
        if (isset($resourceInfo[$field])) {
            $resourceType = str_replace('_id', '', $field);
            if (!userCanAccessResource($userId, $resourceType, $resourceInfo[$field])) {
                throw new Exception("Acceso denegado: sin permisos para este $resourceType");
            }
        }
    }
}

/**
 * Función auxiliar para logging de accesos
 * 
 * @param int $userId ID del usuario
 * @param string $action Acción realizada
 * @param string $resource Recurso accedido
 * @param bool $success Si la acción fue exitosa
 */
function logPermissionAccess($userId, $action, $resource, $success = true) {
    try {
        if (function_exists('logActivity')) {
            $message = $success ? "Acceso permitido: $action en $resource" : "Acceso denegado: $action en $resource";
            logActivity($userId, 'permission_check', 'security', null, $message);
        }
    } catch (Exception $e) {
        // No interrumpir el flujo por errores de logging
        error_log('Error logging permission access: ' . $e->getMessage());
    }
}
?>