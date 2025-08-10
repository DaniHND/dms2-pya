<?php
/*
 * modules/groups/actions/get_group_details.php
 * Obtener detalles de un grupo específico - VERSIÓN ULTRA LIMPIA
 */

// Evitar cualquier output

// Limpiar cualquier buffer de salida
while (ob_get_level()) {
    ob_end_clean();
}

// Iniciar buffer limpio
ob_start();

// Usar rutas absolutas
$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/config/session.php';

// Limpiar cualquier output de los includes
ob_clean();

// Configurar headers ANTES de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Verificar autenticación
if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
    exit;
}

// Verificar método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Obtener ID del grupo
    $groupId = $_GET['id'] ?? null;
    
    if (!$groupId || !is_numeric($groupId)) {
        echo json_encode(['success' => false, 'message' => 'ID de grupo inválido']);
        exit;
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Obtener datos del grupo con información del creador
    $groupQuery = "
        SELECT 
            ug.*,
            COALESCE(CONCAT(creator.first_name, ' ', creator.last_name), 'Sistema') as created_by_name
        FROM user_groups ug
        LEFT JOIN users creator ON ug.created_by = creator.id
        WHERE ug.id = ?
    ";
    
    $groupStmt = $pdo->prepare($groupQuery);
    $groupStmt->execute([$groupId]);
    
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Grupo no encontrado']);
        exit;
    }
    
    // Obtener estadísticas del grupo
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT ugm.user_id) as total_members,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN ugm.user_id END) as active_members,
            COUNT(DISTINCT u.company_id) as companies_represented,
            COUNT(DISTINCT u.department_id) as departments_represented
        FROM user_group_members ugm
        LEFT JOIN users u ON ugm.user_id = u.id
        WHERE ugm.group_id = ?
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute([$groupId]);
    
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Si no hay estadísticas, establecer valores por defecto
    if (!$stats) {
        $stats = [
            'total_members' => 0,
            'active_members' => 0,
            'companies_represented' => 0,
            'departments_represented' => 0
        ];
    }
    
    // Obtener lista de miembros del grupo
    $membersQuery = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.status,
            c.name as company_name,
            d.name as department_name,
            ugm.added_at
        FROM user_group_members ugm
        INNER JOIN users u ON ugm.user_id = u.id
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE ugm.group_id = ?
        ORDER BY u.first_name, u.last_name
    ";
    
    $membersStmt = $pdo->prepare($membersQuery);
    $membersStmt->execute([$groupId]);
    
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar permisos y restricciones JSON
    $modulePermissions = [];
    $accessRestrictions = [];
    
    if (!empty($group['module_permissions'])) {
        $modulePermissions = json_decode($group['module_permissions'], true) ?: [];
    }
    
    if (!empty($group['access_restrictions'])) {
        $accessRestrictions = json_decode($group['access_restrictions'], true) ?: [];
    }
    
    // Formatear fechas
    if ($group['created_at']) {
        $group['created_at_formatted'] = date('d/m/Y H:i', strtotime($group['created_at']));
    }
    
    if ($group['updated_at']) {
        $group['updated_at_formatted'] = date('d/m/Y H:i', strtotime($group['updated_at']));
    }
    
    // Formatear miembros
    foreach ($members as &$member) {
        $member['full_name'] = trim($member['first_name'] . ' ' . $member['last_name']);
        if ($member['added_at']) {
            $member['added_at_formatted'] = date('d/m/Y H:i', strtotime($member['added_at']));
        }
    }
    
    // Agregar estadísticas al objeto grupo
    $group['total_members'] = (int)$stats['total_members'];
    $group['active_members'] = (int)$stats['active_members'];
    $group['companies_represented'] = (int)$stats['companies_represented'];
    $group['departments_represented'] = (int)$stats['departments_represented'];
    
    echo json_encode([
        'success' => true,
        'group' => $group,
        'members' => $members,
        'module_permissions' => $modulePermissions,
        'access_restrictions' => $accessRestrictions,
        'total_members' => (int)$stats['total_members'],
        'stats' => $stats
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

// Limpiar y enviar
ob_end_flush();
exit;
?>

// Verificar autenticación
if (!SessionManager::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Permisos insuficientes'
    ]);
    exit;
}

