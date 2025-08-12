<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

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
        /* Esquema de colores elegante - Café, Crema y Dorado */
        :root {
            --primary-color: #8B4513;           /* Café principal */
            --primary-light: #A0522D;           /* Café claro */
            --secondary-color: #D4AF37;         /* Dorado */
            --secondary-light: #F5DEB3;         /* Crema dorado */
            --accent-color: #CD853F;            /* Peru */
            --bg-primary: #FDFCF9;              /* Crema muy claro */
            --bg-secondary: #F9F6F2;            /* Crema */
            --bg-tertiary: #F5E6D3;             /* Crema oscuro */
            --text-primary: #3C2817;            /* Café muy oscuro */
            --text-secondary: #5D4037;          /* Café medio */
            --text-muted: #8D6E63;              /* Café gris */
            --border-color: #E8DDD4;            /* Borde crema */
            --shadow-light: 0 2px 8px rgba(139, 69, 19, 0.08);
            --shadow-medium: 0 4px 12px rgba(139, 69, 19, 0.12);
            --gradient-primary: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            --gradient-secondary: linear-gradient(135deg, #D4AF37 0%, #B8860B 100%);
            --gradient-tertiary: linear-gradient(135deg, #CD853F 0%, #8B4513 100%);
            --gradient-success: linear-gradient(135deg, #228B22 0%, #32CD32 100%);
            --gradient-info: linear-gradient(135deg, #4682B4 0%, #87CEEB 100%);
        }

        /* Layout principal */
        .reports-content {
            background: transparent;
            padding: 0;
        }

        .dashboard-layout {
            background: var(--bg-primary);
            min-height: 100vh;
        }

        /* Estadísticas principales */
        .reports-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .reports-stat-card {
            background: linear-gradient(135deg, #ffffff 0%, var(--bg-secondary) 100%);
            border-radius: 16px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-color);
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
            background: var(--gradient-primary);
        }

        .reports-stat-card:nth-child(2)::before {
            background: var(--gradient-secondary);
        }

        .reports-stat-card:nth-child(3)::before {
            background: var(--gradient-tertiary);
        }

        .reports-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(139, 69, 19, 0.15);
        }

        .reports-stat-icon {
            background: var(--gradient-primary);
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(139, 69, 19, 0.3);
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .reports-stat-card:nth-child(2) .reports-stat-icon {
            background: var(--gradient-secondary);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        }

        .reports-stat-card:nth-child(3) .reports-stat-icon {
            background: var(--gradient-tertiary);
            box-shadow: 0 4px 12px rgba(205, 133, 63, 0.3);
        }

        .reports-stat-info {
            flex: 1;
        }

        .reports-stat-number {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 2rem;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .reports-stat-card:nth-child(2) .reports-stat-number {
            background: var(--gradient-secondary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .reports-stat-card:nth-child(3) .reports-stat-number {
            background: var(--gradient-tertiary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .reports-stat-label {
            color: var(--text-muted);
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

        /* Navegación de tipos de reportes */
        .reports-nav {
            background: linear-gradient(135deg, #ffffff 0%, var(--bg-secondary) 100%);
            border-radius: 16px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-color);
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
            background: var(--gradient-primary);
        }

        .reports-nav h3 {
            margin: 0 0 1.5rem 0;
            color: var(--text-primary);
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
            background: linear-gradient(135deg, #ffffff 0%, var(--bg-tertiary) 100%);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: var(--text-secondary);
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
            background: var(--gradient-primary);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .nav-btn:nth-child(2)::before {
            background: var(--gradient-secondary);
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            border-color: var(--primary-color);
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav-btn:hover::before {
            transform: scaleY(1);
        }

        .nav-btn i {
            color: var(--primary-color);
            transition: color 0.3s ease;
            width: 20px;
            height: 20px;
        }

        .nav-btn:nth-child(2) i {
            color: var(--secondary-color);
        }

        /* Sección de gráficos */
        .charts-section {
            background: linear-gradient(135deg, #ffffff 0%, var(--bg-secondary) 100%);
            border-radius: 16px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-color);
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
            background: var(--gradient-info);
        }

        .chart-container {
            background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-secondary) 100%);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-light);
        }

        .chart-container h3 {
            margin: 0 0 1rem 0;
            color: var(--text-primary);
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
            background: linear-gradient(135deg, #ffffff 0%, var(--bg-secondary) 100%);
            border-radius: 8px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            width: 100%;
            height: 300px;
        }

        /* Header mejorado */
        .content-header {
            background: linear-gradient(135deg, #ffffff 0%, var(--bg-secondary) 100%);
            border-radius: 16px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
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
           background: var(--gradient-primary);
       }

       /* Breadcrumb mejorado */
       .reports-nav-breadcrumb {
           margin-bottom: 2rem;
       }

       .breadcrumb-link {
           background: linear-gradient(135deg, #ffffff 0%, var(--bg-secondary) 100%);
           border: 2px solid var(--border-color);
           border-radius: 12px;
           box-shadow: var(--shadow-light);
           transition: all 0.3s ease;
           padding: 0.75rem 1.5rem;
           text-decoration: none;
           color: var(--text-secondary);
           display: inline-flex;
           align-items: center;
           gap: 0.5rem;
           font-weight: 500;
       }

       .breadcrumb-link:hover {
           transform: translateY(-2px);
           box-shadow: var(--shadow-medium);
           border-color: var(--primary-color);
           color: var(--primary-color);
           text-decoration: none;
       }

       .breadcrumb-link i {
           color: var(--primary-color);
       }

       /* Botones de header */
       .btn-icon {
           background: linear-gradient(135deg, #ffffff 0%, var(--bg-secondary) 100%);
           border: 2px solid var(--border-color);
           border-radius: 12px;
           box-shadow: var(--shadow-light);
           transition: all 0.3s ease;
           padding: 0.5rem;
           color: var(--text-secondary);
       }

       .btn-icon:hover {
           transform: translateY(-2px);
           box-shadow: var(--shadow-medium);
           border-color: var(--primary-color);
           color: var(--primary-color);
       }

       .logout-btn {
           color: #DC2626;
       }

       .logout-btn:hover {
           border-color: #DC2626;
           color: #DC2626;
       }

       /* Información de usuario en header */
       .user-name-header {
           background: var(--gradient-primary);
           -webkit-background-clip: text;
           -webkit-text-fill-color: transparent;
           background-clip: text;
           font-weight: 600;
       }

       .current-time {
           color: var(--text-muted);
           font-size: 0.875rem;
           font-weight: 500;
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
       .charts-section {
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

       .charts-section {
           animation-delay: 0.6s;
       }

       /* Responsive */
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
           .charts-section {
               padding: 1.5rem;
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

           .chart-canvas {
               height: 250px;
           }
       }
   </style>

</body>

</html>