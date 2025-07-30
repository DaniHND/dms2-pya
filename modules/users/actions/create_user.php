<?php
// modules/users/actions/create_user.php
// Crear nuevo usuario - DMS2

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
    $requiredFields = ['first_name', 'last_name', 'username', 'email', 'role', 'password', 'confirm_password'];
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
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $companyId = !empty($_POST['company_id']) ? intval($_POST['company_id']) : null;
    $downloadEnabled = isset($_POST['download_enabled']) ? 1 : 0;
    
    // Validaciones
    if ($password !== $confirmPassword) {
        throw new Exception('Las contraseñas no coinciden');
    }
    
    if (strlen($password) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no es válido');
    }
    
    if (!in_array($role, ['admin', 'user', 'viewer'])) {
        throw new Exception('Rol inválido');
    }
    
    // Verificar conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Verificar que el username no exista
    $checkUsernameQuery = "SELECT id FROM users WHERE username = ? AND status != 'deleted'";
    $stmt = $pdo->prepare($checkUsernameQuery);
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new Exception('El nombre de usuario ya existe');
    }
    
    // Verificar que el email no exista
    $checkEmailQuery = "SELECT id FROM users WHERE email = ? AND status != 'deleted'";
    $stmt = $pdo->prepare($checkEmailQuery);
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('El email ya está registrado');
    }
    
    // Encriptar contraseña
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insertar usuario
    $insertQuery = "INSERT INTO users (first_name, last_name, username, email, password, role, company_id, download_enabled, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
    
    $stmt = $pdo->prepare($insertQuery);
    $success = $stmt->execute([
        $firstName,
        $lastName, 
        $username,
        $email,
        $hashedPassword,
        $role,
        $companyId,
        $downloadEnabled
    ]);
    
    if ($success) {
        $newUserId = $pdo->lastInsertId();
        
        // Registrar actividad
        $currentUser = SessionManager::getCurrentUser();
        logActivity(
            $currentUser['id'], 
            'user_created', 
            'users', 
            $newUserId, 
            "Usuario {$firstName} {$lastName} ({$username}) creado con rol {$role}"
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'user_id' => $newUserId
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