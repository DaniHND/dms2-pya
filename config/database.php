<?php
// config/database.php
// Configuración de la base de datos para DMS2 - CORREGIDO

class Database {
    private $host = 'localhost';
    private $db_name = 'dms2';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
}

// Funciones auxiliares para la base de datos
function executeQuery($query, $params = []) {
    $database = new Database();
    $conn = $database->getConnection();
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        error_log("Error en query: " . $e->getMessage());
        return false;
    }
}

function fetchOne($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
}

function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
}

function insertRecord($table, $data) {
    $columns = implode(',', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    
    $stmt = executeQuery($query, $data);
    return $stmt ? true : false;
}

function updateRecord($table, $data, $condition, $conditionParams = []) {
    $setParts = [];
    foreach (array_keys($data) as $key) {
        $setParts[] = "$key = :$key";
    }
    $setClause = implode(', ', $setParts);
    $query = "UPDATE $table SET $setClause WHERE $condition";
    
    $params = array_merge($data, $conditionParams);
    $stmt = executeQuery($query, $params);
    return $stmt ? true : false;
}

function deleteRecord($table, $condition, $params = []) {
    $query = "DELETE FROM $table WHERE $condition";
    $stmt = executeQuery($query, $params);
    return $stmt ? true : false;
}

// Función para log de actividades
function logActivity($userId, $action, $tableName = null, $recordId = null, $description = null) {
    $data = [
        'user_id' => $userId,
        'action' => $action,
        'table_name' => $tableName,
        'record_id' => $recordId,
        'description' => $description,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    return insertRecord('activity_logs', $data);
}

// FUNCIÓN ELIMINADA: getSystemConfig() - ya está definida en session.php

// Función para actualizar configuración del sistema
function updateSystemConfig($key, $value) {
    // Verificar si existe una función getSystemConfig en session.php
    if (function_exists('getSystemConfig')) {
        $existing = getSystemConfig($key);
    } else {
        $existing = null;
    }
    
    if ($existing) {
        return updateRecord('system_config', 
            ['config_value' => $value], 
            'config_key = :key', 
            ['key' => $key]
        );
    } else {
        return insertRecord('system_config', [
            'config_key' => $key,
            'config_value' => $value
        ]);
    }
}
?>