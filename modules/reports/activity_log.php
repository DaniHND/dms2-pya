<?php
// modules/reports/activity_log.php
// Log de actividades del sistema - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtrado
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$userId = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';
$page = intval($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Función para obtener actividades con filtros
function getActivities($currentUser, $dateFrom, $dateTo, $userId, $action, $limit, $offset)
{
    $whereConditions = [];
    $params = [];

    // Filtro por fechas
    $whereConditions[] = "al.created_at >= :date_from";
    $whereConditions[] = "al.created_at <= :date_to";
    $params['date_from'] = $dateFrom . ' 00:00:00';
    $params['date_to'] = $dateTo . ' 23:59:59';

    // Filtro por usuario
    if (!empty($userId)) {
        $whereConditions[] = "al.user_id = :user_id";
        $params['user_id'] = $userId;
    }

    // Filtro por acción
    if (!empty($action)) {
        $whereConditions[] = "al.action = :action";
        $params['action'] = $action;
    }

    // Filtro por empresa (si no es admin)
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "u.company_id = :company_id";
        $params['company_id'] = $currentUser['company_id'];
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Query principal
    $query = "SELECT al.*, u.first_name, u.last_name, u.username, c.name as company_name
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              LEFT JOIN companies c ON u.company_id = c.id
              WHERE $whereClause
              ORDER BY al.created_at DESC
              LIMIT :limit OFFSET :offset";

    $params['limit'] = $limit;
    $params['offset'] = $offset;

    return fetchAll($query, $params);
}

// Función para obtener el total de registros
function getTotalActivities($currentUser, $dateFrom, $dateTo, $userId, $action)
{
    $whereConditions = [];
    $params = [];

    // Filtro por fechas
    $whereConditions[] = "al.created_at >= :date_from";
    $whereConditions[] = "al.created_at <= :date_to";
    $params['date_from'] = $dateFrom . ' 00:00:00';
    $params['date_to'] = $dateTo . ' 23:59:59';

    // Filtro por usuario
    if (!empty($userId)) {
        $whereConditions[] = "al.user_id = :user_id";
        $params['user_id'] = $userId;
    }

    // Filtro por acción
    if (!empty($action)) {
        $whereConditions[] = "al.action = :action";
        $params['action'] = $action;
    }

    // Filtro por empresa (si no es admin)
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "u.company_id = :company_id";
        $params['company_id'] = $currentUser['company_id'];
    }

    $whereClause = implode(' AND ', $whereConditions);

    $query = "SELECT COUNT(*) as total
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE $whereClause";

    $result = fetchOne($query, $params);
    return $result['total'] ?? 0;
}

// Función para obtener usuarios para filtro
function getUsers($currentUser)
{
    if ($currentUser['role'] === 'admin') {
        $query = "SELECT id, username, first_name, last_name FROM users WHERE status = 'active' ORDER BY first_name, last_name";
        return fetchAll($query);
    } else {
        $query = "SELECT id, username, first_name, last_name FROM users WHERE company_id = :company_id AND status = 'active' ORDER BY first_name, last_name";
        return fetchAll($query, ['company_id' => $currentUser['company_id']]);
    }
}

// Función para obtener tipos de acciones
function getActionTypes()
{
    $query = "SELECT DISTINCT action FROM activity_logs ORDER BY action";
    return fetchAll($query);
}

$activities = getActivities($currentUser, $dateFrom, $dateTo, $userId, $action, $limit, $offset);
$totalActivities = getTotalActivities($currentUser, $dateFrom, $dateTo, $userId, $action);
$users = getUsers($currentUser);
$actionTypes = getActionTypes();

$totalPages = ceil($totalActivities / $limit);

// Registrar acceso
logActivity($currentUser['id'], 'view_activity_log', 'reports', null, 'Usuario accedió al log de actividades');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Actividades - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reports.css">
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
                <h1>Log de Actividades</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(getFullName()); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido -->
        <div class="reports-content">
            <!-- Navegación de reportes -->
            <div class="reports-nav-breadcrumb">
                <a href="index.php" class="breadcrumb-link">
                    <i data-feather="arrow-left"></i>
                    Volver a Reportes
                </a>
            </div>

            <!-- Filtros -->
            <div class="reports-filters">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" action="">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="date_from">Desde</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="date_to">Hasta</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="user_id">Usuario</label>
                            <select id="user_id" name="user_id">
                                <option value="">Todos los usuarios</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"
                                        <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="action">Acción</label>
                            <select id="action" name="action">
                                <option value="">Todas las acciones</option>
                                <?php foreach ($actionTypes as $actionType): ?>
                                    <option value="<?php echo $actionType['action']; ?>"
                                        <?php echo $action == $actionType['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($actionType['action'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filters-actions">
                        <button type="submit" class="btn-filter">
                            <i data-feather="search"></i>
                            Filtrar
                        </button>
                        <a href="activity_log.php" class="btn-filter secondary">
                            <i data-feather="x"></i>
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Exportación -->
            <div class="export-section">
                <h3>Exportar Datos</h3>
                <div class="export-buttons">
                    <button class="export-btn" onclick="exportData('csv')">
                        <i data-feather="file-text"></i>
                        Exportar CSV
                    </button>
                    <button class="export-btn" onclick="exportData('excel')">
                        <i data-feather="grid"></i>
                        Exportar Excel
                    </button>
                    <button class="export-btn" onclick="printReport()">
                        <i data-feather="printer"></i>
                        Imprimir
                    </button>
                </div>
            </div>

            <!-- Tabla de actividades -->
            <div class="reports-table">
                <h3>Registro de Actividades (<?php echo number_format($totalActivities); ?> registros)</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Usuario</th>
                                <th>Empresa</th>
                                <th>Acción</th>
                                <th>Descripción</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activities)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i data-feather="search"></i>
                                        <p>No se encontraron actividades con los filtros seleccionados</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activities as $activity): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                            <br>
                                            <small>@<?php echo htmlspecialchars($activity['username']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['company_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo getActionClass($activity['action']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($activity['action'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['description'] ?? 'Sin descripción'); ?></td>
                                        <td>
                                            <code><?php echo htmlspecialchars($activity['ip_address'] ?? 'N/A'); ?></code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Mostrando <?php echo number_format(($page - 1) * $limit + 1); ?> -
                            <?php echo number_format(min($page * $limit, $totalActivities)); ?>
                            de <?php echo number_format($totalActivities); ?> registros
                        </div>
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">
                                    <i data-feather="chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                    class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">
                                    <i data-feather="chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Variables de configuración
        var currentFilters = <?php echo json_encode($_GET); ?>;

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);
        });

        function updateTime() {
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                const dateString = now.toLocaleDateString('es-ES', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
                timeElement.textContent = `${dateString} ${timeString}`;
            }
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('active');
            }
        }

        function exportData(format) {
            const url = `export.php?format=${format}&type=activity_log&${new URLSearchParams(currentFilters).toString()}`;
            window.open(url, '_blank');
        }

        function printReport() {
            window.print();
        }

        function showComingSoon(feature) {
            alert(`${feature} - Próximamente`);
        }

        // Responsive
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });
    </script>
</body>

</html>

<?php
// Función auxiliar para obtener clase CSS según acción
function getActionClass($action)
{
    $classes = [
        'login' => 'success',
        'logout' => 'info',
        'upload' => 'success',
        'download' => 'info',
        'delete' => 'error',
        'create' => 'success',
        'update' => 'warning',
        'view' => 'info'
    ];

    return $classes[$action] ?? 'info';
}
?>