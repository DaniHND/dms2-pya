<?php
// modules/companies/actions/get_company_details.php
// Obtener detalles completos de una empresa - DMS2

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
    
    // Obtener información completa de la empresa
    $companyQuery = "SELECT c.*,
                     (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.status != 'deleted') as user_count,
                     (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.status = 'active') as active_users,
                     (SELECT COUNT(*) FROM documents d JOIN users u ON d.user_id = u.id WHERE u.company_id = c.id) as document_count,
                     (SELECT MAX(u.created_at) FROM users u WHERE u.company_id = c.id) as last_user_created
                     FROM companies c 
                     WHERE c.id = ? AND c.status != 'deleted'";
    
    $stmt = $pdo->prepare($companyQuery);
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        throw new Exception('Empresa no encontrada');
    }
    
    // Obtener usuarios de la empresa (últimos 5)
    $usersQuery = "SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.status, u.created_at
                   FROM users u 
                   WHERE u.company_id = ? AND u.status != 'deleted'
                   ORDER BY u.created_at DESC 
                   LIMIT 5";
    
    $stmt = $pdo->prepare($usersQuery);
    $stmt->execute([$companyId]);
    $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear números
    $company['user_count'] = (int) $company['user_count'];
    $company['active_users'] = (int) $company['active_users'];
    $company['document_count'] = (int) $company['document_count'];
    
    // Agregar usuarios recientes
    $company['recent_users'] = $recentUsers;
    
    echo json_encode([
        'success' => true,
        'company' => $company
    ]);
    
} catch (Exception $e) {
    error_log("Error getting company details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>