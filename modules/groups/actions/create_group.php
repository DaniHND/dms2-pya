<?php
/*
 * modules/groups/actions/create_group.php
 * Acción para crear nuevos grupos con permisos y restricciones
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
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $downloadLimit = !empty($_POST['download_limit_daily']) ? (int)$_POST['download_limit_daily'] : null;
    $uploadLimit = !empty($_POST['upload_limit_daily']) ? (int)$_POST['upload_limit_daily'] : null;
    
    // Validar campos obligatorios
    if (empty($name)) {
        throw new Exception('El nombre del grupo es obligatorio');
    }
    
    if (strlen($name) > 150) {
        throw new Exception('El nombre del grupo no puede exceder 150 caracteres');
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception('Estado inválido');
    }
    
    // Verificar que el nombre no exista
    $checkQuery = "SELECT id FROM user_groups WHERE name = ? AND id != ?";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$name, 0]); // 0 porque es nuevo grupo
    
    if ($stmt->fetch()) {
        throw new Exception('Ya existe un grupo con ese nombre');
    }
    
    // Procesar permisos
    $permissions = [];
    if (!empty($_POST['permissions'])) {
        $permissionsData = json_decode($_POST['permissions'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($permissionsData)) {
            $permissions = $permissionsData;
        }
    }
    
    // Procesar restricciones
    $restrictions = [];
    if (!empty($_POST['restrictions'])) {
        $restrictionsData = json_decode($_POST['restrictions'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($restrictionsData)) {
            $restrictions = $restrictionsData;
        }
    }
    
    // Validar permisos (estructura básica)
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
    
    // Validar restricciones
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
        // Insertar el grupo
        $insertQuery = "INSERT INTO user_groups (
            name, 
            description, 
            module_permissions, 
            access_restrictions, 
            download_limit_daily, 
            upload_limit_daily, 
            status, 
            is_system_group, 
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($insertQuery);
        $success = $stmt->execute([
            $name,
            $description,
            json_encode($permissions),
            json_encode($restrictions),
            $downloadLimit,
            $uploadLimit,
            $status,
            false, // Grupos creados por admin no son de sistema
            $currentUser['id']
        ]);
        
        if (!$success) {
            throw new Exception('Error al insertar el grupo en la base de datos');
        }
        
        $groupId = $pdo->lastInsertId();
        
        // Registrar actividad
        $logQuery = "INSERT INTO activity_logs (user_id, action, module, details, ip_address) 
                     VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($logQuery);
        $stmt->execute([
            $currentUser['id'],
            'group_created',
            'groups',
            "Grupo '$name' creado con ID $groupId",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Confirmar transacción
        $pdo->commit();
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Grupo creado correctamente',
            'group_id' => $groupId,
            'data' => [
                'id' => $groupId,
                'name' => $name,
                'description' => $description,
                'status' => $status,
                'permissions' => $permissions,
                'restrictions' => $restrictions,
                'download_limit_daily' => $downloadLimit,
                'upload_limit_daily' => $uploadLimit
            ]
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en create_group.php: " . $e->getMessage());
    
    // Respuesta de error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GROUP_CREATE_ERROR'
    ]);
}
?>