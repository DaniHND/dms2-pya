<?php
// verificacion_bd_completa.php - Verificación exhaustiva de la BD DMS2
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head><title>Verificación Completa BD DMS2</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .sql-code { background: #f5f5f5; padding: 10px; border-radius: 3px; font-family: monospace; }
    .missing { background-color: #ffebee; }
    .present { background-color: #e8f5e8; }
</style></head><body>";

echo "<h1>🔍 Verificación Completa de Base de Datos DMS2</h1>";
echo "<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";

try {
    require_once 'config/database.php';
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "<div class='section'>";
    echo "<h2>✅ Conexión Establecida Correctamente</h2>";
    echo "<p>Usuario: root | Base de datos: dms2 | Charset: utf8mb4</p>";
    echo "</div>";
    
    // ============================================================================
    // 1. VERIFICAR ESTRUCTURA DE TABLAS COMPLETA
    // ============================================================================
    echo "<div class='section'>";
    echo "<h2>1. 📊 Estructura de Tablas Requeridas</h2>";
    
    $required_tables = [
        'users' => [
            'description' => 'Usuarios del sistema',
            'required_columns' => ['id', 'username', 'password', 'email', 'first_name', 'last_name', 'role', 'company_id', 'department_id', 'status', 'download_enabled', 'created_at']
        ],
        'companies' => [
            'description' => 'Empresas/organizaciones', 
            'required_columns' => ['id', 'name', 'description', 'ruc', 'address', 'phone', 'email', 'status', 'created_at']
        ],
        'departments' => [
            'description' => 'Departamentos por empresa',
            'required_columns' => ['id', 'company_id', 'name', 'description', 'status', 'created_at']
        ],
        'documents' => [
            'description' => 'Documentos del sistema',
            'required_columns' => ['id', 'company_id', 'department_id', 'document_type_id', 'user_id', 'name', 'file_path', 'status', 'created_at', 'deleted_at', 'deleted_by']
        ],
        'document_types' => [
            'description' => 'Tipos de documentos',
            'required_columns' => ['id', 'name', 'description', 'status', 'created_at']
        ],
        'document_folders' => [
            'description' => 'Carpetas para organizar documentos',
            'required_columns' => ['id', 'name', 'company_id', 'department_id', 'created_by', 'created_at']
        ],
        'user_groups' => [
            'description' => 'Grupos de usuarios con permisos',
            'required_columns' => ['id', 'name', 'description', 'module_permissions', 'access_restrictions', 'status', 'created_by', 'created_at']
        ],
        'user_group_members' => [
            'description' => 'Relación usuarios-grupos',
            'required_columns' => ['id', 'user_id', 'group_id', 'added_by', 'added_at']
        ],
        'activity_logs' => [
            'description' => 'Logs de actividad del sistema',
            'required_columns' => ['id', 'user_id', 'action', 'table_name', 'description', 'created_at']
        ],
        'system_config' => [
            'description' => 'Configuración del sistema',
            'required_columns' => ['id', 'config_key', 'config_value', 'created_at']
        ],
        'security_groups' => [
            'description' => 'Grupos de seguridad adicionales',
            'required_columns' => ['id', 'name', 'description', 'permissions', 'status']
        ],
        'notifications' => [
            'description' => 'Sistema de notificaciones',
            'required_columns' => ['id', 'user_id', 'type', 'title', 'message', 'read_status', 'created_at']
        ],
        'inbox_records' => [
            'description' => 'Bandeja de entrada de documentos',
            'required_columns' => ['id', 'document_id', 'sender_user_id', 'recipient_user_id', 'status', 'created_at']
        ]
    ];
    
    // Obtener todas las tablas existentes
    $stmt = $pdo->query("SHOW TABLES");
    $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<table>";
    echo "<tr><th>Tabla</th><th>Estado</th><th>Registros</th><th>Descripción</th><th>Columnas Críticas</th></tr>";
    
    $missing_tables = [];
    $incomplete_tables = [];
    
    foreach ($required_tables as $table => $info) {
        if (in_array($table, $existing_tables)) {
            // Tabla existe - verificar estructura
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
                $count = $stmt->fetch()['count'];
                
                // Verificar columnas
                $stmt = $pdo->query("DESCRIBE `$table`");
                $existing_columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
                
                $missing_columns = array_diff($info['required_columns'], $existing_columns);
                
                if (empty($missing_columns)) {
                    $status = "<span class='success'>✅ Completa</span>";
                    $columns_status = "<span class='success'>Todas presentes</span>";
                } else {
                    $status = "<span class='warning'>⚠️ Incompleta</span>";
                    $columns_status = "<span class='warning'>Faltan: " . implode(', ', $missing_columns) . "</span>";
                    $incomplete_tables[] = $table;
                }
                
                echo "<tr class='present'>";
                echo "<td><strong>$table</strong></td>";
                echo "<td>$status</td>";
                echo "<td>$count</td>";
                echo "<td>{$info['description']}</td>";
                echo "<td>$columns_status</td>";
                echo "</tr>";
                
            } catch (Exception $e) {
                echo "<tr class='missing'>";
                echo "<td><strong>$table</strong></td>";
                echo "<td><span class='error'>❌ Error</span></td>";
                echo "<td>-</td>";
                echo "<td>{$info['description']}</td>";
                echo "<td><span class='error'>Error: " . $e->getMessage() . "</span></td>";
                echo "</tr>";
            }
        } else {
            // Tabla no existe
            $missing_tables[] = $table;
            echo "<tr class='missing'>";
            echo "<td><strong>$table</strong></td>";
            echo "<td><span class='error'>❌ Faltante</span></td>";
            echo "<td>-</td>";
            echo "<td>{$info['description']}</td>";
            echo "<td><span class='error'>Tabla no existe</span></td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    // ============================================================================
    // 2. VERIFICAR FUNCIONES Y TRIGGERS
    // ============================================================================
    echo "</div>";
    echo "<div class='section'>";
    echo "<h2>2. ⚙️ Funciones y Triggers de Base de Datos</h2>";
    
    // Verificar funciones
    $stmt = $pdo->query("SELECT ROUTINE_NAME, ROUTINE_TYPE, CREATED FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = 'dms2'");
    $functions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Funciones MySQL:</h3>";
    if (empty($functions)) {
        echo "<p class='warning'>⚠️ No hay funciones definidas (esto puede causar errores en triggers)</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Nombre</th><th>Tipo</th><th>Creada</th></tr>";
        foreach ($functions as $func) {
            echo "<tr><td>{$func['ROUTINE_NAME']}</td><td>{$func['ROUTINE_TYPE']}</td><td>{$func['CREATED']}</td></tr>";
        }
        echo "</table>";
    }
    
    // Verificar triggers
    $stmt = $pdo->query("SELECT TRIGGER_NAME, EVENT_MANIPULATION, ACTION_TIMING FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = 'dms2'");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Triggers:</h3>";
    if (empty($triggers)) {
        echo "<p class='success'>✅ No hay triggers (simplifica el mantenimiento)</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Nombre</th><th>Evento</th><th>Timing</th></tr>";
        foreach ($triggers as $trigger) {
            echo "<tr><td>{$trigger['TRIGGER_NAME']}</td><td>{$trigger['EVENT_MANIPULATION']}</td><td>{$trigger['ACTION_TIMING']}</td></tr>";
        }
        echo "</table>";
    }
    
    // ============================================================================
    // 3. VERIFICAR DATOS CRÍTICOS
    // ============================================================================
    echo "</div>";
    echo "<div class='section'>";
    echo "<h2>3. 🗄️ Datos Críticos del Sistema</h2>";
    
    // Verificar usuarios admin
    $stmt = $pdo->query("SELECT id, username, first_name, last_name, role, status FROM users WHERE role = 'admin' AND status = 'active'");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Usuarios Administradores:</h3>";
    if (empty($admins)) {
        echo "<p class='error'>❌ No hay usuarios admin activos - Sistema sin administrador</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Nombre</th><th>Estado</th></tr>";
        foreach ($admins as $admin) {
            echo "<tr><td>{$admin['id']}</td><td>{$admin['username']}</td><td>{$admin['first_name']} {$admin['last_name']}</td><td>{$admin['status']}</td></tr>";
        }
        echo "</table>";
    }
    
    // Verificar empresa por defecto
    $stmt = $pdo->query("SELECT id, name, status FROM companies WHERE status = 'active' LIMIT 1");
    $default_company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Empresa Principal:</h3>";
    if (!$default_company) {
        echo "<p class='warning'>⚠️ No hay empresas activas - Crear empresa por defecto</p>";
    } else {
        echo "<p class='success'>✅ Empresa activa: {$default_company['name']} (ID: {$default_company['id']})</p>";
    }
    
    // Verificar tipos de documentos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM document_types WHERE status = 'active'");
    $doc_types_count = $stmt->fetch()['count'];
    
    echo "<h3>Tipos de Documentos:</h3>";
    if ($doc_types_count == 0) {
        echo "<p class='warning'>⚠️ No hay tipos de documentos activos</p>";
    } else {
        echo "<p class='success'>✅ $doc_types_count tipos de documentos activos</p>";
    }
    
    // ============================================================================
    // 4. VERIFICAR CONFIGURACIÓN DEL SISTEMA
    // ============================================================================
    echo "</div>";
    echo "<div class='section'>";
    echo "<h2>4. ⚙️ Configuración del Sistema</h2>";
    
    $config_checks = [
        'max_file_size' => 'Tamaño máximo de archivo',
        'allowed_extensions' => 'Extensiones permitidas',
        'system_name' => 'Nombre del sistema',
        'session_timeout' => 'Tiempo de sesión'
    ];
    
    echo "<table>";
    echo "<tr><th>Configuración</th><th>Estado</th><th>Valor</th></tr>";
    
    foreach ($config_checks as $key => $description) {
        $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
        $stmt->execute([$key]);
        $config = $stmt->fetch();
        
        if ($config) {
            echo "<tr class='present'><td>$description</td><td><span class='success'>✅ Configurado</span></td><td>" . substr($config['config_value'], 0, 50) . "...</td></tr>";
        } else {
            echo "<tr class='missing'><td>$description</td><td><span class='warning'>⚠️ Faltante</span></td><td>-</td></tr>";
        }
    }
    echo "</table>";
    
    // ============================================================================
    // 5. GENERAR RECOMENDACIONES
    // ============================================================================
    echo "</div>";
    echo "<div class='section'>";
    echo "<h2>5. 💡 Recomendaciones y Acciones</h2>";
    
    $recommendations = [];
    
    if (!empty($missing_tables)) {
        $recommendations[] = [
            'priority' => 'high',
            'action' => 'Crear tablas faltantes: ' . implode(', ', $missing_tables),
            'sql' => "-- Ejecutar el script de creación completo de DMS2"
        ];
    }
    
    if (!empty($incomplete_tables)) {
        $recommendations[] = [
            'priority' => 'medium',
            'action' => 'Actualizar estructura de tablas: ' . implode(', ', $incomplete_tables),
            'sql' => "-- Ejecutar ALTER TABLE para agregar columnas faltantes"
        ];
    }
    
    if (empty($admins)) {
        $recommendations[] = [
            'priority' => 'critical',
            'action' => 'Crear usuario administrador',
            'sql' => "INSERT INTO users (username, password, email, first_name, last_name, role, status) VALUES ('admin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@dms2.com', 'Administrador', 'Sistema', 'admin', 'active');"
        ];
    }
    
    if (!$default_company) {
        $recommendations[] = [
            'priority' => 'high',
            'action' => 'Crear empresa por defecto',
            'sql' => "INSERT INTO companies (name, description, status) VALUES ('Empresa Principal', 'Empresa por defecto del sistema', 'active');"
        ];
    }
    
    if (empty($functions) && !empty($triggers)) {
        $recommendations[] = [
            'priority' => 'medium',
            'action' => 'Eliminar triggers huérfanos o crear funciones faltantes',
            'sql' => "-- Eliminar triggers que referencian funciones inexistentes"
        ];
    }
    
    if (empty($recommendations)) {
        echo "<div class='success'>";
        echo "<h3>✅ ¡Base de Datos Completa y Funcional!</h3>";
        echo "<p>No se detectaron problemas críticos. El sistema está listo para usar.</p>";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<h3>🔧 Acciones Recomendadas:</h3>";
        foreach ($recommendations as $rec) {
            $priority_class = $rec['priority'] === 'critical' ? 'error' : ($rec['priority'] === 'high' ? 'warning' : 'info');
            echo "<div class='$priority_class'>";
            echo "<p><strong>[" . strtoupper($rec['priority']) . "]</strong> {$rec['action']}</p>";
            if (isset($rec['sql'])) {
                echo "<div class='sql-code'>{$rec['sql']}</div>";
            }
            echo "</div>";
        }
        echo "</div>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Error durante la verificación:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p style='text-align: center; color: #666;'>";
echo "Verificación completada en " . date('Y-m-d H:i:s') . " | ";
echo "DMS2 Database Verification Tool";
echo "</p>";

echo "</body></html>";
?>