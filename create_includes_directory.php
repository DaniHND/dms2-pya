<?php
// create_includes_directory.php
// Script para crear directorio includes y módulos

echo "<h2>Creando directorios necesarios...</h2>\n";

$directories = [
    'includes',
    'modules/reports',
    'modules/activity'
];

$created = 0;
$existing = 0;

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Directorio creado: $dir\n<br>";
            $created++;
        } else {
            echo "❌ Error al crear: $dir\n<br>";
        }
    } else {
        echo "📁 Ya existe: $dir\n<br>";
        $existing++;
    }
}

echo "\n<h3>Archivos a crear:</h3>\n";
echo "1. <strong>includes/sidebar.php</strong> - Componente sidebar reutilizable\n<br>";
echo "2. <strong>modules/reports/index.php</strong> - Módulo de reportes\n<br>";
echo "3. <strong>modules/activity/log.php</strong> - Log de actividades\n<br>";
echo "4. Actualizar <strong>dashboard.php</strong> - Para usar el sidebar componente\n<br>";

echo "\n<h3>Resumen:</h3>\n";
echo "Directorios creados: $created\n<br>";
echo "Directorios existentes: $existing\n<br>";
echo "Total verificados: " . count($directories) . "\n<br>";

echo "\n<p style='color: green; font-weight: bold;'>✅ Directorios listos para el sidebar componente!</p>\n";
?>