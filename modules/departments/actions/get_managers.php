<?php
// modules/departments/actions/get_managers.php
// Acción para obtener lista de usuarios que pueden ser managers

// Evitar cualquier output antes del JSON
ob_start();

try {
    // Corregir rutas relativas usando dirname(__FILE__)
    $configPath = dirname(__FILE__) . '/../../../config/session.php';
    $databasePath = dirname(__FILE__) . '/../../../config/database.php';
    $functionsPath = dirname(__FILE__) . '/../../../includes/functions.php';
    
    // Verificar que los archivos existen antes de incluirlos
    if (!file_exists($configPath)) {
        throw new Exception("No se encontró config/session.php en la ruta: " . $configPath);
    }
    
    if (!file_exists($databasePath)) {
        throw new Exception("No se encontró config/database.php en la ruta: " . $databasePath);
    }
    
    if (!file_exists($functionsPath)) {
        throw new Exception("No se encontró includes/functions.php en la ruta: " . $functionsPath);
    }
    
    require_once $configPath;
    require_once $databasePath;
    require_once $functionsPath;

    // Limpiar cualquier output previo
    ob_clean();

    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    // Verificar permisos
    if (!SessionManager::isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }

    $currentUser = SessionManager::getCurrentUser();
    if ($currentUser['role'] !== 'admin') {
        header('Content-Type: application/json');   
        echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
        exit;
    }

    // Obtener usuarios that pueden ser managers (admin y manager)
    $query = "SELECT id, first_name, last_name, email, role 
              FROM users 
              WHERE status = 'active' 
              AND role IN ('admin', 'manager') 
              ORDER BY first_name ASC, last_name ASC";
    
    $managers = fetchAll($query);
    
    // Verificar que fetchAll devolvió un array válido
    if ($managers === false) {
        throw new Exception("Error en la consulta de managers");
    }
    
    // Asegurar que $managers es un array
    if (!is_array($managers)) {
        $managers = [];
    }
    
    // Formatear datos para la respuesta
    $formattedManagers = array_map(function($manager) {
        return [
            'id' => $manager['id'],
            'name' => trim(($manager['first_name'] ?? '') . ' ' . ($manager['last_name'] ?? '')),
            'email' => $manager['email'] ?? '',
            'role' => $manager['role'] ?? ''
        ];
    }, $managers);

    // Enviar respuesta JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'managers' => $formattedManagers,
        'total' => count($formattedManagers)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Limpiar output buffer en caso de error
    ob_clean();
    
    error_log("Error en get_managers.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// Terminar y limpiar el buffer
ob_end_flush();
?>