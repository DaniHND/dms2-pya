<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico DMS2 - Usuarios</h1>";

echo "<h2>1. Verificando archivos...</h2>";
$files = [
    '../../config/session.php',
    '../../config/database.php',
    '../../includes/functions.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe<br>";
    } else {
        echo "❌ $file NO existe<br>";
    }
}

echo "<h2>2. Cargando archivos...</h2>";
try {
    require_once '../../config/session.php';
    echo "✅ session.php cargado<br>";
    
    require_once '../../config/database.php';
    echo "✅ database.php cargado<br>";
    
    if (file_exists('../../includes/functions.php')) {
        require_once '../../includes/functions.php';
        echo "✅ functions.php cargado<br>";
    }
} catch (Exception $e) {
    echo "❌ Error cargando archivos: " . $e->getMessage() . "<br>";
}

echo "<h2>3. Verificando autenticación...</h2>";
try {
    if (class_exists('SessionManager')) {
        echo "✅ Clase SessionManager existe<br>";
        
        if (method_exists('SessionManager', 'isLoggedIn')) {
            if (SessionManager::isLoggedIn()) {
                echo "✅ Usuario logueado<br>";
                $currentUser = SessionManager::getCurrentUser();
                echo "Usuario: " . htmlspecialchars($currentUser['first_name'] ?? 'N/A') . " " . htmlspecialchars($currentUser['last_name'] ?? 'N/A') . "<br>";
                echo "Rol: " . htmlspecialchars($currentUser['role'] ?? 'N/A') . "<br>";
            } else {
                echo "❌ Usuario NO logueado<br>";
            }
        } else {
            echo "❌ Método isLoggedIn no existe<br>";
        }
    } else {
        echo "❌ Clase SessionManager no existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Error verificando autenticación: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Verificando base de datos...</h2>";
try {
    if (function_exists('fetchAll')) {
        echo "✅ Función fetchAll existe<br>";
        
        // Probar consulta simple
        $result = fetchAll("SHOW TABLES");
        if (is_array($result)) {
            echo "✅ Conexión a base de datos OK<br>";
            echo "Tablas encontradas: " . count($result) . "<br>";
            
            // Verificar tabla users
            $userTableExists = false;
            foreach ($result as $table) {
                $tableName = reset($table);
                if ($tableName === 'users') {
                    $userTableExists = true;
                    break;
                }
            }
            
            if ($userTableExists) {
                echo "✅ Tabla 'users' existe<br>";
                
                // Contar usuarios
                $userCount = fetchOne("SELECT COUNT(*) as count FROM users");
                echo "Total usuarios en DB: " . ($userCount['count'] ?? 'Error') . "<br>";
                
                // Usuarios activos
                $activeCount = fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
                echo "Usuarios activos: " . ($activeCount['count'] ?? 'Error') . "<br>";
                
                // Mostrar algunos usuarios
                $sampleUsers = fetchAll("SELECT id, username, first_name, last_name, status FROM users LIMIT 3");
                if (is_array($sampleUsers) && !empty($sampleUsers)) {
                    echo "✅ Muestra de usuarios:<br>";
                    echo "<pre>";
                    foreach ($sampleUsers as $user) {
                        echo "ID: " . $user['id'] . " | Usuario: " . $user['username'] . " | Nombre: " . ($user['first_name'] ?? '') . " " . ($user['last_name'] ?? '') . " | Estado: " . ($user['status'] ?? '') . "\n";
                    }
                    echo "</pre>";
                } else {
                    echo "❌ No se pudieron obtener usuarios de muestra<br>";
                }
                
            } else {
                echo "❌ Tabla 'users' NO existe<br>";
            }
            
        } else {
            echo "❌ Error en conexión a base de datos<br>";
        }
    } else {
        echo "❌ Función fetchAll no existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Error verificando base de datos: " . $e->getMessage() . "<br>";
}

echo "<h2>5. Probando consulta específica de user_reports...</h2>";
try {
    if (isset($currentUser)) {
        $whereClause = "WHERE u.status = 'active'";
        $params = [];
        
        if ($currentUser['role'] !== 'admin') {
            $whereClause .= " AND u.company_id = ? AND u.role != 'admin'";
            $params[] = $currentUser['company_id'];
        }
        
        $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, u.created_at,
                         COALESCE(c.name, 'Sin empresa') as company_name
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  $whereClause
                  ORDER BY u.first_name, u.last_name
                  LIMIT 5";
        
        echo "Consulta: " . $query . "<br>";
        echo "Parámetros: " . print_r($params, true) . "<br>";
        
        $result = fetchAll($query, $params);
        
        if (is_array($result)) {
            echo "✅ Consulta exitosa, " . count($result) . " usuarios obtenidos<br>";
            if (!empty($result)) {
                echo "<pre>";
                print_r($result[0]);
                echo "</pre>";
            }
        } else {
            echo "❌ Consulta falló o retornó false<br>";
        }
    } else {
        echo "❌ No hay usuario actual para probar<br>";
    }
} catch (Exception $e) {
    echo "❌ Error en consulta específica: " . $e->getMessage() . "<br>";
}

echo "<h2>6. Verificando tabla companies...</h2>";
try {
    $companiesExist = fetchOne("SELECT COUNT(*) as count FROM companies");
    echo "Empresas en DB: " . ($companiesExist['count'] ?? 'Error') . "<br>";
} catch (Exception $e) {
    echo "❌ Error verificando companies: " . $e->getMessage() . "<br>";
}
?>