<?php
// modules/users/actions/update_user.php
// Actualizar usuario existente

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
    
    // Obtener ID del usuario a actualizar
    $userId = intval($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new Exception('ID de usuario no válido');
    }
    
    // Verificar que el usuario existe
    $existingUser = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$existingUser) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Obtener datos del formulario
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $companyId = intval($_POST['company_id'] ?? 0);
    $downloadEnabled = isset($_POST['download_enabled']) ? 1 : 0;
    $changePassword = isset($_POST['change_password']);
    
    // Validaciones
    if (strlen($firstName) < 2) {
        throw new Exception('El nombre debe tener al menos 2 caracteres');
    }
    
    if (strlen($lastName) < 2) {
        throw new Exception('El apellido debe tener al menos 2 caracteres');
    }
    
    if (strlen($username) < 3) {
        throw new Exception('El usuario debe tener al menos 3 caracteres');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no es válido');
    }
    
    if (!in_array($role, ['admin', 'manager', 'user', 'viewer'])) {
        throw new Exception('Rol no válido');
    }
    
    if ($companyId <= 0) {
        throw new Exception('Debe seleccionar una empresa válida');
    }
    
    // Verificar unicidad
    $checkUsername = fetchOne("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $userId]);
    if ($checkUsername) {
        throw new Exception('El nombre de usuario ya existe');
    }
    
    $checkEmail = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
    if ($checkEmail) {
        throw new Exception('El email ya está en uso');
    }
    
    // Preparar consulta de actualización
    $updateFields = [
        'first_name = ?',
        'last_name = ?',
        'username = ?',
        'email = ?',
        'role = ?',
        'company_id = ?',
        'download_enabled = ?'
    ];
    
    $updateParams = [
        $firstName,
        $lastName,
        $username,
        $email,
        $role,
        $companyId,
        $downloadEnabled
    ];
    
    // Si se va a cambiar contraseña
    if ($changePassword) {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || empty($confirmPassword)) {
            throw new Exception('Debe completar ambos campos de contraseña');
        }
        
        if ($password !== $confirmPassword) {
            throw new Exception('Las contraseñas no coinciden');
        }
        
        if (strlen($password) < 6) {
            throw new Exception('La contraseña debe tener al menos 6 caracteres');
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateFields[] = 'password = ?';
        $updateParams[] = $hashedPassword;
    }
    
    // Agregar ID del usuario al final
    $updateParams[] = $userId;
    
    // Ejecutar actualización
    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = executeQuery($sql, $updateParams);
    
    if ($stmt) {
        // Registrar actividad
        logActivity($currentUser['id'], 'update_user', 'users', $userId, 
                   "Usuario actualizado: {$firstName} {$lastName} (@{$username})");
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'data' => [
                'user_id' => $userId,
                'username' => $username,
                'email' => $email,
                'password_changed' => $changePassword
            ]
        ]);
    } else {
        throw new Exception('Error al actualizar el usuario');
    }
    
} catch (Exception $e) {
    error_log("Error updating user: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>