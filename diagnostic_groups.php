<?php
/*
 * diagnostic_groups.php
 * Script para diagnosticar problemas en el módulo de grupos
 * Ejecutar desde: http://localhost/dms2-pya/diagnostic_groups.php
 */

echo "<h1>🔍 Diagnóstico del Módulo de Grupos</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
    .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; }
</style>";

// 1. VERIFICAR ARCHIVOS CRÍTICOS
echo "<div class='section'>";
echo "<h2>📁 1. Verificación de Archivos</h2>";

$criticalFiles = [
    'modules/groups/index.php' => 'Página principal',
    'modules/groups/actions/get_group_details.php' => 'Obtener detalles (CRÍTICO)',
    'modules/groups/actions/create_group.php' => 'Crear grupo',
    'modules/groups/actions/toggle_group_status.php' => 'Cambiar estado',
    'config/database.php' => 'Configuración BD',
    'config/session.php' => 'Gestión de sesiones'
];

$missingFiles = [];
foreach ($criticalFiles as $file => $desc) {
    if (file_exists($file)) {
        $size = round(filesize($file) / 1024, 1);
        echo "<span class='success'>✅ $file</span> ($desc) - {$size}KB<br>";
    } else {
        echo "<span class='error'>❌ $file</span> ($desc) - <strong>FALTA</strong><br>";
        $missingFiles[] = $file;
    }
}

if (empty($missingFiles)) {
    echo "<div class='success'>🎉 Todos los archivos críticos están presentes</div>";
} else {
    echo "<div class='error'>⚠️ Faltan " . count($missingFiles) . " archivos críticos</div>";
}
echo "</div>";

