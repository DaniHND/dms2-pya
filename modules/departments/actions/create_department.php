<?php
// modules/departments/actions/create_department.php
// Acción para crear nuevo departamento - DMS2

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
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $company_id = intval($_POST['company_id'] ?? 0);
    $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;
    $status = $_POST['status'] ?? 'active';

    // Validaciones básicas
    $errors = [];

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

    // Verificar nombre único en la empresa
    $existingDepartment = fetchOne(
        "SELECT id FROM departments WHERE name = :name AND company_id = :company_id", 
        ['name' => $name, 'company_id' => $company_id]
    );
    
    if ($existingDepartment) {
        $errors[] = 'Ya existe un departamento con este nombre en la empresa seleccionada';
    }

    if (!empty($errors)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Errores de validación',
            'errors' => $errors
        ]);
        exit;
    }

    // Insertar nuevo departamento
    $database = new Database();
    $pdo = $database->getConnection();

    $query = "INSERT INTO departments (name, description, company_id, manager_id, status, created_at) 
              VALUES (:name, :description, :company_id, :manager_id, :status, NOW())";

    $params = [
        'name' => $name,
        'description' => $description,
        'company_id' => $company_id,
        'manager_id' => $manager_id,
        'status' => $status
    ];

    $stmt = $pdo->prepare($query);
    $success = $stmt->execute($params);

    if ($success) {
        $departmentId = $pdo->lastInsertId();
        
        // Registrar actividad
        logActivity(
            $currentUser['id'], 
            'create_department', 
            'departments', 
            $departmentId, 
            "Creó el departamento: $name"
        );

        // Obtener datos del departamento creado para la respuesta
        $newDepartment = fetchOne(
            "SELECT d.*, c.name as company_name,
                    CONCAT(u.first_name, ' ', u.last_name) as manager_name
             FROM departments d 
             LEFT JOIN companies c ON d.company_id = c.id 
             LEFT JOIN users u ON d.manager_id = u.id 
             WHERE d.id = :id",
            ['id' => $departmentId]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Departamento creado exitosamente',
            'department' => $newDepartment
        ]);
    } else {
        throw new Exception('Error al insertar el departamento en la base de datos');
    }

} catch (Exception $e) {
    error_log("Error en create_department.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor. Por favor, inténtelo de nuevo.'
    ]);
}
?>