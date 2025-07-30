<?php
// check_departments_table.php
// Script para verificar si la tabla departments ya existe y tiene la estructura correcta

require_once 'config/database.php';

echo "<h2>🔍 Verificación de Tabla Departments - DMS2</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; }
</style>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "<span class='success'>✅ Conexión a base de datos exitosa</span><br><br>";
    
    // 1. Verificar si la tabla departments existe
    echo "<h3>1. ¿Existe la tabla departments?</h3>";
    $query = "SHOW TABLES LIKE 'departments'";
    $result = $conn->query($query);
    
    if ($result && $result->rowCount() > 0) {
        echo "<span class='success'>✅ SÍ - La tabla 'departments' ya existe</span><br><br>";
        
        // 2. Mostrar estructura actual
        echo "<h3>2. Estructura actual de la tabla:</h3>";
        $columns = $conn->query("DESCRIBE departments")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Clave</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . ($column['Extra'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 3. Verificar campos requeridos para el módulo
        echo "<h3>3. ¿Tiene todos los campos necesarios?</h3>";
        $requiredFields = [
            'id' => 'Clave primaria',
            'name' => 'Nombre del departamento',
            'description' => 'Descripción (opcional)',
            'company_id' => 'ID de la empresa',
            'manager_id' => 'ID del manager (opcional)',
            'status' => 'Estado (active/inactive)',
            'created_at' => 'Fecha de creación',
            'updated_at' => 'Fecha de actualización'
        ];
        
        $existingFields = array_column($columns, 'Field');
        $missingFields = [];
        
        foreach ($requiredFields as $field => $description) {
            if (in_array($field, $existingFields)) {
                echo "<span class='success'>✅ $field</span> - $description<br>";
            } else {
                echo "<span class='error'>❌ $field</span> - $description <strong>(FALTA)</strong><br>";
                $missingFields[] = $field;
            }
        }
        
        // 4. Verificar datos existentes
        echo "<br><h3>4. Datos existentes en la tabla:</h3>";
        $dataCount = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch(PDO::FETCH_ASSOC);
        echo "Total de departamentos: <strong>" . $dataCount['count'] . "</strong><br>";
        
        if ($dataCount['count'] > 0) {
            echo "<br><strong>Primeros 5 departamentos:</strong><br>";
            $sampleData = $conn->query("SELECT * FROM departments LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            echo "<table>";
            $headers = array_keys($sampleData[0]);
            echo "<tr>";
            foreach ($headers as $header) {
                echo "<th>$header</th>";
            }
            echo "</tr>";
            foreach ($sampleData as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // 5. Verificar relaciones con otras tablas
        echo "<br><h3>5. Verificación de relaciones:</h3>";
        
        // Verificar si users tiene department_id
        $userColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'department_id'")->fetchAll();
        if (!empty($userColumns)) {
            echo "<span class='success'>✅ Tabla 'users' tiene columna 'department_id'</span><br>";
            
            // Contar usuarios con departamento asignado
            $usersWithDept = $conn->query("SELECT COUNT(*) as count FROM users WHERE department_id IS NOT NULL")->fetch(PDO::FETCH_ASSOC);
            echo "<span class='info'>📊 Usuarios con departamento asignado: " . $usersWithDept['count'] . "</span><br>";
        } else {
            echo "<span class='error'>❌ Tabla 'users' NO tiene columna 'department_id'</span><br>";
            echo "<span class='warning'>⚠️ Necesitas agregar esta columna para la relación</span><br>";
        }
        
        // Verificar tabla companies
        $companiesExist = $conn->query("SHOW TABLES LIKE 'companies'")->rowCount() > 0;
        if ($companiesExist) {
            echo "<span class='success'>✅ Tabla 'companies' existe</span><br>";
        } else {
            echo "<span class='error'>❌ Tabla 'companies' no existe</span><br>";
        }
        
        // 6. Conclusión y recomendaciones
        echo "<br><h3>6. 🎯 Conclusión:</h3>";
        
        if (empty($missingFields)) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;'>";
            echo "<span class='success'>🎉 ¡PERFECTO! Tu tabla departments ya tiene todo lo necesario</span><br>";
            echo "<strong>✅ NO necesitas ejecutar ningún SQL</strong><br>";
            echo "<strong>✅ Puedes usar el módulo directamente</strong><br>";
            echo "</div>";
            
            echo "<br><h4>📋 Pasos siguientes:</h4>";
            echo "<ol>";
            echo "<li>✅ Copiar los archivos del módulo a <code>modules/departments/</code></li>";
            echo "<li>✅ Copiar los archivos CSS y JS a <code>assets/</code></li>";
            echo "<li>✅ Actualizar el sidebar (ya hecho)</li>";
            echo "<li>✅ Probar el módulo en <code>modules/departments/index.php</code></li>";
            echo "</ol>";
            
        } else {
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;'>";
            echo "<span class='warning'>⚠️ Faltan algunos campos en tu tabla</span><br>";
            echo "<strong>Campos faltantes:</strong> " . implode(', ', $missingFields) . "<br>";
            echo "</div>";
            
            echo "<br><h4>🔧 SQL para agregar campos faltantes:</h4>";
            echo "<div class='code'>";
            foreach ($missingFields as $field) {
                switch ($field) {
                    case 'description':
                        echo "ALTER TABLE departments ADD COLUMN description TEXT AFTER name;<br>";
                        break;
                    case 'manager_id':
                        echo "ALTER TABLE departments ADD COLUMN manager_id INT NULL AFTER company_id;<br>";
                        break;
                    case 'status':
                        echo "ALTER TABLE departments ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER manager_id;<br>";
                        break;
                    case 'updated_at':
                        echo "ALTER TABLE departments ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;<br>";
                        break;
                }
            }
            echo "</div>";
        }
        
    } else {
        echo "<span class='error'>❌ NO - La tabla 'departments' no existe</span><br><br>";
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545;'>";
        echo "<span class='error'>🚨 NECESITAS crear la tabla departments</span><br>";
        echo "<strong>✅ SÍ necesitas ejecutar el SQL completo</strong><br>";
        echo "</div>";
        
        echo "<br><h4>📋 Pasos siguientes:</h4>";
        echo "<ol>";
        echo "<li>🔧 Ejecutar el script SQL completo para crear la tabla</li>";
        echo "<li>📁 Copiar los archivos del módulo</li>";
        echo "<li>🧭 Actualizar el sidebar</li>";
        echo "<li>🧪 Probar el módulo</li>";
        echo "</ol>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "<br><hr>";
echo "<p><em>Verificación completada: " . date('Y-m-d H:i:s') . "</em></p>";
echo "<p><strong>💡 Consejo:</strong> Ejecuta este script para verificar antes de instalar el módulo</p>";
?>