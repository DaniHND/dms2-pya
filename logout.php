<?php
require_once 'config/session.php';

// Cerrar sesión
SessionManager::logout();

// Redirigir al login con mensaje
SessionManager::setFlashMessage('success', 'Sesión cerrada exitosamente');
header('Location: login.php');
exit();
?>