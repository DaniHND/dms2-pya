<?php
/*
 * modules/folders/api/create_folder.php
 * API para crear carpetas de documentos desde el explorador
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once '../../../config/session.php';
require_once '../../../config/database.php';

// Verificar sesión
try {
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Validar permisos
if ($currentUser['role'] !== 'admin' && !($currentUser['permissions']['create'] ?? true)) {
    http_response_code(403);
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

// Validaciones
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'El nombre de la carpeta es requerido']);
    exit;
}

if (strlen($name) < 2 || strlen($name) > 150) {
    echo json_encode(['success' => false, 'message' => 'El nombre debe tener entre 2 y 150 caracteres']);
    exit;
}

if ($companyId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de empresa inválido']);
    exit;
}

if ($departmentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Debe seleccionar un departamento']);
    exit;
}

if (strlen($description) > 500) {
    echo json_encode(['success' => false, 'message' => 'La descripción no puede exceder 500 caracteres']);
    exit;
}

// Validar colores permitidos
$allowedColors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#34495e'];
if (!in_array($folderColor, $allowedColors)) {
    $folderColor = '#e74c3c';
}

// Validar iconos permitidos
$allowedIcons = ['folder', 'file-text', 'archive', 'briefcase', 'inbox', 'layers'];
if (!in_array($folderIcon, $allowedIcons)) {
    $folderIcon = 'folder';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // ==========================================
    // VERIFICAR PERMISOS DE EMPRESA
    // ==========================================
    if ($currentUser['role'] !== 'admin') {
        if ($currentUser['company_id'] != $companyId) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para crear carpetas en esta empresa']);
            exit;
        }
    }
    
    // Verificar que la empresa existe
    $companyQuery = "SELECT id, name FROM companies WHERE id = :id AND status = 'active'";
    $stmt = $pdo->prepare($companyQuery);
    $stmt->execute(['id' => $companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        echo json_encode(['success' => false, 'message' => 'La empresa no existe o está inactiva']);
        exit;
    }
    
    // Verificar que el departamento existe y pertenece a la empresa
    $deptQuery = "SELECT id, name FROM departments WHERE id = :id AND company_id = :company_id AND status = 'active'";
    $stmt = $pdo->prepare($deptQuery);
    $stmt->execute(['id' => $departmentId, 'company_id' => $companyId]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$department) {
        echo json_encode(['success' => false, 'message' => 'El departamento no existe o no pertenece a la empresa']);
        exit;
    }
    
    // ==========================================
    // VERIFICAR QUE NO EXISTE CARPETA DUPLICADA
    // ==========================================
    $checkQuery = "SELECT id FROM document_folders WHERE name = :name AND company_id = :company_id AND department_id = :department_id AND is_active = 1";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([
        'name' => $name,
        'company_id' => $companyId,
        'department_id' => $departmentId
    ]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya existe una carpeta con este nombre en el departamento seleccionado']);
        exit;
    }
    
    // ==========================================
    // CREAR LA CARPETA
    // ==========================================
    $insertQuery = "
        INSERT INTO document_folders (
            name, description, company_id, department_id, 
            folder_color, folder_icon, folder_path, 
            is_active, created_by, created_at
        ) VALUES (
            :name, :description, :company_id, :department_id,
            :folder_color, :folder_icon, :folder_path,
            1, :created_by, NOW()
        )
    ";
    
    $stmt = $pdo->prepare($insertQuery);
    $success = $stmt->execute([
        'name' => $name,
        'description' => $description,
        'company_id' => $companyId,
        'department_id' => $departmentId,
        'folder_color' => $folderColor,
        'folder_icon' => $folderIcon,
        'folder_path' => $name, // Path simple para carpetas de primer nivel
        'created_by' => $currentUser['id']
    ]);
    
    if ($success) {
        $folderId = $pdo->lastInsertId();
        
        // ==========================================
        // REGISTRAR ACTIVIDAD
        // ==========================================
        try {
            $activityQuery = "
                INSERT INTO activity_logs (user_id, action, description, created_at)
                VALUES (:user_id, :action, :description, NOW())
            ";
            
            $description_log = "Creó carpeta de documentos '{$name}' en {$company['name']} > {$department['name']}";
            
            $activityStmt = $pdo->prepare($activityQuery);
            $activityStmt->execute([
                'user_id' => $currentUser['id'],
                'action' => 'folder_created',
                'description' => $description_log
            ]);
        } catch (Exception $e) {
            // Error en log no es crítico
            error_log("Error registrando actividad: " . $e->getMessage());
        }
        
        // ==========================================
        // RESPUESTA EXITOSA
        // ==========================================
        echo json_encode([
            'success' => true,
            'message' => 'Carpeta de documentos creada exitosamente',
            'data' => [
                'id' => $folderId,
                'name' => $name,
                'description' => $description,
                'company_name' => $company['name'],
                'department_name' => $department['name'],
                'folder_color' => $folderColor,
                'folder_icon' => $folderIcon,
                'created_by' => trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))
            ]
        ]);
        
    } else {
        throw new Exception("Error ejecutando la inserción en la base de datos");
    }
    
} catch (Exception $e) {
    error_log("Error en create_folder.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>