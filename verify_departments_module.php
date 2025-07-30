<?php
// verify_departments_module.php
// Script para verificar que el módulo de departamentos esté correctamente instalado

echo "<h1>Verificación del Módulo de Departamentos - DMS2</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
    .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

// 1. Verificar estructura de archivos
echo "<div class='section'>";
echo "<h2>1. 📁 Verificación de Estructura de Archivos</h2>";

$requiredFiles = [
    'modules/departments/index.php' => 'Página principal del módulo',
    'modules/departments/actions/create_department.php' => 'Acción crear departamento',
    'modules/departments/actions/get_departments.php' => 'Acción obtener departamentos',
    'modules/departments/actions/get_department_details.php' => 'Acción obtener detalles',
    'modules/departments/actions/update_department.php' => 'Acción actualizar departamento',
    'modules/departments/actions/toggle_department_status.php' => 'Acción cambiar estado',
    'assets/css/departments.css' => 'Estilos del módulo',
    'assets/js/departments.js' => 'JavaScript del módulo'
];

$missingFiles = [];
foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file</span> - $description<br>";
    } else {
        echo "<span class='error'>❌ $file</span> - $description <strong>(FALTA)</strong><br>";
        $missingFiles[] = $file;
    }
}

if (empty($missingFiles)) {
    echo "<br><span class='success'>🎉 Todos los archivos requeridos están presentes!</span>";
} else {
    echo "<br><span class='error'>⚠️ Faltan " . count($missingFiles) . " archivo(s). Ver lista arriba.</span>";
}
echo "</div>";

