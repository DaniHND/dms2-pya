<?php
/*
 * modules/groups/permissions.php
 * Sistema de permisos - Código limpio
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
    
    // Obtener todos los usuarios activos
    $allUsersQuery = "
        SELECT u.id, u.username, u.first_name, u.last_name, u.email,
               c.name as company_name, d.name as department_name
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.status = 'active'
        ORDER BY u.first_name, u.last_name
    ";
    $allUsers = $pdo->query($allUsersQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener recursos para restricciones
    $companies = $pdo->query("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $departments = $pdo->query("SELECT d.id, d.name, c.name as company_name FROM departments d LEFT JOIN companies c ON d.company_id = c.id WHERE d.status = 'active' ORDER BY c.name, d.name")->fetchAll(PDO::FETCH_ASSOC);
    $documentTypes = $pdo->query("SELECT id, name FROM document_types WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log('Error en permisos: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permisos: <?php echo htmlspecialchars($group['name']); ?> - DMS2</title>
    
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
    :root {
        --dms-primary: #8B4513;
        --dms-primary-hover: #654321;
        --dms-success: #10b981;
        --dms-danger: #ef4444;
        --dms-warning: #f59e0b;
        --dms-info: #3b82f6;
        --dms-bg: #f8fafc;
        --dms-card-bg: #ffffff;
        --dms-border: #e2e8f0;
        --dms-text: #1e293b;
        --dms-text-muted: #64748b;
        --dms-radius: 12px;
        --dms-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .permissions-container {
        padding: 24px;
        background: var(--dms-bg);
        min-height: calc(100vh - 80px);
        margin-left: 240px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 32px;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--dms-text);
        margin: 0 0 8px 0;
    }

    .page-subtitle {
        color: var(--dms-text-muted);
        font-size: 1rem;
        margin: 0;
    }

    .btn-back {
        background: var(--dms-primary);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(139, 69, 19, 0.3);
    }

    .btn-back:hover {
        background: var(--dms-primary-hover);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4);
    }

    .group-info-section {
        background: var(--dms-card-bg);
        border-radius: var(--dms-radius);
        padding: 24px;
        margin-bottom: 24px;
        border: 1px solid var(--dms-border);
        box-shadow: var(--dms-shadow);
    }

    .group-info-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dms-text);
        margin-bottom: 8px;
    }

    .group-info-description {
        color: var(--dms-text-muted);
        margin-bottom: 16px;
    }

    .group-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
    }

    .stat-item {
        text-align: center;
        padding: 12px;
        background: #f8fafc;
        border-radius: 8px;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dms-primary);
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--dms-text-muted);
    }

    .tabs-container {
        background: var(--dms-card-bg);
        border-radius: var(--dms-radius);
        border: 1px solid var(--dms-border);
        box-shadow: var(--dms-shadow);
        overflow: hidden;
    }

    .tabs-header {
        display: flex;
        border-bottom: 1px solid var(--dms-border);
        background: #f8fafc;
    }

    .tab-btn {
        flex: 1;
        padding: 16px 24px;
        background: none;
        border: none;
        font-weight: 600;
        color: var(--dms-text-muted);
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        position: relative;
    }

    .tab-btn:hover {
        background: rgba(139, 69, 19, 0.05);
        color: var(--dms-primary);
    }

    .tab-btn.active {
        color: var(--dms-primary);
        background: white;
    }

    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--dms-primary);
    }

    .tab-content {
        padding: 24px;
    }

    .tab-section {
        display: none;
    }

    .tab-section.active {
        display: block;
    }

    .selection-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }

    .selection-box {
        border: 1px solid var(--dms-border);
        border-radius: var(--dms-radius);
        overflow: hidden;
        background: white;
    }

    .selection-header {
        background: #f8fafc;
        padding: 16px;
        border-bottom: 1px solid var(--dms-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .selection-title {
        font-weight: 600;
        color: var(--dms-text);
        flex: 1;
    }

    .selection-count {
        background: var(--dms-primary);
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        min-width: 20px;
        text-align: center;
    }

    .selection-search {
        width: 100%;
        padding: 12px;
        border: none;
        border-bottom: 1px solid var(--dms-border);
        font-size: 0.875rem;
    }

    .selection-list {
        max-height: 400px;
        overflow-y: auto;
    }

    .selection-item {
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .selection-item:hover {
        background: #f8fafc;
    }

    .selection-item.selected {
        background: rgba(139, 69, 19, 0.1);
        border-left: 3px solid var(--dms-primary);
    }

    .item-checkbox {
        appearance: none;
        width: 18px;
        height: 18px;
        border: 2px solid var(--dms-border);
        border-radius: 3px;
        cursor: pointer;
        position: relative;
    }

    .item-checkbox:checked {
        background: var(--dms-primary);
        border-color: var(--dms-primary);
    }

    .item-checkbox:checked::after {
        content: '✓';
        position: absolute;
        top: -2px;
        left: 2px;
        color: white;
        font-size: 12px;
        font-weight: bold;
    }

    .item-info {
        flex: 1;
    }

    .item-name {
        font-weight: 500;
        color: var(--dms-text);
        font-size: 0.875rem;
    }

    .item-meta {
        font-size: 0.75rem;
        color: var(--dms-text-muted);
        margin-top: 2px;
    }

    .user-avatar {
        width: 32px;
        height: 32px;
        background: var(--dms-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.75rem;
    }

    .permissions-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    .permissions-card {
        background: #f8fafc;
        border-radius: var(--dms-radius);
        padding: 20px;
        border: 1px solid var(--dms-border);
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--dms-text);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .permission-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid var(--dms-border);
    }

    .permission-item:last-child {
        border-bottom: none;
    }

    .permission-info {
        flex: 1;
    }

    .permission-name {
        font-weight: 500;
        color: var(--dms-text);
        font-size: 0.875rem;
    }

    .permission-desc {
        font-size: 0.75rem;
        color: var(--dms-text-muted);
        margin-top: 2px;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .3s;
        border-radius: 24px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: var(--dms-primary);
    }

    input:checked + .slider:before {
        transform: translateX(20px);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        font-weight: 600;
        color: var(--dms-text);
        margin-bottom: 8px;
        font-size: 0.875rem;
    }

    .form-input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--dms-border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: white;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--dms-primary);
        box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
    }

    .checkbox-container {
        max-height: 150px;
        overflow-y: auto;
        border: 1px solid var(--dms-border);
        border-radius: 8px;
        padding: 8px;
        background: white;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 0;
        cursor: pointer;
        font-size: 0.875rem;
    }

    .checkbox-item:hover {
        background: rgba(139, 69, 19, 0.05);
        border-radius: 4px;
        padding: 6px 8px;
        margin: 0 -8px;
    }

    .help-text {
        color: var(--dms-text-muted);
        font-size: 0.75rem;
        margin-top: 4px;
        display: block;
    }

    .limits-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        font-size: 0.875rem;
    }

    .btn-primary {
        background: var(--dms-primary);
        color: white;
    }

    .btn-primary:hover {
        background: #654321;
        transform: translateY(-1px);
    }

    .btn-success {
        background: var(--dms-success);
        color: white;
    }

    .btn-success:hover {
        background: #059669;
    }

    .actions-bar {
        display: flex;
        justify-content: center;
        padding: 20px;
    }

    @media (max-width: 768px) {
        .permissions-container {
            margin-left: 0;
            padding: 16px;
        }
        
        .selection-container,
        .permissions-grid {
            grid-template-columns: 1fr;
        }
        
        .limits-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body class="dashboard-layout">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="permissions-container">
            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Permisos de Grupo</h1>
                    <p class="page-subtitle">Gestiona miembros, permisos y restricciones del grupo</p>
                </div>
                
                <a href="index.php" class="btn-back">
                    <i data-feather="arrow-left"></i>
                    Volver a Grupos
                </a>
            </div>
            
            <!-- Información del grupo -->
            <div class="group-info-section">
                <h2 class="group-info-title"><?php echo htmlspecialchars($group['name']); ?></h2>
                <p class="group-info-description"><?php echo htmlspecialchars($group['description'] ?: 'Sin descripción'); ?></p>
                
                <div class="group-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo count($members); ?></div>
                        <div class="stat-label">Miembros</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $group['status'] === 'active' ? 'Activo' : 'Inactivo'; ?></div>
                        <div class="stat-label">Estado</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $group['is_system_group'] ? 'Sistema' : 'Personalizado'; ?></div>
                        <div class="stat-label">Tipo</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo date('d/m/Y', strtotime($group['created_at'])); ?></div>
                        <div class="stat-label">Creado</div>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab-btn <?php echo $activeTab === 'members' ? 'active' : ''; ?>" onclick="switchTab('members')">
                        <i data-feather="users"></i>
                        Miembros (<?php echo count($members); ?>)
                    </button>
                    <button class="tab-btn <?php echo $activeTab === 'permissions' ? 'active' : ''; ?>" onclick="switchTab('permissions')">
                        <i data-feather="shield"></i>
                        Permisos y Restricciones
                    </button>
                </div>
                
                <div class="tab-content">
                    <!-- Tab Miembros -->
                    <div id="membersTab" class="tab-section <?php echo $activeTab === 'members' ? 'active' : ''; ?>">
                        <div class="selection-container">
                            <!-- Usuarios disponibles -->
                            <div class="selection-box">
                                <div class="selection-header">
                                    <span class="selection-title">Todos los Usuarios</span>
                                    <span class="selection-count" id="totalUsersCount"><?php echo count($allUsers); ?></span>
                                </div>
                                
                                <input type="text" class="selection-search" id="searchAllUsers" placeholder="Buscar usuarios...">
                                
                                <div class="selection-list" id="allUsersList">
                                    <?php foreach ($allUsers as $user): ?>
                                        <?php 
                                        $isMember = false;
                                        foreach ($members as $member) {
                                            if ($member['id'] == $user['id']) {
                                                $isMember = true;
                                                break;
                                            }
                                        }
                                        ?>
                                        <div class="selection-item <?php echo $isMember ? 'selected' : ''; ?>" data-user-id="<?php echo $user['id']; ?>">
                                            <input type="checkbox" class="item-checkbox" <?php echo $isMember ? 'checked' : ''; ?> 
                                                   onchange="toggleUserMembership(<?php echo $user['id']; ?>, this.checked)">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                            </div>
                                            <div class="item-info">
                                                <div class="item-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                <div class="item-meta">
                                                    <?php echo htmlspecialchars($user['username']); ?> • 
                                                    <?php echo htmlspecialchars($user['company_name'] ?: 'Sin empresa'); ?> • 
                                                    <?php echo htmlspecialchars($user['department_name'] ?: 'Sin departamento'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Miembros actuales -->
                            <div class="selection-box">
                                <div class="selection-header">
                                    <span class="selection-title">Miembros del Grupo</span>
                                    <span class="selection-count" id="membersCount"><?php echo count($members); ?></span>
                                </div>
                                
                                <input type="text" class="selection-search" id="searchMembers" placeholder="Buscar miembros...">
                                
                                <div class="selection-list" id="membersList">
                                    <?php if (empty($members)): ?>
                                        <div class="selection-item" style="text-align: center; color: #64748b; font-style: italic;">
                                            No hay miembros en este grupo
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($members as $member): ?>
                                            <div class="selection-item selected" data-user-id="<?php echo $member['id']; ?>">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                                </div>
                                                <div class="item-info">
                                                    <div class="item-name"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                    <div class="item-meta">
                                                        <?php echo htmlspecialchars($member['username']); ?> • 
                                                        Agregado: <?php echo date('d/m/Y', strtotime($member['added_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="actions-bar">
                            <button class="btn btn-success" onclick="saveMembers()">
                                <i data-feather="save"></i>
                                Guardar Miembros
                            </button>
                        </div>
                    </div>
                    
                    <!-- Tab Permisos -->
                    <div id="permissionsTab" class="tab-section <?php echo $activeTab === 'permissions' ? 'active' : ''; ?>">
                        <form id="permissionsForm">
                            <div class="permissions-grid">
                                <!-- Permisos de acciones -->
                                <div class="permissions-card">
                                    <h3 class="card-title">
                                        <i data-feather="key"></i>
                                        Permisos de Acciones
                                    </h3>
                                    
                                    <?php 
                                    $permissionsList = [
                                        'view' => ['Ver documentos', 'Permite visualizar documentos'],
                                        'view_reports' => ['Ver reportes', 'Acceso a módulo de reportes'],
                                        'download' => ['Descargar', 'Descargar documentos'],
                                        'export' => ['Exportar', 'Exportar reportes y datos'],
                                        'create' => ['Crear documentos', 'Subir nuevos documentos'],
                                        'edit' => ['Editar documentos', 'Modificar documentos existentes'],
                                        'delete' => ['Eliminar documentos', 'Mover documentos a papelera'],
                                        'delete_permanent' => ['Eliminación permanente', 'Eliminar documentos definitivamente'],
                                        'manage_users' => ['Gestionar usuarios', 'Crear y administrar usuarios'],
                                        'system_config' => ['Configuración del sistema', 'Acceso a configuración avanzada']
                                    ];
                                    
                                    foreach ($permissionsList as $key => $info): ?>
                                        <div class="permission-item">
                                            <div class="permission-info">
                                                <div class="permission-name"><?php echo $info[0]; ?></div>
                                                <div class="permission-desc"><?php echo $info[1]; ?></div>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="permissions[<?php echo $key; ?>]" <?php echo !empty($permissions[$key]) ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Restricciones -->
                                <div class="permissions-card">
                                    <h3 class="card-title">
                                        <i data-feather="filter"></i>
                                        Restricciones de Acceso
                                    </h3>
                                    
                                    <!-- Empresas -->
                                    <div class="form-group">
                                        <label class="form-label">Empresas permitidas</label>
                                        <div class="checkbox-container">
                                            <?php foreach ($companies as $company): ?>
                                                <label class="checkbox-item">
                                                    <input type="checkbox" name="restrictions[companies][]" value="<?php echo $company['id']; ?>" 
                                                           <?php echo in_array($company['id'], $restrictions['companies'] ?? []) ? 'checked' : ''; ?>>
                                                    <span><?php echo htmlspecialchars($company['name']); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <small class="help-text">Si no selecciona ninguna, tendrá acceso a todas las empresas</small>
                                    </div>
                                    
                                    <!-- Departamentos -->
                                    <div class="form-group">
                                        <label class="form-label">Departamentos permitidos</label>
                                        <div class="checkbox-container">
                                            <?php foreach ($departments as $department): ?>
                                                <label class="checkbox-item">
                                                    <input type="checkbox" name="restrictions[departments][]" value="<?php echo $department['id']; ?>" 
                                                           <?php echo in_array($department['id'], $restrictions['departments'] ?? []) ? 'checked' : ''; ?>>
                                                    <span>
                                                        <?php echo htmlspecialchars($department['name']); ?>
                                                        <?php if ($department['company_name']): ?>
                                                            <small style="color: var(--dms-text-muted);">(<?php echo htmlspecialchars($department['company_name']); ?>)</small>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <small class="help-text">Si no selecciona ninguno, tendrá acceso a todos los departamentos</small>
                                    </div>
                                    
                                    <!-- Tipos de documentos -->
                                    <div class="form-group">
                                        <label class="form-label">Tipos de documentos permitidos</label>
                                        <div class="checkbox-container">
                                            <?php foreach ($documentTypes as $docType): ?>
                                                <label class="checkbox-item">
                                                    <input type="checkbox" name="restrictions[document_types][]" value="<?php echo $docType['id']; ?>" 
                                                           <?php echo in_array($docType['id'], $restrictions['document_types'] ?? []) ? 'checked' : ''; ?>>
                                                    <span><?php echo htmlspecialchars($docType['name']); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <small class="help-text">Si no selecciona ninguno, tendrá acceso a todos los tipos de documentos</small>
                                    </div>
                                    
                                    <!-- Límites diarios -->
                                    <div class="limits-grid">
                                        <div class="form-group">
                                            <label class="form-label">Descargas por día</label>
                                            <input type="number" class="form-input" name="download_limit_daily" 
                                                   value="<?php echo $group['download_limit_daily'] ?: ''; ?>" 
                                                   placeholder="Sin límite" min="0">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Subidas por día</label>
                                            <input type="number" class="form-input" name="upload_limit_daily" 
                                                   value="<?php echo $group['upload_limit_daily'] ?: ''; ?>" 
                                                   placeholder="Sin límite" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        
                        <div class="actions-bar">
                            <button type="button" class="btn btn-primary" onclick="savePermissions()">
                                <i data-feather="save"></i>
                                Guardar Permisos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    const groupId = <?php echo $groupId; ?>;
    let selectedUsers = new Set();
    
    // Inicializar usuarios seleccionados
    <?php foreach ($members as $member): ?>
    selectedUsers.add(<?php echo $member['id']; ?>);
    <?php endforeach; ?>
    
    document.addEventListener('DOMContentLoaded', function() {
        feather.replace();
        initializeSearch();
        updateCounts();
        
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab') || 'members';
        switchTab(tab);
    });
    
    function switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-section').forEach(section => section.classList.remove('active'));
        
        if (tabName === 'members') {
            document.querySelector('.tab-btn:first-child').classList.add('active');
            document.getElementById('membersTab').classList.add('active');
        } else if (tabName === 'permissions') {
            document.querySelector('.tab-btn:last-child').classList.add('active');
            document.getElementById('permissionsTab').classList.add('active');
        }
        
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        history.replaceState(null, '', url);
        
        feather.replace();
    }
    
    function initializeSearch() {
        document.getElementById('searchAllUsers').addEventListener('input', function() {
            filterItems('allUsersList', this.value);
        });
        
        document.getElementById('searchMembers').addEventListener('input', function() {
            filterItems('membersList', this.value);
        });
    }
    
    function filterItems(containerId, searchTerm) {
        const container = document.getElementById(containerId);
        const items = container.querySelectorAll('.selection-item');
        
        items.forEach(item => {
            const name = item.querySelector('.item-name')?.textContent.toLowerCase() || '';
            const meta = item.querySelector('.item-meta')?.textContent.toLowerCase() || '';
            
            if (name.includes(searchTerm.toLowerCase()) || meta.includes(searchTerm.toLowerCase())) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    function toggleUserMembership(userId, isChecked) {
        const userItem = document.querySelector(`[data-user-id="${userId}"]`);
        
        if (isChecked) {
            selectedUsers.add(userId);
            userItem.classList.add('selected');
        } else {
            selectedUsers.delete(userId);
            userItem.classList.remove('selected');
        }
        
        updateCounts();
        updateMembersList();
    }
    
    function updateCounts() {
        document.getElementById('membersCount').textContent = selectedUsers.size;
    }
    
    function updateMembersList() {
        const membersList = document.getElementById('membersList');
        const allUsers = <?php echo json_encode($allUsers); ?>;
        
        membersList.innerHTML = '';
        
        if (selectedUsers.size === 0) {
            membersList.innerHTML = '<div class="selection-item" style="text-align: center; color: #64748b; font-style: italic;">No hay miembros seleccionados</div>';
            return;
        }
        
        selectedUsers.forEach(userId => {
            const user = allUsers.find(u => u.id == userId);
            if (user) {
                const initials = user.first_name.charAt(0).toUpperCase() + user.last_name.charAt(0).toUpperCase();
                membersList.innerHTML += `
                    <div class="selection-item selected" data-user-id="${user.id}">
                        <div class="user-avatar">${initials}</div>
                        <div class="item-info">
                            <div class="item-name">${user.first_name} ${user.last_name}</div>
                            <div class="item-meta">${user.username} • ${user.company_name || 'Sin empresa'}</div>
                        </div>
                    </div>
                `;
            }
        });
    }
    
    function saveMembers() {
        const memberIds = Array.from(selectedUsers);
        const btn = event.target;
        
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i data-feather="loader"></i> Guardando...';
        btn.disabled = true;
        feather.replace();
        
        fetch('actions/update_group_members.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                group_id: groupId,
                member_ids: memberIds
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Miembros actualizados correctamente', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(data.message || 'Error al actualizar miembros', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al procesar la solicitud', 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            feather.replace();
        });
    }
    
    function savePermissions() {
        const form = document.getElementById('permissionsForm');
        const formData = new FormData(form);
        const btn = event.target;
        
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i data-feather="loader"></i> Guardando...';
        btn.disabled = true;
        feather.replace();
        
        const permissions = {};
        const restrictions = {
            companies: [],
            departments: [],
            document_types: []
        };
        
        let downloadLimit = null;
        let uploadLimit = null;
        
        for (let [key, value] of formData.entries()) {
            if (key.startsWith('permissions[')) {
                const permissionKey = key.replace('permissions[', '').replace(']', '');
                permissions[permissionKey] = true;
            } else if (key.startsWith('restrictions[')) {
                const restrictionKey = key.replace('restrictions[', '').replace('][]', '');
                if (!restrictions[restrictionKey]) {
                    restrictions[restrictionKey] = [];
                }
                restrictions[restrictionKey].push(parseInt(value));
            } else if (key === 'download_limit_daily') {
                downloadLimit = value ? parseInt(value) : null;
            } else if (key === 'upload_limit_daily') {
                uploadLimit = value ? parseInt(value) : null;
            }
        }
        
        fetch('actions/update_group_permissions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                group_id: groupId,
                permissions: permissions,
                restrictions: restrictions,
                download_limit_daily: downloadLimit,
                upload_limit_daily: uploadLimit
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Permisos actualizados correctamente', 'success');
            } else {
                showNotification(data.message || 'Error al actualizar permisos', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al procesar la solicitud', 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            feather.replace();
        });
    }
    
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        const bgColor = type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6';
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${bgColor};
            color: white;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            z-index: 10000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            font-weight: 500;
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 8px;
        `;
        
        notification.innerHTML = `
            <i data-feather="${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        feather.replace();
        
        setTimeout(() => notification.style.transform = 'translateX(0)', 100);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(400px)';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }
    </script>
</body>
</html>