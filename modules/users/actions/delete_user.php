<?php
// modules/users/actions/delete_user.php
// Eliminar usuario - DMS2 (CORREGIDO)

require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

SessionManager::requireLogin();
SessionManager::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $currentUser = SessionManager::getCurrentUser();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos inválidos');
    }
    
    $userId = intval($input['user_id'] ?? 0);
    
    if ($userId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    // Verificar que el usuario existe
    $user = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // No permitir eliminar al propio usuario
    if ($userId == $currentUser['id']) {
        throw new Exception('No puede eliminar su propia cuenta');
    }
    
    // Verificar si el usuario tiene documentos
    $documentsCount = fetchOne("SELECT COUNT(*) as total FROM documents WHERE user_id = ?", [$userId]);
    if ($documentsCount['total'] > 0) {
        throw new Exception('No se puede eliminar el usuario porque tiene documentos asociados. Primero desactívelo.');
    }
    
    // Crear conexión para transacción
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Iniciar transacción
    $conn->beginTransaction();
    
    try {
        // Eliminar registros de actividad del usuario
        $deleteActivityQuery = "DELETE FROM activity_logs WHERE user_id = ?";
        $deleteActivityStmt = $conn->prepare($deleteActivityQuery);
        $deleteActivityStmt->execute([$userId]);
        
        // Eliminar el usuario
        $deleteUserQuery = "DELETE FROM users WHERE id = ?";
        $deleteUserStmt = $conn->prepare($deleteUserQuery);
        $deleteUserResult = $deleteUserStmt->execute([$userId]);
        
        if ($deleteUserResult) {
            // Registrar actividad de eliminación
            logActivity($currentUser['id'], 'delete_user', 'users', $userId, 
                       "Eliminó el usuario: {$user['first_name']} {$user['last_name']} (@{$user['username']})");
            
            // Confirmar transacción
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ]);
        } else {
            throw new Exception('Error al eliminar el usuario');
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error deleting user: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>