// 2. VERIFICAR BASE DE DATOS
echo "<div class='section'>";
echo "<h2>🗄️ 2. Verificación de Base de Datos</h2>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "<span class='success'>✅ Conexión a BD exitosa</span><br>";
    
    // Verificar tablas necesarias
    $requiredTables = [
        'user_groups' => 'Tabla principal de grupos',
        'user_group_members' => 'Miembros de grupos',
        'users' => 'Tabla de usuarios'
    ];
    
    foreach ($requiredTables as $table => $desc) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            // Contar registros
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<span class='success'>✅ $table</span> ($desc) - $count registros<br>";
        } else {
            echo "<span class='error'>❌ $table</span> ($desc) - <strong>NO EXISTE</strong><br>";
        }
    }
    
    // Verificar estructura de user_groups
    echo "<h3>📋 Estructura de user_groups:</h3>";
    $stmt = $pdo->query("DESCRIBE user_groups");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Mostrar grupos existentes
    echo "<h3>👥 Grupos Existentes:</h3>";
    $stmt = $pdo->query("SELECT id, name, status, is_system_group, created_at FROM user_groups ORDER BY id");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($groups)) {
        echo "<div class='warning'>⚠️ No hay grupos en la base de datos</div>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Estado</th><th>Sistema</th><th>Fecha</th></tr>";
        foreach ($groups as $group) {
            echo "<tr>";
            echo "<td>" . $group['id'] . "</td>";
            echo "<td>" . htmlspecialchars($group['name']) . "</td>";
            echo "<td>" . $group['status'] . "</td>";
            echo "<td>" . ($group['is_system_group'] ? 'Sí' : 'No') . "</td>";
            echo "<td>" . $group['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error de BD: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 3. PROBAR FUNCIONALIDAD GET_GROUP_DETAILS
echo "<div class='section'>";
echo "<h2>🔧 3. Prueba de get_group_details.php</h2>";

if (file_exists('modules/groups/actions/get_group_details.php')) {
    echo "<span class='success'>✅ Archivo get_group_details.php existe</span><br>";
    
    // Mostrar contenido del archivo para debugging
    echo "<h3>📝 Contenido del archivo:</h3>";
    $content = file_get_contents('modules/groups/actions/get_group_details.php');
    $lines = explode("\n", $content);
    $previewLines = array_slice($lines, 0, 20); // Primeras 20 líneas
    
    echo "<div class='code'>";
    foreach ($previewLines as $i => $line) {
        $lineNum = $i + 1;
        echo sprintf("%02d: %s<br>", $lineNum, htmlspecialchars($line));
    }
    if (count($lines) > 20) {
        echo "... (+" . (count($lines) - 20) . " líneas más)\n";
    }
    echo "</div>";
    
    // Verificar sintaxis PHP
    $tempFile = tempnam(sys_get_temp_dir(), 'php_check');
    file_put_contents($tempFile, $content);
    
    $output = shell_exec("php -l $tempFile 2>&1");
    unlink($tempFile);
    
    if (strpos($output, 'No syntax errors') !== false) {
        echo "<span class='success'>✅ Sintaxis PHP correcta</span><br>";
    } else {
        echo "<span class='error'>❌ Error de sintaxis PHP:</span><br>";
        echo "<div class='code'>" . htmlspecialchars($output) . "</div>";
    }
    
} else {
    echo "<span class='error'>❌ Archivo get_group_details.php NO EXISTE</span><br>";
    echo "<span class='info'>💡 Necesitas crear este archivo en modules/groups/actions/</span><br>";
}
echo "</div>";

// 4. VERIFICAR PERMISOS Y SESIÓN
echo "<div class='section'>";
echo "<h2>🔐 4. Verificación de Sesión y Permisos</h2>";

if (file_exists('config/session.php')) {
    echo "<span class='success'>✅ config/session.php existe</span><br>";
    
    // Iniciar sesión si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['user_id'])) {
        echo "<span class='success'>✅ Sesión activa - Usuario ID: " . $_SESSION['user_id'] . "</span><br>";
        
        if (isset($_SESSION['role'])) {
            echo "<span class='info'>👤 Rol del usuario: " . $_SESSION['role'] . "</span><br>";
            
            if ($_SESSION['role'] === 'admin') {
                echo "<span class='success'>✅ Usuario tiene permisos de administrador</span><br>";
            } else {
                echo "<span class='warning'>⚠️ Usuario NO es administrador (requerido para grupos)</span><br>";
            }
        } else {
            echo "<span class='warning'>⚠️ Rol de usuario no definido</span><br>";
        }
    } else {
        echo "<span class='warning'>⚠️ No hay sesión activa</span><br>";
        echo "<span class='info'>💡 Necesitas loguearte primero: <a href='login.php'>login.php</a></span><br>";
    }
} else {
    echo "<span class='error'>❌ config/session.php NO EXISTE</span><br>";
}
echo "</div>";

// 5. PRUEBA ESPECÍFICA DEL ERROR
echo "<div class='section'>";
echo "<h2>🚨 5. Simulación del Error Reportado</h2>";

try {
    if (!isset($pdo)) {
        require_once 'config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
    }
    
    // Intentar obtener un grupo específico
    $testGroupId = 1; // Probar con el primer grupo
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_groups WHERE id = ?");
    $stmt->execute([$testGroupId]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if ($exists) {
        echo "<span class='success'>✅ Grupo ID $testGroupId existe en la BD</span><br>";
        
        // Intentar la consulta completa como en get_group_details.php
        $testQuery = "SELECT 
                        ug.*,
                        CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name,
                        COUNT(DISTINCT ugm.user_id) as total_members
                      FROM user_groups ug
                      LEFT JOIN users creator ON ug.created_by = creator.id
                      LEFT JOIN user_group_members ugm ON ug.id = ugm.group_id
                      WHERE ug.id = ?
                      GROUP BY ug.id";
        
        $stmt = $pdo->prepare($testQuery);
        $stmt->execute([$testGroupId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "<span class='success'>✅ Consulta de detalles exitosa</span><br>";
            echo "<span class='info'>📋 Datos obtenidos: " . json_encode($result, JSON_PRETTY_PRINT) . "</span><br>";
        } else {
            echo "<span class='error'>❌ La consulta no devolvió resultados</span><br>";
        }
        
    } else {
        echo "<span class='warning'>⚠️ No hay grupos para probar</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error en la prueba: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "<span class='info'>🔍 Este podría ser el problema reportado</span><br>";
}
echo "</div>";

// 6. RECOMENDACIONES FINALES
echo "<div class='section'>";
echo "<h2>💡 6. Recomendaciones para Solucionar</h2>";

echo "<h3>Si el error persiste:</h3>";
echo "<ol>";
echo "<li><strong>Verificar logs de error:</strong> Revisar el log de errores de Apache/PHP</li>";
echo "<li><strong>Recrear archivo:</strong> Si get_group_details.php tiene errores, usar el código corregido</li>";
echo "<li><strong>Verificar permisos:</strong> Asegurar que el usuario esté logueado como admin</li>";
echo "<li><strong>Probar consulta directa:</strong> Ejecutar la consulta SQL directamente en phpMyAdmin</li>";
echo "<li><strong>Revisar red:</strong> Usar F12 en el navegador para ver errores AJAX</li>";
echo "</ol>";

echo "<h3>Links útiles para debugging:</h3>";
echo "<ul>";
echo "<li><a href='modules/groups/index.php'>modules/groups/index.php</a> - Página principal</li>";
echo "<li><a href='login.php'>login.php</a> - Iniciar sesión</li>";
echo "<li><a href='phpinfo.php'>phpinfo.php</a> - Información de PHP (si existe)</li>";
echo "</ul>";

echo "</div>";

echo "<p><em>Diagnóstico completado: " . date('Y-m-d H:i:s') . "</em></p>";
?>