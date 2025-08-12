<?php
// modules/companies/actions/toggle_company_status.php
// Cambiar estado de empresa - DMS2

require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

// Verificar sesión y permisos
try {
    SessionManager::requireLogin();
    SessionManager::requireRole('admin');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $companyId = intval($_POST['company_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    
    if ($companyId <= 0) {
        throw new Exception('ID de empresa inválido');
    }
    
    if (!in_array($newStatus, ['active', 'inactive'])) {
        throw new Exception('Estado inválido');
    }
    
    // Verificar conexión a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Verificar que la empresa existe
    $checkQuery = "SELECT id, name, status FROM companies WHERE id = ? AND status != 'deleted'";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        throw new Exception('Empresa no encontrada');
    }
    
    // Verificar si hay usuarios activos en la empresa antes de desactivarla
    if ($newStatus === 'inactive') {
        $activeUsersQuery = "SELECT COUNT(*) as count FROM users WHERE company_id = ? AND status = 'active'";
        $stmt = $pdo->prepare($activeUsersQuery);
        $stmt->execute([$companyId]);
        $activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($activeUsers > 0) {
            throw new Exception("No se puede desactivar la empresa porque tiene {$activeUsers} usuario(s) activo(s). Desactive primero los usuarios.");
        }
    }
    
    // Actualizar estado
    $updateQuery = "UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($updateQuery);
    $success = $stmt->execute([$newStatus, $companyId]);
    
    if ($success) {
        // Registrar actividad
        $currentUser = SessionManager::getCurrentUser();
        logActivity(
            $currentUser['id'], 
            'company_status_changed', 
            'companies', 
            $companyId, 
            "Estado de la empresa '{$company['name']}' cambiado de {$company['status']} a {$newStatus}"
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Estado de la empresa actualizado correctamente',
            'new_status' => $newStatus
        ]);
    } else {
        throw new Exception('Error al actualizar el estado de la empresa');
    }
    
} catch (Exception $e) {
    error_log("Error toggling company status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>