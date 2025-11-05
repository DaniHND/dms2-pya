<?php
// config/email.php
// Configuración de email para el sistema

// Cargar autoload de Composer PRIMERO
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ===================================
// CONFIGURACIÓN SMTP
// ===================================

// Opción 1: Gmail (Producción)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'danihon89@gmail.com');
define('SMTP_PASSWORD', 'rbfaojzrppgrdisz'); // ⚠️ Cambiar por tu contraseña de aplicación
define('SMTP_FROM_EMAIL', 'danihon89@gmail.com');
define('SMTP_FROM_NAME', 'DMS2 - Perdomo y Asociados');

// Opción 2: MailHog (Desarrollo - comentar si usas Gmail)
/*
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 1025);
define('SMTP_SECURE', '');
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'dms2@localhost');
define('SMTP_FROM_NAME', 'DMS2 - Sistema Local');
*/

// ===================================
// FUNCIÓN PARA ENVIAR EMAIL
// ===================================

/**
 * Envía un email usando PHPMailer
 */
function sendEmail($to, $subject, $body, $isHTML = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = !empty(SMTP_USERNAME);
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Remitente
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Destinatario
        $mail->addAddress($to);
        
        // Contenido
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        if ($isHTML) {
            $mail->AltBody = strip_tags($body);
        }
        
        // Enviar
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Email enviado correctamente'
        ];
        
    } catch (Exception $e) {
        error_log("Error al enviar email: {$mail->ErrorInfo}");
        return [
            'success' => false,
            'message' => "Error al enviar email: {$mail->ErrorInfo}"
        ];
    }
}

/**
 * Envía email de recuperación de contraseña
 */
function sendPasswordResetEmail($email, $userName, $token) {
    $resetUrl = "http://localhost/dms2-pya/reset_password.php?token=" . urlencode($token);
    
    $subject = "Recuperación de Contraseña - DMS2";
    
    $body = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                background: #ffffff;
                border-radius: 10px;
                padding: 30px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                padding-bottom: 20px;
                border-bottom: 2px solid #2e1e12;
                margin-bottom: 30px;
            }
            .header img {
                max-width: 200px;
                height: auto;
            }
            .header h1 {
                color: #2e1e12;
                margin: 15px 0 5px 0;
                font-size: 24px;
            }
            .content {
                margin: 20px 0;
            }
            .content p {
                margin: 15px 0;
            }
            .button-container {
                text-align: center;
                margin: 30px 0;
            }
            .button {
                display: inline-block;
                padding: 15px 40px;
                background: #2e1e12;
                color: #ffffff !important;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
                font-size: 16px;
            }
            .button:hover {
                background: #4a3020;
            }
            .info-box {
                background: #f8f9fa;
                border-left: 4px solid #2e1e12;
                padding: 15px;
                margin: 20px 0;
                border-radius: 5px;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e0e0e0;
                font-size: 12px;
                color: #666;
                text-align: center;
            }
            .warning {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 20px 0;
                border-radius: 5px;
            }
            .link-box {
                word-break: break-all;
                background: #f8f9fa;
                padding: 10px;
                border-radius: 5px;
                font-size: 12px;
            }
            .link-box a {
                color: #2e1e12;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='https://perdomoyasociados.com/wp-content/uploads/2023/09/logo_perdomo_2023_dorado-768x150.png' alt='Perdomo y Asociados'>
                <h1>Recuperación de Contraseña</h1>
                <p style='color: #666; margin: 5px 0;'>DMS2 - Sistema de Gestión Documental</p>
            </div>
            
            <div class='content'>
                <p>Hola <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                
                <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en el Sistema de Gestión Documental (DMS2).</p>
                
                <div class='button-container'>
                    <a href='" . $resetUrl . "' class='button'>Restablecer Contraseña</a>
                </div>
                
                <div class='info-box'>
                    <strong>Importante:</strong> Este enlace expirará en <strong>1 hora</strong> por seguridad.
                </div>
                
                <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                <p class='link-box'>
                    <a href='" . $resetUrl . "'>" . $resetUrl . "</a>
                </p>
                
                <div class='warning'>
                    <strong>¿No solicitaste este cambio?</strong><br>
                    Si no solicitaste restablecer tu contraseña, ignora este mensaje. Tu cuenta permanecerá segura.
                </div>
            </div>
            
            <div class='footer'>
                <p>Este es un email automático del Sistema DMS2</p>
                <p>Perdomo y Asociados &copy; " . date('Y') . "</p>
                <p style='margin-top: 10px;'>
                    <a href='https://perdomoyasociados.com' style='color: #2e1e12; text-decoration: none;'>
                        www.perdomoyasociados.com
                    </a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body, true);
}

/**
 * Envía email de confirmación después de cambiar contraseña
 */
function sendPasswordChangedEmail($email, $userName) {
    $subject = "Contraseña Actualizada - DMS2";
    
    $body = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                background: #ffffff;
                border-radius: 10px;
                padding: 30px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                padding-bottom: 20px;
                border-bottom: 2px solid #2e1e12;
                margin-bottom: 30px;
            }
            .header img {
                max-width: 200px;
                height: auto;
            }
            .success-box {
                background: #d4edda;
                border-left: 4px solid #28a745;
                padding: 15px;
                margin: 20px 0;
                border-radius: 5px;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e0e0e0;
                font-size: 12px;
                color: #666;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='https://perdomoyasociados.com/wp-content/uploads/2023/09/logo_perdomo_2023_dorado-768x150.png' alt='Perdomo y Asociados'>
                <h2 style='color: #2e1e12; margin: 15px 0;'>Contraseña Actualizada</h2>
            </div>
            
            <p>Hola <strong>" . htmlspecialchars($userName) . "</strong>,</p>
            
            <div class='success-box'>
                Tu contraseña ha sido actualizada exitosamente.
            </div>
            
            <p>Si no realizaste este cambio, contacta inmediatamente al administrador del sistema.</p>
            
            <div class='footer'>
                <p>Este es un email automático del Sistema DMS2</p>
                <p>Perdomo y Asociados &copy; " . date('Y') . "</p>
                <p style='margin-top: 10px;'>
                    <a href='https://perdomoyasociados.com' style='color: #2e1e12; text-decoration: none;'>
                        www.perdomoyasociados.com
                    </a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body, true);
}