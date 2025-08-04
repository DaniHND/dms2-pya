<?php
/*
 * modules/groups/actions/delete_group.php
 * Eliminar grupo de usuarios (soft delete o hard delete)
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
    
    if (!$groupId || !is_numeric($groupId)) {
        throw new Exception("ID de grupo inválido");
    }
    
    $groupId = (int)$groupId;
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Verificar que el grupo existe y obtener información
    $groupQuery = "SELECT id, name, is_system_group FROM user_groups WHERE id = ?";
    $groupStmt = $pdo->prepare($groupQuery);
    $groupStmt->execute([$groupId]);
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        throw new Exception("Grupo no encontrado");
    }
    
    // Verificar que no es un grupo del sistema
    if ($group['is_system_group'] == 1) {
        throw new Exception("No se puede eliminar un grupo del sistema");
    }
    
    // Contar miembros del grupo
    $membersQuery = "SELECT COUNT(*) as member_count FROM user_group_members WHERE group_id = ?";
    $membersStmt = $pdo->prepare($membersQuery);
    $membersStmt->execute([$groupId]);
    $memberCount = $membersStmt->fetch(PDO::FETCH_ASSOC)['member_count'];
    
    // Comenzar transacción
    $pdo->beginTransaction();
    
    try {
        // Verificar si la tabla user_groups tiene columna deleted_at
        $columnsQuery = "SHOW COLUMNS FROM user_groups LIKE 'deleted_at'";
        $columnsStmt = $pdo->prepare($columnsQuery);
        $columnsStmt->execute();
        $hasDeletedAt = $columnsStmt->rowCount() > 0;
        
        if ($hasDeletedAt) {
            // Soft delete - marcar como eliminado
            $deleteQuery = "
                UPDATE user_groups 
                SET deleted_at = NOW(), 
                    status = 'inactive',
                    updated_at = NOW()
                WHERE id = ?
            ";
            $deleteStmt = $pdo->prepare($deleteQuery);
            $deleteStmt->execute([$groupId]);
            
            // No eliminar miembros en soft delete, solo marcar grupo como eliminado
            $deleteType = 'soft';
            
        } else {
            // Hard delete - eliminar completamente
            
            // 1. Primero eliminar todas las relaciones de miembros
            $deleteMembersQuery = "DELETE FROM user_group_members WHERE group_id = ?";
            $deleteMembersStmt = $pdo->prepare($deleteMembersQuery);
            $deleteMembersStmt->execute([$groupId]);
            
            // 2. Luego eliminar el grupo
            $deleteGroupQuery = "DELETE FROM user_groups WHERE id = ?";
            $deleteGroupStmt = $pdo->prepare($deleteGroupQuery);
            $deleteGroupStmt->execute([$groupId]);
            
            $deleteType = 'hard';
        }
        
        // Verificar que se eliminó correctamente
        if ($hasDeletedAt) {
            // Verificar que se marcó como eliminado
            $verifyQuery = "SELECT deleted_at FROM user_groups WHERE id = ?";
            $verifyStmt = $pdo->prepare($verifyQuery);
            $verifyStmt->execute([$groupId]);
            $result = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result || !$result['deleted_at']) {
                throw new Exception("Error al marcar el grupo como eliminado");
            }
        } else {
            // Verificar que se eliminó completamente
            $verifyQuery = "SELECT COUNT(*) as count FROM user_groups WHERE id = ?";
            $verifyStmt = $pdo->prepare($verifyQuery);
            $verifyStmt->execute([$groupId]);
            $count = $verifyStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                throw new Exception("Error al eliminar el grupo");
            }
        }
        
        // Confirmar transacción
        $pdo->commit();
        
        // Registrar actividad
        if (function_exists('logActivity')) {
            $activityDetails = [
                'group_name' => $group['name'],
                'member_count' => $memberCount,
                'delete_type' => $deleteType
            ];
            
            logActivity(
                $currentUser['id'], 
                'delete_group', 
                'groups', 
                $groupId, 
                "Grupo '{$group['name']}' eliminado ({$deleteType} delete) - {$memberCount} miembros afectados",
                json_encode($activityDetails)
            );
        }
        
        $message = $deleteType === 'soft' ? 
            "Grupo '{$group['name']}' marcado como eliminado" :
            "Grupo '{$group['name']}' eliminado permanentemente";
            
        if ($memberCount > 0) {
            $message .= ". {$memberCount} usuario(s) removido(s) del grupo.";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'group_id' => $groupId,
            'group_name' => $group['name'],
            'delete_type' => $deleteType,
            'members_affected' => $memberCount
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log('Error PDO en delete_group.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al eliminar grupo',
        'error_code' => 'DB_ERROR'
    ]);
    
} catch (Exception $e) {
    error_log('Error en delete_group.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GENERAL_ERROR'
    ]);
}
?>