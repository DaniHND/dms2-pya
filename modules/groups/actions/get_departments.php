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
    
    $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
    
    $query = "
        SELECT 
            d.id,
            d.name,
            d.description,
            d.company_id,
            c.name as company_name,
            (SELECT COUNT(*) FROM users WHERE department_id = d.id AND status = 'active') as user_count
        FROM departments d
        LEFT JOIN companies c ON d.company_id = c.id
        WHERE d.status = 'active'
    ";
    
    $params = [];
    if ($companyId) {
        $query .= " AND d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }
    
    $query .= " ORDER BY c.name, d.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedDepartments = array_map(function($dept) {
        return [
            'id' => (int)$dept['id'],
            'name' => $dept['name'],
            'description' => $dept['description'] ?: 'Departamento',
            'company_id' => $dept['company_id'] ? (int)$dept['company_id'] : null,
            'company_name' => $dept['company_name'],
            'user_count' => (int)$dept['user_count'],
            'full_name' => $dept['company_name'] ? $dept['company_name'] . ' - ' . $dept['name'] : $dept['name']
        ];
    }, $departments);
    
    echo json_encode([
        'success' => true,
        'departments' => $formattedDepartments,
        'total' => count($formattedDepartments)
    ]);
    
} catch (Exception $e) {
    error_log('Error obteniendo departamentos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}
?>