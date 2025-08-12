<?php
// logout.php
// Cerrar sesión del usuario - DMS2

require_once 'config/session.php';

// Cerrar sesión
SessionManager::logout();

// Redirigir al login con mensaje
SessionManager::setFlashMessage('success', 'Sesión cerrada exitosamente');
header('Location: login.php');
exit();
?>