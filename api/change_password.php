<?php
// api/change_password.php
// API para cambiar la contraseña del usuario - DMS2

session_start();
header('Content-Type: application/json');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado'
    ]);
    exit;
}

// Incluir configuración de base de datos
require_once __DIR__ . '/../config/database.php';

try {
    // Obtener y validar datos del formulario
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validaciones
    if (empty($currentPassword)) {
        $errors['current_password'] = 'La contraseña actual es requerida';
    }
    
    if (empty($newPassword)) {
        $errors['new_password'] = 'La nueva contraseña es requerida';
    } elseif (strlen($newPassword) < 8) {
        $errors['new_password'] = 'La contraseña debe tener al menos 8 caracteres';
    } elseif (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $newPassword)) {
        $errors['new_password'] = 'Debe incluir mayúsculas, minúsculas y números';
    }
    
    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Debe confirmar la nueva contraseña';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Las contraseñas no coinciden';
    }
    
    if ($currentPassword === $newPassword && !empty($currentPassword) && !empty($newPassword)) {
        $errors['new_password'] = 'La nueva contraseña debe ser diferente a la actual';
    }
    
    // Si hay errores de validación, retornarlos
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Por favor corrija los errores en el formulario',
            'errors' => $errors
        ]);
        exit;
    }
    
    // Obtener información del usuario actual
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT id, password 
        FROM users 
        WHERE id = :user_id 
        AND status = 'active'
    ");
    
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Verificar contraseña actual
    if (!password_verify($currentPassword, $user['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'La contraseña actual es incorrecta',
            'errors' => [
                'current_password' => 'La contraseña actual es incorrecta'
            ]
        ]);
        exit;
    }
    
    // Hashear nueva contraseña
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Actualizar contraseña en la base de datos
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET password = :password,
            updated_at = NOW()
        WHERE id = :user_id
    ");
    
    $updateStmt->execute([
        'password' => $hashedPassword,
        'user_id' => $userId
    ]);
    
    // Registrar en el log de actividades
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, module, record_id, details, ip_address, created_at)
            VALUES (:user_id, 'password_change', 'users', :user_id, :details, :ip, NOW())
        ");
        
        $logStmt->execute([
            'user_id' => $userId,
            'details' => 'Contraseña actualizada correctamente',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // No fallar si el log no se puede registrar
        error_log("Error al registrar cambio de contraseña: " . $e->getMessage());
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Contraseña actualizada correctamente'
    ]);
    
} catch (PDOException $e) {
    error_log("Error de base de datos en change_password.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor. Por favor intente nuevamente.'
    ]);
    
} catch (Exception $e) {
    error_log("Error en change_password.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
