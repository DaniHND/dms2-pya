<?php
// modules/users/actions/get_user.php
// Obtener datos de un usuario para edición - DMS2 (CORREGIDO)

require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

SessionManager::requireLogin();
SessionManager::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $userId = intval($_GET['id'] ?? 0);
    
    if ($userId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    $query = "SELECT u.*, c.name as company_name 
              FROM users u 
              LEFT JOIN companies c ON u.company_id = c.id 
              WHERE u.id = ?";
    
    $user = fetchOne($query, [$userId]);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Remover campos sensibles
    unset($user['password']);
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

---

<?php
// modules/users/actions/update_user.php
// Actualizar datos de usuario - DMS2 (CORREGIDO)

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
    
    $userId = intval($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    // Verificar que el usuario existe
    $existingUser = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$existingUser) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Validar campos requeridos
    $requiredFields = ['first_name', 'last_name', 'username', 'email', 'role', 'company_id'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo {$field} es requerido");
        }
    }
    
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $companyId = intval($_POST['company_id']);
    $downloadEnabled = isset($_POST['download_enabled']) ? 1 : 0;
    $changePassword = isset($_POST['change_password']);
    
    // Validaciones
    if (strlen($firstName) < 2) {
        throw new Exception('El nombre debe tener al menos 2 caracteres');
    }
    
    if (strlen($lastName) < 2) {
        throw new Exception('El apellido debe tener al menos 2 caracteres');
    }
    
    if (strlen($username) < 3) {
        throw new Exception('El nombre de usuario debe tener al menos 3 caracteres');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no es válido');
    }
    
    if (!in_array($role, ['admin', 'user', 'viewer'])) {
        throw new Exception('Rol no válido');
    }
    
    // Verificar que no exista el username (excepto el actual)
    $checkUsername = fetchOne("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $userId]);
    if ($checkUsername) {
        throw new Exception('El nombre de usuario ya existe');
    }
    
    // Verificar que no exista el email (excepto el actual)
    $checkEmail = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
    if ($checkEmail) {
        throw new Exception('El email ya está registrado');
    }
    
    // Verificar que la empresa existe
    $checkCompany = fetchOne("SELECT id FROM companies WHERE id = ? AND status = 'active'", [$companyId]);
    if (!$checkCompany) {
        throw new Exception('La empresa seleccionada no es válida');
    }
    
    // Preparar datos para actualizar
    $updateData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'company_id' => $companyId,
        'download_enabled' => $downloadEnabled
    ];
    
    // Si se va a cambiar la contraseña
    if ($changePassword) {
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            throw new Exception('La contraseña debe tener al menos 6 caracteres');
        }
        
        $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    // Actualizar el usuario usando updateRecord
    $result = updateRecord('users', $updateData, 'id = :id', ['id' => $userId]);
    
    if ($result) {
        // Registrar actividad
        logActivity($currentUser['id'], 'update_user', 'users', $userId, 
                   "Actualizó el usuario: {$firstName} {$lastName} (@{$username})");
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente'
        ]);
    } else {
        throw new Exception('Error al actualizar el usuario');
    }
    
} catch (Exception $e) {
    error_log("Error updating user: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

---

<?php
// modules/users/actions/get_user_details.php
// Obtener detalles completos de un usuario - DMS2 (CORREGIDO)

require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

SessionManager::requireLogin();
SessionManager::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $userId = intval($_GET['id'] ?? 0);
    
    if ($userId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    // Obtener datos básicos del usuario
    $query = "SELECT u.*, c.name as company_name 
              FROM users u 
              LEFT JOIN companies c ON u.company_id = c.id 
              WHERE u.id = ?";
    
    $user = fetchOne($query, [$userId]);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Remover campos sensibles
    unset($user['password']);
    
    // Obtener estadísticas del usuario
    $stats = [];
    
    // Total de actividades
    $result = fetchOne("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ?", [$userId]);
    $stats['total_activities'] = $result['total'] ?? 0;
    
    // Total de documentos subidos
    $result = fetchOne("SELECT COUNT(*) as total FROM documents WHERE user_id = ?", [$userId]);
    $stats['total_documents'] = $result['total'] ?? 0;
    
    // Total de descargas
    $result = fetchOne("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ? AND action = 'download'", [$userId]);
    $stats['total_downloads'] = $result['total'] ?? 0;
    
    // Días desde el último login
    $stats['days_since_last_login'] = 'N/A';
    if ($user['last_login']) {
        $lastLogin = new DateTime($user['last_login']);
        $now = new DateTime();
        $diff = $now->diff($lastLogin);
        $stats['days_since_last_login'] = $diff->days;
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

---

<?php
// modules/users/actions/toggle_user_status.php
// Cambiar estado de usuario (activo/inactivo) - DMS2 (CORREGIDO)

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
    $newStatus = $input['status'] ?? '';
    
    if ($userId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    if (!in_array($newStatus, ['active', 'inactive'])) {
        throw new Exception('Estado inválido');
    }
    
    // Verificar que el usuario existe
    $user = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // No permitir desactivar al propio usuario
    if ($userId == $currentUser['id'] && $newStatus === 'inactive') {
        throw new Exception('No puede desactivar su propia cuenta');
    }
    
    // Actualizar estado usando updateRecord
    $result = updateRecord('users', ['status' => $newStatus], 'id = :id', ['id' => $userId]);
    
    if ($result) {
        // Registrar actividad
        $action = $newStatus === 'active' ? 'activar' : 'desactivar';
        logActivity($currentUser['id'], 'toggle_user_status', 'users', $userId, 
                   "Cambió el estado del usuario {$user['first_name']} {$user['last_name']} a {$newStatus}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado exitosamente',
            'new_status' => $newStatus
        ]);
    } else {
        throw new Exception('Error al actualizar el estado');
    }
    
} catch (Exception $e) {
    error_log("Error toggling user status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

---

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