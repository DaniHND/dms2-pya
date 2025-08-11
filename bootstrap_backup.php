<?php
/**
 * bootstrap.php - Inicializador global del sistema DMS2
 * Este archivo debe ser incluido en TODOS los módulos
 */

// Prevenir inclusión múltiple
if (defined('DMS_BOOTSTRAP_LOADED')) {
    return;
}
define('DMS_BOOTSTRAP_LOADED', true);

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0); // Cambiar a 1 para debug
ini_set('log_errors', 1);

// Definir constantes del sistema
if (!defined('DMS_ROOT')) {
    define('DMS_ROOT', __DIR__);
}

if (!defined('DMS_VERSION')) {
    define('DMS_VERSION', '2.0.0');
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
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// ============================================================================
// CARGAR ARCHIVOS CORE DEL SISTEMA
// ============================================================================

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

// 3. Cargar funciones de permisos
$permissionsPath = DMS_ROOT . '/includes/permission_functions.php';
if (file_exists($permissionsPath)) {
    require_once $permissionsPath;
}

// 4. Cargar funciones helper
$helperPath = DMS_ROOT . '/includes/helper_functions.php';
if (file_exists($helperPath)) {
    require_once $helperPath;
}

// ============================================================================
// DEFINIR FUNCIONES BÁSICAS SI NO EXISTEN
// ============================================================================

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
            return false;
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
            
            $query = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address, user_agent) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt = $pdo->prepare($query);
            return $stmt->execute([
                $userId, $action, $tableName, $recordId, $description, $ipAddress, $userAgent
            ]);
        } catch (Exception $e) {
            error_log('Error logging activity: ' . $e->getMessage());
            return false;
        }
    }
}

// Función getFullName si no existe
if (!function_exists('getFullName')) {
    function getFullName($userId = null) {
        if ($userId === null) {
            if (class_exists('SessionManager')) {
                return SessionManager::getFullUserName();
            }
            return 'Usuario Desconocido';
        }
        
        try {
            $user = fetchOne("SELECT first_name, last_name FROM users WHERE id = ?", [$userId]);
            return $user ? trim($user['first_name'] . ' ' . $user['last_name']) : 'Usuario Desconocido';
        } catch (Exception $e) {
            return 'Usuario Desconocido';
        }
    }
}

// ============================================================================
// FUNCIONES DE UTILIDAD ADICIONALES
// ============================================================================

/**
 * Obtener la ruta base del proyecto desde cualquier subdirectorio
 */
if (!function_exists('getProjectRoot')) {
    function getProjectRoot($currentFile = null) {
        if ($currentFile === null) {
            $currentFile = $_SERVER['SCRIPT_FILENAME'];
        }
        
        $dir = dirname($currentFile);
        $maxDepth = 10; // Prevenir bucles infinitos
        $depth = 0;
        
        while ($depth < $maxDepth) {
            if (file_exists($dir . '/config/database.php')) {
                return $dir;
            }
            
            $parentDir = dirname($dir);
            if ($parentDir === $dir) {
                break; // Llegamos a la raíz del sistema
            }
            
            $dir = $parentDir;
            $depth++;
        }
        
        return __DIR__; // Fallback
    }
}

/**
 * Incluir bootstrap desde cualquier ubicación
 */
if (!function_exists('includeBootstrap')) {
    function includeBootstrap($currentFile = null) {
        $root = getProjectRoot($currentFile);
        $bootstrapPath = $root . '/bootstrap.php';
        
        if (file_exists($bootstrapPath) && !defined('DMS_BOOTSTRAP_LOADED')) {
            require_once $bootstrapPath;
        }
    }
}

/**
 * Verificar que el sistema esté completamente cargado
 */
if (!function_exists('verifySystemLoaded')) {
    function verifySystemLoaded() {
        $required = [
            'class' => ['SessionManager', 'Database'],
            'function' => ['fetchOne', 'fetchAll', 'logActivity', 'getFullName']
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
            error_log('Sistema DMS2 incompleto. Faltantes: ' . implode(', ', $missing));
            return false;
        }
        
        return true;
    }
}

// ============================================================================
// VERIFICACIÓN FINAL
// ============================================================================

// Verificar que todo esté cargado correctamente
if (!verifySystemLoaded()) {
    error_log('Error: Sistema DMS2 no se cargó completamente en bootstrap.php');
}

// Log de inicialización
error_log('DMS2 Bootstrap cargado correctamente - Versión ' . DMS_VERSION);

?>