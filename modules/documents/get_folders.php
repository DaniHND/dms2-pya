<?php
/*
 * get_folders.php
 * API para obtener carpetas de un departamento
 */

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/database.php';

try {
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();
    
    $companyId = intval($_GET['company_id'] ?? 0);
    $departmentId = intval($_GET['department_id'] ?? 0);
    
    if ($companyId <= 0 || $departmentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'IDs inválidos']);
        exit;
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar que el departamento pertenece a la empresa
    $deptQuery = "SELECT id FROM departments WHERE id = ? AND company_id = ? AND status = 'active'";
    $deptStmt = $pdo->prepare($deptQuery);
    $deptStmt->execute([$departmentId, $companyId]);
    
    if (!$deptStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Departamento no encontrado']);
        exit;
    }
    
    // Verificar permisos de acceso
    if ($currentUser['role'] !== 'admin') {
        $accessQuery = "
            SELECT ug.access_restrictions 
            FROM user_groups ug
            INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
            WHERE ugm.user_id = ? AND ug.status = 'active'
        ";
        $accessStmt = $pdo->prepare($accessQuery);
        $accessStmt->execute([$currentUser['id']]);
        $restrictions = $accessStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hasAccess = false;
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
        
        if (!$hasAccess) {
            echo json_encode(['success' => false, 'message' => 'No tienes acceso a este departamento']);
            exit;
        }
    }
    
    // Obtener carpetas
    $query = "
        SELECT id, name, description, folder_color, folder_icon
        FROM document_folders 
        WHERE company_id = ? AND department_id = ? AND is_active = 1
        ORDER BY name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$companyId, $departmentId]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'folders' => $folders
    ]);
    
} catch (Exception $e) {
    error_log("Error getting folders: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>