<?php
// modules/document-types/actions/toggle_document_type_status.php
// Acción para cambiar el estado de un tipo de documento - DMS2

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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    // Validar datos
    $documentTypeId = intval($_POST['document_type_id'] ?? 0);
    $currentStatus = $_POST['current_status'] ?? '';

    if ($documentTypeId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de tipo de documento inválido']);
        exit;
    }

    if (!in_array($currentStatus, ['active', 'inactive'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Estado actual inválido']);
        exit;
    }

    // Verificar que el tipo de documento existe
    $documentType = fetchOne(
        "SELECT id, name, status FROM document_types WHERE id = :id",
        ['id' => $documentTypeId]
    );

    if (!$documentType) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Tipo de documento no encontrado']);
        exit;
    }

    // Determinar nuevo estado
    $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

    // Validación especial: No permitir desactivar si tiene documentos activos
    if ($newStatus === 'inactive') {
        $activeDocuments = fetchOne(
            "SELECT COUNT(*) as count FROM documents WHERE document_type_id = :id AND status = 'active'",
            ['id' => $documentTypeId]
        );
        
        if ($activeDocuments['count'] > 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'No se puede desactivar el tipo de documento porque tiene ' . 
                           $activeDocuments['count'] . ' documento(s) activo(s). ' .
                           'Primero debe cambiar el tipo de estos documentos.',
                'documents_count' => $activeDocuments['count']
            ]);
            exit;
        }
    }

    // Actualizar estado - adaptado a la estructura existente
    $database = new Database();
    $pdo = $database->getConnection();

    // Verificar si existe la columna updated_at
    $columns = $pdo->query("DESCRIBE document_types")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    $updateClause = "status = :status";
    if (in_array('updated_at', $existingColumns)) {
        $updateClause .= ", updated_at = NOW()";
    }

    $query = "UPDATE document_types SET $updateClause WHERE id = :id";
    $params = [
        'id' => $documentTypeId,
        'status' => $newStatus
    ];

    $stmt = $pdo->prepare($query);
    $success = $stmt->execute($params);

    if ($success) {
        // Registrar actividad
        $actionText = $newStatus === 'active' ? 'Activó' : 'Desactivó';
        logActivity(
            $currentUser['id'], 
            'toggle_status', 
            'document_types', 
            $documentTypeId, 
            "$actionText tipo de documento: {$documentType['name']}"
        );

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Estado del tipo de documento actualizado correctamente',
            'new_status' => $newStatus
        ]);
    } else {
        throw new Exception('Error al actualizar el estado del tipo de documento');
    }

} catch (Exception $e) {
    // Limpiar output buffer en caso de error
    ob_clean();
    
    error_log("Error en toggle_document_type_status.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'debug' => $e->getMessage()
    ]);
}

// Terminar y limpiar el buffer
ob_end_flush();
?>