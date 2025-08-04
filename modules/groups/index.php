<?php
/*
 * modules/groups/index.php
 * Módulo de Gestión de Grupos con diseño consistente
 */

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
    
    // Costruir consulta con filtros
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
    
    // Obtener grupos con información adicional
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
    
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">
    
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
    :root {
        --dms-primary: #8B4513;
        --dms-primary-hover: #654321;
        --dms-success: #10b981;
        --dms-warning: #f59e0b;
        --dms-danger: #ef4444;
        --dms-info: #3b82f6;
        --dms-bg: #f8fafc;
        --dms-card-bg: #ffffff;
        --dms-border: #e2e8f0;
        --dms-text: #1e293b;
        --dms-text-muted: #64748b;
        --dms-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        --dms-radius: 12px;
    }

    .groups-container {
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

    .btn-create-group {
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

    .btn-create-group:hover {
        background: var(--dms-primary-hover);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4);
    }

    /* Filtros */
    .filters-section {
        background: var(--dms-card-bg);
        border-radius: var(--dms-radius);
        padding: 24px;
        margin-bottom: 24px;
        border: 1px solid var(--dms-border);
        box-shadow: var(--dms-shadow);
    }

    .filters-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--dms-text);
        margin-bottom: 16px;
    }

    .filters-row {
        display: grid;
        grid-template-columns: 1fr 200px 200px;
        gap: 16px;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .filter-label {
        font-weight: 500;
        color: var(--dms-text);
        font-size: 0.875rem;
    }

    .filter-input,
    .filter-select {
        padding: 12px 16px;
        border: 1px solid var(--dms-border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: white;
    }

    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: var(--dms-primary);
        box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
    }

    .btn-clear {
        background: #6b7280;
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .btn-clear:hover {
        background: #4b5563;
    }

    /* Lista de grupos */
    .groups-list {
        background: var(--dms-card-bg);
        border-radius: var(--dms-radius);
        border: 1px solid var(--dms-border);
        box-shadow: var(--dms-shadow);
        overflow: hidden;
    }

    .list-header {
        background: #f8fafc;
        padding: 16px 24px;
        border-bottom: 1px solid var(--dms-border);
        font-weight: 600;
        color: var(--dms-text);
        font-size: 1rem;
    }

    .groups-table {
        width: 100%;
        border-collapse: collapse;
    }

    .groups-table th {
        background: #f8fafc;
        padding: 16px 24px;
        text-align: left;
        font-weight: 600;
        color: var(--dms-text);
        font-size: 0.875rem;
        border-bottom: 1px solid var(--dms-border);
    }

    .groups-table td {
        padding: 16px 24px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .groups-table tr:hover {
        background: #f8fafc;
    }

    .group-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .group-icon {
        width: 40px;
        height: 40px;
        background: var(--dms-primary);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .group-details {
        flex: 1;
    }

    .group-name {
        font-weight: 600;
        color: var(--dms-text);
        font-size: 0.875rem;
        margin-bottom: 2px;
    }

    .group-description {
        font-size: 0.75rem;
        color: var(--dms-text-muted);
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-active {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .status-inactive {
        background: rgba(107, 114, 128, 0.1);
        color: #4b5563;
    }

    .members-count {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.875rem;
        color: var(--dms-text);
    }

    .members-number {
        background: var(--dms-primary);
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        min-width: 24px;
        text-align: center;
    }

    .actions-cell {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .btn-action {
        padding: 8px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }

    .btn-view {
        background: rgba(59, 130, 246, 0.1);
        color: #2563eb;
    }

    .btn-view:hover {
        background: #2563eb;
        color: white;
    }

    .btn-edit {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    .btn-edit:hover {
        background: #d97706;
        color: white;
    }

    .btn-delete {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    .btn-delete:hover {
        background: #dc2626;
        color: white;
    }

    .btn-toggle {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .btn-toggle:hover {
        background: #059669;
        color: white;
    }

    .btn-toggle.inactive {
        background: rgba(107, 114, 128, 0.1);
        color: #4b5563;
    }

    .btn-toggle.inactive:hover {
        background: #4b5563;
        color: white;
    }

    /* Paginación */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 24px;
        padding: 20px;
    }

    .pagination-btn {
        padding: 8px 12px;
        border: 1px solid var(--dms-border);
        background: white;
        color: var(--dms-text);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        font-size: 0.875rem;
    }

    .pagination-btn:hover {
        background: var(--dms-primary);
        color: white;
        border-color: var(--dms-primary);
    }

    .pagination-btn.active {
        background: var(--dms-primary);
        color: white;
        border-color: var(--dms-primary);
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    @media (max-width: 768px) {
        .groups-container {
            margin-left: 0;
            padding: 16px;
        }
        
        .filters-row {
            grid-template-columns: 1fr;
        }
        
        .groups-table {
            font-size: 0.875rem;
        }
        
        .groups-table th,
        .groups-table td {
            padding: 12px;
        }
    }
    </style>
</head>

<body class="dashboard-layout">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="groups-container">
            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Gestión de Grupos</h1>
                    <p class="page-subtitle">Administrar grupos de usuarios y permisos del sistema</p>
                </div>
                
                <button class="btn-create-group" onclick="showCreateGroupModal()">
                    <i data-feather="plus"></i>
                    Crear Grupo
                </button>
            </div>
            
            <!-- Filtros -->
            <div class="filters-section">
                <h3 class="filters-title">Filtros de Búsqueda</h3>
                
                <form method="GET" class="filters-row">
                    <div class="filter-group">
                        <label class="filter-label">Buscar Grupo</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Nombre o descripción..." 
                               value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Estado</label>
                        <select name="status" class="filter-select">
                            <option value="">Todos los estados</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="button" class="btn-clear" onclick="clearFilters()">
                            <i data-feather="x"></i>
                            Limpiar
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Lista de grupos -->
            <div class="groups-list">
                <div class="list-header">
                    Grupos de Usuarios (<?php echo $totalItems; ?> registros)
                </div>
                
                <table class="groups-table">
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
                        <?php if (empty($groups)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--dms-text-muted); font-style: italic;">
                                    No se encontraron grupos que coincidan con los filtros
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($groups as $group): ?>
                                <tr>
                                    <td>
                                        <div class="group-info">
                                            <div class="group-icon">
                                                <i data-feather="<?php echo $group['is_system_group'] ? 'shield' : 'users'; ?>"></i>
                                            </div>
                                            <div class="group-details">
                                                <div class="group-name"><?php echo htmlspecialchars($group['name']); ?></div>
                                                <div class="group-description">
                                                    <?php echo htmlspecialchars($group['description'] ?: 'Sin descripción'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="members-count">
                                            <i data-feather="users"></i>
                                            <span class="members-number"><?php echo $group['total_members']; ?></span>
                                            <span>miembros</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $group['status']; ?>">
                                            <?php echo $group['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($group['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <a href="permissions.php?group=<?php echo $group['id']; ?>&tab=members" 
                                               class="btn-action btn-view" title="Ver Miembros">
                                                <i data-feather="users"></i>
                                            </a>
                                            
                                            <a href="permissions.php?group=<?php echo $group['id']; ?>&tab=permissions" 
                                               class="btn-action btn-edit" title="Configurar Permisos">
                                                <i data-feather="shield"></i>
                                            </a>
                                            
                                            <button class="btn-action btn-toggle <?php echo $group['status'] === 'inactive' ? 'inactive' : ''; ?>" 
                                                    onclick="toggleGroupStatus(<?php echo $group['id']; ?>)"
                                                    title="<?php echo $group['status'] === 'active' ? 'Desactivar' : 'Activar'; ?>">
                                                <i data-feather="<?php echo $group['status'] === 'active' ? 'eye-off' : 'eye'; ?>"></i>
                                            </button>
                                            
                                            <?php if (!$group['is_system_group']): ?>
                                                <button class="btn-action btn-delete" 
                                                        onclick="deleteGroup(<?php echo $group['id']; ?>)"
                                                        title="Eliminar Grupo">
                                                    <i data-feather="trash-2"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>" 
                               class="pagination-btn">
                                <i data-feather="chevron-left"></i>
                                Anterior
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>" 
                               class="pagination-btn <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>" 
                               class="pagination-btn">
                                Siguiente
                                <i data-feather="chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        feather.replace();
    });
    
    function clearFilters() {
        window.location.href = 'index.php';
    }
    
    function showCreateGroupModal() {
        // Por ahora redirigir a una página de creación
        alert('Funcionalidad de crear grupo - próximamente');
    }
    
    function toggleGroupStatus(groupId) {
        if (confirm('¿Está seguro de cambiar el estado de este grupo?')) {
            fetch('actions/toggle_group_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `group_id=${groupId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error al cambiar estado');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
            });
        }
    }
    
    function deleteGroup(groupId) {
        if (confirm('¿Está seguro de eliminar este grupo? Esta acción no se puede deshacer.')) {
            fetch('actions/delete_group.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `group_id=${groupId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error al eliminar grupo');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
            });
        }
    }
    </script>
</body>
</html>