<?php

require_once '../config/session.php';
require_once '../config/database.php';

// Verificar que el usuario esté autenticado
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    $company = isset($_POST['company']) && $_POST['company'] !== '' ? (int)$_POST['company'] : null;
    $department = isset($_POST['department']) && $_POST['department'] !== '' ? (int)$_POST['department'] : null;
    
    // Validar que la empresa existe si se especificó
    if ($company) {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE id = ? AND status = 'active'");
        $stmt->execute([$company]);
        
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Empresa no válida']);
            exit;
        }
    }
    
    // Validar que el departamento existe y pertenece a la empresa si se especificó
    if ($department) {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $query = "SELECT id FROM departments WHERE id = ? AND status = 'active'";
        $params = [$department];
        
        if ($company) {
            $query .= " AND company_id = ?";
            $params[] = $company;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Departamento no válido']);
            exit;
        }
    }
    
    // Actualizar la sesión
    $_SESSION['context_company'] = $company;
    $_SESSION['context_department'] = $department;
    
    // Log de la actividad
    $currentUser = SessionManager::getCurrentUser();
    $description = "Usuario actualizó contexto: Empresa=" . ($company ?: 'Todas') . ", Departamento=" . ($department ?: 'Todos');
    
    // Insertar log si tenemos la función disponible
    if (function_exists('logActivity')) {
        logActivity($currentUser['id'], 'update_context', 'session', null, $description);
    }
    
    echo json_encode([
        'success' => true,
        'company' => $company,
        'department' => $department,
        'message' => 'Contexto actualizado correctamente'
    ]);
    
} catch (Exception $e) {
    error_log('Error actualizando contexto: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>