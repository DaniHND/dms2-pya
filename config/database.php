<?php
// config/database.php
// Configuración de la base de datos para DMS2 - VERSIÓN FINAL SIN DUPLICADOS

// Incluir funciones globales
if (file_exists(dirname(__FILE__) . '/../includes/functions.php')) {
    require_once dirname(__FILE__) . '/../includes/functions.php';
}

class Database {
    private $host = 'localhost';
    private $db_name = 'dms2';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            error_log("Error de conexión a la base de datos: " . $exception->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
        
        return $this->conn;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
    
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }
}

// Variable global para la conexión
$GLOBALS['database'] = null;

// Función para obtener conexión singleton
function getDbConnection() {
    if (!isset($GLOBALS['database']) || $GLOBALS['database'] === null) {
        $GLOBALS['database'] = new Database();
    }
    return $GLOBALS['database']->getConnection();
}

// Funciones auxiliares para la base de datos
function executeQuery($query, $params = []) {
    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        error_log("Error en query: " . $e->getMessage() . " | Query: " . $query);
        return false;
    }
}

function fetchOne($query, $params = []) {
    try {
        $stmt = executeQuery($query, $params);
        if ($stmt) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch(Exception $e) {
        error_log("Error en fetchOne: " . $e->getMessage());
    }
    return false;
}

function fetchAll($query, $params = []) {
    try {
        $stmt = executeQuery($query, $params);
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(Exception $e) {
        error_log("Error en fetchAll: " . $e->getMessage());
    }
    return false;
}

function insertRecord($table, $data) {
    try {
        if (empty($data) || !is_array($data)) {
            throw new Exception("Datos inválidos para inserción");
        }
        
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
        
        $query = "INSERT INTO " . $table . " (" . implode(',', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = executeQuery($query, $data);
        return $stmt !== false;
        
    } catch(Exception $e) {
        error_log("Error en insertRecord: " . $e->getMessage());
        return false;
    }
}

function updateRecord($table, $data, $condition, $conditionParams = []) {
    try {
        if (empty($data) || !is_array($data)) {
            throw new Exception("Datos inválidos para actualización");
        }
        
        $setParts = [];
        foreach (array_keys($data) as $key) {
            $setParts[] = $key . " = :" . $key;
        }
        $setClause = implode(', ', $setParts);
        
        $query = "UPDATE " . $table . " SET " . $setClause . " WHERE " . $condition;
        
        $params = array_merge($data, $conditionParams);
        $stmt = executeQuery($query, $params);
        return $stmt !== false;
        
    } catch(Exception $e) {
        error_log("Error en updateRecord: " . $e->getMessage());
        return false;
    }
}

function deleteRecord($table, $condition, $params = []) {
    try {
        $query = "DELETE FROM " . $table . " WHERE " . $condition;
        $stmt = executeQuery($query, $params);
        return $stmt !== false;
        
    } catch(Exception $e) {
        error_log("Error en deleteRecord: " . $e->getMessage());
        return false;
    }
}

// Función mejorada para log de actividades
function logActivity($userId, $action, $tableName = null, $recordId = null, $description = null) {
    try {
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 500), // Limitar longitud
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return insertRecord('activity_logs', $data);
        
    } catch(Exception $e) {
        error_log("Error en logActivity: " . $e->getMessage());
        return false;
    }
}

// Función para obtener configuración del sistema
function getSystemSetting($key, $default = null) {
    try {
        $query = "SELECT setting_value, setting_type FROM system_settings WHERE setting_key = :key";
        $result = fetchOne($query, ['key' => $key]);
        
        if ($result && isset($result['setting_value'])) {
            $value = $result['setting_value'];
            $type = $result['setting_type'] ?? 'string';
            
            // Convertir según el tipo
            switch ($type) {
                case 'boolean':
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                case 'number':
                case 'integer':
                    return intval($value);
                case 'float':
                case 'decimal':
                    return floatval($value);
                case 'json':
                    return json_decode($value, true) ?: [];
                default:
                    return $value;
            }
        }
        
        return $default;
        
    } catch (Exception $e) {
        error_log("Error getting system setting '$key': " . $e->getMessage());
        return $default;
    }
}

// Función para verificar si una tabla existe
function tableExists($tableName) {
    try {
        $query = "SHOW TABLES LIKE :table";
        $result = fetchOne($query, ['table' => $tableName]);
        return $result !== false;
    } catch (Exception $e) {
        error_log("Error checking table existence: " . $e->getMessage());
        return false;
    }
}

// Función para verificar estructura de base de datos
function checkDatabaseStructure() {
    $requiredTables = [
        'users', 'companies', 'departments', 'documents', 
        'document_types', 'activity_logs', 'system_settings'
    ];
    
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        if (!tableExists($table)) {
            $missingTables[] = $table;
        }
    }
    
    return [
        'valid' => empty($missingTables),
        'missing_tables' => $missingTables
    ];
}

// Función para limpiar logs antiguos
function cleanOldLogs($days = 90) {
    try {
        $query = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = executeQuery($query, ['days' => $days]);
        
        if ($stmt) {
            return $stmt->rowCount();
        }
        
        return 0;
        
    } catch (Exception $e) {
        error_log("Error cleaning old logs: " . $e->getMessage());
        return 0;
    }
}

// Función para obtener estadísticas de la base de datos
function getDatabaseStats() {
    try {
        $stats = [];
        
        // Contar registros en tablas principales
        $tables = ['users', 'companies', 'documents', 'activity_logs'];
        
        foreach ($tables as $table) {
            if (tableExists($table)) {
                $result = fetchOne("SELECT COUNT(*) as count FROM " . $table);
                $stats[$table] = $result ? $result['count'] : 0;
            } else {
                $stats[$table] = 'N/A';
            }
        }
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error getting database stats: " . $e->getMessage());
        return [];
    }
}

// Verificar conexión al cargar el archivo
try {
    $database = new Database();
    if (!$database->testConnection()) {
        error_log("Warning: No se pudo establecer conexión con la base de datos al cargar database.php");
    }
} catch (Exception $e) {
    error_log("Error al verificar conexión: " . $e->getMessage());
}

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

/**
 * Función helper para obtener múltiples registros
 */
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

/**
 * Función para registrar actividades del sistema
 */
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
            $stmt->execute([
                $userId,
                $action,
                $tableName,
                $recordId,
                $description,
                $ipAddress,
                $userAgent
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log('Error logging activity: ' . $e->getMessage());
            return false;
        }
    }
}

?>