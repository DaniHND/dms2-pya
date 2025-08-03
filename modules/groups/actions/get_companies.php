<?php
require_once '../../../config/database.php';
require_once '../../../includes/session.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $query = "
        SELECT 
            id,
            name,
            description,
            address,
            status,
            (SELECT COUNT(*) FROM users WHERE company_id = companies.id AND status = 'active') as user_count
        FROM companies 
        WHERE status = 'active'
        ORDER BY name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedCompanies = array_map(function($company) {
        return [
            'id' => (int)$company['id'],
            'name' => $company['name'],
            'description' => $company['description'] ?: 'Empresa',
            'user_count' => (int)$company['user_count']
        ];
    }, $companies);
    
    echo json_encode([
        'success' => true,
        'companies' => $formattedCompanies,
        'total' => count($formattedCompanies)
    ]);
    
} catch (Exception $e) {
    error_log('Error obteniendo empresas: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}
?>