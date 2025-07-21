<?php
// config/session.php
// Manejo de sesiones para DMS2 - VERSIÓN CORREGIDA SIN WARNINGS

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
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            self::redirectUnauthorized();
        }
    }
    
    public static function requireRole($role) {
        self::requireLogin();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
            self::redirectUnauthorized();
        }
    }
    
    public static function requireManager() {
        self::requireLogin();
        $allowedRoles = ['admin', 'manager'];
        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
            self::redirectUnauthorized();
        }
    }
    
    private static function redirectUnauthorized() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Construir URL base
        $baseUrl = $protocol . '://' . $host;
        if (strpos($_SERVER['REQUEST_URI'], '/dms2/') !== false) {
            $baseUrl .= '/dms2';
        }
        
        self::setFlashMessage('error', 'No tiene permisos para acceder a esta sección');
        header('Location: ' . $baseUrl . '/dashboard.php');
        exit();
    }
    
    public static function getUserPermissions($userId) {
        if (!$userId) {
            return [];
        }
        
        try {
            require_once 'database.php';
            
            // Obtener permisos del usuario basados en su grupo
            $query = "SELECT ug.permissions 
                      FROM user_groups ug
                      INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
                      WHERE ugm.user_id = :user_id AND ug.status = 'active'";
            
            $result = fetchOne($query, ['user_id' => $userId]);
            
            if ($result && isset($result['permissions']) && !empty($result['permissions'])) {
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
    
    public static function canAccessCompany($companyId) {
        self::startSession();
        
        // Admin puede acceder a todas las empresas
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }
        
        // Usuario solo puede acceder a su empresa
        return isset($_SESSION['company_id']) && $_SESSION['company_id'] == $companyId;
    }
    
    public static function canAccessDepartment($departmentId) {
        self::startSession();
        
        // Admin puede acceder a todos los departamentos
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }
        
        // Usuario solo puede acceder a su departamento
        return isset($_SESSION['department_id']) && $_SESSION['department_id'] == $departmentId;
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
    
    public static function getCompanyId() {
        self::startSession();
        return $_SESSION['company_id'] ?? null;
    }
    
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
    
    public static function regenerateSession() {
        self::startSession();
        session_regenerate_id(true);
    }
    
    public static function isAdmin() {
        return self::getUserRole() === 'admin';
    }
    
    public static function isManager() {
        $role = self::getUserRole();
        return in_array($role, ['admin', 'manager']);
    }
    
    public static function canDownloadDocuments() {
        self::startSession();
        
        try {
            require_once 'database.php';
            $userId = self::getUserId();
            
            if (!$userId) {
                return false;
            }
            
            $query = "SELECT download_enabled FROM users WHERE id = :id";
            $result = fetchOne($query, ['id' => $userId]);
            
            return $result && ($result['download_enabled'] ?? false);
            
        } catch (Exception $e) {
            error_log("Error checking download permission: " . $e->getMessage());
            return false;
        }
    }
}

// Funciones auxiliares globales
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

// Función para obtener configuración del sistema
function getSystemConfig($key, $default = null) {
    try {
        require_once 'database.php';
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = :key";
        $result = fetchOne($query, ['key' => $key]);
        
        if ($result && isset($result['setting_value'])) {
            return $result['setting_value'];
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