<?php
/*
 * api/get_companies.php
 * Obtener lista de empresas para restricciones de acceso
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
            id, 
            name, 
            description,
            status
        FROM companies 
        WHERE status = 'active'
        ORDER BY name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'companies' => $companies,
        'total' => count($companies)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

--- SEPARADOR DE ARCHIVO ---

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

--- SEPARADOR DE ARCHIVO ---

<?php
/*
 * api/get_document_types.php
 * Obtener lista de tipos de documentos para restricciones de acceso
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
            id, 
            name, 
            description,
            status,
            file_extensions,
            max_file_size
        FROM document_types 
        WHERE status = 'active'
        ORDER BY name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $documentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'document_types' => $documentTypes,
        'total' => count($documentTypes)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>