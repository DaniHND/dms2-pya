<?php
/*
 * setup_groups_module.php
 * Script completo de instalación del módulo de Grupos
 * Ejecutar desde la raíz del proyecto: php setup_groups_module.php
 */

// Configuración de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🚀 INSTALADOR DEL MÓDULO DE GRUPOS - DMS2\n";
echo "==========================================\n\n";

// Verificar que se ejecute desde línea de comandos
if (php_sapi_name() !== 'cli') {
    echo "❌ Este script debe ejecutarse desde la línea de comandos\n";
    echo "Uso: php setup_groups_module.php\n";
    exit(1);
}

// Incluir configuración de base de datos
if (!file_exists('config/database.php')) {
    echo "❌ No se encontró config/database.php\n";
    echo "Asegúrate de ejecutar este script desde la raíz del proyecto DMS2\n";
    exit(1);
}

require_once 'config/database.php';

// Variables de control
$errors = [];
$warnings = [];
$success = [];

// Función helper para mostrar progreso
function showProgress($message, $status = 'info') {
    $icons = [
        'info' => 'ℹ️',
        'success' => '✅',
        'warning' => '⚠️',
        'error' => '❌'
    ];
    
    echo $icons[$status] . " $message\n";
}

// 1. VERIFICAR CONEXIÓN A BASE DE DATOS
showProgress("Verificando conexión a base de datos...", 'info');

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo establecer conexión");
    }
    
    showProgress("Conexión a base de datos exitosa", 'success');
    $success[] = "Conexión a base de datos establecida";
    
} catch (Exception $e) {
    showProgress("Error de conexión: " . $e->getMessage(), 'error');
    $errors[] = "Conexión a base de datos falló: " . $e->getMessage();
    exit(1);
}

// 2. CREAR ESTRUCTURA DE DIRECTORIOS
showProgress("Creando estructura de directorios...", 'info');

$directories = [
    'modules/groups',
    'modules/groups/actions',
    'assets/css',
    'assets/js'
];

$createdDirs = 0;
$existingDirs = 0;

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            showProgress("Directorio creado: $dir", 'success');
            $createdDirs++;
        } else {
            showProgress("Error al crear directorio: $dir", 'error');
            $errors[] = "No se pudo crear directorio: $dir";
        }
    } else {
        showProgress("Directorio ya existe: $dir", 'warning');
        $existingDirs++;
    }
}

$success[] = "Estructura de directorios: $createdDirs creados, $existingDirs existentes";

// 3. VERIFICAR TABLAS RELACIONADAS
showProgress("Verificando tablas relacionadas...", 'info');

$requiredTables = [
    'users' => 'Tabla de usuarios',
    'companies' => 'Tabla de empresas', 
    'departments' => 'Tabla de departamentos',
    'documents' => 'Tabla de documentos',
    'document_types' => 'Tabla de tipos de documentos',
    'activity_logs' => 'Tabla de logs de actividad'
];

$missingTables = [];

foreach ($requiredTables as $table => $description) {
    try {
        $query = "SHOW TABLES LIKE '$table'";
        $result = $pdo->query($query);
        
        if ($result && $result->rowCount() > 0) {
            showProgress("Tabla encontrada: $table", 'success');
        } else {
            showProgress("Tabla faltante: $table - $description", 'warning');
            $missingTables[] = $table;
        }
    } catch (Exception $e) {
        showProgress("Error verificando tabla $table: " . $e->getMessage(), 'error');
        $errors[] = "Error verificando tabla $table";
    }
}

if (!empty($missingTables)) {
    $warnings[] = "Tablas faltantes: " . implode(', ', $missingTables);
    showProgress("ADVERTENCIA: Algunas tablas relacionadas no existen", 'warning');
    showProgress("El módulo puede no funcionar correctamente sin estas tablas", 'warning');
}

// 4. CREAR TABLAS DEL MÓDULO DE GRUPOS
showProgress("Creando tablas del módulo de grupos...", 'info');

