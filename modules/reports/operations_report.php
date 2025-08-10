<?php
require_once '../../bootstrap.php';
// modules/reports/operations_report.php
// Seguimiento de operaciones del sistema - DMS2

// require_once '../../config/session.php'; // Cargado por bootstrap
// require_once '../../config/database.php'; // Cargado por bootstrap

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtrado
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$operation = $_GET['operation'] ?? '';

// Función para obtener estadísticas de operaciones
function getOperationsStats($currentUser, $dateFrom, $dateTo)
{
    $whereCondition = "";
    $params = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];

    // Filtro por empresa si no es admin
    if ($currentUser['role'] !== 'admin') {
        $whereCondition = "AND u.company_id = :company_id";
        $params['company_id'] = $currentUser['company_id'];
    }

    $stats = [];

    // Operaciones por tipo
    $query = "SELECT al.action, COUNT(*) as count
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereCondition
              GROUP BY al.action
              ORDER BY count DESC";

    $stats['by_action'] = fetchAll($query, $params);

    // Operaciones por día
    $query = "SELECT DATE(al.created_at) as date, COUNT(*) as count
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereCondition
              GROUP BY DATE(al.created_at)
              ORDER BY date";

    $stats['by_date'] = fetchAll($query, $params);

    // Operaciones por hora del día
    $query = "SELECT HOUR(al.created_at) as hour, COUNT(*) as count
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereCondition
              GROUP BY HOUR(al.created_at)
              ORDER BY hour";

    $stats['by_hour'] = fetchAll($query, $params);

    // Top usuarios más activos
    $query = "SELECT u.first_name, u.last_name, u.username, COUNT(*) as operations_count
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereCondition
              GROUP BY al.user_id
              ORDER BY operations_count DESC
              LIMIT 10";

    $stats['top_users'] = fetchAll($query, $params);

    return $stats;
}

// Función para obtener operaciones detalladas
function getDetailedOperations($currentUser, $dateFrom, $dateTo, $operation, $limit = 100)
{
    $whereConditions = [];
    $params = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];

    $whereConditions[] = "al.created_at >= :date_from";
    $whereConditions[] = "al.created_at <= :date_to";

    // Filtro por empresa si no es admin
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "u.company_id = :company_id";
        $params['company_id'] = $currentUser['company_id'];
    }

    // Filtro por tipo de operación
    if (!empty($operation)) {
        $whereConditions[] = "al.action = :operation";
        $params['operation'] = $operation;
    }

    $whereClause = implode(' AND ', $whereConditions);

    $query = "SELECT al.*, u.first_name, u.last_name, u.username, u.email,
                     al.details as description, al.ip_address,
                     CASE WHEN al.table_name IS NOT NULL THEN al.table_name ELSE 'Sistema' END as target_name
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE $whereClause
              ORDER BY al.created_at DESC
              LIMIT :limit";

    $params['limit'] = $limit;

    return fetchAll($query, $params);
}

// Función para obtener métricas de rendimiento
function getPerformanceMetrics($currentUser, $dateFrom, $dateTo)
{
    $whereCondition = "";
    $params = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];

    if ($currentUser['role'] !== 'admin') {
        $whereCondition = "AND u.company_id = :company_id";
        $params['company_id'] = $currentUser['company_id'];
    }

    $metrics = [];

    // Total de operaciones
    $query = "SELECT COUNT(*) as total
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereCondition";

    $result = fetchOne($query, $params);
    $metrics['total_operations'] = $result['total'] ?? 0;

    // Promedio diario
    $days = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400);
    $metrics['daily_average'] = round($metrics['total_operations'] / $days, 2);

    // Pico de actividad (hora con más operaciones)
    $query = "SELECT HOUR(al.created_at) as peak_hour, COUNT(*) as count
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereCondition
              GROUP BY HOUR(al.created_at)
              ORDER BY count DESC
              LIMIT 1";

    $result = fetchOne($query, $params);
    $metrics['peak_hour'] = $result['peak_hour'] ?? 0;
    $metrics['peak_count'] = $result['count'] ?? 0;

    // Usuarios únicos activos
    $query = "SELECT COUNT(DISTINCT al.user_id) as unique_users
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereCondition";

    $result = fetchOne($query, $params);
    $metrics['unique_users'] = $result['unique_users'] ?? 0;

    return $metrics;
}

$stats = getOperationsStats($currentUser, $dateFrom, $dateTo);
$operations = getDetailedOperations($currentUser, $dateFrom, $dateTo, $operation);
$metrics = getPerformanceMetrics($currentUser, $dateFrom, $dateTo);