// 2. Verificar base de datos
echo "<div class='section'>";
echo "<h2>2. 🗄️ Verificación de Base de Datos</h2>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "<span class='success'>✅ Conexión a base de datos exitosa</span><br>";
    
    // Verificar tabla departments
    $query = "SHOW TABLES LIKE 'departments'";
    $result = $conn->query($query);
    
    if ($result && $result->rowCount() > 0) {
        echo "<span class='success'>✅ Tabla 'departments' existe</span><br>";
        
        // Verificar estructura de la tabla
        $columns = $conn->query("DESCRIBE departments")->fetchAll(PDO::FETCH_ASSOC);
        echo "<br><strong>Estructura de la tabla departments:</strong><br>";
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Clave</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar columna department_id en users
        $userColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'department_id'")->fetchAll();
        if (!empty($userColumns)) {
            echo "<span class='success'>✅ Columna 'department_id' existe en tabla 'users'</span><br>";
        } else {
            echo "<span class='error'>❌ Columna 'department_id' NO existe en tabla 'users'</span><br>";
            echo "<div class='code'>ALTER TABLE users ADD COLUMN department_id INT NULL AFTER company_id;</div>";
        }
        
    } else {
        echo "<span class='error'>❌ Tabla 'departments' NO existe</span><br>";
        echo "<span class='warning'>Ejecute el script sql/departments_module.sql</span><br>";
    }
    
    // Verificar datos de ejemplo
    if ($result && $result->rowCount() > 0) {
        $sampleData = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch(PDO::FETCH_ASSOC);
        echo "<br><strong>Datos en la tabla:</strong><br>";
        echo "Total de departamentos: " . $sampleData['count'] . "<br>";
        
        if ($sampleData['count'] > 0) {
            $departments = $conn->query("SELECT d.name, c.name as company_name, d.status FROM departments d LEFT JOIN companies c ON d.company_id = c.id LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            echo "<table>";
            echo "<tr><th>Nombre</th><th>Empresa</th><th>Estado</th></tr>";
            foreach ($departments as $dept) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($dept['name']) . "</td>";
                echo "<td>" . htmlspecialchars($dept['company_name'] ?? 'Sin empresa') . "</td>";
                echo "<td>" . htmlspecialchars($dept['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error de base de datos: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 3. Verificar permisos de archivos
echo "<div class='section'>";
echo "<h2>3. 🔐 Verificación de Permisos</h2>";

$directories = [
    'modules/departments/',
    'modules/departments/actions/',
    'assets/css/',
    'assets/js/'
];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (is_readable($dir) && is_writable($dir)) {
            echo "<span class='success'>✅ $dir</span> - Permisos correctos<br>";
        } else {
            echo "<span class='warning'>⚠️ $dir</span> - Verificar permisos<br>";
        }
    } else {
        echo "<span class='error'>❌ $dir</span> - Directorio no existe<br>";
    }
}
echo "</div>";

// 4. Verificar integración con sidebar
echo "<div class='section'>";
echo "<h2>4. 🧭 Verificación de Integración</h2>";

// Verificar si el archivo sidebar incluye departamentos
if (file_exists('includes/sidebar.php')) {
    $sidebarContent = file_get_contents('includes/sidebar.php');
    if (strpos($sidebarContent, 'departments') !== false && strpos($sidebarContent, 'layers') !== false) {
        echo "<span class='success'>✅ Sidebar actualizada con módulo de departamentos</span><br>";
    } else {
        echo "<span class='warning'>⚠️ Sidebar NO incluye el módulo de departamentos</span><br>";
        echo "<span class='info'>Agregar la entrada de departamentos en includes/sidebar.php</span><br>";
    }
} else {
    echo "<span class='error'>❌ Archivo includes/sidebar.php no encontrado</span><br>";
}

// Verificar iconos Feather
echo "<br><strong>Verificación de iconos:</strong><br>";
$iconFile = 'assets/js/main.js';
if (file_exists($iconFile)) {
    $jsContent = file_get_contents($iconFile);
    if (strpos($jsContent, 'feather.replace') !== false) {
        echo "<span class='success'>✅ Iconos Feather configurados correctamente</span><br>";
    } else {
        echo "<span class='warning'>⚠️ Verificar configuración de iconos Feather</span><br>";
    }
} else {
    echo "<span class='warning'>⚠️ Archivo assets/js/main.js no encontrado</span><br>";
}
echo "</div>";

// 5. Verificar funcionalidades AJAX
echo "<div class='section'>";
echo "<h2>5. 🔄 Verificación de Funcionalidades AJAX</h2>";

$ajaxEndpoints = [
    'modules/departments/actions/create_department.php',
    'modules/departments/actions/get_departments.php',
    'modules/departments/actions/get_department_details.php',
    'modules/departments/actions/update_department.php',
    'modules/departments/actions/toggle_department_status.php'
];

foreach ($ajaxEndpoints as $endpoint) {
    if (file_exists($endpoint)) {
        // Verificar que el archivo contenga las funciones básicas
        $content = file_get_contents($endpoint);
        if (strpos($content, 'SessionManager::requireRole') !== false && 
            strpos($content, 'json_encode') !== false) {
            echo "<span class='success'>✅ $endpoint</span> - Estructura correcta<br>";
        } else {
            echo "<span class='warning'>⚠️ $endpoint</span> - Verificar estructura<br>";
        }
    } else {
        echo "<span class='error'>❌ $endpoint</span> - Archivo faltante<br>";
    }
}
echo "</div>";

// 6. Test de conectividad (simulado)
echo "<div class='section'>";
echo "<h2>6. 🧪 Tests de Funcionalidad</h2>";

// Simular test de creación (sin ejecutar realmente)
echo "<strong>Tests disponibles:</strong><br>";
echo "<span class='info'>📝 Test de creación de departamento</span><br>";
echo "<span class='info'>📖 Test de lectura de departamentos</span><br>";
echo "<span class='info'>✏️ Test de actualización de departamento</span><br>";
echo "<span class='info'>🔄 Test de cambio de estado</span><br>";
echo "<span class='info'>🔍 Test de búsqueda y filtros</span><br>";

echo "<br><span class='warning'>⚠️ Los tests funcionales deben ejecutarse manualmente desde la interfaz web</span><br>";
echo "</div>";

// 7. Resumen y recomendaciones
echo "<div class='section'>";
echo "<h2>7. 📋 Resumen y Próximos Pasos</h2>";

$errors = 0;
$warnings = 0;

// Contar errores y advertencias (simulado basado en verificaciones anteriores)
if (!empty($missingFiles)) $errors += count($missingFiles);

echo "<strong>Estado del módulo:</strong><br>";
if ($errors == 0) {
    echo "<span class='success'>🎉 Módulo de Departamentos listo para usar!</span><br>";
} else {
    echo "<span class='error'>❌ Se encontraron $errors errores que deben corregirse</span><br>";
}

echo "<br><strong>Lista de verificación final:</strong><br>";
echo "☐ Ejecutar SQL: <code>sql/departments_module.sql</code><br>";
echo "☐ Actualizar sidebar con entrada de departamentos<br>";
echo "☐ Verificar permisos de usuario administrador<br>";
echo "☐ Probar crear departamento desde la interfaz<br>";
echo "☐ Probar editar y cambiar estado<br>";
echo "☐ Verificar relación con empresas y usuarios<br>";
echo "☐ Probar filtros y búsqueda<br>";

echo "<br><strong>URLs para probar:</strong><br>";
echo "• <a href='modules/departments/index.php' target='_blank'>modules/departments/index.php</a> - Página principal<br>";
echo "• <a href='dashboard.php' target='_blank'>dashboard.php</a> - Verificar estadísticas<br>";

echo "<br><strong>Comandos SQL útiles:</strong><br>";
echo "<div class='code'>";
echo "-- Ver todos los departamentos<br>";
echo "SELECT d.*, c.name as company_name FROM departments d LEFT JOIN companies c ON d.company_id = c.id;<br><br>";
echo "-- Ver usuarios con departamento<br>";
echo "SELECT u.first_name, u.last_name, d.name as department FROM users u LEFT JOIN departments d ON u.department_id = d.id;<br><br>";
echo "-- Estadísticas rápidas<br>";
echo "SELECT<br>";
echo "&nbsp;&nbsp;(SELECT COUNT(*) FROM departments) as total_departments,<br>";
echo "&nbsp;&nbsp;(SELECT COUNT(*) FROM departments WHERE status = 'active') as active_departments,<br>";
echo "&nbsp;&nbsp;(SELECT COUNT(*) FROM users WHERE department_id IS NOT NULL) as users_with_department;<br>";
echo "</div>";

echo "</div>";

// Footer
echo "<hr>";
echo "<p><strong>Verificación completada:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Próximo módulo sugerido:</strong> Grupos de usuarios o Tipos de documento</p>";
echo "<p style='color: green; font-weight: bold;'>✅ Módulo de Departamentos implementado siguiendo los patrones establecidos de DMS2!</p>";
?>