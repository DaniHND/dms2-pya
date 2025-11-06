<?php
//bootstrap.php - Inicializador global del sistema DMS2 
 
// Prevenir inclusión múltiple
if (defined('DMS_BOOTSTRAP_LOADED')) {
    return;
}
define('DMS_BOOTSTRAP_LOADED', true);

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 1); 
ini_set('log_errors', 1);

// Definir constantes del sistema
if (!defined('DMS_ROOT')) {
    define('DMS_ROOT', __DIR__);
}

if (!defined('DMS_VERSION')) {
    define('DMS_VERSION', '2.1.0-UNIFIED');
}

// Configurar zona horaria
date_default_timezone_set('America/Tegucigalpa');

// Configurar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función de auto-carga para clases
spl_autoload_register(function ($className) {
    $paths = [
        DMS_ROOT . '/classes/' . $className . '.php',
        DMS_ROOT . '/config/' . $className . '.php',
        DMS_ROOT . '/includes/' . $className . '.php',
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// 1. Cargar configuración de base de datos
$databasePath = DMS_ROOT . '/config/database.php';
if (file_exists($databasePath)) {
    require_once $databasePath;
} else {
    die('Error: No se pudo cargar config/database.php');
}

// 2. Cargar SessionManager
$sessionPath = DMS_ROOT . '/config/session.php';
if (file_exists($sessionPath)) {
    require_once $sessionPath;
} else {
    die('Error: No se pudo cargar config/session.php');
}

// 3. Cargar funciones helper básicas
$helperPath = DMS_ROOT . '/includes/helper_functions.php';
if (file_exists($helperPath)) {
    require_once $helperPath;
}

// 4. Cargar funciones principales
$functionsPath = DMS_ROOT . '/includes/functions.php';
if (file_exists($functionsPath)) {
    require_once $functionsPath;
}

// 5. *** NUEVO *** Cargar Sistema Unificado de Permisos
$unifiedPermissionsPath = DMS_ROOT . '/includes/UnifiedPermissionSystem.php';
if (file_exists($unifiedPermissionsPath)) {
    require_once $unifiedPermissionsPath;
    error_log('DMS2: Sistema Unificado de Permisos cargado');
} else {
    error_log('DMS2: ADVERTENCIA - Sistema Unificado de Permisos no encontrado');
    // Cargar sistema de permisos anterior como fallback
    $legacyPermissionsPath = DMS_ROOT . '/includes/permission_functions.php';
    if (file_exists($legacyPermissionsPath)) {
        require_once $legacyPermissionsPath;
        error_log('DMS2: Sistema de Permisos Legacy cargado como fallback');
    }
}

// Función fetchOne si no existe
if (!function_exists('fetchOne')) {
    function fetchOne($query, $params = []) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error in fetchOne: ' . $e->getMessage());
            return null;
        }
    }
}

// Función fetchAll si no existe
if (!function_exists('fetchAll')) {
    function fetchAll($query, $params = []) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error in fetchAll: ' . $e->getMessage());
            return [];
        }
    }
}

// Función logActivity si no existe
if (!function_exists('logActivity')) {
    function logActivity($userId, $action, $tableName = null, $recordId = null, $description = null) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $query = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
            
            $stmt = $pdo->prepare($query);
            return $stmt->execute([$userId, $action, $tableName, $recordId, $description, $ipAddress, $userAgent]);
        } catch (Exception $e) {
            error_log('Error in logActivity: ' . $e->getMessage());
            return false;
        }
    }
}

// Función formatBytes si no existe
if (!function_exists('formatBytes')) {
    function formatBytes($size, $precision = 2) {
        if ($size == 0 || !is_numeric($size)) {
            return '0 B';
        }
        
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $base = log($size, 1024);
        $unitIndex = floor($base);
        
        // Validar el índice
        if ($unitIndex < 0) $unitIndex = 0;
        if ($unitIndex >= count($units)) $unitIndex = count($units) - 1;
        
        $pow = pow(1024, $base - $unitIndex);
        $unit = $units[$unitIndex];
        
        return round($pow, $precision) . ' ' . $unit;
    }
}

// Función getFullName si no existe
if (!function_exists('getFullName')) {
    function getFullName($userId = null) {
        if ($userId === null) {
            $currentUser = SessionManager::getCurrentUser();
            if ($currentUser) {
                return trim($currentUser['first_name'] . ' ' . $currentUser['last_name']);
            }
            return 'Usuario Desconocido';
        }
        
        try {
            $query = "SELECT first_name, last_name FROM users WHERE id = ? AND status = 'active'";
            $user = fetchOne($query, [$userId]);
            
            if ($user) {
                return trim($user['first_name'] . ' ' . $user['last_name']);
            }
            
            return 'Usuario Desconocido';
        } catch (Exception $e) {
            error_log('Error in getFullName: ' . $e->getMessage());
            return 'Usuario Desconocido';
        }
    }
}

// Función getFileExtension si no existe
if (!function_exists('getFileExtension')) {
    function getFileExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
}

