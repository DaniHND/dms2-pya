<?php
// modules/departments/actions/get_department_details.php
// Acción para obtener detalles de un departamento específico - DMS2 (VERSIÓN CORREGIDA)

// Evitar cualquier output antes del JSON
ob_start();

try {
    // Corregir rutas relativas - desde modules/departments/actions/ necesitamos ir 3 niveles atrás
    $configPath = dirname(__FILE__) . '/../../../config/session.php';
    $databasePath = dirname(__FILE__) . '/../../../config/database.php';
    $functionsPath = dirname(__FILE__) . '/../../../includes/functions.php';
    
    // Verificar que los archivos existen antes de incluirlos
    if (!file_exists($configPath)) {
        throw new Exception("No se encontró config/session.php en la ruta: " . $configPath);
    }
    
    if (!file_exists($databasePath)) {
        throw new Exception("No se encontró config/database.php en la ruta: " . $databasePath);
    }
    
    if (!file_exists($functionsPath)) {
        throw new Exception("No se encontró includes/functions.php en la ruta: " . $functionsPath);
    }
    
    require_once $configPath;
    require_once $databasePath;
    require_once $functionsPath;

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
    $departmentQuery = "SELECT d.id, d.name, d.description, d.company_id, d.manager_id, d.parent_id, d.status, d.created_at, d.updated_at,
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
    $usersQuery = "SELECT id, first_name, last_name, email, role, status, created_at
                   FROM users 
                   WHERE department_id = :department_id 
                   ORDER BY status DESC, last_name ASC, first_name ASC";

    $users = fetchAll($usersQuery, ['department_id' => $departmentId]);

    // Formatear usuarios para la respuesta
    $formattedUsers = array_map(function($user) {
        return [
            'id' => $user['id'],
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'email' => $user['email'] ?? '',
            'role' => $user['role'] ?? '',
            'status' => $user['status'] ?? '',
            'created_at' => $user['created_at'] ?? '',
            'formatted_date' => !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : ''
        ];
    }, $users);

    // Preparar respuesta
    $response = [
        'success' => true,
        'department' => [
            'id' => $department['id'],
            'name' => $department['name'] ?? '',
            'description' => $department['description'] ?? '',
            'company_id' => $department['company_id'] ?? null,
            'company_name' => $department['company_name'] ?? '',
            'manager_id' => $department['manager_id'] ?? null,
            'manager_name' => $managerName,
            'manager_email' => $department['manager_email'] ?? '',
            'parent_id' => $department['parent_id'] ?? null,
            'status' => $department['status'] ?? 'active',
            'created_at' => $department['created_at'] ?? '',
            'updated_at' => $department['updated_at'] ?? '',
            'formatted_created_date' => !empty($department['created_at']) ? date('d/m/Y H:i', strtotime($department['created_at'])) : '',
            'formatted_updated_date' => !empty($department['updated_at']) ? date('d/m/Y H:i', strtotime($department['updated_at'])) : ''
        ],
        'statistics' => [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $inactiveUsers
        ],
        'users' => $formattedUsers
    ];

    // Enviar respuesta JSON
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Limpiar output buffer en caso de error
    ob_clean();
    
    error_log("Error en get_department_details.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'debug' => $e->getMessage() // Solo para desarrollo, remover en producción
    ], JSON_UNESCAPED_UNICODE);
} finally {
    ob_end_flush();
}
?>