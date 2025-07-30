<?php
// modules/departments/actions/get_department_details.php
// Acción para obtener detalles de un departamento específico - DMS2 (VERSIÓN ROBUSTA)

// Evitar cualquier output antes del JSON
ob_start();

try {
    require_once '../../../config/session.php';
    require_once '../../../config/database.php';
    require_once '../../../includes/functions.php';

    // Limpiar cualquier output previo
    ob_clean();

    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    // Verificar sesión y permisos
    if (!SessionManager::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }

    $currentUser = SessionManager::getCurrentUser();
    if ($currentUser['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
        exit;
    }

    // Validar parámetros
    $departmentId = intval($_GET['id'] ?? 0);
    if ($departmentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de departamento inválido']);
        exit;
    }

    // Obtener detalles del departamento
    $departmentQuery = "SELECT d.id, d.name, d.description, d.company_id, d.manager_id, d.status, d.created_at, d.updated_at,
                               c.name as company_name,
                               u.first_name as manager_first_name,
                               u.last_name as manager_last_name,
                               u.email as manager_email
                        FROM departments d 
                        LEFT JOIN companies c ON d.company_id = c.id 
                        LEFT JOIN users u ON d.manager_id = u.id 
                        WHERE d.id = :id";

    $department = fetchOne($departmentQuery, ['id' => $departmentId]);

    if (!$department) {
        echo json_encode(['success' => false, 'message' => 'Departamento no encontrado']);
        exit;
    }

    // Construir nombre del manager
    $managerName = '';
    if (!empty($department['manager_first_name']) && !empty($department['manager_last_name'])) {
        $managerName = trim($department['manager_first_name'] . ' ' . $department['manager_last_name']);
    }

    // Obtener estadísticas de usuarios
    $userStatsQuery = "SELECT 
                         COUNT(*) as total_users,
                         SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                         SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users
                       FROM users 
                       WHERE department_id = :department_id";

    $userStats = fetchOne($userStatsQuery, ['department_id' => $departmentId]);
    
    // Asegurar que las estadísticas sean números
    $totalUsers = intval($userStats['total_users'] ?? 0);
    $activeUsers = intval($userStats['active_users'] ?? 0);
    $inactiveUsers = intval($userStats['inactive_users'] ?? 0);

    // Obtener lista de usuarios del departamento
    $usersQuery = "SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.status, u.created_at
                   FROM users u 
                   WHERE u.department_id = :department_id 
                   ORDER BY u.first_name, u.last_name";

    $usersResult = fetchAll($usersQuery, ['department_id' => $departmentId]);
    $users = is_array($usersResult) ? $usersResult : [];

    // Formatear usuarios
    $formattedUsers = [];
    foreach ($users as $user) {
        $formattedUsers[] = [
            'id' => intval($user['id']),
            'name' => trim($user['first_name'] . ' ' . $user['last_name']),
            'email' => $user['email'],
            'role' => $user['role'],
            'department_role' => ($user['id'] == $department['manager_id']) ? 'Manager' : $user['role'],
            'status' => $user['status'],
            'created_at' => $user['created_at'],
            'formatted_date' => date('d/m/Y', strtotime($user['created_at']))
        ];
    }

    // Preparar respuesta
    $response = [
        'id' => intval($department['id']),
        'name' => $department['name'],
        'description' => $department['description'] ?? '',
        'company_id' => intval($department['company_id'] ?? 0),
        'company_name' => $department['company_name'] ?? 'Sin empresa',
        'manager_id' => $department['manager_id'] ? intval($department['manager_id']) : null,
        'manager_name' => $managerName,
        'manager_email' => $department['manager_email'] ?? '',
        'status' => $department['status'],
        'created_at' => $department['created_at'],
        'updated_at' => $department['updated_at'],
        'formatted_created' => date('d/m/Y H:i', strtotime($department['created_at'])),
        'formatted_updated' => $department['updated_at'] ? date('d/m/Y H:i', strtotime($department['updated_at'])) : '',
        'users' => $formattedUsers,
        'stats' => [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $inactiveUsers,
            'has_manager' => !empty($department['manager_id'])
        ]
    ];

    // Enviar respuesta JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'department' => $response
    ]);

} catch (Exception $e) {
    // Limpiar cualquier output
    ob_clean();
    
    // Log del error
    error_log("Error en get_department_details.php: " . $e->getMessage());
    
    // Respuesta de error
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}

// Terminar el buffer y enviar
ob_end_flush();
?>