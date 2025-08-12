<?php
/*
 * modules/groups/actions/get_groups.php
 * Obtener lista de todos los grupos con estadísticas
 */

// Configurar headers y manejo de errores
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);

// Obtener rutas absolutas
$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/config/session.php';

function logDebug($message) {
    error_log("[GET_GROUPS] " . $message);
}

try {
    logDebug("Iniciando obtención de grupos");
    
    // Verificar autenticación
    if (!SessionManager::isLoggedIn()) {
        throw new Exception("Usuario no autenticado");
    }
    
    $currentUser = SessionManager::getCurrentUser();
    if ($currentUser['role'] !== 'admin') {
        throw new Exception("Permisos insuficientes");
    }
    
    logDebug("Usuario autenticado: " . $currentUser['id']);
    
    // Conectar a BD
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    logDebug("Conexión a BD establecida");
    
    // Query principal para obtener grupos con estadísticas
    $query = "
        SELECT 
            ug.id,
            ug.name,
            ug.description,
            ug.status,
            ug.is_system_group,
            ug.module_permissions,
            ug.access_restrictions,
            ug.download_limit_daily,
            ug.upload_limit_daily,
            ug.created_at,
            ug.updated_at,
            COALESCE(CONCAT(creator.first_name, ' ', creator.last_name), 'Sistema') as created_by_name,
            COALESCE(member_stats.total_members, 0) as total_members,
            COALESCE(member_stats.active_members, 0) as active_members,
            COALESCE(company_stats.companies_represented, 0) as companies_represented,
            COALESCE(department_stats.departments_represented, 0) as departments_represented
        FROM user_groups ug
        LEFT JOIN users creator ON ug.created_by = creator.id
        LEFT JOIN (
            SELECT 
                ugm.group_id,
                COUNT(DISTINCT ugm.user_id) as total_members,
                COUNT(DISTINCT CASE WHEN u.status = 'active' THEN ugm.user_id END) as active_members
            FROM user_group_members ugm
            LEFT JOIN users u ON ugm.user_id = u.id
            GROUP BY ugm.group_id
        ) member_stats ON ug.id = member_stats.group_id
        LEFT JOIN (
            SELECT 
                ugm.group_id,
                COUNT(DISTINCT u.company_id) as companies_represented
            FROM user_group_members ugm
            LEFT JOIN users u ON ugm.user_id = u.id
            WHERE u.company_id IS NOT NULL
            GROUP BY ugm.group_id
        ) company_stats ON ug.id = company_stats.group_id
        LEFT JOIN (
            SELECT 
                ugm.group_id,
                COUNT(DISTINCT u.department_id) as departments_represented
            FROM user_group_members ugm
            LEFT JOIN users u ON ugm.user_id = u.id
            WHERE u.department_id IS NOT NULL
            GROUP BY ugm.group_id
        ) department_stats ON ug.id = department_stats.group_id
        ORDER BY 
            ug.is_system_group DESC,
            ug.name ASC
    ";
    
    logDebug("Ejecutando query principal");
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logDebug("Grupos obtenidos: " . count($groups));
    
    // Procesar cada grupo
    foreach ($groups as &$group) {
        // Convertir campos numéricos
        $group['id'] = (int)$group['id'];
        $group['is_system_group'] = (int)$group['is_system_group'];
        $group['total_members'] = (int)$group['total_members'];
        $group['active_members'] = (int)$group['active_members'];
        $group['companies_represented'] = (int)$group['companies_represented'];
        $group['departments_represented'] = (int)$group['departments_represented'];
        
        // Procesar límites
        $group['download_limit_daily'] = $group['download_limit_daily'] ? (int)$group['download_limit_daily'] : null;
        $group['upload_limit_daily'] = $group['upload_limit_daily'] ? (int)$group['upload_limit_daily'] : null;
        
        // Formatear fechas
        if ($group['created_at']) {
            $group['created_at_formatted'] = date('d/m/Y H:i', strtotime($group['created_at']));
        }
        if ($group['updated_at']) {
            $group['updated_at_formatted'] = date('d/m/Y H:i', strtotime($group['updated_at']));
        }
        
        // Validar JSON de permisos
        if ($group['module_permissions']) {
            $permissions = json_decode($group['module_permissions'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                logDebug("Error JSON en permisos del grupo {$group['id']}: " . json_last_error_msg());
                $group['module_permissions'] = '{}';
            }
        } else {
            $group['module_permissions'] = '{}';
        }
        
        // Validar JSON de restricciones
        if ($group['access_restrictions']) {
            $restrictions = json_decode($group['access_restrictions'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                logDebug("Error JSON en restricciones del grupo {$group['id']}: " . json_last_error_msg());
                $group['access_restrictions'] = '{}';
            }
        } else {
            $group['access_restrictions'] = '{}';
        }
    }
    
    // Obtener estadísticas generales
    $totalGroups = count($groups);
    $activeGroups = count(array_filter($groups, function($g) { return $g['status'] === 'active'; }));
    $systemGroups = count(array_filter($groups, function($g) { return $g['is_system_group'] == 1; }));
    $totalMembers = array_sum(array_column($groups, 'total_members'));
    
    logDebug("Estadísticas: Total=$totalGroups, Activos=$activeGroups, Sistema=$systemGroups, Miembros=$totalMembers");
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'groups' => $groups,
        'stats' => [
            'total_groups' => $totalGroups,
            'active_groups' => $activeGroups,
            'system_groups' => $systemGroups,
            'total_members' => $totalMembers
        ],
        'total' => $totalGroups
    ]);
    
    logDebug("Respuesta enviada exitosamente");
    
} catch (PDOException $e) {
    logDebug("Error PDO: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al obtener grupos',
        'error_code' => 'DB_ERROR',
        'groups' => [],
        'stats' => [
            'total_groups' => 0,
            'active_groups' => 0,
            'system_groups' => 0,
            'total_members' => 0
        ]
    ]);
    
} catch (Exception $e) {
    logDebug("Error general: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GENERAL_ERROR',
        'groups' => [],
        'stats' => [
            'total_groups' => 0,
            'active_groups' => 0,
            'system_groups' => 0,
            'total_members' => 0
        ]
    ]);
}
?>