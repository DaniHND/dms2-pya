<?php
/*
 * modules/groups/index.php
 * Módulo de Gestión de Grupos - DMS2
 * Estructura integrada con el diseño del sistema
 */

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar sesión y permisos
SessionManager::requireRole('admin');
$currentUser = SessionManager::getCurrentUser();

// Obtener conexión a la base de datos
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo establecer conexión con la base de datos");
    }
} catch (Exception $e) {
    error_log("Error de conexión a base de datos en groups/index.php: " . $e->getMessage());
    die("Error de conexión a la base de datos. Por favor, contacte al administrador.");
}

// Configuración de paginación
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Filtros
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'is_system' => $_GET['is_system'] ?? ''
];

// Construir consulta base
$whereConditions = ["1=1"];
$params = [];

if (!empty($filters['search'])) {
    $whereConditions[] = "(ug.name LIKE ? OR ug.description LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

if (!empty($filters['status'])) {
    $whereConditions[] = "ug.status = ?";
    $params[] = $filters['status'];
}

if ($filters['is_system'] !== '') {
    $whereConditions[] = "ug.is_system_group = ?";
    $params[] = $filters['is_system'] ? 1 : 0;
}

$whereClause = implode(' AND ', $whereConditions);

// Obtener estadísticas
try {
    $statsQuery = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                    SUM(CASE WHEN is_system_group = 1 THEN 1 ELSE 0 END) as system_groups,
                    SUM(CASE WHEN is_system_group = 0 THEN 1 ELSE 0 END) as custom_groups
                   FROM user_groups ug 
                   WHERE " . $whereClause;
    
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas de grupos: " . $e->getMessage());
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'system_groups' => 0,
        'custom_groups' => 0
    ];
}

// Obtener grupos con estadísticas
try {
    $groupsQuery = "SELECT ug.*,
                    COUNT(DISTINCT ugm.user_id) as total_members,
                    COUNT(DISTINCT CASE WHEN u.status = 'active' THEN ugm.user_id END) as active_members,
                    COUNT(DISTINCT u.company_id) as companies_represented,
                    COUNT(DISTINCT u.department_id) as departments_represented,
                    CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name
                    FROM user_groups ug
                    LEFT JOIN user_group_members ugm ON ug.id = ugm.group_id
                    LEFT JOIN users u ON ugm.user_id = u.id AND u.status != 'deleted'
                    LEFT JOIN users creator ON ug.created_by = creator.id
                    WHERE " . $whereClause . "
                    GROUP BY ug.id
                    ORDER BY ug.created_at DESC
                    LIMIT ? OFFSET ?";
    
    $allParams = array_merge($params, [$itemsPerPage, $offset]);
    $stmt = $pdo->prepare($groupsQuery);
    $stmt->execute($allParams);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total para paginación
    $countQuery = "SELECT COUNT(*) as total FROM user_groups ug WHERE " . $whereClause;
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalItems = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalItems / $itemsPerPage);
    
} catch (Exception $e) {
    error_log("Error obteniendo grupos: " . $e->getMessage());
    $groups = [];
    $totalItems = 0;
    $totalPages = 0;
}

