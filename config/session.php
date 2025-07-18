<?php
// config/session.php
// Manejo de sesiones para DMS2 - CON requireRole() AGREGADO

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
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['department_id'] = $user['department_id'];
        $_SESSION['group_id'] = $user['group_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['permissions'] = self::getUserPermissions($user['group_id']);
        $_SESSION['last_activity'] = time();
        
        // Actualizar último login
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
        $timeout = 3600; // 1 hora por defecto
        
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
            header('Location: /dms2/login.php');
            exit();
        }
    }
    
    public static function requireAdmin() {
        self::requireLogin();
        if ($_SESSION['role'] !== 'admin') {
            header('Location: /dms2/dashboard.php?error=access_denied');
            exit();
        }
    }
    
    // ✅ FUNCIÓN FALTANTE AGREGADA - requireRole()
    public static function requireRole($role) {
        self::requireLogin();
        if ($_SESSION['role'] !== $role) {
            header('Location: /dms2/dashboard.php?error=access_denied');
            exit();
        }
    }
    
    public static function getUserPermissions($groupId) {
        if (!$groupId) return [];
        
        try {
            require_once 'database.php';
            $query = "SELECT permissions FROM security_groups WHERE id = :id AND status = 'active'";
            $result = fetchOne($query, ['id' => $groupId]);
            
            if ($result && $result['permissions']) {
                return json_decode($result['permissions'], true);
            }
        } catch (Exception $e) {
            error_log("Error getting user permissions: " . $e->getMessage());
        }
        
        return [];
    }
    
    public static function hasPermission($permission) {
        self::startSession();
        
        if (!isset($_SESSION['permissions'])) {
            return false;
        }
        
        return isset($_SESSION['permissions'][$permission]) && 
               $_SESSION['permissions'][$permission] === true;
    }
    
    public static function canAccessCompany($companyId) {
        self::startSession();
        
        // Admin puede acceder a todas las empresas
        if ($_SESSION['role'] === 'admin') {
            return true;
        }
        
        // Usuario solo puede acceder a su empresa
        return $_SESSION['company_id'] == $companyId;
    }
    
    public static function canAccessDepartment($departmentId) {
        self::startSession();
        
        // Admin puede acceder a todos los departamentos
        if ($_SESSION['role'] === 'admin') {
            return true;
        }
        
        // Usuario solo puede acceder a su departamento
        return $_SESSION['department_id'] == $departmentId;
    }
    
    public static function getCurrentUser() {
        self::startSession();
        
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'first_name' => $_SESSION['first_name'],
            'last_name' => $_SESSION['last_name'],
            'company_id' => $_SESSION['company_id'],
            'department_id' => $_SESSION['department_id'],
            'group_id' => $_SESSION['group_id'],
            'role' => $_SESSION['role'],
            'permissions' => $_SESSION['permissions']
        ];
    }
    
    public static function getFullName() {
        self::startSession();
        return ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');
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
}

// Función auxiliar para verificar permisos
function checkPermission($permission) {
    return SessionManager::hasPermission($permission);
}

// Función auxiliar para obtener el usuario actual
function getCurrentUser() {
    return SessionManager::getCurrentUser();
}

// Función auxiliar para obtener el nombre completo
function getFullName() {
    return SessionManager::getFullName();
}
?>