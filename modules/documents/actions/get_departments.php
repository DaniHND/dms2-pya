<?php
/**
* modules/documents/actions/get_departments.php
* Obtener departamentos filtrados por permisos de grupos
*/

require_once '../../../config/session.php';
require_once '../../../config/database.php';
require_once '../../../includes/group_permissions.php';

header('Content-Type: application/json');

// Verificar sesión
if (!SessionManager::isLoggedIn()) {
   http_response_code(401);
   echo json_encode(['success' => false, 'message' => 'No autorizado']);
   exit;
}

$currentUser = SessionManager::getCurrentUser();

// Verificar permisos (con compatibilidad para admins)
$userPermissions = getUserGroupPermissions($currentUser['id']);
if (!$userPermissions['has_groups']) {
   // Permitir a admins sin grupos (compatibilidad temporal)
   if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'super_admin') {
       http_response_code(403);
       echo json_encode(['success' => false, 'message' => 'Usuario sin grupos de acceso']);
       exit;
   }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   http_response_code(405);
   echo json_encode(['success' => false, 'message' => 'Método no permitido']);
   exit;
}

// Leer datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['company_id'])) {
   http_response_code(400);
   echo json_encode(['success' => false, 'message' => 'ID de empresa requerido']);
   exit;
}

$companyId = (int)$data['company_id'];

// Verificar que el usuario tenga acceso a la empresa (solo si tiene grupos)
if ($userPermissions['has_groups'] && !canUserAccessCompany($currentUser['id'], $companyId)) {
   http_response_code(403);
   echo json_encode(['success' => false, 'message' => 'Sin acceso a esta empresa']);
   exit;
}

try {
   if ($userPermissions['has_groups']) {
       // Usuario con grupos - aplicar restricciones
       $departments = getUserAllowedDepartments($currentUser['id'], $companyId);
   } else {
       // Admin sin grupos - acceso completo (temporal)
       $database = new Database();
       $pdo = $database->getConnection();
       
       $query = "SELECT id, name FROM departments WHERE company_id = ? AND status = 'active' ORDER BY name";
       $stmt = $pdo->prepare($query);
       $stmt->execute([$companyId]);
       $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
   }
   
   echo json_encode([
       'success' => true,
       'departments' => $departments,
       'total' => count($departments)
   ]);
   
} catch (Exception $e) {
   error_log("Error en get_departments.php: " . $e->getMessage());
   echo json_encode([
       'success' => false, 
       'message' => 'Error obteniendo departamentos'
   ]);
}
?>