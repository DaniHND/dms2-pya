<?php
/**
 * bootstrap.php
 * Archivo de inicialización del sistema DMS2
 * Versión simplificada sin ENVIRONMENT
 */

// Definir constantes del sistema
define('DMS_VERSION', '2.0.0');
define('DMS_ROOT', __DIR__);

// Configuración de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', DMS_ROOT . '/logs/error.log');

// Autoloader básico para clases del sistema
spl_autoload_register(function ($class) {
    $classFile = DMS_ROOT . '/classes/' . $class . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// Cargar archivos de configuración base
require_once DMS_ROOT . '/config/database.php';
require_once DMS_ROOT . '/config/session.php';

// ===================================================================
// SISTEMA DE PERMISOS DE GRUPOS - CARGA PRIORITARIA
// ===================================================================

// Cargar sistema de permisos de grupos (PRIORIDAD ABSOLUTA)
require_once DMS_ROOT . '/includes/group_permissions.php';
require_once DMS_ROOT . '/includes/permission_check.php';

// ===================================================================
// FUNCIONES GLOBALES DEL SISTEMA
// ===================================================================

/**
 * Función para obtener la URL base del sistema
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script);
    
    // Limpiar path
    $path = str_replace('\\', '/', $path);
    $path = rtrim($path, '/');
    
    return $protocol . $host . $path;
}

/**
 * Función para redireccionar con mensajes
 */
function redirectWithMessage($url, $message, $type = 'info') {
    session_start();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit;
}

/**
 * Función para mostrar mensajes flash
 */
function getFlashMessage() {
    session_start();
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Función para escapar HTML
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Función para formatear fechas
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Función para formatear tamaños de archivo
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Función para generar tokens CSRF
 */
function generateCSRFToken() {
    session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Función para verificar tokens CSRF
 */
function verifyCSRFToken($token) {
    session_start();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Función para limpiar y validar entrada
 */
function cleanInput($input) {
    if (is_array($input)) {
        return array_map('cleanInput', $input);
    }
    return trim(strip_tags($input));
}

/**
 * Función para validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Función para log de actividades del sistema
 */
function logSystemActivity($action, $description, $userId = null, $tableAffected = null, $recordId = null) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $tableAffected,
            $recordId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

// ===================================================================
// FUNCIONES ESPECÍFICAS DE PERMISOS (WRAPPERS)
// ===================================================================

/**
 * Verificar si el usuario actual puede subir archivos
 */
function currentUserCanUpload() {
    if (!SessionManager::isLoggedIn()) return false;
    $user = SessionManager::getCurrentUser();
    return canUserUploadFiles($user['id']);
}

/**
 * Verificar si el usuario actual puede ver archivos
 */
function currentUserCanViewFiles() {
    if (!SessionManager::isLoggedIn()) return false;
    $user = SessionManager::getCurrentUser();
    return canUserViewFiles($user['id']);
}

/**
 * Verificar si el usuario actual puede crear carpetas
 */
function currentUserCanCreateFolders() {
    if (!SessionManager::isLoggedIn()) return false;
    $user = SessionManager::getCurrentUser();
    return canUserCreateFolders($user['id']);
}

/**
 * Verificar si el usuario actual puede descargar archivos
 */
function currentUserCanDownload() {
    if (!SessionManager::isLoggedIn()) return false;
    $user = SessionManager::getCurrentUser();
    return canUserDownloadFiles($user['id']);
}

/**
 * Verificar si el usuario actual puede eliminar archivos
 */
function currentUserCanDelete() {
    if (!SessionManager::isLoggedIn()) return false;
    $user = SessionManager::getCurrentUser();
    return canUserDeleteFiles($user['id']);
}

/**
 * Obtener empresas permitidas para el usuario actual
 */
function getCurrentUserAllowedCompanies() {
    if (!SessionManager::isLoggedIn()) return [];
    $user = SessionManager::getCurrentUser();
    return getUserAllowedCompanies($user['id']);
}

/**
 * Obtener departamentos permitidos para el usuario actual
 */
function getCurrentUserAllowedDepartments($companyId = null) {
    if (!SessionManager::isLoggedIn()) return [];
    $user = SessionManager::getCurrentUser();
    return getUserAllowedDepartments($user['id'], $companyId);
}

/**
 * Obtener tipos de documentos permitidos para el usuario actual
 */
function getCurrentUserAllowedDocumentTypes() {
    if (!SessionManager::isLoggedIn()) return [];
    $user = SessionManager::getCurrentUser();
    return getUserAllowedDocumentTypes($user['id']);
}

/**
 * Función para verificar acceso a módulos específicos
 */
function checkModuleAccess($module) {
    if (!SessionManager::isLoggedIn()) {
        redirectWithMessage('/login.php', 'Debe iniciar sesión', 'warning');
    }
    
    $user = SessionManager::getCurrentUser();
    
    // Verificar que tenga grupos activos
    $userPerms = getUserGroupPermissions($user['id']);
    if (!$userPerms['has_groups']) {
        redirectWithMessage('/dashboard.php', 'Usuario sin grupos de acceso asignados. Contacte al administrador.', 'error');
    }
    
    // Verificar permisos específicos según el módulo
    switch ($module) {
        case 'upload':
            if (!canUserUploadFiles($user['id'])) {
                redirectWithMessage('/dashboard.php', 'No tiene permisos para subir archivos', 'error');
            }
            break;
            
        case 'inbox':
        case 'documents':
            if (!canUserViewFiles($user['id'])) {
                redirectWithMessage('/dashboard.php', 'No tiene permisos para ver archivos', 'error');
            }
            break;
            
        case 'folders':
            if (!canUserCreateFolders($user['id'])) {
                redirectWithMessage('/dashboard.php', 'No tiene permisos para crear carpetas', 'error');
            }
            break;
            
        case 'download':
            if (!canUserDownloadFiles($user['id'])) {
                redirectWithMessage('/dashboard.php', 'No tiene permisos para descargar archivos', 'error');
            }
            break;
            
        case 'delete':
            if (!canUserDeleteFiles($user['id'])) {
                redirectWithMessage('/dashboard.php', 'No tiene permisos para eliminar archivos', 'error');
            }
            break;
    }
}

// ===================================================================
// CONFIGURACIÓN DE ZONA HORARIA
// ===================================================================

// Establecer zona horaria para Honduras
date_default_timezone_set('America/Tegucigalpa');

// ===================================================================
// CONFIGURACIÓN DE SESIÓN SEGURA
// ===================================================================

// Configurar sesión segura
ini_set('session.cookie_secure', '0'); // Cambiar a 1 en HTTPS
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');

// ===================================================================
// MANEJO DE ERRORES PERSONALIZADO (SIMPLIFICADO)
// ===================================================================

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE'
    ];
    
    $errorType = $errorTypes[$severity] ?? 'UNKNOWN';
    $logMessage = "[$errorType] $message in $file on line $line";
    
    error_log($logMessage);
    
    // No mostrar errores detallados al usuario
    return true;
});

// ===================================================================
// INICIALIZACIÓN COMPLETADA
// ===================================================================

// Marcar que el bootstrap se cargó correctamente
define('DMS_BOOTSTRAP_LOADED', true);

// Log de inicialización
error_log("DMS2 Bootstrap loaded successfully - Version " . DMS_VERSION);
?>