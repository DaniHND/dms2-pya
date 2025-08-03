<?php
/*
 * modules/groups/index.php
 * Módulo de Gestión de Grupos - DMS2 - VERSIÓN DEFINITIVA
 */

// Rutas absolutas
$projectRoot = dirname(dirname(__DIR__));
require_once $projectRoot . '/config/session.php';
require_once $projectRoot . '/config/database.php';

// Verificar sesión y permisos
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    header('Location: ../../dashboard.php');
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Configuración de paginación y filtros
    $itemsPerPage = 10;
    $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Construir consulta con filtros
    $whereConditions = [];
    $params = [];
    
    if (!empty($searchTerm)) {
        $whereConditions[] = "(ug.name LIKE ? OR ug.description LIKE ?)";
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%';
    }
    
    if (!empty($statusFilter)) {
        $whereConditions[] = "ug.status = ?";
        $params[] = $statusFilter;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Obtener grupos con paginación
    $groupsQuery = "
        SELECT 
            ug.*,
            COUNT(DISTINCT ugm.user_id) as total_members,
            COALESCE(CONCAT(creator.first_name, ' ', creator.last_name), 'Sistema') as created_by_name
        FROM user_groups ug
        LEFT JOIN user_group_members ugm ON ug.id = ugm.group_id
        LEFT JOIN users creator ON ug.created_by = creator.id
        $whereClause
        GROUP BY ug.id
        ORDER BY ug.is_system_group ASC, ug.created_at DESC
        LIMIT $itemsPerPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($groupsQuery);
    $stmt->execute($params);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total para paginación
    $countQuery = "
        SELECT COUNT(DISTINCT ug.id) as total
        FROM user_groups ug
        LEFT JOIN user_group_members ugm ON ug.id = ugm.group_id
        LEFT JOIN users creator ON ug.created_by = creator.id
        $whereClause
    ";
    
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalItems / $itemsPerPage);
    
} catch (Exception $e) {
    error_log('Error en grupos: ' . $e->getMessage());
    $groups = [];
    $totalItems = 0;
    $totalPages = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Grupos - DMS2</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
    /* Estilos para que coincida exactamente con otros módulos */
    
    /* Botón crear principal */
    .btn-create-company {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        background: #8B4513;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .btn-create-company:hover {
        background: #A0522D;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    .btn-create-company i {
        width: 16px;
        height: 16px;
    }
    
    /* Sección del botón crear */
    .create-button-section {
        margin-bottom: 24px;
    }
    
    /* Tarjeta de filtros */
    .filters-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
    }
    
    .filters-card h3 {
        margin: 0 0 20px 0;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }
    
    .filters-form {
        display: flex;
        gap: 20px;
        align-items: end;
        flex-wrap: wrap;
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .filter-label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #374151;
        font-size: 14px;
    }
    
    .filter-input,
    .filter-select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        background: white;
        transition: all 0.2s ease;
    }
    
    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    /* Sección de contenido */
    .content-section {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
    }
    
    .section-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e5e7eb;
        background: #f9fafb;
    }
    
    .section-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }
    
    /* Tabla */
    .table-container {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    
    .data-table th {
        background: #f9fafb;
        padding: 16px 20px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }
    
    .data-table td {
        padding: 16px 20px;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }
    
    .data-table tr:hover {
        background: #f9fafb;
    }
    
    /* Información de elementos */
    .item-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .item-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: #e0f2fe;
        border-radius: 8px;
        color: #0369a1;
        flex-shrink: 0;
    }
    
    .item-icon i {
        width: 20px;
        height: 20px;
    }
    
    .item-details {
        flex: 1;
        min-width: 0;
    }
    
    .item-name {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 4px;
        line-height: 1.2;
    }
    
    .item-description {
        font-size: 13px;
        color: #6b7280;
        line-height: 1.4;
    }
    
    .item-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        background: #fef3c7;
        color: #92400e;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }
    
    .item-badge.sistema {
        background: #dbeafe;
        color: #1d4ed8;
    }
    
    /* Badges de estado */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 12px;
        font-weight: 500;
        text-transform: capitalize;
    }
    
    .status-badge.activo {
        background: #dcfce7;
        color: #166534;
    }
    
    .status-badge.inactivo {
        background: #fee2e2;
        color: #991b1b;
    }
    
    /* Información de fechas */
    .date-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .date-info .date {
        font-weight: 500;
        color: #1f2937;
        font-size: 14px;
    }
    
    .date-info .creator {
        font-size: 12px;
        color: #6b7280;
    }
    
    /* Botones de acción - Colores exactos como Departamentos */
    .action-buttons {
        display: flex;
        gap: 6px;
        align-items: center;
    }
    
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        padding: 6px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }
    
    /* Botón VER - Azul */
    .btn-action.btn-view {
        background-color: #3b82f6;
        color: white;
    }
    
    .btn-action.btn-view:hover {
        background-color: #2563eb;
        transform: translateY(-1px);
    }
    
    /* Botón EDITAR - Naranja */
    .btn-action.btn-edit {
        background-color: #f59e0b;
        color: white;
    }
    
    .btn-action.btn-edit:hover {
        background-color: #d97706;
        transform: translateY(-1px);
    }
    
    /* Botón ACTIVAR/DESACTIVAR - Rojo */
    .btn-action.btn-delete {
        background-color: #ef4444;
        color: white;
    }
    
    .btn-action.btn-delete:hover {
        background-color: #dc2626;
        transform: translateY(-1px);
    }
    
    .btn-action i {
        width: 16px;
        height: 16px;
        stroke-width: 2;
    }
    
    /* Estado vacío */
    .empty-state {
        text-align: center;
        padding: 60px 24px;
        color: #6b7280;
    }
    
    .empty-icon {
        margin: 0 auto 20px;
        width: 80px;
        height: 80px;
        background: #f3f4f6;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
    }
    
    .empty-icon i {
        width: 32px;
        height: 32px;
    }
    
    .empty-state h3 {
        margin: 0 0 12px;
        font-size: 18px;
        font-weight: 600;
        color: #374151;
    }
    
    .empty-state p {
        margin: 0 0 24px;
        color: #6b7280;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
        line-height: 1.5;
    }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }
    
    .modal.active {
        display: flex;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow: hidden;
        transform: scale(0.95);
        transition: transform 0.3s ease;
    }
    
    .modal.active .modal-content {
        transform: scale(1);
    }
    
    .modal-header {
        padding: 24px 24px 16px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f9fafb;
    }
    
    .modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }
    
    .modal-header .close {
        background: none;
        border: none;
        padding: 8px;
        cursor: pointer;
        color: #6b7280;
        border-radius: 6px;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-header .close:hover {
        background: #e5e7eb;
        color: #374151;
    }
    
    .modal-header .close i {
        width: 16px;
        height: 16px;
    }
    
    .modal-body {
        padding: 24px;
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .modal-footer {
        padding: 16px 24px 24px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        background: #f9fafb;
    }
    
    /* Formularios */
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #374151;
        font-size: 14px;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
        background: white;
        box-sizing: border-box;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 80px;
        font-family: inherit;
    }
    
    /* Botones del modal */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        gap: 6px;
        min-width: 100px;
    }
    
    .btn-primary {
        background: #3b82f6;
        color: white;
    }
    
    .btn-primary:hover {
        background: #2563eb;
        transform: translateY(-1px);
    }
    
    .btn-secondary {
        background: white;
        color: #374151;
        border: 1px solid #d1d5db;
    }
    
    .btn-secondary:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }
    
    /* Notificaciones */
    .notification {
        position: fixed;
        top: 24px;
        right: 24px;
        padding: 16px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 2000;
        max-width: 400px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        animation: slideInRight 0.3s ease;
    }
    
    .notification.success {
        background: #10b981;
    }
    
    .notification.error {
        background: #ef4444;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    /* Loading */
    .loading {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1500;
        backdrop-filter: blur(2px);
    }
    
    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #e5e7eb;
        border-top: 3px solid #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Responsividad */
    @media (max-width: 768px) {
        .filters-form {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-group {
            min-width: auto;
        }
        
        .filter-actions-group {
            justify-content: flex-start;
        }
        
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .data-table {
            min-width: 600px;
        }
        
        .modal-content {
            width: 95%;
            margin: 20px;
        }
        
        .action-buttons {
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .btn-action {
            width: 28px;
            height: 28px;
        }
    }
    </style>
</head>

<body class="dashboard-layout">
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Contenido principal -->
    <main class="main-content">
        <!-- Header -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Gestión de Grupos</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" onclick="window.location.reload()">
                        <i data-feather="refresh-cw"></i>
                    </button>
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido -->
        <div class="content-wrapper">
            <!-- Botón de crear -->
            <div class="create-button-section">
                <button class="btn-create-company" onclick="showCreateGroupModal()">
                    <i data-feather="users"></i>
                    <span>Crear Grupo</span>
                </button>
            </div>

            <!-- Filtros de búsqueda -->
            <div class="filters-card">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" class="filters-form" id="filtersForm">
                    <div class="filter-group">
                        <label for="search" class="filter-label">Buscar Grupo</label>
                        <input type="text" id="search" name="search" class="filter-input"
                               value="<?php echo htmlspecialchars($searchTerm); ?>" 
                               placeholder="Nombre, descripción..."
                               onkeyup="autoSearch()">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status" class="filter-label">Estado</label>
                        <select id="status" name="status" class="filter-select" onchange="autoSearch()">
                            <option value="">Todos los estados</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Resultados -->
            <div class="content-section">
                <div class="section-header">
                    <h3>Grupos (<?php echo $totalItems; ?> registros)</h3>
                </div>

                <?php if (empty($groups)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i data-feather="users"></i>
                        </div>
                        <h3>No hay grupos</h3>
                        <p>
                            <?php if (!empty($searchTerm) || !empty($statusFilter)): ?>
                                No se encontraron grupos que coincidan con los filtros aplicados.
                            <?php else: ?>
                                Aún no se han creado grupos. Crea el primer grupo para comenzar.
                            <?php endif; ?>
                        </p>
                        <?php if (empty($searchTerm) && empty($statusFilter)): ?>
                            <button class="btn-create-company" onclick="showCreateGroupModal()">
                                <i data-feather="users"></i>
                                <span>Crear Primer Grupo</span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Grupo</th>
                                    <th>Miembros</th>
                                    <th>Estado</th>
                                    <th>Fecha Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groups as $group): ?>
                                    <tr>
                                        <td>
                                            <div class="item-info">
                                                <div class="item-icon">
                                                    <i data-feather="<?php echo $group['is_system_group'] ? 'shield' : 'users'; ?>"></i>
                                                </div>
                                                <div class="item-details">
                                                    <div class="item-name">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                        <?php if ($group['is_system_group']): ?>
                                                            <span class="item-badge sistema">Sistema</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="item-description">
                                                        <?php echo htmlspecialchars($group['description'] ?: 'Sin descripción'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $group['total_members']; ?> usuarios</td>
                                        <td>
                                            <span class="status-badge activo">
                                                <?php echo ucfirst($group['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <div class="date"><?php echo date('d/m/Y H:i', strtotime($group['created_at'])); ?></div>
                                                <div class="creator"><?php echo htmlspecialchars($group['created_by_name'] ?: 'Sistema'); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-action btn-view" 
                                                        onclick="viewGroupDetails(<?php echo $group['id']; ?>)">
                                                    <i data-feather="eye"></i>
                                                </button>
                                                
                                                <?php if (!$group['is_system_group']): ?>
                                                    <button type="button" class="btn-action btn-edit" 
                                                            onclick="editGroup(<?php echo $group['id']; ?>)">
                                                        <i data-feather="edit"></i>
                                                    </button>
                                                    
                                                    <button type="button" class="btn-action btn-delete" 
                                                            onclick="toggleGroupStatus(<?php echo $group['id']; ?>, '<?php echo $group['status']; ?>')">
                                                        <i data-feather="<?php echo $group['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal para Crear/Editar Grupo -->
    <div id="groupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="groupModalTitle">Crear Nuevo Grupo</h3>
                <button type="button" class="close" onclick="closeGroupModal()">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="groupForm">
                    <input type="hidden" id="groupId" name="group_id">
                    
                    <div class="form-group">
                        <label for="groupName">Nombre del Grupo *</label>
                        <input type="text" id="groupName" name="group_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="groupDescription">Descripción</label>
                        <textarea id="groupDescription" name="group_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="groupStatus">Estado</label>
                        <select id="groupStatus" name="group_status" class="form-control">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeGroupModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveGroup()">Guardar Grupo</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
    // Variables globales
    let currentGroupId = null;
    let searchTimeout = null;
    
    // Función para búsqueda automática
    function autoSearch() {
        // Limpiar timeout anterior si existe
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Crear un pequeño delay para evitar demasiadas búsquedas mientras se escribe
        searchTimeout = setTimeout(function() {
            const form = document.getElementById('filtersForm');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            
            // Redirigir con los nuevos parámetros
            window.location.href = '?' + params.toString();
        }, 500); // Esperar 500ms después de que el usuario deje de escribir
    }
    
    // Funciones de utilidad
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 3000);
    }
    
    function showLoading() {
        const loading = document.createElement('div');
        loading.className = 'loading';
        loading.id = 'loadingOverlay';
        loading.innerHTML = '<div class="loading-spinner"></div>';
        document.body.appendChild(loading);
    }
    
    function hideLoading() {
        const loading = document.getElementById('loadingOverlay');
        if (loading && loading.parentNode) {
            loading.parentNode.removeChild(loading);
        }
    }
    
    // Función para mostrar modal de crear grupo
    function showCreateGroupModal() {
        currentGroupId = null;
        document.getElementById('groupModalTitle').textContent = 'Crear Nuevo Grupo';
        clearGroupForm();
        
        const modal = document.getElementById('groupModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    // Función para cerrar modal
    function closeGroupModal() {
        const modal = document.getElementById('groupModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        clearGroupForm();
    }
    
    // Función para limpiar formulario
    function clearGroupForm() {
        document.getElementById('groupForm').reset();
        document.getElementById('groupId').value = '';
        currentGroupId = null;
    }
    
    // Función para guardar grupo
    function saveGroup() {
        const groupName = document.getElementById('groupName').value.trim();
        const groupDescription = document.getElementById('groupDescription').value.trim();
        const groupStatus = document.getElementById('groupStatus').value;
        
        if (!groupName) {
            showNotification('El nombre del grupo es obligatorio', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('group_name', groupName);
        formData.append('group_description', groupDescription);
        formData.append('group_status', groupStatus);
        
        if (currentGroupId) {
            formData.append('group_id', currentGroupId);
        }
        
        const url = currentGroupId ? 'actions/update_group.php' : 'actions/create_group.php';
        
        showLoading();
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            hideLoading();
            
            let data;
            try {
                data = JSON.parse(text.trim());
            } catch (e) {
                console.error('Respuesta:', text);
                throw new Error('Respuesta inválida del servidor');
            }
            
            if (data.success) {
                showNotification(data.message || 'Grupo guardado exitosamente', 'success');
                closeGroupModal();
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(data.message || 'Error al guardar el grupo', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showNotification('Error: ' + error.message, 'error');
        });
    }
    
    // Función para editar grupo
    function editGroup(groupId) {
        currentGroupId = groupId;
        document.getElementById('groupModalTitle').textContent = 'Editar Grupo';
        
        showLoading();
        
        fetch(`actions/get_group_details.php?id=${groupId}`)
        .then(response => response.text())
        .then(text => {
            hideLoading();
            
            let data;
            try {
                data = JSON.parse(text.trim());
            } catch (e) {
                console.error('Respuesta:', text);
                throw new Error('Respuesta inválida del servidor');
            }
            
            if (data.success && data.group) {
                document.getElementById('groupId').value = data.group.id;
                document.getElementById('groupName').value = data.group.name;
                document.getElementById('groupDescription').value = data.group.description || '';
                document.getElementById('groupStatus').value = data.group.status;
                
                const modal = document.getElementById('groupModal');
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            } else {
                showNotification(data.message || 'Error al cargar datos del grupo', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showNotification('Error: ' + error.message, 'error');
        });
    }
    
    // Función para ver detalles del grupo
    function viewGroupDetails(groupId) {
        showLoading();
        
        fetch(`actions/get_group_details.php?id=${groupId}`)
        .then(response => response.text())
        .then(text => {
            hideLoading();
            
            let data;
            try {
                data = JSON.parse(text.trim());
            } catch (e) {
                console.error('Respuesta:', text);
                throw new Error('Respuesta inválida del servidor');
            }
            
            if (data.success && data.group) {
                const group = data.group;
                let details = `
                    <strong>Nombre:</strong> ${group.name}<br>
                    <strong>Descripción:</strong> ${group.description || 'Sin descripción'}<br>
                    <strong>Estado:</strong> ${group.status}<br>
                    <strong>Miembros:</strong> ${group.total_members} usuarios<br>
                    <strong>Tipo:</strong> ${group.is_system_group ? 'Sistema' : 'Personalizado'}<br>
                    <strong>Creado:</strong> ${group.created_at_formatted || group.created_at}<br>
                    <strong>Por:</strong> ${group.created_by_name}
                `;
                
                alert(`Detalles del Grupo:\n\n${details.replace(/<br>/g, '\n').replace(/<strong>|<\/strong>/g, '')}`);
            } else {
                showNotification(data.message || 'Error al cargar detalles', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showNotification('Error: ' + error.message, 'error');
        });
    }
    
    // Función para cambiar estado del grupo
    function toggleGroupStatus(groupId, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        const action = newStatus === 'active' ? 'activar' : 'desactivar';
        
        if (!confirm(`¿Está seguro de que desea ${action} este grupo?`)) {
            return;
        }
        
        showLoading();
        
        const formData = new FormData();
        formData.append('group_id', groupId);
        formData.append('status', newStatus);
        
        fetch('actions/toggle_group_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            hideLoading();
            
            let data;
            try {
                data = JSON.parse(text.trim());
            } catch (e) {
                console.error('Respuesta:', text);
                throw new Error('Respuesta inválida del servidor');
            }
            
            if (data.success) {
                showNotification(data.message || `Grupo ${action}do exitosamente`, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(data.message || `Error al ${action} el grupo`, 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showNotification('Error: ' + error.message, 'error');
        });
    }
    
    // Inicialización cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar iconos
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        // Actualizar reloj
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        updateTime();
        setInterval(updateTime, 60000);
        
        // Cerrar modal al hacer clic fuera
        const modal = document.getElementById('groupModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeGroupModal();
                }
            });
        }
        
        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('groupModal');
                if (modal && modal.classList.contains('active')) {
                    closeGroupModal();
                }
            }
        });
    });
    </script>
</body>
</html>