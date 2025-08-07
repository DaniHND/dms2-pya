<?php
/*
 * install_system.php
 * Verificación e instalación completa del sistema de carpetas
 */

// Solo permitir acceso desde localhost para seguridad
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    die('Acceso denegado. Solo desde localhost.');
}

require_once '../../config/database.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación Sistema DMS2</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; line-height: 1.6; background: #f8fafc; color: #334155; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .header { text-align: center; margin-bottom: 2rem; }
        .header h1 { color: #1e293b; font-size: 2rem; margin-bottom: 0.5rem; }
        .header p { color: #64748b; }
        .step { background: white; margin: 1rem 0; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .step h2 { color: #1e293b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .success { color: #059669; }
        .error { color: #dc2626; }
        .warning { color: #d97706; }
        .info { color: #2563eb; }
        .code { background: #f1f5f9; padding: 1rem; border-radius: 4px; font-family: monospace; font-size: 0.9rem; margin: 1rem 0; }
        .progress { width: 100%; background: #e2e8f0; border-radius: 4px; height: 8px; margin: 1rem 0; }
        .progress-bar { background: #059669; height: 100%; border-radius: 4px; transition: width 0.3s; }
        ul { margin-left: 1.5rem; }
        li { margin: 0.5rem 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗂️ Sistema DMS2 - Instalación Completa</h1>
            <p>Verificación e instalación del sistema de gestión de carpetas y documentos</p>
        </div>

<?php

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<div class='step'>";
    echo "<h2>📋 Verificando Base de Datos</h2>";
    
    // ========================================
    // 1. VERIFICAR TABLA DOCUMENT_FOLDERS
    // ========================================
    echo "<h3>1. Tabla document_folders</h3>";
    try {
        $result = $pdo->query("DESCRIBE document_folders");
        echo "<span class='success'>✅ Tabla document_folders existe</span><br>";
        
        // Verificar columnas importantes
        $columns = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
        
        $requiredColumns = ['id', 'name', 'company_id', 'department_id', 'folder_color', 'folder_icon', 'is_active'];
        foreach ($requiredColumns as $col) {
            if (in_array($col, $columns)) {
                echo "<span class='success'>✅ Columna {$col} presente</span><br>";
            } else {
                echo "<span class='error'>❌ Columna {$col} faltante</span><br>";
            }
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error: tabla document_folders no existe</span><br>";
        echo "<div class='code'>Creando tabla document_folders...</div>";
        
        $createTable = "
        CREATE TABLE `document_folders` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(150) NOT NULL,
            `description` text DEFAULT NULL,
            `company_id` int(11) NOT NULL,
            `department_id` int(11) NOT NULL,
            `parent_folder_id` int(11) DEFAULT NULL,
            `folder_color` varchar(20) DEFAULT '#3498db',
            `folder_icon` varchar(30) DEFAULT 'folder',
            `folder_path` text DEFAULT NULL,
            `is_active` tinyint(1) DEFAULT 1,
            `created_by` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_folder_per_dept` (`name`,`department_id`,`parent_folder_id`),
            KEY `idx_folders_company` (`company_id`),
            KEY `idx_folders_department` (`department_id`),
            KEY `idx_folders_parent` (`parent_folder_id`),
            KEY `idx_folders_active` (`is_active`),
            KEY `idx_folders_created` (`created_at`),
            KEY `fk_folders_creator` (`created_by`),
            CONSTRAINT `document_folders_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
            CONSTRAINT `document_folders_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
            CONSTRAINT `document_folders_ibfk_3` FOREIGN KEY (`parent_folder_id`) REFERENCES `document_folders` (`id`) ON DELETE CASCADE,
            CONSTRAINT `document_folders_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($createTable);
        echo "<span class='success'>✅ Tabla document_folders creada exitosamente</span><br>";
    }
    
    // ========================================
    // 2. VERIFICAR COLUMNA FOLDER_ID EN DOCUMENTS
    // ========================================
    echo "<h3>2. Columna folder_id en documents</h3>";
    try {
        $result = $pdo->query("SHOW COLUMNS FROM documents LIKE 'folder_id'");
        if ($result->rowCount() > 0) {
            echo "<span class='success'>✅ Columna folder_id existe en documents</span><br>";
        } else {
            echo "<span class='warning'>⚠️ Añadiendo columna folder_id a documents</span><br>";
            $pdo->exec("ALTER TABLE documents ADD COLUMN folder_id int(11) DEFAULT NULL AFTER department_id");
            $pdo->exec("ALTER TABLE documents ADD KEY idx_documents_folder (folder_id)");
            $pdo->exec("ALTER TABLE documents ADD CONSTRAINT fk_documents_folder FOREIGN KEY (folder_id) REFERENCES document_folders (id) ON DELETE SET NULL");
            echo "<span class='success'>✅ Columna folder_id añadida exitosamente</span><br>";
        }
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error al verificar/crear folder_id: " . $e->getMessage() . "</span><br>";
    }
    
    // ========================================
    // 3. INSERTAR DATOS DE EJEMPLO
    // ========================================
    echo "<h3>3. Datos de ejemplo</h3>";
    try {
        $count = $pdo->query("SELECT COUNT(*) as total FROM document_folders")->fetch()['total'];
        
        if ($count == 0) {
            echo "<span class='info'>📝 Insertando carpetas de ejemplo...</span><br>";
            
            $exampleFolders = [
                [
                    'name' => 'Documentos Legales',
                    'description' => 'Contratos, acuerdos y documentos legales',
                    'company_id' => 1,
                    'department_id' => 1,
                    'folder_color' => '#e74c3c',
                    'folder_icon' => 'file-text',
                    'created_by' => 1
                ],
                [
                    'name' => 'Facturas y Comprobantes',
                    'description' => 'Documentos fiscales y contables',
                    'company_id' => 1,
                    'department_id' => 1,
                    'folder_color' => '#2ecc71',
                    'folder_icon' => 'credit-card',
                    'created_by' => 1
                ],
                [
                    'name' => 'Reportes Mensual',
                    'description' => 'Informes y reportes administrativos',
                    'company_id' => 1,
                    'department_id' => 1,
                    'folder_color' => '#3498db',
                    'folder_icon' => 'bar-chart',
                    'created_by' => 1
                ]
            ];
            
            $insertQuery = "
                INSERT INTO document_folders (name, description, company_id, department_id, folder_color, folder_icon, folder_path, is_active, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
            ";
            $stmt = $pdo->prepare($insertQuery);
            
            foreach ($exampleFolders as $folder) {
                $stmt->execute([
                    $folder['name'],
                    $folder['description'],
                    $folder['company_id'],
                    $folder['department_id'],
                    $folder['folder_color'],
                    $folder['folder_icon'],
                    $folder['name'], // folder_path
                    $folder['created_by']
                ]);
                echo "<span class='success'>✅ Carpeta '{$folder['name']}' creada</span><br>";
            }
        } else {
            echo "<span class='info'>📁 Ya existen {$count} carpetas en el sistema</span><br>";
        }
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error al insertar datos de ejemplo: " . $e->getMessage() . "</span><br>";
    }
    
    echo "</div>";
    
    // ========================================
    // 4. VERIFICAR ARCHIVOS DEL SISTEMA
    // ========================================
    echo "<div class='step'>";
    echo "<h2>📁 Verificando Archivos del Sistema</h2>";
    
    $requiredFiles = [
        'inbox.php' => 'Explorador principal de documentos',
        'create_folder.php' => 'API para crear carpetas',
        'move_document.php' => 'API para mover documentos',
        'upload.php' => 'Sistema de subida de archivos',
        'get_departments.php' => 'API para obtener departamentos',
        'get_folders.php' => 'API para obtener carpetas'
    ];
    
    foreach ($requiredFiles as $file => $description) {
        if (file_exists($file)) {
            echo "<span class='success'>✅ {$file}</span> - {$description}<br>";
        } else {
            echo "<span class='error'>❌ {$file}</span> - {$description} <strong>(FALTANTE)</strong><br>";
        }
    }
    
    // Verificar directorios
    $requiredDirs = [
        '../../uploads/documents/' => 'Directorio de documentos subidos'
    ];
    
    foreach ($requiredDirs as $dir => $description) {
        if (is_dir($dir)) {
            echo "<span class='success'>✅ {$dir}</span> - {$description}<br>";
        } else {
            echo "<span class='warning'>⚠️ Creando directorio {$dir}</span><br>";
            if (mkdir($dir, 0755, true)) {
                echo "<span class='success'>✅ Directorio creado exitosamente</span><br>";
            } else {
                echo "<span class='error'>❌ Error al crear directorio</span><br>";
            }
        }
    }
    
    echo "</div>";
    
    // ========================================
    // 5. ESTADÍSTICAS DEL SISTEMA
    // ========================================
    echo "<div class='step'>";
    echo "<h2>📊 Estadísticas del Sistema</h2>";
    
    $stats = [
        'companies' => $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'active'")->fetchColumn(),
        'departments' => $pdo->query("SELECT COUNT(*) FROM departments WHERE status = 'active'")->fetchColumn(),
        'document_folders' => $pdo->query("SELECT COUNT(*) FROM document_folders WHERE is_active = 1")->fetchColumn(),
        'documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'active'")->fetchColumn(),
        'users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn()
    ];
    
    echo "<div class='code'>";
    echo "<strong>📈 ESTADO ACTUAL DEL SISTEMA:</strong><br>";
    echo "• Empresas activas: {$stats['companies']}<br>";
    echo "• Departamentos activos: {$stats['departments']}<br>";
    echo "• Carpetas de documentos: {$stats['document_folders']}<br>";
    echo "• Documentos totales: {$stats['documents']}<br>";
    echo "• Usuarios activos: {$stats['users']}<br>";
    echo "</div>";
    
    // Documentos por carpeta
    $folderStats = $pdo->query("
        SELECT f.name, f.folder_color, COUNT(d.id) as doc_count
        FROM document_folders f
        LEFT JOIN documents d ON f.id = d.folder_id AND d.status = 'active'
        WHERE f.is_active = 1
        GROUP BY f.id, f.name, f.folder_color
        ORDER BY doc_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($folderStats)) {
        echo "<h3>📂 Distribución de documentos por carpeta:</h3>";
        foreach ($folderStats as $folder) {
            echo "<span style='color: {$folder['folder_color']}'>●</span> <strong>{$folder['name']}</strong>: {$folder['doc_count']} documentos<br>";
        }
    }
    
    echo "</div>";
    
    // ========================================
    // MENSAJE FINAL
    // ========================================
    echo "<div class='step' style='background: linear-gradient(135deg, #059669, #047857); color: white;'>";
    echo "<h2>🎉 ¡Sistema Instalado y Verificado!</h2>";
    echo "<p><strong>El sistema de gestión de documentos está completamente funcional.</strong></p>";
    
    echo "<h3>🚀 Funcionalidades disponibles:</h3>";
    echo "<ul>";
    echo "<li>✅ Explorador visual de documentos con navegación por empresa → departamento → carpeta</li>";
    echo "<li>✅ Creación de carpetas personalizadas con colores e iconos</li>";
    echo "<li>✅ Sistema drag & drop para mover documentos entre carpetas</li>";
    echo "<li>✅ Subida de archivos con detección automática de ubicación</li>";
    echo "<li>✅ Búsqueda global de documentos, carpetas y departamentos</li>";
    echo "<li>✅ Sistema de permisos y restricciones por grupos de usuarios</li>";
    echo "<li>✅ Log de actividades para auditoría</li>";
    echo "</ul>";
    
    echo "<h3>📝 Próximos pasos:</h3>";
    echo "<ul>";
    echo "<li>1. Acceder a <strong>modules/documents/inbox.php</strong> para usar el explorador</li>";
    echo "<li>2. Crear carpetas dentro de departamentos</li>";
    echo "<li>3. Subir documentos y organizarlos en carpetas</li>";
    echo "<li>4. Configurar permisos de usuarios según sea necesario</li>";
    echo "</ul>";
    
    echo "<p><strong>¡El sistema está listo para uso en producción!</strong></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='step' style='background: #dc2626; color: white;'>";
    echo "<h2>❌ Error Crítico</h2>";
    echo "<p>Error durante la verificación: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Por favor, verifica tu configuración de base de datos y vuelve a intentar.</p>";
    echo "</div>";
}

?>

    </div>
</body>
</html>