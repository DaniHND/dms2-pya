<?php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/group_permissions.php';

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// ===================================================================
// FUNCIÓN getUserPermissions - COPIA DE INBOX.PHP
// ===================================================================
function getUserPermissions($userId)
{
    if (!$userId) {
        return ['permissions' => ['download' => false]];
    }

    try {
        $database = new Database();
        $pdo = $database->getConnection();

        // Verificar si es administrador
        $query = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && ($user['role'] === 'admin' || $user['role'] === 'super_admin')) {
            // ADMINISTRADORES: ACCESO TOTAL
            return ['permissions' => ['download' => true]];
        }

        // USUARIOS NORMALES: SOLO CON GRUPOS Y PERMISO ACTIVADO
        $groupPermissions = getUserGroupPermissions($userId);

        if (!$groupPermissions['has_groups']) {
            return ['permissions' => ['download' => false]];
        }

        $permissions = $groupPermissions['permissions'];
        return ['permissions' => ['download' => $permissions['download_files'] ?? false]];

    } catch (Exception $e) {
        error_log("Error getting download permissions: " . $e->getMessage());
        return ['permissions' => ['download' => false]];
    }
}

// ===================================================================
// VERIFICACIONES DE SEGURIDAD
// ===================================================================

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener ID del documento
$documentId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;

if (!$documentId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de documento requerido']);
    exit;
}

// Verificar permisos del usuario
$userPermissions = getUserPermissions($currentUser['id']);
$canDownload = $userPermissions['permissions']['download'];

// DEBUG: Log para administradores
if ($currentUser['role'] === 'admin') {
    error_log("DEBUG DOWNLOAD - Admin {$currentUser['id']} intentando descargar documento {$documentId} - Permiso: " . ($canDownload ? 'SI' : 'NO'));
}

if (!$canDownload) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Sin permisos para descargar documentos']);
    exit;
}

// ===================================================================
// PROCESAMIENTO DE DESCARGA
// ===================================================================

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Obtener información del documento
    $query = "
        SELECT d.*, c.name as company_name, dept.name as department_name 
        FROM documents d
        LEFT JOIN companies c ON d.company_id = c.id
        LEFT JOIN departments dept ON d.department_id = dept.id
        WHERE d.id = ? AND d.status = 'active'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Documento no encontrado']);
        exit;
    }

    // Construir ruta del archivo
    $filePath = '../../' . $document['file_path'];
    
    // DEBUG: Log de ruta de archivo
    error_log("DEBUG DOWNLOAD - Buscando archivo en: " . $filePath);
    
    if (!file_exists($filePath)) {
        error_log("ERROR DOWNLOAD - Archivo no encontrado en: " . $filePath);
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Archivo físico no encontrado']);
        exit;
    }

    // Log de actividad exitosa
    error_log("SUCCESS DOWNLOAD - Usuario {$currentUser['id']} ({$currentUser['role']}) descargó documento {$documentId}: {$document['name']}");

    // ===================================================================
    // ENVIO DEL ARCHIVO
    // ===================================================================

    // Limpiar cualquier salida previa
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Headers para descarga
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Enviar archivo
    readfile($filePath);
    exit;

} catch (Exception $e) {
    error_log("ERROR DOWNLOAD - Exception en descarga de documento $documentId: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error interno del servidor']);
    exit;
}
?>