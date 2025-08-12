<?php
/**
 * init.php - Archivo de inicialización del sistema
 * Incluye todas las funciones y configuraciones necesarias
 */

// Prevenir acceso directo
if (!defined('DMS_INIT')) {
    define('DMS_INIT', true);
}

// Configuración de errores para desarrollo
if (!defined('PRODUCTION')) {
    define('PRODUCTION', false);
}

if (!PRODUCTION) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Incluir archivos necesarios
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Incluir funciones de permisos si existe
if (file_exists(__DIR__ . '/permission_functions.php')) {
    require_once __DIR__ . '/permission_functions.php';
}

// Incluir funciones helper
if (file_exists(__DIR__ . '/helper_functions.php')) {
    require_once __DIR__ . '/helper_functions.php';
}

// Agregar funciones adicionales que pueden faltar aquí mismo

/**
 * Función para ejecutar consultas simples (fallback si no existe)
 */
if (!function_exists('executeQuery')) {
    function executeQuery($query, $params = []) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute($params);
            
            return $result;
        } catch (Exception $e) {
            error_log('Error in executeQuery: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Función para obtener el conteo de registros
 */
if (!function_exists('getCount')) {
    function getCount($table, $conditions = [], $params = []) {
        try {
            $query = "SELECT COUNT(*) as total FROM $table";
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }
            
            $result = fetchOne($query, $params);
            return $result ? (int)$result['total'] : 0;
        } catch (Exception $e) {
            error_log('Error in getCount: ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Función para logs de actividad mejorada
 */
if (!function_exists('logUserActivity')) {
    function logUserActivity($action, $description = null, $tableName = null, $recordId = null) {
        $userId = SessionManager::getUserId();
        return logActivity($userId, $action, $tableName, $recordId, $description);
    }
}

/**
 * Función para verificar si una tabla existe
 */
if (!function_exists('tableExists')) {
    function tableExists($tableName) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $query = "SHOW TABLES LIKE ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$tableName]);
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log('Error checking table existence: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Función para obtener estadísticas básicas del sistema
 */
if (!function_exists('getSystemStats')) {
    function getSystemStats() {
        try {
            $stats = [
                'users' => getCount('users', ['status = ?'], ['active']),
                'companies' => getCount('companies', ['status = ?'], ['active']),
                'departments' => getCount('departments', ['status = ?'], ['active']),
                'documents' => getCount('documents', ['status = ?'], ['active']),
                'document_folders' => getCount('document_folders', ['is_active = ?'], [1])
            ];
            
            return $stats;
        } catch (Exception $e) {
            error_log('Error getting system stats: ' . $e->getMessage());
            return [];
        }
    }
}

/**
 * Función para debug (solo en desarrollo)
 */
if (!function_exists('debugLog')) {
    function debugLog($message, $data = null) {
        if (!PRODUCTION) {
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] $message";
            
            if ($data !== null) {
                $logMessage .= " | Data: " . print_r($data, true);
            }
            
            error_log($logMessage);
        }
    }
}

/**
 * Función para obtener la URL base del sistema
 */
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['SCRIPT_NAME']);
        
        // Ajustar la ruta dependiendo de la profundidad
        $pathParts = explode('/', trim($path, '/'));
        $basePath = '';
        
        // Si estamos en un subdirectorio del proyecto, subir niveles
        if (count($pathParts) > 1) {
            $basePath = '/' . $pathParts[0];
        }
        
        return $protocol . '://' . $host . $basePath;
    }
}

/**
 * Función para redireccionar de forma segura
 */
if (!function_exists('safeRedirect')) {
    function safeRedirect($url, $statusCode = 302) {
        // Limpiar cualquier output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header("Location: $url", true, $statusCode);
        exit();
    }
}

/**
 * Función para mostrar mensajes de notificación
 */
if (!function_exists('showNotification')) {
    function showNotification($message, $type = 'info') {
        SessionManager::setFlashMessage($message, $type);
    }
}

/**
 * Función para verificar permisos con mensaje de error
 */
if (!function_exists('requirePermissionWithMessage')) {
    function requirePermissionWithMessage($permission, $customMessage = null) {
        if (!hasUserPermission($permission)) {
            $message = $customMessage ?: "No tienes permisos para realizar esta acción ($permission)";
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                exit;
            }
            
            showNotification($message, 'error');
            safeRedirect('../../dashboard.php');
        }
    }
}

// Inicializar sistema
debugLog('Sistema DMS2 inicializado');

?>