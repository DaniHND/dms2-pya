<?php
// modules/reports/user_reports.php
// Reportes por usuario - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtrado
$selectedUserId = $_GET['user_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Función para obtener usuarios con estadísticas
function getUsersWithStats($currentUser, $dateFrom, $dateTo)
{
    if ($currentUser['role'] === 'admin') {
        $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                         u.last_login, u.created_at, c.name as company_name,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.created_at >= :date_from AND al.created_at <= :date_to) as activity_count,
                         (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id 
                          AND d.created_at >= :date_from AND d.created_at <= :date_to) as documents_uploaded,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.action = 'download' AND al.created_at >= :date_from AND al.created_at <= :date_to) as downloads_count
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.status = 'active'
                  ORDER BY activity_count DESC, u.first_name";
        $params = [
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59'
        ];
    } else {
        $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                         u.last_login, u.created_at, c.name as company_name,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.created_at >= :date_from AND al.created_at <= :date_to) as activity_count,
                         (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id 
                          AND d.created_at >= :date_from AND d.created_at <= :date_to) as documents_uploaded,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.action = 'download' AND al.created_at >= :date_from AND al.created_at <= :date_to) as downloads_count
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.status = 'active' AND u.company_id = :company_id
                  ORDER BY activity_count DESC, u.first_name";
        $params = [
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59',
            'company_id' => $currentUser['company_id']
        ];
    }

    return fetchAll($query, $params);
}

// Función para obtener actividad detallada de un usuario
function getUserActivity($userId, $dateFrom, $dateTo, $limit = 20)
{
    $query = "SELECT al.*, 
                     CASE 
                         WHEN al.table_name = 'documents' THEN d.name
                         ELSE NULL
                     END as document_name
              FROM activity_logs al
              LEFT JOIN documents d ON al.table_name = 'documents' AND al.record_id = d.id
              WHERE al.user_id = :user_id 
              AND al.created_at >= :date_from 
              AND al.created_at <= :date_to
              ORDER BY al.created_at DESC
              LIMIT :limit";

    $params = [
        'user_id' => $userId,
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59',
        'limit' => $limit
    ];

    return fetchAll($query, $params);
}

// Función para obtener estadísticas por acción de un usuario
function getUserActionStats($userId, $dateFrom, $dateTo)
{
    $query = "SELECT action, COUNT(*) as count
              FROM activity_logs
              WHERE user_id = :user_id 
              AND created_at >= :date_from 
              AND created_at <= :date_to
              GROUP BY action
              ORDER BY count DESC";

    $params = [
        'user_id' => $userId,
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];

    return fetchAll($query, $params);
}

$users = getUsersWithStats($currentUser, $dateFrom, $dateTo);
$selectedUserActivity = [];
$selectedUserActionStats = [];
$selectedUserInfo = null;

if ($selectedUserId) {
    $selectedUserActivity = getUserActivity($selectedUserId, $dateFrom, $dateTo);
    $selectedUserActionStats = getUserActionStats($selectedUserId, $dateFrom, $dateTo);

    // Buscar información del usuario seleccionado
    foreach ($users as $user) {
        if ($user['id'] == $selectedUserId) {
            $selectedUserInfo = $user;
            break;
        }
    }
}

