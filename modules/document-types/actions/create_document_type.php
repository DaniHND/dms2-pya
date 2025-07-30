<?php
// modules/document-types/actions/create_document_type.php
// Acción para crear nuevo tipo de documento - DMS2

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
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $icon = trim($_POST['icon'] ?? 'file-text');
    $color = trim($_POST['color'] ?? '#6b7280');

    // Validaciones
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

    // Verificar que no existe un tipo con el mismo nombre
    $existingType = fetchOne(
        "SELECT id FROM document_types WHERE name = :name",
        ['name' => $name]
    );

    if ($existingType) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Ya existe un tipo de documento con ese nombre']);
        exit;
    }

    // Crear el tipo de documento - adaptado a la estructura existente
    $database = new Database();
    $pdo = $database->getConnection();

    // Verificar qué columnas existen en la tabla
    $columns = $pdo->query("DESCRIBE document_types")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    // Construir query dinámicamente basado en columnas existentes
    $insertColumns = ['name', 'description', 'status', 'created_at'];
    $insertValues = [':name', ':description', ':status', 'NOW()'];
    $params = [
        'name' => $name,
        'description' => $description,
        'status' => $status
    ];

    // Agregar columnas opcionales si existen
    if (in_array('icon', $existingColumns)) {
        $insertColumns[] = 'icon';
        $insertValues[] = ':icon';
        $params['icon'] = $icon;
    }

    if (in_array('color', $existingColumns)) {
        $insertColumns[] = 'color';
        $insertValues[] = ':color';
        $params['color'] = $color;
    }

    if (in_array('updated_at', $existingColumns)) {
        $insertColumns[] = 'updated_at';
        $insertValues[] = 'NOW()';
    }

    $query = "INSERT INTO document_types (" . implode(', ', $insertColumns) . ") 
              VALUES (" . implode(', ', $insertValues) . ")";

    $stmt = $pdo->prepare($query);
    $success = $stmt->execute($params);

    if ($success) {
        $documentTypeId = $pdo->lastInsertId();
        
        // Registrar actividad
        logActivity(
            $currentUser['id'], 
            'create', 
            'document_types', 
            $documentTypeId, 
            "Creó tipo de documento: $name"
        );

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Tipo de documento creado correctamente',
            'document_type_id' => $documentTypeId
        ]);
    } else {
        throw new Exception('Error al insertar el tipo de documento en la base de datos');
    }

} catch (Exception $e) {
    // Limpiar output buffer en caso de error
    ob_clean();
    
    error_log("Error en create_document_type.php: " . $e->getMessage());
    
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