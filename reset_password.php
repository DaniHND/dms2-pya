<?php
// reset_password.php
require_once __DIR__ . '/config/database.php';

$token = $_GET['token'] ?? '';
$error = '';
$tokenValid = false;
$user = null;

if (empty($token)) {
    $error = 'Token inválido o expirado';
} else {
    $query = "SELECT prt.*, u.username, u.first_name, u.last_name 
              FROM password_reset_tokens prt
              INNER JOIN users u ON prt.user_id = u.id
              WHERE prt.token = :token 
              AND prt.used = 0 
              AND prt.expires_at > NOW()
              AND u.status = 'active'";
    
    $tokenData = fetchOne($query, ['token' => $token]);
    
    if ($tokenData) {
        $tokenValid = true;
        $user = $tokenData;
    } else {
        $error = 'El enlace de recuperación es inválido o ha expirado.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - DMS2</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #2e1e12 0%, #5a3a24 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo img {
            max-width: 250px;
            height: auto;
        }
        
        h1 {
            color: #2e1e12;
            font-size: 24px;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .subtitle {
            color: #666;
            font-size: 14px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }
        
        .alert-info {
            background: #f5f1ed;
            border: 1px solid #2e1e12;
            color: #2e1e12;
        }
        
        .alert-success {
            background: #e8f5e9;
            border: 1px solid #4caf50;
            color: #2e7d32;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="password"]:focus {
            outline: none;
            border-color: #2e1e12;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #2e1e12;
            color: white;
        }
        
        .btn-primary:hover {
            background: #4a3020;
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .text-center {
            text-align: center;
            margin-top: 20px;
        }
        
        .text-center a {
            color: #2e1e12;
            text-decoration: none;
            font-weight: 500;
        }
        
        .text-center a:hover {
            text-decoration: underline;
        }
        
        small {
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="https://perdomoyasociados.com/wp-content/uploads/2023/09/logo_perdomo_2023_dorado-768x150.png" alt="Perdomo y Asociados">
        </div>
        
        <h1>Restablecer Contraseña</h1>
        <p class="subtitle">DMS2 - Sistema de Gestión Documental</p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <a href="login.php" class="btn btn-secondary">Volver al Login</a>
            
        <?php elseif ($tokenValid): ?>
            <div class="alert alert-info">
                <strong>Usuario:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
            </div>

            <form id="resetForm" method="POST" action="javascript:void(0);">
                <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="password">Nueva Contraseña</label>
                    <input type="password" id="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                    <small>Mínimo 6 caracteres</small>
                </div>

                <div class="form-group">
                    <label for="confirm">Confirmar Contraseña</label>
                    <input type="password" id="confirm" required minlength="6" placeholder="Confirme su contraseña">
                </div>

                <div id="message"></div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        Restablecer Contraseña
                    </button>
                </div>
            </form>

            <div class="text-center">
                <a href="login.php">Volver al Login</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        (function() {
            'use strict';
            
            document.addEventListener('DOMContentLoaded', function() {
                var form = document.getElementById('resetForm');
                if (!form) return;
                
                var submitBtn = document.getElementById('submitBtn');
                var messageDiv = document.getElementById('message');
                
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var token = document.getElementById('token').value.trim();
                    var password = document.getElementById('password').value.trim();
                    var confirm = document.getElementById('confirm').value.trim();
                    
                    messageDiv.innerHTML = '';
                    
                    if (password !== confirm) {
                        messageDiv.innerHTML = '<div class="alert alert-error">Las contraseñas no coinciden</div>';
                        return false;
                    }
                    
                    if (password.length < 6) {
                        messageDiv.innerHTML = '<div class="alert alert-error">La contraseña debe tener al menos 6 caracteres</div>';
                        return false;
                    }
                    
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Procesando...';
                    
                    fetch('api/reset_password.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            token: token,
                            new_password: password
                        })
                    })
                    .then(function(response) {
                        return response.text();
                    })
                    .then(function(text) {
                        var result;
                        try {
                            result = JSON.parse(text);
                        } catch (e) {
                            throw new Error('Respuesta inválida del servidor');
                        }
                        
                        if (result.success) {
                            messageDiv.innerHTML = '<div class="alert alert-success">' + result.message + '</div>';
                            setTimeout(function() {
                                window.location.href = 'login.php';
                            }, 2000);
                        } else {
                            messageDiv.innerHTML = '<div class="alert alert-error">' + result.message + '</div>';
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Restablecer Contraseña';
                        }
                    })
                    .catch(function(error) {
                        messageDiv.innerHTML = '<div class="alert alert-error">Error: ' + error.message + '</div>';
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Restablecer Contraseña';
                    });
                    
                    return false;
                });
            });
        })();
    </script>
</body>
</html>