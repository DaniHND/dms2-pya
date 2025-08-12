<?php
// modules/companies/actions/get_company.php
// Obtener datos de una empresa para edición - DMS2

require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

// Verificar sesión y permisos
try {
    SessionManager::requireLogin();
    SessionManager::requireRole('admin');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $companyId = intval($_GET['id'] ?? 0);
    
    if ($companyId <= 0) {
        throw new Exception('ID de empresa inválido');
    }
    
    // Verificar conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    $query = "SELECT c.* FROM companies c WHERE c.id = ? AND c.status != 'deleted'";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        throw new Exception('Empresa no encontrada');
    }
    
    echo json_encode([
        'success' => true,
        'company' => $company
    ]);
    
} catch (Exception $e) {
    error_log("Error getting company: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>