<?php
// debug_users_data.php
// Script temporal para debuggear por qué no aparecen los usuarios

require_once 'config/session.php';
require_once 'config/database.php';

echo "<h1>🔍 Debug de Usuarios - DMS2</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .debug-section { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .info { color: #17a2b8; }
    .warning { color: #ffc107; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
    th { background: #f8f9fa; }
    code { background: #f8f9fa; padding: 2px 4px; border-radius: 4px; font-family: monospace; }
</style>";

try {
    // Verificar conexión
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<div class='debug-section'>";
        echo "<h2>✅ 1. Conexión a Base de Datos</h2>";
        echo "<p class='success'>Conexión exitosa a la base de datos 'dms2'</p>";
        echo "</div>";
    } else {
        echo "<div class='debug-section'>";
        echo "<h2>❌ 1. Error de Conexión</h2>";
        echo "<p class='error'>No se pudo conectar a la base de datos</p>";
        echo "</div>";
        exit();
    }

    // Verificar si existen las tablas principales
    echo "<div class='debug-section'>";
    echo "<h2>📋 2. Verificar Tablas</h2>";
    
    $tables = ['users', 'companies', 'departments', 'activity_logs', 'documents'];
    foreach ($tables as $table) {
        $query = "SHOW TABLES LIKE '$table'";
        $result = fetchOne($query);
        if ($result) {
            echo "<p class='success'>✅ Tabla '$table' existe</p>";
        } else {
            echo "<p class='error'>❌ Tabla '$table' NO existe</p>";
        }
    }
    echo "</div>";

    // Contar registros en cada tabla
    echo "<div class='debug-section'>";
    echo "<h2>📊 3. Conteo de Registros</h2>";
    
    foreach ($tables as $table) {
        try {
            $query = "SELECT COUNT(*) as count FROM $table";
            $result = fetchOne($query);
            $count = $result ? $result['count'] : 0;
            echo "<p class='info'>📄 Tabla '$table': <strong>$count</strong> registros</p>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error contando '$table': " . $e->getMessage() . "</p>";
        }
    }
    echo "</div>";

    // Verificar usuarios específicamente
    echo "<div class='debug-section'>";
    echo "<h2>👥 4. Análisis de Usuarios</h2>";
    
    try {
        // Usuarios totales
        $totalUsers = fetchOne("SELECT COUNT(*) as count FROM users");
        echo "<p class='info'>👤 Total usuarios: <strong>" . ($totalUsers['count'] ?? 0) . "</strong></p>";
        
        // Usuarios por estado
        $statusQuery = fetchAll("SELECT status, COUNT(*) as count FROM users GROUP BY status");
        echo "<h3>Por Estado:</h3>";
        if ($statusQuery) {
            foreach ($statusQuery as $status) {
                echo "<p class='info'>• {$status['status']}: {$status['count']}</p>";
            }
        } else {
            echo "<p class='warning'>No se encontraron datos de estado</p>";
        }
        
        // Usuarios por rol
        $roleQuery = fetchAll("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        echo "<h3>Por Rol:</h3>";
        if ($roleQuery) {
            foreach ($roleQuery as $role) {
                echo "<p class='info'>• {$role['role']}: {$role['count']}</p>";
            }
        } else {
            echo "<p class='warning'>No se encontraron datos de rol</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error analizando usuarios: " . $e->getMessage() . "</p>";
    }
    echo "</div>";

    // Mostrar algunos usuarios de ejemplo
    echo "<div class='debug-section'>";
    echo "<h2>📝 5. Usuarios de Ejemplo</h2>";
    
    try {
        $sampleUsers = fetchAll("SELECT id, username, first_name, last_name, email, role, status, company_id, created_at FROM users LIMIT 10");
        
        if ($sampleUsers && count($sampleUsers) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Empresa</th><th>Actividades</th><th>Documentos</th><th>Descargas</th></tr>";
            foreach ($testResult as $user) {
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['username']}</td>";
                echo "<td>{$user['first_name']} {$user['last_name']}</td>";
                echo "<td>" . ($user['company_name'] ?? 'Sin empresa') . "</td>";
                echo "<td>{$user['activity_count']}</td>";
                echo "<td>{$user['documents_uploaded']}</td>";
                echo "<td>{$user['downloads_count']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>❌ La query no devolvió resultados</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error ejecutando query de prueba: " . $e->getMessage() . "</p>";
    }
    echo "</div>";

    // Verificar sesión actual
    echo "<div class='debug-section'>";
    echo "<h2>🔐 8. Verificar Sesión Actual</h2>";
    
    try {
        $currentUser = SessionManager::getCurrentUser();
        if ($currentUser) {
            echo "<p class='success'>✅ Usuario logueado correctamente</p>";
            echo "<table>";
            echo "<tr><th>Campo</th><th>Valor</th></tr>";
            foreach ($currentUser as $key => $value) {
                if ($key !== 'password') {
                    echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value) . "</td></tr>";
                }
            }
            echo "</table>";
        } else {
            echo "<p class='error'>❌ No hay usuario logueado</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error verificando sesión: " . $e->getMessage() . "</p>";
    }
    echo "</div>";

    // Verificar permisos de archivos
    echo "<div class='debug-section'>";
    echo "<h2>📂 9. Verificar Archivos y Permisos</h2>";
    
    $files = [
        'config/database.php',
        'config/session.php',
        'includes/functions.php',
        'modules/reports/user_reports.php'
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            if (is_readable($file)) {
                echo "<p class='success'>✅ $file - Existe y es legible</p>";
            } else {
                echo "<p class='warning'>⚠️ $file - Existe pero no es legible</p>";
            }
        } else {
            echo "<p class='error'>❌ $file - No existe</p>";
        }
    }
    echo "</div>";

    // Verificar funciones críticas
    echo "<div class='debug-section'>";
    echo "<h2>⚙️ 10. Verificar Funciones</h2>";
    
    $functions = ['fetchAll', 'fetchOne', 'password_hash', 'password_verify'];
    
    foreach ($functions as $func) {
        if (function_exists($func)) {
            echo "<p class='success'>✅ Función '$func' disponible</p>";
        } else {
            echo "<p class='error'>❌ Función '$func' NO disponible</p>";
        }
    }
    echo "</div>";

    // Diagnóstico final y recomendaciones
    echo "<div class='debug-section'>";
    echo "<h2>🎯 11. Diagnóstico y Recomendaciones</h2>";
    
    $userCount = 0;
    $companyCount = 0;
    
    try {
        $userResult = fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        $userCount = $userResult ? $userResult['count'] : 0;
        
        $companyResult = fetchOne("SELECT COUNT(*) as count FROM companies WHERE status = 'active'");
        $companyCount = $companyResult ? $companyResult['count'] : 0;
    } catch (Exception $e) {
        echo "<p class='error'>Error obteniendo conteos: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>📊 Estado del Sistema:</h3>";
    echo "<ul>";
    echo "<li><strong>Usuarios activos:</strong> $userCount</li>";
    echo "<li><strong>Empresas activas:</strong> $companyCount</li>";
    echo "</ul>";
    
    if ($userCount == 0) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545;'>";
        echo "<h3>🚨 PROBLEMA CRÍTICO: No hay usuarios activos</h3>";
        echo "<p><strong>Solución 1:</strong> Ejecutar el script de datos de ejemplo que te proporcioné anteriormente</p>";
        echo "<p><strong>Solución 2:</strong> Crear un usuario manualmente:</p>";
        echo "<code style='display: block; margin: 10px 0; padding: 10px; background: white;'>";
        echo "INSERT INTO users (first_name, last_name, username, email, password, role, status, company_id, created_at) VALUES<br>";
        echo "('Administrador', 'Sistema', 'admin', 'admin@dms2.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin', 'active', 1, NOW());";
        echo "</code>";
        echo "</div>";
    }
    
    if ($companyCount == 0) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;'>";
        echo "<h3>⚠️ ADVERTENCIA: No hay empresas activas</h3>";
        echo "<p><strong>Solución:</strong> Crear una empresa:</p>";
        echo "<code style='display: block; margin: 10px 0; padding: 10px; background: white;'>";
        echo "INSERT INTO companies (name, status, created_at) VALUES ('Perdomo y Asociados', 'active', NOW());";
        echo "</code>";
        echo "</div>";
    }
    
    if ($userCount > 0 && $companyCount > 0) {
        echo "<div style='background: #d1edff; padding: 15px; border-radius: 5px; border-left: 4px solid #0c5460;'>";
        echo "<h3>✅ SISTEMA APARENTEMENTE CORRECTO</h3>";
        echo "<p>Si aún no aparecen usuarios en el reporte, verifica:</p>";
        echo "<ol>";
        echo "<li>Que estés logueado como administrador</li>";
        echo "<li>Que no haya filtros aplicados que oculten los usuarios</li>";
        echo "<li>Revisa los logs de error de PHP</li>";
        echo "<li>Verifica que el archivo user_reports.php esté actualizado</li>";
        echo "</ol>";
        echo "</div>";
    }
    
    echo "</div>";

    // Enlaces útiles
    echo "<div class='debug-section'>";
    echo "<h2>🔗 12. Enlaces Útiles</h2>";
    echo "<p><a href='modules/reports/user_reports.php' style='color: #007bff;'>🔄 Ir al Reporte de Usuarios</a></p>";
    echo "<p><a href='login.php' style='color: #007bff;'>🔐 Ir al Login</a></p>";
    echo "<p><a href='dashboard.php' style='color: #007bff;'>🏠 Ir al Dashboard</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='debug-section'>";
    echo "<h2>💥 Error Crítico</h2>";
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p class='error'>Archivo: " . $e->getFile() . "</p>";
    echo "<p class='error'>Línea: " . $e->getLine() . "</p>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto;'>";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
    echo "</div>";
}

echo "<div style='margin: 20px 0; padding: 15px; background: #e9ecef; border-radius: 5px;'>";
echo "<p><strong>💡 Nota:</strong> Este archivo es solo para depuración. Elimínalo después de resolver el problema por seguridad.</p>";
echo "<p><strong>📅 Generado:</strong> " . date('d/m/Y H:i:s') . "</p>";
echo "</div>";
?></th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Empresa ID</th><th>Creado</th></tr>";
            foreach ($sampleUsers as $user) {
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td><strong>{$user['username']}</strong></td>";
                echo "<td>{$user['first_name']} {$user['last_name']}</td>";
                echo "<td>{$user['email']}</td>";
                echo "<td>{$user['role']}</td>";
                echo "<td>{$user['status']}</td>";
                echo "<td>{$user['company_id']}</td>";
                echo "<td>" . date('d/m/Y', strtotime($user['created_at'])) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>❌ No se encontraron usuarios en la tabla</p>";
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;'>";
            echo "<h3>🛠️ Solución: Crear Usuario de Prueba</h3>";
            echo "<p>Ejecuta este SQL para crear un usuario administrador:</p>";
            echo "<code>";
            echo "INSERT INTO users (first_name, last_name, username, email, password, role, status, company_id, created_at) VALUES<br>";
            echo "('Admin', 'Sistema', 'admin', 'admin@dms2.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin', 'active', 1, NOW());<br>";
            echo "</code>";
            echo "<p><strong>Credenciales:</strong> admin / admin123</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error obteniendo usuarios: " . $e->getMessage() . "</p>";
    }
    echo "</div>";

    // Verificar empresas
    echo "<div class='debug-section'>";
    echo "<h2>🏢 6. Verificar Empresas</h2>";
    
    try {
        $companies = fetchAll("SELECT id, name, status FROM companies LIMIT 5");
        
        if ($companies && count($companies) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Nombre</th><th>Estado</th></tr>";
            foreach ($companies as $company) {
                echo "<tr>";
                echo "<td>{$company['id']}</td>";
                echo "<td>{$company['name']}</td>";
                echo "<td>{$company['status']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>❌ No se encontraron empresas</p>";
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;'>";
            echo "<h3>🛠️ Solución: Crear Empresa de Prueba</h3>";
            echo "<code>INSERT INTO companies (name, status, created_at) VALUES ('Perdomo y Asociados', 'active', NOW());</code>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error obteniendo empresas: " . $e->getMessage() . "</p>";
    }
    echo "</div>";

    // Probar la query exacta del reporte
    echo "<div class='debug-section'>";
    echo "<h2>🔍 7. Probar Query del Reporte</h2>";
    
    try {
        $dateFrom = date('Y-m-d', strtotime('-30 days')) . ' 00:00:00';
        $dateTo = date('Y-m-d') . ' 23:59:59';
        
        $testQuery = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                             u.last_login, u.created_at, u.status, c.name as company_name,
                             (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                              AND al.created_at >= '$dateFrom' AND al.created_at <= '$dateTo') as activity_count,
                             (SELECT COUNT(*) FROM documents d WHERE d.uploaded_by = u.id 
                              AND d.created_at >= '$dateFrom' AND d.created_at <= '$dateTo') as documents_uploaded,
                             (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                              AND al.action = 'download' AND al.created_at >= '$dateFrom' AND al.created_at <= '$dateTo') as downloads_count
                      FROM users u
                      LEFT JOIN companies c ON u.company_id = c.id
                      WHERE u.status = 'active'
                      ORDER BY u.first_name, u.last_name
                      LIMIT 5";
        
        echo "<p class='info'><strong>Query de prueba:</strong></p>";
        echo "<code style='display: block; white-space: pre-wrap; background: #f8f9fa; padding: 10px; border-radius: 4px;'>" . htmlspecialchars($testQuery) . "</code>";
        
        $testResult = fetchAll($testQuery);
        
        if ($testResult && count($testResult) > 0) {
            echo "<p class='success'>✅ Query ejecutada exitosamente. Resultados: " . count($testResult) . "</p>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Usuario</th