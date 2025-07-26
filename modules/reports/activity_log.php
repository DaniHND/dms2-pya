<?php
// modules/reports/activity_log.php
// Log de actividades con filtros avanzados - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtro
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$userId = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';
$module = $_GET['module'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Función para obtener actividades
function getActivities($currentUser, $dateFrom, $dateTo, $userId, $action, $module, $limit, $offset)
{
    $whereConditions = [];
    $params = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59',
        'limit' => $limit,
        'offset' => $offset
    ];

    // Control de acceso por rol
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "u.company_id = :company_id";
        $params['company_id'] = $currentUser['company_id'];
    }

    // Filtros adicionales
    if (!empty($userId)) {
        $whereConditions[] = "al.user_id = :user_id";
        $params['user_id'] = $userId;
    }

    if (!empty($action)) {
        $whereConditions[] = "al.action = :action";
        $params['action'] = $action;
    }

    if (!empty($module)) {
        $whereConditions[] = "al.module = :module";
        $params['module'] = $module;
    }

    $whereClause = $whereConditions ? 'AND ' . implode(' AND ', $whereConditions) : '';

    $query = "SELECT al.*, 
                     CONCAT(u.first_name, ' ', u.last_name) as user_name,
                     u.username,
                     u.email,
                     c.name as company_name
              FROM activity_logs al 
              LEFT JOIN users u ON al.user_id = u.id 
              LEFT JOIN companies c ON u.company_id = c.id
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereClause
              ORDER BY al.created_at DESC 
              LIMIT :limit OFFSET :offset";

    return fetchAll($query, $params);
}

// Función para obtener total de actividades
function getTotalActivities($currentUser, $dateFrom, $dateTo, $userId, $action, $module)
{
    $whereConditions = [];
    $params = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];

    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "u.company_id = :company_id";
        $params['company_id'] = $currentUser['company_id'];
    }

    if (!empty($userId)) {
        $whereConditions[] = "al.user_id = :user_id";
        $params['user_id'] = $userId;
    }

    if (!empty($action)) {
        $whereConditions[] = "al.action = :action";
        $params['action'] = $action;
    }

    if (!empty($module)) {
        $whereConditions[] = "al.module = :module";
        $params['module'] = $module;
    }

    $whereClause = $whereConditions ? 'AND ' . implode(' AND ', $whereConditions) : '';

    $query = "SELECT COUNT(*) as total
              FROM activity_logs al 
              LEFT JOIN users u ON al.user_id = u.id 
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereClause";

    $result = fetchOne($query, $params);
    return $result['total'] ?? 0;
}

// Función para obtener usuarios
function getUsers($currentUser)
{
    if ($currentUser['role'] === 'admin') {
        $query = "SELECT id, first_name, last_name, email, username FROM users ORDER BY first_name, last_name";
        $params = [];
    } else {
        $query = "SELECT id, first_name, last_name, email, username FROM users WHERE company_id = :company_id ORDER BY first_name, last_name";
        $params = ['company_id' => $currentUser['company_id']];
    }

    return fetchAll($query, $params);
}

// Función para obtener tipos de acciones
function getActionTypes()
{
    $query = "SELECT DISTINCT action FROM activity_logs ORDER BY action";
    return fetchAll($query, []);
}

// Función para obtener módulos
function getModules()
{
    $query = "SELECT DISTINCT module FROM activity_logs ORDER BY module";
    return fetchAll($query, []);
}

// Función para formatear nombres de acciones
function formatActionName($action)
{
    return ucfirst(str_replace('_', ' ', $action));
}

$activities = getActivities($currentUser, $dateFrom, $dateTo, $userId, $action, $module, $limit, $offset);
$totalActivities = getTotalActivities($currentUser, $dateFrom, $dateTo, $userId, $action, $module);
$users = getUsers($currentUser);
$actionTypes = getActionTypes();
$modules = getModules();

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

