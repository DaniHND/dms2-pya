<?php
/*
 * get_departments.php
 * API para obtener departamentos de una empresa
 */

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/database.php';

try {
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();
    
    $companyId = intval($_GET['company_id'] ?? 0);
    
    if ($companyId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de empresa inválido']);
        exit;
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar permisos de acceso a la empresa
    if ($currentUser['role'] !== 'admin') {
        // Verificar si el usuario tiene acceso a esta empresa
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
            
            if (empty($allowedCompanies) || in_array($companyId, $allowedCompanies)) {
                $hasAccess = true;
                break;
            }
        }
        
        // Si no tiene acceso por grupos, verificar si es su empresa
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
            echo json_encode(['success' => false, 'message' => 'No tienes acceso a esta empresa']);
            exit;
        }
    }
    
    // Obtener departamentos
    $query = "
        SELECT id, name, description
        FROM departments 
        WHERE company_id = ? AND status = 'active'
        ORDER BY name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$companyId]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'departments' => $departments
    ]);
    
} catch (Exception $e) {
    error_log("Error getting departments: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>