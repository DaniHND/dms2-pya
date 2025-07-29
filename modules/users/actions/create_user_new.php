<?php
// modules/users/actions/create_user_new.php
// Acción para crear usuario nuevo - con seguridad completa

header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

try {
    // Verificar autenticación
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }
    
    // Obtener usuario actual
    $currentUser = fetchOne("SELECT * FROM users WHERE id = ? AND status = 'active'", [$_SESSION['user_id']]);
    if (!$currentUser) {
        throw new Exception('Usuario no válido');
    }
    
    // Verificar permisos (solo admin puede crear usuarios)
    if ($currentUser['role'] !== 'admin') {
        throw new Exception('No tienes permisos para realizar esta acción');
    }
    
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener y validar datos del formulario
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $companyId = intval($_POST['company_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Manejar checkbox correctamente
    $downloadEnabled = isset($_POST['download_enabled']) ? 1 : 0;
    
    // Validaciones básicas
    if (strlen($firstName) < 2) {
        throw new Exception('El nombre debe tener al menos 2 caracteres');
    }
    
    if (strlen($lastName) < 2) {
        throw new Exception('El apellido debe tener al menos 2 caracteres');
    }
    
    if (strlen($username) < 3) {
        throw new Exception('El nombre de usuario debe tener al menos 3 caracteres');
    }
    
    // Validar que el username solo contenga caracteres permitidos
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        throw new Exception('El nombre de usuario solo puede contener letras, números, guiones y puntos');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no es válido');
    }
    
    if (!in_array($role, ['admin', 'user', 'viewer'])) {
        throw new Exception('Rol no válido');
    }
    
    if ($companyId <= 0) {
        throw new Exception('Debe seleccionar una empresa válida');
    }
    
    if (empty($password)) {
        throw new Exception('La contraseña es requerida');
    }
    
    if (strlen($password) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }
    
    if ($password !== $confirmPassword) {
        throw new Exception('Las contraseñas no coinciden');
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
    
    // Verificar que la empresa existe y está activa
    $checkCompany = fetchOne("SELECT id FROM companies WHERE id = ? AND status = 'active'", [$companyId]);
    if (!$checkCompany) {
        throw new Exception('La empresa seleccionada no es válida');
    }
    
    // Encriptar contraseña
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Preparar datos para insertar (sin created_by si no existe la columna)
    $insertData = [
        $firstName,
        $lastName,
        $username,
        $email,
        $hashedPassword,
        $role,
        $companyId,
        $downloadEnabled,
        'active', // status
        date('Y-m-d H:i:s') // created_at
    ];
    
    // SQL para insertar usuario (sin created_by y updated_at)
    $sql = "INSERT INTO users (
        first_name, 
        last_name, 
        username, 
        email, 
        password, 
        role, 
        company_id, 
        download_enabled, 
        status, 
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    // Ejecutar la inserción
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($insertData);
    
    if ($result) {
        $newUserId = $pdo->lastInsertId();
        
        // Registrar actividad en el log
        $activityDescription = "Creó el usuario: {$firstName} {$lastName} (@{$username}) - Rol: {$role}";
        
        // Intentar registrar actividad (no fallar si no funciona)
        try {
            $logSql = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $logStmt = $pdo->prepare($logSql);
            $logStmt->execute([
                $currentUser['id'],
                'create_user',
                'users',
                $newUserId,
                $activityDescription,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $logError) {
            error_log("Error logging activity: " . $logError->getMessage());
            // No fallar por esto, solo logear el error
        }
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => [
                'user_id' => $newUserId,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'company_id' => $companyId,
                'download_enabled' => $downloadEnabled
            ]
        ]);
        
    } else {
        throw new Exception('Error al crear el usuario en la base de datos');
    }
    
} catch (Exception $e) {
    // Log del error para debugging
    error_log("Error creating user: " . $e->getMessage() . " | File: " . __FILE__ . " | Line: " . __LINE__);
    
    // Respuesta de error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'CREATE_USER_ERROR'
    ]);
}
?>