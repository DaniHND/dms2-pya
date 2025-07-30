<?php
// document_types_checklist.php
// Verificación completa del estado del módulo CRUD Tipos de Documentos

echo "<h1>✅ Checklist CRUD Tipos de Documentos - DMS2</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
    .check-item { margin: 10px 0; padding: 10px; border-radius: 5px; }
    .complete { background: #d4edda; border-left: 4px solid #28a745; }
    .missing { background: #f8d7da; border-left: 4px solid #dc3545; }
    .partial { background: #fff3cd; border-left: 4px solid #ffc107; }
    .status { font-weight: bold; margin-right: 10px; }
    .section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
    .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; margin: 5px 0; }
    .action { background: #e8f4fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .critical { background: #ffebee; border: 2px solid #f44336; padding: 15px; border-radius: 5px; }
</style>";

$checklist = [];
$missingItems = [];
$score = 0;
$totalItems = 0;

// =========================================
// 1. VERIFICAR BASE DE DATOS
// =========================================
echo "<div class='section'>";
echo "<h2>1. 🗄️ BASE DE DATOS</h2>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        // Verificar tabla document_types
        $tableExists = $conn->query("SHOW TABLES LIKE 'document_types'")->rowCount() > 0;
        $checklist['table_exists'] = $tableExists;
        $totalItems++;
        if ($tableExists) $score++;
        
        echo "<div class='check-item " . ($tableExists ? 'complete' : 'missing') . "'>";
        echo "<span class='status'>" . ($tableExists ? '✅' : '❌') . "</span>";
        echo "Tabla 'document_types' existe";
        echo "</div>";
        
        if ($tableExists) {
            // Verificar estructura
            $columns = $conn->query("DESCRIBE document_types")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'Field');
            
            $requiredColumns = ['id', 'name', 'description', 'status', 'created_at'];
            $optionalColumns = ['updated_at', 'icon', 'color', 'extensions', 'max_size'];
            
            foreach ($requiredColumns as $col) {
                $exists = in_array($col, $columnNames);
                $checklist["column_$col"] = $exists;
                $totalItems++;
                if ($exists) $score++;
                
                echo "<div class='check-item " . ($exists ? 'complete' : 'missing') . "'>";
                echo "<span class='status'>" . ($exists ? '✅' : '❌') . "</span>";
                echo "Columna '$col' (requerida)";
                if (!$exists) $missingItems[] = "Columna $col falta en document_types";
                echo "</div>";
            }
            
            foreach ($optionalColumns as $col) {
                $exists = in_array($col, $columnNames);
                echo "<div class='check-item " . ($exists ? 'complete' : 'partial') . "'>";
                echo "<span class='status'>" . ($exists ? '✅' : '⚠️') . "</span>";
                echo "Columna '$col' (opcional)";
                echo "</div>";
            }
            
            // Verificar datos
            $count = $conn->query("SELECT COUNT(*) FROM document_types")->fetchColumn();
            echo "<div class='check-item complete'>";
            echo "<span class='status'>📊</span>";
            echo "Tipos de documentos existentes: $count";
            echo "</div>";
            
            // Verificar relación con documents
            $hasColumn = $conn->query("SHOW COLUMNS FROM documents LIKE 'document_type_id'")->rowCount() > 0;
            $checklist['relation_column'] = $hasColumn;
            $totalItems++;
            if ($hasColumn) $score++;
            
            echo "<div class='check-item " . ($hasColumn ? 'complete' : 'missing') . "'>";
            echo "<span class='status'>" . ($hasColumn ? '✅' : '❌') . "</span>";
            echo "Columna 'document_type_id' en tabla documents";
            if (!$hasColumn) $missingItems[] = "Falta columna document_type_id en tabla documents";
            echo "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='check-item missing'>";
    echo "<span class='status'>❌</span>";
    echo "Error de base de datos: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
echo "</div>";

// =========================================
// 2. VERIFICAR ARCHIVOS PHP
// =========================================
echo "<div class='section'>";
echo "<h2>2. 📁 ARCHIVOS PHP</h2>";

$phpFiles = [
    'modules/document-types/index.php' => 'Página principal del módulo',
    'modules/document-types/actions/create_document_type.php' => 'Crear tipo de documento',
    'modules/document-types/actions/get_document_type_details.php' => 'Obtener detalles',
    'modules/document-types/actions/update_document_type.php' => 'Actualizar tipo',
    'modules/document-types/actions/toggle_document_type_status.php' => 'Cambiar estado'
];

foreach ($phpFiles as $file => $description) {
    $exists = file_exists($file);
    $checklist["file_" . str_replace(['/', '.'], '_', $file)] = $exists;
    $totalItems++;
    if ($exists) $score++;
    
    echo "<div class='check-item " . ($exists ? 'complete' : 'missing') . "'>";
    echo "<span class='status'>" . ($exists ? '✅' : '❌') . "</span>";
    echo "$file - $description";
    if (!$exists) {
        $missingItems[] = "Archivo faltante: $file";
        echo "<br><small>📝 Debe crearse con el contenido proporcionado</small>";
    } else {
        $size = round(filesize($file) / 1024, 2);
        echo "<br><small>📊 Tamaño: {$size} KB</small>";
    }
    echo "</div>";
}
echo "</div>";

// =========================================
// 3. VERIFICAR ARCHIVOS CSS/JS
// =========================================
echo "<div class='section'>";
echo "<h2>3. 🎨 ARCHIVOS CSS/JS</h2>";

$webFiles = [
    'assets/css/document-types.css' => 'Estilos del módulo',
    'assets/js/document-types.js' => 'JavaScript del módulo',
    'assets/css/main.css' => 'Estilos principales',
    'assets/css/modal.css' => 'Estilos de modales'
];

foreach ($webFiles as $file => $description) {
    $exists = file_exists($file);
    $checklist["web_" . str_replace(['/', '.'], '_', $file)] = $exists;
    $totalItems++;
    if ($exists) $score++;
    
    echo "<div class='check-item " . ($exists ? 'complete' : 'missing') . "'>";
    echo "<span class='status'>" . ($exists ? '✅' : '❌') . "</span>";
    echo "$file - $description";
    if ($exists) {
        $size = round(filesize($file) / 1024, 2);
        echo "<br><small>📊 Tamaño: {$size} KB</small>";
    } else {
        $missingItems[] = "Archivo faltante: $file";
    }
    echo "</div>";
}
echo "</div>";

// =========================================
// 4. VERIFICAR SIDEBAR
// =========================================
echo "<div class='section'>";
echo "<h2>4. 🧩 SIDEBAR</h2>";

$sidebarExists = file_exists('includes/sidebar.php');
$sidebarHasEntry = false;

if ($sidebarExists) {
    $sidebarContent = file_get_contents('includes/sidebar.php');
    $sidebarHasEntry = strpos($sidebarContent, 'document-types') !== false;
}

$checklist['sidebar_exists'] = $sidebarExists;
$checklist['sidebar_has_entry'] = $sidebarHasEntry;
$totalItems += 2;
if ($sidebarExists) $score++;
if ($sidebarHasEntry) $score++;

echo "<div class='check-item " . ($sidebarExists ? 'complete' : 'missing') . "'>";
echo "<span class='status'>" . ($sidebarExists ? '✅' : '❌') . "</span>";
echo "Archivo includes/sidebar.php existe";
if (!$sidebarExists) $missingItems[] = "Archivo includes/sidebar.php no encontrado";
echo "</div>";

echo "<div class='check-item " . ($sidebarHasEntry ? 'complete' : 'missing') . "'>";
echo "<span class='status'>" . ($sidebarHasEntry ? '✅' : '❌') . "</span>";
echo "Entrada 'Tipos de Documentos' en sidebar";
if (!$sidebarHasEntry) $missingItems[] = "Falta entrada de tipos de documentos en sidebar";
echo "</div>";
echo "</div>";

// =========================================
// 5. VERIFICAR PERMISOS
// =========================================
echo "<div class='section'>";
echo "<h2>5. 🔐 PERMISOS</h2>";

$directories = [
    'modules/document-types' => 'Directorio principal',
    'modules/document-types/actions' => 'Directorio de acciones'
];

foreach ($directories as $dir => $desc) {
    $exists = is_dir($dir);
    $writable = $exists ? is_writable($dir) : false;
    
    echo "<div class='check-item " . ($exists ? ($writable ? 'complete' : 'partial') : 'missing') . "'>";
    echo "<span class='status'>" . ($exists ? ($writable ? '✅' : '⚠️') : '❌') . "</span>";
    echo "$dir - $desc";
    if ($exists) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        echo "<br><small>📋 Permisos: $perms " . ($writable ? '(Escribible)' : '(Solo lectura)') . "</small>";
    } else {
        $missingItems[] = "Directorio faltante: $dir";
    }
    echo "</div>";
}
echo "</div>";

// =========================================
// 6. RESUMEN Y SCORE
// =========================================
echo "<div class='section'>";
echo "<h2>6. 📊 RESUMEN GENERAL</h2>";

$percentage = round(($score / $totalItems) * 100, 1);
$status = $percentage >= 90 ? 'complete' : ($percentage >= 70 ? 'partial' : 'missing');

echo "<div class='check-item $status'>";
echo "<h3>📈 Puntuación: $score/$totalItems ($percentage%)</h3>";

if ($percentage >= 90) {
    echo "<p>🎉 <strong>¡EXCELENTE!</strong> El módulo está casi completo.</p>";
} elseif ($percentage >= 70) {
    echo "<p>⚠️ <strong>BUENO</strong> - Faltan algunos elementos importantes.</p>";
} else {
    echo "<p>❌ <strong>NECESITA TRABAJO</strong> - Varios elementos críticos faltan.</p>";
}
echo "</div>";

// Mostrar elementos faltantes
if (!empty($missingItems)) {
    echo "<div class='critical'>";
    echo "<h3>🚨 ELEMENTOS FALTANTES CRÍTICOS:</h3>";
    echo "<ol>";
    foreach ($missingItems as $item) {
        echo "<li>$item</li>";
    }
    echo "</ol>";
    echo "</div>";
}
echo "</div>";

// =========================================
// 7. PLAN DE ACCIÓN
// =========================================
echo "<div class='section'>";
echo "<h2>7. 🎯 PLAN DE ACCIÓN</h2>";

echo "<div class='action'>";
echo "<h3>🔥 PRIORIDAD ALTA (Para que funcione el CRUD):</h3>";
echo "<ol>";

if (!$checklist['table_exists']) {
    echo "<li><strong>Crear tabla document_types</strong> - Sin esto no funcionará nada</li>";
}

$criticalFiles = [
    'modules/document-types/index.php',
    'modules/document-types/actions/create_document_type.php',
    'modules/document-types/actions/get_document_type_details.php',
    'modules/document-types/actions/update_document_type.php',
    'modules/document-types/actions/toggle_document_type_status.php'
];

foreach ($criticalFiles as $file) {
    if (!file_exists($file)) {
        echo "<li><strong>Crear $file</strong> - Esencial para el CRUD</li>";
    }
}

if (!$checklist['sidebar_has_entry']) {
    echo "<li><strong>Actualizar sidebar</strong> - Para acceder al módulo</li>";
}

echo "</ol>";
echo "</div>";

echo "<div class='action'>";
echo "<h3>⚡ COMANDOS RÁPIDOS:</h3>";

echo "<h4>1. Crear directorios:</h4>";
echo "<div class='code'>mkdir -p modules/document-types/actions</div>";

echo "<h4>2. SQL para completar tabla:</h4>";
echo "<div class='code'>";
echo "ALTER TABLE document_types ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;<br>";
echo "ALTER TABLE documents ADD CONSTRAINT fk_documents_document_type FOREIGN KEY (document_type_id) REFERENCES document_types(id);";
echo "</div>";

echo "<h4>3. Verificar después de crear archivos:</h4>";
echo "<div class='code'>php document_types_checklist.php</div>";

echo "</div>";
echo "</div>";

// =========================================
// 8. URLS DE PRUEBA
// =========================================
echo "<div class='section'>";
echo "<h2>8. 🌐 URLS PARA PROBAR</h2>";

echo "<p><strong>Una vez completado, estas URLs deberían funcionar:</strong></p>";
echo "<ul>";
echo "<li><a href='modules/document-types/index.php' target='_blank'>modules/document-types/index.php</a> - Página principal</li>";
echo "<li><a href='dashboard.php' target='_blank'>dashboard.php</a> - Verificar estadísticas</li>";
echo "</ul>";

echo "<p><strong>Funcionalidades que estarán disponibles:</strong></p>";
echo "<ul>";
echo "<li>🆕 Crear tipos de documentos</li>";
echo "<li>👁️ Ver detalles de tipos</li>";
echo "<li>✏️ Editar tipos existentes</li>";
echo "<li>🔄 Activar/desactivar tipos</li>";
echo "<li>🔍 Filtrar y buscar</li>";
echo "<li>📄 Paginación</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><strong>Verificación completada:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>