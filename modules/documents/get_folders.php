<?php
/**
 * modules/documents/get_folders.php
 * AJAX endpoint para cargar carpetas por empresa y departamento - VERSIÓN CORREGIDA
 */

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/group_permissions.php';

header('Content-Type: application/json');

// Verificar sesión
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$companyId = intval($_POST['company_id'] ?? 0);
$departmentId = intval($_POST['department_id'] ?? 0);

if (!$companyId || !$departmentId) {
    echo json_encode(['success' => false, 'message' => 'IDs de empresa y departamento requeridos']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // ===== VERIFICAR PERMISOS =====
    $isAdmin = ($currentUser['role'] === 'admin' || $currentUser['role'] === 'super_admin');
    
    if (!$isAdmin) {
        // Verificar acceso para usuarios normales
        if (!canUserAccessCompany($currentUser['id'], $companyId)) {
            echo json_encode(['success' => false, 'message' => 'Sin acceso a esta empresa']);
            exit;
        }
        
        if (!canUserAccessDepartment($currentUser['id'], $departmentId)) {
            echo json_encode(['success' => false, 'message' => 'Sin acceso a este departamento']);
            exit;
        }
    }

    // ===== OBTENER CARPETAS =====
    $query = "SELECT id, name, folder_color, description 
              FROM document_folders 
              WHERE company_id = ? AND department_id = ? AND is_active = 1 
              ORDER BY name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$companyId, $departmentId]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'folders' => $folders
    ]);

} catch (Exception $e) {
    error_log("Error en get_folders.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>