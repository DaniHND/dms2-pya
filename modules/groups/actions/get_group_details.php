<?php
/*
 * modules/groups/actions/get_group_details.php
 * Acción para obtener detalles completos de un grupo
 */

require_once '../../../config/session.php';
require_once '../../../config/database.php';

// Configurar respuesta JSON
header('Content-Type: application/json');

try {
    // Verificar sesión y permisos
    SessionManager::requireRole('admin');
    $currentUser = SessionManager::getCurrentUser();
    
    // Verificar método GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener parámetros
    $groupId = $_GET['id'] ?? null;
    $fullDetails = isset($_GET['full']) && $_GET['full'] == '1';
    
    if (empty($groupId) || !is_numeric($groupId)) {
        throw new Exception('ID de grupo inválido');
    }
    
    // Obtener conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Obtener datos básicos del grupo
    $groupQuery = "SELECT 
                    ug.*,
                    CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name
                   FROM user_groups ug
                   LEFT JOIN users creator ON ug.created_by = creator.id
                   WHERE ug.id = ?";
    
    $stmt = $pdo->prepare($groupQuery);
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        throw new Exception('Grupo no encontrado');
    }
    
    // Decodificar JSON fields
    $group['permissions'] = json_decode($group['module_permissions'], true) ?: [];
    $group['restrictions'] = json_decode($group['access_restrictions'], true) ?: [];
    
    // Obtener estadísticas básicas del grupo
    $statsQuery = "SELECT 
                    COUNT(DISTINCT ugm.user_id) as total_members,
                    COUNT(DISTINCT CASE WHEN u.status = 'active' THEN ugm.user_id END) as active_members,
                    COUNT(DISTINCT u.company_id) as companies_represented,
                    COUNT(DISTINCT u.department_id) as departments_represented
                   FROM user_group_members ugm
                   LEFT JOIN users u ON ugm.user_id = u.id AND u.status != 'deleted'
                   WHERE ugm.group_id = ?";
    
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute([$groupId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Agregar estadísticas al grupo
    $group['stats'] = $stats;
    
    $response = [
        'success' => true,
        'group' => $group
    ];
    
    // Si se solicitan detalles completos, obtener miembros
    if ($fullDetails) {
        // Obtener miembros del grupo con información completa
        $membersQuery = "SELECT 
                            u.id,
                            u.username,
                            u.first_name,
                            u.last_name,
                            u.email,
                            u.status,
                            u.role,
                            c.name as company_name,
                            d.name as department_name,
                            ugm.assigned_at,
                            CONCAT(assigner.first_name, ' ', assigner.last_name) as assigned_by_name
                        FROM user_group_members ugm
                        JOIN users u ON ugm.user_id = u.id
                        LEFT JOIN companies c ON u.company_id = c.id
                        LEFT JOIN departments d ON u.department_id = d.id
                        LEFT JOIN users assigner ON ugm.assigned_by = assigner.id
                        WHERE ugm.group_id = ? AND u.status != 'deleted'
                        ORDER BY u.first_name, u.last_name";
        
        $stmt = $pdo->prepare($membersQuery);
        $stmt->execute([$groupId]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['members'] = $members;
        
        // Obtener información adicional sobre restricciones
        if (!empty($group['restrictions'])) {
            $restrictionDetails = [];
            
            // Detalles de empresas restringidas
            if (isset($group['restrictions']['companies']) && is_array($group['restrictions']['companies'])) {
                $companyIds = implode(',', array_map('intval', $group['restrictions']['companies']));
                $companyQuery = "SELECT id, name FROM companies WHERE id IN ($companyIds) ORDER BY name";
                $companies = $pdo->query($companyQuery)->fetchAll(PDO::FETCH_ASSOC);
                $restrictionDetails['companies'] = $companies;
            }
            
            // Detalles de departamentos restringidos
            if (isset($group['restrictions']['departments']) && is_array($group['restrictions']['departments'])) {
                $deptIds = implode(',', array_map('intval', $group['restrictions']['departments']));
                $deptQuery = "SELECT id, name FROM departments WHERE id IN ($deptIds) ORDER BY name";
                $departments = $pdo->query($deptQuery)->fetchAll(PDO::FETCH_ASSOC);
                $restrictionDetails['departments'] = $departments;
            }
            
            // Detalles de tipos de documentos restringidos
            if (isset($group['restrictions']['document_types']) && is_array($group['restrictions']['document_types'])) {
                $typeIds = implode(',', array_map('intval', $group['restrictions']['document_types']));
                $typeQuery = "SELECT id, name FROM document_types WHERE id IN ($typeIds) ORDER BY name";
                $documentTypes = $pdo->query($typeQuery)->fetchAll(PDO::FETCH_ASSOC);
                $restrictionDetails['document_types'] = $documentTypes;
            }
            
            $response['restriction_details'] = $restrictionDetails;
        }
        
        // Obtener actividad reciente relacionada con el grupo
        $activityQuery = "SELECT 
                            al.*,
                            CONCAT(u.first_name, ' ', u.last_name) as user_name
                          FROM activity_logs al
                          JOIN users u ON al.user_id = u.id
                          WHERE al.module = 'groups' 
                          AND (al.details LIKE ? OR al.details LIKE ?)
                          ORDER BY al.created_at DESC
                          LIMIT 10";
        
        $stmt = $pdo->prepare($activityQuery);
        $stmt->execute([
            "%grupo ID $groupId%",
            "%grupo '{$group['name']}'%"
        ]);
        $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['recent_activity'] = $recentActivity;
    }
    
    // Respuesta exitosa
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en get_group_details.php: " . $e->getMessage());
    
    // Respuesta de error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GROUP_DETAILS_ERROR'
    ]);
}
?>