<?php
// modules/users/actions/create_user.php
// Crear nuevo usuario

header('Content-Type: application/json');
require_once '../../../config/session.php';
require_once '../../../config/database.php';

try {
    SessionManager::requireLogin();
    SessionManager::requireRole('admin');
    
    $currentUser = SessionManager::getCurrentUser();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener y validar datos
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = trim($_POST['role'] ?? '');
    $companyId = intval($_POST['company_id'] ?? 0);
    $downloadEnabled = isset($_POST['download_enabled']) ? 1 : 0;
    
    // Validaciones
    if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
        throw new Exception('Todos los campos obligatorios deben ser completados');
    }
    
    if ($password !== $confirmPassword) {
        throw new Exception('Las contraseñas no coinciden');
    }
    
    if (strlen($password) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no tiene un formato válido');
    }
    
    if (!in_array($role, ['admin', 'manager', 'user', 'viewer'])) {
        throw new Exception('Rol no válido');
    }
    
    if ($companyId <= 0) {
        throw new Exception('Debe seleccionar una empresa válida');
    }
    
    // Verificar que no exista el username o email
    $existingUser = fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
    if ($existingUser) {
        throw new Exception('El usuario o email ya existe');
    }
    
    // Hashear contraseña
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insertar usuario
    $sql = "INSERT INTO users (first_name, last_name, username, email, password, role, status, company_id, download_enabled, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())";
    
    $stmt = executeQuery($sql, [
        $firstName,
        $lastName,
        $username,
        $email,
        $hashedPassword,
        $role,
        $companyId,
        $downloadEnabled
    ]);
    
    if ($stmt) {
        // Obtener el ID del usuario recién creado usando la conexión PDO
        $conn = getDbConnection();
        $newUserId = $conn->lastInsertId();
        
        // Registrar actividad
        logActivity($currentUser['id'], 'create_user', 'users', $newUserId, 
                   "Usuario creado: {$firstName} {$lastName} (@{$username})");
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => [
                'user_id' => $newUserId,
                'username' => $username,
                'email' => $email
            ]
        ]);
    } else {
        throw new Exception('Error al crear el usuario');
    }
    
} catch (Exception $e) {
    error_log("Error creating user: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>