// Asegurar compatibilidad con sistemas que llaman funciones con nombres antiguos
if (!function_exists('userHasPermission')) {
    function userHasPermission($userId, $permission) {
        return hasUserPermission($permission, $userId);
    }
}

if (!function_exists('canAccessCompany')) {
    function canAccessCompany($companyId, $userId = null) {
        return canAccessResource('company', $companyId, $userId);
    }
}

if (!function_exists('canAccessDepartment')) {
    function canAccessDepartment($departmentId, $userId = null) {
        return canAccessResource('department', $departmentId, $userId);
    }
}

if (!function_exists('getUserEffectivePermissions')) {
    function getUserEffectivePermissions($userId) {
        return getUserPermissions($userId);
    }
}


  //Función para verificar permisos al inicio de páginas
if (!function_exists('requirePermission')) {
    function requirePermission($permission, $redirectUrl = '../../dashboard.php') {
        if (!hasUserPermission($permission)) {
            $_SESSION['error_message'] = 'No tienes permisos para acceder a esta función.';
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

/**
 * Función para verificar acceso a recurso específico
 */
if (!function_exists('requireResourceAccess')) {
    function requireResourceAccess($resourceType, $resourceId, $redirectUrl = '../../dashboard.php') {
        if (!canAccessResource($resourceType, $resourceId)) {
            $_SESSION['error_message'] = 'No tienes acceso a este recurso.';
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

/**
 * Función para obtener SQL con restricciones aplicadas
 */
if (!function_exists('buildRestrictedQuery')) {
    function buildRestrictedQuery($baseQuery, $tableAlias = 'd', $userId = null) {
        $restrictions = getQueryRestrictions($tableAlias, $userId);
        
        if (!empty($restrictions['where'])) {
            // Agregar restricciones al WHERE existente o crear nuevo WHERE
            if (stripos($baseQuery, 'WHERE') !== false) {
                $baseQuery .= ' AND (' . $restrictions['where'] . ')';
            } else {
                $baseQuery .= ' WHERE ' . $restrictions['where'];
            }
        }
        
        return [
            'query' => $baseQuery,
            'params' => $restrictions['params']
        ];
    }
}

/**
 * Verificar que el sistema esté completamente cargado
 */
function verifyUnifiedSystemLoaded() {
    $required = [
        'class' => ['SessionManager', 'Database', 'UnifiedPermissionSystem'],
        'function' => ['fetchOne', 'fetchAll', 'logActivity', 'getFullName', 'formatBytes', 'hasUserPermission', 'canAccessResource']
    ];
    
    $missing = [];
    
    foreach ($required['class'] as $class) {
        if (!class_exists($class)) {
            $missing[] = "Clase: $class";
        }
    }
    
    foreach ($required['function'] as $function) {
        if (!function_exists($function)) {
            $missing[] = "Función: $function";
        }
    }
    
    if (!empty($missing)) {
        error_log('Sistema DMS2 Unificado incompleto. Faltantes: ' . implode(', ', $missing));
        return false;
    }
    
    return true;
}

// Verificar que todo esté cargado correctamente
if (!verifyUnifiedSystemLoaded()) {
    error_log('Error: Sistema DMS2 Unificado no se cargó completamente en bootstrap.php');
    // Mostrar error detallado en desarrollo
    if (ini_get('display_errors')) {
        die('Error: Sistema DMS2 Unificado no se cargó completamente. Revise los logs de error.');
    }
} else {
    // Log de inicialización exitosa
    error_log('DMS2 Bootstrap Unificado cargado correctamente - Versión ' . DMS_VERSION);
    
    // Inicializar el sistema de permisos
    if (class_exists('UnifiedPermissionSystem')) {
        $permissionSystem = UnifiedPermissionSystem::getInstance();
        error_log('DMS2: Sistema Unificado de Permisos inicializado');
    }
}

/**
 * Configurar headers de seguridad básicos
 */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
}

/**
 * Definir constantes de permisos para facilitar el uso
 */
if (!defined('PERM_VIEW_FILES')) {
    define('PERM_VIEW_FILES', 'view_files');
    define('PERM_UPLOAD_FILES', 'upload_files');
    define('PERM_DOWNLOAD_FILES', 'download_files');
    define('PERM_DELETE_FILES', 'delete_files');
    define('PERM_CREATE_FOLDERS', 'create_folders');
    define('PERM_VIEW_REPORTS', 'view_reports');
    define('PERM_MANAGE_USERS', 'manage_users');
    define('PERM_MANAGE_COMPANIES', 'manage_companies');
    define('PERM_MANAGE_GROUPS', 'manage_groups');
    define('PERM_SYSTEM_ADMIN', 'system_admin');
}

/**
 * Función de debug para permisos (solo en desarrollo)
 */
if (!function_exists('debugPermissions') && ini_get('display_errors')) {
    function debugPermissions($userId = null) {
        if (!class_exists('UnifiedPermissionSystem')) {
            return "Sistema Unificado no disponible";
        }
        
        $permissionSystem = UnifiedPermissionSystem::getInstance();
        return $permissionSystem->getPermissionDebugInfo($userId ?: SessionManager::getUserId());
    }
}

?>