<?php
require_once '../../../config/database.php';
require_once '../../../includes/session.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $query = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.username,
            u.role,
            u.status,
            u.company_id,
            u.department_id,
            c.name as company_name,
            d.name as department_name
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedUsers = array_map(function($user) {
        return [
            'id' => (int)$user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'username' => $user['username'],
            'role' => $user['role'],
            'company_id' => $user['company_id'] ? (int)$user['company_id'] : null,
            'department_id' => $user['department_id'] ? (int)$user['department_id'] : null,
            'company_name' => $user['company_name'],
            'department_name' => $user['department_name'],
            'full_name' => trim($user['first_name'] . ' ' . $user['last_name'])
        ];
    }, $users);
    
    echo json_encode([
        'success' => true,
        'users' => $formattedUsers,
        'total' => count($formattedUsers)
    ]);
    
} catch (Exception $e) {
    error_log('Error obteniendo usuarios: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}
?>