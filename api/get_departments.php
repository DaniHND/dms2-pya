<?php
/*
 * api/get_departments.php
 * Obtener lista de departamentos para restricciones de acceso
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/session.php';

try {
    // Verificar autenticación
    if (!SessionManager::isLoggedIn()) {
        throw new Exception("Usuario no autenticado");
    }
    
    $currentUser = SessionManager::getCurrentUser();
    if ($currentUser['role'] !== 'admin') {
        throw new Exception("Permisos insuficientes");
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    $query = "
        SELECT 
            d.id, 
            d.name, 
            d.description,
            d.status,
            c.name as company_name
        FROM departments d
        LEFT JOIN companies c ON d.company_id = c.id
        WHERE d.status = 'active'
        ORDER BY c.name, d.name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'departments' => $departments,
        'total' => count($departments)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>