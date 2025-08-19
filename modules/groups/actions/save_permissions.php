<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../../../config/session.php';
    require_once '../../../config/database.php';
    require_once '../../../includes/group_permissions.php';

    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();

    if (!$currentUser || ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'super_admin')) {
        echo json_encode(['success' => false, 'message' => 'Sin permisos de administrador']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    $groupId = (int)($_POST['group_id'] ?? 0);
    $permissions = $_POST['permissions'] ?? [];

    if (!$groupId) {
        echo json_encode(['success' => false, 'message' => 'ID de grupo requerido']);
        exit;
    }

    // Usar la función corregida
    saveGroupPermissions($groupId, $permissions);

    echo json_encode([
        'success' => true, 
        'message' => 'Permisos guardados exitosamente'
    ]);

} catch (Exception $e) {
    error_log("Error al guardar permisos: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno: ' . $e->getMessage()
    ]);
}
?>