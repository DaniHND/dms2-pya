<?php
/**
 * SessionManager - Sistema de sesiones reparado
 * Integración completa con el nuevo sistema de permisos
 */

class SessionManager {
    private static $sessionStarted = false;
    
    public static function startSession() {
        if (!self::$sessionStarted) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            self::$sessionStarted = true;
        }
    }
    
    public static function login($user) {
        self::startSession();
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['department_id'] = $user['department_id'];
        $_SESSION['download_enabled'] = $user['download_enabled'] ?? 1;
        $_SESSION['last_activity'] = time();
        
        // Actualizar última conexión en la base de datos
        try {
            require_once __DIR__ . '/database.php';
            $database = new Database();
            $pdo = $database->getConnection();
            
            $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([$user['id']]);
        } catch (Exception $e) {
            error_log('Error updating last login: ' . $e->getMessage());
        }
        
        return true;
    }
    
    public static function logout() {
        self::startSession();
        
        // Limpiar todas las variables de sesión
        $_SESSION = array();
        
        // Destruir la cookie de sesión si existe
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destruir la sesión
        session_destroy();
        self::$sessionStarted = false;
        
        return true;
    }
    
    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            // Si es una petición AJAX, devolver JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
                exit;
            }
            
            // Redireccionar al login
            header('Location: ' . self::getLoginUrl());
            exit;
        }
        
        // Verificar timeout de sesión
        self::checkSessionTimeout();
    }
    
    public static function checkSessionTimeout() {
        self::startSession();
        
        $timeout = 3600; // 1 hora por defecto
        
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > $timeout) {
            self::logout();
            
            // Si es AJAX, devolver JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Sesión expirada por inactividad']);
                exit;
            }
            
            header('Location: ' . self::getLoginUrl() . '?timeout=1');
            exit;
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    private static function getLoginUrl() {
        // Detectar la ruta relativa al login
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $pathParts = explode('/', $scriptPath);
        $depth = count($pathParts) - 2; // -1 for the file, -1 for root
        
        $relativePath = str_repeat('../', max(0, $depth - 1));
        return $relativePath . 'login.php';
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
            'role' => $_SESSION['role'],
            'company_id' => $_SESSION['company_id'],
            'department_id' => $_SESSION['department_id'],
            'download_enabled' => $_SESSION['download_enabled'] ?? 1,
            'full_name' => trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''))
        ];
    }
    
    public static function getUserId() {
        self::startSession();
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function getUserRole() {
        self::startSession();
        return $_SESSION['role'] ?? 'user';
    }
    
    public static function isAdmin() {
        return self::getUserRole() === 'admin';
    }
    
    public static function getFullUserName() {
        self::startSession();
        $firstName = $_SESSION['first_name'] ?? '';
        $lastName = $_SESSION['last_name'] ?? '';
        return trim($firstName . ' ' . $lastName);
    }
    
    // NUEVAS FUNCIONES INTEGRADAS CON EL SISTEMA DE PERMISOS
    
    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public static function hasUserPermission($permission) {
        // Cargar la función de permisos si existe
        if (function_exists('hasUserPermission')) {
            return hasUserPermission($permission);
        }
        
        // Fallback: los admins pueden todo
        if (self::isAdmin()) {
            return true;
        }
        
        // Para usuarios normales, solo permisos básicos
        $basicPermissions = ['view', 'view_reports', 'download'];
        return in_array($permission, $basicPermissions);
    }
    
    /**
     * Verificar acceso a empresa
     */
    public static function canAccessCompany($companyId) {
        // Admin puede acceder a todo
        if (self::isAdmin()) {
            return true;
        }
        
        // Cargar función de permisos si existe
        if (function_exists('canAccessCompany')) {
            return canAccessCompany($companyId);
        }
        
        // Fallback: solo su propia empresa
        self::startSession();
        return $_SESSION['company_id'] == $companyId;
    }
    
    /**
     * Verificar acceso a departamento
     */
    public static function canAccessDepartment($departmentId) {
        // Admin puede acceder a todo
        if (self::isAdmin()) {
            return true;
        }
        
        // Cargar función de permisos si existe
        if (function_exists('canAccessDepartment')) {
            return canAccessDepartment($departmentId);
        }
        
        // Fallback: verificar si el departamento pertenece a su empresa
        try {
            require_once __DIR__ . '/database.php';
            $database = new Database();
            $pdo = $database->getConnection();
            
            $query = "SELECT company_id FROM departments WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$departmentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return self::canAccessCompany($result['company_id']);
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Error checking department access: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener permisos completos del usuario actual
     */
    public static function getUserGroupPermissions() {
        if (function_exists('getUserPermissions')) {
            return getUserPermissions(self::getUserId());
        }
        
        // Fallback para admin
        if (self::isAdmin()) {
            return [
                'permissions' => [
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
                ],
                'restrictions' => [
                    'companies' => [],
                    'departments' => [],
                    'document_types' => []
                ]
            ];
        }
        
        // Fallback para usuario normal
        return [
            'permissions' => [
                'view' => true,
                'view_reports' => true,
                'download' => true,
                'export' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
                'delete_permanent' => false,
                'manage_users' => false,
                'system_config' => false
            ],
            'restrictions' => [
                'companies' => [self::getCurrentUser()['company_id']],
                'departments' => [],
                'document_types' => []
            ]
        ];
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
            'daily_limits' => $permissions['limits'] ?? []
        ]);
    }
    
    /**
     * Verificar si el usuario puede ver otros usuarios
     */
    public static function canViewUsers() {
        return self::hasUserPermission('manage_users');
    }
    
    /**
     * Verificar si el usuario puede gestionar configuración del sistema
     */
    public static function canManageSystem() {
        return self::hasUserPermission('system_config');
    }
    
    /**
     * Verificar si el usuario puede eliminar documentos permanentemente
     */
    public static function canDeletePermanently() {
        return self::hasUserPermission('delete_permanent');
    }
    
    /**
     * Generar token CSRF para formularios
     */
    public static function generateCSRFToken() {
        self::startSession();
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verificar token CSRF
     */
    public static function verifyCSRFToken($token) {
        self::startSession();
        
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Establecer mensaje flash
     */
    public static function setFlashMessage($message, $type = 'info') {
        self::startSession();
        $_SESSION['flash_message'] = [
            'message' => $message,
            'type' => $type
        ];
    }
    
    /**
     * Obtener y limpiar mensaje flash
     */
    public static function getFlashMessage() {
        self::startSession();
        
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        
        return null;
    }
    
    /**
     * Verificar si hay mensaje flash
     */
    public static function hasFlashMessage() {
        self::startSession();
        return isset($_SESSION['flash_message']);
    }
    
    /**
     * Requerir un rol específico para acceder
     */
    public static function requireRole($requiredRole) {
        self::requireLogin();
        
        $currentRole = self::getUserRole();
        
        // Admin puede acceder a todo
        if ($currentRole === 'admin') {
            return true;
        }
        
        // Verificar si el rol actual coincide con el requerido
        if ($currentRole !== $requiredRole) {
            // Si es una petición AJAX, devolver JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para acceder a esta sección']);
                exit;
            }
            
            // Redireccionar con mensaje de error
            self::setFlashMessage('No tienes permisos para acceder a esta sección', 'error');
            header('Location: ' . self::getAccessDeniedUrl());
            exit;
        }
        
        return true;
    }
    
    /**
     * Requerir permiso específico para acceder
     */
    public static function requirePermission($permission) {
        self::requireLogin();
        
        if (!self::hasUserPermission($permission)) {
            // Si es una petición AJAX, devolver JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción']);
                exit;
            }
            
            // Redireccionar con mensaje de error
            self::setFlashMessage('No tienes permisos para realizar esta acción', 'error');
            header('Location: ' . self::getAccessDeniedUrl());
            exit;
        }
        
        return true;
    }
    
    /**
     * Obtener URL de acceso denegado
     */
    private static function getAccessDeniedUrl() {
        // Detectar la ruta relativa al dashboard
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $pathParts = explode('/', $scriptPath);
        $depth = count($pathParts) - 2;
        
        $relativePath = str_repeat('../', max(0, $depth - 1));
        return $relativePath . 'dashboard.php?error=access_denied';
    }
}

?>