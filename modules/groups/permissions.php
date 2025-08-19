<?php
require_once '../../bootstrap.php';
/*
 * modules/groups/permissions.php
 * Sistema de permisos - mejorado con pestañas modernas y funcionalidad automática
 */

$projectRoot = dirname(dirname(__DIR__));
require_once $projectRoot . '/config/session.php';
require_once $projectRoot . '/config/database.php';

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    header('Location: ../../dashboard.php');
    exit;
}

$groupId = isset($_GET['group']) ? (int)$_GET['group'] : 0;
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'members';

if (!$groupId) {
    header('Location: index.php');
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Obtener información del grupo
    $groupQuery = "SELECT * FROM user_groups WHERE id = ?";
    $stmt = $pdo->prepare($groupQuery);
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        header('Location: index.php');
        exit;
    }

    // Decodificar permisos y restricciones
    $permissions = json_decode($group['module_permissions'] ?: '{}', true);
    $restrictions = json_decode($group['access_restrictions'] ?: '{}', true);

    // Normalizar permisos para las 6 opciones específicas (AGREGADO move_files)
    $defaultPermissions = [
        'upload_files' => false,
        'view_files' => false,
        'create_folders' => false,
        'download_files' => false,
        'delete_files' => false,
        'move_files' => false
    ];

    foreach ($defaultPermissions as $key => $defaultValue) {
        if (!isset($permissions[$key])) {
            $permissions[$key] = $defaultValue;
        }
    }

    // Obtener miembros actuales
    $membersQuery = "
        SELECT u.id, u.username, u.first_name, u.last_name, u.email,
               c.name as company_name, d.name as department_name, ugm.added_at
        FROM user_group_members ugm
        INNER JOIN users u ON ugm.user_id = u.id
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE ugm.group_id = ?
        ORDER BY u.first_name, u.last_name
    ";

    $stmt = $pdo->prepare($membersQuery);
    $stmt->execute([$groupId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener usuarios disponibles para agregar
    $availableUsersQuery = "
        SELECT u.id, u.username, u.first_name, u.last_name, u.email,
               c.name as company_name, d.name as department_name
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.status = 'active' 
        AND u.id NOT IN (
            SELECT user_id FROM user_group_members WHERE group_id = ?
        )
        ORDER BY u.first_name, u.last_name
    ";

    $stmt = $pdo->prepare($availableUsersQuery);
    $stmt->execute([$groupId]);
    $availableUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener datos para restricciones
    $companiesQuery = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
    $stmt = $pdo->prepare($companiesQuery);
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $departmentsQuery = "
        SELECT d.id, d.name, c.name as company_name 
        FROM departments d 
        INNER JOIN companies c ON d.company_id = c.id 
        WHERE d.status = 'active' AND c.status = 'active'
        ORDER BY c.name, d.name
    ";
    $stmt = $pdo->prepare($departmentsQuery);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $docTypesQuery = "SELECT id, name, description FROM document_types WHERE status = 'active' ORDER BY name";
    $stmt = $pdo->prepare($docTypesQuery);
    $stmt->execute();
    $documentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error en permissions.php: " . $e->getMessage());
    header('Location: index.php?error=database');
    exit;
}

function isRestricted($type, $id, $restrictions)
{
    return isset($restrictions[$type]) && is_array($restrictions[$type]) && in_array((int)$id, $restrictions[$type]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permisos del Grupo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/dashboard.css" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
        /* ======= ESTILOS PRINCIPALES ======= */
        .permissions-container {
            padding: 0;
            margin-left: 0;
        }
        
        /* Header */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 24px;
            height: 80px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .header-left h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .header-info {
            text-align: right;
        }
        
        .user-name-header {
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .current-time {
            font-size: 12px;
            color: #64748b;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-icon {
            background: transparent;
            border: none;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-icon:hover {
            background: #f1f5f9;
            color: #374151;
        }
        
        .logout-btn {
            color: #ef4444;
        }
        
        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        .container {
            padding: 0 24px;
            max-width: none;
        }
        
        /* Información del grupo */
        .group-info-section {
            margin-bottom: 32px;
        }
        
        .group-info-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }
        
        .group-info-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 32px;
        }
        
        .group-info-left h2 {
            margin: 0 0 8px 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .group-description {
            margin: 0;
            color: #64748b;
            font-size: 0.95rem;
        }
        
        .group-stats {
            display: flex;
            gap: 32px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-label {
            display: block;
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 4px;
        }
        
        /* ======= PESTAÑAS MODERNAS ======= */
        .modern-tabs {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .tabs-header {
            display: flex;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-bottom: 1px solid #e2e8f0;
            padding: 0;
        }
        
        .tab-button {
            flex: 1;
            background: none;
            border: none;
            padding: 20px 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.95rem;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            text-decoration: none;
        }
        
        .tab-button:hover {
            background: rgba(59, 130, 246, 0.08);
            color: #3b82f6;
        }
        
        .tab-button.active {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: white;
        }
        
        .tab-badge {
            background: rgba(255, 255, 255, 0.2);
            color: inherit;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        .tab-button:not(.active) .tab-badge {
            background: #e2e8f0;
            color: #64748b;
        }
        
        .tab-content-wrapper {
            padding: 32px;
        }
        
        /* ======= ESTILOS PARA MIEMBROS ======= */
        .members-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        
        .members-section {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }
        
        .members-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-bottom: 1px solid #e2e8f0;
        }
        
        .members-header h5 {
            margin: 0;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .members-body {
            padding: 24px;
        }
        
        .search-box {
            margin-bottom: 20px;
        }
        
        .search-box input {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 12px 16px;
            font-size: 0.95rem;
        }
        
        .search-box input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .users-list {
            max-height: 450px;
            overflow-y: auto;
            padding-right: 8px;
        }
        
        .users-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .users-list::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        .users-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .user-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 12px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .user-item:hover {
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
        }
        
        .user-item.selected {
            border-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
        }
        
        .user-item-content {
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-checkbox {
            width: 18px;
            height: 18px;
            border-radius: 4px;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .user-details {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.4;
        }
        
        .user-company {
            font-size: 0.8rem;
            color: #3b82f6;
            font-weight: 500;
        }
        
        .member-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 12px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .member-item.pending-member {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
        }
        
        .member-info {
            flex: 1;
        }
        
        .remove-member-btn {
            background: none;
            border: 1px solid #ef4444;
            color: #ef4444;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .remove-member-btn:hover {
            background: #ef4444;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .save-members-section {
            margin-top: 24px;
            text-align: center;
        }
        
        .btn-save-members {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-save-members:hover {
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transform: translateY(-1px);
        }
        
        /* ======= PERMISOS Y RESTRICCIONES ======= */
        .permission-card {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .permission-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .permission-card.active {
            background: linear-gradient(135deg, #d4edda, #f8fff9);
            border-color: #28a745;
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.2);
        }
        
        .permission-card .icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: #6c757d;
            transition: color 0.3s ease;
        }
        
        .permission-card.active .icon {
            color: #28a745;
        }
        
        .permission-toggle {
            transform: scale(1.4);
        }
        
        .restriction-section {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #007bff;
        }
        
        .security-alert {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: none;
            border-left: 5px solid #ffc107;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(255,193,7,0.2);
        }
        
        .restriction-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .restriction-item {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.2s ease;
        }
        
        .restriction-item:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0,123,255,0.1);
        }
        
        .restriction-item.selected {
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            border-color: #007bff;
        }
        
        .company-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0 1rem 0;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,123,255,0.2);
        }
        
        .department-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }
        
        .department-item {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 0.8rem;
            transition: all 0.2s ease;
        }
        
        .department-item:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }
        
        .document-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .document-type-item {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.2rem;
            transition: all 0.2s ease;
        }
        
        .document-type-item:hover {
            border-color: #28a745;
            box-shadow: 0 2px 8px rgba(40,167,69,0.1);
        }
        
        .document-type-item.selected {
            background: linear-gradient(135deg, #d4edda, #f8fff9);
            border-color: #28a745;
        }
        
        .stats-counter {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.5rem;
        }
        
        .section-divider {
            height: 2px;
            background: linear-gradient(90deg, #007bff, transparent);
            margin: 2rem 0;
            border-radius: 1px;
        }
    </style>
</head>

<body class="dashboard-layout">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Sistema de Permisos de Seguridad</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" onclick="showComingSoon('Configuración')">
                        <i data-feather="settings"></i>
                    </button>
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido del módulo -->
        <div class="container permissions-container">
            <!-- Información del grupo -->
            <div class="group-info-section">
                <div class="group-info-card">
                    <div class="group-info-content">
                        <div class="group-info-left">
                            <h2><i data-feather="users"></i> <?= htmlspecialchars($group['name']) ?></h2>
                            <p class="group-description"><?= htmlspecialchars($group['description']) ?></p>
                        </div>
                        <div class="group-info-right">
                            <div class="group-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?= count($members) ?></span>
                                    <span class="stat-label">Miembros</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= $group['status'] === 'active' ? 'Activo' : 'Inactivo' ?></span>
                                    <span class="stat-label">Estado</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= date('d/m/Y', strtotime($group['created_at'])) ?></span>
                                    <span class="stat-label">Creado</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas modernas -->
            <div class="modern-tabs">
                <div class="tabs-header">
                    <a href="?group=<?= $groupId ?>&tab=members" class="tab-button <?= $activeTab === 'members' ? 'active' : '' ?>">
                        <i data-feather="users"></i>
                        <span>Miembros</span>
                        <span class="tab-badge"><?= count($members) ?></span>
                    </a>
                    <a href="?group=<?= $groupId ?>&tab=permissions" class="tab-button <?= $activeTab === 'permissions' ? 'active' : '' ?>">
                        <i data-feather="key"></i>
                        <span>Permisos de Acción</span>
                        <span class="tab-badge">5</span>
                    </a>
                    <a href="?group=<?= $groupId ?>&tab=restrictions" class="tab-button <?= $activeTab === 'restrictions' ? 'active' : '' ?>">
                        <i data-feather="shield"></i>
                        <span>Restricciones</span>
                        <span class="tab-badge">3</span>
                    </a>
                </div>

                <div class="tab-content-wrapper">
                    <!-- Tab de Miembros -->
                   <?php if ($activeTab === 'members'): ?>
    <div class="members-grid">
        <!-- Usuarios Disponibles para Agregar -->
        <div class="members-section">
            <div class="members-header">
                <h5>
                    <i data-feather="user-plus"></i>
                    Usuarios Disponibles
                    <span class="tab-badge"><?= count($availableUsers) ?></span>
                </h5>
            </div>
            <div class="members-body">
                <div class="search-box">
                    <input type="text" class="form-control" id="searchUsers" placeholder="Buscar usuarios disponibles...">
                </div>
                <div id="usersList" class="users-list">
                    <?php if (empty($availableUsers)): ?>
                        <div class="empty-state">
                            <i data-feather="users"></i>
                            <p>No hay usuarios disponibles para agregar</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($availableUsers as $user): ?>
                            <div class="user-item" data-user-id="<?= $user['id'] ?>" onclick="toggleUserSelection(<?= $user['id'] ?>)">
                                <div class="user-item-content">
                                    <input type="checkbox" class="user-checkbox" id="user_<?= $user['id'] ?>" onchange="handleUserSelection(this, <?= $user['id'] ?>)">
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                        <div class="user-details">
                                            @<?= htmlspecialchars($user['username']) ?> • <?= htmlspecialchars($user['email']) ?>
                                        </div>
                                        <?php if ($user['company_name']): ?>
                                            <div class="user-company"><?= htmlspecialchars($user['company_name']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Miembros Actuales del Grupo -->
        <div class="members-section">
            <div class="members-header">
                <h5>
                    <i data-feather="users"></i>
                    Miembros Actuales
                    <span class="tab-badge" id="membersCount"><?= count($members) ?></span>
                </h5>
            </div>
            <div class="members-body">
                <div class="search-box">
                    <input type="text" class="form-control" id="searchMembers" placeholder="Buscar miembros actuales...">
                </div>
                <div id="membersList" class="users-list">
                    <?php if (empty($members)): ?>
                        <div class="empty-state">
                            <i data-feather="user-x"></i>
                            <p>No hay miembros en este grupo</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                            <div class="member-item" data-member-id="<?= $member['id'] ?>">
                                <div class="member-info">
                                    <div class="user-name"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></div>
                                    <div class="user-details">
                                        @<?= htmlspecialchars($member['username']) ?> • <?= htmlspecialchars($member['email']) ?>
                                    </div>
                                    <?php if ($member['company_name']): ?>
                                        <div class="user-company"><?= htmlspecialchars($member['company_name']) ?></div>
                                    <?php endif; ?>
                                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">
                                        Agregado: <?= date('d/m/Y', strtotime($member['added_at'])) ?>
                                    </div>
                                </div>
                                <button type="button" class="remove-member-btn" onclick="removeMember(<?= $member['id'] ?>)">
                                    <i data-feather="x" style="width: 14px; height: 14px;"></i>
                                    Remover
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón de guardar -->
    <div class="save-members-section">
        <button type="button" class="btn-save-members" onclick="saveMembers()">
            <i data-feather="save" style="width: 18px; height: 18px; margin-right: 8px;"></i>
            Guardar Cambios de Miembros
        </button>
    </div>
<?php endif; ?>
                      
                   <!-- Tab de Permisos -->
                   <?php if ($activeTab === 'permissions'): ?>
                       <div class="security-alert">
                           <div class="d-flex align-items-center">
                               <i class="fas fa-shield-alt fa-2x me-3 text-warning"></i>
                               <div>
                                   <h6 class="mb-1">Configuración de Seguridad</h6>
                                   <p class="mb-0">Configure las 6 acciones principales que pueden realizar los miembros de este grupo. <strong>Por defecto, todos los permisos están desactivados</strong> por seguridad.</p>
                               </div>
                           </div>
                       </div>

                       <form id="permissionsForm">
                           <input type="hidden" name="group_id" value="<?= $groupId ?>">

                           <div class="row">
                               <!-- 1. Subir Archivos -->
                               <div class="col-md-6 col-lg-4 mb-4">
                                   <div class="permission-card <?= $permissions['upload_files'] ? 'active' : '' ?>"
                                       onclick="togglePermission('upload_files')">
                                       <div class="icon">
                                           <i class="fas fa-cloud-upload-alt"></i>
                                       </div>
                                       <h5>1. Subir Archivos</h5>
                                       <p class="text-muted small mb-3">Permite subir nuevos documentos al sistema</p>
                                       <div class="form-check form-switch d-flex justify-content-center">
                                           <input class="form-check-input permission-toggle" type="checkbox"
                                               name="permissions[upload_files]" id="upload_files"
                                               <?= $permissions['upload_files'] ? 'checked' : '' ?>>
                                           <label class="form-check-label ms-2 fw-bold" for="upload_files">
                                               <span class="status-text"><?= $permissions['upload_files'] ? 'ACTIVADO' : 'DESACTIVADO' ?></span>
                                           </label>
                                       </div>
                                   </div>
                               </div>

                               <!-- 2. Ver Archivos -->
                               <div class="col-md-6 col-lg-4 mb-4">
                                   <div class="permission-card <?= $permissions['view_files'] ? 'active' : '' ?>"
                                       onclick="togglePermission('view_files')">
                                       <div class="icon">
                                           <i class="fas fa-eye"></i>
                                       </div>
                                       <h5>2. Ver Archivos</h5>
                                       <p class="text-muted small mb-3">Permite visualizar documentos existentes</p>
                                       <div class="form-check form-switch d-flex justify-content-center">
                                           <input class="form-check-input permission-toggle" type="checkbox"
                                               name="permissions[view_files]" id="view_files"
                                               <?= $permissions['view_files'] ? 'checked' : '' ?>>
                                           <label class="form-check-label ms-2 fw-bold" for="view_files">
                                               <span class="status-text"><?= $permissions['view_files'] ? 'ACTIVADO' : 'DESACTIVADO' ?></span>
                                           </label>
                                       </div>
                                   </div>
                               </div>

                               <!-- 3. Crear Carpetas -->
                               <div class="col-md-6 col-lg-4 mb-4">
                                   <div class="permission-card <?= $permissions['create_folders'] ? 'active' : '' ?>"
                                       onclick="togglePermission('create_folders')">
                                       <div class="icon">
                                           <i class="fas fa-folder-plus"></i>
                                       </div>
                                       <h5>3. Crear Carpetas</h5>
                                       <p class="text-muted small mb-3">Permite crear carpetas para organizar documentos</p>
                                       <div class="form-check form-switch d-flex justify-content-center">
                                           <input class="form-check-input permission-toggle" type="checkbox"
                                               name="permissions[create_folders]" id="create_folders"
                                               <?= $permissions['create_folders'] ? 'checked' : '' ?>>
                                           <label class="form-check-label ms-2 fw-bold" for="create_folders">
                                               <span class="status-text"><?= $permissions['create_folders'] ? 'ACTIVADO' : 'DESACTIVADO' ?></span>
                                           </label>
                                       </div>
                                   </div>
                               </div>

                               <!-- 4. Descargar -->
                               <div class="col-md-6 col-lg-4 mb-4">
                                   <div class="permission-card <?= $permissions['download_files'] ? 'active' : '' ?>"
                                       onclick="togglePermission('download_files')">
                                       <div class="icon">
                                           <i class="fas fa-download"></i>
                                       </div>
                                       <h5>4. Descargar</h5>
                                       <p class="text-muted small mb-3">Permite descargar documentos al equipo local</p>
                                       <div class="form-check form-switch d-flex justify-content-center">
                                           <input class="form-check-input permission-toggle" type="checkbox"
                                               name="permissions[download_files]" id="download_files"
                                               <?= $permissions['download_files'] ? 'checked' : '' ?>>
                                           <label class="form-check-label ms-2 fw-bold" for="download_files">
                                               <span class="status-text"><?= $permissions['download_files'] ? 'ACTIVADO' : 'DESACTIVADO' ?></span>
                                           </label>
                                       </div>
                                   </div>
                               </div>

                               <!-- 5. Eliminar Archivos -->
                               <div class="col-md-6 col-lg-4 mb-4">
                                   <div class="permission-card <?= $permissions['delete_files'] ? 'active' : '' ?>"
                                       onclick="togglePermission('delete_files')">
                                       <div class="icon">
                                           <i class="fas fa-trash-alt"></i>
                                       </div>
                                       <h5>5. Eliminar Archivos</h5>
                                       <p class="text-muted small mb-3">Permite eliminar documentos (solo propios en reportes)</p>
                                       <div class="form-check form-switch d-flex justify-content-center">
                                           <input class="form-check-input permission-toggle" type="checkbox"
                                               name="permissions[delete_files]" id="delete_files"
                                               <?= $permissions['delete_files'] ? 'checked' : '' ?>>
                                           <label class="form-check-label ms-2 fw-bold" for="delete_files">
                                               <span class="status-text"><?= $permissions['delete_files'] ? 'ACTIVADO' : 'DESACTIVADO' ?></span>
                                           </label>
                                       </div>
                                   </div>
                               </div>
                           </div>

                           <div class="row mt-4">
                               <div class="col-12">
                                   <div class="d-flex justify-content-end gap-2">
                                       <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                           <i class="fas fa-arrow-left me-1"></i>Cancelar
                                       </button>
                                       <button type="submit" class="btn btn-success">
                                           <i class="fas fa-save me-1"></i>Guardar Permisos
                                       </button>
                                   </div>
                               </div>
                           </div>
                       </form>
                   <?php endif; ?>

                   <!-- Tab de Restricciones -->
                   <?php if ($activeTab === 'restrictions'): ?>
                       <div class="security-alert">
                           <div class="d-flex align-items-center">
                               <i class="fas fa-shield-alt fa-3x me-4 text-warning"></i>
                               <div>
                                   <h5 class="mb-2 fw-bold">Control de Acceso Estricto</h5>
                                   <p class="mb-0">Los usuarios de este grupo <strong>solo podrán acceder</strong> a las empresas, departamentos y tipos de documentos seleccionados aquí. <span class="text-danger fw-bold">Sin selecciones = Sin acceso.</span></p>
                               </div>
                           </div>
                       </div>

                       <form id="restrictionsForm">
                           <input type="hidden" name="group_id" value="<?= $groupId ?>">

                           <!-- Empresas Permitidas -->
                           <div class="restriction-section">
                               <h5><i class="fas fa-building me-2"></i>Empresas Permitidas</h5>
                               <p class="text-muted mb-3">Seleccione las empresas a las que este grupo tendrá acceso:</p>

                               <?php if (empty($companies)): ?>
                                   <div class="alert alert-warning">
                                       <i class="fas fa-exclamation-triangle me-2"></i>No hay empresas activas disponibles.
                                   </div>
                               <?php else: ?>
                                   <div class="restriction-grid">
                                       <?php foreach ($companies as $company): ?>
                                           <div class="restriction-item <?= isRestricted('companies', $company['id'], $restrictions) ? 'selected' : '' ?>">
                                               <div class="form-check">
                                                   <input class="form-check-input" type="checkbox"
                                                       name="restrictions[companies][]"
                                                       value="<?= $company['id'] ?>"
                                                       id="company_<?= $company['id'] ?>"
                                                       <?= isRestricted('companies', $company['id'], $restrictions) ? 'checked' : '' ?>
                                                       onchange="toggleRestrictionItem(this)">
                                                   <label class="form-check-label fw-bold" for="company_<?= $company['id'] ?>">
                                                       <i class="fas fa-building me-2 text-primary"></i>
                                                       <?= htmlspecialchars($company['name']) ?>
                                                   </label>
                                               </div>
                                           </div>
                                       <?php endforeach; ?>
                                   </div>

                                   <div class="stats-counter">
                                       <i class="fas fa-check-circle me-1"></i>
                                       Seleccionadas: <?= isset($restrictions['companies']) ? count($restrictions['companies']) : 0 ?> de <?= count($companies) ?>
                                   </div>
                               <?php endif; ?>
                           </div>

                           <div class="section-divider"></div>

                           <!-- Departamentos Permitidos -->
                           <div class="restriction-section">
                               <h5><i class="fas fa-sitemap me-2"></i>Departamentos Permitidos</h5>
                               <p class="text-muted mb-3">Seleccione los departamentos a los que este grupo tendrá acceso:</p>

                               <?php if (empty($departments)): ?>
                                   <div class="alert alert-warning">
                                       <i class="fas fa-exclamation-triangle me-2"></i>No hay departamentos activos disponibles.
                                   </div>
                               <?php else: ?>
                                   <?php
                                   $currentCompany = '';
                                   foreach ($departments as $department):
                                       if ($currentCompany !== $department['company_name']):
                                           if ($currentCompany !== '') {
                                               echo '</div>'; // Cerrar grid anterior
                                           }
                                           $currentCompany = $department['company_name'];
                                           echo '<div class="company-header">';
                                           echo '<i class="fas fa-building me-2"></i>' . htmlspecialchars($currentCompany);
                                           echo '</div>';
                                           echo '<div class="department-grid">';
                                       endif;
                                   ?>
                                       <div class="department-item <?= isRestricted('departments', $department['id'], $restrictions) ? 'selected' : '' ?>">
                                           <div class="form-check">
                                               <input class="form-check-input" type="checkbox"
                                                   name="restrictions[departments][]"
                                                   value="<?= $department['id'] ?>"
                                                   id="department_<?= $department['id'] ?>"
                                                   <?= isRestricted('departments', $department['id'], $restrictions) ? 'checked' : '' ?>
                                                   onchange="toggleDepartmentItem(this)">
                                               <label class="form-check-label" for="department_<?= $department['id'] ?>">
                                                   <i class="fas fa-users me-2 text-secondary"></i>
                                                   <?= htmlspecialchars($department['name']) ?>
                                               </label>
                                           </div>
                                       </div>
                                   <?php endforeach; ?>
                                   <?php if ($currentCompany !== '') {
                                       echo '</div>'; // Cerrar último grid
                                   } ?>

                                   <div class="stats-counter mt-3">
                                       <i class="fas fa-check-circle me-1"></i>
                                       Seleccionados: <?= isset($restrictions['departments']) ? count($restrictions['departments']) : 0 ?> de <?= count($departments) ?>
                                   </div>
                               <?php endif; ?>
                           </div>

                           <div class="section-divider"></div>

                           <!-- Tipos de Documentos Permitidos -->
                           <div class="restriction-section">
                               <h5><i class="fas fa-file-alt me-2"></i>Tipos de Documentos Permitidos</h5>
                               <p class="text-muted mb-3">Seleccione los tipos de documentos a los que este grupo tendrá acceso:</p>

                               <?php if (empty($documentTypes)): ?>
                                   <div class="alert alert-warning">
                                       <i class="fas fa-exclamation-triangle me-2"></i>No hay tipos de documentos activos disponibles.
                                   </div>
                               <?php else: ?>
                                   <div class="document-type-grid">
                                       <?php foreach ($documentTypes as $docType): ?>
                                           <div class="document-type-item <?= isRestricted('document_types', $docType['id'], $restrictions) ? 'selected' : '' ?>">
                                               <div class="form-check">
                                                   <input class="form-check-input" type="checkbox"
                                                       name="restrictions[document_types][]"
                                                       value="<?= $docType['id'] ?>"
                                                       id="doctype_<?= $docType['id'] ?>"
                                                       <?= isRestricted('document_types', $docType['id'], $restrictions) ? 'checked' : '' ?>
                                                       onchange="toggleDocumentTypeItem(this)">
                                                   <label class="form-check-label" for="doctype_<?= $docType['id'] ?>">
                                                       <div class="d-flex align-items-start">
                                                           <i class="fas fa-file-alt me-2 text-success mt-1"></i>
                                                           <div>
                                                               <div class="fw-bold"><?= htmlspecialchars($docType['name']) ?></div>
                                                               <?php if ($docType['description']): ?>
                                                                   <small class="text-muted"><?= htmlspecialchars($docType['description']) ?></small>
                                                               <?php endif; ?>
                                                           </div>
                                                       </div>
                                                   </label>
                                               </div>
                                           </div>
                                       <?php endforeach; ?>
                                   </div>

                                   <div class="stats-counter">
                                       <i class="fas fa-check-circle me-1"></i>
                                       Seleccionados: <?= isset($restrictions['document_types']) ? count($restrictions['document_types']) : 0 ?> de <?= count($documentTypes) ?>
                                   </div>
                               <?php endif; ?>
                           </div>

                           <div class="row mt-5">
                               <div class="col-12">
                                   <div class="alert alert-info">
                                       <div class="d-flex align-items-center">
                                           <i class="fas fa-info-circle fa-2x me-3 text-info"></i>
                                           <div>
                                               <h6 class="fw-bold mb-1">Política de Seguridad</h6>
                                               <p class="mb-0">Si no selecciona ningún elemento en una categoría, los usuarios del grupo <strong>no tendrán acceso</strong> a esa categoría completa. Esta es una medida de seguridad preventiva.</p>
                                           </div>
                                       </div>
                                   </div>

                                   <div class="d-flex justify-content-end gap-3 mt-4">
                                       <button type="button" class="btn btn-outline-secondary btn-lg" onclick="window.history.back()">
                                           <i class="fas fa-arrow-left me-2"></i>Cancelar
                                       </button>
                                       <button type="submit" class="btn btn-primary btn-lg">
                                           <i class="fas fa-shield-alt me-2"></i>Guardar Restricciones
                                       </button>
                                   </div>
                               </div>
                           </div>
                       </form>
                   <?php endif; ?>
               </div>
           </div>
       </div>
   </main>

   <!-- Scripts -->
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
   <script>
       // ======= VARIABLES GLOBALES =======
       let selectedUserIds = new Set();
       let pendingMembers = new Set();

       // ======= FUNCIONES PARA MIEMBROS MEJORADAS =======
       
       function toggleUserSelection(userId) {
           const checkbox = document.getElementById(`user_${userId}`);
           checkbox.checked = !checkbox.checked;
           handleUserSelection(checkbox, userId);
       }

       function handleUserSelection(checkbox, userId) {
           const userItem = checkbox.closest('.user-item');
           
           if (checkbox.checked) {
               userItem.classList.add('selected');
               pendingMembers.add(userId);
               
               // Agregar automáticamente a la lista de miembros (visualmente)
               addUserToMembersList(userId, userItem);
               
               showNotification(`Usuario agregado temporalmente. Haz clic en "Guardar Cambios" para confirmar.`, 'info');
           } else {
               userItem.classList.remove('selected');
               pendingMembers.delete(userId);
               
               // Remover de la lista de miembros (si era temporal)
               removeUserFromMembersList(userId);
           }
       }

       function addUserToMembersList(userId, userItem) {
           const membersList = document.getElementById('membersList');
           const emptyState = membersList.querySelector('.empty-state');
           
           if (emptyState) {
               emptyState.remove();
           }

           // Extraer información del usuario
           const userName = userItem.querySelector('.user-name').textContent;
           const userDetails = userItem.querySelector('.user-details').textContent;
           const userCompany = userItem.querySelector('.user-company');
           const companyText = userCompany ? userCompany.textContent : '';

           // Crear elemento de miembro temporal
           const memberElement = document.createElement('div');
           memberElement.className = 'member-item pending-member';
           memberElement.setAttribute('data-member-id', userId);
           memberElement.innerHTML = `
               <div class="member-info">
                   <div class="user-name">${userName} <span class="badge bg-warning ms-2">PENDIENTE</span></div>
                   <div class="user-details">${userDetails}</div>
                   ${companyText ? `<div class="user-company">${companyText}</div>` : ''}
                   <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">
                       Se agregará al guardar cambios
                   </div>
               </div>
               <button type="button" class="remove-member-btn" onclick="removePendingMember(${userId})">
                   <i data-feather="x" style="width: 14px; height: 14px;"></i>
                   Cancelar
               </button>
           `;

           membersList.appendChild(memberElement);
           
           // Re-inicializar iconos de Feather
           feather.replace();
           
           // Actualizar contador
           updateMembersCount();
       }

       function removeUserFromMembersList(userId) {
           const pendingMember = document.querySelector(`[data-member-id="${userId}"].pending-member`);
           if (pendingMember) {
               pendingMember.remove();
               updateMembersCount();
           }
       }

       function removePendingMember(userId) {
           pendingMembers.delete(userId);
           removeUserFromMembersList(userId);
           
           // Desmarcar checkbox
           const checkbox = document.getElementById(`user_${userId}`);
           if (checkbox) {
               checkbox.checked = false;
               checkbox.closest('.user-item').classList.remove('selected');
           }
       }

       function removeMember(userId) {
           if (confirm('¿Está seguro de que desea remover este usuario del grupo?')) {
               fetch('actions/remove_member.php', {
                   method: 'POST',
                   headers: {
                       'Content-Type': 'application/json',
                   },
                   body: JSON.stringify({
                       group_id: <?= $groupId ?>,
                       user_id: userId
                   })
               })
               .then(response => response.json())
               .then(data => {
                   if (data.success) {
                       showNotification('Usuario removido del grupo', 'success');
                       setTimeout(() => {
                           window.location.reload();
                       }, 1500);
                   } else {
                       showNotification(data.message || 'Error al remover usuario', 'error');
                   }
               })
               .catch(error => {
                   console.error('Error:', error);
                   showNotification('Error de conexión', 'error');
               });
           }
       }

       function saveMembers() {
           if (pendingMembers.size === 0) {
               showNotification('No hay cambios pendientes para guardar', 'info');
               return;
           }

           // Obtener miembros actuales
           const currentMembers = Array.from(document.querySelectorAll('[data-member-id]:not(.pending-member)')).map(item => {
               return parseInt(item.getAttribute('data-member-id'));
           }).filter(id => !isNaN(id));

           // Combinar con miembros pendientes
           const finalMembers = [...new Set([...currentMembers, ...pendingMembers])];

           fetch('actions/manage_group_members.php', {
               method: 'POST',
               headers: {
                   'Content-Type': 'application/json',
               },
               body: JSON.stringify({
                   group_id: <?= $groupId ?>,
                   member_ids: finalMembers
               })
           })
           .then(response => response.json())
           .then(data => {
               if (data.success) {
                   showNotification('Miembros guardados correctamente', 'success');
                   pendingMembers.clear();
                   setTimeout(() => {
                       window.location.reload();
                   }, 1500);
               } else {
                   showNotification(data.message || 'Error al guardar miembros', 'error');
               }
           })
           .catch(error => {
               console.error('Error:', error);
               showNotification('Error de conexión', 'error');
           });
       }

       function updateMembersCount() {
           const membersCount = document.getElementById('membersCount');
           const currentCount = document.querySelectorAll('#membersList .member-item').length;
           if (membersCount) {
               membersCount.textContent = currentCount;
           }
       }

       // ======= FUNCIONES DE BÚSQUEDA =======
       function setupSearch() {
           const searchUsers = document.getElementById('searchUsers');
           const searchMembers = document.getElementById('searchMembers');

           if (searchUsers) {
               searchUsers.addEventListener('input', function() {
                   const filter = this.value.toLowerCase();
                   const items = document.querySelectorAll('#usersList .user-item');

                   items.forEach(item => {
                       const text = item.textContent.toLowerCase();
                       item.style.display = text.includes(filter) ? 'block' : 'none';
                   });
               });
           }

           if (searchMembers) {
               searchMembers.addEventListener('input', function() {
                   const filter = this.value.toLowerCase();
                   const items = document.querySelectorAll('#membersList .member-item');

                   items.forEach(item => {
                       const text = item.textContent.toLowerCase();
                       item.style.display = text.includes(filter) ? 'block' : 'none';
                   });
               });
           }
       }

       // ======= FUNCIONES DE PERMISOS =======
       function togglePermission(permissionName) {
           const checkbox = document.getElementById(permissionName);
           checkbox.checked = !checkbox.checked;
           updatePermissionCard(checkbox);
       }

       function updatePermissionCard(checkbox) {
           const card = checkbox.closest('.permission-card');
           const statusText = card.querySelector('.status-text');

           if (checkbox.checked) {
               card.classList.add('active');
               statusText.textContent = 'ACTIVADO';
           } else {
               card.classList.remove('active');
               statusText.textContent = 'DESACTIVADO';
           }
       }

       // ======= FUNCIONES DE RESTRICCIONES =======
       function toggleRestrictionItem(checkbox) {
           const item = checkbox.closest('.restriction-item');
           if (checkbox.checked) {
               item.classList.add('selected');
           } else {
               item.classList.remove('selected');
           }
       }

       function toggleDepartmentItem(checkbox) {
           const item = checkbox.closest('.department-item');
           if (checkbox.checked) {
               item.classList.add('selected');
           } else {
               item.classList.remove('selected');
           }
       }

       function toggleDocumentTypeItem(checkbox) {
           const item = checkbox.closest('.document-type-item');
           if (checkbox.checked) {
               item.classList.add('selected');
           } else {
               item.classList.remove('selected');
           }
       }

       // ======= MANEJO DE FORMULARIOS =======
       
       // Formulario de permisos
       document.getElementById('permissionsForm')?.addEventListener('submit', function(e) {
           e.preventDefault();

           const groupId = document.querySelector('input[name="group_id"]').value;
           const permissions = {};
           const checkboxes = document.querySelectorAll('input[name^="permissions["]');

           checkboxes.forEach(checkbox => {
               const permissionName = checkbox.name.match(/permissions\[(.+)\]/)[1];
               permissions[permissionName] = checkbox.checked;
           });

           const requestData = {
               group_id: parseInt(groupId),
               permissions: permissions,
               restrictions: {}
           };

           fetch('actions/update_group_permissions.php', {
               method: 'POST',
               headers: {
                   'Content-Type': 'application/json',
               },
               body: JSON.stringify(requestData)
           })
           .then(response => response.json())
           .then(data => {
               if (data.success) {
                   showNotification('Permisos actualizados correctamente', 'success');
                   setTimeout(() => {
                       window.location.reload();
                   }, 1500);
               } else {
                   showNotification(data.message || 'Error al actualizar permisos', 'error');
               }
           })
           .catch(error => {
               console.error('Error:', error);
               showNotification('Error de conexión', 'error');
           });
       });

       // Formulario de restricciones
       document.getElementById('restrictionsForm')?.addEventListener('submit', function(e) {
           e.preventDefault();

           const groupId = document.querySelector('input[name="group_id"]').value;
           const restrictions = {
               companies: [],
               departments: [],
               document_types: []
           };

           // Recopilar restricciones
           const companiesChecked = document.querySelectorAll('input[name="restrictions[companies][]"]:checked');
           restrictions.companies = Array.from(companiesChecked).map(cb => parseInt(cb.value));

           const departmentsChecked = document.querySelectorAll('input[name="restrictions[departments][]"]:checked');
           restrictions.departments = Array.from(departmentsChecked).map(cb => parseInt(cb.value));

           const docTypesChecked = document.querySelectorAll('input[name="restrictions[document_types][]"]:checked');
           restrictions.document_types = Array.from(docTypesChecked).map(cb => parseInt(cb.value));

           const requestData = {
               group_id: parseInt(groupId),
               permissions: {},
               restrictions: restrictions
           };

           fetch('actions/update_group_permissions.php', {
               method: 'POST',
               headers: {
                   'Content-Type': 'application/json',
               },
               body: JSON.stringify(requestData)
           })
           .then(response => response.json())
           .then(data => {
               if (data.success) {
                   showNotification('Restricciones actualizadas correctamente', 'success');
                   setTimeout(() => {
                       window.location.reload();
                   }, 1500);
               } else {
                   showNotification(data.message || 'Error al actualizar restricciones', 'error');
               }
           })
           .catch(error => {
               console.error('Error:', error);
               showNotification('Error de conexión', 'error');
           });
       });

       // ======= FUNCIÓN DE NOTIFICACIONES =======
       function showNotification(message, type = 'info') {
           const alertClass = type === 'success' ? 'alert-success' : 
                            type === 'error' ? 'alert-danger' : 'alert-info';
           
           const notification = document.createElement('div');
           notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
           notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
           notification.innerHTML = `
               <div class="d-flex align-items-center">
                   <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                   ${message}
               </div>
               <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
           `;
           
           document.body.appendChild(notification);
           
           // Auto-remover después de 5 segundos
           setTimeout(() => {
               if (notification.parentNode) {
                   notification.remove();
               }
           }, 5000);
       }

       // ======= FUNCIÓN PARA ACTUALIZAR HORA =======
       function updateTime() {
           const now = new Date();
           const timeString = now.toLocaleDateString('es-ES', {
               day: '2-digit',
               month: '2-digit', 
               year: 'numeric'
           }) + ', ' + now.toLocaleTimeString('es-ES', {
               hour: '2-digit',
               minute: '2-digit'
           });
           const timeElement = document.getElementById('currentTime');
           if (timeElement) {
               timeElement.textContent = timeString;
           }
       }

       // ======= INICIALIZACIÓN =======
       document.addEventListener('DOMContentLoaded', function() {
           // Inicializar iconos de Feather
           if (typeof feather !== 'undefined') {
               feather.replace();
           }
           
           // Inicializar estado visual de las tarjetas de permisos
           document.querySelectorAll('.permission-toggle').forEach(checkbox => {
               updatePermissionCard(checkbox);
           });

           // Configurar búsquedas
           setupSearch();

           // Prevenir que el click en checkbox active el toggle de la tarjeta
           document.querySelectorAll('.permission-toggle').forEach(checkbox => {
               checkbox.addEventListener('click', function(e) {
                   e.stopPropagation();
                   updatePermissionCard(this);
               });
           });

           // Actualizar hora cada minuto
           updateTime();
           setInterval(updateTime, 60000);
       });

       // ======= FUNCIONES ADICIONALES PARA MEJOR UX =======
       function showComingSoon(feature) {
           showNotification(`${feature} estará disponible próximamente`, 'info');
       }

       function toggleSidebar() {
           // Función para toggle del sidebar móvil si es necesaria
           const sidebar = document.querySelector('.sidebar');
           if (sidebar) {
               sidebar.classList.toggle('mobile-active');
           }
       }
   </script>
</body>
</html>