<?php
/*
 * test_apis.php
 * Diagnóstico específico de APIs
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test APIs - Diagnóstico</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .test { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .code { background: #e9ecef; padding: 10px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>

<h1>🔍 Diagnóstico de APIs</h1>

<?php
// Test 1: Verificar archivos
echo "<div class='test'>";
echo "<h3>1. Verificación de Archivos API</h3>";

$apiFiles = [
    'api/get_users.php',
    'api/get_companies.php', 
    'api/get_departments.php',
    'api/get_document_types.php'
];

foreach ($apiFiles as $file) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $file existe</div>";
    } else {
        echo "<div class='error'>❌ $file NO existe</div>";
    }
}
echo "</div>";

// Test 2: Verificar configuración
echo "<div class='test'>";
echo "<h3>2. Verificación de Configuración</h3>";

if (file_exists('config/database.php')) {
    echo "<div class='success'>✅ config/database.php existe</div>";
    require_once 'config/database.php';
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        echo "<div class='success'>✅ Conexión a BD exitosa</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error de BD: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='error'>❌ config/database.php NO existe</div>";
}

if (file_exists('config/session.php')) {
    echo "<div class='success'>✅ config/session.php existe</div>";
    require_once 'config/session.php';
} else {
    echo "<div class='error'>❌ config/session.php NO existe</div>";
}
echo "</div>";

// Test 3: Verificar sesión
echo "<div class='test'>";
echo "<h3>3. Verificación de Sesión</h3>";

if (class_exists('SessionManager')) {
    echo "<div class='success'>✅ Clase SessionManager encontrada</div>";
    
    if (SessionManager::isLoggedIn()) {
        $user = SessionManager::getCurrentUser();
        echo "<div class='success'>✅ Usuario logueado: {$user['username']} (Rol: {$user['role']})</div>";
    } else {
        echo "<div class='error'>❌ Usuario NO está logueado</div>";
        echo "<div>Para probar APIs necesitas estar logueado como admin</div>";
    }
} else {
    echo "<div class='error'>❌ Clase SessionManager no encontrada</div>";
}
echo "</div>";

// Test 4: Prueba directa de API
echo "<div class='test'>";
echo "<h3>4. Prueba Directa de API</h3>";

if (SessionManager::isLoggedIn()) {
    echo "<button onclick='testAPI()'>Probar API get_users.php</button>";
    echo "<div id='apiResult'></div>";
} else {
    echo "<div class='error'>❌ Debes estar logueado para probar APIs</div>";
}
echo "</div>";

?>

<script>
async function testAPI() {
    const resultDiv = document.getElementById('apiResult');
    resultDiv.innerHTML = '<div>⏳ Probando API...</div>';
    
    try {
        console.log('Probando: api/get_users.php');
        
        const response = await fetch('api/get_users.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        const text = await response.text();
        console.log('Response text:', text);
        
        let result = `<div class="code">Status: ${response.status}<br>Response: ${text}</div>`;
        
        try {
            const json = JSON.parse(text);
            result += `<div class="success">✅ JSON válido</div>`;
            result += `<div class="code">Datos: ${JSON.stringify(json, null, 2)}</div>`;
        } catch (e) {
            result += `<div class="error">❌ No es JSON válido</div>`;
        }
        
        resultDiv.innerHTML = result;
        
    } catch (error) {
        console.error('Error:', error);
        resultDiv.innerHTML = `<div class="error">❌ Error: ${error.message}</div>`;
    }
}
</script>

</body>
</html>