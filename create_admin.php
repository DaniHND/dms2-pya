<?php
// create_admin.php
// Script para crear usuario administrador con contraseña correcta

require_once 'config/database.php';

echo "<h1>🔧 Creación de Usuario Administrador - DMS2</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    .warning { color: orange; font-weight: bold; }
    .credentials { background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 10px 0; }
    code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
</style>";

try {
    // Verificar conexión a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "<p class='success'>✅ Conexión a base de datos establecida</p>";
    
    // 1. Verificar si ya existe el usuario admin
    $existingAdmin = fetchOne("SELECT id, username FROM users WHERE username = 'admin'");
    
    if ($existingAdmin) {
        echo "<p class='info'>👤 Usuario admin ya existe (ID: {$existingAdmin['id']})</p>";
        echo "<p class='info'>🔄 Actualizando contraseña...</p>";
        
        // Actualizar la contraseña
        $newPassword = password_hash('password', PASSWORD_DEFAULT);
        $result = updateRecord('users', 
            ['password' => $newPassword], 
            'username = :username', 
            ['username' => 'admin']
        );
        
        if ($result) {
            echo "<p class='success'>✅ Contraseña de admin actualizada correctamente</p>";
        } else {
            throw new Exception("Error al actualizar la contraseña del admin");
        }
        
    } else {
        echo "<p class='info'>👤 Usuario admin no existe, creando...</p>";
        
        // Verificar que existe la empresa con ID 1
        $company = fetchOne("SELECT id, name FROM companies WHERE id = 1");
        if (!$company) {
            echo "<p class='warning'>⚠️ No existe empresa con ID 1, creando empresa por defecto...</p>";
            
            // Crear empresa por defecto
            $companyResult = insertRecord('companies', [
                'name' => 'Perdomo y Asociados',
                'description' => 'Firma de abogados',
                'email' => 'info@perdomoyasociados.com',
                'status' => 'active'
            ]);
            
            if ($companyResult) {
                echo "<p class='success'>✅ Empresa 'Perdomo y Asociados' creada</p>";
            } else {
                throw new Exception("Error al crear la empresa por defecto");
            }
        } else {
            echo "<p class='info'>🏢 Empresa encontrada: {$company['name']}</p>";
        }
        
        // Crear el usuario admin
        $adminData = [
            'first_name' => 'Admin',
            'last_name' => 'Sistema',
            'username' => 'admin',
            'email' => 'admin@perdomoyasociados.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active',
            'company_id' => 1,
            'download_enabled' => 1,
            'email_verified' => 1
        ];
        
        $result = insertRecord('users', $adminData);
        
        if ($result) {
            $adminId = $conn->lastInsertId();
            echo "<p class='success'>✅ Usuario admin creado correctamente (ID: $adminId)</p>";
        } else {
            throw new Exception("Error al crear el usuario admin");
        }
    }
    
    // 2. Crear usuario de prueba adicional si no existe
    $existingUser = fetchOne("SELECT id FROM users WHERE username = 'jperez'");
    
    if (!$existingUser) {
        echo "<p class='info'>👤 Creando usuario de prueba 'jperez'...</p>";
        
        $userData = [
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'username' => 'jperez',
            'email' => 'jperez@perdomoyasociados.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'user',
            'status' => 'active',
            'company_id' => 1,
            'download_enabled' => 1,
            'email_verified' => 1
        ];
        
        $result = insertRecord('users', $userData);
        
        if ($result) {
            $userId = $conn->lastInsertId();
            echo "<p class='success'>✅ Usuario 'jperez' creado correctamente (ID: $userId)</p>";
        } else {
            echo "<p class='warning'>⚠️ No se pudo crear el usuario de prueba</p>";
        }
    } else {
        echo "<p class='info'>👤 Usuario 'jperez' ya existe</p>";
    }
    
    // 3. Mostrar todos los usuarios disponibles
    echo "<h2>👥 Usuarios Disponibles:</h2>";
    $users = fetchAll("SELECT id, first_name, last_name, username, email, role, status FROM users ORDER BY id");
    
    if ($users) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr style='background: #f2f2f2;'>";
        echo "<th style='padding: 8px;'>ID</th>";
        echo "<th style='padding: 8px;'>Nombre</th>";
        echo "<th style='padding: 8px;'>Usuario</th>";
        echo "<th style='padding: 8px;'>Email</th>";
        echo "<th style='padding: 8px;'>Rol</th>";
        echo "<th style='padding: 8px;'>Estado</th>";
        echo "</tr>";
        
        foreach ($users as $user) {
            $statusClass = $user['status'] === 'active' ? 'color: green;' : 'color: red;';
            echo "<tr>";
            echo "<td style='padding: 8px; text-align: center;'>{$user['id']}</td>";
            echo "<td style='padding: 8px;'>{$user['first_name']} {$user['last_name']}</td>";
            echo "<td style='padding: 8px;'><strong>{$user['username']}</strong></td>";
            echo "<td style='padding: 8px;'>{$user['email']}</td>";
            echo "<td style='padding: 8px;'>{$user['role']}</td>";
            echo "<td style='padding: 8px; $statusClass'>{$user['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Mostrar credenciales finales
    echo "<div class='credentials'>";
    echo "<h2>🔑 Credenciales de Acceso:</h2>";
    echo "<h3>👨‍💼 ADMINISTRADOR:</h3>";
    echo "<p><strong>Usuario:</strong> <code>admin</code></p>";
    echo "<p><strong>Contraseña:</strong> <code>password</code></p>";
    echo "<h3>👤 USUARIO NORMAL:</h3>";
    echo "<p><strong>Usuario:</strong> <code>jperez</code></p>";
    echo "<p><strong>Contraseña:</strong> <code>password</code></p>";
    echo "</div>";
    
    echo "<h2>📋 Próximos Pasos:</h2>";
    echo "<ol>";
    echo "<li>Ir a <a href='login.php'>login.php</a> para iniciar sesión</li>";
    echo "<li>Usar las credenciales mostradas arriba</li>";
    echo "<li>Cambiar las contraseñas por seguridad</li>";
    echo "<li>Eliminar este archivo (create_admin.php) por seguridad</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Error en create_admin.php: " . $e->getMessage());
}
?>