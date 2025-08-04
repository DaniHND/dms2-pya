<?php
/*
 * install_permission_system.php
 * Instalador completo del sistema de permisos de grupos
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - Sistema de Permisos de Grupos</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .step { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #007bff; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        .code { background: #f1f1f1; padding: 15px; border-radius: 4px; font-family: monospace; margin: 10px 0; overflow-x: auto; }
        .install-btn { background: #28a745; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        .install-btn:hover { background: #218838; }
        .install-btn:disabled { background: #6c757d; cursor: not-allowed; }
        .progress { background: #e9ecef; border-radius: 4px; overflow: hidden; margin: 10px 0; }
        .progress-bar { background: #007bff; height: 20px; transition: width 0.3s; color: white; text-align: center; line-height: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f8f9fa; }
        .file-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 20px 0; }
        .file-item { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚀 Instalador del Sistema de Permisos de Grupos</h1>
    <p><strong>Fecha:</strong> <?= date('Y-m-d H:i:s') ?></p>

    <div class="step">
        <h2>📋 Resumen del Sistema</h2>
        <p>Este instalador configurará un sistema completo de permisos basado en grupos que incluye:</p>
        <ul>
            <li><strong>Gestión de miembros:</strong> Asignar usuarios específicos a grupos</li>
            <li><strong>Restricciones por empresa:</strong> Limitar acceso a empresas específicas</li>
            <li><strong>Restricciones por departamento:</strong> Limitar acceso a departamentos específicos</li>
            <li><strong>Restricciones por tipo de documento:</strong> Limitar tipos de documentos visibles</li>
            <li><strong>Permisos granulares:</strong> Ver, descargar, crear, editar, eliminar</li>
            <li><strong>Límites de uso:</strong> Límites diarios de descarga y subida</li>
        </ul>
    </div>

    <?php
    $installSteps = [
        'database' => false,
        'tables' => false,
        'files' => false,
        'api' => false,
        'permissions' => false
    ];
    
    $errors = [];
    $warnings = [];
    $success = [];
    ?>

    <!-- PASO 1: VERIFICAR BASE DE DATOS -->
    <div class="step">
        <h2>🗄️ Paso 1: Verificación de Base de Datos</h2>
        
        <?php
        try {
            require_once 'config/database.php';
            $database = new Database();
            $pdo = $database->getConnection();
            
            if (!$pdo) {
                throw new Exception("No se pudo conectar a la base de datos");
            }
            
            echo "<div class='success'>✅ Conexión a base de datos exitosa</div>";
            $installSteps['database'] = true;
            
            // Verificar tablas requeridas
            $requiredTables = [
                'user_groups' => 'Tabla principal de grupos',
                'user_group_members' => 'Relación usuario-grupo',
                'users' => 'Tabla de usuarios',
                'companies' => 'Tabla de empresas',
                'departments' => 'Tabla de departamentos',
                'document_types' => 'Tabla de tipos de documentos'
            ];
            
            echo "<h3>Verificando tablas:</h3>";
            $missingTables = [];
            
            foreach ($requiredTables as $table => $desc) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    echo "<div class='success'>✅ $table - $desc</div>";
                } else {
                    echo "<div class='error'>❌ $table - $desc (FALTA)</div>";
                    $missingTables[] = $table;
                }
            }
            
            if (empty($missingTables)) {
                echo "<div class='success'>🎉 Todas las tablas requeridas están presentes</div>";
                $installSteps['tables'] = true;
            } else {
                echo "<div class='warning'>⚠️ Faltan " . count($missingTables) . " tablas</div>";
                $warnings[] = "Tablas faltantes: " . implode(', ', $missingTables);
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            $errors[] = "Error de base de datos: " . $e->getMessage();
        }
        ?>
    </div>

    <!-- PASO 2: VERIFICAR/CREAR ESTRUCTURA -->
    <div class="step">
        <h2>🏗️ Paso 2: Verificación/Creación de Estructura</h2>
        
        <?php
        if ($installSteps['database']) {
            try {
                // Verificar estructura de user_groups
                echo "<h3>Verificando estructura de user_groups:</h3>";
                $columns = $pdo->query("DESCRIBE user_groups")->fetchAll(PDO::FETCH_ASSOC);
                $columnNames = array_column($columns, 'Field');
                
                $requiredColumns = [
                    'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
                    'name' => 'VARCHAR(150) NOT NULL',
                    'description' => 'TEXT',
                    'module_permissions' => 'LONGTEXT',
                    'access_restrictions' => 'LONGTEXT',
                    'download_limit_daily' => 'INT',
                    'upload_limit_daily' => 'INT',
                    'status' => 'ENUM("active","inactive")',
                    'is_system_group' => 'TINYINT(1)',
                    'created_by' => 'INT(11)',
                    'created_at' => 'TIMESTAMP',
                    'updated_at' => 'TIMESTAMP'
                ];
                
                $missingColumns = [];
                foreach ($requiredColumns as $col => $type) {
                    if (in_array($col, $columnNames)) {
                        echo "<div class='success'>✅ $col</div>";
                    } else {
                        echo "<div class='error'>❌ $col (FALTA)</div>";
                        $missingColumns[] = $col;
                    }
                }
                
                // Verificar tabla user_group_members
                echo "<h3>Verificando tabla user_group_members:</h3>";
                $stmt = $pdo->query("SHOW TABLES LIKE 'user_group_members'");
                if ($stmt->rowCount() > 0) {
                    echo "<div class='success'>✅ Tabla user_group_members existe</div>";
                } else {
                    echo "<div class='error'>❌ Tabla user_group_members no existe</div>";
                    $missingColumns[] = 'user_group_members_table';
                }
                
                if (empty($missingColumns)) {
                    echo "<div class='success'>🎉 Estructura de base de datos completa</div>";
                    $installSteps['tables'] = true;
                } else {
                    echo "<div class='warning'>⚠️ Estructura incompleta</div>";
                    echo "<button class='install-btn' onclick='createMissingStructure()'>Crear Estructura Faltante</button>";
                }
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Error verificando estructura: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<div class='warning'>⚠️ Primero debe resolverse la conexión a base de datos</div>";
        }
        ?>
        
        <div id="structureResult" style="display: none;"></div>
    </div>

    <!-- PASO 3: VERIFICAR ARCHIVOS -->
    <div class="step">
        <h2>📁 Paso 3: Verificación de Archivos del Sistema</h2>
        
        <?php
        $requiredFiles = [
            'modules/groups/permissions.php' => 'Página de gestión de permisos',
            'modules/groups/actions/manage_group_members.php' => 'Gestión de miembros',
            'modules/groups/actions/update_group_permissions.php' => 'Actualización de permisos',
            'api/get_users.php' => 'API de usuarios',
            'api/get_companies.php' => 'API de empresas',
            'api/get_departments.php' => 'API de departamentos',
            'api/get_document_types.php' => 'API de tipos de documentos',
            'includes/PermissionManager.php' => 'Sistema de verificación de permisos'
        ];
        
        echo "<div class='file-grid'>";
        $missingFiles = [];
        
        foreach ($requiredFiles as $file => $desc) {
            echo "<div class='file-item'>";
            if (file_exists($file)) {
                $size = round(filesize($file) / 1024, 1);
                echo "<div class='success'>✅ $file</div>";
                echo "<small>$desc ($size KB)</small>";
            } else {
                echo "<div class='error'>❌ $file</div>";
                echo "<small>$desc (FALTA)</small>";
                $missingFiles[] = $file;
            }
            echo "</div>";
        }
        echo "</div>";
        
        if (empty($missingFiles)) {
            echo "<div class='success'>🎉 Todos los archivos están presentes</div>";
            $installSteps['files'] = true;
        } else {
            echo "<div class='warning'>⚠️ Faltan " . count($missingFiles) . " archivos</div>";
            echo "<button class='install-btn' onclick='downloadFiles()'>Descargar Archivos Faltantes</button>";
        }
        ?>
    </div>

    <!-- PASO 4: VERIFICAR APIs -->
    <div class="step">
        <h2>🔌 Paso 4: Verificación de APIs</h2>
        
        <p>Verificando que las APIs respondan correctamente:</p>
        <div id="apiTests">
            <button class='install-btn' onclick='testAPIs()'>Probar APIs</button>
        </div>
        <div id="apiResults"></div>
    </div>

    <!-- PASO 5: CONFIGURACIÓN INICIAL -->
    <div class="step">
        <h2>⚙️ Paso 5: Configuración Inicial</h2>
        
        <?php if ($installSteps['database']): ?>
        <p>Configurar grupos predeterminados y permisos iniciales:</p>
        
        <div>
            <h4>Grupos a crear:</h4>
            <ul>
                <li><strong>Super Administradores:</strong> Acceso completo al sistema</li>
                <li><strong>Administradores:</strong> Gestión de usuarios y documentos</li>
                <li><strong>Editores:</strong> Crear y editar documentos</li>
                <li><strong>Usuarios:</strong> Ver y descargar documentos</li>
                <li><strong>Solo Lectura:</strong> Solo visualizar documentos</li>
            </ul>
            
            <button class='install-btn' onclick='createDefaultGroups()'>Crear Grupos Predeterminados</button>
        </div>
        
        <div id="groupsResult" style="margin-top: 20px;"></div>
        <?php else: ?>
        <div class='warning'>⚠️ Primero debe resolverse la conexión a base de datos</div>
        <?php endif; ?>
    </div>

    <!-- RESUMEN FINAL -->
    <div class="step">
        <h2>📊 Resumen de Instalación</h2>
        
        <div class="progress">
            <div class="progress-bar" id="progressBar" style="width: 0%">0%</div>
        </div>
        
        <div id="installationSummary">
            <p>Estado de la instalación:</p>
            <ul>
                <li id="status-database">🔴 Base de datos</li>
                <li id="status-tables">🔴 Estructura de tablas</li>
                <li id="status-files">🔴 Archivos del sistema</li>
                <li id="status-api">🔴 APIs</li>
                <li id="status-config">🔴 Configuración inicial</li>
            </ul>
        </div>
        
        <div id="finalActions" style="display: none;">
            <h3>🎉 ¡Instalación Completada!</h3>
            <p>El sistema de permisos de grupos ha sido instalado exitosamente.</p>
            <a href="modules/groups/permissions.php" class="install-btn">Acceder a Gestión de Permisos</a>
            <a href="modules/groups/index.php" class="install-btn">Ver Grupos</a>
        </div>
    </div>

</div>

<script>
// Variables de estado
let installationStatus = {
    database: <?= $installSteps['database'] ? 'true' : 'false' ?>,
    tables: <?= $installSteps['tables'] ? 'true' : 'false' ?>,
    files: <?= $installSteps['files'] ? 'true' : 'false' ?>,
    api: false,
    config: false
};

// Actualizar progreso
function updateProgress() {
    const completed = Object.values(installationStatus).filter(Boolean).length;
    const total = Object.keys(installationStatus).length;
    const percentage = Math.round((completed / total) * 100);
    
    document.getElementById('progressBar').style.width = percentage + '%';
    document.getElementById('progressBar').textContent = percentage + '%';
    
    // Actualizar iconos de estado
    Object.keys(installationStatus).forEach(key => {
        const element = document.getElementById('status-' + key);
        if (element) {
            element.innerHTML = installationStatus[key] ? 
                '🟢 ' + element.textContent.substring(2) : 
                '🔴 ' + element.textContent.substring(2);
        }
    });
    
    // Mostrar acciones finales si todo está completo
    if (percentage === 100) {
        document.getElementById('finalActions').style.display = 'block';
    }
}

// Crear estructura faltante
async function createMissingStructure() {
    try {
        const response = await fetch('install_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=create_structure'
        });
        
        const text = await response.text();
        document.getElementById('structureResult').innerHTML = text;
        document.getElementById('structureResult').style.display = 'block';
        
        if (text.includes('✅')) {
            installationStatus.tables = true;
            updateProgress();
        }
    } catch (error) {
        document.getElementById('structureResult').innerHTML = 
            '<div class="error">❌ Error: ' + error.message + '</div>';
        document.getElementById('structureResult').style.display = 'block';
    }
}

// Probar APIs
async function testAPIs() {
    const apiResults = document.getElementById('apiResults');
    apiResults.innerHTML = '<div class="info">⏳ Probando APIs...</div>';
    
    const apis = [
        { url: 'api/get_users.php', name: 'Usuarios' },
        { url: 'api/get_companies.php', name: 'Empresas' },
        { url: 'api/get_departments.php', name: 'Departamentos' },
        { url: 'api/get_document_types.php', name: 'Tipos de Documentos' }
    ];
    
    let results = '<h4>Resultados de pruebas de API:</h4>';
    let allPassed = true;
    
    for (const api of apis) {
        try {
            const response = await fetch(api.url);
            const data = await response.json();
            
            if (data.success !== false) {
                results += `<div class="success">✅ ${api.name}: OK</div>`;
            } else {
                results += `<div class="error">❌ ${api.name}: ${data.message || 'Error'}</div>`;
                allPassed = false;
            }
        } catch (error) {
            results += `<div class="error">❌ ${api.name}: Error de conexión</div>`;
            allPassed = false;
        }
    }
    
    apiResults.innerHTML = results;
    
    if (allPassed) {
        installationStatus.api = true;
        updateProgress();
    }
}

// Crear grupos predeterminados
async function createDefaultGroups() {
    try {
        const response = await fetch('install_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=create_default_groups'
        });
        
        const text = await response.text();
        document.getElementById('groupsResult').innerHTML = text;
        
        if (text.includes('✅')) {
            installationStatus.config = true;
            updateProgress();
        }
    } catch (error) {
        document.getElementById('groupsResult').innerHTML = 
            '<div class="error">❌ Error: ' + error.message + '</div>';
    }
}

// Descargar archivos faltantes
function downloadFiles() {
    alert('Por favor, copie los archivos desde los artifacts generados en la conversación anterior.');
}

// Inicializar progreso
updateProgress();
</script>

</body>
</html>