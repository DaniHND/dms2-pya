<?php
/**
 * helper_functions.php - Funciones helper para el sistema
 * Funciones comunes que se usan en varios módulos
 */

/**
 * Obtener nombre completo del usuario actual
 */
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

/**
 * Obtener nombre de usuario por ID
 */
if (!function_exists('getUserName')) {
    function getUserName($userId) {
        try {
            $query = "SELECT username FROM users WHERE id = ? AND status = 'active'";
            $user = fetchOne($query, [$userId]);
            
            return $user ? $user['username'] : 'usuario_desconocido';
        } catch (Exception $e) {
            error_log('Error in getUserName: ' . $e->getMessage());
            return 'usuario_desconocido';
        }
    }
}

/**
 * Obtener información básica de un usuario
 */
if (!function_exists('getUserInfo')) {
    function getUserInfo($userId) {
        try {
            $query = "
                SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role,
                       u.company_id, u.department_id, c.name as company_name, d.name as department_name
                FROM users u
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.id = ? AND u.status = 'active'
            ";
            
            return fetchOne($query, [$userId]);
        } catch (Exception $e) {
            error_log('Error in getUserInfo: ' . $e->getMessage());
            return null;
        }
    }
}

/**
 * Formatear fecha para mostrar
 */
if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y H:i') {
        if (!$date) return '-';
        
        try {
            if (is_string($date)) {
                $dateObj = new DateTime($date);
            } else {
                $dateObj = $date;
            }
            
            return $dateObj->format($format);
        } catch (Exception $e) {
            return $date; // Devolver la fecha original si hay error
        }
    }
}

/**
 * Formatear tamaño de archivo (alias de formatBytes)
 */
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));
        
        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }
}

/**
 * Sanitizar texto para mostrar
 */
if (!function_exists('sanitizeText')) {
    function sanitizeText($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Truncar texto
 */
if (!function_exists('truncateText')) {
    function truncateText($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . $suffix;
    }
}

/**
 * Generar token aleatorio
 */
if (!function_exists('generateToken')) {
    function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
}

/**
 * Verificar si una cadena es JSON válido
 */
if (!function_exists('isValidJson')) {
    function isValidJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}

/**
 * Obtener icono para tipo de archivo
 * NOTA: Usa getFileExtension() que está definida en functions.php
 */
if (!function_exists('getFileIcon')) {
    function getFileIcon($filename) {
        $extension = getFileExtension($filename); // Usa la función del functions.php
        
        $icons = [
            'pdf' => 'file-text',
            'doc' => 'file-text',
            'docx' => 'file-text',
            'txt' => 'file-text',
            'xls' => 'grid',
            'xlsx' => 'grid',
            'csv' => 'grid',
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'image',
            'gif' => 'image',
            'bmp' => 'image',
            'zip' => 'archive',
            'rar' => 'archive',
            '7z' => 'archive',
            'mp4' => 'video',
            'avi' => 'video',
            'mov' => 'video',
            'mp3' => 'music',
            'wav' => 'music',
        ];
        
        return $icons[$extension] ?? 'file';
    }
}

/**
 * Obtener color para tipo de archivo
 * NOTA: Usa getFileExtension() que está definida en functions.php
 */
if (!function_exists('getFileColor')) {
    function getFileColor($filename) {
        $extension = getFileExtension($filename); // Usa la función del functions.php
        
        $colors = [
            'pdf' => '#dc3545',
            'doc' => '#007bff',
            'docx' => '#007bff',
            'txt' => '#6c757d',
            'xls' => '#28a745',
            'xlsx' => '#28a745',
            'csv' => '#28a745',
            'jpg' => '#fd7e14',
            'jpeg' => '#fd7e14',
            'png' => '#fd7e14',
            'gif' => '#fd7e14',
            'zip' => '#6f42c1',
            'rar' => '#6f42c1',
            'mp4' => '#e83e8c',
            'mp3' => '#20c997',
        ];
        
        return $colors[$extension] ?? '#6c757d';
    }
}

/**
 * Verificar permisos del sistema
 */
if (!function_exists('getSystemConfig')) {
    function getSystemConfig($key, $default = null) {
        try {
            $query = "SELECT config_value FROM system_config WHERE config_key = ?";
            $result = fetchOne($query, [$key]);
            
            return $result ? $result['config_value'] : $default;
        } catch (Exception $e) {
            error_log('Error getting system config: ' . $e->getMessage());
            return $default;
        }
    }
}

/**
 * Verificar si el usuario puede realizar una acción específica
 */
if (!function_exists('canUserPerform')) {
    function canUserPerform($action, $userId = null) {
        // Usar el sistema de permisos corregido
        if (function_exists('hasUserPermission')) {
            return hasUserPermission($action, $userId);
        }
        
        // Si no hay sistema de permisos, usar fallback básico
        if ($userId === null) {
            return SessionManager::isAdmin();
        }
        
        $userInfo = getUserInfo($userId);
        return $userInfo && $userInfo['role'] === 'admin';
    }
}

?>