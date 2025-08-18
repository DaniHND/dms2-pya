<?php
// modules/documents/api/test_api.php
// Archivo de prueba simple para verificar que la ruta funciona

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'API funcionando correctamente',
    'timestamp' => date('Y-m-d H:i:s'),
    'path' => __FILE__,
    'method' => $_SERVER['REQUEST_METHOD']
]);
?>