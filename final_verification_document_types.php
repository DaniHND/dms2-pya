<?php
// final_verification_document_types.php
// Verificación final y setup completo del módulo de tipos de documentos

echo "<h1>🚀 Verificación Final - Módulo Tipos de Documentos - DMS2</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .command { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
    .step { background: #e8f4fd; padding: 15px; border-left: 4px solid #3b82f6; margin: 10px 0; }
</style>";

// 1. Crear directorio actions si no existe
echo "<div class='section'>";
echo "<h2>1. 📁 Crear Estructura de Directorios</h2>";

$directories = [
    'modules/document-types',
    'modules/document-types/actions'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "<span class='success'>✅ Creado directorio: $dir</span><br>";
        } else {
            echo "<span class='error'>❌ Error creando directorio: $dir</span><br>";
        }
    } else {
        echo "<span class='info'>📁 Directorio ya existe: $dir</span><br>";
    }
}
echo "</div>";

// 2. Instrucciones para crear archivos
echo "<div class='section'>";
echo "<h2>2. 📝 Instrucciones para Completar el Módulo</h2>";

echo "<div class='step'>";
echo "<h3>Paso 1: Crear los archivos PHP</h3>";
echo "<p>Necesitas crear los siguientes archivos con el contenido proporcionado:</p>";
echo "<ul>";
echo "<li><strong>modules/document-types/index.php</strong> - Página principal del módulo</li>";
echo "<li><strong>modules/document-types/actions/create_document_type.php</strong> - Crear tipos</li>";
echo "<li><strong>modules/document-types/actions/get_document_type_details.php</strong> - Obtener detalles</li>";
echo "<li><strong>modules/document-types/actions/update_document_type.php</strong> - Actualizar tipos</li>";
echo "<li><strong>modules/document-types/actions/toggle_document_type_status.php</strong> - Cambiar estado</li>";
echo "</ul>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>Paso 2: Ejecutar SQL para completar estructura</h3>";
echo "<p>Ejecuta el siguiente SQL para agregar la columna updated_at y la clave foránea:</p>";
echo "<div class='command'>";
echo "-- Agregar columna updated_at<br>";
echo "ALTER TABLE document_types ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;<br><br>";
echo "-- Agregar clave foránea<br>";
echo "ALTER TABLE documents ADD CONSTRAINT fk_documents_document_type FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE SET NULL;<br><br>";
echo "-- Actualizar timestamps existentes<br>";
echo "UPDATE document_types SET updated_at = created_at WHERE updated_at IS NULL;";
echo "</div>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>Paso 3: Verificar permisos</h3>";
echo "<p>Asegúrate de que los directorios tengan permisos de escritura:</p>";
echo "<div class='command'>";
echo "chmod 755 modules/document-types/<br>";
echo "chmod 755 modules/document-types/actions/";
echo "</div>";
echo "</div>";
echo "</div>";

// 3. Verificación de archivos CSS y JS existentes
echo "<div class='section'>";
echo "<h2>3. ✅ Archivos Ya Disponibles</h2>";

$existingFiles = [
    'assets/css/document-types.css' => 'Estilos del módulo',
    'assets/js/document-types.js' => 'JavaScript del módulo'
];

foreach ($existingFiles as $file => $description) {
    if (file_exists($file)) {
        $size = round(filesize($file) / 1024, 2);
        echo "<span class='success'>✅ $file</span> - $description ($size KB)<br>";
    } else {
        echo "<span class='warning'>⚠️ $file</span> - $description (No encontrado)<br>";
    }
}
echo "</div>";

