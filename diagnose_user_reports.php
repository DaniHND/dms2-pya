<?php
// diagnose_user_reports.php
// Script de diagnóstico específico para el problema de user_reports.php

// Incluir configuración básica
require_once 'config/database.php';
require_once 'config/session.php';

echo "<h1>Diagnóstico del Reporte de Usuarios</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .query-box { background: #f8f8f8; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style>";

// 1. Verificar sesión y usuario actual
echo "<div class='section'>";
echo "<h2>1. Verificando sesión y usuario actual</h2>";

try {
    // Verificar si hay sesión iniciada
    if (!isset($_SESSION)) {
        session_start();
    }
    
    if (isset($_SESSION['user_id'])) {
        echo "<span class='success'>✅ Sesión iniciada</span><br>";
        echo "<span class='info'>👤 ID de usuario: " . $_SESSION['user_id'] . "</span><br>";
        echo "<span class='info'>🔑 Rol: " . ($_SESSION['role'] ?? 'No definido') . "</span><br>";
        
        // Obtener información completa del usuario
        $currentUserId = $_SESSION['user_id'];
        $userQuery = "SELECT id, username, first_name, last_name, email, role, status, company_id FROM users WHERE id = ?";
        $currentUser = fetchOne($userQuery, [$currentUserId]);
        
        if ($currentUser) {
            echo "<span class='success'>✅ Usuario encontrado en base de datos</span><br>";
            echo "<span class='info'>📊 Estado: " . $currentUser['status'] . "</span><br>";
            echo "<span class='info'>🏢 Empresa ID: " . ($currentUser['company_id'] ?? 'No asignada') . "</span><br>";
        } else {
            echo "<span class='error'>❌ Usuario no encontrado en base de datos</span><br>";
        }
    } else {
        echo "<span class='warning'>⚠️ No hay sesión iniciada</span><br>";
        echo "<p>Inicia sesión antes de ejecutar este diagnóstico</p>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error verificando sesión: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 2. Verificar la función getUsersWithStats
echo "<div class='section'>";
echo "<h2>2. Verificando función getUsersWithStats</h2>";

// Parámetros de prueba (últimos 30 días)
$dateFrom = date('Y-m-d', strtotime('-30 days'));
$dateTo = date('Y-m-d');

echo "<span class='info'>📅 Fecha desde: $dateFrom</span><br>";
echo "<span class='info'>📅 Fecha hasta: $dateTo</span><br>";

if (isset($currentUser)) {
    echo "<span class='info'>👤 Rol del usuario: " . $currentUser['role'] . "</span><br><br>";
    
    // Simular la consulta que hace getUsersWithStats
    if ($currentUser['role'] === 'admin') {
        echo "<h3>Consulta para ADMINISTRADOR:</h3>";
        
        $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                         u.last_login, u.created_at, c.name as company_name,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.created_at >= ? AND al.created_at <= ?) as activity_count,
                         (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id 
                          AND d.created_at >= ? AND d.created_at <= ?) as documents_uploaded,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.action = 'download' AND al.created_at >= ? AND al.created_at <= ?) as downloads_count
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.status = 'active'";
        
        $params = [
            $dateFrom . ' 00:00:00',
            $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00',
            $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00',
            $dateTo . ' 23:59:59'
        ];
        
        try {
            $users = fetchAll($query, $params);
            $userCount = count($users);
            
            if ($userCount > 0) {
                echo "<span class='success'>✅ Encontrados $userCount usuarios</span><br>";
                
                echo "<h4>Usuarios encontrados:</h4>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Empresa</th><th>Actividades</th><th>Documentos</th><th>Descargas</th></tr>";
                
                foreach ($users as $user) {
                    echo "<tr>";
                    echo "<td>" . $user['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['company_name'] ?? 'Sin empresa') . "</td>";
                    echo "<td>" . $user['activity_count'] . "</td>";
                    echo "<td>" . $user['documents_uploaded'] . "</td>";
                    echo "<td>" . $user['downloads_count'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
            } else {
                echo "<span class='warning'>⚠️ No se encontraron usuarios activos</span><br>";
                
                // Verificar si hay usuarios inactivos
                $inactiveQuery = "SELECT COUNT(*) as count FROM users WHERE status != 'active'";
                $inactiveResult = fetchOne($inactiveQuery);
                
                if ($inactiveResult && $inactiveResult['count'] > 0) {
                    echo "<span class='warning'>⚠️ Hay " . $inactiveResult['count'] . " usuarios inactivos</span><br>";
                    echo "<div class='query-box'>";
                    echo "<h4>Activar usuarios:</h4>";
                    echo "<code>UPDATE users SET status = 'active' WHERE status != 'active';</code>";
                    echo "</div>";
                }
            }
            
        } catch (Exception $e) {
            echo "<span class='error'>❌ Error ejecutando consulta: " . htmlspecialchars($e->getMessage()) . "</span><br>";
            
            // Mostrar la consulta para debug
            echo "<div class='query-box'>";
            echo "<h4>Consulta que falló:</h4>";
            echo "<code>" . htmlspecialchars($query) . "</code>";
            echo "</div>";
        }
        
    } else {
        echo "<h3>Consulta para USUARIO NORMAL:</h3>";
        
        $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                         u.last_login, u.created_at, c.name as company_name,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.created_at >= ? AND al.created_at <= ?) as activity_count,
                         (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id 
                          AND d.created_at >= ? AND d.created_at <= ?) as documents_uploaded,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.action = 'download' AND al.created_at >= ? AND al.created_at <= ?) as downloads_count
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.id = ? AND u.status = 'active'";
        
        $params = [
            $dateFrom . ' 00:00:00',
            $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00',
            $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00',
            $dateTo . ' 23:59:59',
            $currentUser['id']
        ];
        
        try {
            $users = fetchAll($query, $params);
            $userCount = count($users);
            
            if ($userCount > 0) {
                echo "<span class='success'>✅ Usuario encontrado</span><br>";
                
                $user = $users[0];
                echo "<table>";
                echo "<tr><th>Campo</th><th>Valor</th></tr>";
                echo "<tr><td>ID</td><td>" . $user['id'] . "</td></tr>";
                echo "<tr><td>Usuario</td><td>" . htmlspecialchars($user['username']) . "</td></tr>";
                echo "<tr><td>Nombre</td><td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td></tr>";
                echo "<tr><td>Rol</td><td>" . htmlspecialchars($user['role']) . "</td></tr>";
                echo "<tr><td>Empresa</td><td>" . htmlspecialchars($user['company_name'] ?? 'Sin empresa') . "</td></tr>";
                echo "<tr><td>Actividades</td><td>" . $user['activity_count'] . "</td></tr>";
                echo "<tr><td>Documentos</td><td>" . $user['documents_uploaded'] . "</td></tr>";
                echo "<tr><td>Descargas</td><td>" . $user['downloads_count'] . "</td></tr>";
                echo "</table>";
                
            } else {
                echo "<span class='warning'>⚠️ No se encontró el usuario o está inactivo</span><br>";
            }
            
        } catch (Exception $e) {
            echo "<span class='error'>❌ Error ejecutando consulta: " . htmlspecialchars($e->getMessage()) . "</span><br>";
        }
    }
}
echo "</div>";

// 3. Verificar tablas relacionadas
echo "<div class='section'>";
echo "<h2>3. Verificando tablas relacionadas</h2>";

// Verificar tabla users
try {
    $totalUsers = fetchOne("SELECT COUNT(*) as total FROM users");
    echo "<span class='info'>👥 Total usuarios en sistema: " . ($totalUsers['total'] ?? 0) . "</span><br>";
    
    $activeUsers = fetchOne("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    echo "<span class='info'>✅ Usuarios activos: " . ($activeUsers['total'] ?? 0) . "</span><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error verificando tabla users: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

// Verificar tabla companies
try {
    $totalCompanies = fetchOne("SELECT COUNT(*) as total FROM companies");
    echo "<span class='info'>🏢 Total empresas: " . ($totalCompanies['total'] ?? 0) . "</span><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error verificando tabla companies: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

// Verificar tabla activity_logs
try {
    $totalLogs = fetchOne("SELECT COUNT(*) as total FROM activity_logs");
    echo "<span class='info'>📊 Total logs de actividad: " . ($totalLogs['total'] ?? 0) . "</span><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error verificando tabla activity_logs: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

// Verificar tabla documents
try {
    $totalDocs = fetchOne("SELECT COUNT(*) as total FROM documents");
    echo "<span class='info'>📄 Total documentos: " . ($totalDocs['total'] ?? 0) . "</span><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error verificando tabla documents: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "</div>";

// 4. Verificar archivos requeridos
echo "<div class='section'>";
echo "<h2>4. Verificando archivos requeridos</h2>";

$requiredFiles = [
    'config/session.php' => 'Manejo de sesiones',
    'config/database.php' => 'Conexión a base de datos',
    'modules/reports/user_reports.php' => 'Archivo principal del reporte'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file</span> - $description<br>";
    } else {
        echo "<span class='error'>❌ $file</span> - $description <strong>(FALTA)</strong><br>";
    }
}

echo "</div>";

// 5. Recomendaciones
echo "<div class='section'>";
echo "<h2>5. Recomendaciones de solución</h2>";

echo "<div class='query-box'>";
echo "<h3>🔧 Scripts de solución:</h3>";

echo "<h4>1. Si no hay usuarios activos:</h4>";
echo "<code>UPDATE users SET status = 'active' WHERE status != 'active';</code><br><br>";

echo "<h4>2. Si falta la columna download_enabled:</h4>";
echo "<code>ALTER TABLE users ADD COLUMN download_enabled BOOLEAN DEFAULT TRUE AFTER status;</code><br><br>";

echo "<h4>3. Crear usuario de prueba:</h4>";
echo "<code>INSERT INTO users (first_name, last_name, username, email, password, role, status, company_id, download_enabled, created_at) 
VALUES ('Test', 'Admin', 'testadmin', 'test@dms2.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin', 'active', 1, 1, NOW());</code><br><br>";

echo "<h4>4. Crear empresa de prueba si no existe:</h4>";
echo "<code>INSERT IGNORE INTO companies (name, status, created_at) VALUES ('Empresa Prueba', 'active', NOW());</code><br>";

echo "</div>";

echo "</div>";

// 6. Test directo de la función
echo "<div class='section'>";
echo "<h2>6. Test directo de la función getUsersWithStats</h2>";

if (isset($currentUser)) {
    echo "<h3>Simulando llamada a getUsersWithStats()...</h3>";
    
    // Copiar la lógica de la función directamente
    if ($currentUser['role'] === 'admin') {
        $testQuery = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                             u.last_login, u.created_at, c.name as company_name
                      FROM users u
                      LEFT JOIN companies c ON u.company_id = c.id
                      WHERE u.status = 'active'
                      ORDER BY u.created_at DESC";
        
        try {
            $testUsers = fetchAll($testQuery);
            echo "<span class='success'>✅ Consulta base exitosa: " . count($testUsers) . " usuarios</span><br>";
            
            if (count($testUsers) > 0) {
                echo "<h4>Usuarios encontrados (consulta simplificada):</h4>";
                echo "<ul>";
                foreach ($testUsers as $user) {
                    echo "<li>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . " (@" . htmlspecialchars($user['username']) . ") - " . htmlspecialchars($user['role']) . "</li>";
                }
                echo "</ul>";
            }
            
        } catch (Exception $e) {
            echo "<span class='error'>❌ Error en consulta base: " . htmlspecialchars($e->getMessage()) . "</span><br>";
        }
    } else {
        echo "<span class='info'>Usuario normal - debería ver solo sus propios datos</span><br>";
    }
}

echo "</div>";

?>