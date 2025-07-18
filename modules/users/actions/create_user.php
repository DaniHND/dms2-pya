<?php
// modules/users/actions/create_user.php
// Acción para crear nuevos usuarios - DMS2 (CORREGIDO)

require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

// Verificar que el usuario esté logueado y sea admin
SessionManager::requireLogin();
SessionManager::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $currentUser = SessionManager::getCurrentUser();
    
    // Validar campos requeridos
    $requiredFields = ['first_name', 'last_name', 'username', 'email', 'role', 'company_id', 'password'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo {$field} es requerido");
        }
    }
    
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $companyId = intval($_POST['company_id']);
    $password = $_POST['password'];
    $downloadEnabled = isset($_POST['download_enabled']) ? 1 : 0;
    
    // Validaciones adicionales
    if (strlen($firstName) < 2) {
        throw new Exception('El nombre debe tener al menos 2 caracteres');
    }
    
    if (strlen($lastName) < 2) {
        throw new Exception('El apellido debe tener al menos 2 caracteres');
    }
    
    if (strlen($username) < 3) {
        throw new Exception('El nombre de usuario debe tener al menos 3 caracteres');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no es válido');
    }
    
    if (strlen($password) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }
    
    if (!in_array($role, ['admin', 'user', 'viewer'])) {
        throw new Exception('Rol no válido');
    }
    
    // Verificar que no exista el username
    $checkUsername = fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
    if ($checkUsername) {
        throw new Exception('El nombre de usuario ya existe');
    }
    
    // Verificar que no exista el email
    $checkEmail = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
    if ($checkEmail) {
        throw new Exception('El email ya está registrado');
    }
    
    // Verificar que la empresa existe
    $checkCompany = fetchOne("SELECT id FROM companies WHERE id = ? AND status = 'active'", [$companyId]);
    if (!$checkCompany) {
        throw new Exception('La empresa seleccionada no es válida');
    }
    
    // Hashear la contraseña
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Crear el usuario usando insertRecord
    $userData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'username' => $username,
        'email' => $email,
        'password' => $hashedPassword,
        'role' => $role,
        'company_id' => $companyId,
        'download_enabled' => $downloadEnabled,
        'status' => 'active'
    ];
    
    $result = insertRecord('users', $userData);
    
    if ($result) {
        // Obtener el ID del usuario recién creado
        $database = new Database();
        $conn = $database->getConnection();
        $userId = $conn->lastInsertId();
        
        // Registrar actividad
        logActivity($currentUser['id'], 'create_user', 'users', $userId, 
                   "Creó el usuario: {$firstName} {$lastName} (@{$username})");
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'user_id' => $userId
        ]);
    } else {
        throw new Exception('Error al crear el usuario');
    }
    
} catch (Exception $e) {
    error_log("Error creating user: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>