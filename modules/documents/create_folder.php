<?php
/*
 * create_folder.php
 * API para crear carpetas de documentos - VERSIÓN CORREGIDA
 */

// Usar bootstrap para consistencia
require_once '../../bootstrap.php';

header('Content-Type: application/json');

// Solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Verificar sesión
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();
    
    // ===== VERIFICACIÓN DE PERMISOS CORREGIDA =====
    $hasCreatePermission = false;
    
    if ($currentUser['role'] === 'admin') {
        $hasCreatePermission = true;
    } else {
        // Verificar sistema unificado
        if (class_exists('UnifiedPermissionSystem')) {
            try {
                $permissionSystem = UnifiedPermissionSystem::getInstance();
                $userPerms = $permissionSystem->getUserEffectivePermissions($currentUser['id']);
                $hasCreatePermission = isset($userPerms['permissions']['create_folders']) && 
                                       $userPerms['permissions']['create_folders'] === true;
            } catch (Exception $e) {
                error_log('ERROR en verificación de permisos create_folder: ' . $e->getMessage());
                $hasCreatePermission = false;
            }
        } else {
            // Sistema legacy - buscar permisos antiguos
            $database = new Database();
            $pdo = $database->getConnection();
            
            $permQuery = "
                SELECT ug.module_permissions 
                FROM user_groups ug
                INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
                WHERE ugm.user_id = ? AND ug.status = 'active'
            ";
            $permStmt = $pdo->prepare($permQuery);
            $permStmt->execute([$currentUser['id']]);
            $permissions = $permStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($permissions as $perm) {
                $permData = json_decode($perm['module_permissions'] ?: '{}', true);
                // Buscar tanto el permiso nuevo como el viejo
                if (($permData['create_folders'] ?? false) || ($permData['create'] ?? false)) {
                    $hasCreatePermission = true;
                    break;
                }
            }
        }
    }
    
    if (!$hasCreatePermission) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para crear carpetas']);
        exit;
    }
    
    // Obtener datos del formulario
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $companyId = intval($_POST['company_id'] ?? 0);
    $departmentId = intval($_POST['department_id'] ?? 0);
    $folderColor = $_POST['folder_color'] ?? '#e74c3c';
    $folderIcon = $_POST['folder_icon'] ?? 'folder';
    
    // Validar datos requeridos
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'El nombre de la carpeta es requerido']);
        exit;
    }
    
    if ($companyId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de empresa inválido']);
        exit;
    }
    
    if ($departmentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de departamento inválido']);
        exit;
    }
    
    // Verificar que el departamento existe y pertenece a la empresa
    $database = new Database();
    $pdo = $database->getConnection();
    
    $deptQuery = "SELECT id, name FROM departments WHERE id = ? AND company_id = ? AND status = 'active'";
    $deptStmt = $pdo->prepare($deptQuery);
    $deptStmt->execute([$departmentId, $companyId]);
    $department = $deptStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$department) {
        echo json_encode(['success' => false, 'message' => 'Departamento no encontrado o inválido']);
        exit;
    }
    
    // Verificar que no existe otra carpeta con el mismo nombre en el mismo departamento
    $existsQuery = "SELECT id FROM document_folders WHERE name = ? AND company_id = ? AND department_id = ? AND is_active = 1";
    $existsStmt = $pdo->prepare($existsQuery);
    $existsStmt->execute([$name, $companyId, $departmentId]);
    
    if ($existsStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya existe una carpeta con ese nombre en este departamento']);
        exit;
    }
    
    // Crear la carpeta
    $insertQuery = "
        INSERT INTO document_folders (
            name, 
            description, 
            company_id, 
            department_id, 
            folder_color, 
            folder_icon, 
            folder_path, 
            is_active, 
            created_by, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
    ";
    
    $folderPath = $name; // Ruta simple por ahora
    
    $insertStmt = $pdo->prepare($insertQuery);
    $result = $insertStmt->execute([
        $name,
        $description,
        $companyId,
        $departmentId,
        $folderColor,
        $folderIcon,
        $folderPath,
        $currentUser['id']
    ]);
    
    if ($result) {
        $folderId = $pdo->lastInsertId();
        
        // Log de actividad
        if (function_exists('logActivity')) {
            logActivity(
                $currentUser['id'],
                'create',
                'document_folders',
                $folderId,
                "Carpeta '{$name}' creada en departamento {$department['name']}"
            );
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Carpeta creada exitosamente',
            'folder_id' => $folderId,
            'folder_name' => $name
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear la carpeta']);
    }
    
} catch (Exception $e) {
    error_log("Error creating folder: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>