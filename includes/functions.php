<?php
// includes/functions.php
// Funciones auxiliares globales para DMS2 - VERSIÓN SIMPLIFICADA

// Evitar múltiples inclusiones
if (defined('DMS2_FUNCTIONS_LOADED')) {
    return;
}
define('DMS2_FUNCTIONS_LOADED', true);

// ============================================================================
// FUNCIÓN PRINCIPAL - FORMATEAR BYTES
// ============================================================================

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

// ============================================================================
// FUNCIONES DE VALIDACIÓN
// ============================================================================

if (!function_exists('isValidEmail')) {
    function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('isValidPassword')) {
    function isValidPassword($password, $minLength = 6) {
        return strlen($password) >= $minLength;
    }
}

if (!function_exists('isAllowedFileType')) {
    function isAllowedFileType($filename, $allowedTypes = null) {
        if ($allowedTypes === null) {
            $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip'];
        }
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowedTypes);
    }
}

// ============================================================================
// FUNCIONES DE SEGURIDAD
// ============================================================================

if (!function_exists('escapeHtml')) {
    function escapeHtml($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitizeString')) {
    function sanitizeString($string) {
        return trim(strip_tags($string));
    }
}

if (!function_exists('generateSecureToken')) {
    function generateSecureToken($length = 32) {
        try {
            return bin2hex(random_bytes($length / 2));
        } catch (Exception $e) {
            // Fallback si random_bytes falla
            return substr(md5(uniqid(mt_rand(), true)), 0, $length);
        }
    }
}

if (!function_exists('generateUniqueFilename')) {
    function generateUniqueFilename($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return uniqid() . '_' . time() . '.' . $extension;
    }
}

// ============================================================================
// FUNCIONES DE ARCHIVOS
// ============================================================================

if (!function_exists('getFileIcon')) {
    function getFileIcon($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $iconMap = [
            'pdf' => 'fa-file-pdf',
            'doc' => 'fa-file-word',
            'docx' => 'fa-file-word',
            'xls' => 'fa-file-excel',
            'xlsx' => 'fa-file-excel',
            'ppt' => 'fa-file-powerpoint',
            'pptx' => 'fa-file-powerpoint',
            'jpg' => 'fa-file-image',
            'jpeg' => 'fa-file-image',
            'png' => 'fa-file-image',
            'gif' => 'fa-file-image',
            'zip' => 'fa-file-archive',
            'rar' => 'fa-file-archive',
            'txt' => 'fa-file-alt',
            'csv' => 'fa-file-csv'
        ];
        
        return isset($iconMap[$extension]) ? $iconMap[$extension] : 'fa-file';
    }
}

if (!function_exists('getMimeType')) {
    function getMimeType($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mimeMap = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip'
        ];
        
        return isset($mimeMap[$extension]) ? $mimeMap[$extension] : 'application/octet-stream';
    }
}

// ============================================================================
// FUNCIONES DE FORMATEO
// ============================================================================

if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y H:i') {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        
        try {
            return date($format, strtotime($date));
        } catch (Exception $e) {
            return $date; // Devolver la fecha original si hay error
        }
    }
}

if (!function_exists('formatNumber')) {
    function formatNumber($number, $decimals = 0) {
        if (!is_numeric($number)) {
            return '0';
        }
        return number_format($number, $decimals, '.', ',');
    }
}

// ============================================================================
// FUNCIONES DE TEXTO
// ============================================================================

if (!function_exists('truncateText')) {
    function truncateText($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . $suffix;
    }
}

if (!function_exists('slugify')) {
    function slugify($text) {
        // Convertir a minúsculas y reemplazar caracteres especiales
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }
}

// ============================================================================
// FUNCIONES DE TIEMPO SIMPLIFICADAS
// ============================================================================

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        if (empty($datetime)) {
            return 'nunca';
        }
        
        try {
            $time = strtotime($datetime);
            if ($time === false) {
                return 'fecha inválida';
            }
            
            $diff = time() - $time;
            
            if ($diff < 60) {
                return 'hace un momento';
            } elseif ($diff < 3600) {
                $minutes = floor($diff / 60);
                return "hace $minutes minuto" . ($minutes > 1 ? 's' : '');
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                return "hace $hours hora" . ($hours > 1 ? 's' : '');
            } elseif ($diff < 2592000) {
                $days = floor($diff / 86400);
                return "hace $days día" . ($days > 1 ? 's' : '');
            } else {
                return formatDate($datetime, 'd/m/Y');
            }
        } catch (Exception $e) {
            return 'fecha inválida';
        }
    }
}

// ============================================================================
// FUNCIONES DE ARRAYS
// ============================================================================

if (!function_exists('arrayGet')) {
    function arrayGet($array, $key, $default = null) {
        return isset($array[$key]) ? $array[$key] : $default;
    }
}

if (!function_exists('arrayHas')) {
    function arrayHas($array, $key) {
        return isset($array[$key]);
    }
}

// ============================================================================
// FUNCIONES DE URL
// ============================================================================

if (!function_exists('baseUrl')) {
    function baseUrl($path = '') {
        try {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            
            // Detectar subdirectorio DMS2
            $baseDir = '';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($requestUri, '/dms2/') !== false) {
                $baseDir = '/dms2';
            }
            
            $url = $protocol . '://' . $host . $baseDir;
            
            if (!empty($path)) {
                $url .= '/' . ltrim($path, '/');
            }
            
            return $url;
        } catch (Exception $e) {
            return 'http://localhost/dms2';
        }
    }
}

if (!function_exists('currentUrl')) {
    function currentUrl() {
        try {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            
            return $protocol . '://' . $host . $uri;
        } catch (Exception $e) {
            return '';
        }
    }
}

// ============================================================================
// FUNCIONES DE RESPUESTA JSON
// ============================================================================

if (!function_exists('jsonResponse')) {
    function jsonResponse($success, $message = '', $data = null, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=UTF-8');
        
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

if (!function_exists('jsonSuccess')) {
    function jsonSuccess($message = '', $data = null) {
        jsonResponse(true, $message, $data);
    }
}

if (!function_exists('jsonError')) {
    function jsonError($message = '', $httpCode = 400) {
        jsonResponse(false, $message, null, $httpCode);
    }
}

// ============================================================================
// FUNCIONES DE DEBUG
// ============================================================================

if (!function_exists('logDebug')) {
    function logDebug($message, $data = null) {
        $log = date('Y-m-d H:i:s') . ' - ' . $message;
        if ($data !== null) {
            $log .= ' - ' . print_r($data, true);
        }
        error_log($log);
    }
}

// ============================================================================
// CONSTANTES ÚTILES
// ============================================================================

if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 31457280); // 30MB
}

if (!defined('ALLOWED_EXTENSIONS')) {
    define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'csv', 'zip']);
}

?>