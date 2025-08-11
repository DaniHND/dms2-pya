<?php
/*
 * modules/groups/actions/update_group_permissions.php
 * Actualización de permisos y restricciones - Sistema mejorado con 5 opciones específicas
 */

$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/session.php';
require_once $projectRoot . '/config/database.php';

header('Content-Type: application/json');

// Verificar sesión y permisos
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos de administrador']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Leer datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
    exit;
}

$groupId = (int)($data['group_id'] ?? 0);
$permissions = $data['permissions'] ?? [];
$restrictions = $data['restrictions'] ?? [];

if (!$groupId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de grupo requerido']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar que el grupo existe y obtener información actual
    $groupCheck = $pdo->prepare("SELECT id, name, is_system_group, module_permissions, access_restrictions FROM user_groups WHERE id = ?");
    $groupCheck->execute([$groupId]);
    $group = $groupCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Grupo no encontrado']);
        exit;
    }
    
    // Obtener permisos y restricciones actuales
    $currentPermissions = json_decode($group['module_permissions'] ?: '{}', true);
    $currentRestrictions = json_decode($group['access_restrictions'] ?: '{}', true);
    
    // Definir permisos válidos específicos
    $validPermissions = [
        'upload_files',      // 1. Subir archivo
        'view_files',        // 2. Ver archivos  
        'create_folders',    // 3. Crear carpetas
        'download_files',    // 4. Descargar
        'delete_files'       // 5. Eliminar archivo o documento
    ];
    
    $pdo->beginTransaction();
    
    // Procesar permisos - solo actualizar si se envían
    $finalPermissions = $currentPermissions;
    if (!empty($permissions)) {
        foreach ($validPermissions as $permission) {
            // Solo actualizar permisos específicos, mantener otros existentes
            if (isset($permissions[$permission])) {
                $finalPermissions[$permission] = (bool)$permissions[$permission];
            }
        }
    }
    
    // Validar y procesar restricciones
    $finalRestrictions = $currentRestrictions;
    if (!empty($restrictions)) {
        // Validar empresas
        if (isset($restrictions['companies']) && is_array($restrictions['companies'])) {
            $companyIds = array_filter(array_map('intval', $restrictions['companies']));
            if (!empty($companyIds)) {
                // Verificar que las empresas existen y están activas
                $companyPlaceholders = str_repeat('?,', count($companyIds) - 1) . '?';
                $companyCheck = $pdo->prepare("SELECT id FROM companies WHERE id IN ($companyPlaceholders) AND status = 'active'");
                $companyCheck->execute($companyIds);
                $validCompanies = $companyCheck->fetchAll(PDO::FETCH_COLUMN);
                $finalRestrictions['companies'] = array_map('intval', $validCompanies);
            } else {
                $finalRestrictions['companies'] = [];
            }
        }
        
        // Validar departamentos
        if (isset($restrictions['departments']) && is_array($restrictions['departments'])) {
            $departmentIds = array_filter(array_map('intval', $restrictions['departments']));
            if (!empty($departmentIds)) {
                // Verificar que los departamentos existen y están activos
                $departmentPlaceholders = str_repeat('?,', count($departmentIds) - 1) . '?';
                $departmentCheck = $pdo->prepare("SELECT id FROM departments WHERE id IN ($departmentPlaceholders) AND status = 'active'");
                $departmentCheck->execute($departmentIds);
                $validDepartments = $departmentCheck->fetchAll(PDO::FETCH_COLUMN);
                $finalRestrictions['departments'] = array_map('intval', $validDepartments);
            } else {
                $finalRestrictions['departments'] = [];
            }
        }
        
        // Validar tipos de documentos
        if (isset($restrictions['document_types']) && is_array($restrictions['document_types'])) {
            $docTypeIds = array_filter(array_map('intval', $restrictions['document_types']));
            if (!empty($docTypeIds)) {
                // Verificar que los tipos de documentos existen y están activos
                $docTypePlaceholders = str_repeat('?,', count($docTypeIds) - 1) . '?';
                $docTypeCheck = $pdo->prepare("SELECT id FROM document_types WHERE id IN ($docTypePlaceholders) AND status = 'active'");
                $docTypeCheck->execute($docTypeIds);
                $validDocTypes = $docTypeCheck->fetchAll(PDO::FETCH_COLUMN);
                $finalRestrictions['document_types'] = array_map('intval', $validDocTypes);
            } else {
                $finalRestrictions['document_types'] = [];
            }
        }
    }
    
    // Actualizar en base de datos
    $updateQuery = "UPDATE user_groups SET 
                    module_permissions = ?, 
                    access_restrictions = ?, 
                    updated_at = NOW() 
                    WHERE id = ?";
    
    $stmt = $pdo->prepare($updateQuery);
    $result = $stmt->execute([
        json_encode($finalPermissions),
        json_encode($finalRestrictions),
        $groupId
    ]);
    
    if (!$result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al actualizar grupo']);
        exit;
    }
    
    // Registrar actividad detallada
    $changes = [];
    
    // Detectar cambios en permisos
    foreach ($validPermissions as $permission) {
        if (isset($permissions[$permission])) {
            $oldValue = $currentPermissions[$permission] ?? false;
            $newValue = (bool)$permissions[$permission];
            if ($oldValue !== $newValue) {
                $permissionNames = [
                    'upload_files' => 'Subir archivos',
                    'view_files' => 'Ver archivos (Inbox)',
                    'create_folders' => 'Crear carpetas',
                    'download_files' => 'Descargar archivos',
                    'delete_files' => 'Eliminar archivos'
                ];
                $changes[] = "{$permissionNames[$permission]}: " . ($newValue ? 'ACTIVADO' : 'DESACTIVADO');
            }
        }
    }
    
    // Detectar cambios en restricciones
    foreach (['companies', 'departments', 'document_types'] as $restrictionType) {
        if (isset($restrictions[$restrictionType])) {
            $oldCount = isset($currentRestrictions[$restrictionType]) ? count($currentRestrictions[$restrictionType]) : 0;
            $newCount = count($finalRestrictions[$restrictionType]);
            if ($oldCount !== $newCount) {
                $restrictionNames = [
                    'companies' => 'Empresas permitidas',
                    'departments' => 'Departamentos permitidos',
                    'document_types' => 'Tipos de documentos permitidos'
                ];
                $changes[] = "{$restrictionNames[$restrictionType]}: {$newCount} elementos";
            }
        }
    }
    
    // Registrar en log de actividades
    if (!empty($changes)) {
        $logDetails = "Grupo '{$group['name']}' actualizado: " . implode(', ', $changes);
        
        $logStmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([
            $currentUser['id'],
            'group_permissions_updated',
            'user_groups',
            $groupId,
            $logDetails
        ]);
    }
    
    // Limpiar cache de permisos para usuarios afectados
    try {
        $memberQuery = "SELECT user_id FROM user_group_members WHERE group_id = ?";
        $memberStmt = $pdo->prepare($memberQuery);
        $memberStmt->execute([$groupId]);
        $memberIds = $memberStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Aquí podrías implementar limpieza de cache si usas Redis/Memcached
        // Por ahora solo registramos que se necesita limpiar
        if (!empty($memberIds)) {
            $changes[] = count($memberIds) . " usuarios afectados requieren revalidación de permisos";
        }
    } catch (Exception $e) {
        // Log pero no fallar por esto
        error_log("Warning: Could not clear permission cache: " . $e->getMessage());
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuración actualizada exitosamente',
        'updated_permissions' => $finalPermissions,
        'updated_restrictions' => $finalRestrictions,
        'changes_count' => count($changes),
        'changes' => $changes,
        'affected_users' => $memberIds ?? []
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error PDO en update_group_permissions.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error en update_group_permissions.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>