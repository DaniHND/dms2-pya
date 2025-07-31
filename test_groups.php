<?php
/*
 * test_groups.php
 * Script de prueba para verificar acceso al módulo de Grupos
 * Coloca este archivo en la raíz de tu proyecto y accede vía navegador
 */

require_once 'config/session.php';
require_once 'config/database.php';

// Verificar sesión
try {
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();
} catch (Exception $e) {
    die("❌ Error de sesión: " . $e->getMessage() . "<br><a href='login.php'>Iniciar Sesión</a>");
}

echo "<h1>🧪 Prueba del Módulo de Grupos - DMS2</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .step { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; }
</style>";

// 1. Verificar permisos del usuario
echo "<div class='step'>";
echo "<h2>1. 👤 Verificando Usuario</h2>";
echo "<span class='info'>Usuario actual: " . htmlspecialchars($currentUser['username']) . "</span><br>";
echo "<span class='info'>Rol: " . htmlspecialchars($currentUser['role']) . "</span><br>";

if ($currentUser['role'] === 'admin') {
    echo "<span class='success'>✅ Tienes permisos de administrador - Puedes acceder al módulo</span><br>";
} else {
    echo "<span class='warning'>⚠️ No eres administrador - El módulo de Grupos es solo para admins</span><br>";
}
echo "</div>";

// 2. Verificar conexión a base de datos
echo "<div class='step'>";
echo "<h2>2. 🗄️ Verificando Base de Datos</h2>";
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if ($pdo) {
        echo "<span class='success'>✅ Conexión a base de datos exitosa</span><br>";
        
        // Verificar si existen las tablas del módulo
        $tables = ['user_groups', 'user_group_members'];
        foreach ($tables as $table) {
            $query = "SHOW TABLES LIKE '$table'";
            $result = $pdo->query($query);
            
            if ($result && $result->rowCount() > 0) {
                echo "<span class='success'>✅ Tabla '$table' existe</span><br>";
            } else {
                echo "<span class='error'>❌ Tabla '$table' NO existe</span><br>";
                echo "<span class='warning'>Necesitas ejecutar el SQL del módulo de grupos</span><br>";
            }
        }
        
        // Si las tablas existen, mostrar datos
        if ($pdo->query("SHOW TABLES LIKE 'user_groups'")->rowCount() > 0) {
            $groupCount = $pdo->query("SELECT COUNT(*) FROM user_groups")->fetchColumn();
            echo "<span class='info ℹ️ Grupos existentes: $groupCount</span><br>";
        }
        
    } else {
        echo "<span class='error'>❌ Error de conexión a la base de datos</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 3. Verificar archivos del módulo
echo "<div class='step'>";
echo "<h2>3. 📁 Verificando Archivos del Módulo</h2>";

$requiredFiles = [
    'modules/groups/index.php' => 'Archivo principal del módulo',
    'modules/groups/actions/create_group.php' => 'Crear grupos',
    'modules/groups/actions/get_group_details.php' => 'Obtener detalles',
    'assets/css/groups.css' => 'Estilos del módulo',
    'assets/js/groups.js' => 'JavaScript del módulo'
];

$existingFiles = 0;
foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file</span> - $description<br>";
        $existingFiles++;
    } else {
        echo "<span class='error'>❌ $file</span> - $description <strong>(FALTA)</strong><br>";
    }
}

echo "<p><strong>Archivos existentes: $existingFiles/" . count($requiredFiles) . "</strong></p>";
echo "</div>";

// 4. Verificar directorio de módulos
echo "<div class='step'>";
echo "<h2>4. 📂 Verificando Estructura de Directorios</h2>";

$requiredDirs = [
    'modules/groups',
    'modules/groups/actions',
    'assets/css',
    'assets/js'
];

foreach ($requiredDirs as $dir) {
    if (is_dir($dir)) {
        echo "<span class='success'>✅ Directorio '$dir' existe</span><br>";
    } else {
        echo "<span class='error'>❌ Directorio '$dir' NO existe</span><br>";
        echo "<span class='info'>Crear con: mkdir -p $dir</span><br>";
    }
}
echo "</div>";

// 5. Enlaces de acceso
echo "<div class='step'>";
echo "<h2>5. 🚀 Enlaces de Acceso</h2>";

if (file_exists('modules/groups/index.php')) {
    echo "<p><a href='modules/groups/index.php' target='_blank' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔗 Acceder al Módulo de Grupos</a></p>";
} else {
    echo "<span class='error'>❌ No se puede acceder - El archivo index.php no existe</span><br>";
}

if (file_exists('includes/sidebar.php')) {
    echo "<p><a href='dashboard.php' target='_blank' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Ver Dashboard con Sidebar Actualizado</a></p>";
} else {
    echo "<span class='warning'>⚠️ Sidebar no actualizado</span><br>";
}
echo "</div>";

// 6. Instrucciones de instalación
echo "<div class='step'>";
echo "<h2>6. 📋 Próximos Pasos</h2>";

if ($existingFiles == 0) {
    echo "<p><strong>🚨 MÓDULO NO INSTALADO</strong></p>";
    echo "<ol>";
    echo "<li>Ejecuta el SQL de creación de tablas (Artifact 1)</li>";
    echo "<li>Crea el directorio: <code>mkdir -p modules/groups/actions</code></li>";
    echo "<li>Crea los archivos PHP del módulo (Artifacts 2-10)</li>";
    echo "<li>Crea los archivos CSS y JS (Artifacts 3 y 9)</li>";
    echo "<li>Actualiza el sidebar (Sidebar actualizado)</li>";
    echo "</ol>";
} elseif ($existingFiles < count($requiredFiles)) {
    echo "<p><strong>⚠️ INSTALACIÓN PARCIAL</strong></p>";
    echo "<p>Faltan algunos archivos. Revisa la lista anterior y crea los archivos faltantes.</p>";
} else {
    echo "<p><strong>✅ MÓDULO LISTO</strong></p>";
    echo "<p>Todos los archivos están presentes. El módulo debería funcionar correctamente.</p>";
}

echo "<h3>🔧 Comandos útiles:</h3>";
echo "<div class='code'>";
echo "# Crear directorios<br>";
echo "mkdir -p modules/groups/actions<br><br>";
echo "# Verificar permisos<br>";
echo "chmod -R 755 modules/groups<br><br>";
echo "# Ver logs de errores<br>";
echo "tail -f /var/log/apache2/error.log";
echo "</div>";
echo "</div>";

// 7. Información del sistema
echo "<div class='step'>";
echo "<h2>7. ℹ️ Información del Sistema</h2>";
echo "<span class='info'>PHP Version: " . phpversion() . "</span><br>";
echo "<span class='info'>Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "</span><br>";
echo "<span class='info'>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</span><br>";
echo "<span class='info'>Script actual: " . __FILE__ . "</span><br>";
echo "<span class='info'>Fecha actual: " . date('Y-m-d H:i:s') . "</span><br>";
echo "</div>";

echo "<hr>";
echo "<p><em>Prueba completada: " . date('Y-m-d H:i:s') . "</em></p>";
echo "<p><a href='dashboard.php'>← Volver al Dashboard</a></p>";
?>