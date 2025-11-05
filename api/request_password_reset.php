<?php
// api/request_password_reset.php
// API para solicitar recuperación de contraseña por email

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

require_once '../config/database.php';
require_once '../config/email.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    // Obtener email del request
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    
    // Validar email
    if (empty($email)) {
        throw new Exception('El email es requerido');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }
    
    // Buscar usuario por email
    $query = "SELECT id, username, email, first_name, last_name, status 
              FROM users 
              WHERE email = :email AND status = 'active'";
    
    $user = fetchOne($query, ['email' => $email]);
    
    // Por seguridad, siempre devolver éxito aunque el email no exista
    // Esto previene que se pueda verificar si un email existe en el sistema
    if (!$user) {
        // Esperar un poco para simular procesamiento
        sleep(1);
        echo json_encode([
            'success' => true,
            'message' => 'Si el email existe en nuestro sistema, recibirás instrucciones para recuperar tu contraseña.'
        ]);
        exit();
    }
    
    // Generar token seguro
    $token = bin2hex(random_bytes(32)); // 64 caracteres hexadecimales
    
    // Calcular expiración (1 hora desde ahora)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Obtener IP y User Agent
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Invalidar tokens anteriores del mismo usuario (opcional)
    $invalidateQuery = "UPDATE password_reset_tokens 
                       SET used = 1, used_at = NOW() 
                       WHERE user_id = :user_id AND used = 0";
    executeQuery($invalidateQuery, ['user_id' => $user['id']]);
    
    // Insertar nuevo token
    $insertQuery = "INSERT INTO password_reset_tokens 
                    (user_id, email, token, expires_at, ip_address, user_agent) 
                    VALUES 
                    (:user_id, :email, :token, :expires_at, :ip_address, :user_agent)";
    
    $params = [
        'user_id' => $user['id'],
        'email' => $email,
        'token' => $token,
        'expires_at' => $expiresAt,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent
    ];
    
    executeQuery($insertQuery, $params);
    
    // Enviar email
    $userName = trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username'];
    $emailResult = sendPasswordResetEmail($email, $userName, $token);
    
    if (!$emailResult['success']) {
        throw new Exception('Error al enviar el email: ' . $emailResult['message']);
    }
    
    // Registrar actividad
    logActivity($user['id'], 'password_reset_requested', 'users', $user['id'], 
                'Usuario solicitó recuperación de contraseña');
    
    echo json_encode([
        'success' => true,
        'message' => 'Hemos enviado un email con instrucciones para recuperar tu contraseña. Por favor revisa tu bandeja de entrada.'
    ]);
    
} catch (Exception $e) {
    error_log("Error en request_password_reset: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}