// Verificar método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Obtener ID del grupo
    $groupId = $_GET['id'] ?? null;
    
    if (!$groupId || !is_numeric($groupId)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de grupo inválido'
        ]);
        exit;
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Obtener datos del grupo con información del creador
    $groupQuery = "
        SELECT 
            ug.*,
            COALESCE(CONCAT(creator.first_name, ' ', creator.last_name), 'Sistema') as created_by_name
        FROM user_groups ug
        LEFT JOIN users creator ON ug.created_by = creator.id
        WHERE ug.id = ?
    ";
    
    $groupStmt = $pdo->prepare($groupQuery);
    $groupStmt->execute([$groupId]);
    
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode([
            'success' => false,
            'message' => 'Grupo no encontrado'
        ]);
        exit;
    }
    
    // Obtener estadísticas del grupo
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT ugm.user_id) as total_members,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN ugm.user_id END) as active_members,
            COUNT(DISTINCT u.company_id) as companies_represented,
            COUNT(DISTINCT u.department_id) as departments_represented
        FROM user_group_members ugm
        LEFT JOIN users u ON ugm.user_id = u.id
        WHERE ugm.group_id = ?
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute([$groupId]);
    
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Si no hay estadísticas, establecer valores por defecto
    if (!$stats) {
        $stats = [
            'total_members' => 0,
            'active_members' => 0,
            'companies_represented' => 0,
            'departments_represented' => 0
        ];
    }
    
    // Obtener lista de miembros del grupo
    $membersQuery = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.status,
            c.name as company_name,
            d.name as department_name,
            ugm.added_at
        FROM user_group_members ugm
        INNER JOIN users u ON ugm.user_id = u.id
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE ugm.group_id = ?
        ORDER BY u.first_name, u.last_name
    ";
    
    $membersStmt = $pdo->prepare($membersQuery);
    $membersStmt->execute([$groupId]);
    
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar permisos y restricciones JSON
    $modulePermissions = [];
    $accessRestrictions = [];
    
    if (!empty($group['module_permissions'])) {
        $modulePermissions = json_decode($group['module_permissions'], true) ?: [];
    }
    
    if (!empty($group['access_restrictions'])) {
        $accessRestrictions = json_decode($group['access_restrictions'], true) ?: [];
    }
    
    // Formatear fechas
    if ($group['created_at']) {
        $group['created_at_formatted'] = date('d/m/Y H:i', strtotime($group['created_at']));
    }
    
    if ($group['updated_at']) {
        $group['updated_at_formatted'] = date('d/m/Y H:i', strtotime($group['updated_at']));
    }
    
    // Formatear miembros
    foreach ($members as &$member) {
        $member['full_name'] = trim($member['first_name'] . ' ' . $member['last_name']);
        if ($member['added_at']) {
            $member['added_at_formatted'] = date('d/m/Y H:i', strtotime($member['added_at']));
        }
    }
    
    // Agregar estadísticas al objeto grupo
    $group['total_members'] = (int)$stats['total_members'];
    $group['active_members'] = (int)$stats['active_members'];
    $group['companies_represented'] = (int)$stats['companies_represented'];
    $group['departments_represented'] = (int)$stats['departments_represented'];
    
    echo json_encode([
        'success' => true,
        'group' => $group,
        'members' => $members,
        'module_permissions' => $modulePermissions,
        'access_restrictions' => $accessRestrictions,
        'total_members' => (int)$stats['total_members'],
        'stats' => $stats
    ]);
    
} catch (PDOException $e) {
    error_log('Error PDO en get_group_details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    error_log('Error general en get_group_details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

header('Content-Type: application/json');

// Verificar autenticación
if (!SessionManager::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Permisos insuficientes'
    ]);
    exit;
}

