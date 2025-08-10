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
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // Actualizar el grupo
    $updateStmt = $pdo->prepare("
        UPDATE user_groups 
        SET 
            module_permissions = ?,
            access_restrictions = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $success = $updateStmt->execute([
        json_encode($finalPermissions, JSON_UNESCAPED_UNICODE),
        json_encode($finalRestrictions, JSON_UNESCAPED_UNICODE),
        $groupId
    ]);
    
    if (!$success) {
        throw new Exception('Error al actualizar la base de datos');
    }
    
    // Registrar actividad en logs si la función existe
    try {
        if (function_exists('logActivity') || class_exists('ActivityLogger')) {
            $description = "Permisos y restricciones actualizados para el grupo '{$group['name']}'";
            $changes = [
                'permissions_updated' => !empty($permissions),
                'restrictions_updated' => !empty($restrictions),
                'permissions_count' => count(array_filter($finalPermissions)),
                'companies_restricted' => count($finalRestrictions['companies'] ?? []),
                'departments_restricted' => count($finalRestrictions['departments'] ?? []),
                'document_types_restricted' => count($finalRestrictions['document_types'] ?? [])
            ];
            
            if (function_exists('logActivity')) {
                logActivity(
                    $currentUser['id'], 
                    'update_group_permissions', 
                    'user_groups', 
                    $groupId, 
                    $description,
                    json_encode($changes)
                );
            }
        }
    } catch (Exception $logError) {
        // No interrumpir la operación por errores de logging
        error_log('Error al registrar actividad: ' . $logError->getMessage());
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    // Preparar resumen de cambios para la respuesta
    $permissionsActive = array_filter($finalPermissions);
    $restrictionsSummary = [
        'companies' => count($finalRestrictions['companies'] ?? []),
        'departments' => count($finalRestrictions['departments'] ?? []),
        'document_types' => count($finalRestrictions['document_types'] ?? [])
    ];
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Configuración de seguridad actualizada correctamente',
        'data' => [
            'group' => [
                'id' => $groupId,
                'name' => $group['name']
            ],
            'permissions' => [
                'updated' => !empty($permissions),
                'active_count' => count($permissionsActive),
                'details' => $finalPermissions
            ],
            'restrictions' => [
                'updated' => !empty($restrictions),
                'summary' => $restrictionsSummary,
                'details' => $finalRestrictions
            ],
            'security_level' => calculateSecurityLevel($finalPermissions, $finalRestrictions)
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error PDO en update_group_permissions: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos',
        'debug' => $e->getMessage() // Solo en desarrollo, remover en producción
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Error general en update_group_permissions: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor',
        'debug' => $e->getMessage() // Solo en desarrollo, remover en producción
    ]);
}

/**
 * Calcula el nivel de seguridad basado en permisos y restricciones
 */
function calculateSecurityLevel($permissions, $restrictions) {
    $activePermissions = count(array_filter($permissions));
    $totalRestrictions = count($restrictions['companies'] ?? []) + 
                        count($restrictions['departments'] ?? []) + 
                        count($restrictions['document_types'] ?? []);
    
    if ($activePermissions === 0) {
        return 'maximum'; // Sin permisos = máxima seguridad
    } elseif ($activePermissions <= 2 && $totalRestrictions > 5) {
        return 'high'; // Pocos permisos, muchas restricciones
    } elseif ($activePermissions <= 3 && $totalRestrictions > 2) {
        return 'medium'; // Permisos moderados con restricciones
    } elseif ($totalRestrictions === 0) {
        return 'low'; // Sin restricciones = baja seguridad
    } else {
        return 'medium'; // Caso general
    }
}
?>