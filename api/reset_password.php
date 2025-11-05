<?php
// api/reset_password.php
// API para cambiar la contraseña usando el token

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight request (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ✅ Cargar autoload y configuración con rutas absolutas
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Método no permitido',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
        ]
    ]);
    exit();
}

try {
    // ✅ Obtener datos del request (JSON o POST normal)
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);
    
    // Intentar obtener de JSON primero, luego de POST normal
    $token = '';
    $newPassword = '';
    
    if (!empty($input) && is_array($input)) {
        $token = trim($input['token'] ?? '');
        $newPassword = $input['new_password'] ?? '';
    } elseif (!empty($_POST)) {
        $token = trim($_POST['token'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
    }
    
    // Log para depuración
    error_log("Reset password - Token recibido: " . (!empty($token) ? 'SÍ' : 'NO'));
    error_log("Reset password - Password recibida: " . (!empty($newPassword) ? 'SÍ' : 'NO'));
    
    // Validar datos
    if (empty($token)) {
        throw new Exception('Token inválido');
    }
    
    if (empty($newPassword)) {
        throw new Exception('La nueva contraseña es requerida');
    }
    
    if (strlen($newPassword) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }
    
    // Buscar token válido
    $query = "SELECT prt.*, u.id as user_id, u.username, u.email 
              FROM password_reset_tokens prt
              INNER JOIN users u ON prt.user_id = u.id
              WHERE prt.token = :token 
              AND prt.used = 0 
              AND prt.expires_at > NOW()
              AND u.status = 'active'";
    
    $tokenData = fetchOne($query, ['token' => $token]);
    
    if (!$tokenData) {
        throw new Exception('El enlace de recuperación es inválido o ha expirado');
    }
    
    // Hash de la nueva contraseña
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Actualizar contraseña del usuario
    $updateUserQuery = "UPDATE users 
                        SET password = :password, 
                            updated_at = NOW() 
                        WHERE id = :user_id";
    
    executeQuery($updateUserQuery, [
        'password' => $hashedPassword,
        'user_id' => $tokenData['user_id']
    ]);
    
    // Marcar token como usado
    $updateTokenQuery = "UPDATE password_reset_tokens 
                         SET used = 1, 
                             used_at = NOW() 
                         WHERE id = :token_id";
    
    executeQuery($updateTokenQuery, ['token_id' => $tokenData['id']]);
    
    // Registrar actividad
    logActivity($tokenData['user_id'], 'password_reset_completed', 'users', 
                $tokenData['user_id'], 'Usuario cambió su contraseña mediante recuperación');
    
    echo json_encode([
        'success' => true,
        'message' => 'Contraseña actualizada correctamente. Redirigiendo al login...'
    ]);
    
} catch (Exception $e) {
    error_log("Error en reset_password: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}