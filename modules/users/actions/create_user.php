<?php
// modules/users/actions/create_user.php
// Crear usuario con soporte para checkboxes

header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

try {
    // Verificar autenticación
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }
    
    $currentUser = getCurrentUser();
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
    
    // Verificar que la empresa existe
    $checkCompany = fetchOne("SELECT id FROM companies WHERE id = ? AND status = 'active'", [$companyId]);
    if (!$checkCompany) {
        throw new Exception('La empresa seleccionada no es válida');
    }
    
    // Encriptar contraseña
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Preparar datos para insertar
    $insertData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'username' => $username,
        'email' => $email,
        'password' => $hashedPassword,
        'role' => $role,
        'company_id' => $companyId,
        'download_enabled' => $downloadEnabled,
        'status' => 'active',
        'created_by' => $currentUser['id'],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Construir query de inserción
    $fields = array_keys($insertData);
    $placeholders = array_fill(0, count($fields), '?');
    $values = array_values($insertData);
    
    $sql = "INSERT INTO users (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    // Ejecutar la inserción
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($values);
    
    if ($result) {
        $newUserId = $pdo->lastInsertId();
        
        // Registrar actividad
        $activityDescription = "Creó el usuario: {$firstName} {$lastName} (@{$username})";
        logActivity($currentUser['id'], 'create_user', 'users', $newUserId, $activityDescription);
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => [
                'user_id' => $newUserId,
                'username' => $username,
                'email' => $email,
                'download_enabled' => $downloadEnabled
            ]
        ]);
    } else {
        throw new Exception('Error al crear el usuario en la base de datos');
    }
    
} catch (Exception $e) {
    error_log("Error creating user: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'CREATE_USER_ERROR'
    ]);
}

// Función auxiliar para registrar actividad
function logActivity($userId, $action, $table, $recordId, $description) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $action,
            $table,
            $recordId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

// Función auxiliar para obtener usuario actual
function getCurrentUser() {
    global $pdo;
    
    try {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return false;
    }
}

// Función auxiliar para obtener un registro
function fetchOne($sql, $params = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching record: " . $e->getMessage());
        return false;
    }
}
?>