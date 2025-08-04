<?php
/*
 * install_actions.php
 * Acciones para el instalador del sistema de permisos
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'config/session.php';

$action = $_POST['action'] ?? '';

if ($action === 'create_structure') {
    createDatabaseStructure();
} elseif ($action === 'create_default_groups') {
    createDefaultGroups();
} else {
    echo "<div class='error'>❌ Acción no válida</div>";
}

function createDatabaseStructure() {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        if (!$pdo) {
            throw new Exception("No se pudo conectar a la base de datos");
        }
        
        echo "<h3>Creando estructura de base de datos...</h3>";
        
        // Verificar si user_group_members existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_group_members'");
        if ($stmt->rowCount() === 0) {
            echo "<div class='info'>⏳ Creando tabla user_group_members...</div>";
            
            $createMembersTable = "
                CREATE TABLE user_group_members (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    assigned_by INT NULL,
                    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
                    UNIQUE KEY unique_user_group (user_id, group_id),
                    INDEX idx_group_id (group_id),
                    INDEX idx_user_id (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $pdo->exec($createMembersTable);
            echo "<div class='success'>✅ Tabla user_group_members creada</div>";
        } else {
            echo "<div class='success'>✅ Tabla user_group_members ya existe</div>";
        }
        
        // Verificar y agregar columnas faltantes a user_groups
        $columns = $pdo->query("DESCRIBE user_groups")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        
        $requiredColumns = [
            'module_permissions' => "ALTER TABLE user_groups ADD COLUMN module_permissions LONGTEXT DEFAULT '{}'",
            'access_restrictions' => "ALTER TABLE user_groups ADD COLUMN access_restrictions LONGTEXT DEFAULT '{}'",
            'download_limit_daily' => "ALTER TABLE user_groups ADD COLUMN download_limit_daily INT NULL",
            'upload_limit_daily' => "ALTER TABLE user_groups ADD COLUMN upload_limit_daily INT NULL"
        ];
        
        foreach ($requiredColumns as $column => $sql) {
            if (!in_array($column, $columnNames)) {
                echo "<div class='info'>⏳ Agregando columna $column...</div>";
                $pdo->exec($sql);
                echo "<div class='success'>✅ Columna $column agregada</div>";
            } else {
                echo "<div class='success'>✅ Columna $column ya existe</div>";
            }
        }
        
        // Crear índices si no existen
        try {
            $pdo->exec("CREATE INDEX idx_user_groups_status ON user_groups(status)");
            echo "<div class='success'>✅ Índice idx_user_groups_status creado</div>";
        } catch (Exception $e) {
            echo "<div class='info'>ℹ️ Índice idx_user_groups_status ya existe</div>";
        }
        
        try {
            $pdo->exec("CREATE INDEX idx_user_groups_system ON user_groups(is_system_group)");
            echo "<div class='success'>✅ Índice idx_user_groups_system creado</div>";
        } catch (Exception $e) {
            echo "<div class='info'>ℹ️ Índice idx_user_groups_system ya existe</div>";
        }
        
        echo "<div class='success'>🎉 Estructura de base de datos completada exitosamente</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error creando estructura: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

function createDefaultGroups() {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        if (!$pdo) {
            throw new Exception("No se pudo conectar a la base de datos");
        }
        
        echo "<h3>Creando grupos predeterminados...</h3>";
        
        // Obtener usuario admin para asignar como creador
        $adminQuery = "SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1";
        $adminStmt = $pdo->prepare($adminQuery);
        $adminStmt->execute();
        $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
        $adminId = $admin['id'] ?? 1;
        
        $defaultGroups = [
            [
                'name' => 'Super Administradores',
                'description' => 'Acceso completo a todos los módulos y funcionalidades del sistema sin restricciones',
                'module_permissions' => json_encode([
                    'view' => true,
                    'view_reports' => true,
                    'download' => true,
                    'export' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                    'delete_permanent' => true,
                    'manage_users' => true,
                    'system_config' => true
                ]),
                'access_restrictions' => json_encode([
                    'companies' => [],
                    'departments' => [],
                    'document_types' => []
                ]),
                'is_system_group' => 1
            ],
            [
                'name' => 'Administradores',
                'description' => 'Gestión de usuarios, documentos y configuración básica del sistema',
                'module_permissions' => json_encode([
                    'view' => true,
                    'view_reports' => true,
                    'download' => true,
                    'export' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                    'delete_permanent' => false,
                    'manage_users' => true,
                    'system_config' => false
                ]),
                'access_restrictions' => json_encode([
                    'companies' => [],
                    'departments' => [],
                    'document_types' => []
                ]),
                'is_system_group' => 1
            ],
            [
                'name' => 'Editores',
                'description' => 'Pueden crear, editar y gestionar documentos con permisos de descarga',
                'module_permissions' => json_encode([
                    'view' => true,
                    'view_reports' => true,
                    'download' => true,
                    'export' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => false,
                    'delete_permanent' => false,
                    'manage_users' => false,
                    'system_config' => false
                ]),
                'access_restrictions' => json_encode([
                    'companies' => [],
                    'departments' => [],
                    'document_types' => []
                ]),
                'download_limit_daily' => 100,
                'upload_limit_daily' => 50,
                'is_system_group' => 0
            ],
            [
                'name' => 'Usuarios Estándar',
                'description' => 'Pueden ver y descargar documentos con límites diarios establecidos',
                'module_permissions' => json_encode([
                    'view' => true,
                    'view_reports' => true,
                    'download' => true,
                    'export' => false,
                    'create' => false,
                    'edit' => false,
                    'delete' => false,
                    'delete_permanent' => false,
                    'manage_users' => false,
                    'system_config' => false
                ]),
                'access_restrictions' => json_encode([
                    'companies' => [],
                    'departments' => [],
                    'document_types' => []
                ]),
                'download_limit_daily' => 20,
                'upload_limit_daily' => 5,
                'is_system_group' => 0
            ],
            [
                'name' => 'Solo Lectura',
                'description' => 'Acceso de solo visualización sin permisos de descarga',
                'module_permissions' => json_encode([
                    'view' => true,
                    'view_reports' => true,
                    'download' => false,
                    'export' => false,
                    'create' => false,
                    'edit' => false,
                    'delete' => false,
                    'delete_permanent' => false,
                    'manage_users' => false,
                    'system_config' => false
                ]),
                'access_restrictions' => json_encode([
                    'companies' => [],
                    'departments' => [],
                    'document_types' => []
                ]),
                'download_limit_daily' => 0,
                'upload_limit_daily' => 0,
                'is_system_group' => 0
            ]
        ];
        
        $insertQuery = "
            INSERT INTO user_groups (
                name, description, module_permissions, access_restrictions, 
                download_limit_daily, upload_limit_daily, status, is_system_group, 
                created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            module_permissions = VALUES(module_permissions),
            access_restrictions = VALUES(access_restrictions),
            download_limit_daily = VALUES(download_limit_daily),
            upload_limit_daily = VALUES(upload_limit_daily)
        ";
        
        $insertStmt = $pdo->prepare($insertQuery);
        
        foreach ($defaultGroups as $group) {
            try {
                $insertStmt->execute([
                    $group['name'],
                    $group['description'],
                    $group['module_permissions'],
                    $group['access_restrictions'],
                    $group['download_limit_daily'] ?? null,
                    $group['upload_limit_daily'] ?? null,
                    $group['is_system_group'],
                    $adminId
                ]);
                
                echo "<div class='success'>✅ Grupo '{$group['name']}' creado/actualizado</div>";
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Error creando grupo '{$group['name']}': " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        
        // Verificar grupos creados
        $countQuery = "SELECT COUNT(*) as total FROM user_groups";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute();
        $count = $countStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<div class='success'>🎉 Configuración completada. Total de grupos: {$count['total']}</div>";
        
        // Sugerir asignación de admin al grupo Super Administradores
        $superAdminQuery = "SELECT id FROM user_groups WHERE name = 'Super Administradores'";
        $superAdminStmt = $pdo->prepare($superAdminQuery);
        $superAdminStmt->execute();
        $superAdminGroup = $superAdminStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($superAdminGroup && $adminId) {
            try {
                $assignQuery = "
                    INSERT IGNORE INTO user_group_members (group_id, user_id, assigned_by, added_at) 
                    VALUES (?, ?, ?, NOW())
                ";
                $assignStmt = $pdo->prepare($assignQuery);
                $assignStmt->execute([$superAdminGroup['id'], $adminId, $adminId]);
                
                echo "<div class='success'>✅ Usuario administrador asignado automáticamente al grupo Super Administradores</div>";
            } catch (Exception $e) {
                echo "<div class='warning'>⚠️ No se pudo asignar automáticamente el administrador al grupo</div>";
            }
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error creando grupos predeterminados: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>