<?php
// debug_users.php
// Script para diagnosticar problemas en el módulo de usuarios - CORREGIDO

// NO verificar sesión ni permisos aquí para evitar redirecciones
require_once 'config/database.php';

echo "<h1>Diagnóstico del Módulo de Usuarios - DMS2</h1>";
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

// 1. Verificar conexión a la base de datos
echo "<div class='section'>";
echo "<h2>1. Verificando conexión a la base de datos</h2>";
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<span class='success'>✅ Conexión exitosa a la base de datos</span><br>";
        echo "<span class='info'>💾 Base de datos: dms2</span><br>";
        echo "<span class='info'>🔗 Host: localhost</span><br>";
    } else {
        echo "<span class='error'>❌ Error de conexión a la base de datos</span><br>";
        echo "<span class='warning'>⚠️ No se puede continuar con el diagnóstico</span><br>";
        exit();
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    exit();
}
echo "</div>";

// 2. Verificar estructura de tablas
echo "<div class='section'>";
echo "<h2>2. Verificando estructura de tablas</h2>";

$requiredTables = [
    'users' => 'Tabla principal de usuarios',
    'companies' => 'Tabla de empresas',
    'departments' => 'Tabla de departamentos', 
    'activity_logs' => 'Tabla de logs de actividad',
    'documents' => 'Tabla de documentos'
];

