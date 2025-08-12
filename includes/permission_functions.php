<?php
/**
 * includes/permission_functions.php - VERSIÓN CORREGIDA SIMPLE
 * Funciones básicas de permisos que funcionan sin grupos complejos
 */

/**
 * Verifica si un usuario tiene un permiso específico (versión simple)
 */
if (!function_exists('hasUserPermission')) {
    function hasUserPermission($permission, $userId = null) {
        if ($userId === null && class_exists('SessionManager')) {
            $userId = SessionManager::getUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        // Si es admin, puede hacer todo
        if (class_exists('SessionManager') && SessionManager::isAdmin()) {
            return true;
        }
        
        // Para usuarios normales, permisos básicos
        $basicPermissions = [
            'view_files' => true,
            'view' => true,
            'download_files' => false,
            'upload_files' => false,
            'delete_files' => false,
            'create_folders' => false
        ];
        
        return isset($basicPermissions[$permission]) ? $basicPermissions[$permission] : false;
    }
}

/**
 * Función alias para compatibilidad
 */
if (!function_exists('userHasPermission')) {
    function userHasPermission($userId, $permission) {
        return hasUserPermission($permission, $userId);
    }
}

/**
 * Función para obtener permisos efectivos (versión simple)
 */
if (!function_exists('getUserEffectivePermissions')) {
    function getUserEffectivePermissions($userId) {
        $isAdmin = false;
        if (class_exists('SessionManager')) {
            $isAdmin = SessionManager::isAdmin();
        }
        
        return [
            'permissions' => [
                'upload_files' => $isAdmin,
                'view_files' => true,
                'create_folders' => $isAdmin,
                'download_files' => $isAdmin,
                'delete_files' => $isAdmin
            ],
            'restrictions' => [
                'companies' => [],
                'departments' => [],
                'document_types' => []
            ],
            'has_groups' => false,
            'user_role' => $isAdmin ? 'admin' : 'user'
        ];
    }
}

/**
 * Verificar acceso a recursos (versión simple)
 */
if (!function_exists('userCanAccessResource')) {
    function userCanAccessResource($userId, $resourceType, $resourceId) {
        // Los admins pueden acceder a todo
        if (class_exists('SessionManager') && SessionManager::isAdmin()) {
            return true;
        }
        
        // Para usuarios normales, acceso básico
        return true;
    }
}

/**
 * Funciones de conveniencia
 */
if (!function_exists('canViewReports')) {
    function canViewReports() {
        return hasUserPermission('view_files');
    }
}

if (!function_exists('canDownloadFiles')) {
    function canDownloadFiles() {
        return hasUserPermission('download_files');
    }
}

if (!function_exists('canAccessBasic')) {
    function canAccessBasic($action) {
        return hasUserPermission($action);
    }
}

?>