<?php
// modules/reports/index.php
// Dashboard principal de Reportes y Bitácora - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Función para obtener estadísticas generales
function getReportStats($userId, $companyId, $role)
{
    $stats = [];

    // Total de actividades
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = [];
    } else {
        $query = "SELECT COUNT(*) as total FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  WHERE u.company_id = :company_id AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['activities_30_days'] = $result['total'] ?? 0;

    // Actividades hoy
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM activity_logs WHERE DATE(created_at) = CURDATE()";
        $params = [];
    } else {
        $query = "SELECT COUNT(*) as total FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  WHERE u.company_id = :company_id AND DATE(al.created_at) = CURDATE()";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['activities_today'] = $result['total'] ?? 0;

    // Usuarios activos (últimos 7 días)
    if ($role === 'admin') {
        $query = "SELECT COUNT(DISTINCT user_id) as total FROM activity_logs 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $params = [];
    } else {
        $query = "SELECT COUNT(DISTINCT al.user_id) as total FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  WHERE u.company_id = :company_id AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['active_users'] = $result['total'] ?? 0;

    // Documentos este mes
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM documents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = [];
    } else {
        $query = "SELECT COUNT(*) as total FROM documents d 
                  LEFT JOIN users u ON d.uploaded_by = u.id 
                  WHERE u.company_id = :company_id AND d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['documents_this_month'] = $result['total'] ?? 0;

    // Descargas este mes
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM activity_logs 
                  WHERE action = 'download_document' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = [];
    } else {
        $query = "SELECT COUNT(*) as total FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  WHERE u.company_id = :company_id AND al.action = 'download_document' 
                  AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['downloads_this_month'] = $result['total'] ?? 0;

    // Visualizaciones este mes
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM activity_logs 
                  WHERE action = 'view_document' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = [];
    } else {
        $query = "SELECT COUNT(*) as total FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  WHERE u.company_id = :company_id AND al.action = 'view_document' 
                  AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['views_this_month'] = $result['total'] ?? 0;

    // Búsquedas este mes
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM activity_logs 
                  WHERE action = 'search_documents' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = [];
    } else {
        $query = "SELECT COUNT(*) as total FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  WHERE u.company_id = :company_id AND al.action = 'search_documents' 
                  AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['searches_this_month'] = $result['total'] ?? 0;

    return $stats;
}

// Función para obtener actividad reciente
function getRecentActivity($userId, $role, $companyId, $limit = 10)
{
    if ($role === 'admin') {
        $query = "SELECT al.*, 
                         CONCAT(u.first_name, ' ', u.last_name) as user_name,
                         u.username,
                         c.name as company_name
                  FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  LEFT JOIN companies c ON u.company_id = c.id
                  ORDER BY al.created_at DESC 
                  LIMIT :limit";
        $params = ['limit' => $limit];
    } else {
        $query = "SELECT al.*, 
                         CONCAT(u.first_name, ' ', u.last_name) as user_name,
                         u.username,
                         c.name as company_name
                  FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.company_id = :company_id OR al.user_id = :user_id
                  ORDER BY al.created_at DESC 
                  LIMIT :limit";
        $params = ['company_id' => $companyId, 'user_id' => $userId, 'limit' => $limit];
    }

    return fetchAll($query, $params);
}

// Función para obtener datos de gráficos
function getChartData($userId, $role, $companyId, $days = 7)
{
    $data = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));

        if ($role === 'admin') {
            $query = "SELECT COUNT(*) as count FROM activity_logs 
                      WHERE DATE(created_at) = :date";
            $params = ['date' => $date];
        } else {
            $query = "SELECT COUNT(*) as count FROM activity_logs al 
                      LEFT JOIN users u ON al.user_id = u.id 
                      WHERE u.company_id = :company_id AND DATE(al.created_at) = :date";
            $params = ['company_id' => $companyId, 'date' => $date];
        }

        $result = fetchOne($query, $params);
        $data[] = [
            'date' => $date,
            'count' => $result['count'] ?? 0
        ];
    }

    return $data;
}

$stats = getReportStats($currentUser['id'], $currentUser['company_id'], $currentUser['role']);
$recentActivity = getRecentActivity($currentUser['id'], $currentUser['role'], $currentUser['company_id']);
$chartData = getChartData($currentUser['id'], $currentUser['role'], $currentUser['company_id']);

