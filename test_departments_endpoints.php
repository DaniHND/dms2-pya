<?php
// test_departments_endpoints.php
// Script para probar los endpoints AJAX del módulo de departamentos

session_start();

// Simular sesión de administrador
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin_test';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test - Endpoints Departamentos</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>";

echo "<h1>🧪 Test de Endpoints - Módulo Departamentos</h1>";
echo "<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Test 1: get_companies.php
echo "<div class='test-section'>";
echo "<h2>📋 Test 1: get_companies.php</h2>";

$companiesUrl = 'modules/departments/actions/get_companies.php';
if (file_exists($companiesUrl)) {
    echo "<p class='info'>✅ Archivo existe</p>";
    
    // Simular GET request
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    ob_start();
    try {
        include $companiesUrl;
        $output = ob_get_contents();
    } catch (Exception $e) {
        $output = "ERROR: " . $e->getMessage();
    }
    ob_end_clean();
    
    echo "<h4>📤 Respuesta:</h4>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Verificar si es JSON válido
    $jsonData = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p class='success'>✅ JSON válido</p>";
        if (isset($jsonData['success']) && $jsonData['success']) {
            echo "<p class='success'>✅ Respuesta exitosa</p>";
            echo "<p class='info'>📊 Total empresas: " . (count($jsonData['companies'] ?? [])) . "</p>";
        } else {
            echo "<p class='error'>❌ Respuesta indica error: " . ($jsonData['message'] ?? 'Sin mensaje') . "</p>";
        }
    } else {
        echo "<p class='error'>❌ JSON inválido: " . json_last_error_msg() . "</p>";
    }
} else {
    echo "<p class='error'>❌ Archivo no existe: $companiesUrl</p>";
}
echo "</div>";

// Test 2: get_managers.php
echo "<div class='test-section'>";
echo "<h2>👥 Test 2: get_managers.php</h2>";

$managersUrl = 'modules/departments/actions/get_managers.php';
if (file_exists($managersUrl)) {
    echo "<p class='info'>✅ Archivo existe</p>";
    
    ob_start();
    try {
        include $managersUrl;
        $output = ob_get_contents();
    } catch (Exception $e) {
        $output = "ERROR: " . $e->getMessage();
    }
    ob_end_clean();
    
    echo "<h4>📤 Respuesta:</h4>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Verificar si es JSON válido
    $jsonData = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p class='success'>✅ JSON válido</p>";
        if (isset($jsonData['success']) && $jsonData['success']) {
            echo "<p class='success'>✅ Respuesta exitosa</p>";
            echo "<p class='info'>📊 Total managers: " . (count($jsonData['managers'] ?? [])) . "</p>";
        } else {
            echo "<p class='error'>❌ Respuesta indica error: " . ($jsonData['message'] ?? 'Sin mensaje') . "</p>";
        }
    } else {
        echo "<p class='error'>❌ JSON inválido: " . json_last_error_msg() . "</p>";
    }
} else {
    echo "<p class='error'>❌ Archivo no existe: $managersUrl</p>";
}
echo "</div>";

// Test 3: Verificar datos en BD
echo "<div class='test-section'>";
echo "<h2>🗄️ Test 3: Verificar datos en base de datos</h2>";

try {
    require_once 'config/database.php';
    
    // Verificar empresas
    $companies = fetchAll("SELECT id, name, status FROM companies ORDER BY name");
    echo "<h4>📋 Empresas en BD:</h4>";
    if (!empty($companies)) {
        echo "<ul>";
        foreach ($companies as $company) {
            $statusClass = $company['status'] === 'active' ? 'success' : 'error';
            echo "<li>ID: {$company['id']} - <strong>{$company['name']}</strong> - <span class='$statusClass'>{$company['status']}</span></li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='error'>❌ No hay empresas en la base de datos</p>";
    }
    
    // Verificar usuarios que pueden ser managers
    $managers = fetchAll("SELECT id, first_name, last_name, role, status FROM users WHERE role IN ('admin', 'manager') ORDER BY first_name");
    echo "<h4>👥 Usuarios manager/admin en BD:</h4>";
    if (!empty($managers)) {
        echo "<ul>";
        foreach ($managers as $manager) {
            $statusClass = $manager['status'] === 'active' ? 'success' : 'error';
            $name = trim(($manager['first_name'] ?? '') . ' ' . ($manager['last_name'] ?? ''));
            echo "<li>ID: {$manager['id']} - <strong>$name</strong> - {$manager['role']} - <span class='$statusClass'>{$manager['status']}</span></li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='error'>❌ No hay usuarios admin/manager en la base de datos</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error de base de datos: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Test 4: Recomendaciones
echo "<div class='test-section'>";
echo "<h2>💡 Recomendaciones</h2>";
echo "<h4>Si hay errores de JSON:</h4>";
echo "<ul>";
echo "<li>Verificar que no haya output antes del JSON (espacios, echo, etc.)</li>";
echo "<li>Verificar que los archivos PHP tengan <?php al inicio sin espacios</li>";
echo "<li>Verificar permisos de archivos</li>";
echo "<li>Revisar logs de errores PHP</li>";
echo "</ul>";

echo "<h4>Si hay errores de base de datos:</h4>";
echo "<ul>";
echo "<li>Verificar conexión a la base de datos</li>";
echo "<li>Asegurar que las tablas 'companies' y 'users' existan</li>";
echo "<li>Verificar que haya datos de prueba</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?>