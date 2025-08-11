<?php
/*
 * get_folders.php
 * API para obtener carpetas de un departamento
 * Versión mejorada con soporte completo para grupos de usuarios
 */

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/group_permissions.php'; // AGREGADO

try {
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();
    
    // Soporte para ambos métodos: GET (compatibilidad) y POST (nuevo)
    $companyId = 0;
    $departmentId = 0;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Método nuevo: POST con JSON
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $companyId = intval($data['company_id'] ?? 0);
        $departmentId = intval($data['department_id'] ?? 0);
    } else {
        // Método original: GET (para compatibilidad con otros módulos)
        $companyId = intval($_GET['company_id'] ?? 0);
        $departmentId = intval($_GET['department_id'] ?? 0);
    }
    
    if ($departmentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de departamento requerido']);
        exit;
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Si no se proporciona company_id, obtenerlo del departamento
    if ($companyId <= 0) {
        $companyQuery = "SELECT company_id FROM departments WHERE id = ? AND status = 'active'";
        $companyStmt = $pdo->prepare($companyQuery);
        $companyStmt->execute([$departmentId]);
        $deptInfo = $companyStmt->fetch();
        
        if (!$deptInfo) {
            echo json_encode(['success' => false, 'message' => 'Departamento no encontrado']);
            exit;
        }
        
        $companyId = $deptInfo['company_id'];
    } else {
        // Verificar que el departamento pertenece a la empresa
        $deptQuery = "SELECT id FROM departments WHERE id = ? AND company_id = ? AND status = 'active'";
        $deptStmt = $pdo->prepare($deptQuery);
        $deptStmt->execute([$departmentId, $companyId]);
        
        if (!$deptStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Departamento no encontrado']);
            exit;
        }
    }
    
    // NUEVA LÓGICA DE PERMISOS CON PRIORIDAD DE GRUPOS
    $hasAccess = false;
    
    // Verificar permisos usando el sistema de grupos (prioridad)
    try {
        $userPermissions = getUserGroupPermissions($currentUser['id']);
        
        if ($userPermissions['has_groups']) {
            // Usuario tiene grupos - usar sistema de grupos
            $hasAccess = canUserAccessCompany($currentUser['id'], $companyId) && 
                        canUserAccessDepartment($currentUser['id'], $departmentId);
        } else {
            // Usuario sin grupos - usar lógica fallback (compatibilidad)
            if ($currentUser['role'] === 'admin') {
                $hasAccess = true;
            } else {
                // Verificar si es su empresa
                $userQuery = "SELECT company_id FROM users WHERE id = ?";
                $userStmt = $pdo->prepare($userQuery);
                $userStmt->execute([$currentUser['id']]);
                $userInfo = $userStmt->fetch();
                
                if ($userInfo && $userInfo['company_id'] == $companyId) {
                    $hasAccess = true;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error checking group permissions: " . $e->getMessage());
        
        // Fallback a la lógica original en caso de error
        if ($currentUser['role'] === 'admin') {
            $hasAccess = true;
        } else {
            // Lógica original de verificación manual de grupos
            $accessQuery = "
                SELECT ug.access_restrictions 
                FROM user_groups ug
                INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
                WHERE ugm.user_id = ? AND ug.status = 'active'
            ";
            $accessStmt = $pdo->prepare($accessQuery);
            $accessStmt->execute([$currentUser['id']]);
            $restrictions = $accessStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($restrictions as $restriction) {
                $accessData = json_decode($restriction['access_restrictions'] ?: '{}', true);
                $allowedCompanies = $accessData['companies'] ?? [];
                $allowedDepartments = $accessData['departments'] ?? [];
                
                $companyOk = empty($allowedCompanies) || in_array($companyId, $allowedCompanies);
                $deptOk = empty($allowedDepartments) || in_array($departmentId, $allowedDepartments);
                
                if ($companyOk && $deptOk) {
                    $hasAccess = true;
                    break;
                }
            }
            
            if (!$hasAccess) {
                $userQuery = "SELECT company_id FROM users WHERE id = ?";
                $userStmt = $pdo->prepare($userQuery);
                $userStmt->execute([$currentUser['id']]);
                $userInfo = $userStmt->fetch();
                
                if ($userInfo && $userInfo['company_id'] == $companyId) {
                    $hasAccess = true;
                }
            }
        }
    }
    
    if (!$hasAccess) {
        echo json_encode(['success' => false, 'message' => 'Sin acceso a este departamento']);
        exit;
    }
    
    // Obtener carpetas con información adicional
    $query = "
        SELECT 
            f.id, 
            f.name, 
            f.description, 
            f.folder_color, 
            f.folder_icon,
            COUNT(d.id) as document_count
        FROM document_folders f
        LEFT JOIN documents d ON f.id = d.folder_id AND d.status = 'active'
        WHERE f.company_id = ? AND f.department_id = ? AND f.is_active = 1
        GROUP BY f.id, f.name, f.description, f.folder_color, f.folder_icon
        ORDER BY f.name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$companyId, $departmentId]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'folders' => $folders,
        'total' => count($folders) // Agregado para compatibilidad
    ]);
    
} catch (Exception $e) {
    error_log("Error getting folders: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>