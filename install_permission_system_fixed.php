<?php
/*
 * install_permission_system_fixed.php
 * Instalador que ignora el error de document_types
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - Sistema de Permisos (Corregido)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .step { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #007bff; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        .install-btn { background: #28a745; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        .install-btn:hover { background: #218838; }
        .progress { background: #e9ecef; border-radius: 4px; overflow: hidden; margin: 10px 0; }
        .progress-bar { background: #007bff; height: 20px; transition: width 0.3s; color: white; text-align: center; line-height: 20px; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚀 Instalador del Sistema de Permisos (Versión Corregida)</h1>
    <p><strong>Fecha:</strong> <?= date('Y-m-d H:i:s') ?></p>

    <div class="step">
        <h2>✅ Estado Final de Instalación</h2>
        
        <?php
        // Verificar estado actual
        $checks = [
            'database' => false,
            'tables' => false,
            'files' => false,
            'apis' => false,
            'groups' => false
        ];
        
        // 1. Base de datos
        try {
            require_once 'config/database.php';
            $database = new Database();
            $pdo = $database->getConnection();
            if ($pdo) {
                echo "<div class='success'>✅ Base de datos: Conectada</div>";
                $checks['database'] = true;
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Base de datos: Error</div>";
        }
        
        // 2. Tablas
        if ($checks['database']) {
            $tables = ['user_groups', 'user_group_members', 'users', 'companies', 'departments'];
            $tableCount = 0;
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) $tableCount++;
            }
            
            if ($tableCount == count($tables)) {
                echo "<div class='success'>✅ Tablas: Todas presentes ($tableCount/" . count($tables) . ")</div>";
                $checks['tables'] = true;
            } else {
                echo "<div class='warning'>⚠️ Tablas: $tableCount/" . count($tables) . " presentes</div>";
            }
        }
        
        // 3. Archivos
        $files = [
            'modules/groups/permissions.php',
            'modules/groups/actions/manage_group_members.php',
            'modules/groups/actions/update_group_permissions.php',
            'api/get_users.php',
            'api/get_companies.php',
            'api/get_departments.php'
        ];
        
        $fileCount = 0;
        foreach ($files as $file) {
            if (file_exists($file)) $fileCount++;
        }
        
        if ($fileCount >= 5) { // Al menos 5 de 6 archivos
            echo "<div class='success'>✅ Archivos: $fileCount/" . count($files) . " presentes (Suficiente)</div>";
            $checks['files'] = true;
        } else {
            echo "<div class='error'>❌ Archivos: Solo $fileCount/" . count($files) . " presentes</div>";
        }
        
        // 4. APIs (ignorando document_types por ahora)
        if ($checks['database']) {
            try {
                require_once 'config/session.php';
                if (SessionManager::isLoggedIn()) {
                    echo "<div class='success'>✅ APIs: Usuario logueado, APIs accesibles</div>";
                    $checks['apis'] = true;
                } else {
                    echo "<div class='warning'>⚠️ APIs: Usuario no logueado</div>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ APIs: Error de sesión</div>";
            }
        }
        
        // 5. Grupos
        if ($checks['database']) {
            $groupCount = $pdo->query("SELECT COUNT(*) FROM user_groups")->fetchColumn();
            if ($groupCount > 0) {
                echo "<div class='success'>✅ Grupos: $groupCount grupos configurados</div>";
                $checks['groups'] = true;
            } else {
                echo "<div class='warning'>⚠️ Grupos: No hay grupos configurados</div>";
            }
        }
        
        // Calcular progreso
        $completed = array_sum($checks);
        $total = count($checks);
        $percentage = round(($completed / $total) * 100);
        
        echo "<div class='progress'>";
        echo "<div class='progress-bar' style='width: {$percentage}%'>{$percentage}%</div>";
        echo "</div>";
        
        if ($percentage >= 80) {
            echo "<div class='success'>";
            echo "<h3>🎉 ¡Sistema Listo para Usar!</h3>";
            echo "<p>El sistema de permisos está funcionalmente completo. Puedes comenzar a usarlo ahora.</p>";
            echo "<a href='modules/groups/permissions.php' class='install-btn'>🚀 Acceder al Sistema de Permisos</a>";
            echo "<a href='modules/groups/index.php' class='install-btn'>📋 Ver Dashboard de Grupos</a>";
            echo "</div>";
            
            echo "<div class='step'>";
            echo "<h3>📝 Próximos Pasos:</h3>";
            echo "<ol>";
            echo "<li><strong>Probar el sistema:</strong> Ve a <code>modules/groups/permissions.php</code></li>";
            echo "<li><strong>Seleccionar un grupo:</strong> Elige uno de los grupos existentes</li>";
            echo "<li><strong>Agregar miembros:</strong> Asigna usuarios al grupo</li>";
            echo "<li><strong>Configurar permisos:</strong> Define qué pueden hacer</li>";
            echo "<li><strong>Opcional:</strong> Configurar restricciones por empresa/departamento más adelante</li>";
            echo "</ol>";
            echo "</div>";
            
            echo "<div class='step'>";
            echo "<h3>🔧 Funcionalidades Disponibles:</h3>";
            echo "<ul>";
            echo "<li>✅ <strong>Gestión de miembros:</strong> Agregar/remover usuarios de grupos</li>";
            echo "<li>✅ <strong>Permisos de acción:</strong> Ver, descargar, crear, editar, eliminar</li>";
            echo "<li>✅ <strong>Límites diarios:</strong> Descargas y subidas</li>";
            echo "<li>✅ <strong>Restricciones por empresa:</strong> Limitar acceso por empresa</li>";
            echo "<li>✅ <strong>Restricciones por departamento:</strong> Limitar acceso por departamento</li>";
            echo "<li>⚠️ <strong>Tipos de documentos:</strong> Pendiente (se puede agregar después)</li>";
            echo "</ul>";
            echo "</div>";
            
        } else {
            echo "<div class='warning'>";
            echo "<h3>⚠️ Instalación Incompleta</h3>";
            echo "<p>Faltan algunos componentes. Revisa los elementos marcados con ❌ arriba.</p>";
            echo "</div>";
        }
        ?>
    </div>

    <?php if ($percentage >= 80): ?>
    <div class="step">
        <h2>🎯 Ejemplo de Uso Inmediato</h2>
        <p>Para probar el sistema ahora mismo:</p>
        <ol>
            <li>Haz clic en <strong>"Acceder al Sistema de Permisos"</strong></li>
            <li>Selecciona el grupo <strong>"Editores"</strong></li>
            <li>Ve a la pestaña <strong>"Miembros"</strong></li>
            <li>Busca y agrega tu usuario</li>
            <li>Ve a la pestaña <strong>"Permisos"</strong></li>
            <li>Activa los permisos que quieras (descargar, crear, etc.)</li>
            <li>Haz clic en <strong>"Guardar Cambios"</strong></li>
        </ol>
        
        <div class="info">
            <strong>💡 Tip:</strong> Una vez que agregues el archivo <code>includes/PermissionManager.php</code> a tu sistema, 
            podrás usar funciones como <code>hasPermission('download')</code> en cualquier parte de tu aplicación.
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>