// Registrar acceso
logActivity($currentUser['id'], 'view_operations_report', 'reports', null, 'Usuario accedió al reporte de operaciones');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Operaciones - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reports.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">
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
                <h1>Seguimiento de Operaciones</h1>
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

            <!-- Métricas de rendimiento -->
            <div class="performance-metrics">
                <h3>Métricas de Rendimiento</h3>
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i data-feather="activity"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo number_format($metrics['total_operations']); ?></div>
                            <div class="metric-label">Total Operaciones</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">
                            <i data-feather="trending-up"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo number_format($metrics['daily_average'], 1); ?></div>
                            <div class="metric-label">Promedio Diario</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">
                            <i data-feather="clock"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo str_pad($metrics['peak_hour'], 2, '0', STR_PAD_LEFT); ?>:00</div>
                            <div class="metric-label">Hora Pico</div>
                            <div class="metric-sublabel"><?php echo $metrics['peak_count']; ?> operaciones</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">
                            <i data-feather="users"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo number_format($metrics['unique_users']); ?></div>
                            <div class="metric-label">Usuarios Activos</div>
                        </div>
                    </div>
                </div>
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
                            <label for="operation">Tipo de Operación</label>
                            <select id="operation" name="operation">
                                <option value="">Todas las operaciones</option>
                                <?php if (isset($stats['by_action']) && is_array($stats['by_action'])): ?>
                                    <?php foreach ($stats['by_action'] as $actionStat): ?>
                                        <option value="<?php echo $actionStat['action']; ?>"
                                            <?php echo $operation == $actionStat['action'] ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($actionStat['action']); ?> (<?php echo $actionStat['count']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filters-actions">
                        <button type="submit" class="btn-filter">
                            <i data-feather="search"></i>
                            Filtrar
                        </button>
                        <a href="operations_report.php" class="btn-filter secondary">
                            <i data-feather="x"></i>
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Gráficos de operaciones -->
            <div class="charts-section">
                <div class="chart-row">
                    <div class="chart-container">
                        <h3>Operaciones por Tipo</h3>
                        <canvas id="operationsByTypeChart"></canvas>
                    </div>

                    <div class="chart-container">
                        <h3>Actividad por Hora del Día</h3>
                        <canvas id="operationsByHourChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h3>Operaciones por Día</h3>
                        <canvas id="operationsByDateChart"></canvas>
                    </div>

                    <div class="top-users-section">
                        <h3>Usuarios Más Activos</h3>
                        <div class="top-users-list">
                            <?php if (empty($stats['top_users'])): ?>
                                <div class="empty-state">
                                    <i data-feather="users"></i>
                                    <p>No hay usuarios activos en el período seleccionado</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($stats['top_users'] as $index => $user): ?>
                                    <div class="top-user-item">
                                        <div class="user-rank"><?php echo $index + 1; ?></div>
                                        <div class="user-info">
                                            <div class="user-name">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </div>
                                            <div class="user-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                                        </div>
                                        <div class="user-count">
                                            <span class="count-number"><?php echo number_format($user['operations_count']); ?></span>
                                            <span class="count-label">operaciones</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de operaciones detalladas -->
            <div class="reports-table">
                <h3>Operaciones Detalladas (<?php echo count($operations); ?> registros)</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Usuario</th>
                                <th>Operación</th>
                                <th>Objetivo</th>
                                <th>Descripción</th>
                                <th>IP</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($operations)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i data-feather="activity"></i>
                                        <p>No se encontraron operaciones con los filtros seleccionados</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($operations as $op): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($op['created_at'])); ?></td>
                                        <td>
                                            <div class="user-cell">
                                                <strong><?php echo htmlspecialchars($op['first_name'] . ' ' . $op['last_name']); ?></strong>
                                                <br>
                                                <small>@<?php echo htmlspecialchars($op['username']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo getActionClass($op['action']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($op['action'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($op['target_name']): ?>
                                                <strong><?php echo htmlspecialchars($op['target_name']); ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="description-cell">
                                                <?php echo htmlspecialchars($op['description'] ?? 'Sin descripción'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($op['ip_address'] ?? 'N/A'); ?></code>
                                        </td>
                                        <td>
                                            <span class="status-badge success">
                                                <i data-feather="check"></i>
                                                Completada
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Exportación -->
            <div class="export-section">
                <h3>Exportar Datos</h3>
                <div class="export-buttons">
                    <button class="export-btn" onclick="exportarDatos('csv')">
                        <i data-feather="file-text"></i>
                        Descargar CSV
                    </button>
                    <button class="export-btn" onclick="exportarDatos('excel')">
                        <i data-feather="grid"></i>
                        Descargar Excel
                    </button>
                    <button class="export-btn" onclick="exportarDatos('pdf')">
                        <i data-feather="file"></i>
                        Descargar PDF
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal para vista previa del PDF -->
    <div id="pdfModal" class="pdf-modal">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header">
                <h3 class="pdf-modal-title">Vista Previa del PDF - Seguimiento de Operaciones</h3>
                <button class="pdf-modal-close" onclick="cerrarModalPDF()">&times;</button>
            </div>
            <div class="pdf-modal-body">
                <div class="pdf-loading" id="pdfLoading">
                    <div class="spinner"></div>
                    <p>Generando vista previa del PDF...</p>
                </div>
                <iframe id="pdfIframe" class="pdf-iframe" style="display: none;"></iframe>
            </div>
        </div>
    </div>

    <script>
        // Variables de configuración
        var currentFilters = <?php echo json_encode($_GET); ?>;
        var operationsByType = <?php echo json_encode($stats['by_action']); ?>;
        var operationsByHour = <?php echo json_encode($stats['by_hour']); ?>;
        var operationsByDate = <?php echo json_encode($stats['by_date']); ?>;

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);
            initCharts();
        });

        function initCharts() {
            initOperationsByTypeChart();
            initOperationsByHourChart();
            initOperationsByDateChart();
        }

        function initOperationsByTypeChart() {
            const ctx = document.getElementById('operationsByTypeChart').getContext('2d');
            
            const labels = operationsByType.map(item => item.action);
            const data = operationsByType.map(item => parseInt(item.count));

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            '#4e342e',    // Café oscuro
                            '#A0522D',    // Café medio  
                            '#654321',    // Café muy oscuro
                            '#D2B48C',    // Beige
                            '#CD853F',    // Café claro
                            '#DEB887',    // Café claro accent
                            '#8B4513',    // Café silla de montar
                            '#A0522D'     // Café medio repetido
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function initOperationsByHourChart() {
            const ctx = document.getElementById('operationsByHourChart').getContext('2d');
            
            // Crear array completo de 24 horas
            const hourlyData = new Array(24).fill(0);
            operationsByHour.forEach(item => {
                hourlyData[parseInt(item.hour)] = parseInt(item.count);
            });

            const labels = Array.from({length: 24}, (_, i) => i.toString().padStart(2, '0') + ':00');

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Operaciones',
                        data: hourlyData,
                        backgroundColor: '#4e342e'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        function initOperationsByDateChart() {
            const ctx = document.getElementById('operationsByDateChart').getContext('2d');
            
            const labels = operationsByDate.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('es-ES');
            });
            const data = operationsByDate.map(item => parseInt(item.count));

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Operaciones',
                        data: data,
                        borderColor: '#4e342e',
                        backgroundColor: 'rgba(78, 52, 46, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
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

        function exportarDatos(formato) {
            // Obtener parámetros actuales de la URL
            const urlParams = new URLSearchParams(window.location.search);

            // Construir URL de exportación
            const exportUrl = 'export.php?format=' + formato + '&type=operations_report&modal=1&' + urlParams.toString();

            if (formato === 'pdf') {
                // Para PDF, abrir modal
                abrirModalPDF(exportUrl);
            } else {
                // Para CSV y Excel, abrir en nueva ventana para descarga
                mostrarNotificacion('Preparando descarga...', 'info');
                window.open(exportUrl.replace('&modal=1', ''), '_blank');
            }
        }

        function abrirModalPDF(url) {
            const modal = document.getElementById('pdfModal');
            const iframe = document.getElementById('pdfIframe');
            const loading = document.getElementById('pdfLoading');

            // Mostrar modal y loading
            modal.style.display = 'block';
            loading.style.display = 'flex';
            iframe.style.display = 'none';

            // Cargar PDF en iframe
            iframe.onload = function() {
                loading.style.display = 'none';
                iframe.style.display = 'block';
            };

            iframe.onerror = function() {
                loading.innerHTML = '<div class="spinner"></div><p>Error al cargar la vista previa. <button onclick="cerrarModalPDF()" style="margin-left: 10px; padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">Cerrar</button></p>';
            };

            iframe.src = url;

            // Cerrar modal al hacer clic fuera
            modal.onclick = function(event) {
                if (event.target === modal) {
                    cerrarModalPDF();
                }
            };
        }

        function cerrarModalPDF() {
            const modal = document.getElementById('pdfModal');
            const iframe = document.getElementById('pdfIframe');
            
            modal.style.display = 'none';
            iframe.src = '';
        }

        function mostrarNotificacion(mensaje, tipo = 'info') {
            // Crear elemento de notificación
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${tipo === 'error' ? '#dc3545' : tipo === 'success' ? '#28a745' : '#17a2b8'};
                color: white;
                border-radius: 4px;
                z-index: 1000;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                font-family: Arial, sans-serif;
            `;
            notification.textContent = mensaje;

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 3000);
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