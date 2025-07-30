<?php
// test_simple.php
// Prueba simple de los endpoints

session_start();

// Simular sesión de administrador
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin_test';

echo "<h1>🧪 Prueba Simple de Endpoints</h1>";

// Test directo de get_companies.php
echo "<h2>📋 Test get_companies.php</h2>";
echo "<h3>Llamada directa:</h3>";

// Simular REQUEST_METHOD
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "<p><strong>URL:</strong> <a href='modules/departments/actions/get_companies.php' target='_blank'>modules/departments/actions/get_companies.php</a></p>";

// Verificar que el archivo existe
if (file_exists('modules/departments/actions/get_companies.php')) {
    echo "<p style='color: green;'>✅ Archivo existe</p>";
    
    // Mostrar contenido del archivo para debug
    echo "<h4>📄 Contenido del archivo (primeras 20 líneas):</h4>";
    $lines = file('modules/departments/actions/get_companies.php');
    echo "<pre style='background: #f4f4f4; padding: 10px; border-radius: 4px;'>";
    for ($i = 0; $i < min(20, count($lines)); $i++) {
        echo htmlspecialchars($lines[$i]);
    }
    if (count($lines) > 20) {
        echo "... (y " . (count($lines) - 20) . " líneas más)\n";
    }
    echo "</pre>";
    
} else {
    echo "<p style='color: red;'>❌ Archivo NO existe</p>";
}

// Test directo de get_managers.php
echo "<h2>👥 Test get_managers.php</h2>";
echo "<p><strong>URL:</strong> <a href='modules/departments/actions/get_managers.php' target='_blank'>modules/departments/actions/get_managers.php</a></p>";

if (file_exists('modules/departments/actions/get_managers.php')) {
    echo "<p style='color: green;'>✅ Archivo existe</p>";
} else {
    echo "<p style='color: red;'>❌ Archivo NO existe</p>";
}

// Verificar estructura de directorios
echo "<h2>📁 Verificar estructura de directorios</h2>";

$paths = [
    'config/session.php',
    'config/database.php', 
    'includes/functions.php',
    'modules/departments/actions/',
    'modules/departments/actions/get_companies.php',
    'modules/departments/actions/get_managers.php'
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        $type = is_dir($path) ? 'directorio' : 'archivo';
        echo "<p style='color: green;'>✅ $path ($type)</p>";
    } else {
        echo "<p style='color: red;'>❌ $path (NO EXISTE)</p>";
    }
}

// Verificar datos en BD
echo "<h2>🗄️ Verificar base de datos</h2>";

try {
    require_once 'config/database.php';
    
    // Test de conexión
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<p style='color: green;'>✅ Conexión a BD exitosa</p>";
        
        // Contar empresas
        $companies = fetchAll("SELECT COUNT(*) as count FROM companies WHERE status = 'active'");
        $companyCount = $companies[0]['count'] ?? 0;
        echo "<p>📊 Empresas activas: $companyCount</p>";
        
        // Contar managers
        $managers = fetchAll("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role IN ('admin', 'manager')");
        $managerCount = $managers[0]['count'] ?? 0;
        echo "<p>👥 Usuarios admin/manager activos: $managerCount</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Error de conexión a BD</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>🔧 Instrucciones</h2>";
echo "<ol>";
echo "<li><strong>Haz clic en los enlaces de los endpoints</strong> para verlos directamente en el navegador</li>";
echo "<li><strong>Revisa si devuelven JSON o HTML de error</strong></li>";
echo "<li><strong>Si hay errores PHP</strong>, copiamelos para solucionarlos</li>";
echo "</ol>";

echo "<p><em>Fecha: " . date('Y-m-d H:i:s') . "</em></p>";
?>