try {
    // Verificar si las tablas ya existen
    $groupsTableExists = $pdo->query("SHOW TABLES LIKE 'user_groups'")->rowCount() > 0;
    $membersTableExists = $pdo->query("SHOW TABLES LIKE 'user_group_members'")->rowCount() > 0;
    
    if ($groupsTableExists && $membersTableExists) {
        showProgress("Las tablas del módulo ya existen", 'warning');
        $warnings[] = "Tablas user_groups y user_group_members ya existen";
    } else {
        // Leer y ejecutar el SQL de creación
        $sqlFile = __DIR__ . '/sql/groups_module.sql';
        
        // Si no existe el archivo SQL, crearlo inline
        if (!file_exists($sqlFile)) {
            showProgress("Creando SQL inline para tablas...", 'info');
            
            // SQL para crear tablas (versión simplificada)
            $createTablesSQL = "
            CREATE TABLE IF NOT EXISTS user_groups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL UNIQUE,
                description TEXT,
                module_permissions JSON DEFAULT '{}',
                access_restrictions JSON DEFAULT '{}',
                download_limit_daily INT DEFAULT NULL,
                upload_limit_daily INT DEFAULT NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                is_system_group BOOLEAN DEFAULT FALSE,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_created_by (created_by),
                INDEX idx_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            CREATE TABLE IF NOT EXISTS user_group_members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                group_id INT NOT NULL,
                assigned_by INT NOT NULL,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_group (user_id, group_id),
                INDEX idx_user_id (user_id),
                INDEX idx_group_id (group_id),
                INDEX idx_assigned_by (assigned_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            // Ejecutar SQL
            $pdo->exec($createTablesSQL);
            showProgress("Tablas principales creadas", 'success');
            
            // Crear vistas
            $createViewsSQL = "
            CREATE OR REPLACE VIEW user_access_summary AS
            SELECT 
                u.id as user_id,
                u.username,
                u.first_name,
                u.last_name,
                u.company_id,
                u.department_id,
                GROUP_CONCAT(DISTINCT ug.name ORDER BY ug.name SEPARATOR ', ') as groups,
                GROUP_CONCAT(DISTINCT ug.id ORDER BY ug.id) as group_ids,
                COUNT(DISTINCT ugm.group_id) as total_groups,
                CASE 
                    WHEN u.status = 'active' AND COUNT(ugm.group_id) > 0 THEN 'active_with_groups'
                    WHEN u.status = 'active' AND COUNT(ugm.group_id) = 0 THEN 'active_no_groups'
                    ELSE u.status
                END as access_status
            FROM users u
            LEFT JOIN user_group_members ugm ON u.id = ugm.user_id
            LEFT JOIN user_groups ug ON ugm.group_id = ug.id AND ug.status = 'active'
            WHERE u.status != 'deleted'
            GROUP BY u.id, u.username, u.first_name, u.last_name, u.company_id, u.department_id;
            
            CREATE OR REPLACE VIEW group_stats AS
            SELECT 
                ug.id,
                ug.name,
                ug.description,
                ug.status,
                ug.is_system_group,
                ug.module_permissions,
                ug.access_restrictions,
                ug.download_limit_daily,
                ug.upload_limit_daily,
                COUNT(DISTINCT ugm.user_id) as total_members,
                COUNT(DISTINCT CASE WHEN u.status = 'active' THEN ugm.user_id END) as active_members,
                COUNT(DISTINCT u.company_id) as companies_represented,
                COUNT(DISTINCT u.department_id) as departments_represented,
                ug.created_at,
                CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name
            FROM user_groups ug
            LEFT JOIN user_group_members ugm ON ug.id = ugm.group_id
            LEFT JOIN users u ON ugm.user_id = u.id AND u.status != 'deleted'
            LEFT JOIN users creator ON ug.created_by = creator.id
            GROUP BY ug.id, ug.name, ug.description, ug.status, ug.is_system_group, ug.created_at, creator.first_name, creator.last_name;
            ";
            
            $pdo->exec($createViewsSQL);
            showProgress("Vistas creadas", 'success');
            
            $success[] = "Tablas y vistas del módulo creadas exitosamente";
        }
    }
    
} catch (Exception $e) {
    showProgress("Error creando tablas: " . $e->getMessage(), 'error');
    $errors[] = "Error creando tablas: " . $e->getMessage();
}

// 5. INSERTAR GRUPOS PREDEFINIDOS
showProgress("Insertando grupos predefinidos...", 'info');

try {
    // Verificar si ya existen grupos
    $existingGroups = $pdo->query("SELECT COUNT(*) as count FROM user_groups")->fetch()['count'];
    
    if ($existingGroups > 0) {
        showProgress("Ya existen $existingGroups grupos en el sistema", 'warning');
        $warnings[] = "Grupos predefinidos ya pueden existir";
    } else {
        // Insertar grupos predefinidos
        $defaultGroups = [
            [
                'name' => 'Super Administradores',
                'description' => 'Acceso completo a todos los módulos y funcionalidades del sistema',
                'permissions' => json_encode([
                    'users' => ['read' => true, 'write' => true, 'delete' => true],
                    'companies' => ['read' => true, 'write' => true, 'delete' => true],
                    'departments' => ['read' => true, 'write' => true, 'delete' => true],
                    'documents' => ['read' => true, 'write' => true, 'delete' => true, 'download' => true],
                    'groups' => ['read' => true, 'write' => true, 'delete' => true],
                    'reports' => ['read' => true, 'write' => true]
                ]),
                'restrictions' => json_encode([
                    'companies' => 'all',
                    'departments' => 'all',
                    'document_types' => 'all'
                ])
            ],
            [
                'name' => 'Gerentes Generales',
                'description' => 'Acceso de gestión a múltiples empresas y departamentos',
                'permissions' => json_encode([
                    'users' => ['read' => true, 'write' => true, 'delete' => false],
                    'companies' => ['read' => true, 'write' => true, 'delete' => false],
                    'departments' => ['read' => true, 'write' => true, 'delete' => false],
                    'documents' => ['read' => true, 'write' => true, 'delete' => false, 'download' => true],
                    'reports' => ['read' => true, 'write' => false]
                ]),
                'restrictions' => json_encode([
                    'companies' => 'user_assigned',
                    'departments' => 'all',
                    'document_types' => 'all'
                ])
            ],
            [
                'name' => 'Empleados Estándar',
                'description' => 'Acceso básico para empleados regulares',
                'permissions' => json_encode([
                    'documents' => ['read' => true, 'write' => true, 'delete' => false, 'download' => true],
                    'reports' => ['read' => true, 'write' => false]
                ]),
                'restrictions' => json_encode([
                    'companies' => 'user_company',
                    'departments' => 'user_department',
                    'document_types' => [1, 2, 3, 4, 5]
                ])
            ]
        ];
        
        $insertQuery = "INSERT INTO user_groups (name, description, module_permissions, access_restrictions, created_by, is_system_group) 
                       VALUES (?, ?, ?, ?, 1, TRUE)";
        
        $stmt = $pdo->prepare($insertQuery);
        $insertedGroups = 0;
        
        foreach ($defaultGroups as $group) {
            try {
                $stmt->execute([
                    $group['name'],
                    $group['description'],
                    $group['permissions'],
                    $group['restrictions']
                ]);
                showProgress("Grupo creado: " . $group['name'], 'success');
                $insertedGroups++;
            } catch (Exception $e) {
                showProgress("Error creando grupo '" . $group['name'] . "': " . $e->getMessage(), 'warning');
                $warnings[] = "No se pudo crear grupo predefinido: " . $group['name'];
            }
        }
        
        $success[] = "Grupos predefinidos creados: $insertedGroups";
    }
    
} catch (Exception $e) {
    showProgress("Error insertando grupos predefinidos: " . $e->getMessage(), 'error');
    $errors[] = "Error insertando grupos predefinidos: " . $e->getMessage();
}

// 6. VERIFICAR ARCHIVOS NECESARIOS
showProgress("Verificando archivos del módulo...", 'info');

$moduleFiles = [
    'modules/groups/index.php' => 'Archivo principal del módulo',
    'modules/groups/actions/create_group.php' => 'Acción crear grupo',
    'modules/groups/actions/get_group_details.php' => 'Acción obtener detalles',
    'modules/groups/actions/update_group.php' => 'Acción actualizar grupo',
    'modules/groups/actions/toggle_group_status.php' => 'Acción cambiar estado',
    'modules/groups/actions/manage_group_users.php' => 'Acción gestionar usuarios',
    'modules/groups/actions/get_group_users.php' => 'Acción obtener usuarios',
    'assets/css/groups.css' => 'Estilos del módulo',
    'assets/js/groups.js' => 'JavaScript del módulo'
];

$existingFiles = 0;
$missingFiles = 0;

foreach ($moduleFiles as $file => $description) {
    if (file_exists($file)) {
        showProgress("Archivo encontrado: $file", 'success');
        $existingFiles++;
    } else {
        showProgress("Archivo faltante: $file - $description", 'warning');
        $missingFiles++;
    }
}

if ($missingFiles > 0) {
    $warnings[] = "Archivos faltantes: $missingFiles de " . count($moduleFiles);
    showProgress("ADVERTENCIA: Algunos archivos del módulo no están presentes", 'warning');
    showProgress("Necesitarás crear manualmente los archivos faltantes", 'warning');
} else {
    $success[] = "Todos los archivos del módulo están presentes";
}

// 7. VERIFICAR INTEGRACIÓN CON SIDEBAR
showProgress("Verificando integración del sidebar...", 'info');

$sidebarFiles = [
    'includes/sidebar.php',
    'config/sidebar.php',
    'templates/sidebar.php'
];

$sidebarFound = false;
$sidebarFile = null;

foreach ($sidebarFiles as $file) {
    if (file_exists($file)) {
        $sidebarFound = true;
        $sidebarFile = $file;
        break;
    }
}

if ($sidebarFound) {
    showProgress("Archivo de sidebar encontrado: $sidebarFile", 'success');
    
    // Verificar si ya tiene entrada de grupos
    $sidebarContent = file_get_contents($sidebarFile);
    if (strpos($sidebarContent, 'groups') !== false || strpos($sidebarContent, 'Grupos') !== false) {
        showProgress("Entrada de grupos ya existe en el sidebar", 'success');
        $success[] = "Integración del sidebar ya configurada";
    } else {
        showProgress("Entrada de grupos no encontrada en sidebar", 'warning');
        $warnings[] = "Necesitas agregar entrada de grupos al sidebar manualmente";
        
        echo "\n📝 CÓDIGO PARA AGREGAR AL SIDEBAR:\n";
        echo "================================\n";
        echo '<li class="nav-item">' . "\n";
        echo '    <a href="modules/groups/index.php" class="nav-link">' . "\n";
        echo '        <i class="fas fa-users-cog nav-icon"></i>' . "\n";
        echo '        <p>Grupos</p>' . "\n";
        echo '    </a>' . "\n";
        echo '</li>' . "\n\n";
    }
} else {
    showProgress("No se encontró archivo de sidebar", 'warning');
    $warnings[] = "No se pudo verificar integración del sidebar";
}

// 8. VERIFICAR PERMISOS DE ARCHIVOS
showProgress("Verificando permisos de archivos...", 'info');

$directories = ['modules/groups', 'modules/groups/actions', 'assets/css', 'assets/js'];
$permissionIssues = 0;

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            showProgress("Permisos OK: $dir", 'success');
        } else {
            showProgress("Permisos insuficientes: $dir", 'warning');
            $permissionIssues++;
        }
    }
}

