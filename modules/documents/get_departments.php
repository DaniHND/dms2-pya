<?php
/**
 * modules/documents/get_departments.php
 * AJAX endpoint para cargar departamentos por empresa - VERSIÓN CORREGIDA
 */

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/group_permissions.php';

header('Content-Type: application/json');

// Verificar sesión
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$companyId = intval($_POST['company_id'] ?? 0);

if (!$companyId) {
    echo json_encode(['success' => false, 'message' => 'ID de empresa requerido']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // ===== VERIFICAR PERMISOS =====
    $isAdmin = ($currentUser['role'] === 'admin' || $currentUser['role'] === 'super_admin');
    
    error_log("=== GET_DEPARTMENTS DEBUG ===");
    error_log("Usuario: " . $currentUser['username'] . " (ID: " . $currentUser['id'] . ")");
    error_log("Rol: " . $currentUser['role']);
    error_log("Es Admin: " . ($isAdmin ? 'SI' : 'NO'));
    error_log("Company ID solicitado: $companyId");

    // ===== OBTENER DEPARTAMENTOS =====
    $departments = [];

    if ($isAdmin) {
        // ADMINISTRADORES: VER TODOS LOS DEPARTAMENTOS
        $query = "SELECT id, name, description FROM departments WHERE company_id = ? AND status = 'active' ORDER BY name";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$companyId]);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Admin - Departamentos encontrados: " . count($departments));
    } else {
        // USUARIOS NORMALES: VERIFICAR PERMISOS DE GRUPO
        $userPermissions = getUserGroupPermissions($currentUser['id']);
        
        error_log("Permisos de usuario: " . json_encode($userPermissions));
        
        if (!$userPermissions['has_groups']) {
            error_log("Usuario sin grupos - sin acceso");
            echo json_encode(['success' => true, 'departments' => []]);
            exit;
        }

        // Verificar acceso a la empresa
        if (!canUserAccessCompany($currentUser['id'], $companyId)) {
            error_log("Usuario sin acceso a empresa $companyId");
            echo json_encode(['success' => false, 'message' => 'Sin acceso a esta empresa']);
            exit;
        }

        // Obtener departamentos permitidos
        $allowedDepartments = $userPermissions['restrictions']['departments'] ?? [];
        
        if (empty($allowedDepartments)) {
            error_log("Usuario sin restricciones de departamentos configuradas");
            echo json_encode(['success' => true, 'departments' => []]);
            exit;
        }

        // Filtrar departamentos por empresa Y por permisos
        $placeholders = str_repeat('?,', count($allowedDepartments) - 1) . '?';
        $query = "SELECT id, name, description FROM departments 
                  WHERE id IN ($placeholders) AND company_id = ? AND status = 'active' 
                  ORDER BY name";
        
        $params = array_merge($allowedDepartments, [$companyId]);
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Usuario con grupos - Departamentos permitidos en empresa $companyId: " . count($departments));
    }

    // ===== RESPUESTA =====
    echo json_encode([
        'success' => true,
        'departments' => $departments,
        'debug' => [
            'user_id' => $currentUser['id'],
            'is_admin' => $isAdmin,
            'company_id' => $companyId,
            'departments_count' => count($departments)
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en get_departments.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>