<?php
/*
 * modules/groups/permissions.php
 * Sistema de permisos - Versión limpia sin diagnósticos
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
    
    // Obtener recursos para restricciones
    $companies = $pdo->query("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $departments = $pdo->query("SELECT d.id, d.name, c.name as company_name FROM departments d LEFT JOIN companies c ON d.company_id = c.id WHERE d.status = 'active' ORDER BY c.name, d.name")->fetchAll(PDO::FETCH_ASSOC);
    $documentTypes = $pdo->query("SELECT id, name FROM document_types WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener todos los usuarios activos para agregar
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
    <title>Permisos de Grupo - DMS2</title>
    
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">
    
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
    /* Header igual a grupos */
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
    
    .header-left { display: flex; align-items: center; gap: 16px; }
    .header-left h1 { font-size: 1.5rem; font-weight: 600; color: #1e293b; margin: 0; }
    .mobile-menu-toggle { display: none; background: none; border: none; cursor: pointer; padding: 8px; }
    .header-right { display: flex; align-items: center; gap: 16px; }
    .header-info { text-align: right; }
    .user-name-header { font-weight: 600; color: #374151; font-size: 14px; }
    .current-time { font-size: 12px; color: #64748b; }
    .header-actions { display: flex; align-items: center; gap: 8px; }
    
    .btn-icon {
        background: transparent; border: none; padding: 8px; border-radius: 6px;
        cursor: pointer; transition: all 0.2s ease; color: #64748b;
        display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
    }
    .btn-icon:hover { background: #f1f5f9; color: #374151; }
    .logout-btn { color: #ef4444; }
    .logout-btn:hover { background: rgba(239, 68, 68, 0.1); color: #dc2626; }

    /* Container y estilos básicos */
    .container { padding: 0 24px; }
    
    .btn-back {
        background: #8B4513; color: white; border: none; padding: 12px 20px; border-radius: 8px;
        font-weight: 600; cursor: pointer; transition: all 0.2s ease; display: inline-flex;
        align-items: center; gap: 8px; text-decoration: none; margin-bottom: 24px;
    }
    .btn-back:hover { background: #654321; color: white; }

    .group-info-section {
        background: white; border-radius: 12px; padding: 24px; margin-bottom: 32px;
        border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .group-info-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0 0 8px 0; }
    .group-info-description { color: #64748b; margin: 0 0 20px 0; }
    
    .group-stats {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;
    }
    .stat-item { text-align: center; padding: 16px; background: #f8fafc; border-radius: 8px; }
    .stat-value { font-size: 2rem; font-weight: 700; color: #8B4513; }
    .stat-label { font-size: 0.875rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Tabs */
    .tabs-nav {
        display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 32px;
        background: white; border-radius: 8px 8px 0 0; overflow: hidden;
    }
    .tab-btn {
        padding: 16px 24px; background: none; border: none; cursor: pointer; transition: all 0.2s ease;
        font-weight: 500; color: #64748b; display: flex; align-items: center; gap: 8px;
        text-decoration: none; border-bottom: 3px solid transparent;
    }
    .tab-btn:hover { background: #f8fafc; color: #1e293b; }
    .tab-btn.active { background: #f8fafc; color: #8B4513; border-bottom-color: #8B4513; }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Secciones */
    .permissions-section, .members-section {
        background: white; border-radius: 12px; padding: 24px;
        border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); margin-bottom: 24px;
    }
    
    .section-title {
        font-size: 1.25rem; font-weight: 600; color: #1e293b; margin: 0 0 20px 0;
        display: flex; align-items: center; gap: 8px;
    }

    /* Selección de miembros */
    .selection-container {
        display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;
    }
    
    .selection-box {
        border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: white;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .selection-header {
        background: #f8fafc; padding: 16px; border-bottom: 1px solid #e2e8f0;
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
    }
    
    .selection-title {
        font-weight: 600; color: #1e293b; flex: 1;
    }
    
    .selection-count {
        background: #8B4513; color: white; padding: 4px 8px; border-radius: 12px;
        font-size: 0.75rem; font-weight: 600; min-width: 20px; text-align: center;
    }
    
    .selection-search {
        width: 100%; padding: 12px; border: none; border-bottom: 1px solid #e2e8f0;
        font-size: 0.875rem; outline: none;
    }
    .selection-search:focus { border-bottom-color: #8B4513; }
    
    .selection-list {
        max-height: 400px; overflow-y: auto;
    }
    
    .selection-item {
        padding: 12px 16px; border-bottom: 1px solid #f1f5f9; cursor: pointer;
        transition: all 0.2s ease; display: flex; align-items: center; gap: 12px;
    }
    .selection-item:hover { background: #f8fafc; }
    .selection-item.selected {
        background: rgba(139, 69, 19, 0.1); border-left: 3px solid #8B4513;
    }
    
    .item-checkbox {
        appearance: none; width: 18px; height: 18px; border: 2px solid #e2e8f0;
        border-radius: 3px; cursor: pointer; position: relative; transition: all 0.2s ease;
    }
    .item-checkbox:checked {
        background: #8B4513; border-color: #8B4513;
    }
    .item-checkbox:checked::after {
        content: '✓'; position: absolute; top: -2px; left: 2px; color: white;
        font-size: 12px; font-weight: bold;
    }
    
    .user-avatar {
        width: 36px; height: 36px; background: #8B4513; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; color: white;
        font-weight: 600; font-size: 0.875rem; flex-shrink: 0;
    }
    
    .item-info { flex: 1; min-width: 0; }
    .item-name { font-weight: 600; color: #1e293b; margin-bottom: 2px; }
    .item-meta { font-size: 0.75rem; color: #64748b; }

    /* Permisos */
    .permissions-grid { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 24px; 
    }
    .permission-group { background: #f8fafc; border-radius: 8px; padding: 20px; border: 1px solid #e2e8f0; }
    .permission-group h4 { margin: 0 0 16px 0; color: #1e293b; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
    
    .permission-item {
        display: flex; align-items: center; justify-content: space-between; padding: 12px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    .permission-item:last-child { border-bottom: none; }
    
    .permission-label { display: flex; flex-direction: column; gap: 4px; }
    .permission-name { font-weight: 500; color: #1e293b; }
    .permission-description { font-size: 0.875rem; color: #64748b; }

    .toggle-switch {
        position: relative; width: 50px; height: 24px; background: #cbd5e1;
        border-radius: 12px; cursor: pointer; transition: all 0.3s ease;
    }
    .toggle-switch.active { background: #10b981; }
    .toggle-switch::after {
        content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px;
        background: white; border-radius: 50%; transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    .toggle-switch.active::after { transform: translateX(26px); }

    /* Restricciones */
    .restrictions-grid {
        display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px;
    }
    
    .restriction-group {
        background: #f8fafc; border-radius: 8px; padding: 20px; border: 1px solid #e2e8f0;
    }
    
    .restriction-group h4 {
        margin: 0 0 16px 0; color: #1e293b; font-size: 1rem; display: flex; align-items: center; gap: 8px;
    }
    
    .restriction-item {
        display: flex; align-items: center; justify-content: space-between; padding: 8px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    .restriction-item:last-child { border-bottom: none; }
    
    .restriction-label { font-size: 0.875rem; color: #1e293b; }
    
    .restriction-checkbox {
        width: 18px; height: 18px; accent-color: #8B4513;
    }

    /* Botones */
    .btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px;
        border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
        transition: all 0.2s ease; text-decoration: none; font-size: 0.875rem;
    }
    .btn-primary { background: #8B4513; color: white; }
    .btn-primary:hover { background: #654321; transform: translateY(-1px); }
    .btn-success { background: #10b981; color: white; }
    .btn-success:hover { background: #059669; }
    .btn-remove { background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; font-size: 0.875rem; }
    .btn-remove:hover { background: #dc2626; }

    .actions-bar {
        display: flex; justify-content: center; gap: 16px; padding: 24px;
        background: #f8fafc; border-radius: 0 0 8px 8px; border-top: 1px solid #e2e8f0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .container { padding: 0 16px; }
        .permissions-grid { grid-template-columns: 1fr; }
        .restrictions-grid { grid-template-columns: 1fr; }
        .selection-container { grid-template-columns: 1fr; }
        .group-stats { grid-template-columns: repeat(2, 1fr); }
        .content-header { padding: 0 16px; }
    }
    </style>
</head>

<body class="dashboard-layout">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header igual a grupos -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Permisos de Grupo</h1>
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
        <div class="container">
            <!-- Botón volver -->
            <a href="index.php" class="btn-back">
                <i data-feather="arrow-left"></i>
                Volver a Grupos
            </a>
            
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
            
            <!-- Navegación de tabs -->
            <div class="tabs-nav">
                <a href="?group=<?php echo $groupId; ?>&tab=members" class="tab-btn <?php echo $activeTab === 'members' ? 'active' : ''; ?>">
                    <i data-feather="users"></i>
                    Miembros (<?php echo count($members); ?>)
                </a>
                <a href="?group=<?php echo $groupId; ?>&tab=permissions" class="tab-btn <?php echo $activeTab === 'permissions' ? 'active' : ''; ?>">
                    <i data-feather="shield"></i>
                    Permisos y Restricciones
                </a>
            </div>
            
            <!-- Tab Miembros -->
            <div class="tab-content <?php echo $activeTab === 'members' ? 'active' : ''; ?>" id="members-tab">
                <div class="selection-container">
                    <!-- Usuarios disponibles -->
                    <div class="selection-box">
                        <div class="selection-header">
                            <span class="selection-title">Todos los Usuarios</span>
                            <span class="selection-count" id="totalUsersCount"><?php echo count($allUsers); ?></span>
                        </div>
                        
                        <input type="text" class="selection-search" id="searchAllUsers" placeholder="Buscar usuarios...">
                        
                        <div class="selection-list" id="allUsersList">
                            <?php if (empty($allUsers)): ?>
                                <div class="selection-item" style="text-align: center; color: #64748b; font-style: italic;">
                                    No hay usuarios disponibles
                                </div>
                            <?php else: ?>
                                <?php 
                                $currentMemberIds = array_column($members, 'id');
                                foreach ($allUsers as $user): 
                                    $isMember = in_array($user['id'], $currentMemberIds);
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
                                                <?php echo htmlspecialchars($user['company_name'] ?: 'Sin empresa'); ?>
                                                <?php if ($user['department_name']): ?>
                                                    • <?php echo htmlspecialchars($user['department_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Miembros seleccionados -->
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
                                                <?php echo htmlspecialchars($member['company_name'] ?: 'Sin empresa'); ?>
                                                <?php if ($member['department_name']): ?>
                                                    • <?php echo htmlspecialchars($member['department_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Botón para guardar miembros -->
                <div class="actions-bar">
                    <button class="btn btn-success" onclick="saveMembers()">
                        <i data-feather="save"></i>
                        Guardar Miembros
                    </button>
                </div>
            </div>
            
            <!-- Tab Permisos -->
            <div class="tab-content <?php echo $activeTab === 'permissions' ? 'active' : ''; ?>" id="permissions-tab">
                <!-- Permisos de Acciones -->
                <div class="permissions-section">
                    <h3 class="section-title">
                        <i data-feather="key"></i>
                        Permisos de Acciones
                    </h3>
                    
                    <div class="permissions-grid">
                        <!-- Permisos básicos -->
                        <div class="permission-group">
                            <h4><i data-feather="file-text"></i> Documentos y Visualización</h4>
                            
                            <div class="permission-item">
                                <div class="permission-label">
                                    <div class="permission-name">Ver documentos</div>
                                    <div class="permission-description">Permite visualizar documentos</div>
                                </div>
                                <div class="toggle-switch <?php echo isset($permissions['view']) && $permissions['view'] ? 'active' : ''; ?>" 
                                     onclick="togglePermission('view')"></div>
                            </div>
                            
                            <div class="permission-item">
                                <div class="permission-label">
                                    <div class="permission-name">Ver reportes</div>
                                    <div class="permission-description">Acceso a módulo de reportes</div>
                                </div>
                                <div class="toggle-switch <?php echo isset($permissions['view_reports']) && $permissions['view_reports'] ? 'active' : ''; ?>" 
                                     onclick="togglePermission('view_reports')"></div>
                            </div>
                            
                            <div class="permission-item">
                                <div class="permission-label">
                                    <div class="permission-name">Descargar</div>
                                    <div class="permission-description">Descargar documentos</div>
                                </div>
                                <div class="toggle-switch <?php echo isset($permissions['download']) && $permissions['download'] ? 'active' : ''; ?>" 
                                     onclick="togglePermission('download')"></div>
                            </div>
                            
                            <div class="permission-item">
                                <div class="permission-label">
                                    <div class="permission-name">Exportar</div>
                                    <div class="permission-description">Exportar reportes y datos</div>
                                </div>
                                <div class="toggle-switch <?php echo isset($permissions['export']) && $permissions['export'] ? 'active' : ''; ?>" 
                                     onclick="togglePermission('export')"></div>
                            </div>
                        </div>
                        
                        <!-- Permisos de gestión -->
                        <div class="permission-group">
                            <h4><i data-feather="edit"></i> Gestión y Administración</h4>
                            
                            <div class="permission-item">
                                <div class="permission-label">
                                    <div class="permission-name">Crear documentos</div>
                                    <div class="permission-description">Subir nuevos documentos</div>
                                </div>
                                <div class="toggle-switch <?php echo isset($permissions['create']) && $permissions['create'] ? 'active' : ''; ?>" 
                                     onclick="togglePermission('create')"></div>
                            </div>
                            
                            
                            <div class="permission-item">
                                <div class="permission-label">
                                    <div class="permission-name">Eliminar documentos</div>
                                    <div class="permission-description">Eliminar documentos (papelera)</div>
                                </div>
                                <div class="toggle-switch <?php echo isset($permissions['delete']) && $permissions['delete'] ? 'active' : ''; ?>" 
                                     onclick="togglePermission('delete')"></div>
                            </div>
                            
                            <div class="permission-item">
                                <div class="permission-label">
                                    <div class="permission-name">Eliminación permanente</div>
                                    <div class="permission-description">Eliminar permanentemente</div>
                                </div>
                                <div class="toggle-switch <?php echo isset($permissions['delete_permanent']) && $permissions['delete_permanent'] ? 'active' : ''; ?>" 
                                     onclick="togglePermission('delete_permanent')"></div>
                            </div>
                            
                            <div class="permission-item">
                                <div class="permission-label">
                                    <div class="permission-name">Gestionar usuarios</div>
                                    <div class="permission-description">Crear y modificar usuarios</div>
                                </div>
                                <div class="toggle-switch <?php echo isset($permissions['manage_users']) && $permissions['manage_users'] ? 'active' : ''; ?>" 
                                     onclick="togglePermission('manage_users')"></div>
                            </div>
                            
                            <div class="permission-item">
                                <div class="permission-label">
                                    <div class="permission-name">Configuración del sistema</div>
                                    <div class="permission-description">Acceso a configuración</div>
                                </div>
                                <div class="toggle-switch <?php echo isset($permissions['system_config']) && $permissions['system_config'] ? 'active' : ''; ?>" 
                                     onclick="togglePermission('system_config')"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Restricciones por acceso -->
                <div class="permissions-section">
                    <h3 class="section-title">
                        <i data-feather="filter"></i>
                        Restricciones de Acceso
                    </h3>
                    
                    <div class="restrictions-grid">
                        <!-- Restricciones por empresa -->
                        <div class="restriction-group">
                            <h4><i data-feather="briefcase"></i> Empresas permitidas</h4>
                            
                            <?php if (empty($companies)): ?>
                                <p style="color: #64748b; font-style: italic;">No hay empresas disponibles</p>
                            <?php else: ?>
                                <div style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($companies as $company): ?>
                                        <div class="restriction-item">
                                            <div class="restriction-label"><?php echo htmlspecialchars($company['name']); ?></div>
                                            <input type="checkbox" class="restriction-checkbox"
                                                   id="company_<?php echo $company['id']; ?>" 
                                                   value="<?php echo $company['id']; ?>"
                                                   <?php echo in_array($company['id'], $restrictions['companies'] ?? []) ? 'checked' : ''; ?>
                                                   onchange="updateRestrictions()">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <p style="font-size: 0.75rem; color: #64748b; margin-top: 12px;">
                                    Si no seleccionas ninguna, tendrá acceso a todas las empresas
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Restricciones por departamento -->
                        <div class="restriction-group">
                            <h4><i data-feather="layers"></i> Departamentos permitidos</h4>
                            
                            <?php if (empty($departments)): ?>
                                <p style="color: #64748b; font-style: italic;">No hay departamentos disponibles</p>
                            <?php else: ?>
                                <div style="max-height: 200px; overflow-y: auto;">
                                    <?php 
                                    $currentCompany = '';
                                    foreach ($departments as $department): 
                                        if ($department['company_name'] !== $currentCompany):
                                            if ($currentCompany !== '') echo '</div>';
                                            $currentCompany = $department['company_name'];
                                            echo '<div style="margin-bottom: 12px;">';
                                            echo '<div style="font-weight: 600; color: #8B4513; font-size: 0.875rem; margin-bottom: 8px;">' . htmlspecialchars($currentCompany) . '</div>';
                                        endif;
                                    ?>
                                        <div class="restriction-item" style="padding: 6px 0;">
                                            <div class="restriction-label" style="font-size: 0.875rem;"><?php echo htmlspecialchars($department['name']); ?></div>
                                            <input type="checkbox" class="restriction-checkbox"
                                                   id="department_<?php echo $department['id']; ?>" 
                                                   value="<?php echo $department['id']; ?>"
                                                   <?php echo in_array($department['id'], $restrictions['departments'] ?? []) ? 'checked' : ''; ?>
                                                   onchange="updateRestrictions()">
                                        </div>
                                    <?php 
                                    endforeach; 
                                    if ($currentCompany !== '') echo '</div>';
                                    ?>
                                </div>
                                <p style="font-size: 0.75rem; color: #64748b; margin-top: 12px;">
                                    Si no seleccionas ninguno, tendrá acceso a todos los departamentos
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Restricciones por tipo de documento -->
                        <div class="restriction-group">
                            <h4><i data-feather="file-text"></i> Tipos de documentos</h4>
                            
                            <?php if (empty($documentTypes)): ?>
                                <p style="color: #64748b; font-style: italic;">No hay tipos de documentos disponibles</p>
                            <?php else: ?>
                                <div style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($documentTypes as $docType): ?>
                                        <div class="restriction-item">
                                            <div class="restriction-label"><?php echo htmlspecialchars($docType['name']); ?></div>
                                            <input type="checkbox" class="restriction-checkbox"
                                                   id="doctype_<?php echo $docType['id']; ?>" 
                                                   value="<?php echo $docType['id']; ?>"
                                                   <?php echo in_array($docType['id'], $restrictions['document_types'] ?? []) ? 'checked' : ''; ?>
                                                   onchange="updateRestrictions()">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <p style="font-size: 0.75rem; color: #64748b; margin-top: 12px;">
                                    Si no seleccionas ninguno, tendrá acceso a todos los tipos
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones principales -->
                <div class="actions-bar">
                    <button class="btn btn-success" onclick="savePermissions()">
                        <i data-feather="save"></i>
                        Guardar Permisos
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
    // JavaScript básico y funcional
    var currentPermissions = <?php echo json_encode($permissions); ?>;
    var currentRestrictions = <?php echo json_encode($restrictions); ?>;
    var groupId = <?php echo $groupId; ?>;
    var selectedUsers = new Set();

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        updateTime();
        setInterval(updateTime, 60000);
        
        var activeTab = '<?php echo $activeTab; ?>';
        if (activeTab === 'members') {
            initializeSelectedUsers();
            initializeSearch();
            updateCounts();
        }
    });

    function updateTime() {
        var now = new Date();
        var timeString = now.toLocaleDateString('es-ES', {
            day: '2-digit', month: '2-digit', year: 'numeric'
        }) + ' ' + now.toLocaleTimeString('es-ES', {
            hour: '2-digit', minute: '2-digit'
        });
        var timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
    }

    function toggleSidebar() {
        var sidebar = document.querySelector('.sidebar');
        var mainContent = document.querySelector('.main-content');
        var overlay = document.querySelector('.sidebar-overlay');
        
        if (sidebar) {
            sidebar.classList.toggle('collapsed');
            if (mainContent) {
                mainContent.classList.toggle('sidebar-collapsed');
            }
            if (overlay) {
                overlay.classList.toggle('active');
            }
        }
    }

    function showComingSoon(feature) {
        alert('Función "' + feature + '" próximamente disponible');
    }

    function initializeSelectedUsers() {
        var checkboxes = document.querySelectorAll('#allUsersList .item-checkbox:checked');
        for (var i = 0; i < checkboxes.length; i++) {
            var checkbox = checkboxes[i];
            var userItem = checkbox.closest('[data-user-id]');
            if (userItem && userItem.dataset.userId) {
                var userId = parseInt(userItem.dataset.userId);
                selectedUsers.add(userId);
            }
        }
    }

    function initializeSearch() {
        var searchAllUsers = document.getElementById('searchAllUsers');
        var searchMembers = document.getElementById('searchMembers');
        
        if (searchAllUsers) {
            searchAllUsers.addEventListener('input', function() {
                filterItems('allUsersList', this.value);
            });
        }
        
        if (searchMembers) {
            searchMembers.addEventListener('input', function() {
                filterItems('membersList', this.value);
            });
        }
    }

    function filterItems(containerId, searchTerm) {
        var container = document.getElementById(containerId);
        if (!container) return;
        
        var items = container.querySelectorAll('.selection-item');
        var searchLower = searchTerm.toLowerCase();
        
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var nameElement = item.querySelector('.item-name');
            var metaElement = item.querySelector('.item-meta');
            
            var name = nameElement ? nameElement.textContent.toLowerCase() : '';
            var meta = metaElement ? metaElement.textContent.toLowerCase() : '';
            
            var matches = name.indexOf(searchLower) !== -1 || meta.indexOf(searchLower) !== -1;
            item.style.display = matches ? 'flex' : 'none';
        }
    }

    function toggleUserMembership(userId, isChecked) {
        var userItem = document.querySelector('#allUsersList [data-user-id="' + userId + '"]');
        
        if (isChecked) {
            selectedUsers.add(userId);
            if (userItem) userItem.classList.add('selected');
        } else {
            selectedUsers.delete(userId);
            if (userItem) userItem.classList.remove('selected');
        }
        
        updateCounts();
        updateMembersList();
    }

    function updateCounts() {
        var membersCountElement = document.getElementById('membersCount');
        if (membersCountElement) {
            membersCountElement.textContent = selectedUsers.size;
        }
    }

    function updateMembersList() {
        var membersList = document.getElementById('membersList');
        if (!membersList) return;
        
        var allUsers = <?php echo json_encode($allUsers); ?>;
        membersList.innerHTML = '';
        
        if (selectedUsers.size === 0) {
            membersList.innerHTML = '<div class="selection-item" style="text-align: center; color: #64748b; font-style: italic;">No hay miembros seleccionados</div>';
            return;
        }
        
        selectedUsers.forEach(function(userId) {
            for (var i = 0; i < allUsers.length; i++) {
                var user = allUsers[i];
                if (user.id == userId) {
                    var initials = user.first_name.charAt(0).toUpperCase() + user.last_name.charAt(0).toUpperCase();
                    var companyText = user.company_name || 'Sin empresa';
                    var departmentText = user.department_name ? ' • ' + user.department_name : '';
                    
                    var memberHTML = '<div class="selection-item selected" data-user-id="' + user.id + '">' +
                        '<div class="user-avatar">' + initials + '</div>' +
                        '<div class="item-info">' +
                            '<div class="item-name">' + user.first_name + ' ' + user.last_name + '</div>' +
                            '<div class="item-meta">' + user.username + ' • ' + companyText + departmentText + '</div>' +
                        '</div>' +
                    '</div>';
                    
                    membersList.innerHTML += memberHTML;
                    break;
                }
            }
        });
    }

    function saveMembers() {
        var memberIds = Array.from(selectedUsers);
        var btn = event.target;
        var originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i data-feather="loader"></i> Guardando...';
        
        fetch('actions/manage_group_members.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                group_id: groupId,
                member_ids: memberIds
            })
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                alert('Miembros actualizados correctamente');
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                // Mostrar información detallada del error
                var errorMessage = 'Error: ' + (data.message || 'No se pudieron actualizar los miembros');
                
                if (data.debug) {
                    console.log('DEBUG INFO:', data.debug);
                    errorMessage += '\n\nInformación de debug (ver consola para más detalles):';
                    
                    if (data.debug.table_structure) {
                        console.log('Estructura de tabla:', data.debug.table_structure);
                        errorMessage += '\n- Estructura de tabla mostrada en consola';
                    }
                    
                    if (data.debug.pdo_error) {
                        console.log('Error PDO:', data.debug.pdo_error);
                        errorMessage += '\n- Error de base de datos: ' + data.debug.pdo_error;
                    }
                    
                    if (data.debug.sql_state) {
                        console.log('SQL State:', data.debug.sql_state);
                    }
                }
                
                alert(errorMessage);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('Error de conexión al actualizar miembros');
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (typeof feather !== 'undefined') feather.replace();
        });
    }

    function togglePermission(permission) {
        currentPermissions[permission] = !currentPermissions[permission];
        var toggle = document.querySelector('[onclick="togglePermission(\'' + permission + '\')"]');
        if (toggle) {
            if (currentPermissions[permission]) {
                toggle.classList.add('active');
            } else {
                toggle.classList.remove('active');
            }
        }
    }

    function updateRestrictions() {
        var companyCheckboxes = document.querySelectorAll('input[id^="company_"]:checked');
        currentRestrictions.companies = [];
        for (var i = 0; i < companyCheckboxes.length; i++) {
            currentRestrictions.companies.push(parseInt(companyCheckboxes[i].value));
        }
        
        var departmentCheckboxes = document.querySelectorAll('input[id^="department_"]:checked');
        currentRestrictions.departments = [];
        for (var i = 0; i < departmentCheckboxes.length; i++) {
            currentRestrictions.departments.push(parseInt(departmentCheckboxes[i].value));
        }
        
        var doctypeCheckboxes = document.querySelectorAll('input[id^="doctype_"]:checked');
        currentRestrictions.document_types = [];
        for (var i = 0; i < doctypeCheckboxes.length; i++) {
            currentRestrictions.document_types.push(parseInt(doctypeCheckboxes[i].value));
        }
    }

    function savePermissions() {
        var submitBtn = event.target;
        var originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i data-feather="loader"></i> Guardando...';
        
        updateRestrictions();
        
        var data = {
            group_id: groupId,
            permissions: currentPermissions,
            restrictions: currentRestrictions
        };
        
        fetch('actions/update_group_permissions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                alert('Permisos guardados exitosamente');
            } else {
                alert('Error: ' + (data.message || 'No se pudieron guardar los permisos'));
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('Error de conexión al guardar permisos');
        })
        .finally(function() {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            if (typeof feather !== 'undefined') feather.replace();
        });
    }
    </script>
</body>
</html>