if ($permissionIssues > 0) {
    $warnings[] = "Problemas de permisos en $permissionIssues directorios";
    showProgress("ADVERTENCIA: Algunos directorios pueden tener permisos insuficientes", 'warning');
    showProgress("Ejecuta: chmod -R 755 modules/groups assets/", 'info');
}

// 9. CREAR DATOS DE PRUEBA (OPCIONAL)
showProgress("¿Crear datos de prueba? (y/n): ", 'info');
$createTestData = trim(fgets(STDIN));

if (strtolower($createTestData) === 'y' || strtolower($createTestData) === 'yes') {
    showProgress("Creando datos de prueba...", 'info');
    
    try {
        // Obtener primer usuario admin
        $adminQuery = "SELECT id FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1";
        $admin = $pdo->query($adminQuery)->fetch();
        
        if ($admin) {
            // Asignar admin al grupo Super Administradores
            $superAdminGroupQuery = "SELECT id FROM user_groups WHERE name = 'Super Administradores' LIMIT 1";
            $superAdminGroup = $pdo->query($superAdminGroupQuery)->fetch();
            
            if ($superAdminGroup) {
                $assignQuery = "INSERT IGNORE INTO user_group_members (user_id, group_id, assigned_by) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($assignQuery);
                $stmt->execute([$admin['id'], $superAdminGroup['id'], $admin['id']]);
                
                showProgress("Usuario admin asignado al grupo Super Administradores", 'success');
                $success[] = "Datos de prueba creados";
            }
        } else {
            showProgress("No se encontró usuario admin para datos de prueba", 'warning');
            $warnings[] = "No se pudieron crear datos de prueba";
        }
        
    } catch (Exception $e) {
        showProgress("Error creando datos de prueba: " . $e->getMessage(), 'error');
        $errors[] = "Error creando datos de prueba";
    }
}

