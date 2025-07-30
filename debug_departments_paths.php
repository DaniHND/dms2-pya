<?php
// debug_departments_paths.php
// Script para verificar que todas las rutas y archivos existan

echo "<h2>🔍 Debug de Rutas - Módulo Departamentos</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; }
</style>";

// 1. Verificar archivos de acciones
echo "<h3>1. Verificando archivos de acciones</h3>";

$actionFiles = [
    'modules/departments/actions/create_department.php',
    'modules/departments/actions/get_departments.php',
    'modules/departments/actions/get_department_details.php',
    'modules/departments/actions/update_department.php',
    'modules/departments/actions/toggle_department_status.php'
];

foreach ($actionFiles as $file) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file</span><br>";
        
        // Verificar que el archivo tenga contenido
        $content = file_get_contents($file);
        if (strpos($content, 'json_encode') !== false) {
            echo "<span class='info'>&nbsp;&nbsp;&nbsp;📄 Tiene respuesta JSON</span><br>";
        } else {
            echo "<span class='warning'>&nbsp;&nbsp;&nbsp;⚠️ No parece tener respuesta JSON</span><br>";
        }
    } else {
        echo "<span class='error'>❌ $file FALTA</span><br>";
    }
}

// 2. Verificar archivos JS y CSS
echo "<h3>2. Verificando archivos JS y CSS</h3>";

$assetFiles = [
    'assets/js/departments.js',
    'assets/css/departments.css',
    'assets/js/main.js'
];

foreach ($assetFiles as $file) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file</span><br>";
        echo "<span class='info'>&nbsp;&nbsp;&nbsp;📏 Tamaño: " . number_format(filesize($file)) . " bytes</span><br>";
    } else {
        echo "<span class='error'>❌ $file FALTA</span><br>";
    }
}

// 3. Probar conexión a base de datos
echo "<h3>3. Probando conexión a base de datos</h3>";

try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            echo "<span class='success'>✅ Conexión a BD exitosa</span><br>";
            
            // Probar consulta de departamentos
            $query = "SELECT COUNT(*) as total FROM departments";
            $result = $conn->query($query);
            if ($result) {
                $count = $result->fetch(PDO::FETCH_ASSOC)['total'];
                echo "<span class='info'>📊 Total departamentos: $count</span><br>";
            }
        } else {
            echo "<span class='error'>❌ No se pudo conectar a BD</span><br>";
        }
    } else {
        echo "<span class='error'>❌ Archivo config/database.php no existe</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error BD: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

// 4. Verificar estructura de directorios
echo "<h3>4. Estructura de directorios</h3>";

$directories = [
    'modules/departments/',
    'modules/departments/actions/',
    'modules/users/actions/',
    'assets/js/',
    'assets/css/'
];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "<span class='success'>✅ $dir</span><br>";
        
        // Listar archivos en el directorio
        $files = scandir($dir);
        $phpFiles = array_filter($files, function($file) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'php';
        });
        
        if (!empty($phpFiles)) {
            echo "<span class='info'>&nbsp;&nbsp;&nbsp;📁 Archivos PHP: " . implode(', ', $phpFiles) . "</span><br>";
        }
    } else {
        echo "<span class='error'>❌ $dir NO EXISTE</span><br>";
    }
}

// 5. Test de URL de acciones
echo "<h3>5. Test de URLs (simulado)</h3>";

$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/';

$testUrls = [
    'modules/departments/actions/get_department_details.php?id=1',
    'modules/users/actions/get_users.php?company_id=1&status=active'
];

foreach ($testUrls as $url) {
    $fullUrl = $baseUrl . $url;
    echo "<span class='info'>🌐 $url</span><br>";
    echo "<span class='info'>&nbsp;&nbsp;&nbsp;URL completa: $fullUrl</span><br>";
    
    // Verificar que el archivo existe
    $filePath = $url;
    $queryPos = strpos($filePath, '?');
    if ($queryPos !== false) {
        $filePath = substr($filePath, 0, $queryPos);
    }
    
    if (file_exists($filePath)) {
        echo "<span class='success'>&nbsp;&nbsp;&nbsp;✅ Archivo existe</span><br>";
    } else {
        echo "<span class='error'>&nbsp;&nbsp;&nbsp;❌ Archivo NO existe</span><br>";
    }
}

// 6. Información del servidor
echo "<h3>6. Información del servidor</h3>";

echo "<span class='info'>🖥️ Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "</span><br>";
echo "<span class='info'>🐘 PHP: " . PHP_VERSION . "</span><br>";
echo "<span class='info'>📂 Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</span><br>";
echo "<span class='info'>📍 Script actual: " . $_SERVER['SCRIPT_NAME'] . "</span><br>";
echo "<span class='info'>🌐 Host: " . $_SERVER['HTTP_HOST'] . "</span><br>";

// 7. Sugerencias de solución
echo "<h3>7. 🔧 Posibles soluciones</h3>";

echo "<div class='code'>";
echo "<strong>Si faltan archivos de acciones:</strong><br>";
echo "1. Verificar que todos los archivos PHP estén en modules/departments/actions/<br>";
echo "2. Verificar permisos de lectura en los archivos<br>";
echo "3. Verificar que los archivos tengan el código correcto<br><br>";

echo "<strong>Si hay errores de conexión:</strong><br>";
echo "1. Verificar config/database.php<br>";
echo "2. Verificar credenciales de base de datos<br>";
echo "3. Verificar que la tabla 'departments' exista<br><br>";

echo "<strong>Si hay errores de JavaScript:</strong><br>";
echo "1. Abrir Consola del navegador (F12)<br>";
echo "2. Ver errores en la pestaña 'Console'<br>";
echo "3. Verificar que assets/js/departments.js se cargue correctamente<br><br>";

echo "<strong>Comandos útiles:</strong><br>";
echo "- Consola del navegador: F12 → Console<br>";
echo "- Ver errores PHP: tail -f /var/log/apache2/error.log<br>";
echo "- Verificar permisos: ls -la modules/departments/actions/<br>";
echo "</div>";

echo "<br><p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>