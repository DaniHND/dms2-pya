<?php
// modules/users/actions/get_users.php
// Acción para obtener usuarios filtrados - DMS2
// (Esta acción ya existe, pero agregamos funcionalidad para filtros específicos)

require_once '../../../config/session.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Verificar permisos
SessionManager::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $currentUser = SessionManager::getCurrentUser();
    
    // Parámetros de consulta
    $company_id = $_GET['company_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $role = $_GET['role'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $exclude_id = $_GET['exclude_id'] ?? '';

    // Construir consulta
    $whereConditions = [];
    $params = [];

    if (!empty($company_id)) {
        $whereConditions[] = "u.company_id = :company_id";
        $params['company_id'] = $company_id;
    }

    if (!empty($status)) {
        $whereConditions[] = "u.status = :status";
        $params['status'] = $status;
    }

    if (!empty($role)) {
        $whereConditions[] = "u.role = :role";
        $params['role'] = $role;
    }

    if (!empty($search)) {
        $whereConditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE :search OR u.email LIKE :search_email OR u.username LIKE :search_username)";
        $params['search'] = '%' . $search . '%';
        $params['search_email'] = '%' . $search . '%';
        $params['search_username'] = '%' . $search . '%';
    }

    if (!empty($exclude_id)) {
        $whereConditions[] = "u.id != :exclude_id";
        $params['exclude_id'] = $exclude_id;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Consulta principal
    $query = "SELECT u.id, u.first_name, u.last_name, u.email, u.username, u.role, u.status, u.created_at,
                     c.name as company_name,
                     d.name as department_name
              FROM users u 
              LEFT JOIN companies c ON u.company_id = c.id 
              LEFT JOIN departments d ON u.department_id = d.id 
              $whereClause
              ORDER BY u.first_name, u.last_name";

    $users = fetchAll($query, $params);

    // Formatear datos para la respuesta
    $formattedUsers = array_map(function($user) {
        return [
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'full_name' => $user['first_name'] . ' ' . $user['last_name'],
            'email' => $user['email'],
            'username' => $user['username'],
            'role' => $user['role'],
            'status' => $user['status'],
            'company_name' => $user['company_name'] ?? '',
            'department_name' => $user['department_name'] ?? '',
            'created_at' => $user['created_at'],
            'formatted_date' => date('d/m/Y', strtotime($user['created_at']))
        ];
    }, $users);

    echo json_encode([
        'success' => true,
        'users' => $formattedUsers,
        'total' => count($formattedUsers)
    ]);

} catch (Exception $e) {
    error_log("Error en get_users.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener usuarios'
    ]);
}
?>