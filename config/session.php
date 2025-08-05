<?php
// config/session.php
// Manejo de sesiones para DMS2 - VERSIÓN COMPLETA CON PERMISOS

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class SessionManager {
    
    public static function startSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function login($user) {
        self::startSession();
        
        // Verificar que $user es un array y tiene los índices necesarios
        if (!is_array($user)) {
            return false;
        }
        
        $_SESSION['user_id'] = $user['id'] ?? null;
        $_SESSION['username'] = $user['username'] ?? '';
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['last_name'] = $user['last_name'] ?? '';
        $_SESSION['company_id'] = $user['company_id'] ?? null;
        $_SESSION['department_id'] = $user['department_id'] ?? null;
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['last_activity'] = time();
        
        // Obtener permisos del usuario
        $_SESSION['permissions'] = self::getUserPermissions($user['id'] ?? null);
        
        // Actualizar último login
        if (isset($user['id'])) {
            try {
                require_once 'database.php';
                updateRecord('users', 
                    ['last_login' => date('Y-m-d H:i:s')], 
                    'id = :id', 
                    ['id' => $user['id']]
                );
            } catch (Exception $e) {
                error_log("Error updating last login: " . $e->getMessage());
            }
            
            try {
                // Log de actividad
                logActivity($user['id'], 'login', 'users', $user['id'], 'Usuario inició sesión');
            } catch (Exception $e) {
                error_log("Error logging login activity: " . $e->getMessage());
            }
        }
        
        return true;
    }
    
    public static function logout() {
        self::startSession();
        
        if (isset($_SESSION['user_id'])) {
            try {
                // Log de actividad
                require_once 'database.php';
                logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id'], 'Usuario cerró sesión');
            } catch (Exception $e) {
                error_log("Error logging logout activity: " . $e->getMessage());
            }
        }
        
        // Destruir todas las variables de sesión
        $_SESSION = array();
        
        // Destruir la cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destruir la sesión
        session_destroy();
    }
    
    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public static function checkSession() {
        self::startSession();
        
        if (!self::isLoggedIn()) {
            return false;
        }
        
        // Verificar timeout de sesión
        $timeout = 7200; // 2 horas por defecto
        
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > $timeout) {
            self::logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public static function requireLogin() {
        if (!self::checkSession()) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $path = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            
            // Construir URL base
            $baseUrl = $protocol . '://' . $host;
            if (strpos($_SERVER['REQUEST_URI'], '/dms2/') !== false) {
                $baseUrl .= '/dms2';
            }
            
            header('Location: ' . $baseUrl . '/login.php');
            exit();
        }
    }
    
    public static function requireAdmin() {
        self::requireLogin();
        
        if (!self::isAdmin()) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host;
            if (strpos($_SERVER['REQUEST_URI'], '/dms2/') !== false) {
                $baseUrl .= '/dms2';
            }
            
            header('Location: ' . $baseUrl . '/dashboard.php');
            exit();
        }
    }
    
    public static function requireRole($requiredRole) {
        self::requireLogin();
        
        $userRole = self::getUserRole();
        if ($userRole !== $requiredRole && $userRole !== 'admin') {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host;
            if (strpos($_SERVER['REQUEST_URI'], '/dms2/') !== false) {
                $baseUrl .= '/dms2';
            }
            
            header('Location: ' . $baseUrl . '/dashboard.php');
            exit();
        }
    }
    
    public static function getCurrentUser() {
        self::startSession();
        
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'first_name' => $_SESSION['first_name'] ?? '',
            'last_name' => $_SESSION['last_name'] ?? '',
            'company_id' => $_SESSION['company_id'] ?? null,
            'department_id' => $_SESSION['department_id'] ?? null,
            'role' => $_SESSION['role'] ?? 'user',
            'permissions' => $_SESSION['permissions'] ?? []
        ];
    }
    
    public static function getFullName() {
        self::startSession();
        $firstName = $_SESSION['first_name'] ?? '';
        $lastName = $_SESSION['last_name'] ?? '';
        return trim($firstName . ' ' . $lastName);
    }
    
    public static function getUserRole() {
        self::startSession();
        return $_SESSION['role'] ?? 'user';
    }
    
    public static function getUserId() {
        self::startSession();
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function isAdmin() {
        return self::getUserRole() === 'admin';
    }
    
    public static function getUserPermissions($userId = null) {
        if ($userId === null) {
            self::startSession();
            $userId = $_SESSION['user_id'] ?? null;
        }
        
        if (!$userId) {
            return [];
        }
        
        try {
            require_once 'database.php';
            
            // Obtener grupos del usuario
            $query = "SELECT sg.permissions 
                      FROM security_groups sg
                      INNER JOIN users u ON u.group_id = sg.id
                      WHERE u.id = :user_id AND sg.status = 'active'";
            
            $result = fetchOne($query, ['user_id' => $userId]);
            
            if ($result && $result['permissions']) {
                return json_decode($result['permissions'], true) ?: [];
            }
            
            // Permisos por defecto si no tiene grupo
            return [
                'documents' => ['view' => true, 'download' => true],
                'users' => ['view' => false]
            ];
            
        } catch (Exception $e) {
            error_log("Error getting user permissions: " . $e->getMessage());
            return [];
        }
    }
    
    public static function hasPermission($permission) {
        self::startSession();
        
        if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
            return false;
        }
        
        // Navegar por el array de permisos
        $parts = explode('.', $permission);
        $current = $_SESSION['permissions'];
        
        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                return false;
            }
            $current = $current[$part];
        }
        
        return $current === true;
    }
    
    // NUEVAS FUNCIONES DE PERMISOS DE GRUPOS
    
    /**
     * Obtener permisos consolidados del usuario actual usando el nuevo sistema
     */
    public static function getUserGroupPermissions() {
        self::startSession();
        
        if (!self::isLoggedIn()) {
            return [
                'permissions' => [],
                'restrictions' => ['companies' => [], 'departments' => [], 'document_types' => []]
            ];
        }
        
        // Usar la función global de permisos si existe
        if (function_exists('getUserPermissions')) {
            return getUserPermissions($_SESSION['user_id']);
        }
        
        // Fallback al método anterior
        return [
            'permissions' => $_SESSION['permissions'] ?? [],
            'restrictions' => ['companies' => [], 'departments' => [], 'document_types' => []]
        ];
    }
    
    /**
     * Verificar si el usuario actual tiene un permiso específico (nuevo sistema)
     */
    public static function hasUserPermission($permission) {
        // Si existe la función global, usarla
        if (function_exists('hasUserPermission')) {
            return hasUserPermission($permission);
        }
        
        // Usar permisos de grupos
        $perms = self::getUserGroupPermissions();
        return isset($perms['permissions'][$permission]) && $perms['permissions'][$permission] === true;
    }
    
    /**
     * Verificar acceso a empresa (actualizado para usar restricciones de grupos)
     */
    public static function canAccessCompany($companyId) {
        self::startSession();
        
        // Los admins pueden acceder a todas las empresas
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }
        
        // Usar restricciones de grupos
        $perms = self::getUserGroupPermissions();
        
        // Si no hay restricciones de empresa, puede acceder a todas
        if (empty($perms['restrictions']['companies'])) {
            return true;
        }
        
        // Verificar si la empresa está en las restricciones
        return in_array($companyId, $perms['restrictions']['companies']);
    }
    
    /**
     * Verificar acceso a departamento (actualizado para usar restricciones de grupos)
     */
    public static function canAccessDepartment($departmentId) {
        self::startSession();
        
        // Los admins pueden acceder a todos los departamentos
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }
        
        // Usar restricciones de grupos
        $perms = self::getUserGroupPermissions();
        
        // Si no hay restricciones de departamento, puede acceder a todos
        if (empty($perms['restrictions']['departments'])) {
            return true;
        }
        
        // Verificar si el departamento está en las restricciones
        return in_array($departmentId, $perms['restrictions']['departments']);
    }
    
    /**
     * Obtener información completa del usuario incluyendo permisos
     */
    public static function getFullUserInfo() {
        $basicInfo = self::getCurrentUser();
        
        if (!$basicInfo) {
            return null;
        }
        
        $permissions = self::getUserGroupPermissions();
        
        return array_merge($basicInfo, [
            'group_permissions' => $permissions['permissions'],
            'group_restrictions' => $permissions['restrictions'],
            'daily_limits' => $permissions['limits'] ?? ['download' => null, 'upload' => null]
        ]);
    }
    
    /**
     * Verificar si el usuario puede realizar una acción específica
     */
    public static function canPerformAction($action, $context = []) {
        switch ($action) {
            case 'upload_document':
                return self::hasUserPermission('create');
                
            case 'download_document':
                if (!self::hasUserPermission('download')) {
                    return false;
                }
                
                // Verificar límites diarios si están definidos
                $perms = self::getUserGroupPermissions();
                $downloadLimit = $perms['limits']['download'] ?? null;
                
                if ($downloadLimit !== null) {
                    $todayDownloads = self::getTodayDownloadCount();
                    return $todayDownloads < $downloadLimit;
                }
                
                return true;
                
            case 'view_document':
                return self::hasUserPermission('view');
                
            case 'edit_document':
                return self::hasUserPermission('edit');
                
            case 'delete_document':
                return self::hasUserPermission('delete');
                
            case 'manage_users':
                return self::hasUserPermission('manage_users');
                
            case 'system_config':
                return self::hasUserPermission('system_config');
                
            default:
                return false;
        }
    }
    
    /**
     * Obtener conteo de descargas de hoy
     */
    private static function getTodayDownloadCount() {
        if (!self::isLoggedIn()) {
            return 0;
        }
        
        try {
            require_once 'database.php';
            
            $query = "
                SELECT COUNT(*) 
                FROM activity_logs 
                WHERE user_id = ? 
                AND action = 'download_document' 
                AND DATE(created_at) = CURDATE()
            ";
            
            $result = fetchOne($query, [$_SESSION['user_id']]);
            return (int)($result['COUNT(*)'] ?? 0);
            
        } catch (Exception $e) {
            error_log('Error obteniendo descargas de hoy: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtener mensaje informativo sobre las restricciones activas
     */
    public static function getRestrictionsMessage() {
        $perms = self::getUserGroupPermissions();
        $messages = [];
        
        if (!empty($perms['restrictions']['companies'])) {
            $count = count($perms['restrictions']['companies']);
            $messages[] = "Acceso limitado a $count empresa(s)";
        }
        
        if (!empty($perms['restrictions']['departments'])) {
            $count = count($perms['restrictions']['departments']);
            $messages[] = "Acceso limitado a $count departamento(s)";
        }
        
        if (!empty($perms['restrictions']['document_types'])) {
            $count = count($perms['restrictions']['document_types']);
            $messages[] = "Acceso limitado a $count tipo(s) de documento";
        }
        
        return !empty($messages) ? implode(', ', $messages) : null;
    }
    
    // FUNCIONES DE MENSAJES FLASH
    
    public static function setFlashMessage($type, $message) {
        self::startSession();
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    public static function getFlashMessage() {
        self::startSession();
        
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        
        return null;
    }
    
    public static function hasFlashMessage() {
        self::startSession();
        return isset($_SESSION['flash_message']);
    }
    
    // FUNCIONES DE VALIDACIÓN DE DESCARGAS
    
    public static function canDownload() {
        self::startSession();
        
        if (!self::isLoggedIn()) {
            return false;
        }
        
        try {
            require_once 'database.php';
            
            $query = "SELECT download_enabled FROM users WHERE id = :id";
            $result = fetchOne($query, ['id' => $_SESSION['user_id']]);
            
            return $result ? ($result['download_enabled'] ?? true) : false;
            
        } catch (Exception $e) {
            error_log("Error checking download permission: " . $e->getMessage());
            return false;
        }
    }
}

// FUNCIONES AUXILIARES GLOBALES

function checkPermission($permission) {
    return SessionManager::hasPermission($permission);
}

function getCurrentUser() {
    return SessionManager::getCurrentUser();
}

function getFullName() {
    return SessionManager::getFullName();
}

function requireLogin() {
    SessionManager::requireLogin();
}

function requireAdmin() {
    SessionManager::requireAdmin();
}

function requireRole($role) {
    SessionManager::requireRole($role);
}

function isLoggedIn() {
    return SessionManager::isLoggedIn();
}

function getUserRole() {
    return SessionManager::getUserRole();
}

function getUserId() {
    return SessionManager::getUserId();
}

function setFlashMessage($type, $message) {
    SessionManager::setFlashMessage($type, $message);
}

function getFlashMessage() {
    return SessionManager::getFlashMessage();
}

/**
 * Verificar si el usuario actual puede realizar una acción
 */
function canUserPerform($action, $context = []) {
    return SessionManager::canPerformAction($action, $context);
}

/**
 * Verificar si el usuario actual tiene un permiso específico
 */
function userHasPermission($permission) {
    return SessionManager::hasUserPermission($permission);
}

/**
 * Obtener empresas accesibles por el usuario actual
 */
function getUserAccessibleCompanies() {
    if (function_exists('getAccessibleCompanies')) {
        return getAccessibleCompanies();
    }
    return [];
}

/**
 * Obtener departamentos accesibles por el usuario actual
 */
function getUserAccessibleDepartments($companyId = null) {
    if (function_exists('getAccessibleDepartments')) {
        return getAccessibleDepartments($companyId);
    }
    return [];
}

/**
 * Obtener tipos de documentos accesibles por el usuario actual
 */
function getUserAccessibleDocumentTypes() {
    if (function_exists('getAccessibleDocumentTypes')) {
        return getAccessibleDocumentTypes();
    }
    return [];
}

// Función para obtener configuración del sistema
function getSystemConfig($key, $default = null) {
    try {
        require_once 'database.php';
        $query = "SELECT config_value FROM system_config WHERE config_key = :key";
        $result = fetchOne($query, ['key' => $key]);
        
        if ($result && isset($result['config_value'])) {
            return $result['config_value'];
        }
        
        return $default;
        
    } catch (Exception $e) {
        error_log("Error getting system config '$key': " . $e->getMessage());
        return $default;
    }
}

// Inicializar sesión automáticamente
SessionManager::startSession();
?>