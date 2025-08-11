<?php
/**
 * modules/documents/actions/create_folder.php
 * Crear carpetas protegido por permisos de grupos
 */

require_once '../../../config/session.php';
require_once '../../../config/database.php';
require_once '../../../includes/group_permissions.php';
require_once '../../../includes/permission_check.php';

header('Content-Type: application/json');

// Verificar sesión
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();

// Verificar permisos de grupos
try {
    requireActiveGroups();
    requireFolderPermission();
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Validar datos del formulario
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$departmentId = (int)($_POST['department_id'] ?? 0);
$companyId = (int)($_POST['company_id'] ?? 0);
$folderColor = $_POST['folder_color'] ?? '#3498db';
$folderIcon = $_POST['folder_icon'] ?? 'folder';

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'El nombre de la carpeta es requerido']);
    exit;
}

if (!$departmentId) {
    echo json_encode(['success' => false, 'message' => 'Debe seleccionar un departamento']);
    exit;
}

if (!$companyId) {
    echo json_encode(['success' => false, 'message' => 'Debe seleccionar una empresa']);
    exit;
}

// Verificar acceso a empresa y departamento
if (!canUserAccessCompany($currentUser['id'], $companyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin acceso a esta empresa']);
    exit;
}

if (!canUserAccessDepartment($currentUser['id'], $departmentId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin acceso a este departamento']);
    exit;
}

// Validar colores e iconos permitidos
$allowedColors = ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6', '#34495e'];
$allowedIcons = ['folder', 'file-text', 'briefcase', 'archive', 'folder-open'];

if (!in_array($folderColor, $allowedColors)) {
    $folderColor = '#3498db';
}

if (!in_array($folderIcon, $allowedIcons)) {
    $folderIcon = 'folder';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $pdo->beginTransaction();
    
    // Verificar que el departamento pertenece a la empresa
    $deptCheckQuery = "
        SELECT d.id, d.name, c.name as company_name
        FROM departments d
        INNER JOIN companies c ON d.company_id = c.id
        WHERE d.id = ? AND d.company_id = ? 
        AND d.status = 'active' AND c.status = 'active'
    ";
    
    $deptCheckStmt = $pdo->prepare($deptCheckQuery);
    $deptCheckStmt->execute([$departmentId, $companyId]);
    $department = $deptCheckStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$department) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Departamento no válido para esta empresa']);
        exit;
    }
    
    // Verificar que no existe una carpeta con el mismo nombre en el departamento
    $duplicateCheckQuery = "
        SELECT id FROM document_folders 
        WHERE name = ? AND department_id = ? AND is_active = 1
    ";
    
    $duplicateStmt = $pdo->prepare($duplicateCheckQuery);
    $duplicateStmt->execute([$name, $departmentId]);
    
    if ($duplicateStmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Ya existe una carpeta con este nombre en el departamento']);
        exit;
    }
    
    // Crear la carpeta
    $insertQuery = "
        INSERT INTO document_folders (
            name, description, company_id, department_id, 
            folder_color, folder_icon, folder_path, 
            is_active, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
    ";
    
    $folderPath = $name; // Path simple por ahora
    
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
    
    if (!$result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al crear la carpeta']);
        exit;
    }
    
    $folderId = $pdo->lastInsertId();
    
    // Registrar actividad
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $logStmt->execute([
        $currentUser['id'],
        'folder_created',
        'document_folders',
        $folderId,
        "Carpeta creada: '{$name}' en {$department['company_name']} - {$department['name']}"
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Carpeta creada correctamente',
        'folder' => [
            'id' => $folderId,
            'name' => $name,
            'description' => $description,
            'department_name' => $department['name'],
            'company_name' => $department['company_name'],
            'folder_color' => $folderColor,
            'folder_icon' => $folderIcon
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error PDO en create_folder.php: ' . $e->getMessage());
    
    // Manejar errores específicos
    if ($e->getCode() == 23000) { // Constraint violation
        echo json_encode([
            'success' => false, 
            'message' => 'Ya existe una carpeta con este nombre en el departamento'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Error de base de datos al crear carpeta'
        ]);
    }
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error en create_folder.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor'
    ]);
}
?>