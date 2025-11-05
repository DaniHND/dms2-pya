<?php
// login.php
// Página de autenticación para DMS2

require_once 'config/session.php';
require_once 'config/database.php';

// Si ya está logueado, redirigir al dashboard
if (SessionManager::isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Por favor ingrese usuario y contraseña';
    } else {
        // Buscar usuario en la base de datos
        $query = "SELECT u.*, c.name as company_name, d.name as department_name 
                  FROM users u 
                  LEFT JOIN companies c ON u.company_id = c.id 
                  LEFT JOIN departments d ON u.department_id = d.id 
                  WHERE u.username = :username AND u.status = 'active'";

        $user = fetchOne($query, ['username' => $username]);

        if ($user && password_verify($password, $user['password'])) {
            // Login exitoso
            SessionManager::login($user);
            $success = 'Inicio de sesión exitoso. Redirigiendo...';

            // Redirigir después de 2 segundos
            header('refresh:2;url=dashboard.php');
        } else {
            $error = 'Usuario o contraseña incorrectos';

            // Log de intento fallido
            logActivity(null, 'failed_login', 'users', null, 'Intento fallido de login para usuario: ' . $username);
        }
    }
}

// Obtener mensaje flash si existe
$flash = SessionManager::getFlashMessage();
if ($flash) {
    if ($flash['type'] == 'error') {
        $error = $flash['message'];
    } else {
        $success = $flash['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS2 - Iniciar Sesión</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.css">
</head>

<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <img src="https://perdomoyasociados.com/wp-content/uploads/2023/09/logo_perdomo_2023_dorado-768x150.png" alt="Perdomo y Asociados" class="logo-image">
                </div>
                <h1>DMS2</h1>
                <p>Document Management System</p>

            </div>

            <div class="login-form">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i data-feather="alert-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i data-feather="check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="loginForm">
                    <div class="form-group">
                        <label for="username">Usuario</label>
                        <div class="input-group">
                            <i data-feather="user"></i>
                            <input type="text" id="username" name="username"
                                placeholder="Ingrese su usuario" required
                                value="<?php echo htmlspecialchars($username ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <div class="input-group">
                            <i data-feather="lock"></i>
                            <input type="password" id="password" name="password"
                                placeholder="Ingrese su contraseña" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i data-feather="eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="log-in"></i>
                            Iniciar Sesión
                        </button>
                    </div>
                </form>

                <div class="login-footer">
                    <a href="#" onclick="showRecoveryModal()">¿Olvidó su contraseña?</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para recuperación de contraseña -->
    <div id="recoveryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Recuperar Contraseña</h3>
                <button class="close" onclick="hideRecoveryModal()">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Ingrese su email para recibir instrucciones de recuperación:</p>
                <form id="recoveryForm">
                    <div class="form-group">
                        <label for="recovery_email">Email</label>
                        <input type="email" id="recovery_email" name="recovery_email"
                            placeholder="correo@ejemplo.com" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="send"></i>
                            Enviar Instrucciones
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.js"></script>
    <script src="assets/js/login.js"></script>
    <script>
        // Inicializar iconos
        feather.replace();

        // Auto-completar para testing
        document.addEventListener('DOMContentLoaded', function() {
            // Permitir llenar automáticamente con credenciales de prueba
            const demoUsers = document.querySelectorAll('.demo-user');
            demoUsers.forEach(user => {
                user.style.cursor = 'pointer';
                user.addEventListener('click', function() {
                    const text = this.textContent;
                    if (text.includes('admin')) {
                        document.getElementById('username').value = 'admin';
                        document.getElementById('password').value = 'password';
                    } else if (text.includes('jperez')) {
                        document.getElementById('username').value = 'jperez';
                        document.getElementById('password').value = 'password';
                    }
                });
            });
        });
    </script>
</body>

</html>