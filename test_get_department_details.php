<?php
// test_get_department_details.php
// Script para probar el archivo get_department_details.php directamente

echo "<h2>🧪 Test de get_department_details.php</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; }
</style>";

echo "<h3>1. Probando acceso directo al archivo</h3>";

// Test 1: Verificar que el archivo existe
$filePath = 'modules/departments/actions/get_department_details.php';
if (file_exists($filePath)) {
    echo "<span class='success'>✅ Archivo existe: $filePath</span><br>";
} else {
    echo "<span class='error'>❌ Archivo NO existe: $filePath</span><br>";
    exit;
}

echo "<h3>2. Probando consulta directa</h3>";

try {
    // Incluir dependencias
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    
    // Obtener primer departamento para test
    $testDept = fetchOne("SELECT id FROM departments LIMIT 1");
    
    if ($testDept) {
        $deptId = $testDept['id'];
        echo "<span class='info'>📋 Usando departamento ID: $deptId</span><br>";
        
        // Probar la consulta que está fallando
        $department = fetchOne(
            "SELECT d.*, 
                    c.name as company_name,
                    c.id as company_id,
                    CONCAT(u.first_name, ' ', u.last_name) as manager_name,
                    u.email as manager_email,
                    u.id as manager_id,
                    (SELECT COUNT(*) FROM users WHERE department_id = d.id AND status = 'active') as total_users,
                    (SELECT COUNT(*) FROM users WHERE department_id = d.id AND status = 'inactive') as inactive_users
             FROM departments d 
             LEFT JOIN companies c ON d.company_id = c.id 
             LEFT JOIN users u ON d.manager_id = u.id 
             WHERE d.id = :id",
            ['id' => $deptId]
        );
        
        if ($department) {
            echo "<span class='success'>✅ Consulta principal exitosa</span><br>";
            echo "<div class='code'>";
            print_r($department);
            echo "</div>";
        } else {
            echo "<span class='error'>❌ Consulta principal falló</span><br>";
        }
        
        // Probar consulta de usuarios
        $users = fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.status, u.created_at,
                    CASE 
                        WHEN u.id = :manager_id THEN 'Manager'
                        ELSE u.role 
                    END as department_role
             FROM users u 
             WHERE u.department_id = :department_id 
             ORDER BY 
                CASE WHEN u.id = :manager_id_order THEN 0 ELSE 1 END,
                u.first_name, u.last_name",
            [
                'department_id' => $deptId,
                'manager_id' => $department['manager_id'] ?? 0,
                'manager_id_order' => $department['manager_id'] ?? 0
            ]
        );
        
        echo "<span class='info'>👥 Usuarios encontrados: " . (is_array($users) ? count($users) : 'Error') . "</span><br>";
        
    } else {
        echo "<span class='error'>❌ No hay departamentos en la BD</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error en test: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "<h3>3. Simulando llamada AJAX</h3>";

// Simular la llamada que hace JavaScript
$deptId = $_GET['test_id'] ?? 1;
echo "<span class='info'>🌐 Simulando: modules/departments/actions/get_department_details.php?id=$deptId</span><br>";

// Capturar la salida del archivo
ob_start();
$_GET['id'] = $deptId;
$_SERVER['REQUEST_METHOD'] = 'GET';

// Simular que hay sesión de admin
if (!isset($_SESSION)) {
    session_start();
}

try {
    // Incluir el archivo que está fallando
    include 'modules/departments/actions/get_department_details.php';
    $output = ob_get_contents();
} catch (Exception $e) {
    $output = "ERROR: " . $e->getMessage();
}
ob_end_clean();

echo "<h4>📤 Salida del archivo:</h4>";
echo "<div class='code'>$output</div>";

// Verificar si es JSON válido
$decoded = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<span class='success'>✅ Salida es JSON válido</span><br>";
    echo "<h4>📊 Datos decodificados:</h4>";
    echo "<div class='code'>" . print_r($decoded, true) . "</div>";
} else {
    echo "<span class='error'>❌ Salida NO es JSON válido</span><br>";
    echo "<span class='error'>Error JSON: " . json_last_error_msg() . "</span><br>";
}

echo "<h3>4. 🔧 Recomendaciones</h3>";
echo "<div class='code'>";
echo "Si hay errores:\n";
echo "1. Verificar que config/session.php funcione correctamente\n";
echo "2. Verificar que no haya output antes del JSON (echo, print_r, etc.)\n";
echo "3. Verificar que las consultas SQL sean correctas\n";
echo "4. Verificar que no haya errores de PHP que generen HTML\n";
echo "5. Verificar permisos del usuario (debe ser admin)\n";
echo "</div>";

echo "<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>