// 10. VERIFICACIÓN FINAL
showProgress("Ejecutando verificación final...", 'info');

try {
    // Verificar que las tablas funcionan correctamente
    $testQuery = "SELECT COUNT(*) as count FROM user_groups";
    $result = $pdo->query($testQuery)->fetch();
    $groupCount = $result['count'];
    
    $testQuery2 = "SELECT COUNT(*) as count FROM user_group_members";
    $result2 = $pdo->query($testQuery2)->fetch();
    $memberCount = $result2['count'];
    
    showProgress("Grupos en sistema: $groupCount", 'success');
    showProgress("Asignaciones de usuarios: $memberCount", 'success');
    
    // Verificar vistas
    $viewQuery = "SELECT COUNT(*) as count FROM user_access_summary";
    $viewResult = $pdo->query($viewQuery)->fetch();
    showProgress("Vista user_access_summary funcional: " . $viewResult['count'] . " registros", 'success');
    
    $success[] = "Verificación final exitosa";
    
} catch (Exception $e) {
    showProgress("Error en verificación final: " . $e->getMessage(), 'error');
    $errors[] = "Error en verificación final";
}

// MOSTRAR RESUMEN FINAL
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 RESUMEN DE INSTALACIÓN\n";
echo str_repeat("=", 50) . "\n\n";