<body class="dashboard-layout reports-page">
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
                    <button class="btn-icon" onclick="showComingSoon('Configuración')">
                        <i data-feather="settings"></i>
                    </button>
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

            <!-- Estadísticas principales con diseño consistente del index -->
            <div class="reports-stats-grid">
                <div class="reports-stat-card">
                    <div class="reports-stat-icon">
                        <i data-feather="activity"></i>
                    </div>
                    <div class="reports-stat-info">
                        <div class="reports-stat-number"><?php echo number_format($totalActivities); ?></div>
                        <div class="reports-stat-label">Total Actividades</div>
                    </div>
                </div>

                <div class="reports-stat-card">
                    <div class="reports-stat-icon">
                        <i data-feather="calendar"></i>
                    </div>
                    <div class="reports-stat-info">
                        <div class="reports-stat-number"><?php echo date('d/m/Y', strtotime($dateFrom)) . ' - ' . date('d/m/Y', strtotime($dateTo)); ?></div>
                        <div class="reports-stat-label">Período</div>
                    </div>
                </div>

                <?php if (!empty($_GET['date_from']) || !empty($_GET['user_id']) || !empty($_GET['action'])): ?>
                <div class="reports-stat-card">
                    <div class="reports-stat-icon">
                        <i data-feather="users"></i>
                    </div>
                    <div class="reports-stat-info">
                        <div class="reports-stat-number"><?php 
                            $uniqueUsers = array_unique(array_column($activities, 'user_id'));
                            echo count($uniqueUsers);
                        ?></div>
                        <div class="reports-stat-label">Usuarios Únicos</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filtros -->
            <div class="reports-filters" id="filtersPanel" style="display: block;">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" action="">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="date_from">Desde</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" required>
                        </div>
                        <div class="filter-group">
                            <label for="date_to">Hasta</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" required>
                        </div>
                        <?php if ($currentUser['role'] === 'admin'): ?>
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
                        <?php endif; ?>
                        <div class="filter-group">
                            <label for="action">Acción</label>
                            <select id="action" name="action">
                                <option value="">Todas las acciones</option>
                                <?php foreach ($actionTypes as $actionType): ?>
                                    <option value="<?php echo htmlspecialchars($actionType['action']); ?>"
                                        <?php echo $action == $actionType['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(formatActionName($actionType['action'])); ?>
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

            <!-- Tabla de actividades - Solo mostrar si hay filtros aplicados -->
            <?php if (!empty($_GET['date_from']) || !empty($_GET['user_id']) || !empty($_GET['action'])): ?>
                <?php if (!empty($activities)): ?>
                <div class="activity-table-container">
                    <div class="activity-table-wrapper">
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>FECHA/HORA</th>
                                    <th>USUARIO</th>
                                    <th>EMPRESA</th>
                                    <th>DESCRIPCIÓN</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $activity): ?>
                                <tr data-activity-id="<?php echo $activity['id']; ?>">
                                    <td class="date-column">
                                        <div class="date-primary"><?php echo date('d/m/Y', strtotime($activity['created_at'])); ?></div>
                                        <div class="time-secondary"><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></div>
                                    </td>
                                    <td class="user-column">
                                        <div class="user-primary"><?php echo htmlspecialchars($activity['user_name'] ?? ($activity['first_name'] . ' ' . $activity['last_name'])); ?></div>
                                        <div class="user-secondary">@<?php echo htmlspecialchars($activity['username'] ?? 'sistema'); ?></div>
                                    </td>
                                    <td class="company-column">
                                        <?php echo htmlspecialchars($activity['company_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="description-column">
                                        <?php 
                                        $description = formatActionName($activity['action']);
                                        if (!empty($activity['details'])) {
                                            $description = $activity['details'];
                                        }
                                        echo htmlspecialchars($description);
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-btn">
                                    <i data-feather="chevrons-left"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                                    <i data-feather="chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                                    <i data-feather="chevron-right"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="pagination-btn">
                                    <i data-feather="chevrons-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <!-- Mensaje cuando no hay resultados -->
                    <div class="empty-state">
                        <i data-feather="search"></i>
                        <p>No se encontraron actividades con los filtros seleccionados.</p>
                        <p>Prueba modificar los criterios de búsqueda.</p>
                    </div>
                <?php endif; ?>

                <!-- Exportar datos - Solo mostrar si hay resultados -->
                <?php if (!empty($activities)): ?>
                <div class="export-section">
                    <h3>Exportar Datos</h3>
                    <div class="export-buttons">
                        <a href="export.php?type=activity_log&format=csv&<?php echo http_build_query($_GET); ?>" class="export-btn csv-btn">
                            <i data-feather="file-text"></i>
                            Descargar CSV
                        </a>
                        <a href="export.php?type=activity_log&format=excel&<?php echo http_build_query($_GET); ?>" class="export-btn excel-btn">
                            <i data-feather="grid"></i>
                            Descargar Excel
                        </a>
                        <a href="export.php?type=activity_log&format=pdf&<?php echo http_build_query($_GET); ?>" class="export-btn pdf-btn">
                            <i data-feather="file"></i>
                            Descargar PDF
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Scripts -->
    <script>
        // Inicializar Feather Icons
        feather.replace();

        // Actualizar tiempo
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('es-ES', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
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

        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebar && mainContent) {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
            }
        }

        // Función placeholder para próximamente
        function showComingSoon(feature) {
            alert(`${feature} estará disponible próximamente.`);
        }

        // Función placeholder para próximamente
        function showComingSoon(feature) {
            alert(`${feature} estará disponible próximamente.`);
        }

        // Validación de fechas
        document.getElementById('date_from').addEventListener('change', function() {
            const dateFrom = new Date(this.value);
            const dateTo = new Date(document.getElementById('date_to').value);
            
            if (dateFrom > dateTo) {
                document.getElementById('date_to').value = this.value;
            }
        });

        document.getElementById('date_to').addEventListener('change', function() {
            const dateFrom = new Date(document.getElementById('date_from').value);
            const dateTo = new Date(this.value);
            
            if (dateTo < dateFrom) {
                document.getElementById('date_from').value = this.value;
            }
        });

        // Tooltip para detalles largos
        document.querySelectorAll('.details-text').forEach(element => {
            element.addEventListener('click', function() {
                const fullText = this.getAttribute('title');
                if (fullText && fullText.length > 80) {
                    alert(fullText);
                }
            });
        });

        // Resaltar filas al hacer hover
        document.querySelectorAll('.data-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8fafc';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });

        // Auto-submit form con delay
        let filterTimeout;
        document.querySelectorAll('select[name="user_id"], select[name="action"], select[name="module"]').forEach(select => {
            select.addEventListener('change', function() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(() => {
                    // Auto-enviar formulario después de 500ms
                    // this.form.submit();
                }, 500);
            });
        });
    </script>
</body>

</html>

<?php
// Funciones auxiliares para CSS y iconos

function getActionClass($action)
{
    $classes = [
        'login' => 'active',
        'logout' => 'warning',
        'upload' => 'active',
        'download' => 'active',
        'delete' => 'inactive',
        'create' => 'active',
        'update' => 'warning',
        'view' => 'active',
        'share' => 'warning',
        'access_denied' => 'inactive',
        'view_activity_log' => 'active',
        'export_csv' => 'active',
        'export_pdf' => 'active',
        'export_excel' => 'active'
    ];

    return $classes[$action] ?? 'active';
}

function getActionIcon($action)
{
    $icons = [
        'login' => 'log-in',
        'logout' => 'log-out',
        'upload' => 'upload',
        'download' => 'download',
        'delete' => 'trash-2',
        'create' => 'plus',
        'update' => 'edit',
        'view' => 'eye',
        'share' => 'share-2',
        'access_denied' => 'shield-off',
        'view_activity_log' => 'activity',
        'export_csv' => 'file-text',
        'export_pdf' => 'file',
        'export_excel' => 'grid'
    ];

    return $icons[$action] ?? 'activity';
}
?>