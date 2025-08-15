<?php
/**
 * modules/documents/get_departments.php
 * AJAX endpoint para cargar departamentos por empresa
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

if (!$companyId) {
    echo json_encode(['success' => false, 'message' => 'ID de empresa requerido']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Verificar acceso a la empresa
    $userPermissions = getUserGroupPermissions($currentUser['id']);
    
    if ($userPermissions['has_groups'] && !canUserAccessCompany($currentUser['id'], $companyId)) {
        echo json_encode(['success' => false, 'message' => 'Sin acceso a esta empresa']);
        exit;
    }

    // Obtener departamentos
    $departments = [];
    
    if ($userPermissions['has_groups'] && !empty($userPermissions['restrictions']['departments'])) {
        // Usuario con restricciones de departamentos
        $departments = getUserAllowedDepartments($currentUser['id'], $companyId);
    } else {
        // Sin restricciones o administrador
        $query = "SELECT id, name FROM departments WHERE company_id = ? AND status = 'active' ORDER BY name";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$companyId]);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'departments' => $departments
    ]);

} catch (Exception $e) {
    error_log("Error en get_departments.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}