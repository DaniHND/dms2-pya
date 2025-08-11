<?php
/**
 * modules/documents/actions/get_folders.php
 * Obtener carpetas filtradas por permisos de grupos
 */

require_once '../../../config/session.php';
require_once '../../../config/database.php';
require_once '../../../includes/group_permissions.php';

header('Content-Type: application/json');

// Verificar sesión
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();

// Verificar que el usuario tenga grupos activos
$userPermissions = getUserGroupPermissions($currentUser['id']);
if (!$userPermissions['has_groups']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Usuario sin grupos de acceso']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Leer datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['department_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de departamento requerido']);
    exit;
}

$departmentId = (int)$data['department_id'];

// Verificar que el usuario tenga acceso al departamento
if (!canUserAccessDepartment($currentUser['id'], $departmentId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin acceso a este departamento']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Obtener carpetas del departamento
    $query = "
        SELECT id, name, description, folder_color, folder_icon
        FROM document_folders
        WHERE department_id = ? AND is_active = 1
        ORDER BY name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$departmentId]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'folders' => $folders,
        'total' => count($folders)
    ]);
    
} catch (Exception $e) {
    error_log("Error en get_folders.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error obteniendo carpetas'
    ]);
}
?>