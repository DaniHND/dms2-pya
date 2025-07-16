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
    $stats['total_activities'] = $result['total'] ?? 0;

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

    // Acciones más comunes
    if ($role === 'admin') {
        $query = "SELECT action, COUNT(*) as count FROM activity_logs 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  GROUP BY action ORDER BY count DESC LIMIT 1";
        $params = [];
    } else {
        $query = "SELECT al.action, COUNT(*) as count FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  WHERE u.company_id = :company_id AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  GROUP BY al.action ORDER BY count DESC LIMIT 1";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['top_action'] = $result['action'] ?? 'N/A';
    $stats['top_action_count'] = $result['count'] ?? 0;

    return $stats;
}

// Función para obtener actividad reciente
function getRecentActivity($userId, $role, $companyId, $limit = 10)
{
    if ($role === 'admin') {
        $query = "SELECT al.*, u.first_name, u.last_name, u.username 
                  FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  ORDER BY al.created_at DESC 
                  LIMIT :limit";
        $params = ['limit' => $limit];
    } else {
        $query = "SELECT al.*, u.first_name, u.last_name, u.username 
                  FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
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

        <!-- Contenido de reportes -->
        <div class="reports-content">
            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="activity"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['total_activities']); ?></div>
                        <div class="stat-label">Actividades (30 días)</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="zap"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['activities_today']); ?></div>
                        <div class="stat-label">Actividades Hoy</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['active_users']); ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                </div>


            </div>

            <!-- Grid principal -->
            <div class="reports-grid">
                <!-- Navegación de reportes -->
                <div class="reports-nav">
                    <h3>Tipos de Reportes</h3>
                    <div class="nav-buttons">
                        <a href="activity_log.php" class="nav-btn">
                            <i data-feather="list"></i>
                            <span>Actividades</span>
                        </a>
                        <a href="user_reports.php" class="nav-btn">
                            <i data-feather="user"></i>
                            <span>Reportes por Usuario</span>
                        </a>

                        
                        <a href="documents_report.php" class="nav-btn">
                            <i data-feather="file-text"></i>
                            <span>Reportes de Documentos</span>
                        </a>
                    </div>
                </div>

                <!-- Gráfico de actividad -->
                <div class="chart-container">
                    <h3>Actividad de los Últimos 7 Días</h3>
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>
        </div>
    </main>

    <!-- Variables JavaScript -->
    <script>
        var chartData = <?php echo json_encode($chartData); ?>;
        var currentUserRole = '<?php echo $currentUser['role']; ?>';

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);
            initActivityChart();
        });

        // Función para el gráfico de actividad
        function initActivityChart() {
            const ctx = document.getElementById('activityChart').getContext('2d');
            const labels = chartData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('es-ES', {
                    month: 'short',
                    day: 'numeric'
                });
            });
            const data = chartData.map(item => item.count);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Actividades',
                        data: data,
                        borderColor: '#8B4513',
                        backgroundColor: 'rgba(139, 69, 19, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
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

        // Funciones auxiliares
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