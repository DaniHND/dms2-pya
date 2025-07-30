<?php
// check_document_types_existing.php
// Script para verificar la tabla document_types existente y completar configuración

echo "<h1>🔍 Verificación Tabla document_types Existente - DMS2</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; }
</style>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "<span class='success'>✅ Conexión a base de datos exitosa</span><br><br>";
    
    // 1. Verificar que la tabla document_types existe
    echo "<div class='section'>";
    echo "<h2>1. 📊 Estado de la tabla document_types</h2>";
    
    $query = "SHOW TABLES LIKE 'document_types'";
    $result = $conn->query($query);
    
    if ($result && $result->rowCount() > 0) {
        echo "<span class='success'>✅ Tabla 'document_types' encontrada</span><br><br>";
        
        // Mostrar estructura actual
        echo "<h3>Estructura actual:</h3>";
        $columns = $conn->query("DESCRIBE document_types")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Clave</th><th>Default</th><th>Extra</th></tr>";
        
        $requiredFields = ['id', 'name', 'description', 'status', 'created_at', 'updated_at'];
        $existingFields = [];
        
        foreach ($columns as $column) {
            $existingFields[] = $column['Field'];
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
        
        // Verificar campos requeridos
        echo "<h3>Verificación de campos requeridos:</h3>";
        $missingFields = array_diff($requiredFields, $existingFields);
        
        if (empty($missingFields)) {
            echo "<span class='success'>✅ Todos los campos requeridos están presentes</span><br>";
        } else {
            echo "<span class='warning'>⚠️ Faltan campos: " . implode(', ', $missingFields) . "</span><br>";
        }
        
    } else {
        echo "<span class='error'>❌ Tabla 'document_types' NO existe</span><br>";
        echo "<span class='info'>💡 Se necesita crear la tabla primero</span><br>";
        echo "</div>";
        exit();
    }
    echo "</div>";
    
    // 2. Verificar datos existentes
    echo "<div class='section'>";
    echo "<h2>2. 📋 Datos existentes en document_types</h2>";
    
    $count = $conn->query("SELECT COUNT(*) FROM document_types")->fetchColumn();
    echo "<span class='info'>📊 Total de tipos de documentos: $count</span><br><br>";
    
    if ($count > 0) {
        // Mostrar tipos existentes
        $types = $conn->query("SELECT id, name, description, status, created_at FROM document_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Tipos existentes:</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Creado</th></tr>";
        foreach ($types as $type) {
            echo "<tr>";
            echo "<td>" . $type['id'] . "</td>";
            echo "<td>" . htmlspecialchars($type['name']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($type['description'] ?? '', 0, 50)) . (strlen($type['description'] ?? '') > 50 ? '...' : '') . "</td>";
            echo "<td>" . htmlspecialchars($type['status']) . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($type['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Estadísticas por estado
        $stats = $conn->query("SELECT status, COUNT(*) as count FROM document_types GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Estadísticas por estado:</h3>";
        foreach ($stats as $stat) {
            echo "<span class='info'>• {$stat['status']}: {$stat['count']} tipos</span><br>";
        }
        
    } else {
        echo "<span class='warning'>⚠️ No hay tipos de documentos creados aún</span><br>";
        echo "<span class='info'>💡 Se pueden agregar tipos desde el módulo</span><br>";
    }
    echo "</div>";
    
    // 3. Verificar relación con tabla documents
    echo "<div class='section'>";
    echo "<h2>3. 🔗 Verificar relación con tabla documents</h2>";
    
    // Verificar si existe la columna document_type_id en documents
    $query = "SHOW COLUMNS FROM documents LIKE 'document_type_id'";
    $result = $conn->query($query);
    
    if ($result && $result->rowCount() > 0) {
        echo "<span class='success'>✅ Columna 'document_type_id' existe en tabla documents</span><br>";
        
        // Verificar la clave foránea
        $query = "SELECT COUNT(*) as count FROM information_schema.table_constraints 
                  WHERE table_schema = DATABASE() 
                  AND table_name = 'documents' 
                  AND constraint_name LIKE '%document_type%'";
        $result = $conn->query($query)->fetchColumn();
        
        if ($result > 0) {
            echo "<span class='success'>✅ Clave foránea encontrada entre documents y document_types</span><br>";
        } else {
            echo "<span class='warning'>⚠️ No se encontró clave foránea entre documents y document_types</span><br>";
            echo "<div class='code'>ALTER TABLE documents ADD CONSTRAINT fk_documents_document_type FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE SET NULL;</div>";
        }
        
        // Estadísticas de uso
        $documentsWithType = $conn->query("SELECT COUNT(*) FROM documents WHERE document_type_id IS NOT NULL")->fetchColumn();
        $documentsWithoutType = $conn->query("SELECT COUNT(*) FROM documents WHERE document_type_id IS NULL")->fetchColumn();
        
        echo "<br><h3>Uso en documentos:</h3>";
        echo "<span class='info'>📄 Documentos con tipo asignado: $documentsWithType</span><br>";
        echo "<span class='info'>📄 Documentos sin tipo: $documentsWithoutType</span><br>";
        
        if ($documentsWithoutType > 0) {
            echo "<span class='warning'>⚠️ Hay documentos sin tipo asignado</span><br>";
        }
        
    } else {
        echo "<span class='error'>❌ Columna 'document_type_id' NO existe en tabla documents</span><br>";
        echo "<span class='info'>💡 Comando para agregar la columna:</span><br>";
        echo "<div class='code'>ALTER TABLE documents ADD COLUMN document_type_id INT NULL AFTER company_id;</div>";
    }
    echo "</div>";
    
    // 4. Verificar archivos del módulo
    echo "<div class='section'>";
    echo "<h2>4. 📁 Verificar archivos del módulo</h2>";
    
    $moduleFiles = [
        'modules/document-types/index.php' => 'Página principal',
        'modules/document-types/actions/create_document_type.php' => 'Crear tipo',
        'modules/document-types/actions/get_document_type_details.php' => 'Obtener detalles',
        'modules/document-types/actions/update_document_type.php' => 'Actualizar tipo',
        'modules/document-types/actions/toggle_document_type_status.php' => 'Cambiar estado',
        'assets/css/document-types.css' => 'Estilos del módulo',
        'assets/js/document-types.js' => 'JavaScript del módulo'
    ];
    
    $missingFiles = [];
    foreach ($moduleFiles as $file => $description) {
        if (file_exists($file)) {
            $size = round(filesize($file) / 1024, 2);
            echo "<span class='success'>✅ $file</span> - $description ($size KB)<br>";
        } else {
            echo "<span class='error'>❌ $file</span> - $description <strong>(FALTA)</strong><br>";
            $missingFiles[] = $file;
        }
    }
    
    if (empty($missingFiles)) {
        echo "<br><span class='success'>🎉 Todos los archivos del módulo están presentes!</span>";
    } else {
        echo "<br><span class='warning'>⚠️ Faltan " . count($missingFiles) . " archivos del módulo</span>";
    }
    echo "</div>";
    
    // 5. Verificar acceso al módulo
    echo "<div class='section'>";
    echo "<h2>5. 🌐 Acceso al módulo</h2>";
    
    // Verificar que el sidebar tenga la entrada
    if (file_exists('includes/sidebar.php')) {
        $sidebarContent = file_get_contents('includes/sidebar.php');
        if (strpos($sidebarContent, 'document-types') !== false) {
            echo "<span class='success'>✅ Entrada del módulo encontrada en sidebar</span><br>";
        } else {
            echo "<span class='warning'>⚠️ Entrada del módulo NO encontrada en sidebar</span><br>";
        }
    }
    
    echo "<br><strong>URL para acceder:</strong><br>";
    echo "<a href='modules/document-types/index.php' target='_blank'>modules/document-types/index.php</a><br>";
    echo "</div>";
    
    // 6. Comandos SQL útiles si se necesitan
    echo "<div class='section'>";
    echo "<h2>6. 🔧 Comandos SQL útiles (solo si es necesario)</h2>";
    
    echo "<h3>Agregar tipos de documentos básicos (si no existen):</h3>";
    echo "<div class='code'>";
    echo "INSERT IGNORE INTO document_types (name, description, status) VALUES<br>";
    echo "('Contrato', 'Contratos comerciales y laborales', 'active'),<br>";
    echo "('Factura', 'Facturas de compra y venta', 'active'),<br>";
    echo "('Reporte', 'Reportes internos y operativos', 'active'),<br>";
    echo "('Certificado', 'Certificados oficiales', 'active'),<br>";
    echo "('Correspondencia', 'Cartas y comunicaciones', 'active');<br>";
    echo "</div>";
    
    if ($documentsWithoutType > 0) {
        echo "<h3>Asignar tipo por defecto a documentos sin tipo:</h3>";
        echo "<div class='code'>";
        echo "UPDATE documents SET document_type_id = (SELECT id FROM document_types WHERE name = 'Reporte' LIMIT 1) WHERE document_type_id IS NULL;<br>";
        echo "</div>";
    }
    echo "</div>";
    
    // 7. Resumen final
    echo "<div class='section'>";
    echo "<h2>7. 📋 Resumen Final</h2>";
    
    $issues = count($missingFiles);
    if ($documentsWithoutType > 0) $issues++;
    
    if ($issues == 0) {
        echo "<span class='success'>🎉 ¡Módulo de Tipos de Documentos listo para usar!</span><br>";
        echo "<span class='info'>✨ Todo está configurado correctamente</span><br>";
    } else {
        echo "<span class='warning'>⚠️ Se encontraron $issues problema(s) menores que se pueden resolver</span><br>";
    }
    
    echo "<br><strong>Próximos pasos:</strong><br>";
    echo "1. Acceder al módulo: <a href='modules/document-types/index.php' target='_blank'>modules/document-types/index.php</a><br>";
    echo "2. Probar crear, editar y cambiar estado de tipos<br>";
    echo "3. Verificar que funcionen los filtros y búsqueda<br>";
    echo "4. Probar la relación con documentos<br>";
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "<hr>";
echo "<p><strong>Verificación completada:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>