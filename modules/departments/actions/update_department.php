<?php
// modules/departments/actions/update_department.php
// Acción para actualizar departamento - DMS2

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
    
    // Validar y sanitizar datos
    $department_id = intval($_POST['department_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $company_id = intval($_POST['company_id'] ?? 0);
    $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;
    $status = $_POST['status'] ?? 'active';

    // Validaciones básicas
    $errors = [];

    if ($department_id <= 0) {
        $errors[] = 'ID de departamento inválido';
    }

    if (empty($name)) {
        $errors[] = 'El nombre del departamento es requerido';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $errors[] = 'El nombre debe tener entre 2 y 100 caracteres';
    }

    if ($company_id <= 0) {
        $errors[] = 'Debe seleccionar una empresa válida';
    }

    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }

    if (!empty($description) && strlen($description) > 500) {
        $errors[] = 'La descripción no puede exceder 500 caracteres';
    }

    // Verificar que el departamento existe
    $existingDepartment = fetchOne("SELECT * FROM departments WHERE id = :id", ['id' => $department_id]);
    if (!$existingDepartment) {
        $errors[] = 'El departamento no existe';
    }

    // Verificar que la empresa existe
    if ($company_id > 0) {
        $company = fetchOne("SELECT id FROM companies WHERE id = :id AND status = 'active'", ['id' => $company_id]);
        if (!$company) {
            $errors[] = 'La empresa seleccionada no existe o está inactiva';
        }
    }

    // Verificar que el manager existe y pertenece a la empresa
    if ($manager_id) {
        $manager = fetchOne(
            "SELECT id FROM users WHERE id = :id AND company_id = :company_id AND status = 'active'", 
            ['id' => $manager_id, 'company_id' => $company_id]
        );
        if (!$manager) {
            $errors[] = 'El manager seleccionado no existe o no pertenece a la empresa';
        }
    }

    // Verificar nombre único en la empresa (excluyendo el departamento actual)
    $existingName = fetchOne(
        "SELECT id FROM departments WHERE name = :name AND company_id = :company_id AND id != :department_id", 
        ['name' => $name, 'company_id' => $company_id, 'department_id' => $department_id]
    );
    
    if ($existingName) {
        $errors[] = 'Ya existe otro departamento con este nombre en la empresa seleccionada';
    }

    // Validación especial: No permitir desactivar si tiene usuarios activos
    if ($status === 'inactive') {
        $activeUsers = fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE department_id = :id AND status = 'active'",
            ['id' => $department_id]
        );
        
        if ($activeUsers['count'] > 0) {
            $errors[] = 'No se puede desactivar el departamento porque tiene usuarios activos. Primero reasigne o desactive los usuarios.';
        }
    }

    if (!empty($errors)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Errores de validación',
            'errors' => $errors
        ]);
        exit;
    }

    // Actualizar departamento
    $database = new Database();
    $pdo = $database->getConnection();

    $query = "UPDATE departments 
              SET name = :name, 
                  description = :description, 
                  company_id = :company_id, 
                  manager_id = :manager_id, 
                  status = :status, 
                  updated_at = NOW() 
              WHERE id = :id";

    $params = [
        'id' => $department_id,
        'name' => $name,
        'description' => $description,
        'company_id' => $company_id,
        'manager_id' => $manager_id,
        'status' => $status
    ];

    $stmt = $pdo->prepare($query);
    $success = $stmt->execute($params);

    if ($success) {
        // Si cambió la empresa, actualizar usuarios del departamento
        if ($existingDepartment['company_id'] != $company_id) {
            // Actualizar company_id de todos los usuarios del departamento
            $updateUsersQuery = "UPDATE users SET company_id = :new_company_id WHERE department_id = :department_id";
            $updateUsersStmt = $pdo->prepare($updateUsersQuery);
            $updateUsersStmt->execute([
                'new_company_id' => $company_id,
                'department_id' => $department_id
            ]);
        }
        
        // Registrar actividad
        $changes = [];
        if ($existingDepartment['name'] != $name) $changes[] = "nombre: '{$existingDepartment['name']}' → '$name'";
        if ($existingDepartment['company_id'] != $company_id) $changes[] = "empresa cambiada";
        if ($existingDepartment['manager_id'] != $manager_id) $changes[] = "manager cambiado";
        if ($existingDepartment['status'] != $status) $changes[] = "estado: '{$existingDepartment['status']}' → '$status'";
        
        $changeLog = !empty($changes) ? 'Cambios: ' . implode(', ', $changes) : 'Departamento actualizado';
        
        logActivity(
            $currentUser['id'], 
            'update_department', 
            'departments', 
            $department_id, 
            "Actualizó el departamento: $name. $changeLog"
        );

        // Obtener datos actualizados del departamento
        $updatedDepartment = fetchOne(
            "SELECT d.*, c.name as company_name,
                    CONCAT(u.first_name, ' ', u.last_name) as manager_name
             FROM departments d 
             LEFT JOIN companies c ON d.company_id = c.id 
             LEFT JOIN users u ON d.manager_id = u.id 
             WHERE d.id = :id",
            ['id' => $department_id]
        );

        // Validar que se obtuvo el departamento
        if (!$updatedDepartment) {
            throw new Exception('No se pudo obtener el departamento actualizado');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Departamento actualizado exitosamente',
            'department' => $updatedDepartment
        ]);
    } else {
        throw new Exception('Error al actualizar el departamento en la base de datos');
    }

} catch (Exception $e) {
    error_log("Error en update_department.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor. Por favor, inténtelo de nuevo.'
    ]);
}
?>