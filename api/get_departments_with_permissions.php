<?php

require_once '../config/session.php';
require_once '../includes/permission_functions.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Leer datos JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $companyId = isset($data['company_id']) ? (int)$data['company_id'] : null;
    
    if (!$companyId) {
        echo json_encode([
            'success' => false, 
            'message' => 'ID de empresa requerido'
        ]);
        exit;
    }
    
    // Obtener departamentos accesibles para la empresa específica
    $departments = getAccessibleDepartments($companyId);
    
    // Formatear respuesta
    $formattedDepartments = [];
    foreach ($departments as $dept) {
        $formattedDepartments[] = [
            'id' => (int)$dept['id'],
            'name' => $dept['name'],
            'company_id' => (int)$dept['company_id']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'departments' => $formattedDepartments,
        'count' => count($formattedDepartments)
    ]);
    
} catch (Exception $e) {
    error_log('Error en get_departments_with_permissions: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>