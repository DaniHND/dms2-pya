<?php
// verify_groups2_module.php - VERSIÓN CORREGIDA
// Script para verificar la instalación del módulo groups2

echo "<!DOCTYPE html>
<html>
<head>
    <title>Verificación - Módulo Groups2</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #059669; font-weight: bold; }
        .error { color: #dc2626; font-weight: bold; }
        .warning { color: #d97706; font-weight: bold; }
        .info { color: #0284c7; font-weight: bold; }
        .section { margin: 25px 0; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; }
        .header { text-align: center; color: #111827; margin-bottom: 30px; }
        .code { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; overflow-x: auto; }
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 15px 0; }
        .status-item { padding: 15px; background: white; border-radius: 6px; border-left: 4px solid #3b82f6; }
    </style>
</head>
<body>
<div class='container'>";

echo "<div class='header'>";
echo "<h1>🔍 Verificación del Módulo Groups2</h1>";
echo "<p>Sistema de gestión de grupos de usuarios para DMS2</p>";
echo "<hr>";
echo "</div>";

// 1. Verificar archivos principales
echo "<div class='section'>";
echo "<h2>📁 1. Estructura de Archivos</h2>";

$requiredFiles = [
    'modules/groups2/index.php' => 'Página principal del módulo',
    'modules/groups2/actions/create_group.php' => 'Crear nuevos grupos',
    'modules/groups2/actions/get_groups.php' => 'Listar grupos existentes',
    'modules/groups2/actions/get_group_details.php' => 'Obtener detalles de grupo',
    'modules/groups2/actions/update_group.php' => 'Actualizar grupos existentes',
    'modules/groups2/actions/toggle_group_status.php' => 'Cambiar estado de grupo',
    'modules/groups2/actions/manage_members.php' => 'Gestionar miembros de grupos',
    'assets/css/groups2.css' => 'Estilos del módulo',
    'assets/js/groups2.js' => 'Funcionalidad JavaScript'
];

$missingFiles = 0;
$totalFiles = count($requiredFiles);

echo "<div class='status-grid'>";
foreach ($requiredFiles as $file => $description) {
    echo "<div class='status-item'>";
    if (file_exists($file)) {
        $size = round(filesize($file) / 1024, 1);
        echo "<span class='success'>✅ $file</span><br>";
        echo "<small>$description</small><br>";
        echo "<small class='info'>Tamaño: {$size}KB</small>";
    } else {
        echo "<span class='error'>❌ $file</span><br>";
        echo "<small>$description</small><br>";
        echo "<small class='error'>ARCHIVO FALTANTE</small>";
        $missingFiles++;
    }
    echo "</div>";
}
echo "</div>";

if ($missingFiles === 0) {
    echo "<div class='success'>🎉 Todos los archivos están presentes! ($totalFiles/$totalFiles)</div>";
} else {
    echo "<div class='error'>⚠️ Faltan $missingFiles de $totalFiles archivos</div>";
}
echo "</div>";

// 2. Verificar base de datos - CORREGIDO para usar tablas existentes
echo "<div class='section'>";
echo "<h2>🗄️ 2. Verificación de Base de Datos</h2>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<div class='success'>✅ Conexión a base de datos exitosa</div><br>";
    
    // Verificar tablas EXISTENTES (no user_groups2)
    $tables = [
        'user_groups' => 'Tabla principal de grupos (EXISTENTE)',
        'user_group_members' => 'Relación usuario-grupo (EXISTENTE)',
        'users' => 'Tabla de usuarios (requerida)',
        'companies' => 'Tabla de empresas (para filtros)',
        'departments' => 'Tabla de departamentos (para filtros)'
    ];
    
    $tablesOk = 0;
    echo "<h3>Verificando tablas requeridas:</h3>";
    
    foreach ($tables as $table => $description) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "<span class='success'>✅ $table</span> - $description<br>";
            $tablesOk++;
        } else {
            echo "<span class='error'>❌ $table</span> - $description <strong>(FALTANTE)</strong><br>";
        }
    }
    
    if ($tablesOk === count($tables)) {
        echo "<br><div class='success'>🎉 Todas las tablas están presentes!</div>";
        
        // Estadísticas actuales
        echo "<h3>📊 Estadísticas Actuales:</h3>";
        
        $groupsCount = $conn->query("SELECT COUNT(*) FROM user_groups")->fetchColumn();
        $membersCount = $conn->query("SELECT COUNT(*) FROM user_group_members")->fetchColumn();
        $usersCount = $conn->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        $companiesCount = $conn->query("SELECT COUNT(*) FROM companies WHERE status = 'active'")->fetchColumn();
        
        echo "<div class='status-grid'>";
        echo "<div class='status-item'><strong>$groupsCount</strong><br><small>Grupos totales</small></div>";
        echo "<div class='status-item'><strong>$membersCount</strong><br><small>Asignaciones usuario-grupo</small></div>";
        echo "<div class='status-item'><strong>$usersCount</strong><br><small>Usuarios activos</small></div>";
        echo "<div class='status-item'><strong>$companiesCount</strong><br><small>Empresas activas</small></div>";
        echo "</div>";
        
        // Mostrar grupos existentes
        if ($groupsCount > 0) {
            echo "<h3>🏷️ Grupos Existentes:</h3>";
            $groups = $conn->query("
                SELECT g.id, g.name, g.status, g.is_system_group, COUNT(ugm.user_id) as member_count
                FROM user_groups g
                LEFT JOIN user_group_members ugm ON g.id = ugm.group_id
                GROUP BY g.id
                ORDER BY g.is_system_group DESC, g.name
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<div class='status-grid'>";
            foreach ($groups as $group) {
                $type = $group['is_system_group'] ? 'Sistema' : 'Personalizado';
                $status = $group['status'] === 'active' ? '🟢 Activo' : '🔴 Inactivo';
                $members = $group['member_count'];
                
                echo "<div class='status-item'>";
                echo "<strong>{$group['name']}</strong><br>";
                echo "<small>ID: {$group['id']} | $type</small><br>";
                echo "<small>Estado: $status</small><br>";
                echo "<small>Miembros: $members</small>";
                echo "</div>";
            }
            echo "</div>";
        }
    } else {
        echo "<br><div class='error'>⚠️ Faltan " . (count($tables) - $tablesOk) . " tablas en la base de datos</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error de conexión a la base de datos:</div>";
    echo "<div class='code'>" . htmlspecialchars($e->getMessage()) . "</div>";
}
echo "</div>";

// 3. Verificar dependencias del sistema - CORREGIDO
echo "<div class='section'>";
echo "<h2>🔐 3. Verificación de Dependencias del Sistema</h2>";

$dependencies = [
    'config/session.php' => 'Sistema de sesiones',
    'config/database.php' => 'Conexión a base de datos',
];

foreach ($dependencies as $file => $description) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file</span> - $description<br>";
    } else {
        echo "<span class='error'>❌ $file</span> - $description <strong>(FALTANTE)</strong><br>";
    }
}

// Verificar SessionManager (CORRECTO para tu sistema)
if (file_exists('config/session.php')) {
    try {
        require_once 'config/session.php';
        if (class_exists('SessionManager')) {
            echo "<span class='success'>✅ Clase SessionManager disponible</span><br>";
            
            $methods = ['requireRole', 'getCurrentUser', 'getUserRole', 'isLoggedIn'];
            foreach ($methods as $method) {
                if (method_exists('SessionManager', $method)) {
                    echo "<span class='success'>✅ Método SessionManager::$method() disponible</span><br>";
                } else {
                    echo "<span class='warning'>⚠️ Método SessionManager::$method() no encontrado</span><br>";
                }
            }
        } else {
            echo "<span class='error'>❌ Clase SessionManager no encontrada</span><br>";
        }
    } catch (Exception $e) {
        echo "<span class='warning'>⚠️ Error al verificar SessionManager: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
}

// Verificar Bootstrap y FontAwesome
echo "<br><h3>🎨 Dependencias del Frontend:</h3>";
echo "<span class='info'>ℹ️ Bootstrap 5.1.3 - CDN (se carga desde internet)</span><br>";
echo "<span class='info'>ℹ️ FontAwesome 6.0.0 - CDN (se carga desde internet)</span><br>";
echo "<span class='success'>✅ No se requieren archivos locales adicionales</span><br>";

echo "</div>";

// 4. URLs de prueba y comandos útiles
echo "<div class='section'>";
echo "<h2>🌐 4. URLs para Probar</h2>";

echo "<p><strong>Una vez que inicies sesión como administrador, prueba estas URLs:</strong></p>";
echo "<ul>";
echo "<li><a href='modules/groups2/index.php' target='_blank'>modules/groups2/index.php</a> - Página principal del módulo</li>";
echo "<li><a href='modules/groups2/actions/get_groups.php' target='_blank'>modules/groups2/actions/get_groups.php</a> - API para obtener grupos (requiere login)</li>";
echo "</ul>";

echo "<h3>📝 Para agregar al sidebar:</h3>";
echo "<div class='code'>";
echo htmlspecialchars('<?php if (SessionManager::getUserRole() === \'admin\'): ?>') . "<br>";
echo htmlspecialchars('    <li class="nav-item">') . "<br>";
echo htmlspecialchars('        <a href="modules/groups2/index.php" class="nav-link">') . "<br>";
echo htmlspecialchars('            <i class="fas fa-users"></i>') . "<br>";
echo htmlspecialchars('            <span>Gestión de Grupos</span>') . "<br>";
echo htmlspecialchars('        </a>') . "<br>";
echo htmlspecialchars('    </li>') . "<br>";
echo htmlspecialchars('<?php endif; ?>') . "<br>";
echo "</div>";

echo "<h3>📋 Comandos SQL útiles (CORREGIDOS):</h3>";
echo "<div class='code'>";
echo "-- Ver todos los grupos con estadísticas<br>";
echo "SELECT g.*, COUNT(ugm.user_id) as members<br>";
echo "FROM user_groups g<br>";
echo "LEFT JOIN user_group_members ugm ON g.id = ugm.group_id<br>";
echo "GROUP BY g.id ORDER BY g.name;<br><br>";

echo "-- Ver usuarios y sus grupos<br>";
echo "SELECT u.first_name, u.last_name, u.email, g.name as group_name<br>";
echo "FROM users u<br>";
echo "LEFT JOIN user_group_members ugm ON u.id = ugm.user_id<br>";
echo "LEFT JOIN user_groups g ON ugm.group_id = g.id<br>";
echo "WHERE u.status = 'active'<br>";
echo "ORDER BY u.first_name;<br><br>";

echo "-- Verificar permisos de un grupo específico<br>";
echo "SELECT name, module_permissions, access_restrictions<br>";
echo "FROM user_groups WHERE id = 1;<br>";
echo "</div>";
echo "</div>";

// 5. Próximos pasos - ACTUALIZADO
echo "<div class='section'>";
echo "<h2>🚀 5. Estado de la Instalación</h2>";

$allFilesPresent = ($missingFiles === 0);
$allTablesPresent = ($tablesOk === count($tables));
$sessionManagerOk = class_exists('SessionManager');

if ($allFilesPresent && $allTablesPresent && $sessionManagerOk) {
    echo "<div class='success'>";
    echo "<h3>🎉 ¡Módulo Completamente Funcional!</h3>";
    echo "<p>El módulo Groups2 está correctamente instalado y listo para usar.</p>";
    echo "<p><strong>✅ Todo está funcionando:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Todos los archivos PHP presentes</li>";
    echo "<li>✅ Base de datos con tablas existentes</li>";
    echo "<li>✅ Sistema de sesiones disponible</li>";
    echo "<li>✅ Grupos existentes listos para gestionar</li>";
    echo "</ul>";
    
    echo "<p><strong>🚀 Próximos pasos:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Agregar entrada al sidebar</strong> (código arriba)</li>";
    echo "<li><strong>Iniciar sesión como administrador</strong></li>";
    echo "<li><strong>Acceder a modules/groups2/index.php</strong></li>";
    echo "<li><strong>¡Comenzar a gestionar grupos!</strong></li>";
    echo "</ol>";
    echo "</div>";
    
} else {
    echo "<div class='warning'>";
    echo "<h3>⚠️ Instalación Casi Completa</h3>";
    echo "<p>Faltan algunos componentes menores:</p>";
    echo "<ul>";
    if (!$allFilesPresent) {
        echo "<li>$missingFiles archivos faltantes</li>";
    }
    if (!$allTablesPresent) {
        echo "<li>" . (count($tables) - $tablesOk) . " tablas faltantes en la base de datos</li>";
    }
    if (!$sessionManagerOk) {
        echo "<li>Sistema de sesiones no disponible</li>";
    }
    echo "</ul>";
    echo "</div>";
}
echo "</div>";

echo "<hr>";
echo "<div style='text-align: center; color: #6b7280; font-size: 0.9em;'>";
echo "<p><strong>Verificación completada:</strong> " . date('d/m/Y H:i:s') . "</p>";
echo "<p>Módulo Groups2 para DMS2 - Sistema de Gestión de Grupos de Usuarios</p>";
echo "<p><strong>Versión:</strong> 1.0.0 | <strong>Compatible con:</strong> Tu sistema DMS2 existente</p>";
echo "</div>";

echo "</div>";
echo "</body></html>";
?>