// Obtener datos para formularios
try {
    $companies = $pdo->query("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $documentTypes = $pdo->query("SELECT id, name FROM document_types WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $availableUsers = $pdo->query("SELECT id, username, first_name, last_name, company_id, department_id FROM users WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error obteniendo datos para formularios: " . $e->getMessage());
    $companies = [];
    $departments = [];
    $documentTypes = [];
    $availableUsers = [];
}

// Funciones helper
function formatPermissions($permissionsJson) {
    $permissions = json_decode($permissionsJson, true);
    if (!$permissions) return '<span class="text-muted">Sin permisos específicos</span>';
    
    $formatted = [];
    foreach ($permissions as $module => $actions) {
        $moduleActions = [];
        foreach ($actions as $action => $allowed) {
            if ($allowed) {
                $moduleActions[] = ucfirst($action);
            }
        }
        if (!empty($moduleActions)) {
            $formatted[] = '<strong>' . ucfirst($module) . ':</strong> ' . implode(', ', $moduleActions);
        }
    }
    
    return !empty($formatted) ? implode('<br>', $formatted) : '<span class="text-muted">Sin permisos</span>';
}

function formatRestrictions($restrictionsJson) {
    $restrictions = json_decode($restrictionsJson, true);
    if (!$restrictions) return '<span class="text-muted">Sin restricciones</span>';
    
    $formatted = [];
    
    if (isset($restrictions['companies'])) {
        if ($restrictions['companies'] === 'all') {
            $formatted[] = '<strong>Empresas:</strong> Todas';
        } elseif ($restrictions['companies'] === 'user_company') {
            $formatted[] = '<strong>Empresas:</strong> Solo su empresa';
        } elseif (is_array($restrictions['companies'])) {
            $formatted[] = '<strong>Empresas:</strong> ' . count($restrictions['companies']) . ' específicas';
        }
    }
    
    if (isset($restrictions['departments'])) {
        if ($restrictions['departments'] === 'all') {
            $formatted[] = '<strong>Departamentos:</strong> Todos';
        } elseif ($restrictions['departments'] === 'user_department') {
            $formatted[] = '<strong>Departamentos:</strong> Solo su departamento';
        } elseif (is_array($restrictions['departments'])) {
            $formatted[] = '<strong>Departamentos:</strong> ' . count($restrictions['departments']) . ' específicos';
        }
    }
    
    return !empty($formatted) ? implode('<br>', $formatted) : '<span class="text-muted">Sin restricciones</span>';
}

// Registrar actividad
logActivity($currentUser['id'], 'view_groups', 'groups', null, 'Usuario accedió al módulo de grupos');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Grupos - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/groups.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">
    <script src="https://unpkg.com/feather-icons"></script>
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
                    <div class="user-name-header"><?php echo htmlspecialchars(getFullName()); ?></div>
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

        <!-- Contenedor principal -->
        <div class="container">
            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-content">
                        <div class="stat-number"><?= $stats['total'] ?></div>
                        <div class="stat-label">Total Grupos</div>
                    </div>
                    <div class="stat-icon">
                        <i data-feather="users"></i>
                    </div>
                </div>

                <div class="stat-card stat-success">
                    <div class="stat-content">
                        <div class="stat-number"><?= $stats['active'] ?></div>
                        <div class="stat-label">Activos</div>
                    </div>
                    <div class="stat-icon">
                        <i data-feather="check-circle"></i>
                    </div>
                </div>

                <div class="stat-card stat-warning">
                    <div class="stat-content">
                        <div class="stat-number"><?= $stats['inactive'] ?></div>
                        <div class="stat-label">Inactivos</div>
                    </div>
                    <div class="stat-icon">
                        <i data-feather="pause-circle"></i>
                    </div>
                </div>

                <div class="stat-card stat-info">
                    <div class="stat-content">
                        <div class="stat-number"><?= $stats['system_groups'] ?></div>
                        <div class="stat-label">Del Sistema</div>
                    </div>
                    <div class="stat-icon">
                        <i data-feather="shield"></i>
                    </div>
                </div>

                <div class="stat-card stat-secondary">
                    <div class="stat-content">
                        <div class="stat-number"><?= $stats['custom_groups'] ?></div>
                        <div class="stat-label">Personalizados</div>
                    </div>
                    <div class="stat-icon">
                        <i data-feather="settings"></i>
                    </div>
                </div>
            </div>

            <!-- Botón crear -->
            <div class="create-button-section">
                <button class="create-btn" onclick="showCreateGroupModal()">
                    <i data-feather="plus"></i>
                    Crear Nuevo Grupo
                </button>
            </div>

            <!-- Filtros -->
            <div class="filters-section">
                <div class="filters-card">
                    <form method="GET" class="filters-form">
                        <div class="filter-group">
                            <label class="filter-label">Buscar:</label>
                            <input type="text" class="filter-input" name="search" 
                                   placeholder="Nombre o descripción..." value="<?= htmlspecialchars($filters['search']) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Estado:</label>
                            <select class="filter-select" name="status">
                                <option value="">Todos</option>
                                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Tipo:</label>
                            <select class="filter-select" name="is_system">
                                <option value="">Todos</option>
                                <option value="1" <?= $filters['is_system'] === '1' ? 'selected' : '' ?>>Sistema</option>
                                <option value="0" <?= $filters['is_system'] === '0' ? 'selected' : '' ?>>Personalizado</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn-filter">
                                <i data-feather="search"></i> Filtrar
                            </button>
                            <a href="index.php" class="btn-clear">
                                <i data-feather="x"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla -->
            <div class="table-section">
                <div class="table-header">
                    <h2>Lista de Grupos</h2>
                    <div class="table-actions">
                        <button class="btn-export" onclick="exportGroups()">
                            <i data-feather="download"></i>
                            Exportar
                        </button>
                    </div>
                </div>

                <?php if (empty($groups)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i data-feather="users"></i>
                        </div>
                        <h3>No hay grupos disponibles</h3>
                        <p>Crea el primer grupo haciendo clic en "Crear Nuevo Grupo"</p>
                        <button class="btn-primary" onclick="showCreateGroupModal()">
                            <i data-feather="plus"></i>
                            Crear Primer Grupo
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="5%"><input type="checkbox" id="selectAll"></th>
                                    <th width="25%">Grupo</th>
                                    <th width="15%">Miembros</th>
                                    <th width="25%">Permisos</th>
                                    <th width="20%">Restricciones</th>
                                    <th width="10%">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groups as $group): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="group-checkbox" value="<?= $group['id'] ?>">
                                        </td>
                                        <td>
                                            <div class="group-info">
                                                <div class="group-name">
                                                    <?= htmlspecialchars($group['name']) ?>
                                                    <?php if ($group['is_system_group']): ?>
                                                        <span class="badge badge-info">Sistema</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="group-description">
                                                    <?= htmlspecialchars($group['description'] ?? 'Sin descripción') ?>
                                                </div>
                                                <div class="group-meta">
                                                    <span class="status-badge status-<?= $group['status'] ?>">
                                                        <?= ucfirst($group['status']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="members-info">
                                                <div class="member-count"><?= $group['total_members'] ?></div>
                                                <div class="member-details">
                                                    <?= $group['active_members'] ?> activos
                                                    <?php if ($group['total_members'] > 0): ?>
                                                        <br><small>
                                                            <?= $group['companies_represented'] ?> empresas, 
                                                            <?= $group['departments_represented'] ?> depts
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="permissions-display">
                                                <?= formatPermissions($group['module_permissions'] ?? '{}') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="restrictions-display">
                                                <?= formatRestrictions($group['access_restrictions'] ?? '{}') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="table-actions-cell">
                                                <button class="btn-action btn-view" 
                                                        onclick="viewGroupDetails(<?= $group['id'] ?>)">
                                                    <i data-feather="eye"></i>
                                                </button>
                                                <button class="btn-action btn-users" 
                                                        onclick="manageGroupUsers(<?= $group['id'] ?>)">
                                                    <i data-feather="user-plus"></i>
                                                </button>
                                                <?php if (!$group['is_system_group']): ?>
                                                    <button class="btn-action btn-edit" 
                                                            onclick="editGroup(<?= $group['id'] ?>)">
                                                        <i data-feather="edit"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn-action btn-toggle" 
                                                        onclick="toggleGroupStatus(<?= $group['id'] ?>, '<?= $group['status'] ?>')">
                                                    <i data-feather="<?= $group['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-section">
                    <div class="pagination-info">
                        Mostrando <?= (($currentPage - 1) * $itemsPerPage) + 1 ?> - <?= min($currentPage * $itemsPerPage, $totalItems) ?> de <?= $totalItems ?> grupos
                    </div>
                    <div class="pagination-controls">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?= $i ?><?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= !empty($_GET['status']) ? '&status=' . urlencode($_GET['status']) : '' ?><?= !empty($_GET['is_system']) ? '&is_system=' . urlencode($_GET['is_system']) : '' ?>" 
                               class="pagination-link <?= $i === $currentPage ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Scripts -->
    <script src="../../assets/js/dashboard.js"></script>
    <script src="../../assets/js/groups.js"></script>
    <script>
        // Funciones específicas del módulo
        function showCreateGroupModal() {
            alert('Crear grupo - Funcionalidad próximamente');
        }

        function viewGroupDetails(id) {
            alert('Ver detalles del grupo ID: ' + id);
        }

        function editGroup(id) {
            alert('Editar grupo ID: ' + id);
        }

        function manageGroupUsers(id) {
            alert('Gestionar usuarios del grupo ID: ' + id);
        }

        function toggleGroupStatus(id, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactivo' : 'activo';
            if (confirm(`¿Está seguro de que desea ${newStatus === 'activo' ? 'activar' : 'desactivar'} este grupo?`)) {
                alert(`Cambiar estado del grupo ID: ${id} a ${newStatus}`);
            }
        }

        function exportGroups() {
            alert('Exportar grupos - Funcionalidad próximamente');
        }

        // Actualizar reloj
        function updateTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('es-ES');
            const element = document.getElementById('currentTime');
            if (element) {
                element.textContent = timeStr;
            }
        }

        setInterval(updateTime, 1000);
        updateTime();

        // Inicializar feather icons
        feather.replace();

        // Checkbox "Seleccionar Todos"
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.group-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    </script>
    
</body>

</html>