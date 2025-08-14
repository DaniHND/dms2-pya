<?php
/**
 * create_folder.php - Crear carpetas de documentos
 * Ubicación: modules/documents/create_folder.php
 */

// Headers para JSON
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Incluir archivos necesarios
    require_once '../../config/session.php';
    require_once '../../config/database.php';
    require_once '../../includes/group_permissions.php';

    // Verificar sesión
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();

    if (!$currentUser) {
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }

    error_log("CREATE_FOLDER.PHP - Usuario: {$currentUser['username']}, ID: {$currentUser['id']}");

    // ===== OBTENER Y VALIDAR DATOS =====
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $companyId = intval($_POST['company_id'] ?? 0);
    $departmentId = intval($_POST['department_id'] ?? 0);
    $folderColor = $_POST['folder_color'] ?? '#e74c3c';
    $folderIcon = $_POST['folder_icon'] ?? 'folder';

    error_log("CREATE_FOLDER.PHP - Datos recibidos: name='$name', company_id=$companyId, department_id=$departmentId");

    // Validaciones básicas
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'El nombre de la carpeta es requerido']);
        exit;
    }

    if (strlen($name) < 2 || strlen($name) > 100) {
        echo json_encode(['success' => false, 'message' => 'El nombre debe tener entre 2 y 100 caracteres']);
        exit;
    }

    if ($companyId <= 0 || $departmentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'IDs de empresa y departamento son requeridos']);
        exit;
    }

    // ===== VERIFICAR PERMISOS =====
    $hasCreatePermission = false;

    if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'super_admin') {
        $hasCreatePermission = true;
        error_log("CREATE_FOLDER.PHP - Usuario es admin, permisos otorgados");
    } else {
        // Verificar permisos de grupo
        try {
            $groupPermissions = getUserGroupPermissions($currentUser['id']);
            if ($groupPermissions['has_groups']) {
                $permissions = $groupPermissions['permissions'];
                $hasCreatePermission = isset($permissions['create_folders']) && $permissions['create_folders'] === true;
                error_log("CREATE_FOLDER.PHP - Permisos de grupo: create_folders=" . ($hasCreatePermission ? 'true' : 'false'));
            } else {
                error_log("CREATE_FOLDER.PHP - Usuario sin grupos asignados");
            }
        } catch (Exception $e) {
            error_log("CREATE_FOLDER.PHP - Error verificando permisos: " . $e->getMessage());
        }
    }

    if (!$hasCreatePermission) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para crear carpetas']);
        exit;
    }

    // ===== CONECTAR A BASE DE DATOS =====
    $database = new Database();
    $pdo = $database->getConnection();

    // ===== VERIFICAR QUE EMPRESA Y DEPARTAMENTO EXISTEN =====
    $checkQuery = "SELECT c.name as company_name, d.name as department_name 
                   FROM companies c 
                   INNER JOIN departments d ON c.id = d.company_id 
                   WHERE c.id = ? AND d.id = ? AND c.status = 'active' AND d.status = 'active'";
    
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$companyId, $departmentId]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$location) {
        error_log("CREATE_FOLDER.PHP - ERROR: Empresa o departamento no encontrado: company_id=$companyId, department_id=$departmentId");
        echo json_encode(['success' => false, 'message' => 'Empresa o departamento no válido']);
        exit;
    }

    error_log("CREATE_FOLDER.PHP - Ubicación válida: {$location['company_name']} → {$location['department_name']}");

    // ===== VERIFICAR NOMBRE DUPLICADO =====
    $duplicateQuery = "SELECT id FROM document_folders 
                       WHERE name = ? AND company_id = ? AND department_id = ? AND is_active = 1";
    
    $stmt = $pdo->prepare($duplicateQuery);
    $stmt->execute([$name, $companyId, $departmentId]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya existe una carpeta con ese nombre en este departamento']);
        exit;
    }

    // ===== CREAR CARPETA =====
    $pdo->beginTransaction();

    try {
        $insertQuery = "INSERT INTO document_folders (
            name, 
            description, 
            company_id, 
            department_id, 
            folder_color, 
            folder_icon,
            folder_path,
            is_active,
            created_by,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())";
        
        $folderPath = "/{$location['company_name']}/{$location['department_name']}/{$name}";
        
        $stmt = $pdo->prepare($insertQuery);
        $result = $stmt->execute([
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
            throw new Exception('Error al insertar la carpeta en la base de datos');
        }

        $folderId = $pdo->lastInsertId();
        error_log("CREATE_FOLDER.PHP - Carpeta creada con ID: $folderId");

        // ===== REGISTRAR ACTIVIDAD =====
        try {
            $logQuery = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
                         VALUES (?, ?, ?, ?, ?, NOW())";
            $logStmt = $pdo->prepare($logQuery);
            $logStmt->execute([
                $currentUser['id'],
                'folder_created',
                'document_folders',
                $folderId,
                "Creó carpeta '{$name}' en {$location['company_name']} → {$location['department_name']}"
            ]);
        } catch (Exception $e) {
            error_log("CREATE_FOLDER.PHP - Warning: No se pudo registrar actividad: " . $e->getMessage());
        }

        // Confirmar transacción
        $pdo->commit();
        
        error_log("CREATE_FOLDER.PHP - Carpeta creada exitosamente");

        echo json_encode([
            'success' => true,
            'message' => 'Carpeta creada exitosamente',
            'folder' => [
                'id' => $folderId,
                'name' => $name,
                'description' => $description,
                'company_name' => $location['company_name'],
                'department_name' => $location['department_name'],
                'folder_color' => $folderColor,
                'folder_icon' => $folderIcon,
                'folder_path' => $folderPath
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("CREATE_FOLDER.PHP - Error en transacción: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al crear la carpeta: ' . $e->getMessage()]);
    }

} catch (Exception $e) {
    error_log("CREATE_FOLDER.PHP - Error general: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del sistema: ' . $e->getMessage()]);
}
?>