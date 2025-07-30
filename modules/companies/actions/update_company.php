<?php
// modules/companies/actions/update_company.php
// Actualizar empresa - DMS2

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
    $companyId = intval($_POST['company_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    
    if ($companyId <= 0) {
        throw new Exception('ID de empresa inválido');
    }
    
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
    $status = $_POST['status'] ?? 'active';
    
    // Validar email si se proporciona
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no es válido');
    }
    
    // Validar estado
    if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception('Estado inválido');
    }
    
    // Verificar conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Verificar que la empresa existe
    $checkQuery = "SELECT id, name FROM companies WHERE id = ? AND status != 'deleted'";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$companyId]);
    $existingCompany = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingCompany) {
        throw new Exception('Empresa no encontrada');
    }
    
    // Verificar que el nombre no esté en uso por otra empresa
    $checkNameQuery = "SELECT id FROM companies WHERE name = ? AND id != ? AND status != 'deleted'";
    $stmt = $pdo->prepare($checkNameQuery);
    $stmt->execute([$name, $companyId]);
    if ($stmt->fetch()) {
        throw new Exception('Ya existe otra empresa con este nombre');
    }
    
    // Verificar que el email no esté en uso por otra empresa (si se proporciona)
    if (!empty($email)) {
        $checkEmailQuery = "SELECT id FROM companies WHERE email = ? AND id != ? AND status != 'deleted'";
        $stmt = $pdo->prepare($checkEmailQuery);
        $stmt->execute([$email, $companyId]);
        if ($stmt->fetch()) {
            throw new Exception('Ya existe otra empresa con este email');
        }
    }
    
    // Verificar si se puede desactivar la empresa
    if ($status === 'inactive') {
        $activeUsersQuery = "SELECT COUNT(*) as count FROM users WHERE company_id = ? AND status = 'active'";
        $stmt = $pdo->prepare($activeUsersQuery);
        $stmt->execute([$companyId]);
        $activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($activeUsers > 0) {
            throw new Exception("No se puede desactivar la empresa porque tiene {$activeUsers} usuario(s) activo(s). Desactive primero los usuarios.");
        }
    }
    
    // Actualizar empresa
    $updateQuery = "UPDATE companies SET 
                    name = ?, 
                    description = ?, 
                    email = ?, 
                    phone = ?, 
                    address = ?, 
                    status = ?, 
                    updated_at = NOW() 
                    WHERE id = ?";
    
    $stmt = $pdo->prepare($updateQuery);
    $success = $stmt->execute([
        $name,
        $description ?: null,
        $email ?: null,
        $phone ?: null,
        $address ?: null,
        $status,
        $companyId
    ]);
    
    if ($success) {
        // Registrar actividad
        $currentUser = SessionManager::getCurrentUser();
        $changes = [];
        
        if ($existingCompany['name'] !== $name) {
            $changes[] = "nombre cambiado de '{$existingCompany['name']}' a '{$name}'";
        }
        
        $changeDescription = !empty($changes) ? implode(', ', $changes) : 'información actualizada';
        
        logActivity(
            $currentUser['id'], 
            'company_updated', 
            'companies', 
            $companyId, 
            "Empresa '{$name}' actualizada: {$changeDescription}"
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Empresa actualizada exitosamente'
        ]);
    } else {
        throw new Exception('Error al actualizar la empresa');
    }
    
} catch (Exception $e) {
    error_log("Error updating company: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>