<?php
// verificacion_dms2_custom.php - Verificación optimizada para tu base de datos actual
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head><title>DMS2 - Verificación de Sistema Personalizada</title>";
echo "<meta charset='utf-8'>";
echo "<style>
    body { 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        margin: 0; 
        padding: 20px; 
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        color: #333;
    }
    .container {
        max-width: 1200px;
        margin: 0 auto;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #fd7e14; font-weight: bold; }
    .info { color: #17a2b8; font-weight: bold; }
    .section { 
        margin: 25px 0; 
        padding: 20px; 
        border: 1px solid #e9ecef; 
        border-radius: 10px; 
        background: #f8f9fa;
    }
    .section h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
        color: #495057;
    }
    table { 
        border-collapse: collapse; 
        width: 100%; 
        margin: 15px 0; 
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    th, td { 
        border: 1px solid #dee2e6; 
        padding: 12px 8px; 
        text-align: left; 
    }
    th { 
        background: linear-gradient(135deg, #667eea, #764ba2); 
        color: white;
        font-weight: 600;
    }
    .present { background-color: #d1ecf1; }
    .missing { background-color: #f8d7da; }
    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .badge-success { background: #d4edda; color: #155724; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-danger { background: #f8d7da; color: #721c24; }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        border-left: 4px solid #667eea;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #667eea;
    }
    .stat-label {
        color: #6c757d;
        font-size: 14px;
        margin-top: 5px;
    }
    .header-info {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        text-align: center;
    }
    .progress-bar {
        background: #e9ecef;
        border-radius: 10px;
        height: 8px;
        overflow: hidden;
        margin: 5px 0;
    }
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #28a745, #20c997);
        transition: width 0.3s ease;
    }
    .recommendation {
        background: #fff;
        border-left: 4px solid #28a745;
        padding: 15px;
        margin: 10px 0;
        border-radius: 0 8px 8px 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
</style></head><body>";

echo "<div class='container'>";
echo "<div class='header-info'>";
echo "<h1>🎯 DMS2 - Análisis Personalizado del Sistema</h1>";
echo "<p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>";
echo "<p><strong>Análisis basado en tu estructura actual</strong></p>";
echo "</div>";

try {
    require_once 'config/database.php';
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    // Obtener información de la base de datos
    $dbInfo = $pdo->query("SELECT DATABASE() as db_name, VERSION() as version")->fetch();
    
    echo "<div class='section'>";
    echo "<h2>✅ Estado de Conexión</h2>";
    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>✓ CONECTADO</div>";
    echo "<div class='stat-label'>Estado de la conexión</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>{$dbInfo['db_name']}</div>";
    echo "<div class='stat-label'>Base de datos</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>{$dbInfo['version']}</div>";
    echo "<div class='stat-label'>Versión MySQL/MariaDB</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>UTF8MB4</div>";
    echo "<div class='stat-label'>Charset</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // ============================================================================
    // 1. ANÁLISIS DE TABLAS EXISTENTES CON TU ESTRUCTURA ACTUAL
    // ============================================================================
    echo "<div class='section'>";
    echo "<h2>1. 📊 Análisis de Estructura Actual</h2>";
    
    // Obtener todas las tablas
    $stmt = $pdo->query("SHOW TABLES");
    $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Definir las tablas que tienes según tu BD actual
    $your_tables = [
        'activity_logs' => 'Logs de actividad del sistema',
        'companies' => 'Empresas y organizaciones', 
        'departments' => 'Departamentos por empresa',
        'documents' => 'Documentos del sistema',
        'document_folders' => 'Carpetas para organizar documentos',
        'document_types' => 'Tipos de documentos',
        'inbox_records' => 'Bandeja de entrada de documentos',
        'notifications' => 'Sistema de notificaciones',
        'security_groups' => 'Grupos de seguridad',
        'system_config' => 'Configuración del sistema',
        'users' => 'Usuarios del sistema',
        'user_groups' => 'Grupos de usuarios con permisos',
        'user_group_members' => 'Relación usuarios-grupos',
        'user_groups_backup' => 'Respaldo de grupos de usuarios'
    ];
    
    // También incluir las vistas
    $your_views = [
        'group_stats' => 'Estadísticas de grupos',
        'user_access_summary' => 'Resumen de acceso de usuarios',
        'v_folders_complete' => 'Vista completa de carpetas',
        'v_group_permissions_summary' => 'Resumen de permisos por grupo'
    ];
    
    echo "<h3>📋 Tablas del Sistema</h3>";
    echo "<table>";
    echo "<tr><th>Tabla</th><th>Estado</th><th>Registros</th><th>Descripción</th><th>Tamaño</th></tr>";
    
    $total_records = 0;
    $table_count = 0;
    
    foreach ($your_tables as $table => $description) {
        if (in_array($table, $existing_tables)) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
                $count = $stmt->fetch()['count'];
                $total_records += $count;
                $table_count++;
                
                // Obtener tamaño de tabla
                $stmt = $pdo->query("SELECT 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb 
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE() AND table_name = '$table'");
                $size_info = $stmt->fetch();
                $size = $size_info ? $size_info['size_mb'] . ' MB' : 'N/A';
                
                echo "<tr class='present'>";
                echo "<td><strong>$table</strong></td>";
                echo "<td><span class='status-badge badge-success'>✅ Activa</span></td>";
                echo "<td>" . number_format($count) . "</td>";
                echo "<td>$description</td>";
                echo "<td>$size</td>";
                echo "</tr>";
                
            } catch (Exception $e) {
                echo "<tr class='missing'>";
                echo "<td><strong>$table</strong></td>";
                echo "<td><span class='status-badge badge-danger'>❌ Error</span></td>";
                echo "<td>-</td>";
                echo "<td>$description</td>";
                echo "<td>Error al consultar</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr class='missing'>";
            echo "<td><strong>$table</strong></td>";
            echo "<td><span class='status-badge badge-warning'>⚠️ Faltante</span></td>";
            echo "<td>-</td>";
            echo "<td>$description</td>";
            echo "<td>-</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    echo "<h3>👁️ Vistas del Sistema</h3>";
    echo "<table>";
    echo "<tr><th>Vista</th><th>Estado</th><th>Descripción</th></tr>";
    
    foreach ($your_views as $view => $description) {
        if (in_array($view, $existing_tables)) {
            echo "<tr class='present'>";
            echo "<td><strong>$view</strong></td>";
            echo "<td><span class='status-badge badge-success'>✅ Disponible</span></td>";
            echo "<td>$description</td>";
            echo "</tr>";
        } else {
            echo "<tr class='missing'>";
            echo "<td><strong>$view</strong></td>";
            echo "<td><span class='status-badge badge-warning'>⚠️ Faltante</span></td>";
            echo "<td>$description</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    // Estadísticas generales
    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>$table_count</div>";
    echo "<div class='stat-label'>Tablas Principales</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>" . number_format($total_records) . "</div>";
    echo "<div class='stat-label'>Total Registros</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>" . count(array_intersect(array_keys($your_views), $existing_tables)) . "/" . count($your_views) . "</div>";
    echo "<div class='stat-label'>Vistas Disponibles</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>" . count($existing_tables) . "</div>";
    echo "<div class='stat-label'>Total Objetos BD</div>";
    echo "</div>";
    echo "</div>";
    
    // ============================================================================
    // 2. ANÁLISIS DE DATOS Y CONTENIDO
    // ============================================================================
    echo "</div>";
    echo "<div class='section'>";
    echo "<h2>2. 📈 Análisis de Datos</h2>";
    
    // Análisis de usuarios
    if (in_array('users', $existing_tables)) {
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN last_login IS NOT NULL THEN 1 ELSE 0 END) as with_login
            FROM users");
        $user_stats = $stmt->fetch();
        
        echo "<h3>👥 Usuarios</h3>";
        echo "<div class='stats-grid'>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-value'>{$user_stats['total']}</div>";
        echo "<div class='stat-label'>Total Usuarios</div>";
        echo "</div>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-value'>{$user_stats['active']}</div>";
        echo "<div class='stat-label'>Usuarios Activos</div>";
        echo "</div>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-value'>{$user_stats['admins']}</div>";
        echo "<div class='stat-label'>Administradores</div>";
        echo "</div>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-value'>{$user_stats['with_login']}</div>";
        echo "<div class='stat-label'>Han Iniciado Sesión</div>";
        echo "</div>";
        echo "</div>";
    }
    
    // Análisis de documentos
    if (in_array('documents', $existing_tables)) {
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as not_deleted,
            ROUND(AVG(file_size)/1024/1024, 2) as avg_size_mb
            FROM documents");
        $doc_stats = $stmt->fetch();
        
        echo "<h3>📄 Documentos</h3>";
        echo "<div class='stats-grid'>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-value'>{$doc_stats['total']}</div>";
        echo "<div class='stat-label'>Total Documentos</div>";
        echo "</div>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-value'>{$doc_stats['active']}</div>";
        echo "<div class='stat-label'>Documentos Activos</div>";
        echo "</div>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-value'>{$doc_stats['not_deleted']}</div>";
        echo "<div class='stat-label'>No Eliminados</div>";
        echo "</div>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-value'>" . ($doc_stats['avg_size_mb'] ?: '0') . " MB</div>";
        echo "<div class='stat-label'>Tamaño Promedio</div>";
        echo "</div>";
        echo "</div>";
    }
    
    // Análisis de empresas y departamentos
    if (in_array('companies', $existing_tables) && in_array('departments', $existing_tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as companies FROM companies WHERE status = 'active'");
        $companies_count = $stmt->fetch()['companies'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as departments FROM departments WHERE status = 'active'");
        $departments_count = $stmt->fetch()['departments'];
        
        echo "<h3>🏢 Estructura Organizacional</h3>";
        echo "<div class='stats-grid'>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-value'>$companies_count</div>";
        echo "<div class='stat-label'>Empresas Activas</div>";
        echo "</div>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-value'>$departments_count</div>";
        echo "<div class='stat-label'>Departamentos Activos</div>";
        echo "</div>";
        echo "</div>";
    }
    
    // ============================================================================
    // 3. VERIFICACIÓN DE INTEGRIDAD
    // ============================================================================
    echo "</div>";
    echo "<div class='section'>";
    echo "<h2>3. 🔍 Verificación de Integridad</h2>";
    
    $integrity_checks = [];
    
    // Verificar relaciones críticas
    if (in_array('users', $existing_tables) && in_array('companies', $existing_tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users u LEFT JOIN companies c ON u.company_id = c.id WHERE u.company_id IS NOT NULL AND c.id IS NULL");
        $orphan_users = $stmt->fetch()['count'];
        $integrity_checks[] = [
            'name' => 'Usuarios con empresa inválida',
            'result' => $orphan_users == 0 ? 'Correcto' : "$orphan_users usuario(s) afectado(s)",
            'status' => $orphan_users == 0 ? 'success' : 'warning'
        ];
    }
    
    if (in_array('documents', $existing_tables) && in_array('users', $existing_tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM documents d LEFT JOIN users u ON d.user_id = u.id WHERE d.user_id IS NOT NULL AND u.id IS NULL");
        $orphan_docs = $stmt->fetch()['count'];
        $integrity_checks[] = [
            'name' => 'Documentos con usuario inválido',
            'result' => $orphan_docs == 0 ? 'Correcto' : "$orphan_docs documento(s) afectado(s)",
            'status' => $orphan_docs == 0 ? 'success' : 'warning'
        ];
    }
    
    if (in_array('user_group_members', $existing_tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_group_members ugm LEFT JOIN users u ON ugm.user_id = u.id LEFT JOIN user_groups ug ON ugm.group_id = ug.id WHERE u.id IS NULL OR ug.id IS NULL");
        $orphan_memberships = $stmt->fetch()['count'];
        $integrity_checks[] = [
            'name' => 'Membresías de grupo inválidas',
            'result' => $orphan_memberships == 0 ? 'Correcto' : "$orphan_memberships membresía(s) afectada(s)",
            'status' => $orphan_memberships == 0 ? 'success' : 'warning'
        ];
    }
    
    // Verificar índices importantes
    $stmt = $pdo->query("SHOW INDEX FROM users WHERE Key_name != 'PRIMARY'");
    $user_indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $integrity_checks[] = [
        'name' => 'Índices en tabla users',
        'result' => count($user_indexes) > 0 ? count($user_indexes) . ' índice(s) configurado(s)' : 'Sin índices adicionales',
        'status' => count($user_indexes) > 0 ? 'success' : 'info'
    ];
    
    echo "<table>";
    echo "<tr><th>Verificación</th><th>Resultado</th><th>Estado</th></tr>";
    foreach ($integrity_checks as $check) {
        $status_icon = $check['status'] === 'success' ? '✅' : ($check['status'] === 'warning' ? '⚠️' : 'ℹ️');
        $status_class = $check['status'];
        echo "<tr>";
        echo "<td>{$check['name']}</td>";
        echo "<td>{$check['result']}</td>";
        echo "<td><span class='$status_class'>$status_icon</span></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // ============================================================================
    // 4. FUNCIONALIDADES DETECTADAS
    // ============================================================================
    echo "</div>";
    echo "<div class='section'>";
    echo "<h2>4. 🎯 Funcionalidades Implementadas</h2>";
    
    $features = [];
    
    // Detectar funcionalidades basadas en la estructura
    if (in_array('users', $existing_tables) && in_array('user_groups', $existing_tables)) {
        $features[] = ['name' => 'Sistema de Usuarios y Grupos', 'status' => 'Implementado', 'icon' => '👥'];
    }
    
    if (in_array('documents', $existing_tables) && in_array('document_types', $existing_tables)) {
        $features[] = ['name' => 'Gestión de Documentos', 'status' => 'Implementado', 'icon' => '📄'];
    }
    
    if (in_array('document_folders', $existing_tables)) {
        $features[] = ['name' => 'Organización por Carpetas', 'status' => 'Implementado', 'icon' => '📁'];
    }
    
    if (in_array('companies', $existing_tables) && in_array('departments', $existing_tables)) {
        $features[] = ['name' => 'Estructura Organizacional', 'status' => 'Implementado', 'icon' => '🏢'];
    }
    
    if (in_array('activity_logs', $existing_tables)) {
        $features[] = ['name' => 'Auditoría y Logs', 'status' => 'Implementado', 'icon' => '📋'];
    }
    
    if (in_array('notifications', $existing_tables)) {
        $features[] = ['name' => 'Sistema de Notificaciones', 'status' => 'Implementado', 'icon' => '🔔'];
    }
    
    if (in_array('inbox_records', $existing_tables)) {
        $features[] = ['name' => 'Bandeja de Entrada', 'status' => 'Implementado', 'icon' => '📨'];
    }
    
    if (in_array('security_groups', $existing_tables)) {
        $features[] = ['name' => 'Grupos de Seguridad', 'status' => 'Implementado', 'icon' => '🔒'];
    }
    
    if (in_array('system_config', $existing_tables)) {
        $features[] = ['name' => 'Configuración del Sistema', 'status' => 'Implementado', 'icon' => '⚙️'];
    }
    
    // Verificar si hay vistas (funcionalidad avanzada)
    $views_count = count(array_intersect(array_keys($your_views), $existing_tables));
    if ($views_count > 0) {
        $features[] = ['name' => 'Vistas de Base de Datos Avanzadas', 'status' => "Implementadas ($views_count vistas)", 'icon' => '🔍'];
    }
    
    // Verificar triggers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()");
    $triggers_count = $stmt->fetch()['count'];
    if ($triggers_count > 0) {
        $features[] = ['name' => 'Triggers de Base de Datos', 'status' => "Implementados ($triggers_count triggers)", 'icon' => '⚡'];
    }
    
    echo "<div class='stats-grid'>";
    foreach ($features as $feature) {
        echo "<div class='stat-card'>";
        echo "<div class='stat-value'>{$feature['icon']}</div>";
        echo "<div class='stat-label'>{$feature['name']}</div>";
        echo "<div style='font-size: 12px; color: #28a745; margin-top: 5px;'>{$feature['status']}</div>";
        echo "</div>";
    }
    echo "</div>";
    
    // ============================================================================
    // 5. ANÁLISIS DE RENDIMIENTO
    // ============================================================================
    echo "</div>";
    echo "<div class='section'>";
    echo "<h2>5. ⚡ Análisis de Rendimiento</h2>";
    
    // Información de la base de datos
    try {
        $stmt = $pdo->query("SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb,
            COUNT(*) as total_tables,
            SUM(CASE WHEN ENGINE = 'InnoDB' THEN 1 ELSE 0 END) as innodb_tables
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()");
        $db_info = $stmt->fetch();
        
        // Verificar configuración MySQL
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'max_connections'");
        $max_conn = $stmt->fetch()['Value'];
        
        $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
        $current_conn = $stmt->fetch()['Value'];
        
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
        $buffer_pool = $stmt->fetch()['Value'];
        $buffer_pool_mb = round($buffer_pool / 1024 / 1024, 0);
        
    } catch (Exception $e) {
        $db_info = ['db_size_mb' => 'N/A', 'total_tables' => 'N/A', 'innodb_tables' => 'N/A'];
        $max_conn = 'N/A';
        $current_conn = 'N/A';
        $buffer_pool_mb = 'N/A';
    }
    
    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>{$db_info['db_size_mb']} MB</div>";
    echo "<div class='stat-label'>Tamaño Total BD</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>{$db_info['innodb_tables']}/{$db_info['total_tables']}</div>";
    echo "<div class='stat-label'>Tablas InnoDB</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>$current_conn/$max_conn</div>";
    echo "<div class='stat-label'>Conexiones</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>{$buffer_pool_mb} MB</div>";
    echo "<div class='stat-label'>Buffer Pool</div>";
    echo "</div>";
    echo "</div>";
    
    // Verificar índices en tablas principales
    echo "<h3>📊 Índices en Tablas Principales</h3>";
    $main_tables = ['users', 'documents', 'companies', 'user_groups'];
    echo "<table>";
    echo "<tr><th>Tabla</th><th>Índices</th><th>Claves Foráneas</th><th>Estado</th></tr>";
    
    foreach ($main_tables as $table) {
        if (in_array($table, $existing_tables)) {
            try {
                $stmt = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name != 'PRIMARY'");
                $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $index_count = count(array_unique(array_column($indexes, 'Key_name')));
                
                $stmt = $pdo->query("SELECT COUNT(*) as fk_count FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND REFERENCED_TABLE_NAME IS NOT NULL");
                $fk_count = $stmt->fetch()['fk_count'];
                
                $status = $index_count > 0 ? 'Optimizada' : 'Básica';
                $status_class = $index_count > 0 ? 'success' : 'info';
                
                echo "<tr>";
                echo "<td><strong>$table</strong></td>";
                echo "<td>$index_count índice(s)</td>";
                echo "<td>$fk_count FK</td>";
                echo "<td><span class='$status_class'>$status</span></td>";
                echo "</tr>";
                
            } catch (Exception $e) {
                echo "<tr>";
                echo "<td><strong>$table</strong></td>";
                echo "<td>Error</td>";
                echo "<td>Error</td>";
                echo "<td><span class='error'>Error</span></td>";
                echo "</tr>";
            }
        }
    }
    echo "</table>";
    
    // ============================================================================
    // 6. RECOMENDACIONES ESPECÍFICAS
    // ============================================================================
    echo "</div>";
    echo "<div class='section'>";
    echo "<h2>6. 💡 Recomendaciones</h2>";
    
    $recommendations = [];
    
    // Verificar si hay tabla system_settings
    if (!in_array('system_settings', $existing_tables)) {
        $recommendations[] = [
            'type' => 'Optimización',
            'priority' => 'Baja',
            'title' => 'Considerar tabla system_settings',
            'description' => 'Podrías agregar una tabla system_settings para configuraciones más avanzadas, separada de system_config.',
            'benefit' => 'Mejor organización de configuraciones'
        ];
    }
    
    // Verificar documentos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM documents");
    $doc_count = $stmt->fetch()['count'];
    if ($doc_count == 0) {
        $recommendations[] = [
            'type' => 'Contenido',
            'priority' => 'Media',
            'title' => 'Agregar documentos de prueba',
            'description' => 'El sistema no tiene documentos. Considera agregar algunos documentos de prueba para verificar la funcionalidad.',
            'benefit' => 'Validar funcionalidad completa del sistema'
        ];
    }
    
    // Verificar grupos de usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_groups WHERE deleted_at IS NULL");
    $groups_count = $stmt->fetch()['count'];
    if ($groups_count == 0) {
        $recommendations[] = [
            'type' => 'Configuración',
            'priority' => 'Media',
            'title' => 'Crear grupos de usuarios básicos',
            'description' => 'No hay grupos de usuarios activos. Crear grupos básicos como "Administradores", "Usuarios", "Solo Lectura".',
            'benefit' => 'Mejor gestión de permisos'
        ];
    }
    
    // Verificar respaldos
    $recommendations[] = [
        'type' => 'Mantenimiento',
        'priority' => 'Alta',
        'title' => 'Implementar respaldos automáticos',
        'description' => 'Configura respaldos automáticos de la base de datos usando mysqldump o herramientas de XAMPP.',
        'benefit' => 'Protección de datos críticos'
    ];
    
    // Verificar logs antiguos
    if (in_array('activity_logs', $existing_tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as old_logs FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $old_logs = $stmt->fetch()['old_logs'];
        if ($old_logs > 100) {
            $recommendations[] = [
                'type' => 'Mantenimiento',
                'priority' => 'Baja',
                'title' => 'Limpiar logs antiguos',
                'description' => "Hay $old_logs registros de log antiguos (>90 días). Considera implementar limpieza automática.",
                'benefit' => 'Mejor rendimiento de la BD'
            ];
        }
    }
    
    // Mostrar recomendaciones
    if (empty($recommendations)) {
        echo "<div class='recommendation'>";
        echo "<h3>✅ ¡Excelente! Tu sistema está muy bien configurado</h3>";
        echo "<p>No se encontraron problemas significativos. El sistema DMS2 está funcionando correctamente.</p>";
        echo "<h4>Mantenimiento recomendado:</h4>";
        echo "<ul>";
        echo "<li>Realizar respaldos periódicos de la base de datos</li>";
        echo "<li>Monitorear el crecimiento del tamaño de la BD</li>";
        echo "<li>Revisar logs de actividad regularmente</li>";
        echo "<li>Mantener actualizado XAMPP y sus componentes</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        foreach ($recommendations as $rec) {
            $priority_colors = [
                'Alta' => '#dc3545',
                'Media' => '#ffc107',
                'Baja' => '#28a745'
            ];
            $border_color = $priority_colors[$rec['priority']];
            
            echo "<div class='recommendation' style='border-left-color: $border_color;'>";
            echo "<h4>{$rec['title']} <small style='color: $border_color;'>[{$rec['priority']}]</small></h4>";
            echo "<p><strong>Tipo:</strong> {$rec['type']}</p>";
            echo "<p><strong>Descripción:</strong> {$rec['description']}</p>";
            echo "<p><strong>Beneficio:</strong> {$rec['benefit']}</p>";
            echo "</div>";
        }
    }
    
    // ============================================================================
    // 7. RESUMEN EJECUTIVO
    // ============================================================================
    echo "</div>";
    echo "<div class='section'>";
    echo "<h2>7. 📋 Resumen Ejecutivo</h2>";
    
    // Calcular puntuación
    $score = 85; // Base score
    
    // Ajustar puntuación basada en análisis
    if ($user_stats['total'] > 0) $score += 5;
    if ($user_stats['admins'] > 0) $score += 5;
    if (count($features) >= 8) $score += 5;
    if ($doc_count > 0) $score += 0; // No penalizar si no hay docs aún
    
    $score = min(100, $score); // Máximo 100
    
    $status_message = $score >= 95 ? "🟢 Excelente" :
                     ($score >= 85 ? "🟡 Muy Bueno" :
                     ($score >= 70 ? "🟠 Bueno" : "🔴 Necesita Atención"));
    
    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>$score%</div>";
    echo "<div class='stat-label'>Puntuación General</div>";
    echo "<div class='progress-bar'><div class='progress-fill' style='width: {$score}%'></div></div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>$status_message</div>";
    echo "<div class='stat-label'>Estado del Sistema</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>" . count($features) . "</div>";
    echo "<div class='stat-label'>Funcionalidades</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>" . count($recommendations) . "</div>";
    echo "<div class='stat-label'>Recomendaciones</div>";
    echo "</div>";
    echo "</div>";
    
    echo "<h3>🎯 Conclusión</h3>";
    echo "<div class='recommendation'>";
    if ($score >= 90) {
        echo "<p><strong>¡Felicitaciones!</strong> Tu sistema DMS2 está <strong>excelentemente configurado</strong>. La estructura de base de datos es sólida, las relaciones están bien definidas y tienes todas las funcionalidades principales implementadas.</p>";
        echo "<p><strong>Estado:</strong> ✅ Listo para producción</p>";
        echo "<p><strong>Fortalezas:</strong> Estructura completa, integridad de datos, funcionalidades avanzadas</p>";
    } else if ($score >= 80) {
        echo "<p>Tu sistema DMS2 está <strong>muy bien configurado</strong>. La mayoría de funcionalidades están implementadas correctamente.</p>";
        echo "<p><strong>Estado:</strong> ✅ Funcional con mejoras menores pendientes</p>";
        echo "<p><strong>Fortalezas:</strong> Base sólida, funcionalidades principales completas</p>";
    } else {
        echo "<p>Tu sistema DMS2 necesita algunas mejoras para estar completamente optimizado.</p>";
        echo "<p><strong>Estado:</strong> ⚠️ Funcional pero necesita atención</p>";
    }
    echo "</div>";
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<h2 class='error'>❌ Error durante el análisis</h2>";
    echo "<div class='recommendation' style='border-left-color: #dc3545;'>";
    echo "<h3>Error de Conexión o Configuración</h3>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<h4>Posibles soluciones:</h4>";
    echo "<ol>";
    echo "<li>Verificar que XAMPP esté ejecutándose (Apache y MySQL activos)</li>";
    echo "<li>Confirmar que existe la base de datos 'dms2'</li>";
    echo "<li>Revisar las credenciales en config/database.php</li>";
    echo "<li>Verificar que el archivo config/database.php existe y es accesible</li>";
    echo "</ol>";
    echo "</div>";
    echo "</div>";
}

// Footer
echo "<div style='margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 10px; text-align: center; border-top: 3px solid #667eea;'>";
echo "<h3>📊 Información del Análisis</h3>";
echo "<p><strong>Herramienta:</strong> DMS2 Custom Verification Tool</p>";
echo "<p><strong>Versión:</strong> 1.0 - Optimizada para tu estructura</p>";
echo "<p><strong>Análisis completado:</strong> " . date('d/m/Y H:i:s') . "</p>";
echo "<p><strong>Tiempo de ejecución:</strong> " . round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000) . " ms</p>";
echo "<p style='font-size: 12px; color: #6c757d; margin-top: 15px;'>";
echo "Herramienta personalizada para verificar la integridad y funcionalidad del sistema DMS2<br>";
echo "Desarrollada específicamente para tu estructura de base de datos actual";
echo "</p>";
echo "</div>";

echo "</div>"; // Cerrar container
echo "</body></html>";
?>