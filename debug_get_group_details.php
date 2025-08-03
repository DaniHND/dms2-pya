<?php
/*
 * debug_get_group_details.php
 * Archivo temporal para debuggear qué está devolviendo get_group_details.php
 * Guárdalo en la raíz del proyecto y ve a: http://localhost/dms2-pya/debug_get_group_details.php?id=1
 */

echo "<h1>🔍 Debug de get_group_details.php</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
</style>";

// Capturar cualquier output
ob_start();

echo "<h2>📋 Parámetros recibidos:</h2>";
echo "<div class='info'>";
echo "ID del grupo: " . ($_GET['id'] ?? 'NO ESPECIFICADO') . "<br>";
echo "Método: " . $_SERVER['REQUEST_METHOD'] . "<br>";
echo "</div>";

echo "<h2>🔧 Ejecutando get_group_details.php:</h2>";

// Simular la ejecución del archivo original
$_GET['id'] = $_GET['id'] ?? 1; // Default para testing
$_SERVER['REQUEST_METHOD'] = 'GET';

// Capturar output del archivo
ob_start();
try {
    include 'modules/groups/actions/get_group_details.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
$output = ob_get_clean();

echo "<h3>✅ Output capturado:</h3>";
echo "<div class='code'>";
echo "Tamaño: " . strlen($output) . " caracteres<br><br>";
echo "Contenido:<br>";
echo htmlspecialchars($output);
echo "</div>";

echo "<h3>🔍 Análisis del JSON:</h3>";
$decoded = json_decode($output, true);
if ($decoded !== null) {
    echo "<div class='success'>✅ JSON válido</div>";
    echo "<div class='code'>";
    echo "<pre>" . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    echo "</div>";
} else {
    echo "<div class='error'>❌ JSON inválido</div>";
    echo "<div class='error'>Error: " . json_last_error_msg() . "</div>";
    
    // Buscar posibles problemas
    echo "<h4>🔍 Análisis de problemas:</h4>";
    
    if (strpos($output, 'Warning:') !== false) {
        echo "<div class='error'>⚠️ Se encontraron warnings de PHP</div>";
    }
    
    if (strpos($output, 'Notice:') !== false) {
        echo "<div class='error'>⚠️ Se encontraron notices de PHP</div>";
    }
    
    if (strpos($output, 'Fatal error:') !== false) {
        echo "<div class='error'>💀 Se encontró un error fatal</div>";
    }
    
    if (strpos($output, '<?php') !== false) {
        echo "<div class='error'>📄 El output contiene código PHP (posible error de include)</div>";
    }
    
    if (strpos($output, '<html') !== false || strpos($output, '<HTML') !== false) {
        echo "<div class='error'>🌐 El output contiene HTML (no debería)</div>";
    }
}

echo "<h3>🛠️ Verificaciones adicionales:</h3>";

// Verificar archivos
$files = [
    'modules/groups/actions/get_group_details.php',
    'config/database.php',
    'config/session.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $file existe</div>";
    } else {
        echo "<div class='error'>❌ $file NO EXISTE</div>";
    }
}

// Verificar sesión
session_start();
if (isset($_SESSION['user_id'])) {
    echo "<div class='success'>✅ Sesión activa - Usuario: " . $_SESSION['user_id'] . "</div>";
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        echo "<div class='success'>✅ Permisos de admin</div>";
    } else {
        echo "<div class='error'>❌ No es admin</div>";
    }
} else {
    echo "<div class='error'>❌ No hay sesión activa</div>";
}

echo "<hr>";
echo "<p><em>Debug completado: " . date('Y-m-d H:i:s') . "</em></p>";
?>