echo "✅ ÉXITOS (" . count($success) . "):\n";
foreach ($success as $item) {
    echo "   • $item\n";
}

if (!empty($warnings)) {
    echo "\n⚠️  ADVERTENCIAS (" . count($warnings) . "):\n";
    foreach ($warnings as $item) {
        echo "   • $item\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ ERRORES (" . count($errors) . "):\n";
    foreach ($errors as $item) {
        echo "   • $item\n";
    }
}

// PRÓXIMOS PASOS
echo "\n🎯 PRÓXIMOS PASOS:\n";
echo "=================\n";

if (empty($errors)) {
    echo "1. ✅ Accede a: http://tu-dominio/dms2/modules/groups/index.php\n";
    echo "2. ✅ Verifica que el módulo carga correctamente\n";
    echo "3. ✅ Crea tus primeros grupos personalizados\n";
    echo "4. ✅ Asigna usuarios a los grupos\n";
    echo "5. ✅ Prueba los permisos y restricciones\n";
} else {
    echo "1. ❌ Corrige los errores reportados arriba\n";
    echo "2. ❌ Vuelve a ejecutar este script\n";
    echo "3. ❌ Verifica la configuración de la base de datos\n";
}

if (!empty($warnings)) {
    echo "\n⚠️  ACCIONES RECOMENDADAS:\n";
    echo "• Crea los archivos faltantes del módulo\n";
    echo "• Agrega entrada de grupos al sidebar\n";
    echo "• Verifica permisos de directorios\n";
    echo "• Revisa configuración de tablas relacionadas\n";
}

// INFORMACIÓN ADICIONAL
echo "\n📚 INFORMACIÓN ADICIONAL:\n";
echo "========================\n";
echo "• Documentación: README del módulo de grupos\n";
echo "• Archivos de configuración: config/database.php\n";
echo "• Logs de errores: error_log del servidor\n";
echo "• Soporte: Revisa los comentarios en el código\n";

// ESTADÍSTICAS FINALES
$totalIssues = count($errors) + count($warnings);
$successRate = count($success) / (count($success) + $totalIssues) * 100;

echo "\n📈 TASA DE ÉXITO: " . round($successRate, 1) . "%\n";

if ($successRate >= 80) {
    echo "🎉 ¡INSTALACIÓN EXITOSA! El módulo está listo para usar.\n";
} elseif ($successRate >= 60) {
    echo "⚠️  INSTALACIÓN CON ADVERTENCIAS. Revisa los puntos pendientes.\n";
} else {
    echo "❌ INSTALACIÓN CON PROBLEMAS. Corrige los errores antes de continuar.\n";
}

echo "\n🚀 ¡Instalación del Módulo de Grupos completada!\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 50) . "\n";

// Salir con código apropiado
exit(empty($errors) ? 0 : 1);
?>