// Registrar acceso
logActivity($currentUser['id'], 'view_user_reports', 'reports', null, 'Usuario accedió a reportes por usuario');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes por Usuario - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reports.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <h1>Reportes por Usuario</h1>
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
                            <label for="user_id">Usuario Específico</label>
                            <select id="user_id" name="user_id">
                                <option value="">Ver todos los usuarios</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"
                                        <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
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
                        <a href="user_reports.php" class="btn-filter secondary">
                            <i data-feather="x"></i>
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <?php if ($selectedUserId && $selectedUserInfo): ?>
                <!-- Información del usuario seleccionado -->
                <div class="user-detail-section">
                    <div class="user-detail-card">
                        <div class="user-detail-header">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($selectedUserInfo['first_name'], 0, 1) . substr($selectedUserInfo['last_name'], 0, 1)); ?>
                            </div>
                            <div class="user-detail-info">
                                <h2><?php echo htmlspecialchars($selectedUserInfo['first_name'] . ' ' . $selectedUserInfo['last_name']); ?></h2>
                                <p>@<?php echo htmlspecialchars($selectedUserInfo['username']); ?></p>
                                <p><?php echo htmlspecialchars($selectedUserInfo['email']); ?></p>
                                <p><?php echo htmlspecialchars($selectedUserInfo['company_name']); ?> - <?php echo ucfirst($selectedUserInfo['role']); ?></p>
                            </div>
                        </div>

                        <div class="user-stats-grid">
                            <div class="user-stat">
                                <div class="stat-number"><?php echo number_format($selectedUserInfo['activity_count']); ?></div>
                                <div class="stat-label">Actividades</div>
                            </div>
                            <div class="user-stat">
                                <div class="stat-number"><?php echo number_format($selectedUserInfo['documents_uploaded']); ?></div>
                                <div class="stat-label">Documentos Subidos</div>
                            </div>
                            <div class="user-stat">
                                <div class="stat-number"><?php echo number_format($selectedUserInfo['downloads_count']); ?></div>
                                <div class="stat-label">Descargas</div>
                            </div>
                            <div class="user-stat">
                                <div class="stat-number">
                                    <?php echo $selectedUserInfo['last_login'] ? date('d/m/Y', strtotime($selectedUserInfo['last_login'])) : 'Nunca'; ?>
                                </div>
                                <div class="stat-label">Último Acceso</div>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de acciones del usuario -->
                    <div class="user-chart-section">
                        <h3>Distribución de Acciones</h3>
                        <canvas id="userActionsChart"></canvas>
                    </div>

                    <!-- Actividad reciente del usuario -->
                    <div class="user-activity-section">
                        <h3>Actividad Reciente</h3>
                        <div class="activity-list">
                            <?php if (empty($selectedUserActivity)): ?>
                                <div class="empty-state">
                                    <i data-feather="activity"></i>
                                    <p>No hay actividad para mostrar en el rango de fechas seleccionado</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($selectedUserActivity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <?php
                                            $iconMap = [
                                                'login' => 'log-in',
                                                'logout' => 'log-out',
                                                'upload' => 'upload',
                                                'download' => 'download',
                                                'delete' => 'trash-2',
                                                'view' => 'eye',
                                                'create' => 'plus',
                                                'update' => 'edit'
                                            ];
                                            $icon = $iconMap[$activity['action']] ?? 'activity';
                                            ?>
                                            <i data-feather="<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-action">
                                                <span class="status-badge <?php echo getActionClass($activity['action']); ?>">
                                                    <?php echo htmlspecialchars(ucfirst($activity['action'])); ?>
                                                </span>
                                            </div>
                                            <div class="activity-description">
                                                <?php echo htmlspecialchars($activity['description'] ?? 'Sin descripción'); ?>
                                                <?php if ($activity['document_name']): ?>
                                                    <br><small>Documento: <?php echo htmlspecialchars($activity['document_name']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="activity-time">
                                                <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Tabla de usuarios con estadísticas -->
                <div class="reports-table">
                    <h3>Usuarios y sus Estadísticas (<?php echo count($users); ?> usuarios)</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Empresa</th>
                                    <th>Rol</th>
                                    <th>Actividades</th>
                                    <th>Documentos</th>
                                    <th>Descargas</th>
                                    <th>Último Acceso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="empty-state">
                                            <i data-feather="users"></i>
                                            <p>No se encontraron usuarios</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-small">
                                                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                        <br>
                                                        <small>@<?php echo htmlspecialchars($user['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['company_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="role-badge <?php echo $user['role']; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="stat-badge"><?php echo number_format($user['activity_count']); ?></span>
                                            </td>
                                            <td>
                                                <span class="stat-badge"><?php echo number_format($user['documents_uploaded']); ?></span>
                                            </td>
                                            <td>
                                                <span class="stat-badge"><?php echo number_format($user['downloads_count']); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($user['last_login']): ?>
                                                    <?php echo date('d/m/Y H:i', strtotime($user['last_login'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Nunca</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?user_id=<?php echo $user['id']; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>"
                                                    class="btn-action" title="Ver detalles">
                                                    <i data-feather="eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

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
        </div>
    </main>

    <script>
        // Variables de configuración
        var currentFilters = <?php echo json_encode($_GET); ?>;
        var userActionStats = <?php echo json_encode($selectedUserActionStats); ?>;

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);

            <?php if ($selectedUserId && !empty($selectedUserActionStats)): ?>
                initUserActionsChart();
            <?php endif; ?>
        });

        function initUserActionsChart() {
            const ctx = document.getElementById('userActionsChart').getContext('2d');

            const labels = userActionStats.map(item => item.action.charAt(0).toUpperCase() + item.action.slice(1));
            const data = userActionStats.map(item => parseInt(item.count));

            const colors = [
                '#8B4513', '#A0522D', '#CD853F', '#D2B48C', '#DEB887',
                '#F4A460', '#DAA520', '#B8860B', '#9ACD32', '#6B8E23'
            ];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors.slice(0, data.length),
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }

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
            const url = `export.php?format=${format}&type=user_reports&${new URLSearchParams(currentFilters).toString()}`;
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