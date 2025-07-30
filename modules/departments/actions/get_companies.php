<?php
// modules/departments/actions/get_companies.php
// Acción para obtener lista de empresas activas para selects

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

    // Obtener empresas activas - usando consulta más simple y robusta
    try {
        // Primero verificar que la tabla companies existe
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Consulta más simple para evitar problemas
        $query = "SELECT id, name FROM companies WHERE status = :status ORDER BY name ASC";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':status', 'active', PDO::PARAM_STR);
        $stmt->execute();
        
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si no hay resultados, devolver array vacío
        if (!$companies) {
            $companies = [];
        }
        
    } catch (Exception $dbError) {
        // Si hay error en la consulta, intentar consulta más básica
        try {
            $query = "SELECT id, name FROM companies ORDER BY name ASC LIMIT 10";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!$companies) {
                $companies = [];
            }
        } catch (Exception $basicError) {
            // Si incluso la consulta básica falla, devolver array vacío
            $companies = [];
        }
    }
    
    // Formatear datos para la respuesta
    $formattedCompanies = [];
    if (is_array($companies) && count($companies) > 0) {
        foreach ($companies as $company) {
            $formattedCompanies[] = [
                'id' => $company['id'] ?? '',
                'name' => $company['name'] ?? 'Sin nombre',
                'description' => $company['description'] ?? ''
            ];
        }
    }

    // Enviar respuesta JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'companies' => $formattedCompanies,
        'total' => count($formattedCompanies)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Limpiar output buffer en caso de error
    ob_clean();
    
    error_log("Error en get_companies.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'debug' => $e->getMessage(),
        'companies' => [],
        'total' => 0
    ], JSON_UNESCAPED_UNICODE);
}

// Terminar y limpiar el buffer
ob_end_flush();
?>