// Verificar método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Obtener ID del grupo
    $groupId = $_GET['id'] ?? null;
    
    if (!$groupId || !is_numeric($groupId)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de grupo inválido'
        ]);
        exit;
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Obtener datos del grupo con información del creador
    $groupQuery = "
        SELECT 
            ug.*,
            COALESCE(CONCAT(creator.first_name, ' ', creator.last_name), 'Sistema') as created_by_name
        FROM user_groups ug
        LEFT JOIN users creator ON ug.created_by = creator.id
        WHERE ug.id = ?
    ";
    
    $groupStmt = $pdo->prepare($groupQuery);
    $groupStmt->execute([$groupId]);
    
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode([
            'success' => false,
            'message' => 'Grupo no encontrado'
        ]);
        exit;
    }
    
    // Obtener estadísticas del grupo
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT ugm.user_id) as total_members,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN ugm.user_id END) as active_members,
            COUNT(DISTINCT u.company_id) as companies_represented,
            COUNT(DISTINCT u.department_id) as departments_represented
        FROM user_group_members ugm
        LEFT JOIN users u ON ugm.user_id = u.id
        WHERE ugm.group_id = ?
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute([$groupId]);
    
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Si no hay estadísticas, establecer valores por defecto
    if (!$stats) {
        $stats = [
            'total_members' => 0,
            'active_members' => 0,
            'companies_represented' => 0,
            'departments_represented' => 0
        ];
    }
    
    // Obtener lista de miembros del grupo
    $membersQuery = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.status,
            c.name as company_name,
            d.name as department_name,
            ugm.added_at
        FROM user_group_members ugm
        INNER JOIN users u ON ugm.user_id = u.id
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE ugm.group_id = ?
        ORDER BY u.first_name, u.last_name
    ";
    
    $membersStmt = $pdo->prepare($membersQuery);
    $membersStmt->execute([$groupId]);
    
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar permisos y restricciones JSON
    $modulePermissions = [];
    $accessRestrictions = [];
    
    if (!empty($group['module_permissions'])) {
        $modulePermissions = json_decode($group['module_permissions'], true) ?: [];
    }
    
    if (!empty($group['access_restrictions'])) {
        $accessRestrictions = json_decode($group['access_restrictions'], true) ?: [];
    }
    
    // Formatear fechas
    if ($group['created_at']) {
        $group['created_at_formatted'] = date('d/m/Y H:i', strtotime($group['created_at']));
    }
    
    if ($group['updated_at']) {
        $group['updated_at_formatted'] = date('d/m/Y H:i', strtotime($group['updated_at']));
    }
    
    // Formatear miembros
    foreach ($members as &$member) {
        $member['full_name'] = trim($member['first_name'] . ' ' . $member['last_name']);
        if ($member['added_at']) {
            $member['added_at_formatted'] = date('d/m/Y H:i', strtotime($member['added_at']));
        }
    }
    
    // Agregar estadísticas al objeto grupo
    $group['total_members'] = (int)$stats['total_members'];
    $group['active_members'] = (int)$stats['active_members'];
    $group['companies_represented'] = (int)$stats['companies_represented'];
    $group['departments_represented'] = (int)$stats['departments_represented'];
    
    echo json_encode([
        'success' => true,
        'group' => $group,
        'members' => $members,
        'module_permissions' => $modulePermissions,
        'access_restrictions' => $accessRestrictions,
        'total_members' => (int)$stats['total_members'],
        'stats' => $stats
    ]);
    
} catch (PDOException $e) {
    error_log('Error PDO en get_group_details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    error_log('Error general en get_group_details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

// Verificar autenticación
if (!SessionManager::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Permisos insuficientes'
    ]);
    exit;
}

// Verificar método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Obtener ID del grupo
    $groupId = $_GET['id'] ?? null;
    
    if (!$groupId || !is_numeric($groupId)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de grupo inválido'
        ]);
        exit;
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Obtener datos del grupo con información del creador
    $groupQuery = "
        SELECT 
            ug.*,
            COALESCE(CONCAT(creator.first_name, ' ', creator.last_name), 'Sistema') as created_by_name
        FROM user_groups ug
        LEFT JOIN users creator ON ug.created_by = creator.id
        WHERE ug.id = :group_id
    ";
    
    $groupStmt = $pdo->prepare($groupQuery);
    $groupStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    $groupStmt->execute();
    
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode([
            'success' => false,
            'message' => 'Grupo no encontrado'
        ]);
        exit;
    }
    
    // Obtener estadísticas del grupo
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT ugm.user_id) as total_members,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN ugm.user_id END) as active_members,
            COUNT(DISTINCT u.company_id) as companies_represented,
            COUNT(DISTINCT u.department_id) as departments_represented
        FROM user_group_members ugm
        LEFT JOIN users u ON ugm.user_id = u.id
        WHERE ugm.group_id = :group_id
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    $statsStmt->execute();
    
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Si no hay estadísticas, establecer valores por defecto
    if (!$stats) {
        $stats = [
            'total_members' => 0,
            'active_members' => 0,
            'companies_represented' => 0,
            'departments_represented' => 0
        ];
    }
    
    // Obtener lista de miembros del grupo
    $membersQuery = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.status,
            c.name as company_name,
            d.name as department_name,
            ugm.added_at
        FROM user_group_members ugm
        INNER JOIN users u ON ugm.user_id = u.id
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE ugm.group_id = :group_id
        ORDER BY u.first_name, u.last_name
    ";
    
    $membersStmt = $pdo->prepare($membersQuery);
    $membersStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    $membersStmt->execute();
    
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar permisos y restricciones JSON
    $modulePermissions = [];
    $accessRestrictions = [];
    
    if (!empty($group['module_permissions'])) {
        $modulePermissions = json_decode($group['module_permissions'], true) ?: [];
    }
    
    if (!empty($group['access_restrictions'])) {
        $accessRestrictions = json_decode($group['access_restrictions'], true) ?: [];
    }
    
    // Formatear fechas
    if ($group['created_at']) {
        $group['created_at_formatted'] = date('d/m/Y H:i', strtotime($group['created_at']));
    }
    
    if ($group['updated_at']) {
        $group['updated_at_formatted'] = date('d/m/Y H:i', strtotime($group['updated_at']));
    }
    
    // Formatear miembros
    foreach ($members as &$member) {
        $member['full_name'] = trim($member['first_name'] . ' ' . $member['last_name']);
        if ($member['added_at']) {
            $member['added_at_formatted'] = date('d/m/Y H:i', strtotime($member['added_at']));
        }
    }
    
    // Agregar estadísticas al objeto grupo
    $group['total_members'] = (int)$stats['total_members'];
    $group['active_members'] = (int)$stats['active_members'];
    $group['companies_represented'] = (int)$stats['companies_represented'];
    $group['departments_represented'] = (int)$stats['departments_represented'];
    
    echo json_encode([
        'success' => true,
        'group' => $group,
        'members' => $members,
        'module_permissions' => $modulePermissions,
        'access_restrictions' => $accessRestrictions,
        'total_members' => (int)$stats['total_members'],
        'stats' => $stats
    ]);
    
} catch (PDOException $e) {
    error_log('Error PDO en get_group_details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al obtener los detalles'
    ]);
    
} catch (Exception $e) {
    error_log('Error general en get_group_details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>

// Verificar autenticación
if (!SessionManager::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Permisos insuficientes'
    ]);
    exit;
}

