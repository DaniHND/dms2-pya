<?php
/*
 * test_toggle_status.php
 * Archivo de prueba para debuggear el toggle status
 * Colocar en la raíz del proyecto
 */

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Toggle Status</title>
</head>
<body>
    <h1>Test de Toggle Status</h1>
    
    <h2>Prueba Manual</h2>
    <form method="POST" action="">
        <label>Group ID: <input type="number" name="group_id" value="5" required></label><br><br>
        <label>Nuevo Estado: 
            <select name="status" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </label><br><br>
        <button type="submit">Enviar por POST</button>
    </form>
    
    <h2>Prueba JavaScript</h2>
    <button onclick="testFetch()">Probar Fetch</button>
    <button onclick="testToggleAction()">Probar Toggle Real</button>
    
    <div id="result"></div>
    
    <script>
    function testFetch() {
        console.log('=== TESTING FETCH ===');
        
        const formData = new FormData();
        formData.append('group_id', '5');
        formData.append('status', 'inactive');
        
        console.log('Enviando a: test_toggle_status.php');
        
        fetch('test_toggle_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Response:', text);
            document.getElementById('result').innerHTML = '<pre>' + text + '</pre>';
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('result').innerHTML = 'Error: ' + error.message;
        });
    }
    
    function testToggleAction() {
        console.log('=== TESTING REAL TOGGLE ===');
        
        const formData = new FormData();
        formData.append('group_id', '5');
        formData.append('status', 'inactive');
        
        console.log('Enviando a: modules/groups/actions/toggle_group_status.php');
        
        fetch('modules/groups/actions/toggle_group_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Response:', text);
            document.getElementById('result').innerHTML = '<pre>' + text + '</pre>';
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('result').innerHTML = 'Error: ' + error.message;
        });
    }
    </script>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Resultado del POST:</h2>";
    echo "<pre>";
    
    header('Content-Type: text/plain');
    
    echo "=== DEBUG TOGGLE STATUS ===\n";
    echo "Método: " . $_SERVER['REQUEST_METHOD'] . "\n";
    echo "POST data: " . print_r($_POST, true) . "\n";
    echo "Raw input: " . file_get_contents('php://input') . "\n";
    
    $groupId = $_POST['group_id'] ?? null;
    $newStatus = $_POST['status'] ?? null;
    
    echo "Group ID recibido: " . var_export($groupId, true) . "\n";
    echo "Status recibido: " . var_export($newStatus, true) . "\n";
    
    if ($groupId && $newStatus) {
        try {
            require_once 'config/database.php';
            $database = new Database();
            $pdo = $database->getConnection();
            
            if ($pdo) {
                echo "✅ Conexión a BD exitosa\n";
                
                $query = "SELECT id, name, status, is_system_group FROM user_groups WHERE id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$groupId]);
                $group = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($group) {
                    echo "✅ Grupo encontrado: " . print_r($group, true) . "\n";
                    
                    $updateQuery = "UPDATE user_groups SET status = ? WHERE id = ?";
                    $updateStmt = $pdo->prepare($updateQuery);
                    $result = $updateStmt->execute([$newStatus, $groupId]);
                    
                    if ($result) {
                        echo "✅ Actualización exitosa\n";
                        echo "Filas afectadas: " . $updateStmt->rowCount() . "\n";
                        echo "JSON result: " . json_encode(['success' => true, 'message' => 'Actualizado correctamente']) . "\n";
                    } else {
                        echo "❌ Error en actualización\n";
                        echo "Error info: " . print_r($updateStmt->errorInfo(), true) . "\n";
                    }
                } else {
                    echo "❌ Grupo no encontrado con ID: $groupId\n";
                }
            } else {
                echo "❌ No se pudo conectar a la BD\n";
            }
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Datos incompletos\n";
    }
    
    echo "</pre>";
    exit;
}
?>

</body>
</html>