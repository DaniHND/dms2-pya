<?php
/*
 * debug_toggle_status.php
 * Archivo de diagnóstico para el problema del toggle status
 * Colocar en la raíz del proyecto
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Toggle Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        .code { background: #f8f9fa; padding: 15px; border-radius: 4px; border-left: 4px solid #007bff; font-family: monospace; white-space: pre-wrap; margin: 10px 0; }
        .test-button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .test-button:hover { background: #0056b3; }
        .result-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 Diagnóstico: Toggle Group Status</h1>
    <p><strong>Fecha:</strong> <?= date('Y-m-d H:i:s') ?></p>

    <!-- 1. VERIFICACIÓN DE ARCHIVOS -->
    <div class="section">
        <h2>📁 1. Verificación de Archivos</h2>
        
        <?php
        $files = [
            'modules/groups/actions/toggle_group_status.php' => 'Archivo principal',
            'config/database.php' => 'Configuración de BD',
            'config/session.php' => 'Gestión de sesiones',
            'modules/groups/index.php' => 'Página principal grupos'
        ];
        
        foreach ($files as $file => $desc) {
            if (file_exists($file)) {
                $size = round(filesize($file) / 1024, 2);
                echo "<div class='success'>✅ $file ($size KB) - $desc</div>";
            } else {
                echo "<div class='error'>❌ $file - $desc (FALTA)</div>";
            }
        }
        ?>
    </div>

    <!-- 2. VERIFICACIÓN DE BASE DE DATOS -->
    <div class="section">
        <h2>🗄️ 2. Verificación de Base de Datos</h2>
        
        <?php
        try {
            require_once 'config/database.php';
            $database = new Database();
            $pdo = $database->getConnection();
            
            echo "<div class='success'>✅ Conexión a BD exitosa</div>";
            
            // Verificar tabla user_groups
            $query = "DESCRIBE user_groups";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>📋 Estructura de tabla user_groups:</h3>";
            echo "<table>";
            echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td>{$col['Field']}</td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Contar grupos
            $countQuery = "SELECT COUNT(*) as total, status, COUNT(*) as count FROM user_groups WHERE deleted_at IS NULL GROUP BY status";
            $countStmt = $pdo->prepare($countQuery);
            $countStmt->execute();
            $counts = $countStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>📊 Estadísticas de grupos:</h3>";
            foreach ($counts as $count) {
                echo "<div class='info'>Estado '{$count['status']}': {$count['count']} grupos</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error de BD: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    </div>

    <!-- 3. VERIFICACIÓN DE SESIÓN -->
    <div class="section">
        <h2>👤 3. Verificación de Sesión</h2>
        
        <?php
        try {
            require_once 'config/session.php';
            
            if (class_exists('SessionManager')) {
                echo "<div class='success'>✅ Clase SessionManager encontrada</div>";
                
                if (SessionManager::isLoggedIn()) {
                    $user = SessionManager::getCurrentUser();
                    echo "<div class='success'>✅ Usuario logueado: {$user['username']} (Rol: {$user['role']})</div>";
                    
                    if ($user['role'] === 'admin') {
                        echo "<div class='success'>✅ Permisos de administrador confirmados</div>";
                    } else {
                        echo "<div class='warning'>⚠️ Usuario no es administrador</div>";
                    }
                } else {
                    echo "<div class='warning'>⚠️ Usuario no está logueado</div>";
                }
            } else {
                echo "<div class='error'>❌ Clase SessionManager no encontrada</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error de sesión: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    </div>

    <!-- 4. PRUEBAS MANUALES -->
    <div class="section">
        <h2>🧪 4. Pruebas Manuales</h2>
        
        <h3>A) Acceso directo al archivo</h3>
        <button class="test-button" onclick="testDirectAccess()">Probar acceso directo (GET)</button>
        <div id="direct-result" class="result-box" style="display: none;"></div>
        
        <h3>B) Envío POST manual</h3>
        <form id="manual-form">
            <label>Group ID: <input type="number" id="group_id" value="1" min="1"></label><br><br>
            <label>Status: 
                <select id="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </label><br><br>
            <button type="button" class="test-button" onclick="testManualPost()">Enviar POST</button>
        </form>
        <div id="manual-result" class="result-box" style="display: none;"></div>
        
        <h3>C) Simular llamada desde JavaScript del módulo</h3>
        <button class="test-button" onclick="testModuleCall()">Simular toggleGroupStatus()</button>
        <div id="module-result" class="result-box" style="display: none;"></div>
    </div>

    <!-- 5. VERIFICACIÓN DE LOGS -->
    <div class="section">
        <h2>📋 5. Logs de PHP</h2>
        
        <?php
        $logPaths = [
            '/var/log/apache2/error.log',
            '/var/log/php_errors.log',
            'C:\\xampp\\apache\\logs\\error.log',
            ini_get('error_log')
        ];
        
        echo "<p>Ubicaciones comunes de logs:</p>";
        foreach ($logPaths as $path) {
            if ($path && file_exists($path)) {
                echo "<div class='success'>✅ $path (existe)</div>";
            } else {
                echo "<div class='info'>ℹ️ $path (no encontrado)</div>";
            }
        }
        
        echo "<div class='info'>💡 Log actual configurado: " . (ini_get('error_log') ?: 'No configurado') . "</div>";
        ?>
    </div>

</div>

<script>
function showResult(elementId, content, isError = false) {
    const element = document.getElementById(elementId);
    element.style.display = 'block';
    element.innerHTML = `<pre style="color: ${isError ? '#dc3545' : '#28a745'}">${content}</pre>`;
}

async function testDirectAccess() {
    console.log('=== TEST: Acceso directo ===');
    
    try {
        const response = await fetch('modules/groups/actions/toggle_group_status.php');
        const text = await response.text();
        
        console.log('Response status:', response.status);
        console.log('Response:', text);
        
        showResult('direct-result', `Status: ${response.status}\n\nRespuesta:\n${text}`);
    } catch (error) {
        console.error('Error:', error);
        showResult('direct-result', `Error: ${error.message}`, true);
    }
}

async function testManualPost() {
    console.log('=== TEST: POST manual ===');
    
    const groupId = document.getElementById('group_id').value;
    const status = document.getElementById('status').value;
    
    const formData = new FormData();
    formData.append('group_id', groupId);
    formData.append('status', status);
    
    console.log('Enviando:', { group_id: groupId, status: status });
    
    try {
        const response = await fetch('modules/groups/actions/toggle_group_status.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        
        console.log('Response status:', response.status);
        console.log('Response:', text);
        
        let result = `Status: ${response.status}\n\nRespuesta:\n${text}`;
        
        try {
            const json = JSON.parse(text);
            result += `\n\nJSON parseado:\n${JSON.stringify(json, null, 2)}`;
        } catch (e) {
            result += `\n\nNo es JSON válido`;
        }
        
        showResult('manual-result', result);
    } catch (error) {
        console.error('Error:', error);
        showResult('manual-result', `Error: ${error.message}`, true);
    }
}

async function testModuleCall() {
    console.log('=== TEST: Llamada del módulo ===');
    
    // Simular la función del módulo groups
    const groupId = 1;
    const currentStatus = 'active';
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    
    const formData = new FormData();
    formData.append('group_id', groupId);
    formData.append('status', newStatus);
    
    console.log('Simulando toggleGroupStatus:', { groupId, newStatus });
    
    try {
        const response = await fetch('modules/groups/actions/toggle_group_status.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        
        console.log('Response status:', response.status);
        console.log('Response:', text);
        
        let result = `Simulando: toggleGroupStatus(${groupId}, '${currentStatus}')\n`;
        result += `Status: ${response.status}\n\nRespuesta:\n${text}`;
        
        try {
            const json = JSON.parse(text.trim());
            result += `\n\nJSON parseado:\n${JSON.stringify(json, null, 2)}`;
            
            if (json.success) {
                result += `\n\n✅ ÉXITO: ${json.message}`;
            } else {
                result += `\n\n❌ ERROR: ${json.message}`;
            }
        } catch (e) {
            result += `\n\n⚠️ Respuesta no es JSON válido`;
        }
        
        showResult('module-result', result);
    } catch (error) {
        console.error('Error:', error);
        showResult('module-result', `Error: ${error.message}`, true);
    }
}

// Auto-ejecutar algunas pruebas
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 Debug Toggle Status cargado');
    console.log('💡 Usa las funciones de prueba para diagnosticar el problema');
});
</script>

</body>
</html>