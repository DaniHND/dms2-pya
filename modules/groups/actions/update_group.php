<?php
/*
 * modules/groups/actions/update_group.php
 * Acción para actualizar grupos existentes
 */

require_once '../../../config/session.php';
require_once '../../../config/database.php';

// Configurar respuesta JSON
header('Content-Type: application/json');

try {
    // Verificar sesión y permisos
    SessionManager::requireRole('admin');
    $currentUser = SessionManager::getCurrentUser();
    
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Validar datos recibidos
    $groupId = $_POST['group_id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $downloadLimit = !empty($_POST['download_limit_daily']) ? (int)$_POST['download_limit_daily'] : null;
    $uploadLimit = !empty($_POST['upload_limit_daily']) ? (int)$_POST['upload_limit_daily'] : null;
    
    // Validar campos obligatorios
    if (empty($groupId) || !is_numeric($groupId)) {
        throw new Exception('ID de grupo inválido');
    }
    
    if (empty($name)) {
        throw new Exception('El nombre del grupo es obligatorio');
    }
    
    if (strlen($name) > 150) {
        throw new Exception('El nombre del grupo no puede exceder 150 caracteres');
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception('Estado inválido');
    }
    
    // Verificar que el grupo existe
    $groupQuery = "SELECT id, name, is_system_group FROM user_groups WHERE id = ?";
    $stmt = $pdo->prepare($groupQuery);
    $stmt->execute([$groupId]);
    $existingGroup = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingGroup) {
        throw new Exception('Grupo no encontrado');
    }
    
    // Prevenir modificación de grupos del sistema (excepto estado)
    if ($existingGroup['is_system_group']) {
        // Solo permitir cambio de estado y límites para grupos del sistema
        $allowedChanges = [
            'status' => $status,
            'download_limit_daily' => $downloadLimit,
            'upload_limit_daily' => $uploadLimit
        ];
        
        // Mantener datos originales para otros campos
        $name = $existingGroup['name'];
        $permissions = [];
        $restrictions = [];
    } else {
        // Verificar que el nombre no esté en uso por otro grupo
        $checkQuery = "SELECT id FROM user_groups WHERE name = ? AND id != ?";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([$name, $groupId]);
        
        if ($stmt->fetch()) {
            throw new Exception('Ya existe otro grupo con ese nombre');
        }
        
        // Procesar permisos para grupos no del sistema
        $permissions = [];
        if (!empty($_POST['permissions'])) {
            $permissionsData = json_decode($_POST['permissions'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($permissionsData)) {
                $permissions = $permissionsData;
            }
        }
        
        // Procesar restricciones para grupos no del sistema
        $restrictions = [];
        if (!empty($_POST['restrictions'])) {
            $restrictionsData = json_decode($_POST['restrictions'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($restrictionsData)) {
                $restrictions = $restrictionsData;
            }
        }
        
        // Validar permisos (misma lógica que create_group.php)
        $validModules = ['users', 'companies', 'departments', 'documents', 'groups', 'reports'];
        $validActions = ['read', 'write', 'delete', 'download'];
        
        foreach ($permissions as $module => $actions) {
            if (!in_array($module, $validModules)) {
                throw new Exception("Módulo inválido: $module");
            }
            
            foreach ($actions as $action => $allowed) {
                if (!in_array($action, $validActions)) {
                    throw new Exception("Acción inválida: $action en módulo $module");
                }
                
                if (!is_bool($allowed)) {
                    throw new Exception("Valor de permiso inválido para $action en $module");
                }
            }
        }
        
        // Validar restricciones (misma lógica que create_group.php)
        if (isset($restrictions['companies'])) {
            if (!in_array($restrictions['companies'], ['all', 'user_company']) && !is_array($restrictions['companies'])) {
                throw new Exception('Restricción de empresas inválida');
            }
            
            if (is_array($restrictions['companies'])) {
                foreach ($restrictions['companies'] as $companyId) {
                    if (!is_int($companyId) || $companyId <= 0) {
                        throw new Exception('ID de empresa inválido en restricciones');
                    }
                }
            }
        }
        
        if (isset($restrictions['departments'])) {
            if (!in_array($restrictions['departments'], ['all', 'user_department']) && !is_array($restrictions['departments'])) {
                throw new Exception('Restricción de departamentos inválida');
            }
            
            if (is_array($restrictions['departments'])) {
                foreach ($restrictions['departments'] as $deptId) {
                    if (!is_int($deptId) || $deptId <= 0) {
                        throw new Exception('ID de departamento inválido en restricciones');
                    }
                }
            }
        }
        
        if (isset($restrictions['document_types'])) {
            if ($restrictions['document_types'] !== 'all' && !is_array($restrictions['document_types'])) {
                throw new Exception('Restricción de tipos de documentos inválida');
            }
            
            if (is_array($restrictions['document_types'])) {
                foreach ($restrictions['document_types'] as $typeId) {
                    if (!is_int($typeId) || $typeId <= 0) {
                        throw new Exception('ID de tipo de documento inválido en restricciones');
                    }
                }
            }
        }
    }
    
    // Validar límites operacionales
    if ($downloadLimit !== null && ($downloadLimit < 0 || $downloadLimit > 10000)) {
        throw new Exception('Límite de descargas diarias inválido (debe estar entre 0 y 10000)');
    }
    
    if ($uploadLimit !== null && ($uploadLimit < 0 || $uploadLimit > 1000)) {
        throw new Exception('Límite de subidas diarias inválido (debe estar entre 0 y 1000)');
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // Preparar query de actualización
        if ($existingGroup['is_system_group']) {
            // Solo actualizar campos permitidos para grupos del sistema
            $updateQuery = "UPDATE user_groups SET 
                           status = ?, 
                           download_limit_daily = ?, 
                           upload_limit_daily = ?, 
                           updated_at = CURRENT_TIMESTAMP
                           WHERE id = ?";
            
            $updateParams = [$status, $downloadLimit, $uploadLimit, $groupId];
        } else {
            // Actualización completa para grupos personalizados
            $updateQuery = "UPDATE user_groups SET 
                           name = ?, 
                           description = ?, 
                           module_permissions = ?, 
                           access_restrictions = ?, 
                           download_limit_daily = ?, 
                           upload_limit_daily = ?, 
                           status = ?, 
                           updated_at = CURRENT_TIMESTAMP
                           WHERE id = ?";
            
            $updateParams = [
                $name,
                $description,
                json_encode($permissions),
                json_encode($restrictions),
                $downloadLimit,
                $uploadLimit,
                $status,
                $groupId
            ];
        }
        
        $stmt = $pdo->prepare($updateQuery);
        $success = $stmt->execute($updateParams);
        
        if (!$success) {
            throw new Exception('Error al actualizar el grupo en la base de datos');
        }
        
        // Verificar si realmente se actualizó algo
        if ($stmt->rowCount() === 0) {
            throw new Exception('No se realizaron cambios en el grupo');
        }
        
        // Registrar actividad
        $logQuery = "INSERT INTO activity_logs (user_id, action, module, details, ip_address) 
                     VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($logQuery);
        $stmt->execute([
            $currentUser['id'],
            'group_updated',
            'groups',
            "Grupo '$name' actualizado (ID: $groupId)",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Si se cambió el estado a inactivo, registrar usuarios afectados
        if ($status === 'inactive') {
            $affectedUsersQuery = "SELECT COUNT(*) as count FROM user_group_members ugm 
                                  JOIN users u ON ugm.user_id = u.id 
                                  WHERE ugm.group_id = ? AND u.status = 'active'";
            $stmt = $pdo->prepare($affectedUsersQuery);
            $stmt->execute([$groupId]);
            $affectedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($affectedCount > 0) {
                $logQuery = "INSERT INTO activity_logs (user_id, action, module, details, ip_address) 
                             VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($logQuery);
                $stmt->execute([
                    $currentUser['id'],
                    'group_deactivation_affects_users',
                    'groups',
                    "Desactivación del grupo '$name' afecta a $affectedCount usuarios",
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
        }
        
        // Confirmar transacción
        $pdo->commit();
        
        // Obtener datos actualizados del grupo
        $updatedGroupQuery = "SELECT ug.*, 
                             COUNT(DISTINCT ugm.user_id) as total_members,
                             COUNT(DISTINCT CASE WHEN u.status = 'active' THEN ugm.user_id END) as active_members
                             FROM user_groups ug
                             LEFT JOIN user_group_members ugm ON ug.id = ugm.group_id
                             LEFT JOIN users u ON ugm.user_id = u.id AND u.status != 'deleted'
                             WHERE ug.id = ?
                             GROUP BY ug.id";
        
        $stmt = $pdo->prepare($updatedGroupQuery);
        $stmt->execute([$groupId]);
        $updatedGroup = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Grupo actualizado correctamente',
            'group_id' => $groupId,
            'data' => [
                'id' => $groupId,
                'name' => $name,
                'description' => $description,
                'status' => $status,
                'is_system_group' => $existingGroup['is_system_group'],
                'permissions' => $existingGroup['is_system_group'] ? null : $permissions,
                'restrictions' => $existingGroup['is_system_group'] ? null : $restrictions,
                'download_limit_daily' => $downloadLimit,
                'upload_limit_daily' => $uploadLimit,
                'total_members' => $updatedGroup['total_members'],
                'active_members' => $updatedGroup['active_members']
            ]
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en update_group.php: " . $e->getMessage());
    
    // Respuesta de error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GROUP_UPDATE_ERROR'
    ]);
}
?>