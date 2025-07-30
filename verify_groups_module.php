<?php
// verify_groups_module.php
// Script para verificar si existe la base de datos para el módulo de grupos

echo "<h1>🔍 Verificación Módulo de Grupos - DMS2</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
    .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .critical { background: #ffebee; border: 2px solid #f44336; padding: 15px; border-radius: 5px; margin: 15px 0; }
    .ready { background: #e8f5e8; border: 2px solid #4caf50; padding: 15px; border-radius: 5px; margin: 15px 0; }
</style>";

$dbStatus = [];
$missingItems = [];
$score = 0;
$totalChecks = 0;

// =========================================
// 1. VERIFICAR CONEXIÓN A LA BASE DE DATOS
// =========================================
echo "<div class='section'>";
echo "<h2>1. 🗄️ Conexión a Base de Datos</h2>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<span class='success'>✅ Conexión exitosa a base de datos</span><br>";
        echo "<span class='info'>📊 Base de datos conectada correctamente</span><br>";
        $dbStatus['connection'] = true;
    } else {
        echo "<span class='error'>❌ Error de conexión a base de datos</span><br>";
        echo "<span class='critical'>🚨 No se puede continuar sin conexión a la base de datos</span><br>";
        exit();
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    exit();
}
echo "</div>";

// =========================================
// 2. VERIFICAR TABLAS PRINCIPALES
// =========================================
echo "<div class='section'>";
echo "<h2>2. 📋 Verificación de Tablas Principales</h2>";

$requiredTables = [
    'user_groups' => 'Tabla principal de grupos de usuarios',
    'user_group_members' => 'Tabla de relación usuario-grupo',
    'users' => 'Tabla de usuarios (requerida para relaciones)',
    'companies' => 'Tabla de empresas (requerida para filtros)',
    'departments' => 'Tabla de departamentos (requerida para filtros)',
    'documents' => 'Tabla de documentos (requerida para permisos)',
    'document_types' => 'Tabla de tipos de documentos (requerida para filtros)'
];

foreach ($requiredTables as $table => $description) {
    $totalChecks++;
    try {
        $query = "SHOW TABLES LIKE '$table'";
        $result = $conn->query($query);
        
        if ($result && $result->rowCount() > 0) {
            echo "<span class='success'>✅ $table</span> - $description<br>";
            $dbStatus[$table] = true;
            $score++;
        } else {
            echo "<span class='error'>❌ $table</span> - $description <strong>(FALTA)</strong><br>";
            $dbStatus[$table] = false;
            $missingItems[] = "Tabla faltante: $table";
        }
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error verificando tabla '$table': " . htmlspecialchars($e->getMessage()) . "</span><br>";
        $dbStatus[$table] = false;
        $missingItems[] = "Error en tabla: $table";
    }
}
echo "</div>";

// =========================================
// 3. VERIFICAR ESTRUCTURA DE TABLA user_groups
// =========================================
echo "<div class='section'>";
echo "<h2>3. 🏗️ Estructura de user_groups</h2>";

if ($dbStatus['user_groups'] ?? false) {
    try {
        $columns = $conn->query("DESCRIBE user_groups")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        
        echo "<h3>Columnas existentes:</h3>";
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Clave</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar columnas críticas para grupos avanzados
        $criticalColumns = [
            'id' => 'ID principal',
            'name' => 'Nombre del grupo',
            'description' => 'Descripción',
            'allowed_companies' => 'Empresas permitidas (JSON)',
            'allowed_departments' => 'Departamentos permitidos (JSON)',
            'allowed_document_types' => 'Tipos de documentos permitidos (JSON)',
            'permissions' => 'Permisos del grupo (JSON)',
            'status' => 'Estado del grupo',
            'created_at' => 'Fecha de creación',
            'created_by' => 'Creado por'
        ];
        
        echo "<h3>Verificación de columnas críticas:</h3>";
        foreach ($criticalColumns as $col => $desc) {
            $totalChecks++;
            if (in_array($col, $columnNames)) {
                echo "<span class='success'>✅ $col</span> - $desc<br>";
                $score++;
            } else {
                echo "<span class='error'>❌ $col</span> - $desc <strong>(FALTA)</strong><br>";
                $missingItems[] = "Columna faltante en user_groups: $col";
            }
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error verificando estructura: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
} else {
    echo "<span class='warning'>⚠️ No se puede verificar estructura - tabla user_groups no existe</span><br>";
}
echo "</div>";

// =========================================
// 4. VERIFICAR DATOS EXISTENTES
// =========================================
echo "<div class='section'>";
echo "<h2>4. 📊 Datos Existentes</h2>";

if ($dbStatus['user_groups'] ?? false) {
    try {
        // Contar grupos
        $groupCount = $conn->query("SELECT COUNT(*) FROM user_groups")->fetchColumn();
        echo "<span class='info'>📋 Total de grupos: $groupCount</span><br>";
        
        if ($groupCount > 0) {
            // Mostrar grupos existentes
            $groups = $conn->query("SELECT id, name, description, status, created_at FROM user_groups ORDER BY name LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            echo "<h3>Grupos existentes:</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Creado</th></tr>";
            foreach ($groups as $group) {
                echo "<tr>";
                echo "<td>" . $group['id'] . "</td>";
                echo "<td>" . htmlspecialchars($group['name']) . "</td>";
                echo "<td>" . htmlspecialchars(substr($group['description'] ?? '', 0, 50)) . (strlen($group['description'] ?? '') > 50 ? '...' : '') . "</td>";
                echo "<td>" . htmlspecialchars($group['status']) . "</td>";
                echo "<td>" . date('d/m/Y', strtotime($group['created_at'])) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Estadísticas por estado
            $stats = $conn->query("SELECT status, COUNT(*) as count FROM user_groups GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
            echo "<h3>Estadísticas:</h3>";
            foreach ($stats as $stat) {
                echo "<span class='info'>• {$stat['status']}: {$stat['count']} grupos</span><br>";
            }
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error obteniendo datos: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
}

if ($dbStatus['user_group_members'] ?? false) {
    try {
        $memberCount = $conn->query("SELECT COUNT(*) FROM user_group_members")->fetchColumn();
        echo "<span class='info'>👥 Total de asignaciones usuario-grupo: $memberCount</span><br>";
    } catch (Exception $e) {
        echo "<span class='warning'>⚠️ Error contando miembros de grupos</span><br>";
    }
}
echo "</div>";

// =========================================
// 5. VERIFICAR FUNCIONES Y VISTAS
// =========================================
echo "<div class='section'>";
echo "<h2>5. ⚙️ Funciones y Vistas del Sistema</h2>";

$dbObjects = [
    'user_access_summary' => 'Vista de resumen de acceso de usuarios',
    'group_stats' => 'Vista de estadísticas de grupos'
];

foreach ($dbObjects as $object => $description) {
    $totalChecks++;
    try {
        // Verificar vistas
        $query = "SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_" . $conn->query("SELECT DATABASE()")->fetchColumn() . " = '$object'";
        $result = $conn->query($query);
        
        if ($result && $result->rowCount() > 0) {
            echo "<span class='success'>✅ $object</span> - $description (Vista)<br>";
            $score++;
        } else {
            echo "<span class='error'>❌ $object</span> - $description <strong>(FALTA)</strong><br>";
            $missingItems[] = "Vista faltante: $object";
        }
    } catch (Exception $e) {
        echo "<span class='warning'>⚠️ No se pudo verificar $object</span><br>";
    }
}

// Verificar funciones
$functions = [
    'user_has_permission' => 'Función para verificar permisos',
    'user_has_module_access' => 'Función para verificar acceso a módulos',
    'user_can_access_document' => 'Función para verificar acceso a documentos'
];

foreach ($functions as $function => $description) {
    $totalChecks++;
    try {
        $query = "SHOW FUNCTION STATUS WHERE Db = DATABASE() AND Name = '$function'";
        $result = $conn->query($query);
        
        if ($result && $result->rowCount() > 0) {
            echo "<span class='success'>✅ $function</span> - $description (Función)<br>";
            $score++;
        } else {
            echo "<span class='error'>❌ $function</span> - $description <strong>(FALTA)</strong><br>";
            $missingItems[] = "Función faltante: $function";
        }
    } catch (Exception $e) {
        echo "<span class='warning'>⚠️ No se pudo verificar función $function</span><br>";
    }
}
echo "</div>";

// =========================================
// 6. VERIFICAR ARCHIVOS DEL MÓDULO
// =========================================
echo "<div class='section'>";
echo "<h2>6. 📁 Archivos del Módulo</h2>";

$moduleFiles = [
    'modules/groups/index.php' => 'Página principal del módulo',
    'modules/groups/actions/create_group.php' => 'Crear grupo',
    'modules/groups/actions/get_group_details.php' => 'Obtener detalles',
    'modules/groups/actions/update_group.php' => 'Actualizar grupo',
    'modules/groups/actions/toggle_group_status.php' => 'Cambiar estado',
    'modules/groups/actions/assign_users.php' => 'Asignar usuarios',
    'assets/css/groups.css' => 'Estilos del módulo',
    'assets/js/groups.js' => 'JavaScript del módulo'
];

$missingFiles = [];
foreach ($moduleFiles as $file => $description) {
    $totalChecks++;
    if (file_exists($file)) {
        $size = round(filesize($file) / 1024, 2);
        echo "<span class='success'>✅ $file</span> - $description ($size KB)<br>";
        $score++;
    } else {
        echo "<span class='error'>❌ $file</span> - $description <strong>(FALTA)</strong><br>";
        $missingFiles[] = $file;
        $missingItems[] = "Archivo faltante: $file";
    }
}
echo "</div>";

// =========================================
// 7. VERIFICAR SIDEBAR
// =========================================
echo "<div class='section'>";
echo "<h2>7. 🧩 Verificación del Sidebar</h2>";

$totalChecks++;
if (file_exists('includes/sidebar.php')) {
    $sidebarContent = file_get_contents('includes/sidebar.php');
    if (strpos($sidebarContent, 'groups') !== false) {
        echo "<span class='success'>✅ Entrada de grupos encontrada en sidebar</span><br>";
        $score++;
    } else {
        echo "<span class='warning'>⚠️ Entrada de grupos NO encontrada en sidebar</span><br>";
        $missingItems[] = "Falta entrada de grupos en sidebar";
    }
} else {
    echo "<span class='error'>❌ Archivo sidebar.php no encontrado</span><br>";
    $missingItems[] = "Archivo sidebar.php faltante";
}
echo "</div>";

// =========================================
// 8. RESUMEN Y RECOMENDACIONES
// =========================================
echo "<div class='section'>";
echo "<h2>8. 📊 Resumen Final</h2>";

$percentage = round(($score / $totalChecks) * 100, 1);

if ($percentage >= 90) {
    echo "<div class='ready'>";
    echo "<h3>🎉 ¡MÓDULO LISTO!</h3>";
    echo "<p>Puntuación: $score/$totalChecks ($percentage%)</p>";
    echo "<p>El módulo de grupos está listo para usar o necesita ajustes menores.</p>";
    echo "</div>";
} elseif ($percentage >= 60) {
    echo "<div class='warning'>";
    echo "<h3>⚠️ MÓDULO PARCIALMENTE LISTO</h3>";
    echo "<p>Puntuación: $score/$totalChecks ($percentage%)</p>";
    echo "<p>Faltan algunos componentes importantes.</p>";
    echo "</div>";
} else {
    echo "<div class='critical'>";
    echo "<h3>🚨 MÓDULO NO LISTO</h3>";
    echo "<p>Puntuación: $score/$totalChecks ($percentage%)</p>";
    echo "<p>Se necesita implementar la mayoría de componentes.</p>";
    echo "</div>";
}

// Mostrar elementos faltantes
if (!empty($missingItems)) {
    echo "<h3>🚨 Elementos Faltantes:</h3>";
    echo "<ol>";
    foreach ($missingItems as $item) {
        echo "<li>$item</li>";
    }
    echo "</ol>";
}

echo "<h3>🎯 Próximos Pasos:</h3>";
if ($percentage < 50) {
    echo "<div class='critical'>";
    echo "<h4>PRIORIDAD ALTA - Crear estructura base:</h4>";
    echo "<ol>";
    echo "<li><strong>Ejecutar SQL base</strong> - Crear tablas user_groups y user_group_members</li>";
    echo "<li><strong>Crear directorios</strong> - mkdir modules/groups modules/groups/actions</li>";
    echo "<li><strong>Implementar archivos PHP básicos</strong> - index.php y acciones principales</li>";
    echo "</ol>";
    echo "</div>";
} elseif ($percentage < 80) {
    echo "<div class='warning'>";
    echo "<h4>COMPLETAR IMPLEMENTACIÓN:</h4>";
    echo "<ol>";
    echo "<li><strong>Crear archivos faltantes</strong> - " . count($missingFiles) . " archivos por crear</li>";
    echo "<li><strong>Implementar funciones avanzadas</strong> - Vistas y funciones SQL</li>";
    echo "<li><strong>Integrar con otros módulos</strong> - Aplicar filtros en documentos</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='ready'>";
    echo "<h4>FINALIZAR DETALLES:</h4>";
    echo "<ol>";
    echo "<li><strong>Probar funcionalidades</strong> - Crear grupos y asignar usuarios</li>";
    echo "<li><strong>Verificar permisos</strong> - Probar filtros en módulo de archivos</li>";
    echo "<li><strong>Optimizar rendimiento</strong> - Ajustar consultas y índices</li>";
    echo "</ol>";
    echo "</div>";
}
echo "</div>";

// =========================================
// 9. COMANDOS ÚTILES
// =========================================
echo "<div class='section'>";
echo "<h2>9. 🔧 Comandos Útiles</h2>";

echo "<h3>Para crear estructura básica:</h3>";
echo "<div class='code'>";
echo "-- Crear directorios<br>";
echo "mkdir -p modules/groups/actions<br><br>";

echo "-- SQL básico para empezar<br>";
echo "CREATE TABLE user_groups (<br>";
echo "&nbsp;&nbsp;id INT AUTO_INCREMENT PRIMARY KEY,<br>";
echo "&nbsp;&nbsp;name VARCHAR(150) NOT NULL UNIQUE,<br>";
echo "&nbsp;&nbsp;description TEXT,<br>";
echo "&nbsp;&nbsp;permissions JSON DEFAULT '{}',<br>";
echo "&nbsp;&nbsp;status ENUM('active','inactive') DEFAULT 'active',<br>";
echo "&nbsp;&nbsp;created_by INT NOT NULL,<br>";
echo "&nbsp;&nbsp;created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP<br>";
echo ");<br>";
echo "</div>";

echo "<h3>Para verificar después de implementar:</h3>";
echo "<div class='code'>";
echo "-- Verificar grupos creados<br>";
echo "SELECT * FROM user_groups;<br><br>";
echo "-- Verificar asignaciones<br>";
echo "SELECT u.username, g.name FROM users u<br>";
echo "JOIN user_group_members ugm ON u.id = ugm.user_id<br>";
echo "JOIN user_groups g ON ugm.group_id = g.id;<br>";
echo "</div>";

echo "<h3>URLs para probar:</h3>";
echo "<ul>";
echo "<li><a href='modules/groups/index.php' target='_blank'>modules/groups/index.php</a> - Página principal</li>";
echo "<li><a href='dashboard.php' target='_blank'>dashboard.php</a> - Verificar estadísticas</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><strong>Verificación completada:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Estado actual:</strong> $percentage% completado</p>";

if ($percentage >= 80) {
    echo "<p style='color: green; font-weight: bold;'>✅ ¡Módulo de Grupos casi listo para usar!</p>";
} elseif ($percentage >= 50) {
    echo "<p style='color: orange; font-weight: bold;'>⚠️ Módulo de Grupos en desarrollo - Faltan algunos componentes</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>🚨 Módulo de Grupos necesita implementación completa</p>";
}
?>