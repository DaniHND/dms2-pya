<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Función helper para nombres completos
// Función helper para nombres completos
function getFullName($firstName = null, $lastName = null)
{
    global $currentUser;

    // Si no se pasan parámetros, usar el usuario actual
    if ($firstName === null && $lastName === null) {
        $firstName = $currentUser['first_name'] ?? '';
        $lastName = $currentUser['last_name'] ?? '';
    }

    return trim($firstName . ' ' . $lastName);
}
// Verificar permisos básicos
if ($currentUser['role'] !== 'admin') {
    // Aquí puedes agregar lógica de permisos si necesitas
}

// Tu código original continúa desde aquí...

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
    <style>
        /* Aplicar los mismos colores elegantes de activity_log.php y documents_report.php */
        :root {
            --primary-gradient: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            --secondary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            --success-gradient: linear-gradient(135deg, #10B981 0%, #059669 100%);
            --warning-gradient: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            --info-gradient: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            --danger-gradient: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            --soft-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --soft-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Layout principal */
        .reports-content {
            background: transparent;
            padding: 0;
        }

        /* Estadísticas principales con gradientes elegantes */
        .reports-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .reports-stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .reports-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
        }

        .reports-stat-card:nth-child(2)::before {
            background: var(--info-gradient);
        }

        .reports-stat-card:nth-child(3)::before {
            background: var(--success-gradient);
        }

        .reports-stat-card:nth-child(4)::before {
            background: var(--warning-gradient);
        }

        .reports-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
        }

        .reports-stat-icon {
            background: var(--primary-gradient);
            border-radius: 16px;
            box-shadow: 0 4px 8px rgba(139, 69, 19, 0.3);
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .reports-stat-card:nth-child(2) .reports-stat-icon {
            background: var(--info-gradient);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }

        .reports-stat-card:nth-child(3) .reports-stat-icon {
            background: var(--success-gradient);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }

        .reports-stat-card:nth-child(4) .reports-stat-icon {
            background: var(--warning-gradient);
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
        }

        .reports-stat-info {
            flex: 1;
        }

        .reports-stat-number {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 2rem;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .reports-stat-card:nth-child(2) .reports-stat-number {
            background: var(--info-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .reports-stat-card:nth-child(3) .reports-stat-number {
            background: var(--success-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .reports-stat-card:nth-child(4) .reports-stat-number {
            background: var(--warning-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .reports-stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            margin: 0;
        }

        /* Grid principal de reportes */
        .reports-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Navegación de tipos de reportes mejorada */
        .reports-nav {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: var(--soft-shadow-lg);
            border: 1px solid #e5e7eb;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            height: fit-content;
        }

        .reports-nav::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
        }

        .reports-nav h3 {
            margin: 0 0 1.5rem 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .reports-nav h3::before {
            content: '📊';
            font-size: 1.5rem;
        }

        .nav-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .nav-btn {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: var(--soft-shadow);
            transition: all 0.3s ease;
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .nav-btn::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-gradient);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .nav-btn:nth-child(1)::before {
            background: var(--primary-gradient);
        }

        .nav-btn:nth-child(2)::before {
            background: var(--info-gradient);
        }

        .nav-btn:nth-child(3)::before {
            background: var(--success-gradient);
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
            border-color: #8B4513;
            color: #8B4513;
            text-decoration: none;
        }

        .nav-btn:hover::before {
            transform: scaleY(1);
        }

        .nav-btn i {
            color: #8B4513;
            transition: color 0.3s ease;
            width: 20px;
            height: 20px;
        }

        .nav-btn:nth-child(2) i {
            color: #3B82F6;
        }

        .nav-btn:nth-child(3) i {
            color: #10B981;
        }

        /* Sección de gráficos mejorada */
        .charts-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: var(--soft-shadow-lg);
            border: 1px solid #e5e7eb;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .charts-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--info-gradient);
        }

        .chart-container {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            box-shadow: var(--soft-shadow);
        }

        .chart-container h3 {
            margin: 0 0 1rem 0;
            color: #1f2937;
            font-size: 1.125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container h3::before {
            content: '📈';
            font-size: 1.25rem;
        }

        .chart-canvas {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 8px;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
            width: 100%;
            height: 300px;
        }

        /* Métricas de rendimiento mejoradas */
        .performance-metrics {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: var(--soft-shadow-lg);
            border: 1px solid #e5e7eb;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .performance-metrics::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--success-gradient);
        }

        .performance-metrics h3 {
            margin: 0 0 1.5rem 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .performance-metrics h3::before {
            content: '⚡';
            font-size: 1.5rem;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }

        .metric-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 12px;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
            padding: 1.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: var(--success-gradient);
        }

        .metric-card:nth-child(1)::before {
            background: var(--primary-gradient);
        }

        .metric-card:nth-child(2)::before {
            background: var(--info-gradient);
        }

        .metric-card:nth-child(3)::before {
            background: var(--warning-gradient);
        }

        .metric-card:nth-child(4)::before {
            background: var(--danger-gradient);
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
        }

        .metric-icon {
            width: 50px;
            height: 50px;
            background: var(--success-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
            flex-shrink: 0;
        }

        .metric-card:nth-child(1) .metric-icon {
            background: var(--primary-gradient);
            box-shadow: 0 4px 8px rgba(139, 69, 19, 0.3);
        }

        .metric-card:nth-child(2) .metric-icon {
            background: var(--info-gradient);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }

        .metric-card:nth-child(3) .metric-icon {
            background: var(--warning-gradient);
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
        }

        .metric-card:nth-child(4) .metric-icon {
            background: var(--danger-gradient);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }

        .metric-content {
            flex: 1;
        }

        .metric-number {
            background: var(--success-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 1.5rem;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .metric-card:nth-child(1) .metric-number {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .metric-card:nth-child(2) .metric-number {
            background: var(--info-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .metric-card:nth-child(3) .metric-number {
            background: var(--warning-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .metric-card:nth-child(4) .metric-number {
            background: var(--danger-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .metric-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            margin: 0;
        }

        /* Tabla de actividad reciente mejorada */
        .reports-table {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: var(--soft-shadow-lg);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            position: relative;
        }

        .reports-table::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--secondary-gradient);
        }

        .reports-table h3 {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.5rem;
            margin: 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .reports-table h3::before {
            content: '🕒';
            font-size: 1.25rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1rem 0.75rem;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            font-size: 0.875rem;
            text-align: left;
        }

        .data-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .data-table tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }

        .text-muted {
            color: #6b7280;
            font-size: 0.875rem;
        }

        /* Header mejorado */
        .content-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .content-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
        }

        /* Breadcrumb mejorado */
        .reports-nav-breadcrumb {
            margin-bottom: 2rem;
        }

        .breadcrumb-link {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: var(--soft-shadow);
            transition: all 0.3s ease;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            color: #374151;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .breadcrumb-link:hover {
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
            border-color: #8B4513;
            color: #8B4513;
            text-decoration: none;
        }

        .breadcrumb-link i {
            color: #8B4513;
        }

        /* Botones de header mejorados */
        .btn-icon {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: var(--soft-shadow);
            transition: all 0.3s ease;
            padding: 0.5rem;
            color: #374151;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
            border-color: #8B4513;
            color: #8B4513;
        }

        /* Información de usuario en header */
        .user-name-header {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 600;
        }

        .current-time {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Layout general mejorado */
        .dashboard-layout {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
        }

        /* Animaciones suaves */
        @keyframes elegantFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reports-stat-card,
        .reports-nav,
        .charts-section,
        .performance-metrics,
        .metric-card,
        .reports-table {
            animation: elegantFadeIn 0.6s ease-out;
            animation-fill-mode: both;
        }

        .reports-stat-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .reports-stat-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .reports-stat-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .nav-btn:nth-child(1) {
            animation-delay: 0.4s;
        }

        .nav-btn:nth-child(2) {
            animation-delay: 0.5s;
        }

        .nav-btn:nth-child(3) {
            animation-delay: 0.6s;
        }

        .charts-section {
            animation-delay: 0.7s;
        }

        .performance-metrics {
            animation-delay: 0.8s;
        }

        .metric-card:nth-child(1) {
            animation-delay: 0.9s;
        }

        .metric-card:nth-child(2) {
            animation-delay: 1.0s;
        }

        .metric-card:nth-child(3) {
            animation-delay: 1.1s;
        }

        .metric-card:nth-child(4) {
            animation-delay: 1.2s;
        }

        .reports-table {
            animation-delay: 1.3s;
        }

        /* Responsive mejorado */
        @media (max-width: 768px) {
            .reports-stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .reports-stat-card {
                padding: 1rem;
                flex-direction: column;
                text-align: center;
                gap: 0.75rem;
            }

            .reports-stat-icon {
                width: 50px;
                height: 50px;
            }

            .reports-stat-number {
                font-size: 1.5rem;
            }

            .reports-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .reports-nav,
            .charts-section,
            .performance-metrics {
                padding: 1.5rem;
            }

            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .metric-card {
                padding: 1rem;
                flex-direction: column;
                text-align: center;
                gap: 0.75rem;
            }

            .metric-icon {
                width: 40px;
                height: 40px;
            }

            .metric-number {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .reports-stats-grid {
                grid-template-columns: 1fr;
            }

            .nav-btn {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .chart-canvas {
                height: 250px;
            }

            .data-table th,
            .data-table td {
                padding: 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>

</body>

</html>

</body>

</html>