<?php
// modules/document-types/actions/update_document_type.php
// Acción para actualizar tipo de documento - DMS2

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

    // Validar y sanitizar datos
    $documentTypeId = intval($_POST['document_type_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $icon = trim($_POST['icon'] ?? 'file-text');
    $color = trim($_POST['color'] ?? '#6b7280');

    // Validaciones
    if ($documentTypeId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de tipo de documento inválido']);
        exit;
    }

    if (empty($name)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'El nombre del tipo de documento es requerido']);
        exit;
    }

    if (strlen($name) > 100) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'El nombre no puede exceder 100 caracteres']);
        exit;
    }

    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }

    // Verificar que el tipo de documento existe
    $existingType = fetchOne(
        "SELECT id, name FROM document_types WHERE id = :id",
        ['id' => $documentTypeId]
    );

    if (!$existingType) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Tipo de documento no encontrado']);
        exit;
    }

    // Verificar que no existe otro tipo con el mismo nombre (excluyendo el actual)
    $duplicateType = fetchOne(
        "SELECT id FROM document_types WHERE name = :name AND id != :id",
        ['name' => $name, 'id' => $documentTypeId]
    );

    if ($duplicateType) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Ya existe otro tipo de documento con ese nombre']);
        exit;
    }

    // Actualizar el tipo de documento - adaptado a la estructura existente
    $database = new Database();
    $pdo = $database->getConnection();

    // Verificar qué columnas existen en la tabla
    $columns = $pdo->query("DESCRIBE document_types")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');

    // Construir UPDATE dinámicamente basado en columnas existentes
    $updateColumns = ['name = :name', 'description = :description', 'status = :status'];
    $params = [
        'id' => $documentTypeId,
        'name' => $name,
        'description' => $description,
        'status' => $status
    ];

    // Agregar columnas opcionales si existen
    if (in_array('icon', $existingColumns)) {
        $updateColumns[] = 'icon = :icon';
        $params['icon'] = $icon;
    }

    if (in_array('color', $existingColumns)) {
        $updateColumns[] = 'color = :color';
        $params['color'] = $color;
    }

    if (in_array('updated_at', $existingColumns)) {
        $updateColumns[] = 'updated_at = NOW()';
    }

    $query = "UPDATE document_types 
              SET " . implode(', ', $updateColumns) . " 
              WHERE id = :id";

    $stmt = $pdo->prepare($query);
    $success = $stmt->execute($params);

    if ($success) {
        // Registrar actividad
        logActivity(
            $currentUser['id'], 
            'update', 
            'document_types', 
            $documentTypeId, 
            "Actualizó tipo de documento: $name"
        );

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Tipo de documento actualizado correctamente'
        ]);
    } else {
        throw new Exception('Error al actualizar el tipo de documento en la base de datos');
    }

} catch (Exception $e) {
    // Limpiar output buffer en caso de error
    ob_clean();
    
    error_log("Error en update_document_type.php: " . $e->getMessage());
    
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