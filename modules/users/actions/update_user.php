<?php
// modules/users/actions/update_user.php
// Actualizar datos de usuario - DMS2 (CORREGIDO)

require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

SessionManager::requireLogin();
SessionManager::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $currentUser = SessionManager::getCurrentUser();
    
    $userId = intval($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    // Verificar que el usuario existe
    $existingUser = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$existingUser) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Validar campos requeridos
    $requiredFields = ['first_name', 'last_name', 'username', 'email', 'role', 'company_id'];
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
        throw new Exception('El nombre de usuario debe tener al menos 3 caracteres');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no es válido');
    }
    
    if (!in_array($role, ['admin', 'user', 'viewer'])) {
        throw new Exception('Rol no válido');
    }
    
    // Verificar que no exista el username (excepto el actual)
    $checkUsername = fetchOne("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $userId]);
    if ($checkUsername) {
        throw new Exception('El nombre de usuario ya existe');
    }
    
    // Verificar que no exista el email (excepto el actual)
    $checkEmail = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
    if ($checkEmail) {
        throw new Exception('El email ya está registrado');
    }
    
    // Verificar que la empresa existe
    $checkCompany = fetchOne("SELECT id FROM companies WHERE id = ? AND status = 'active'", [$companyId]);
    if (!$checkCompany) {
        throw new Exception('La empresa seleccionada no es válida');
    }
    
    // Preparar datos para actualizar
    $updateData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'company_id' => $companyId,
        'download_enabled' => $downloadEnabled
    ];
    
    // Si se va a cambiar la contraseña
    if ($changePassword) {
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            throw new Exception('La contraseña debe tener al menos 6 caracteres');
        }
        
        $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    // Actualizar el usuario usando updateRecord
    $result = updateRecord('users', $updateData, 'id = :id', ['id' => $userId]);
    
    if ($result) {
        // Registrar actividad
        logActivity($currentUser['id'], 'update_user', 'users', $userId, 
                   "Actualizó el usuario: {$firstName} {$lastName} (@{$username})");
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente'
        ]);
    } else {
        throw new Exception('Error al actualizar el usuario');
    }
    
} catch (Exception $e) {
    error_log("Error updating user: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>