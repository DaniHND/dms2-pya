<?php
// diagnostico_departamentos.php
// Script para diagnosticar problemas en el módulo de departamentos

echo "<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico - Módulo Departamentos</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .info { color: #3498db; font-weight: bold; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #3498db; background: #f8f9fa; }
        .code { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; font-family: 'Courier New', monospace; overflow-x: auto; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .test-success { background: #d4edda; border: 1px solid #c3e6cb; }
        .test-error { background: #f8d7da; border: 1px solid #f5c6cb; }
        h1, h2 { color: #2c3e50; }
        .status-icon { font-size: 20px; margin-right: 10px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Diagnóstico del Módulo Departamentos</h1>";
echo "<p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>";

// 1. Verificar estructura de archivos
echo "<div class='section'>";
echo "<h2>📁 1. Verificación de Archivos</h2>";

$requiredFiles = [
    'config/session.php' => 'Manejo de sesiones',
    'config/database.php' => 'Conexión a base de datos', 
    'includes/functions.php' => 'Funciones auxiliares',
    'modules/departments/actions/get_department_details.php' => 'Detalles del departamento',
    'modules/departments/actions/get_departments.php' => 'Lista de departamentos',
    'modules/departments/actions/create_department.php' => 'Crear departamento',
    'modules/departments/actions/update_department.php' => 'Actualizar departamento',
    'modules/departments/actions/toggle_department_status.php' => 'Cambiar estado',
    'modules/departments/index.php' => 'Página principal del módulo'
];

$allFilesExist = true;

foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='test-result test-success'>";
        echo "<span class='status-icon'>✅</span><strong>$file</strong> - $description";
        echo "<br><span style='color: #666; font-size: 0.9em;'>Tamaño: " . number_format(filesize($file)) . " bytes</span>";
        echo "</div>";
    } else {
        echo "<div class='test-result test-error'>";
        echo "<span class='status-icon'>❌</span><strong>$file</strong> - $description <span class='error'>(FALTA)</span>";
        echo "</div>";
        $allFilesExist = false;
    }
}

if ($allFilesExist) {
    echo "<p class='success'>✅ Todos los archivos necesarios están presentes</p>";
} else {
    echo "<p class='error'>❌ Faltan archivos importantes. Verifique la estructura del proyecto.</p>";
}
echo "</div>";

// 2. Verificar conexión a base de datos
echo "<div class='section'>";
echo "<h2>🗄️ 2. Verificación de Base de Datos</h2>";

try {
    require_once 'config/database.php';
    
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<div class='test-result test-success'>";
        echo "<span class='status-icon'>✅</span><strong>Conexión a base de datos exitosa</strong>";
        echo "</div>";
        
        // Verificar tablas necesarias
        $requiredTables = ['departments', 'companies', 'users'];
        
        foreach ($requiredTables as $table) {
            try {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result->rowCount() > 0) {
                    // Contar registros
                    $count = $conn->query("SELECT COUNT(*) as count FROM $table")->fetch();
                    echo "<div class='test-result test-success'>";
                    echo "<span class='status-icon'>✅</span><strong>Tabla '$table'</strong> existe - {$count['count']} registros";
                    echo "</div>";
                } else {
                    echo "<div class='test-result test-error'>";
                    echo "<span class='status-icon'>❌</span><strong>Tabla '$table'</strong> no existe";
                    echo "</div>";
                }
            } catch (Exception $e) {
                echo "<div class='test-result test-error'>";
                echo "<span class='status-icon'>❌</span><strong>Error verificando tabla '$table':</strong> " . $e->getMessage();
                echo "</div>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<div class='test-result test-error'>";
    echo "<span class='status-icon'>❌</span><strong>Error de conexión a base de datos:</strong> " . $e->getMessage();
    echo "</div>";
}
echo "</div>";

// 3. Verificar rutas del archivo problemático
echo "<div class='section'>";
echo "<h2>🔧 3. Diagnóstico de Rutas - get_department_details.php</h2>";

$actionFile = 'modules/departments/actions/get_department_details.php';
if (file_exists($actionFile)) {
    // Verificar rutas desde el contexto del archivo de acciones
    $basePath = dirname(__FILE__);
    $actionPath = dirname($actionFile);
    
    echo "<p><strong>Directorio actual:</strong> <code>$basePath</code></p>";
    echo "<p><strong>Directorio del archivo de acción:</strong> <code>" . realpath($actionPath) . "</code></p>";
    
    // Verificar cada ruta relativa
    $relativePaths = [
        '../../../config/session.php' => 'Sesiones',
        '../../../config/database.php' => 'Base de datos',
        '../../../includes/functions.php' => 'Funciones'
    ];
    
    foreach ($relativePaths as $relativePath => $description) {
        $fullPath = realpath($actionPath . '/' . $relativePath);
        
        if ($fullPath && file_exists($fullPath)) {
            echo "<div class='test-result test-success'>";
            echo "<span class='status-icon'>✅</span><strong>$relativePath</strong> → <code>$fullPath</code>";
            echo "</div>";
        } else {
            echo "<div class='test-result test-error'>";
            echo "<span class='status-icon'>❌</span><strong>$relativePath</strong> → Ruta no válida";
            echo "</div>";
        }
    }
} else {
    echo "<div class='test-result test-error'>";
    echo "<span class='status-icon'>❌</span>No se puede verificar rutas porque el archivo no existe";
    echo "</div>";
}
echo "</div>";

// 4. Test simulado de la función
echo "<div class='section'>";
echo "<h2>🧪 4. Test Simulado de get_department_details.php</h2>";

if (file_exists('config/database.php') && file_exists('includes/functions.php')) {
    try {
        require_once 'config/database.php';
        require_once 'includes/functions.php';
        
        // Obtener un departamento de prueba
        $testDept = fetchOne("SELECT id FROM departments LIMIT 1");
        
        if ($testDept) {
            $deptId = $testDept['id'];
            echo "<p class='info'>🔍 Probando con departamento ID: $deptId</p>";
            
            // Simular la consulta principal
            $departmentQuery = "SELECT d.id, d.name, d.description, d.company_id, d.manager_id, d.parent_id, d.status, d.created_at, d.updated_at,
                                       c.name as company_name,
                                       u.first_name as manager_first_name,
                                       u.last_name as manager_last_name,
                                       u.email as manager_email
                                FROM departments d 
                                LEFT JOIN companies c ON d.company_id = c.id 
                                LEFT JOIN users u ON d.manager_id = u.id 
                                WHERE d.id = :id";
            
            $department = fetchOne($departmentQuery, ['id' => $deptId]);
            
            if ($department) {
                echo "<div class='test-result test-success'>";
                echo "<span class='status-icon'>✅</span><strong>Consulta principal exitosa</strong>";
                echo "<br><span style='color: #666;'>Departamento: " . htmlspecialchars($department['name']) . "</span>";
                echo "</div>";
                
                // Test de estadísticas de usuarios
                $userStatsQuery = "SELECT COUNT(*) as total_users FROM users WHERE department_id = :department_id";
                $userStats = fetchOne($userStatsQuery, ['department_id' => $deptId]);
                
                echo "<div class='test-result test-success'>";
                echo "<span class='status-icon'>✅</span><strong>Consulta de estadísticas exitosa</strong>";
                echo "<br><span style='color: #666;'>Total usuarios: " . ($userStats['total_users'] ?? 0) . "</span>";
                echo "</div>";
                
            } else {
                echo "<div class='test-result test-error'>";
                echo "<span class='status-icon'>❌</span><strong>La consulta no devolvió resultados</strong>";
                echo "</div>";
            }
            
        } else {
            echo "<div class='test-result test-warning'>";
            echo "<span class='status-icon'>⚠️</span><strong>No hay departamentos en la base de datos para probar</strong>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='test-result test-error'>";
        echo "<span class='status-icon'>❌</span><strong>Error en test:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
} else {
    echo "<div class='test-result test-error'>";
    echo "<span class='status-icon'>❌</span><strong>No se pueden cargar las dependencias para el test</strong>";
    echo "</div>";
}
echo "</div>";

// 5. Recomendaciones
echo "<div class='section'>";
echo "<h2>💡 5. Recomendaciones para Solucionar los Problemas</h2>";

echo "<h3>🔧 Para corregir el error de rutas:</h3>";
echo "<div class='code'>";
echo "1. Reemplazar el archivo modules/departments/actions/get_department_details.php\n";
echo "   con la versión corregida que incluye verificación de rutas\n\n";
echo "2. Verificar que la estructura de directorios sea:\n";
echo "   proyecto/\n";
echo "   ├── config/\n";
echo "   │   ├── session.php\n";
echo "   │   └── database.php\n";
echo "   ├── includes/\n";
echo "   │   └── functions.php\n";
echo "   └── modules/\n";
echo "       └── departments/\n";
echo "           └── actions/\n";
echo "               └── get_department_details.php\n\n";
echo "3. Asegurar que todos los archivos tengan permisos de lectura\n";
echo "</div>";

echo "<h3>🛠️ Pasos adicionales:</h3>";
echo "<ul>";
echo "<li><strong>Verificar sesiones:</strong> Asegurar que SessionManager esté funcionando correctamente</li>";
echo "<li><strong>Revisar logs:</strong> Verificar los logs de PHP por errores adicionales</li>";
echo "<li><strong>Test manual:</strong> Probar el endpoint directamente desde el navegador</li>";
echo "<li><strong>Depuración:</strong> Activar error_reporting para ver todos los warnings</li>";
echo "</ul>";

echo "</div>";

echo "</div></body></html>";
?>