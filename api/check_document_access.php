<?php
/**
 * api/check_document_access.php
 * API para verificar acceso a documentos específicos con permisos de grupos
 */

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
    
    $documentId = isset($data['document_id']) ? (int)$data['document_id'] : null;
    $action = isset($data['action']) ? $data['action'] : 'view';
    
    if (!$documentId) {
        echo json_encode([
            'success' => false, 
            'message' => 'ID de documento requerido'
        ]);
        exit;
    }
    
    // Verificar si el documento existe y es accesible
    if (!canAccessDocument($documentId)) {
        echo json_encode([
            'success' => false,
            'message' => 'No tienes acceso a este documento según las restricciones de tu grupo'
        ]);
        exit;
    }
    
    // Verificar permisos específicos según la acción
    $hasPermission = false;
    $errorMessage = '';
    
    switch ($action) {
        case 'view':
            $hasPermission = hasUserPermission('view');
            $errorMessage = 'No tienes permisos para ver documentos';
            break;
            
        case 'download':
            $hasPermission = hasUserPermission('download');
            $errorMessage = 'No tienes permisos para descargar documentos';
            
            // Verificar límites diarios de descarga
            if ($hasPermission) {
                $userPerms = getUserPermissions();
                $downloadLimit = $userPerms['limits']['download'];
                
                if ($downloadLimit !== null) {
                    $todayDownloads = getTodayDownloadCount();
                    if ($todayDownloads >= $downloadLimit) {
                        $hasPermission = false;
                        $errorMessage = "Has alcanzado tu límite diario de descargas ($downloadLimit)";
                    }
                }
            }
            break;
            
        case 'edit':
            $hasPermission = hasUserPermission('edit');
            $errorMessage = 'No tienes permisos para editar documentos';
            break;
            
        case 'delete':
            $hasPermission = hasUserPermission('delete');
            $errorMessage = 'No tienes permisos para eliminar documentos';
            break;
            
        case 'delete_permanent':
            $hasPermission = hasUserPermission('delete_permanent');
            $errorMessage = 'No tienes permisos para eliminación permanente';
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Acción no válida'
            ]);
            exit;
    }
    
    if (!$hasPermission) {
        echo json_encode([
            'success' => false,
            'message' => $errorMessage
        ]);
        exit;
    }
    
    // Si llegamos aquí, el usuario tiene permisos
    $response = [
        'success' => true,
        'message' => 'Acceso permitido',
        'document_id' => $documentId,
        'action' => $action
    ];
    
    // Agregar información adicional para descargas
    if ($action === 'download') {
        $userPerms = getUserPermissions();
        $downloadLimit = $userPerms['limits']['download'];
        $todayDownloads = getTodayDownloadCount();
        
        $response['download_info'] = [
            'daily_limit' => $downloadLimit,
            'used_today' => $todayDownloads,
            'remaining' => $downloadLimit ? max(0, $downloadLimit - $todayDownloads) : null
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Error en check_document_access: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}

/**
 * Obtener cantidad de descargas del usuario para hoy
 */
function getTodayDownloadCount($userId = null) {
    if ($userId === null) {
        $currentUser = SessionManager::getCurrentUser();
        $userId = $currentUser['id'] ?? null;
    }
    
    if (!$userId) {
        return 0;
    }
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $query = "
            SELECT COUNT(*) 
            FROM activity_logs 
            WHERE user_id = ? 
            AND action = 'download_document' 
            AND DATE(created_at) = CURDATE()
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        
        return (int)$stmt->fetchColumn();
        
    } catch (Exception $e) {
        error_log('Error obteniendo conteo de descargas: ' . $e->getMessage());
        return 0;
    }
}
?>