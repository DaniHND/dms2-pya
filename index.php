<?php
// index.php
// Página principal que redirige según el estado de autenticación

require_once 'config/session.php';

// Verificar si el usuario ya está logueado
if (SessionManager::isLoggedIn()) {
    // Redirigir al dashboard
    header('Location: dashboard.php');
} else {
    // Redirigir al login
    header('Location: login.php');
}

exit();
?>