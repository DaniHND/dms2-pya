<?php
// modules/users/actions/get_companies.php
// Obtener lista de empresas activas

header('Content-Type: application/json');
require_once '../../../config/session.php';
require_once '../../../config/database.php';

try {
    SessionManager::requireLogin();
    SessionManager::requireRole('admin');
    
    $companies = fetchAll("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name");
    
    echo json_encode([
        'success' => true,
        'companies' => $companies ?: []
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'companies' => []
    ]);
}
?>