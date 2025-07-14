<?php
// check_database.php
// Script para verificar la estructura de la base de datos

require_once 'config/database.php';

echo "<h2>Verificación de Base de Datos - DMS2</h2>\n";

try {
    // Verificar conexión
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "✅ Conexión a base de datos exitosa\n<br>";
    } else {
        echo "❌ Error de conexión a base de datos\n<br>";
        exit();
    }

    // Verificar tablas principales
    $tables = ['users', 'companies', 'departments', 'documents', 'document_types', 'activity_logs'];
    
    echo "<h3>Verificando tablas:</h3>\n";
    foreach ($tables as $table) {
        $query = "SHOW TABLES LIKE '$table'";
        $result = $conn->query($query);
        
        if ($result && $result->rowCount() > 0) {
            echo "✅ Tabla '$table' existe\n<br>";
        } else {
            echo "❌ Tabla '$table' NO existe\n<br>";
        }
    }

    // Verificar columna download_enabled en users
    echo "<h3>Verificando columna download_enabled:</h3>\n";
    $query = "SHOW COLUMNS FROM users LIKE 'download_enabled'";
    $result = $conn->query($query);
    
    if ($result && $result->rowCount() > 0) {
        echo "✅ Columna 'download_enabled' existe en tabla 'users'\n<br>";
    } else {
        echo "❌ Columna 'download_enabled' NO existe en tabla 'users'\n<br>";
        echo "<strong>Solución:</strong> Ejecutar: <code>ALTER TABLE users ADD COLUMN download_enabled BOOLEAN DEFAULT TRUE AFTER status;</code>\n<br>";
    }

    // Verificar datos de usuarios
    echo "<h3>Usuarios en el sistema:</h3>\n";
    $query = "SELECT id, username, first_name, last_name, role, status FROM users ORDER BY id";
    $users = fetchAll($query);
    
    if ($users) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Estado</th></tr>\n";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($user['status']) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "❌ No hay usuarios en el sistema\n<br>";
    }

    // Verificar datos de empresas
    echo "<h3>Empresas en el sistema:</h3>\n";
    $query = "SELECT id, name, status FROM companies ORDER BY id";
    $companies = fetchAll($query);
    
    if ($companies) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>ID</th><th>Empresa</th><th>Estado</th></tr>\n";
        foreach ($companies as $company) {
            echo "<tr>";
            echo "<td>" . $company['id'] . "</td>";
            echo "<td>" . htmlspecialchars($company['name']) . "</td>";
            echo "<td>" . htmlspecialchars($company['status']) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "❌ No hay empresas en el sistema\n<br>";
    }

    // Verificar documentos
    echo "<h3>Documentos en el sistema:</h3>\n";
    $query = "SELECT COUNT(*) as total FROM documents WHERE status = 'active'";
    $result = fetchOne($query);
    $totalDocs = $result['total'] ?? 0;
    
    echo "📄 Total de documentos activos: <strong>$totalDocs</strong>\n<br>";

    // Verificar directorio uploads
    echo "<h3>Verificando directorios:</h3>\n";
    $uploadDir = 'uploads/documents/';
    
    if (is_dir($uploadDir)) {
        echo "✅ Directorio '$uploadDir' existe\n<br>";
        
        if (is_writable($uploadDir)) {
            echo "✅ Directorio '$uploadDir' tiene permisos de escritura\n<br>";
        } else {
            echo "❌ Directorio '$uploadDir' NO tiene permisos de escritura\n<br>";
            echo "<strong>Solución:</strong> Ejecutar: <code>chmod 755 $uploadDir</code>\n<br>";
        }
    } else {
        echo "❌ Directorio '$uploadDir' NO existe\n<br>";
        echo "<strong>Solución:</strong> Ejecutar el script setup_directories.php\n<br>";
    }

    echo "<h3>✅ Verificación completada</h3>\n";
    echo "<p>Si hay errores, corrígelos antes de usar el sistema.</p>\n";

} catch (Exception $e) {
    echo "❌ Error durante la verificación: " . $e->getMessage() . "\n<br>";
}
?>