<?php
// modules/departments/actions/get_departments.php
// Acción para obtener lista de departamentos - DMS2

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
    $search = trim($_GET['search'] ?? '');

    // Construir consulta
    $whereConditions = [];
    $params = [];

    if (!empty($company_id)) {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $company_id;
    }

    if (!empty($status)) {
        $whereConditions[] = "d.status = :status";
        $params['status'] = $status;
    }

    if (!empty($search)) {
        $whereConditions[] = "(d.name LIKE :search OR d.description LIKE :search OR c.name LIKE :search_company)";
        $params['search'] = '%' . $search . '%';
        $params['search_company'] = '%' . $search . '%';
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Consulta principal
    $query = "SELECT d.id, d.name, d.description, d.status, d.created_at,
                     c.name as company_name,
                     CONCAT(u.first_name, ' ', u.last_name) as manager_name,
                     u.email as manager_email,
                     (SELECT COUNT(*) FROM users WHERE department_id = d.id AND status = 'active') as total_users
              FROM departments d 
              LEFT JOIN companies c ON d.company_id = c.id 
              LEFT JOIN users u ON d.manager_id = u.id 
              $whereClause
              ORDER BY d.created_at DESC";

    $departments = fetchAll($query, $params);

    // Formatear datos para la respuesta
    $formattedDepartments = array_map(function($dept) {
        return [
            'id' => $dept['id'],
            'name' => $dept['name'],
            'description' => $dept['description'] ?? '',
            'company_name' => $dept['company_name'] ?? 'Sin empresa',
            'manager_name' => $dept['manager_name'] ?? '',
            'manager_email' => $dept['manager_email'] ?? '',
            'total_users' => intval($dept['total_users']),
            'status' => $dept['status'],
            'created_at' => $dept['created_at'],
            'formatted_date' => date('d/m/Y H:i', strtotime($dept['created_at']))
        ];
    }, $departments);

    echo json_encode([
        'success' => true,
        'departments' => $formattedDepartments,
        'total' => count($formattedDepartments)
    ]);

} catch (Exception $e) {
    error_log("Error en get_departments.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener departamentos'
    ]);
}
?>