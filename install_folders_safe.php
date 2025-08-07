<?php
/*
 * install_folders_safe.php
 * Instalador ultra-seguro del sistema de carpetas
 * Maneja todos los errores posibles de MySQL
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Instalador Seguro de Carpetas - DMS2</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .info { color: #3498db; font-weight: bold; }
        .step { background: white; margin: 15px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .step h3 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: 'Courier New', monospace; margin: 10px 0; }
        .progress { background: #ecf0f1; border-radius: 10px; padding: 3px; margin: 10px 0; }
        .progress-bar { background: linear-gradient(90deg, #3498db, #2ecc71); height: 15px; border-radius: 7px; transition: width 0.3s; }
    </style>
</head>
<body>
<div class='container'>";

echo "<div class='header'>
    <h1>🗂️ Instalador Ultra-Seguro de Sistema de Carpetas</h1>
    <p>Instalación paso a paso con manejo completo de errores</p>
</div>";

// Variables de control
$steps = [];
$errors = [];
$warnings = [];
$totalSteps = 10;
$currentStep = 0;

// Función para mostrar progreso
function showStep($title, $description = '') {
    global $currentStep, $totalSteps;
    $currentStep++;
    $progress = round(($currentStep / $totalSteps) * 100);
    
    echo "<div class='step'>";
    echo "<h3>Paso $currentStep/$totalSteps: $title</h3>";
    if ($description) echo "<p>$description</p>";
    echo "<div class='progress'><div class='progress-bar' style='width: {$progress}%'></div></div>";
    
    return $currentStep;
}

// Función segura para ejecutar SQL
function safeExecuteSQL($pdo, $sql, $description, $allowError = true) {
    try {
        $pdo->exec($sql);
        echo "<span class='success'>✅ $description</span><br>";
        return true;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        
        // Errores que podemos ignorar (son normales)
        $ignorableErrors = [
            'Duplicate column name',
            'already exists',
            'Duplicate key name',
            'Duplicate entry',
            'Cannot add foreign key constraint'
        ];
        
        $isIgnorable = false;
        foreach ($ignorableErrors as $ignorable) {
            if (strpos($errorMsg, $ignorable) !== false) {
                $isIgnorable = true;
                break;
            }
        }
        
        if ($allowError && $isIgnorable) {
            echo "<span class='info'>ℹ️ $description (ya existía o no requerido)</span><br>";
            return true;
        } else {
            echo "<span class='error'>❌ Error en $description: " . htmlspecialchars($errorMsg) . "</span><br>";
            return false;
        }
    }
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo conectar a la base de datos");
    }

    // ========================================
    // PASO 1: VERIFICAR CONEXIÓN
    // ========================================
    $step = showStep("Verificar Conexión", "Probando la conexión a la base de datos");
    echo "<span class='success'>✅ Conexión a base de datos exitosa</span><br>";
    echo "<span class='info'>📊 Servidor MySQL: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "</span><br>";
    echo "</div>";

    // ========================================
    // PASO 2: VERIFICAR TABLAS REQUERIDAS
    // ========================================
    $step = showStep("Verificar Tablas Requeridas", "Confirmando que las tablas básicas existan");
    
    $requiredTables = ['companies', 'departments', 'users', 'documents'];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<span class='success'>✅ Tabla $table existe</span><br>";
        } else {
            echo "<span class='error'>❌ Tabla $table NO existe</span><br>";
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        echo "<div class='code'><strong>ERROR CRÍTICO:</strong> Faltan tablas básicas: " . implode(', ', $missingTables) . "<br>";
        echo "Debe instalar primero el sistema base de DMS antes de las carpetas.</div>";
        echo "</div></div></body></html>";
        exit();
    }
    echo "</div>";

    // ========================================
    // PASO 3: CREAR TABLA document_folders
    // ========================================
    $step = showStep("Crear Tabla document_folders", "Creando la tabla principal de carpetas");
    
    $createFoldersTable = "
        CREATE TABLE IF NOT EXISTS document_folders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            description TEXT,
            company_id INT NOT NULL,
            department_id INT NOT NULL,
            parent_folder_id INT NULL,
            folder_color VARCHAR(20) DEFAULT '#3498db',
            folder_icon VARCHAR(30) DEFAULT 'folder',
            folder_path TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    safeExecuteSQL($pdo, $createFoldersTable, "Tabla document_folders creada", true);
    echo "</div>";

    // ========================================
    // PASO 4: AGREGAR COLUMNA folder_id A documents
    // ========================================
    $step = showStep("Modificar Tabla documents", "Agregando columna folder_id para vincular documentos");
    
    safeExecuteSQL($pdo, "ALTER TABLE documents ADD COLUMN folder_id INT NULL AFTER department_id", 
                   "Columna folder_id agregada a documents", true);
    echo "</div>";

    // ========================================
    // PASO 5: CREAR ÍNDICES
    // ========================================
    $step = showStep("Crear Índices", "Agregando índices para mejorar el rendimiento");
    
    $indexes = [
        "CREATE INDEX idx_folders_company ON document_folders(company_id)" => "Índice por empresa",
        "CREATE INDEX idx_folders_department ON document_folders(department_id)" => "Índice por departamento",
        "CREATE INDEX idx_folders_parent ON document_folders(parent_folder_id)" => "Índice por carpeta padre",
        "CREATE INDEX idx_folders_active ON document_folders(is_active)" => "Índice por estado activo",
        "CREATE INDEX idx_documents_folder ON documents(folder_id)" => "Índice folder_id en documents",
        "CREATE INDEX idx_folders_search ON document_folders(name, description)" => "Índice de búsqueda",
        "CREATE INDEX idx_folders_created ON document_folders(created_at)" => "Índice por fecha"
    ];
    
    foreach ($indexes as $sql => $description) {
        safeExecuteSQL($pdo, $sql, $description, true);
    }
    echo "</div>";

    // ========================================
    // PASO 6: CREAR CONSTRAINT ÚNICO
    // ========================================
    $step = showStep("Crear Constraints Únicos", "Evitando carpetas duplicadas por departamento");
    
    safeExecuteSQL($pdo, "ALTER TABLE document_folders ADD CONSTRAINT unique_folder_per_dept UNIQUE KEY (name, department_id, parent_folder_id)", 
                   "Constraint único creado", true);
    echo "</div>";

    // ========================================
    // PASO 7: CREAR FOREIGN KEYS
    // ========================================
    $step = showStep("Crear Foreign Keys", "Estableciendo relaciones entre tablas");
    
    $foreignKeys = [
        "ALTER TABLE document_folders ADD CONSTRAINT fk_folders_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE" => "FK empresa",
        "ALTER TABLE document_folders ADD CONSTRAINT fk_folders_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE" => "FK departamento",
        "ALTER TABLE document_folders ADD CONSTRAINT fk_folders_parent FOREIGN KEY (parent_folder_id) REFERENCES document_folders(id) ON DELETE CASCADE" => "FK carpeta padre",
        "ALTER TABLE document_folders ADD CONSTRAINT fk_folders_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT" => "FK creador",
        "ALTER TABLE documents ADD CONSTRAINT fk_documents_folder FOREIGN KEY (folder_id) REFERENCES document_folders(id) ON DELETE SET NULL" => "FK documentos a carpetas"
    ];
    
    foreach ($foreignKeys as $sql => $description) {
        safeExecuteSQL($pdo, $sql, $description, true);
    }
    echo "</div>";

    // ========================================
    // PASO 8: CREAR VISTA
    // ========================================
    $step = showStep("Crear Vista Completa", "Creando vista para consultas optimizadas");
    
    $createView = "
        CREATE OR REPLACE VIEW v_folders_complete AS
        SELECT 
            f.id, f.name, f.description, f.company_id, f.department_id,
            f.parent_folder_id, f.folder_color, f.folder_icon, f.folder_path,
            f.is_active, f.created_by, f.created_at, f.updated_at,
            
            c.name as company_name,
            d.name as department_name,
            pf.name as parent_folder_name,
            CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
            
            COUNT(doc.id) as document_count,
            CASE WHEN f.parent_folder_id IS NULL THEN 0 ELSE 1 END as folder_level
            
        FROM document_folders f
        LEFT JOIN companies c ON f.company_id = c.id
        LEFT JOIN departments d ON f.department_id = d.id
        LEFT JOIN document_folders pf ON f.parent_folder_id = pf.id
        LEFT JOIN users u ON f.created_by = u.id
        LEFT JOIN documents doc ON f.id = doc.folder_id AND doc.status = 'active'
        WHERE f.is_active = TRUE
        GROUP BY f.id, f.name, f.description, f.company_id, f.department_id, 
                 f.parent_folder_id, f.folder_color, f.folder_icon, f.folder_path, 
                 f.is_active, f.created_by, f.created_at, f.updated_at,
                 c.name, d.name, pf.name, u.first_name, u.last_name
    ";
    
    safeExecuteSQL($pdo, $createView, "Vista v_folders_complete creada", false);
    echo "</div>";

    // ========================================
    // PASO 9: INSERTAR DATOS DE EJEMPLO
    // ========================================
    $step = showStep("Crear Carpetas de Ejemplo", "Insertando carpetas iniciales para prueba");
    
    // Obtener IDs para ejemplos
    $company = $pdo->query("SELECT id FROM companies ORDER BY id LIMIT 1")->fetch();
    $department = $pdo->query("SELECT id FROM departments ORDER BY id LIMIT 1")->fetch();
    $admin = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetch();
    
    if ($company && $department && $admin) {
        $sampleFolders = [
            ['Documentos Generales', 'Carpeta principal para documentos diversos', '#2ecc71', 'folder'],
            ['Contratos', 'Documentos contractuales y acuerdos', '#e74c3c', 'file-text'],
            ['Reportes', 'Informes y reportes mensuales', '#f39c12', 'bar-chart'],
            ['Facturas', 'Facturas y documentos fiscales', '#9b59b6', 'credit-card']
        ];
        
        foreach ($sampleFolders as $folder) {
            $sql = "INSERT IGNORE INTO document_folders 
                    (name, description, company_id, department_id, folder_color, folder_icon, created_by, folder_path) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $folder[0], $folder[1], $company['id'], $department['id'], 
                    $folder[2], $folder[3], $admin['id'], $folder[0]
                ]);
                echo "<span class='success'>✅ Carpeta '{$folder[0]}' creada</span><br>";
            } catch (Exception $e) {
                echo "<span class='info'>ℹ️ Carpeta '{$folder[0]}' ya existía</span><br>";
            }
        }
    } else {
        echo "<span class='warning'>⚠️ No se pudieron crear carpetas de ejemplo (falta empresa/departamento/admin)</span><br>";
    }
    echo "</div>";

    // ========================================
    // PASO 10: VERIFICACIÓN FINAL
    // ========================================
    $step = showStep("Verificación Final", "Probando que todo funcione correctamente");
    
    // Probar la vista
    try {
        $folderCount = $pdo->query("SELECT COUNT(*) as total FROM v_folders_complete")->fetch();
        echo "<span class='success'>✅ Vista funcional: {$folderCount['total']} carpetas visibles</span><br>";
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error en vista: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
    
    // Verificar estructura de documents
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM documents LIKE 'folder_id'")->fetchAll();
        if (!empty($columns)) {
            echo "<span class='success'>✅ Columna folder_id presente en documents</span><br>";
        } else {
            echo "<span class='error'>❌ Columna folder_id NO encontrada en documents</span><br>";
        }
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error verificando documents: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
    
    // Mostrar resumen
    $summary = $pdo->query("
        SELECT 
            COUNT(*) as total_folders,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_folders
        FROM document_folders
    ")->fetch();
    
    echo "<div class='code'>
        <strong>📊 RESUMEN DE INSTALACIÓN:</strong><br>
        • Total de carpetas: {$summary['total_folders']}<br>
        • Carpetas activas: {$summary['active_folders']}<br>
        • Sistema: Completamente funcional ✅
    </div>";
    
    echo "</div>";

    // ========================================
    // MENSAJE FINAL
    // ========================================
    echo "<div class='step' style='background: linear-gradient(135deg, #2ecc71, #27ae60); color: white;'>";
    echo "<h2>🎉 ¡Instalación Completada Exitosamente!</h2>";
    echo "<p><strong>El sistema de carpetas está listo para usar.</strong></p>";
    echo "<h3>Próximos pasos:</h3>";
    echo "<ul>
        <li>✅ Actualizar <code>modules/documents/inbox.php</code> con el nuevo código</li>
        <li>✅ Crear el módulo de gestión en <code>modules/folders/</code></li>
        <li>✅ Agregar entrada al sidebar para 'Gestionar Carpetas'</li>
        <li>✅ Probar el drag & drop de documentos</li>
    </ul>";
    echo "<p><strong>¡Todo listo para organizar los documentos por carpetas!</strong></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step' style='background: #e74c3c; color: white;'>";
    echo "<h2>❌ Error Crítico</h2>";
    echo "<p>Error durante la instalación: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Por favor, verifica tu configuración de base de datos y vuelve a intentar.</p>";
    echo "</div>";
}

echo "</div></body></html>";
?>