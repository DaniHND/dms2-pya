<?php
// includes/functions.php
// Funciones auxiliares globales para DMS2 - VERSIÓN COMPLETA

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

if (!function_exists('getFileExtension')) {
    function getFileExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
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

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $currency = 'HNL') {
        $formatted = formatNumber($amount, 2);
        return $currency . ' ' . $formatted;
    }
}

// ============================================================================
// FUNCIONES DE LOGGING Y DEBUG
// ============================================================================

if (!function_exists('debugLog')) {
    function debugLog($message, $data = null) {
        $log = '[DEBUG] ' . date('Y-m-d H:i:s') . ' - ' . $message;
        if ($data !== null) {
            $log .= ' - ' . print_r($data, true);
        }
        error_log($log);
    }
}

if (!function_exists('logError')) {
    function logError($message, $context = []) {
        $log = '[ERROR] ' . date('Y-m-d H:i:s') . ' - ' . $message;
        if (!empty($context)) {
            $log .= ' - Context: ' . json_encode($context);
        }
        error_log($log);
    }
}

// ============================================================================
// FUNCIONES DE UTILIDAD
// ============================================================================

if (!function_exists('getCurrentUrl')) {
    function getCurrentUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        return $protocol . '://' . $host . $uri;
    }
}

if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = $_SERVER['SCRIPT_NAME'];
        $base = dirname($script);
        
        if ($base === '/') {
            $base = '';
        }
        
        return $protocol . '://' . $host . $base;
    }
}

if (!function_exists('redirect')) {
    function redirect($url, $statusCode = 302) {
        header('Location: ' . $url, true, $statusCode);
        exit();
    }
}

if (!function_exists('generateSlug')) {
    function generateSlug($text) {
        // Convertir a minúsculas
        $text = strtolower($text);
        
        // Eliminar acentos
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        
        // Eliminar caracteres especiales
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        // Eliminar guiones del inicio y final
        $text = trim($text, '-');
        
        return $text;
    }
}

if (!function_exists('truncateText')) {
    function truncateText($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . $suffix;
    }
}

// ============================================================================
// FUNCIONES DE ARRAYS Y COLECCIONES
// ============================================================================

if (!function_exists('arrayGet')) {
    function arrayGet($array, $key, $default = null) {
        if (is_array($array) && array_key_exists($key, $array)) {
            return $array[$key];
        }
        return $default;
    }
}

if (!function_exists('arrayOnly')) {
    function arrayOnly($array, $keys) {
        return array_intersect_key($array, array_flip($keys));
    }
}

if (!function_exists('arrayExcept')) {
    function arrayExcept($array, $keys) {
        return array_diff_key($array, array_flip($keys));
    }
}

// ============================================================================
// FUNCIONES DE VALIDACIÓN AVANZADA
// ============================================================================

if (!function_exists('isValidUrl')) {
    function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

if (!function_exists('isValidDate')) {
    function isValidDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

if (!function_exists('isValidJson')) {
    function isValidJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
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

// ============================================================================
// FUNCIONES ESPECÍFICAS PARA EL SISTEMA DMS
// ============================================================================

if (!function_exists('getUserDisplayName')) {
    function getUserDisplayName($user) {
        if (is_array($user)) {
            $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            return $fullName ?: ($user['username'] ?? 'Usuario Desconocido');
        }
        return 'Usuario Desconocido';
    }
}

if (!function_exists('getDocumentStatusBadge')) {
    function getDocumentStatusBadge($status) {
        $badges = [
            'active' => '<span class="badge badge-success">Activo</span>',
            'inactive' => '<span class="badge badge-secondary">Inactivo</span>',
            'deleted' => '<span class="badge badge-danger">Eliminado</span>',
            'pending' => '<span class="badge badge-warning">Pendiente</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge badge-secondary">Desconocido</span>';
    }
}

if (!function_exists('getUserRoleBadge')) {
    function getUserRoleBadge($role) {
        $badges = [
            'admin' => '<span class="badge badge-danger">Administrador</span>',
            'manager' => '<span class="badge badge-primary">Gerente</span>',
            'user' => '<span class="badge badge-success">Usuario</span>',
            'viewer' => '<span class="badge badge-info">Visualizador</span>'
        ];
        
        return $badges[$role] ?? '<span class="badge badge-secondary">Desconocido</span>';
    }
}

?>