foreach ($requiredTables as $table => $description) {
    try {
        $query = "SHOW TABLES LIKE '$table'";
        $result = $conn->query($query);
        
        if ($result && $result->rowCount() > 0) {
            echo "<span class='success'>✅ $table</span> - $description<br>";
        } else {
            echo "<span class='error'>❌ $table</span> - $description <strong>(FALTA)</strong><br>";
        }
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error verificando tabla '$table': " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
}
echo "</div>";

// 3. Verificar estructura de la tabla users
echo "<div class='section'>";
echo "<h2>3. Estructura de la tabla 'users'</h2>";
try {
    $query = "DESCRIBE users";
    $result = fetchAll($query);
    
    if ($result) {
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        $requiredColumns = ['id', 'first_name', 'last_name', 'username', 'email', 'password', 'role', 'status', 'download_enabled'];
        $foundColumns = [];
        
        foreach ($result as $column) {
            $foundColumns[] = $column['Field'];
            $isRequired = in_array($column['Field'], $requiredColumns);
            $class = $isRequired ? 'success' : 'info';
            
            echo "<tr class='$class'>";
            echo "<td><strong>{$column['Field']}</strong></td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar columnas faltantes
        $missingColumns = array_diff($requiredColumns, $foundColumns);
        if (!empty($missingColumns)) {
            echo "<h3>❌ Columnas faltantes importantes:</h3>";
            foreach ($missingColumns as $missing) {
                echo "<span class='error'>• $missing</span><br>";
                if ($missing === 'download_enabled') {
                    echo "<div class='query-box'>";
                    echo "<strong>Solución:</strong><br>";
                    echo "<code>ALTER TABLE users ADD COLUMN download_enabled BOOLEAN DEFAULT TRUE AFTER status;</code>";
                    echo "</div>";
                }
            }
        } else {
            echo "<span class='success'>✅ Todas las columnas importantes están presentes</span>";
        }
    } else {
        echo "<span class='error'>❌ No se pudo obtener la estructura de la tabla 'users'</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 4. Verificar datos en la tabla users
echo "<div class='section'>";
echo "<h2>4. Datos en la tabla 'users'</h2>";
try {
    // Contar usuarios
    $totalUsers = fetchOne("SELECT COUNT(*) as total FROM users");
    $totalCount = $totalUsers['total'] ?? 0;
    echo "<span class='info'>📊 Total de usuarios: $totalCount</span><br>";
    
    if ($totalCount > 0) {
        // Usuarios por estado
        $statusQuery = fetchAll("SELECT status, COUNT(*) as count FROM users GROUP BY status");
        echo "<h3>👥 Usuarios por estado:</h3>";
        foreach ($statusQuery as $status) {
            echo "<span class='info'>• {$status['status']}: {$status['count']}</span><br>";
        }
        
        // Usuarios por rol
        $roleQuery = fetchAll("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        echo "<h3>🔐 Usuarios por rol:</h3>";
        foreach ($roleQuery as $role) {
            echo "<span class='info'>• {$role['role']}: {$role['count']}</span><br>";
        }
        
        // Mostrar algunos usuarios de ejemplo
        $sampleUsers = fetchAll("SELECT id, username, first_name, last_name, email, role, status, company_id FROM users LIMIT 5");
        
        if ($sampleUsers) {
            echo "<h3>👤 Usuarios de ejemplo:</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Empresa</th></tr>";
            foreach ($sampleUsers as $user) {
                $statusClass = $user['status'] === 'active' ? 'success' : 'warning';
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td><strong>{$user['username']}</strong></td>";
                echo "<td>{$user['first_name']} {$user['last_name']}</td>";
                echo "<td>{$user['email']}</td>";
                echo "<td><span class='info'>{$user['role']}</span></td>";
                echo "<td><span class='$statusClass'>{$user['status']}</span></td>";
                echo "<td>{$user['company_id']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<span class='warning'>⚠️ NO HAY USUARIOS EN LA BASE DE DATOS</span><br>";
        echo "<div class='query-box'>";
        echo "<h3>🛠️ Crear usuario administrador de prueba:</h3>";
        echo "<code>
INSERT INTO users (first_name, last_name, username, email, password, role, status, company_id, download_enabled, created_at) 
VALUES (
    'Admin', 
    'Sistema', 
    'admin', 
    'admin@dms2.com', 
    '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 
    'admin', 
    'active', 
    1, 
    1, 
    NOW()
);
        </code>";
        echo "<p><strong>Usuario:</strong> admin<br><strong>Contraseña:</strong> admin123</p>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error consultando usuarios: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 5. Verificar datos en la tabla companies
echo "<div class='section'>";
echo "<h2>5. Datos en la tabla 'companies'</h2>";
try {
    $totalCompanies = fetchOne("SELECT COUNT(*) as total FROM companies");
    $companyCount = $totalCompanies['total'] ?? 0;
    echo "<span class='info'>📊 Total de empresas: $companyCount</span><br>";
    
    if ($companyCount > 0) {
        $companies = fetchAll("SELECT id, name, status FROM companies LIMIT 10");
        
        if ($companies) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Nombre</th><th>Estado</th></tr>";
            foreach ($companies as $company) {
                $statusClass = $company['status'] === 'active' ? 'success' : 'warning';
                echo "<tr>";
                echo "<td>{$company['id']}</td>";
                echo "<td><strong>{$company['name']}</strong></td>";
                echo "<td><span class='$statusClass'>{$company['status']}</span></td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<span class='warning'>⚠️ NO HAY EMPRESAS EN LA BASE DE DATOS</span><br>";
        echo "<div class='query-box'>";
        echo "<h3>🛠️ Crear empresa de prueba:</h3>";
        echo "<code>INSERT INTO companies (name, status, created_at) VALUES ('Empresa Demo', 'active', NOW());</code>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error consultando empresas: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 6. Probar las consultas del módulo de usuarios
echo "<div class='section'>";
echo "<h2>6. Probando consultas del módulo de usuarios</h2>";

try {
    // Consulta simplificada primero
    echo "<h3>🧪 Prueba básica:</h3>";
    $basicQuery = "SELECT COUNT(*) as total FROM users WHERE status != 'deleted'";
    $basicResult = fetchOne($basicQuery);
    echo "<span class='info'>Usuarios activos/inactivos: " . ($basicResult['total'] ?? 0) . "</span><br>";
    
    // Consulta más compleja
    echo "<h3>🧪 Prueba con JOIN:</h3>";
    $complexQuery = "SELECT u.id, u.username, u.first_name, u.last_name, c.name as company_name 
                     FROM users u 
                     LEFT JOIN companies c ON u.company_id = c.id 
                     WHERE u.status != 'deleted' 
                     LIMIT 3";
    
    $complexResult = fetchAll($complexQuery);
    
    if ($complexResult && count($complexResult) > 0) {
        echo "<span class='success'>✅ Consulta con JOIN funciona correctamente</span><br>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Empresa</th></tr>";
        foreach ($complexResult as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['first_name']} {$user['last_name']}</td>";
            echo "<td>" . ($user['company_name'] ?? 'Sin empresa') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<span class='warning'>⚠️ La consulta no devolvió resultados</span><br>";
    }
    
    // Probar la consulta problemática del módulo
    echo "<h3>🧪 Prueba consulta completa del módulo:</h3>";
    $fullQuery = "SELECT u.*, c.name as company_name,
                  COALESCE((SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id), 0) as document_count,
                  COALESCE((SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id), 0) as activity_count
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.status != 'deleted'
                  ORDER BY u.created_at DESC
                  LIMIT 3";
    
    $fullResult = fetchAll($fullQuery);
    
    if ($fullResult && count($fullResult) > 0) {
        echo "<span class='success'>✅ Consulta completa funciona correctamente</span><br>";
        echo "<span class='info'>Usuarios obtenidos: " . count($fullResult) . "</span><br>";
    } else {
        echo "<span class='error'>❌ La consulta completa falló</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error en las pruebas: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 7. Verificar archivos del módulo
echo "<div class='section'>";
echo "<h2>7. Verificando archivos del módulo</h2>";

$requiredFiles = [
    'modules/users/index.php' => 'Página principal del módulo',
    'modules/users/actions/create_user.php' => 'Crear usuario',
    'modules/users/actions/get_user.php' => 'Obtener usuario',
    'modules/users/actions/update_user.php' => 'Actualizar usuario',
    'modules/users/actions/delete_user.php' => 'Eliminar usuario',
    'modules/users/actions/toggle_user_status.php' => 'Cambiar estado',
    'modules/users/actions/get_user_details.php' => 'Detalles del usuario',
    'assets/js/users.js' => 'JavaScript del módulo',
    'assets/css/users.css' => 'CSS del módulo',
    'config/session.php' => 'Manejo de sesiones',
    'config/database.php' => 'Conexión a base de datos'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file</span> - $description<br>";
    } else {
        echo "<span class='error'>❌ $file</span> - $description <strong>(FALTA)</strong><br>";
    }
}
echo "</div>";

// 8. Verificar permisos de directorios
echo "<div class='section'>";
echo "<h2>8. Verificando permisos de directorios</h2>";

$directories = [
    'modules/users/actions/',
    'assets/js/',
    'assets/css/',
    'config/'
];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (is_readable($dir)) {
            echo "<span class='success'>✅ $dir</span> - Legible<br>";
        } else {
            echo "<span class='error'>❌ $dir</span> - No legible<br>";
        }
    } else {
        echo "<span class='error'>❌ $dir</span> - No existe<br>";
    }
}
echo "</div>";

// 9. Resumen y recomendaciones
echo "<div class='section'>";
echo "<h2>9. 🎯 Resumen y Recomendaciones</h2>";

echo "<h3>✅ Pasos para solucionar el problema:</h3>";
echo "<ol>";

if ($totalCount == 0) {
    echo "<li><strong style='color: red;'>CRÍTICO:</strong> Crear al menos un usuario administrador usando el SQL proporcionado arriba</li>";
}

if ($companyCount == 0) {
    echo "<li><strong style='color: red;'>CRÍTICO:</strong> Crear al menos una empresa usando el SQL proporcionado arriba</li>";
}

echo "<li><strong>Reemplazar archivos:</strong> Usar las versiones corregidas de <code>index.php</code> y <code>users.js</code></li>";
echo "<li><strong>Verificar permisos:</strong> Asegurarse de estar logueado como administrador</li>";
echo "<li><strong>Probar el módulo:</strong> Ir a <code>modules/users/index.php</code> después de los cambios</li>";
echo "</ol>";

echo "<h3>🔧 Si sigues teniendo problemas:</h3>";
echo "<ul>";
echo "<li>Verificar logs de errores de PHP</li>";
echo "<li>Revisar la consola del navegador para errores JavaScript</li>";
echo "<li>Asegurarse de que la sesión esté activa</li>";
echo "<li>Verificar que la función <code>requireRole('admin')</code> existe en SessionManager</li>";
echo "</ul>";

echo "<div class='query-box'>";
echo "<h3>🚀 Comandos SQL completos para empezar:</h3>";
echo "<code>
-- Crear empresa
INSERT INTO companies (name, status, created_at) VALUES ('Empresa Demo', 'active', NOW());

-- Crear usuario admin
INSERT INTO users (first_name, last_name, username, email, password, role, status, company_id, download_enabled, created_at) 
VALUES ('Admin', 'Sistema', 'admin', 'admin@dms2.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin', 'active', 1, 1, NOW());

-- Verificar que se crearon correctamente
SELECT * FROM users;
SELECT * FROM companies;
</code>";
echo "</div>";

echo "</div>";

echo "<h2>🏁 Diagnóstico Completado</h2>";
echo "<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Siguiente paso:</strong> Revisar los puntos marcados en rojo y aplicar las correcciones sugeridas.</p>";
?>