// Registrar acceso a reportes
logActivity($currentUser['id'], 'view_reports', 'reports', null, 'Usuario accedió al módulo de reportes');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Bitácora - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reports.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <h1>Reportes y Bitácora</h1>
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

        <!-- Contenido del dashboard de reportes -->
        <div class="reports-content">
            <!-- Breadcrumb -->
            <div class="reports-nav-breadcrumb">
                <a href="#" class="breadcrumb-link">
                    <i data-feather="home"></i>
                    Reportes y Bitácora
                </a>
            </div>

            <!-- Grid de estadísticas principales -->
            <div class="reports-stats-grid">
                <div class="reports-stat-card">
                    <div class="reports-stat-icon">
                        <i data-feather="activity"></i>
                    </div>
                    <div class="reports-stat-info">
                        <div class="reports-stat-number"><?php echo number_format($stats['activities_30_days'] ?? 0); ?></div>
                        <div class="reports-stat-label">Actividades (30 días)</div>
                    </div>
                </div>

                <div class="reports-stat-card">
                    <div class="reports-stat-icon">
                        <i data-feather="zap"></i>
                    </div>
                    <div class="reports-stat-info">
                        <div class="reports-stat-number"><?php echo number_format($stats['activities_today'] ?? 0); ?></div>
                        <div class="reports-stat-label">Actividades Hoy</div>
                    </div>
                </div>

                <div class="reports-stat-card">
                    <div class="reports-stat-icon">
                        <i data-feather="users"></i>
                    </div>
                    <div class="reports-stat-info">
                        <div class="reports-stat-number"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                        <div class="reports-stat-label">Usuarios Activos</div>
                    </div>
                </div>
            </div>

            <!-- Grid principal de reportes -->
            <div class="reports-grid">
                <!-- Navegación de tipos de reportes -->
                <div class="reports-nav">
                    <h3>Tipos de Reportes</h3>
                    <div class="nav-buttons">
                        <a href="activity_log.php" class="nav-btn">
                            <i data-feather="list"></i>
                            Actividades
                        </a>
                        <a href="user_reports.php" class="nav-btn">
                            <i data-feather="users"></i>
                            Reportes por Usuario
                        </a>
                        <a href="documents_report.php" class="nav-btn">
                            <i data-feather="file-text"></i>
                            Reportes de Documentos
                        </a>
                    </div>
                </div>

                <!-- Área principal con gráfico -->
                <div class="charts-section">
                    <div class="chart-container">
                        <h3>Actividad de los Últimos 7 Días</h3>
                        <canvas id="activityChart" class="chart-canvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Métricas de rendimiento -->
            <div class="performance-metrics">
                <h3>Métricas de Rendimiento</h3>
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i data-feather="trending-up"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo number_format($stats['documents_this_month'] ?? 0); ?></div>
                            <div class="metric-label">Docs. Este Mes</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">
                            <i data-feather="download"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo number_format($stats['downloads_this_month'] ?? 0); ?></div>
                            <div class="metric-label">Descargas</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">
                            <i data-feather="eye"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo number_format($stats['views_this_month'] ?? 0); ?></div>
                            <div class="metric-label">Visualizaciones</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">
                            <i data-feather="search"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo number_format($stats['searches_this_month'] ?? 0); ?></div>
                            <div class="metric-label">Búsquedas</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actividad reciente -->
            <?php if (!empty($recentActivity)): ?>
            <div class="reports-table">
                <h3>Actividad Reciente</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Módulo</th>
                                <th>Fecha</th>
                                <th>Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($recentActivity, 0, 10) as $activity): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'Sistema'); ?></strong>
                                    <?php if (!empty($activity['company_name'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($activity['company_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-active">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $activity['action']))); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst($activity['module'])); ?></td>
                                <td>
                                    <time datetime="<?php echo $activity['created_at']; ?>">
                                        <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                    </time>
                                </td>
                                <td>
                                    <?php if (!empty($activity['details'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($activity['details']); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Scripts -->
    <script>
        // Inicializar Feather Icons
        feather.replace();

        // Función para actualizar la hora
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

        // Actualizar tiempo cada minuto
        updateTime();
        setInterval(updateTime, 60000);

        // Configuración del gráfico de actividad
        const chartData = <?php echo json_encode($chartData ?? []); ?>;
        
        if (chartData && chartData.length > 0) {
            const ctx = document.getElementById('activityChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.map(item => {
                            const date = new Date(item.date);
                            return date.toLocaleDateString('es-ES', { 
                                month: 'short', 
                                day: 'numeric' 
                            });
                        }),
                        datasets: [{
                            label: 'Actividades',
                            data: chartData.map(item => item.count),
                            borderColor: '#8B4513',
                            backgroundColor: 'rgba(139, 69, 19, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#8B4513',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                cornerRadius: 8,
                                displayColors: false,
                                callbacks: {
                                    title: function(context) {
                                        const dataIndex = context[0].dataIndex;
                                        const date = new Date(chartData[dataIndex].date);
                                        return date.toLocaleDateString('es-ES', {
                                            weekday: 'long',
                                            month: 'long',
                                            day: 'numeric'
                                        });
                                    },
                                    label: function(context) {
                                        return `${context.parsed.y} actividades`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#64748b',
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    color: '#64748b',
                                    font: {
                                        size: 12
                                    },
                                    callback: function(value) {
                                        return Math.floor(value);
                                    }
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
            }
        }

        // Función para toggle del sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebar && mainContent) {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
            }
        }

        // Función placeholder para "próximamente"
        function showComingSoon(feature) {
            alert(`${feature} estará disponible próximamente.`);
        }

        // Animación de las tarjetas al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.reports-stat-card, .metric-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.3s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Responsive: cerrar sidebar en mobile al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.querySelector('.sidebar');
                const menuToggle = document.querySelector('.mobile-menu-toggle');
                
                if (sidebar && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                }
            }
        });
    </script>
    
</body>
</html>