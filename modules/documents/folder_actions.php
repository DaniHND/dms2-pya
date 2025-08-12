<?php
/*
 * modules/documents/folder_actions.php
 * Gestión de carpetas - Crear y renombrar
 */

require_once '../../config/session.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
$action = $_POST['action'] ?? '';

// Verificar permisos básicos
function getUserPermissions($userId) {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $query = "
        SELECT ug.module_permissions, ug.access_restrictions 
        FROM user_groups ug
        INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
        WHERE ugm.user_id = ? AND ug.status = 'active'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $groupData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $permissions = ['create' => false, 'edit' => false];
    foreach ($groupData as $group) {
        $perms = json_decode($group['module_permissions'] ?: '{}', true);
        if (isset($perms['create']) && $perms['create']) $permissions['create'] = true;
        if (isset($perms['edit']) && $perms['edit']) $permissions['edit'] = true;
    }
    
    return $permissions;
}

$permissions = getUserPermissions($currentUser['id']);

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    switch ($action) {
        case 'create_company':
            if (!$permissions['create'] && $currentUser['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sin permisos para crear empresas']);
                exit;
            }
            
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'El nombre es requerido']);
                exit;
            }
            
            // Verificar si ya existe
            $checkStmt = $pdo->prepare("SELECT id FROM companies WHERE name = ?");
            $checkStmt->execute([$name]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una empresa con ese nombre']);
                exit;
            }
            
            $insertStmt = $pdo->prepare("INSERT INTO companies (name, description, status) VALUES (?, ?, 'active')");
            $insertStmt->execute([$name, $description]);
            
            $companyId = $pdo->lastInsertId();
            
            logActivity($currentUser['id'], 'create', 'companies', $companyId, "Empresa creada: $name");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Empresa creada exitosamente',
                'company_id' => $companyId,
                'company_name' => $name
            ]);
            break;
            
        case 'create_department':
            if (!$permissions['create'] && $currentUser['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sin permisos para crear departamentos']);
                exit;
            }
            
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $companyId = (int)($_POST['company_id'] ?? 0);
            
            if (empty($name) || !$companyId) {
                echo json_encode(['success' => false, 'message' => 'Nombre y empresa son requeridos']);
                exit;
            }
            
            // Verificar que la empresa existe
            $companyStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ? AND status = 'active'");
            $companyStmt->execute([$companyId]);
            $company = $companyStmt->fetch();
            
            if (!$company) {
                echo json_encode(['success' => false, 'message' => 'Empresa no encontrada']);
                exit;
            }
            
            // Verificar duplicados
            $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND company_id = ?");
            $checkStmt->execute([$name, $companyId]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Ya existe un departamento con ese nombre en esta empresa']);
                exit;
            }
            
            $insertStmt = $pdo->prepare("INSERT INTO departments (name, description, company_id, status) VALUES (?, ?, ?, 'active')");
            $insertStmt->execute([$name, $description, $companyId]);
            
            $departmentId = $pdo->lastInsertId();
            
            logActivity($currentUser['id'], 'create', 'departments', $departmentId, "Departamento creado: $name en {$company['name']}");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Departamento creado exitosamente',
                'department_id' => $departmentId,
                'department_name' => $name,
                'company_name' => $company['name']
            ]);
            break;
            
        case 'rename_folder':
            if (!$permissions['edit'] && $currentUser['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sin permisos para renombrar carpetas']);
                exit;
            }
            
            $folderType = $_POST['folder_type'] ?? ''; // 'company' o 'department'
            $folderId = (int)($_POST['folder_id'] ?? 0);
            $newName = trim($_POST['new_name'] ?? '');
            
            if (empty($folderType) || !$folderId || empty($newName)) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                exit;
            }
            
            if ($folderType === 'company') {
                // Verificar duplicados
                $checkStmt = $pdo->prepare("SELECT id FROM companies WHERE name = ? AND id != ?");
                $checkStmt->execute([$newName, $folderId]);
                if ($checkStmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Ya existe una empresa con ese nombre']);
                    exit;
                }
                
                $updateStmt = $pdo->prepare("UPDATE companies SET name = ? WHERE id = ?");
                $updateStmt->execute([$newName, $folderId]);
                
                logActivity($currentUser['id'], 'update', 'companies', $folderId, "Empresa renombrada a: $newName");
                
            } elseif ($folderType === 'department') {
                // Obtener empresa del departamento
                $deptStmt = $pdo->prepare("SELECT company_id FROM departments WHERE id = ?");
                $deptStmt->execute([$folderId]);
                $dept = $deptStmt->fetch();
                
                if (!$dept) {
                    echo json_encode(['success' => false, 'message' => 'Departamento no encontrado']);
                    exit;
                }
                
                // Verificar duplicados en la misma empresa
                $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND company_id = ? AND id != ?");
                $checkStmt->execute([$newName, $dept['company_id'], $folderId]);
                if ($checkStmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Ya existe un departamento con ese nombre en esta empresa']);
                    exit;
                }
                
                $updateStmt = $pdo->prepare("UPDATE departments SET name = ? WHERE id = ?");
                $updateStmt->execute([$newName, $folderId]);
                
                logActivity($currentUser['id'], 'update', 'departments', $folderId, "Departamento renombrado a: $newName");
            }
            
            echo json_encode(['success' => true, 'message' => 'Carpeta renombrada exitosamente']);
            break;
            
        case 'get_companies':
            // Obtener empresas disponibles para crear departamentos
            $stmt = $pdo->query("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name");
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'companies' => $companies]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    error_log("Error in folder_actions.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>