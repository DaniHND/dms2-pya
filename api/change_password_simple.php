<?php
session_start();
header('Content-Type: application/json');

// Verificar petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Incluir configuración
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Obtener datos
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $currentUserId = $_SESSION['user_id'];
    $errors = [];
    
    // Validar nueva contraseña
    if (empty($newPassword)) {
        $errors['new_password'] = 'La nueva contraseña es requerida';
    } elseif (strlen($newPassword) < 6) {
        $errors['new_password'] = 'Debe tener al menos 6 caracteres';
    }
    
    // Validar confirmación
    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Debe confirmar la nueva contraseña';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Las contraseñas no coinciden';
    }
    
    // Si hay errores, retornar
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Por favor corrija los errores',
            'errors' => $errors
        ]);
        exit;
    }
    
    // Hashear nueva contraseña
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Actualizar contraseña
    $stmt = $pdo->prepare("
        UPDATE users 
        SET password = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    $stmt->execute([$hashedPassword, $currentUserId]);
    
    // Registrar en log de actividad
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, table_name, record_id, description, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $currentUserId,
            'password_change',
            'users',
            $currentUserId,
            'Usuario cambió su contraseña',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Error registrando cambio de contraseña: " . $e->getMessage());
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Contraseña actualizada correctamente'
    ]);
    
} catch (PDOException $e) {
    error_log("Error DB en change_password_simple.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor'
    ]);
} catch (Exception $e) {
    error_log("Error en change_password_simple.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
