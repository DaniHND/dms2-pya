<?php
// modules/companies/actions/create_company.php
// Crear nueva empresa - DMS2

require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

// Verificar sesión y permisos
try {
    SessionManager::requireLogin();
    SessionManager::requireRole('admin');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Validar datos requeridos
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        throw new Exception('El nombre de la empresa es requerido');
    }
    
    if (strlen($name) < 2) {
        throw new Exception('El nombre de la empresa debe tener al menos 2 caracteres');
    }
    
    $description = trim($_POST['description'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validar email si se proporciona
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no es válido');
    }
    
    // Verificar conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Verificar que el nombre de empresa no exista
    $checkNameQuery = "SELECT id FROM companies WHERE name = ? AND status != 'deleted'";
    $stmt = $pdo->prepare($checkNameQuery);
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        throw new Exception('Ya existe una empresa con este nombre');
    }
    
    // Verificar que el email no exista si se proporciona
    if (!empty($email)) {
        $checkEmailQuery = "SELECT id FROM companies WHERE email = ? AND status != 'deleted'";
        $stmt = $pdo->prepare($checkEmailQuery);
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Ya existe una empresa con este email');
        }
    }
    
    // Insertar empresa
    $insertQuery = "INSERT INTO companies (name, description, email, phone, address, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'active', NOW())";
    
    $stmt = $pdo->prepare($insertQuery);
    $success = $stmt->execute([
        $name,
        $description ?: null,
        $email ?: null,
        $phone ?: null,
        $address ?: null
    ]);
    
    if ($success) {
        $newCompanyId = $pdo->lastInsertId();
        
        // Registrar actividad
        $currentUser = SessionManager::getCurrentUser();
        logActivity(
            $currentUser['id'], 
            'company_created', 
            'companies', 
            $newCompanyId, 
            "Empresa '{$name}' creada en el sistema"
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Empresa creada exitosamente',
            'company_id' => $newCompanyId
        ]);
    } else {
        throw new Exception('Error al crear la empresa');
    }
    
} catch (Exception $e) {
    error_log("Error creating company: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>