<?php
/*
 * modules/departments/actions/get_departments.php
 * API simple para obtener departamentos por empresa
 */

header('Content-Type: application/json');

require_once '../../../config/session.php';
require_once '../../../config/database.php';

try {
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$companyId = intval($_GET['company_id'] ?? 0);

if ($companyId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de empresa inválido']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Verificar permisos
    if ($currentUser['role'] !== 'admin' && $currentUser['company_id'] != $companyId) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para ver departamentos de esta empresa']);
        exit;
    }
    
    // Obtener departamentos
    $query = "SELECT id, name, description FROM departments WHERE company_id = :company_id AND status = 'active' ORDER BY name";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['company_id' => $companyId]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'departments' => $departments
    ]);
    
} catch (Exception $e) {
    error_log("Error en get_departments.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>