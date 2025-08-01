<?php
// test_groups_connection.php
// Diagnóstico completo para el módulo de grupos

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; }
    .section { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: #22c55e; font-weight: bold; }
    .error { color: #ef4444; font-weight: bold; }
    .warning { color: #f59e0b; font-weight: bold; }
    .info { color: #3b82f6; font-weight: bold; }
    .test-result { padding: 10px; margin: 5px 0; border-left: 4px solid #ddd; }
    .test-success { border-color: #22c55e; background: #f0fdf4; }
    .test-error { border-color: #ef4444; background: #fef2f2; }
    .test-warning { border-color: #f59e0b; background: #fffbeb; }
    code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
    th { background: #f8f9fa; }
    .step { margin: 15px 0; }
    .credentials { background: #e0f2fe; padding: 15px; border-radius: 5px; margin: 10px 0; }
</style>";

echo "<div class='container'>";
echo "<h1>🔧 Diagnóstico Completo - Módulo de Grupos</h1>";
echo "<p><em>Ejecutado: " . date('Y-m-d H:i:s') . "</em></p>";

// 1. Verificar estructura de archivos
echo "<div class='section'>";
echo "<h2>1. 📁 Verificando Estructura de Archivos</h2>";

$requiredFiles = [
    'config/session.php' => 'Manejo de sesiones',
    'config/database.php' => 'Conexión a BD',
    'includes/functions.php' => 'Funciones auxiliares',
    'includes/sidebar.php' => 'Sidebar de navegación',
    'modules/groups/index.php' => 'Página principal grupos',
    'assets/css/main.css' => 'CSS principal',
    'assets/css/dashboard.css' => 'CSS dashboard',
    'assets/css/groups.css' => 'CSS grupos',
    'assets/js/groups.js' => 'JavaScript grupos'
];

$filesOk = 0;
foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='test-result test-success'>✅ <strong>$file</strong> - $description</div>";
        $filesOk++;
    } else {
        echo "<div class='test-result test-error'>❌ <strong>$file</strong> - $description (FALTA)</div>";
    }
}

$filesPercentage = round(($filesOk / count($requiredFiles)) * 100);
echo "<p><strong>Archivos disponibles: {$filesOk}/" . count($requiredFiles) . " ({$filesPercentage}%)</strong></p>";
echo "</div>";

// 2. Test de conexión a base de datos
echo "<div class='section'>";
echo "<h2>2. 🗄️ Test de Conexión a Base de Datos</h2>";

try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        echo "<div class='test-result test-success'>✅ Archivo database.php cargado</div>";
        
        // Test de conexión
        $conn = getDbConnection();
        if ($conn) {
            echo "<div class='test-result test-success'>✅ Conexión a BD establecida</div>";
            
            // Verificar tablas necesarias
            $requiredTables = [
                'users' => 'Usuarios',
                'user_groups' => 'Grupos de usuarios',
                'user_group_members' => 'Miembros de grupos',
                'companies' => 'Empresas',
                'departments' => 'Departamentos'
            ];
            
            foreach ($requiredTables as $table => $desc) {
                $query = "SHOW TABLES LIKE '$table'";
                $result = $conn->query($query);
                if ($result && $result->rowCount() > 0) {
                    echo "<div class='test-result test-success'>✅ Tabla <code>$table</code> existe - $desc</div>";
                } else {
                    echo "<div class='test-result test-error'>❌ Tabla <code>$table</code> NO existe - $desc</div>";
                }
            }
            
        } else {
            echo "<div class='test-result test-error'>❌ No se pudo establecer conexión a BD</div>";
        }
    } else {
        echo "<div class='test-result test-error'>❌ Archivo config/database.php no encontrado</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result test-error'>❌ Error de BD: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo "</div>";

// 3. Test de sesiones
echo "<div class='section'>";
echo "<h2>3. 🔐 Test de Sistema de Sesiones</h2>";

try {
    if (file_exists('config/session.php')) {
        require_once 'config/session.php';
        echo "<div class='test-result test-success'>✅ Archivo session.php cargado</div>";
        
        // Verificar clase SessionManager
        if (class_exists('SessionManager')) {
            echo "<div class='test-result test-success'>✅ Clase SessionManager disponible</div>";
            
            // Test de métodos
            $methods = ['startSession', 'isLoggedIn', 'getCurrentUser', 'login', 'logout'];
            foreach ($methods as $method) {
                if (method_exists('SessionManager', $method)) {
                    echo "<div class='test-result test-success'>✅ Método <code>SessionManager::$method()</code> disponible</div>";
                } else {
                    echo "<div class='test-result test-error'>❌ Método <code>SessionManager::$method()</code> NO disponible</div>";
                }
            }
            
            // Test de sesión actual
            if (SessionManager::isLoggedIn()) {
                $currentUser = SessionManager::getCurrentUser();
                echo "<div class='test-result test-success'>✅ Usuario logueado: " . htmlspecialchars($currentUser['username'] ?? 'Sin nombre') . "</div>";
                echo "<div class='test-result test-info'>ℹ️ Rol: " . htmlspecialchars($currentUser['role'] ?? 'Sin rol') . "</div>";
            } else {
                echo "<div class='test-result test-warning'>⚠️ No hay usuario logueado (normal si no has iniciado sesión)</div>";
            }
            
        } else {
            echo "<div class='test-result test-error'>❌ Clase SessionManager NO disponible</div>";
        }
    } else {
        echo "<div class='test-result test-error'>❌ Archivo config/session.php no encontrado</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result test-error'>❌ Error de sesión: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo "</div>";

// 4. Test de funciones auxiliares
echo "<div class='section'>";
echo "<h2>4. 🛠️ Test de Funciones Auxiliares</h2>";

try {
    if (file_exists('includes/functions.php')) {
        require_once 'includes/functions.php';
        echo "<div class='test-result test-success'>✅ Archivo functions.php cargado</div>";
        
        // Test de funciones importantes
        $functions = ['formatBytes', 'isValidEmail', 'escapeHtml', 'logActivity'];
        foreach ($functions as $func) {
            if (function_exists($func)) {
                echo "<div class='test-result test-success'>✅ Función <code>$func()</code> disponible</div>";
            } else {
                echo "<div class='test-result test-warning'>⚠️ Función <code>$func()</code> NO disponible</div>";
            }
        }
    } else {
        echo "<div class='test-result test-error'>❌ Archivo includes/functions.php no encontrado</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result test-error'>❌ Error cargando funciones: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo "</div>";

// 5. Test de creación de directorio de acciones
echo "<div class='section'>";
echo "<h2>5. 📂 Test de Directorios de Acciones</h2>";

$actionDir = 'modules/groups/actions';
if (!is_dir($actionDir)) {
    if (mkdir($actionDir, 0755, true)) {
        echo "<div class='test-result test-success'>✅ Directorio <code>$actionDir</code> creado exitosamente</div>";
    } else {
        echo "<div class='test-result test-error'>❌ No se pudo crear directorio <code>$actionDir</code></div>";
    }
} else {
    echo "<div class='test-result test-success'>✅ Directorio <code>$actionDir</code> ya existe</div>";
}

// Verificar permisos de escritura
if (is_writable($actionDir)) {
    echo "<div class='test-result test-success'>✅ Directorio <code>$actionDir</code> tiene permisos de escritura</div>";
} else {
    echo "<div class='test-result test-error'>❌ Directorio <code>$actionDir</code> NO tiene permisos de escritura</div>";
}
echo "</div>";

// 6. Test de creación de tabla user_groups si no existe
echo "<div class='section'>";
echo "<h2>6. 🗃️ Test/Creación de Tablas de Grupos</h2>";

try {
    if (isset($conn)) {
        // Verificar si existe la tabla user_groups
        $checkTable = $conn->query("SHOW TABLES LIKE 'user_groups'");
        if ($checkTable && $checkTable->rowCount() > 0) {
            echo "<div class='test-result test-success'>✅ Tabla user_groups existe</div>";
            
            // Mostrar estructura
            $columns = $conn->query("DESCRIBE user_groups")->fetchAll();
            echo "<h4>Estructura de user_groups:</h4>";
            echo "<table><tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th></tr>";
            foreach ($columns as $col) {
                echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
            }
            echo "</table>";
            
        } else {
            echo "<div class='test-result test-warning'>⚠️ Tabla user_groups NO existe - Intentando crear...</div>";
            
            $createTableSQL = "
                CREATE TABLE user_groups (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    description TEXT,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    module_permissions JSON,
                    access_restrictions JSON,
                    download_limit_daily INT NULL,
                    upload_limit_daily INT NULL,
                    is_system_group BOOLEAN DEFAULT FALSE,
                    created_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_status (status),
                    INDEX idx_system_group (is_system_group),
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            if ($conn->exec($createTableSQL)) {
                echo "<div class='test-result test-success'>✅ Tabla user_groups creada exitosamente</div>";
            } else {
                echo "<div class='test-result test-error'>❌ Error creando tabla user_groups</div>";
            }
        }
        
        // Verificar tabla user_group_members
        $checkMembersTable = $conn->query("SHOW TABLES LIKE 'user_group_members'");
        if ($checkMembersTable && $checkMembersTable->rowCount() > 0) {
            echo "<div class='test-result test-success'>✅ Tabla user_group_members existe</div>";
        } else {
            echo "<div class='test-result test-warning'>⚠️ Tabla user_group_members NO existe - Intentando crear...</div>";
            
            $createMembersSQL = "
                CREATE TABLE user_group_members (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    assigned_by INT NOT NULL,
                    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_group_user (group_id, user_id),
                    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            if ($conn->exec($createMembersSQL)) {
                echo "<div class='test-result test-success'>✅ Tabla user_group_members creada exitosamente</div>";
            } else {
                echo "<div class='test-result test-error'>❌ Error creando tabla user_group_members</div>";
            }
        }
    }
} catch (Exception $e) {
    echo "<div class='test-result test-error'>❌ Error con tablas: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo "</div>";

// 7. Test de rutas relativas
echo "<div class='section'>";
echo "<h2>7. 🔗 Test de Rutas Relativas</h2>";

$testPaths = [
    'desde modules/groups/index.php' => [
        '../../config/session.php' => realpath('config/session.php'),
        '../../config/database.php' => realpath('config/database.php'),
        '../../includes/sidebar.php' => realpath('includes/sidebar.php'),
        '../../assets/css/main.css' => realpath('assets/css/main.css')
    ],
    'desde modules/groups/actions/' => [
        '../../../config/session.php' => realpath('config/session.php'),
        '../../../config/database.php' => realpath('config/database.php'),
        '../../../includes/functions.php' => realpath('includes/functions.php')
    ]
];

foreach ($testPaths as $context => $paths) {
    echo "<h4>$context:</h4>";
    foreach ($paths as $relative => $absolute) {
        if ($absolute && file_exists($absolute)) {
            echo "<div class='test-result test-success'>✅ <code>$relative</code> → <code>$absolute</code></div>";
        } else {
            echo "<div class='test-result test-error'>❌ <code>$relative</code> → Ruta no válida</div>";
        }
    }
}
echo "</div>";

// 8. Crear archivos de acción básicos si no existen
echo "<div class='section'>";
echo "<h2>8. 📝 Creando Archivos de Acción Básicos</h2>";

$actionFiles = [
    'create_group.php' => '<?php
// modules/groups/actions/create_group.php
require_once "../../../config/session.php";
require_once "../../../config/database.php";
header("Content-Type: application/json");

if (!SessionManager::isLoggedIn()) {
    echo json_encode(["success" => false, "message" => "No autorizado"]);
    exit;
}

// Implementar lógica de creación aquí
echo json_encode(["success" => false, "message" => "Función en desarrollo"]);
?>',
    
    'get_group_details.php' => '<?php
// modules/groups/actions/get_group_details.php
require_once "../../../config/session.php";
require_once "../../../config/database.php";
header("Content-Type: application/json");

if (!SessionManager::isLoggedIn()) {
    echo json_encode(["success" => false, "message" => "No autorizado"]);
    exit;
}

// Implementar lógica de obtener detalles aquí
echo json_encode(["success" => false, "message" => "Función en desarrollo"]);
?>',
    
    'toggle_group_status.php' => '<?php
// modules/groups/actions/toggle_group_status.php
require_once "../../../config/session.php";
require_once "../../../config/database.php";
header("Content-Type: application/json");

if (!SessionManager::isLoggedIn()) {
    echo json_encode(["success" => false, "message" => "No autorizado"]);
    exit;
}

// Implementar lógica de cambio de estado aquí
echo json_encode(["success" => false, "message" => "Función en desarrollo"]);
?>'
];

foreach ($actionFiles as $filename => $content) {
    $filepath = "modules/groups/actions/$filename";
    if (!file_exists($filepath)) {
        if (file_put_contents($filepath, $content)) {
            echo "<div class='test-result test-success'>✅ Archivo <code>$filename</code> creado</div>";
        } else {
            echo "<div class='test-result test-error'>❌ No se pudo crear <code>$filename</code></div>";
        }
    } else {
        echo "<div class='test-result test-info'>ℹ️ Archivo <code>$filename</code> ya existe</div>";
    }
}
echo "</div>";

// 9. Resumen y próximos pasos
echo "<div class='section'>";
echo "<h2>9. 📋 Resumen y Próximos Pasos</h2>";

$issues = [];
$recommendations = [];

if ($filesPercentage < 80) {
    $issues[] = "Faltan archivos CSS/JS importantes";
    $recommendations[] = "Crear los archivos CSS faltantes o copiarlos de otros módulos";
}

if (!isset($conn) || !$conn) {
    $issues[] = "Problemas de conexión a base de datos";
    $recommendations[] = "Verificar configuración en config/database.php";
}

if (empty($issues)) {
    echo "<div class='test-result test-success'>🎉 <strong>¡Todo parece estar en orden!</strong></div>";
    echo "<p>El módulo de grupos debería funcionar correctamente.</p>";
} else {
    echo "<h4>⚠️ Problemas encontrados:</h4>";
    foreach ($issues as $issue) {
        echo "<div class='test-result test-warning'>⚠️ $issue</div>";
    }
    
    echo "<h4>💡 Recomendaciones:</h4>";
    foreach ($recommendations as $rec) {
        echo "<div class='test-result test-info'>💡 $rec</div>";
    }
}

echo "<h4>🚀 Próximos pasos:</h4>";
echo "<ol>";
echo "<li>Si hay problemas de BD: Ejecutar <code>setup_groups_module.php</code></li>";
echo "<li>Si faltan archivos CSS: Copiar desde otros módulos o crear básicos</li>";
echo "<li>Acceder a <a href='modules/groups/index.php' target='_blank'><code>modules/groups/index.php</code></a> para probar</li>";
echo "<li>Si no estás logueado: Ir a <a href='login.php' target='_blank'><code>login.php</code></a> primero</li>";
echo "</ol>";

echo "</div>";
echo "</div>";

echo "<p><em>Diagnóstico completado: " . date('Y-m-d H:i:s') . "</em></p>";
?>