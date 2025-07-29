<?php
// modules/users/actions/debug_create.php
// Archivo de debug para diagnosticar el problema

// Desactivar todos los warnings y notices
error_reporting(E_ERROR);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    echo json_encode([
        'debug' => 'Test inicial',
        'method' => $_SERVER['REQUEST_METHOD'],
        'session_status' => session_status(),
        'post_data' => $_POST,
        'files_exist' => [
            'database' => file_exists('../../../config/database.php'),
            'functions' => file_exists('../../../includes/functions.php')
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>