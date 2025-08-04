<?php
/*
 * debug_create_group.php
 * Diagnóstico para creación de grupos
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug - Crear Grupo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .test { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .code { background: #e9ecef; padding: 10px; border-radius: 3px; font-family: monospace; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>

<h1>🔍 Debug - Crear Grupo</h1>

<?php
// Verificaciones previas
echo "<div class='test'>";
echo "<h3>1. Verificaciones del Sistema</h3>";

// Verificar archivos
if (file_exists('modules/groups/actions/create_group.php')) {
    echo "<div class='success'>✅ Archivo create_group.php existe</div>";
} else {
    echo "<div class='error'>❌ Archivo create_group.php NO existe</div>";
}

// Verificar configuración
try {
    require_once 'config/database.php';
    require_once 'config/session.php';
    
    $database = new Database();
    $pdo = $database->getConnection();
    echo "<div class='success'>✅ Conexión a BD exitosa</div>";
    
    if (SessionManager::isLoggedIn()) {
        $user = SessionManager::getCurrentUser();
        echo "<div class='success'>✅ Usuario logueado: {$user['username']} (Rol: {$user['role']})</div>";
    } else {
        echo "<div class='error'>❌ Usuario NO está logueado</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

// Verificar estructura de tabla
echo "<div class='test'>";
echo "<h3>2. Estructura de Tabla user_groups</h3>";

try {
    $columns = $pdo->query("DESCRIBE user_groups")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Error obteniendo estructura: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";
?>

<!-- Formulario de prueba -->
<div class="test">
    <h3>3. Prueba de Creación de Grupo</h3>
    
    <form id="testForm">
        <div class="form-group">
            <label for="testName">Nombre del Grupo:</label>
            <input type="text" id="testName" name="name" value="Grupo de Prueba <?= time() ?>" required>
        </div>
        
        <div class="form-group">
            <label for="testDescription">Descripción:</label>
            <textarea id="testDescription" name="description" rows="3">Grupo creado para pruebas del sistema</textarea>
        </div>
        
        <div class="form-group">
            <label for="testStatus">Estado:</label>
            <select id="testStatus" name="status">
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Permisos Básicos:</label>
            <label><input type="checkbox" id="permDownload" checked> Descargar</label><br>
            <label><input type="checkbox" id="permCreate" checked> Crear</label><br>
            <label><input type="checkbox" id="permEdit"> Editar</label>
        </div>
        
        <button type="button" onclick="testCreateGroup()">Crear Grupo de Prueba</button>
    </form>
    
    <div id="testResult" style="margin-top: 20px;"></div>
</div>

<script>
async function testCreateGroup() {
    const resultDiv = document.getElementById('testResult');
    resultDiv.innerHTML = '<div>⏳ Creando grupo...</div>';
    
    try {
        const formData = new FormData();
        formData.append('name', document.getElementById('testName').value);
        formData.append('description', document.getElementById('testDescription').value);
        formData.append('status', document.getElementById('testStatus').value);
        
        // Permisos básicos
        const permissions = {
            view: true,
            download: document.getElementById('permDownload').checked,
            create: document.getElementById('permCreate').checked,
            edit: document.getElementById('permEdit').checked
        };
        formData.append('basic_permissions', JSON.stringify(permissions));
        
        console.log('Enviando datos:', {
            name: document.getElementById('testName').value,
            description: document.getElementById('testDescription').value,
            status: document.getElementById('testStatus').value,
            permissions: permissions
        });
        
        const response = await fetch('modules/groups/actions/create_group.php', {
            method: 'POST',
            body: formData
        });
        
        console.log('Response status:', response.status);
        
        const text = await response.text();
        console.log('Response text:', text);
        
        let result = `<div class="code">Status: ${response.status}<br>Response: ${text}</div>`;
        
        try {
            const json = JSON.parse(text);
            if (json.success) {
                result += `<div class="success">✅ ÉXITO: ${json.message}</div>`;
                result += `<div class="code">ID del grupo: ${json.group_id}</div>`;
            } else {
                result += `<div class="error">❌ ERROR: ${json.message}</div>`;
                if (json.error_code) {
                    result += `<div class="code">Código de error: ${json.error_code}</div>`;
                }
            }
        } catch (e) {
            result += `<div class="error">❌ Respuesta no es JSON válido</div>`;
        }
        
        resultDiv.innerHTML = result;
        
    } catch (error) {
        console.error('Error:', error);
        resultDiv.innerHTML = `<div class="error">❌ Error: ${error.message}</div>`;
    }
}
</script>

</body>
</html>