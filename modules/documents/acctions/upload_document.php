<?php
/**
 * modules/documents/actions/upload_document.php
 * Subir documentos protegido por permisos de grupos
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
    requireUploadPermission();
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

// Validar que se enviaron archivos
if (empty($_FILES['files']['name'][0])) {
    echo json_encode(['success' => false, 'message' => 'No se enviaron archivos']);
    exit;
}

// Validar datos del formulario
$companyId = (int)($_POST['company_id'] ?? 0);
$departmentId = (int)($_POST['department_id'] ?? 0);
$documentTypeId = (int)($_POST['document_type_id'] ?? 0);
$folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
$description = trim($_POST['description'] ?? '');

if (!$companyId || !$departmentId || !$documentTypeId) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios son requeridos']);
    exit;
}

// Verificar acceso a empresa, departamento y tipo de documento
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

if (!canUserAccessDocumentType($currentUser['id'], $documentTypeId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin acceso a este tipo de documento']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Obtener configuración del sistema
    $configQuery = "SELECT config_key, config_value FROM system_config WHERE config_key IN ('max_file_size', 'allowed_extensions')";
    $configStmt = $pdo->prepare($configQuery);
    $configStmt->execute();
    $configResults = $configStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $maxFileSize = $configResults['max_file_size'] ?? 20971520; // 20MB por defecto
    $allowedExtensions = json_decode($configResults['allowed_extensions'] ?? '["pdf", "jpg", "jpeg", "png", "gif", "doc", "docx", "xlsx"]', true);
    
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
        echo json_encode(['success' => false, 'message' => 'Departamento no válido para esta empresa']);
        exit;
    }
    
    // Verificar tipo de documento
    $docTypeQuery = "SELECT id, name FROM document_types WHERE id = ? AND status = 'active'";
    $docTypeStmt = $pdo->prepare($docTypeQuery);
    $docTypeStmt->execute([$documentTypeId]);
    $documentType = $docTypeStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$documentType) {
        echo json_encode(['success' => false, 'message' => 'Tipo de documento no válido']);
        exit;
    }
    
    // Verificar carpeta si se especificó
    if ($folderId) {
        $folderQuery = "SELECT id, name FROM document_folders WHERE id = ? AND department_id = ? AND is_active = 1";
        $folderStmt = $pdo->prepare($folderQuery);
        $folderStmt->execute([$folderId, $departmentId]);
        $folder = $folderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$folder) {
            echo json_encode(['success' => false, 'message' => 'Carpeta no válida para este departamento']);
            exit;
        }
    }
    
    // Crear directorio de subida si no existe
    $projectRoot = dirname(dirname(dirname(__DIR__)));
    $uploadDir = $projectRoot . '/uploads/documents/';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Error creando directorio de subida']);
            exit;
        }
    }
    
    $pdo->beginTransaction();
    
    $uploadedFiles = [];
    $errors = [];
    $uploadedCount = 0;
    
    // Procesar cada archivo
    for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "Error en archivo " . ($_i + 1) . ": " . $_FILES['files']['name'][$i];
            continue;
        }
        
        $originalName = $_FILES['files']['name'][$i];
        $tempPath = $_FILES['files']['tmp_name'][$i];
        $fileSize = $_FILES['files']['size'][$i];
        $mimeType = $_FILES['files']['type'][$i];
        
        // Validar tamaño
        if ($fileSize > $maxFileSize) {
            $errors[] = "Archivo demasiado grande: {$originalName} (máximo " . number_format($maxFileSize/1024/1024, 1) . "MB)";
            continue;
        }
        
        // Validar extensión
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = "Tipo de archivo no permitido: {$originalName}";
            continue;
        }
        
        // Generar nombre único para el archivo
        $uniqueName = uniqid() . '_' . time() . '.' . $extension;
        $filePath = 'uploads/documents/' . $uniqueName;
        $fullPath = $uploadDir . $uniqueName;
        
        // Mover archivo
        if (!move_uploaded_file($tempPath, $fullPath)) {
            $errors[] = "Error subiendo archivo: {$originalName}";
            continue;
        }
        
        // Generar nombre limpio para la base de datos
        $cleanName = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Insertar en base de datos
        $insertQuery = "
            INSERT INTO documents (
                company_id, department_id, folder_id, document_type_id, user_id,
                name, original_name, file_path, file_size, mime_type, description,
                tags, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '[]', 'active', NOW(), NOW())
        ";
        
        $insertStmt = $pdo->prepare($insertQuery);
        $result = $insertStmt->execute([
            $companyId,
            $departmentId,
            $folderId,
            $documentTypeId,
            $currentUser['id'],
            $cleanName,
            $originalName,
            $filePath,
            $fileSize,
            $mimeType,
            $description
        ]);
        
        if ($result) {
            $documentId = $pdo->lastInsertId();
            $uploadedFiles[] = [
                'id' => $documentId,
                'name' => $cleanName,
                'original_name' => $originalName,
                'size' => $fileSize
            ];
            $uploadedCount++;
            
            // Registrar actividad
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $currentUser['id'],
                'document_uploaded',
                'documents',
                $documentId,
                "Documento subido: '{$cleanName}' a {$department['company_name']} - {$department['name']}"
            ]);
        } else {
            // Si falla la inserción, eliminar archivo físico
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            $errors[] = "Error guardando en base de datos: {$originalName}";
        }
    }
    
    if ($uploadedCount > 0) {
        $pdo->commit();
        
        $response = [
            'success' => true,
            'message' => "{$uploadedCount} archivo(s) subido(s) correctamente",
            'uploaded_count' => $uploadedCount,
            'uploaded_files' => $uploadedFiles
        ];
        
        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }
        
        echo json_encode($response);
    } else {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo subir ningún archivo',
            'errors' => $errors
        ]);
    }
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error PDO en upload_document.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos al subir archivos'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error en upload_document.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor'
    ]);
}
?>