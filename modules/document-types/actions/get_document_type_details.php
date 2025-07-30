<?php
// modules/document-types/actions/get_document_type_details.php
// Acción para obtener detalles de un tipo de documento específico - DMS2

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

    // Validar parámetros
    $documentTypeId = intval($_GET['id'] ?? 0);
    if ($documentTypeId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de tipo de documento inválido']);
        exit;
    }

    // Obtener detalles del tipo de documento - adaptado a la estructura existente
    $database = new Database();
    $pdo = $database->getConnection();

    // Verificar qué columnas existen
    $columns = $pdo->query("DESCRIBE document_types")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');

    // Construir SELECT dinámicamente
    $selectColumns = ['id', 'name', 'description', 'status', 'created_at'];
    
    if (in_array('updated_at', $existingColumns)) {
        $selectColumns[] = 'updated_at';
    }
    if (in_array('icon', $existingColumns)) {
        $selectColumns[] = 'icon';
    }
    if (in_array('color', $existingColumns)) {
        $selectColumns[] = 'color';
    }
    if (in_array('extensions', $existingColumns)) {
        $selectColumns[] = 'extensions';
    }
    if (in_array('max_size', $existingColumns)) {
        $selectColumns[] = 'max_size';
    }

    $documentTypeQuery = "SELECT " . implode(', ', $selectColumns) . "
                          FROM document_types 
                          WHERE id = :id";

    $documentType = fetchOne($documentTypeQuery, ['id' => $documentTypeId]);

    if (!$documentType) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Tipo de documento no encontrado']);
        exit;
    }

    // Obtener estadísticas de documentos
    $statsQuery = "SELECT 
                     COUNT(*) as total_documents,
                     SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_documents,
                     SUM(CASE WHEN status = 'deleted' THEN 1 ELSE 0 END) as deleted_documents
                   FROM documents 
                   WHERE document_type_id = :document_type_id";

    $stats = fetchOne($statsQuery, ['document_type_id' => $documentTypeId]);
    
    // Asegurar que las estadísticas sean números
    $totalDocuments = intval($stats['total_documents'] ?? 0);
    $activeDocuments = intval($stats['active_documents'] ?? 0);
    $deletedDocuments = intval($stats['deleted_documents'] ?? 0);

    // Preparar respuesta
    $response = [
        'success' => true,
        'document_type' => [
            'id' => $documentType['id'],
            'name' => $documentType['name'] ?? '',
            'description' => $documentType['description'] ?? '',
            'status' => $documentType['status'] ?? 'active',
            'created_at' => $documentType['created_at'] ?? '',
            'formatted_created_date' => !empty($documentType['created_at']) ? date('d/m/Y H:i', strtotime($documentType['created_at'])) : ''
        ],
        'statistics' => [
            'total_documents' => $totalDocuments,
            'active_documents' => $activeDocuments,
            'deleted_documents' => $deletedDocuments
        ]
    ];

    // Agregar campos adicionales si existen
    if (isset($documentType['updated_at'])) {
        $response['document_type']['updated_at'] = $documentType['updated_at'];
        $response['document_type']['formatted_updated_date'] = !empty($documentType['updated_at']) ? date('d/m/Y H:i', strtotime($documentType['updated_at'])) : '';
    }

    if (isset($documentType['icon'])) {
        $response['document_type']['icon'] = $documentType['icon'];
    }

    if (isset($documentType['color'])) {
        $response['document_type']['color'] = $documentType['color'];
    }

    if (isset($documentType['extensions'])) {
        $response['document_type']['extensions'] = $documentType['extensions'];
    }

    if (isset($documentType['max_size'])) {
        $response['document_type']['max_size'] = $documentType['max_size'];
        $response['document_type']['formatted_max_size'] = formatFileSize($documentType['max_size']);
    }

    // Enviar respuesta JSON
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Limpiar output buffer en caso de error
    ob_clean();
    
    error_log("Error en get_document_type_details.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// Función auxiliar para formatear tamaño de archivo
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Terminar y limpiar el buffer
ob_end_flush();
?>