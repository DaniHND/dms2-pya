<?php
// modules/departments/actions/toggle_department_status.php
// Acción para cambiar el estado de un departamento - DMS2

require_once '../../../config/session.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Verificar permisos
SessionManager::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $currentUser = SessionManager::getCurrentUser();
    
    // Validar datos
    $department_id = intval($_POST['department_id'] ?? 0);
    $current_status = $_POST['current_status'] ?? '';

    if ($department_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de departamento inválido']);
        exit;
    }

    if (!in_array($current_status, ['active', 'inactive'])) {
        echo json_encode(['success' => false, 'message' => 'Estado actual inválido']);
        exit;
    }

    // Verificar que el departamento existe
    $department = fetchOne(
        "SELECT d.*, c.name as company_name FROM departments d 
         LEFT JOIN companies c ON d.company_id = c.id 
         WHERE d.id = :id",
        ['id' => $department_id]
    );

    if (!$department) {
        echo json_encode(['success' => false, 'message' => 'Departamento no encontrado']);
        exit;
    }

    // Determinar nuevo estado
    $new_status = ($current_status === 'active') ? 'inactive' : 'active';

    // Validación especial: No permitir desactivar si tiene usuarios activos
    if ($new_status === 'inactive') {
        $activeUsers = fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE department_id = :id AND status = 'active'",
            ['id' => $department_id]
        );
        
        if ($activeUsers['count'] > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'No se puede desactivar el departamento porque tiene ' . 
                           $activeUsers['count'] . ' usuario(s) activo(s). ' .
                           'Primero reasigne o desactive los usuarios.',
                'users_count' => $activeUsers['count']
            ]);
            exit;
        }
    }

    // Actualizar estado
    $database = new Database();
    $pdo = $database->getConnection();

    $query = "UPDATE departments SET status = :status, updated_at = NOW() WHERE id = :id";
    $params = [
        'id' => $department_id,
        'status' => $new_status
    ];

    $stmt = $pdo->prepare($query);
    $success = $stmt->execute($params);

    if ($success) {
        // Registrar actividad
        $action_text = $new_status === 'active' ? 'activó' : 'desactivó';
        logActivity(
            $currentUser['id'], 
            'toggle_department_status', 
            'departments', 
            $department_id, 
            "Estado cambiado: $action_text el departamento '{$department['name']}'"
        );

        // Preparar respuesta
        $status_text = $new_status === 'active' ? 'activado' : 'desactivado';
        $message = "Departamento {$status_text} exitosamente";

        echo json_encode([
            'success' => true,
            'message' => $message,
            'new_status' => $new_status,
            'department' => [
                'id' => $department['id'],
                'name' => $department['name'],
                'status' => $new_status
            ]
        ]);
    } else {
        throw new Exception('Error al actualizar el estado del departamento');
    }

} catch (Exception $e) {
    error_log("Error en toggle_department_status.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor. Por favor, inténtelo de nuevo.'
    ]);
}
?>