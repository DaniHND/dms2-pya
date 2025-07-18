<?php
// debug_css.php
// Script para diagnosticar problemas con archivos CSS

echo "<h1>Diagnóstico de CSS - DMS2</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
</style>";

// 1. Verificar rutas de archivos CSS
echo "<div class='section'>";
echo "<h2>1. Verificando archivos CSS</h2>";

$cssFiles = [
    '../../assets/css/main.css' => 'CSS principal del sistema',
    '../../assets/css/dashboard.css' => 'CSS del dashboard',
    '../../assets/css/users.css' => 'CSS del módulo de usuarios',
    'assets/css/main.css' => 'CSS principal (ruta relativa)',
    'assets/css/dashboard.css' => 'CSS dashboard (ruta relativa)',
    'assets/css/users.css' => 'CSS usuarios (ruta relativa)'
];

foreach ($cssFiles as $file => $description) {
    if (file_exists($file)) {
        $size = filesize($file);
        $readable = is_readable($file);
        echo "<span class='success'>✅ $file</span> - $description<br>";
        echo "<span class='info'>📁 Tamaño: " . number_format($size) . " bytes | Legible: " . ($readable ? 'Sí' : 'No') . "</span><br><br>";
    } else {
        echo "<span class='error'>❌ $file</span> - $description <strong>(NO EXISTE)</strong><br><br>";
    }
}
echo "</div>";

// 2. Verificar contenido del archivo users.css
echo "<div class='section'>";
echo "<h2>2. Contenido del archivo users.css</h2>";

$usersCSS = '../../assets/css/users.css';
if (file_exists($usersCSS)) {
    $content = file_get_contents($usersCSS);
    $lines = count(file($usersCSS));
    
    echo "<span class='info'>📄 Líneas: $lines</span><br>";
    echo "<span class='info'>📊 Caracteres: " . strlen($content) . "</span><br><br>";
    
    // Mostrar las primeras líneas
    echo "<h3>Primeras 10 líneas del archivo:</h3>";
    echo "<pre style='background: #f8f8f8; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
    $fileLines = file($usersCSS);
    for ($i = 0; $i < min(10, count($fileLines)); $i++) {
        echo htmlspecialchars($fileLines[$i]);
    }
    echo "</pre>";
    
    // Verificar si contiene CSS válido
    if (strpos($content, '{') !== false && strpos($content, '}') !== false) {
        echo "<span class='success'>✅ El archivo parece contener CSS válido</span><br>";
    } else {
        echo "<span class='error'>❌ El archivo no parece contener CSS válido</span><br>";
    }
} else {
    echo "<span class='error'>❌ El archivo users.css no existe</span><br>";
}
echo "</div>";

// 3. Verificar desde el navegador
echo "<div class='section'>";
echo "<h2>3. Verificación desde el navegador</h2>";
echo "<p>Intenta acceder directamente a estos archivos desde tu navegador:</p>";

$baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
$cssUrls = [
    '/assets/css/main.css',
    '/assets/css/dashboard.css', 
    '/assets/css/users.css'
];

foreach ($cssUrls as $url) {
    $fullUrl = $baseUrl . $url;
    echo "<span class='info'>🔗 <a href='$fullUrl' target='_blank'>$fullUrl</a></span><br>";
}

echo "<br><p><strong>Si algún enlace da error 404, ese es el problema.</strong></p>";
echo "</div>";

// 4. Generar HTML de prueba
echo "<div class='section'>";
echo "<h2>4. Test de carga de CSS</h2>";
echo "<p>Aquí tienes un HTML de prueba para verificar la carga:</p>";

$testHTML = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Test CSS</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/users.css">
    <style>
        .test-indicator {
            background: red;
            color: white;
            padding: 10px;
            margin: 10px 0;
        }
        
        /* Si el CSS externo funciona, este debería ser sobrescrito */
        .users-table {
            background: green !important;
            color: white !important;
            padding: 20px !important;
        }
    </style>
</head>
<body>
    <h1>Test de CSS</h1>
    <div class="test-indicator">Si ves esto en rojo, el CSS inline funciona</div>
    
    <div class="users-table">
        <p>Si el CSS externo funciona, este div debería tener estilos especiales.</p>
        <p>Si no funciona, se verá verde (CSS inline)</p>
    </div>
    
    <script>
        // Verificar si los archivos CSS se cargaron
        const links = document.querySelectorAll("link[rel=\'stylesheet\']");
        links.forEach((link, index) => {
            link.onload = () => console.log(`✅ CSS ${index + 1} cargado:`, link.href);
            link.onerror = () => console.error(`❌ Error cargando CSS ${index + 1}:`, link.href);
        });
    </script>
</body>
</html>';

echo "<h3>Guarda este código como test_css.html:</h3>";
echo "<textarea style='width: 100%; height: 200px; font-family: monospace; font-size: 12px;'>" . htmlspecialchars($testHTML) . "</textarea>";
echo "</div>";

// 5. Soluciones recomendadas
echo "<div class='section'>";
echo "<h2>5. 🔧 Soluciones recomendadas</h2>";

echo "<h3>Solución 1: Verificar rutas</h3>";
echo "<p>Desde <code>modules/users/index.php</code>, la ruta correcta debería ser:</p>";
echo "<code>&lt;link rel=\"stylesheet\" href=\"../../assets/css/users.css\"&gt;</code><br><br>";

echo "<h3>Solución 2: Limpiar cache</h3>";
echo "<p>En tu navegador:</p>";
echo "<ul>";
echo "<li>Presiona <strong>Ctrl + F5</strong> (recarga forzada)</li>";
echo "<li>O presiona <strong>F12 → Network → Disable cache</strong></li>";
echo "</ul>";

echo "<h3>Solución 3: Verificar permisos</h3>";
echo "<p>En tu servidor, asegúrate de que la carpeta <code>assets/css/</code> tenga permisos de lectura.</p>";

echo "<h3>Solución 4: Agregar versioning</h3>";
echo "<p>Agrega un parámetro de versión para forzar la recarga:</p>";
echo "<code>&lt;link rel=\"stylesheet\" href=\"../../assets/css/users.css?v=" . time() . "\"&gt;</code><br><br>";

echo "<h3>Solución 5: Verificar desde DevTools</h3>";
echo "<p>En tu navegador:</p>";
echo "<ol>";
echo "<li>Presiona <strong>F12</strong></li>";
echo "<li>Ve a la pestaña <strong>Network</strong></li>";
echo "<li>Recarga la página</li>";
echo "<li>Busca los archivos CSS y verifica si dan error</li>";
echo "</ol>";

echo "</div>";

echo "<h2>🏁 Siguiente paso</h2>";
echo "<p>Ejecuta este diagnóstico y dime qué archivos existen y cuáles no. Luego podremos crear los archivos faltantes.</p>";
?>