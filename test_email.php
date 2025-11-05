<?php
// test_email.php
// Script de prueba para verificar que el envío de emails funciona correctamente

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Test de Email - DMS2</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        h1 { color: #8B4513; }
        .config { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .step { background: #e7f3ff; border-left: 4px solid #2196f3; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <h1>🧪 Test de Configuración de Email</h1>
    <p>Este script verifica que la configuración de email esté correcta.</p>";

echo "<h2>📋 Paso 1: Verificar Estructura del Proyecto</h2>";

$projectRoot = __DIR__;
echo "<div class='info'><strong>Carpeta del proyecto:</strong> <code>$projectRoot</code></div>";

// Verificar vendor/autoload.php
$autoloadPath = $projectRoot . '/vendor/autoload.php';
echo "<div class='step'>";
echo "<strong>Verificando:</strong> <code>vendor/autoload.php</code><br>";

if (file_exists($autoloadPath)) {
    echo "<span style='color: green;'>✅ Archivo encontrado en: <code>$autoloadPath</code></span>";
    require_once $autoloadPath;
} else {
    echo "<span style='color: red;'>❌ NO encontrado en: <code>$autoloadPath</code></span><br><br>";
    echo "<strong>SOLUCIÓN:</strong><br>";
    echo "1. Abrir CMD en la carpeta del proyecto:<br>";
    echo "<code>cd " . $projectRoot . "</code><br><br>";
    echo "2. Ejecutar:<br>";
    echo "<code>composer require phpmailer/phpmailer</code><br><br>";
    echo "3. Recargar esta página";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// Verificar si PHPMailer está cargado
echo "<h2>📦 Paso 2: Verificar PHPMailer</h2>";
echo "<div class='step'>";

if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<span style='color: green;'>✅ PHPMailer está instalado correctamente</span><br>";
    
    // Mostrar versión
    $reflection = new ReflectionClass('PHPMailer\PHPMailer\PHPMailer');
    $phpmailerPath = dirname($reflection->getFileName());
    echo "<strong>Ruta de PHPMailer:</strong> <code>$phpmailerPath</code>";
} else {
    echo "<span style='color: red;'>❌ PHPMailer NO está cargado</span><br>";
    echo "A pesar de que <code>vendor/autoload.php</code> existe, PHPMailer no se cargó correctamente.<br><br>";
    echo "<strong>SOLUCIÓN:</strong> Reinstalar PHPMailer:<br>";
    echo "<code>composer require phpmailer/phpmailer --ignore-platform-reqs</code>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// Verificar config/email.php
echo "<h2>⚙️ Paso 3: Verificar Configuración</h2>";
echo "<div class='step'>";

$configPath = $projectRoot . '/config/email.php';
echo "<strong>Verificando:</strong> <code>config/email.php</code><br>";

if (file_exists($configPath)) {
    echo "<span style='color: green;'>✅ Archivo encontrado</span><br>";
    require_once $configPath;
    
    // Verificar constantes
    echo "<br><strong>Configuración actual:</strong><br>";
    echo "<ul>";
    echo "<li><strong>SMTP Host:</strong> " . (defined('SMTP_HOST') ? SMTP_HOST : '<span style="color:red">NO DEFINIDO</span>') . "</li>";
    echo "<li><strong>SMTP Port:</strong> " . (defined('SMTP_PORT') ? SMTP_PORT : '<span style="color:red">NO DEFINIDO</span>') . "</li>";
    echo "<li><strong>SMTP Username:</strong> " . (defined('SMTP_USERNAME') ? (SMTP_USERNAME ?: '<em>vacío (MailHog)</em>') : '<span style="color:red">NO DEFINIDO</span>') . "</li>";
    echo "<li><strong>From Email:</strong> " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '<span style="color:red">NO DEFINIDO</span>') . "</li>";
    echo "</ul>";
    
} else {
    echo "<span style='color: red;'>❌ NO encontrado en: <code>$configPath</code></span><br><br>";
    echo "<strong>SOLUCIÓN:</strong> Crear el archivo <code>config/email.php</code> con la configuración correcta.";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// Verificar función sendEmail
echo "<h2>🔧 Paso 4: Verificar Función sendEmail()</h2>";
echo "<div class='step'>";

if (function_exists('sendEmail')) {
    echo "<span style='color: green;'>✅ Función sendEmail() está disponible</span>";
} else {
    echo "<span style='color: red;'>❌ Función sendEmail() NO encontrada</span><br>";
    echo "Verifica que <code>config/email.php</code> tenga la función definida.";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

echo "<div class='success'>";
echo "<h3>✅ Todas las verificaciones pasaron</h3>";
echo "<p>El sistema está listo para enviar emails.</p>";
echo "</div>";

// Formulario para enviar email de prueba
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testEmail = $_POST['test_email'] ?? '';
    
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo "<div class='error'><strong>❌ Error:</strong> Email inválido</div>";
    } else {
        echo "<div class='info'>📧 Enviando email de prueba a: <strong>$testEmail</strong>...</div>";
        
        $result = sendEmail(
            $testEmail,
            'Test DMS2 - Sistema de Recuperación de Contraseña',
            '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%); color: white; padding: 20px; text-align: center;">
                    <h1>✅ Test Exitoso</h1>
                </div>
                <div style="padding: 20px; background: #f4f4f4;">
                    <p>Si estás leyendo este email, significa que:</p>
                    <ul>
                        <li>✅ PHPMailer está instalado correctamente</li>
                        <li>✅ La configuración de email es correcta</li>
                        <li>✅ El sistema de email funciona perfectamente</li>
                    </ul>
                    <p><strong>El sistema de recuperación de contraseña está listo para usar.</strong></p>
                    <hr>
                    <p style="font-size: 12px; color: #666;">
                        DMS2 - Sistema de Gestión Documental<br>
                        Perdomo y Asociados
                    </p>
                </div>
            </div>
            ',
            'Test DMS2 - Si recibes esto, el sistema funciona correctamente'
        );
        
        if ($result['success']) {
            echo "<div class='success'>
                <h3>✅ Email enviado exitosamente!</h3>
                <p>" . $result['message'] . "</p>";
            
            // Instrucciones según la configuración
            if (SMTP_HOST === 'localhost' && SMTP_PORT == 1025) {
                echo "<p><strong>🔍 Ver el email en MailHog:</strong></p>";
                echo "<a href='http://localhost:8025' target='_blank' style='display: inline-block; background: #8B4513; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0;'>
                    Abrir MailHog (http://localhost:8025)
                </a>";
            } else {
                echo "<p><strong>Revisa tu bandeja de entrada (y SPAM) en:</strong> $testEmail</p>";
            }
            
            echo "</div>";
        } else {
            echo "<div class='error'>
                <h3>❌ Error al enviar email</h3>
                <p><strong>Error:</strong> " . htmlspecialchars($result['message']) . "</p>
                <br>
                <strong>Posibles soluciones:</strong>
                <ul>
                    <li>Si usas Gmail: Verificar credenciales y usar Contraseña de Aplicación</li>
                    <li>Si usas MailHog: Verificar que <code>mailhog.exe</code> esté ejecutándose</li>
                    <li>Verificar firewall no bloquee el puerto SMTP</li>
                    <li>Verificar extensión OpenSSL en PHP</li>
                </ul>
            </div>";
        }
    }
}

?>

<form method="POST" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
    <h3>📨 Enviar Email de Prueba</h3>
    <p>Ingresa tu email para recibir un mensaje de prueba:</p>
    <input type="email" name="test_email" placeholder="tu-email@ejemplo.com" required 
           style="padding: 10px; width: 100%; max-width: 400px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
    <br><br>
    <button type="submit" style="background: #8B4513; color: white; padding: 10px 30px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: bold;">
        Enviar Test
    </button>
</form>

<div style="margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 5px;">
    <h4>📚 Siguiente Paso:</h4>
    <ul>
        <li><strong>Si usas MailHog:</strong> Descargar y ejecutar <code>mailhog.exe</code> de <a href="https://github.com/mailhog/MailHog/releases" target="_blank">GitHub</a></li>
        <li><strong>Si usas Gmail:</strong> Configurar credenciales en <code>config/email.php</code></li>
        <li><strong>Ver instrucciones completas:</strong> <code>INSTRUCCIONES_RECUPERACION_PASSWORD.md</code></li>
    </ul>
</div>

</body>
</html>