// Verificar método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Obtener ID del grupo
    $groupId = $_GET['id'] ?? null;
    
    if (!$groupId || !is_numeric($groupId)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de grupo inválido'
        ]);
        exit;
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Obtener datos del grupo con información del creador
    $groupQuery = "
        SELECT 
            ug.*,
            COALESCE(CONCAT(creator.first_name, ' ', creator.last_name), 'Sistema') as created_by_name
        FROM user_groups ug
        LEFT JOIN users creator ON ug.created_by = creator.id
        WHERE ug.id = :group_id
    ";
    
    $groupStmt = $pdo->prepare($groupQuery);
    $groupStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    $groupStmt->execute();
    
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode([
            'success' => false,
            'message' => 'Grupo no encontrado'
        ]);
        exit;
    }
    
    // Obtener estadísticas del grupo
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT ugm.user_id) as total_members,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN ugm.user_id END) as active_members,
            COUNT(DISTINCT u.company_id) as companies_represented,
            COUNT(DISTINCT u.department_id) as departments_represented
        FROM user_group_members ugm
        LEFT JOIN users u ON ugm.user_id = u.id
        WHERE ugm.group_id = :group_id
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    $statsStmt->execute();
    
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Si no hay estadísticas, establecer valores por defecto
    if (!$stats) {
        $stats = [
            'total_members' => 0,
            'active_members' => 0,
            'companies_represented' => 0,
            'departments_represented' => 0
        ];
    }
    
    // Obtener lista de miembros del grupo
    $membersQuery = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.status,
            c.name as company_name,
            d.name as department_name,
            ugm.added_at
        FROM user_group_members ugm
        INNER JOIN users u ON ugm.user_id = u.id
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE ugm.group_id = :group_id
        ORDER BY u.first_name, u.last_name
    ";
    
    $membersStmt = $pdo->prepare($membersQuery);
    $membersStmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
    $membersStmt->execute();
    
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar permisos y restricciones JSON
    $modulePermissions = [];
    $accessRestrictions = [];
    
    if (!empty($group['module_permissions'])) {
        $modulePermissions = json_decode($group['module_permissions'], true) ?: [];
    }
    
    if (!empty($group['access_restrictions'])) {
        $accessRestrictions = json_decode($group['access_restrictions'], true) ?: [];
    }
    
    // Formatear fechas
    if ($group['created_at']) {
        $group['created_at_formatted'] = date('d/m/Y H:i', strtotime($group['created_at']));
    }
    
    if ($group['updated_at']) {
        $group['updated_at_formatted'] = date('d/m/Y H:i', strtotime($group['updated_at']));
    }
    
    // Formatear miembros
    foreach ($members as &$member) {
        $member['full_name'] = trim($member['first_name'] . ' ' . $member['last_name']);
        if ($member['added_at']) {
            $member['added_at_formatted'] = date('d/m/Y H:i', strtotime($member['added_at']));
        }
    }
    
    // Agregar estadísticas al objeto grupo
    $group['total_members'] = (int)$stats['total_members'];
    $group['active_members'] = (int)$stats['active_members'];
    $group['companies_represented'] = (int)$stats['companies_represented'];
    $group['departments_represented'] = (int)$stats['departments_represented'];
    
    echo json_encode([
        'success' => true,
        'group' => $group,
        'members' => $members,
        'module_permissions' => $modulePermissions,
        'access_restrictions' => $accessRestrictions,
        'total_members' => (int)$stats['total_members'],
        'stats' => $stats
    ]);
    
} catch (PDOException $e) {
    error_log('Error PDO en get_group_details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al obtener los detalles'
    ]);
    
} catch (Exception $e) {
    error_log('Error general en get_group_details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>