<?php
// setup_directories.php
// Script para crear la estructura de directorios necesaria para DMS2

echo "<h2>Configurando estructura de directorios para DMS2...</h2>\n";

$directories = [
    'uploads',
    'uploads/documents',
    'modules',
    'modules/documents',
    'modules/users',
    'modules/companies',
    'modules/departments',
    'modules/groups',
    'modules/reports',
    'modules/auth',
    'assets',
    'assets/css',
    'assets/js',
    'assets/icons',
    'includes'
];

$created = 0;
$existing = 0;

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Creado: $dir\n";
            $created++;
        } else {
            echo "❌ Error al crear: $dir\n";
        }
    } else {
        echo "📁 Ya existe: $dir\n";
        $existing++;
    }
}

// Crear archivo .htaccess para proteger uploads
$htaccessContent = "# Proteger directorio de uploads
Options -Indexes
<Files ~ \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)\$\">
    deny from all
</Files>";

$htaccessPath = 'uploads/.htaccess';
if (!file_exists($htaccessPath)) {
    if (file_put_contents($htaccessPath, $htaccessContent)) {
        echo "✅ Creado archivo de protección: .htaccess\n";
    } else {
        echo "❌ Error al crear .htaccess\n";
    }
} else {
    echo "📁 Ya existe: .htaccess\n";
}

// Crear archivo index.php de protección en uploads
$indexContent = "<?php
// Archivo de protección - No permitir acceso directo
header('HTTP/1.0 403 Forbidden');
exit('Acceso denegado');
?>";

$indexPath = 'uploads/index.php';
if (!file_exists($indexPath)) {
    if (file_put_contents($indexPath, $indexContent)) {
        echo "✅ Creado archivo de protección: index.php\n";
    } else {
        echo "❌ Error al crear index.php\n";
    }
}

echo "\n<h3>Resumen:</h3>\n";
echo "Directorios creados: $created\n";
echo "Directorios existentes: $existing\n";
echo "Total verificados: " . count($directories) . "\n";

echo "\n<h3>Permisos recomendados:</h3>\n";
echo "- uploads/: 755 (lectura/escritura para el servidor web)\n";
echo "- config/: 644 (solo lectura)\n";
echo "- modules/: 644 (solo lectura)\n";

echo "\n<h3>Siguiente paso:</h3>\n";
echo "1. Verificar que la base de datos esté creada e importada\n";
echo "2. Verificar la configuración en config/database.php\n";
echo "3. Acceder al sistema vía login.php\n";

echo "\n<p style='color: green; font-weight: bold;'>✅ Configuración de directorios completada!</p>\n";
?>