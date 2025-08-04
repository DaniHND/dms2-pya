<?php
/*
 * modules/groups/actions/manage_group_members.php
 * Gestionar miembros de grupos (agregar/remover usuarios)
 */

// Configurar headers y manejo de errores
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Obtener rutas absolutas
$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/config/session.php';

// Cargar functions.php si existe
if (file_exists($projectRoot . '/includes/functions.php')) {
    require_once $projectRoot . '/includes/functions.php';
}

try {
    // Verificar autenticación
    if (!SessionManager::isLoggedIn()) {
        throw new Exception("Usuario no autenticado");
    }
    
    $currentUser = SessionManager::getCurrentUser();
    if ($currentUser['role'] !== 'admin') {
        throw new Exception("Permisos insuficientes. Se requiere rol de administrador");
    }
    
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método HTTP no permitido");
    }
    
    // Obtener y validar datos
    $groupId = $_POST['group_id'] ?? null;
    $userId = $_POST['user_id'] ?? null;
    $action = $_POST['action'] ?? null;
    
    if (!$groupId || !is_numeric($groupId)) {
        throw new Exception("ID de grupo inválido");
    }
    
    if (!$userId || !is_numeric($userId)) {
        throw new Exception("ID de usuario inválido");
    }
    
    if (!in_array($action, ['add', 'remove'])) {
        throw new Exception("Acción inválida. Debe ser 'add' o 'remove'");
    }
    
    $groupId = (int)$groupId;
    $userId = (int)$userId;
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Verificar que el grupo existe y no es del sistema
    $groupQuery = "SELECT id, name, is_system_group FROM user_groups WHERE id = ?";
    $groupStmt = $pdo->prepare($groupQuery);
    $groupStmt->execute([$groupId]);
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        throw new Exception("Grupo no encontrado");
    }
    
    if ($group['is_system_group'] == 1) {
        throw new Exception("No se puede modificar la membresía de un grupo del sistema");
    }
    
    // Verificar que el usuario existe y está activo
    $userQuery = "SELECT id, first_name, last_name, email, status FROM users WHERE id = ?";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("Usuario no encontrado");
    }
    
    if ($user['status'] !== 'active') {
        throw new Exception("El usuario no está activo");
    }
    
    // Verificar si ya existe la relación
    $memberQuery = "SELECT id FROM user_group_members WHERE group_id = ? AND user_id = ?";
    $memberStmt = $pdo->prepare($memberQuery);
    $memberStmt->execute([$groupId, $userId]);
    $existingMember = $memberStmt->fetch();
    
    if ($action === 'add') {
        // Agregar usuario al grupo
        if ($existingMember) {
            throw new Exception("El usuario ya es miembro de este grupo");
        }
        
        $insertQuery = "
            INSERT INTO user_group_members (group_id, user_id, assigned_by, added_at) 
            VALUES (?, ?, ?, NOW())
        ";
        $insertStmt = $pdo->prepare($insertQuery);
        
        if (!$insertStmt->execute([$groupId, $userId, $currentUser['id']])) {
            throw new Exception("Error al agregar usuario al grupo");
        }
        
        // Registrar actividad
        if (function_exists('logActivity')) {
            logActivity(
                $currentUser['id'], 
                'add_user_to_group', 
                'groups', 
                $groupId, 
                "Usuario {$user['first_name']} {$user['last_name']} agregado al grupo {$group['name']}"
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Usuario agregado al grupo exitosamente",
            'user' => [
                'id' => $user['id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email']
            ]
        ]);
        
    } elseif ($action === 'remove') {
        // Remover usuario del grupo
        if (!$existingMember) {
            throw new Exception("El usuario no es miembro de este grupo");
        }
        
        $deleteQuery = "DELETE FROM user_group_members WHERE group_id = ? AND user_id = ?";
        $deleteStmt = $pdo->prepare($deleteQuery);
        
        if (!$deleteStmt->execute([$groupId, $userId])) {
            throw new Exception("Error al remover usuario del grupo");
        }
        
        // Registrar actividad
        if (function_exists('logActivity')) {
            logActivity(
                $currentUser['id'], 
                'remove_user_from_group', 
                'groups', 
                $groupId, 
                "Usuario {$user['first_name']} {$user['last_name']} removido del grupo {$group['name']}"
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Usuario removido del grupo exitosamente",
            'user' => [
                'id' => $user['id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email']
            ]
        ]);
    }
    
} catch (PDOException $e) {
    error_log('Error PDO en manage_group_members.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage(),
        'error_code' => 'DB_ERROR'
    ]);
    
} catch (Exception $e) {
    error_log('Error en manage_group_members.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GENERAL_ERROR'
    ]);
}
?>