// 4. Verificar base de datos
echo "<div class='section'>";
echo "<h2>4. 🗄️ Estado de la Base de Datos</h2>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<span class='success'>✅ Conexión a base de datos exitosa</span><br>";
        
        // Contar tipos existentes
        $count = $conn->query("SELECT COUNT(*) FROM document_types")->fetchColumn();
        echo "<span class='info'>📊 Tipos de documentos existentes: $count</span><br>";
        
        // Verificar relación con documents
        $documentsWithType = $conn->query("SELECT COUNT(*) FROM documents WHERE document_type_id IS NOT NULL")->fetchColumn();
        echo "<span class='info'>📄 Documentos con tipo asignado: $documentsWithType</span><br>";
        
        // Verificar columna updated_at
        $query = "SHOW COLUMNS FROM document_types LIKE 'updated_at'";
        $result = $conn->query($query);
        
        if ($result && $result->rowCount() > 0) {
            echo "<span class='success'>✅ Columna 'updated_at' existe</span><br>";
        } else {
            echo "<span class='warning'>⚠️ Falta columna 'updated_at' - ejecutar SQL</span><br>";
        }
        
        // Verificar clave foránea
        $query = "SELECT COUNT(*) as count FROM information_schema.table_constraints 
                  WHERE table_schema = DATABASE() 
                  AND table_name = 'documents' 
                  AND constraint_name LIKE '%document_type%'";
        $result = $conn->query($query)->fetchColumn();
        
        if ($result > 0) {
            echo "<span class='success'>✅ Clave foránea existe</span><br>";
        } else {
            echo "<span class='warning'>⚠️ Falta clave foránea - ejecutar SQL</span><br>";
        }
        
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error de base de datos: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 5. Plan de acción
echo "<div class='section'>";
echo "<h2>5. 🎯 Plan de Acción</h2>";

echo "<h3>Tareas pendientes:</h3>";
echo "<ol>";
echo "<li>✅ <strong>Crear archivos PHP</strong> - Usar el contenido proporcionado en los artifacts</li>";
echo "<li>🔧 <strong>Ejecutar SQL</strong> - Para completar la estructura de la tabla</li>";
echo "<li>🧪 <strong>Probar el módulo</strong> - Acceder a modules/document-types/index.php</li>";
echo "<li>🎨 <strong>Verificar estilos</strong> - Los archivos CSS y JS ya están listos</li>";
echo "</ol>";

echo "<h3>URLs para probar después del setup:</h3>";
echo "<ul>";
echo "<li><a href='modules/document-types/index.php' target='_blank'>modules/document-types/index.php</a> - Página principal</li>";
echo "<li><a href='dashboard.php' target='_blank'>dashboard.php</a> - Verificar estadísticas</li>";
echo "</ul>";

echo "<h3>Funcionalidades que estarán disponibles:</h3>";
echo "<ul>";
echo "<li>🆕 <strong>Crear tipos de documentos</strong> con nombre, descripción, icono y color</li>";
echo "<li>👁️ <strong>Ver detalles</strong> de cada tipo con estadísticas de uso</li>";
echo "<li>✏️ <strong>Editar tipos</strong> existentes</li>";
echo "<li>🔄 <strong>Cambiar estado</strong> (activar/desactivar)</li>";
echo "<li>🔍 <strong>Filtrar y buscar</strong> tipos de documentos</li>";
echo "<li>📄 <strong>Paginación</strong> para grandes cantidades de tipos</li>";
echo "<li>🛡️ <strong>Protección</strong> contra eliminar tipos con documentos activos</li>";
echo "</ul>";
echo "</div>";

// 6. Características especiales del módulo
echo "<div class='section'>";
echo "<h2>6. ⭐ Características Especiales</h2>";

echo "<p>Tu tabla <code>document_types</code> tiene campos adicionales interesantes:</p>";
echo "<ul>";
echo "<li><strong>icon</strong> - Para mostrar iconos personalizados de Feather Icons</li>";
echo "<li><strong>color</strong> - Para personalizar colores de cada tipo</li>";
echo "<li><strong>extensions</strong> - Para definir extensiones permitidas</li>";
echo "<li><strong>max_size</strong> - Para limitar tamaño de archivos</li>";
echo "</ul>";

echo "<p>Esto hace que tu módulo sea más avanzado que el estándar básico!</p>";
echo "</div>";

echo "<hr>";
echo "<p><strong>Verificación completada:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p style='color: green; font-weight: bold;'>🎉 ¡Módulo de Tipos de Documentos casi listo! Solo falta crear los archivos PHP y ejecutar el SQL.</p>";
?>