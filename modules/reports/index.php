<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

// Asegurar que las funciones de base de datos estén disponibles
if (!function_exists('fetchOne')) {
    function fetchOne($query, $params = []) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error in fetchOne: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('fetchAll')) {
    function fetchAll($query, $params = []) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error in fetchAll: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('logActivity')) {
    function logActivity($userId, $action, $tableName = null, $recordId = null, $description = null) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $query = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address, user_agent, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
            
            $stmt = $pdo->prepare($query);
            return $stmt->execute([$userId, $action, $tableName, $recordId, $description, $ipAddress, $userAgent]);
        } catch (Exception $e) {
            error_log('Error in logActivity: ' . $e->getMessage());
            return false;
        }
    }
}

// Función helper para nombres completos
if (!function_exists('getFullName')) {
    function getFullName()
    {
        $currentUser = SessionManager::getCurrentUser();
        if ($currentUser) {
            return trim($currentUser['first_name'] . ' ' . $currentUser['last_name']);
        }
        return 'Usuario';
    }
}

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Función para obtener estadísticas generales
function getReportStats($userId, $companyId, $role)
{
    $stats = [];

    // Total de actividades (últimos 30 días)
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
        $query = "SELECT COUNT(*) as total FROM documents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status = 'active'";
        $params = [];
    } else {
        $query = "SELECT COUNT(*) as total FROM documents d 
                  LEFT JOIN users u ON d.user_id = u.id 
                  WHERE u.company_id = :company_id AND d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND d.status = 'active'";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['documents_this_month'] = $result['total'] ?? 0;

    return $stats;
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
$chartData = getChartData($currentUser['id'], $currentUser['role'], $currentUser['company_id']);

// Calcular días analizados y promedio diario
$daysAnalyzed = 7;
$dailyAverage = $stats['activities_30_days'] > 0 ? round($stats['activities_30_days'] / 30, 1) : 0;

// Registrar acceso a reportes
if (function_exists('logActivity')) {
    logActivity($currentUser['id'], 'view_reports', 'reports', null, 'Usuario accedió al módulo de reportes');
}
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
                <h1>Reportes y Actividades</h1>
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

        <!-- Contenido de reportes -->
        <div class="reports-content">
            <!-- Estadísticas resumen -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="activity"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['activities_30_days'] ?? 0); ?></div>
                        <div class="stat-label">Total Actividades</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="calendar"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($daysAnalyzed); ?></div>
                        <div class="stat-label">Días Analizados</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="trending-up"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $dailyAverage; ?></div>
                        <div class="stat-label">Promedio Diario</div>
                    </div>
                </div>
            </div>

            <!-- Sección de navegación de reportes -->
            <div class="reports-nav-section">
                <h3>Tipos de Reportes</h3>
                <div class="nav-buttons-grid">
                    <a href="activity_log.php" class="nav-btn-card">
                        <div class="nav-btn-content">
                            <h4>Reporte de Actividades</h4>
                            <p>Registro detallado de todas las acciones del sistema</p>
                        </div>
                        <div class="nav-btn-arrow">
                            <i data-feather="chevron-right"></i>
                        </div>
                    </a>

                    <a href="user_reports.php" class="nav-btn-card">

                        <div class="nav-btn-content">
                            <h4>Reporte de Usuarios</h4>
                            <p>Estadísticas y actividad por usuario del sistema</p>
                        </div>
                        <div class="nav-btn-arrow">
                            <i data-feather="chevron-right"></i>
                        </div>
                    </a>

                    <a href="documents_report.php" class="nav-btn-card">

                        <div class="nav-btn-content">
                            <h4>Reporte de Documentos</h4>
                            <p>Análisis completo de documentos y descargas</p>
                        </div>
                        <div class="nav-btn-arrow">
                            <i data-feather="chevron-right"></i>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Gráfico de actividad -->
            <div class="chart-section">
                <div class="chart-card">
                    <h3><i data-feather="bar-chart-2"></i> Actividad de los Últimos 7 Días</h3>
                    <div class="chart-container">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Variables de configuración
        var chartData = <?php echo json_encode($chartData ?? []); ?>;

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);
            initChart();
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

        function initChart() {
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
                                            return context[0].label;
                                        },
                                        label: function(context) {
                                            return `Actividades: ${context.parsed.y}`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.1)'
                                    },
                                    ticks: {
                                        color: '#6b7280'
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.1)'
                                    },
                                    ticks: {
                                        color: '#6b7280',
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
            }
        }

        function showComingSoon(feature) {
            alert(`${feature} - Próximamente`);
        }
    </script>

    <style>
        /* Estilos específicos para el índice de reportes con diseño de documents_report */
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

        /* Estadísticas estilo imagen proporcionada */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border: 2px solid #3b82f6;
            border-radius: 16px;
            padding: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-color: #3b82f6;
        }

        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-color: #3b82f6;
        }

        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-color: #3b82f6;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.15);
        }

        .stat-icon {
            width: 80px;
            height: 80px;
            background: #3b82f6;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.25);
        }

        .stat-card:nth-child(2) .stat-icon {
            background: #3b82f6;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.25);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: #3b82f6;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.25);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: #3b82f6;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.25);
        }

        .stat-icon i {
            width: 40px;
            height: 40px;
            stroke-width: 1.5;
        }

        .stat-info {
            flex: 1;
        }

        .stat-number {
            font-size: 2.75rem;
            font-weight: 700;
            color: #1e40af;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #1e40af;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Sección de navegación de reportes */
        .reports-nav-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
        }

        .reports-nav-section h3 {
            margin: 0 0 1.5rem 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .reports-nav-section h3::before {
            content: '';
            width: 24px;
            height: 24px;
            background: var(--primary-gradient);
            border-radius: 8px;
        }

        .nav-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .nav-btn-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            text-decoration: none;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            box-shadow: var(--soft-shadow);
            position: relative;
            overflow: hidden;
        }

        .nav-btn-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
            border-color: #8B4513;
            text-decoration: none;
            color: #374151;
        }

        .nav-btn-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .nav-btn-card:hover::before {
            left: 100%;
        }

        .nav-btn-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-gradient);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 8px rgba(139, 69, 19, 0.3);
        }

        .nav-btn-card:nth-child(2) .nav-btn-icon {
            background: var(--info-gradient);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }

        .nav-btn-card:nth-child(3) .nav-btn-icon {
            background: var(--success-gradient);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
         }

        .nav-btn-icon i {
            width: 30px;
            height: 30px;
        }

        .nav-btn-content {
            flex: 1;
        }

        .nav-btn-content h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
        }

        .nav-btn-content p {
            margin: 0;
            font-size: 0.875rem;
            color: #6b7280;
            line-height: 1.4;
        }

        .nav-btn-arrow {
            color: #9ca3af;
            transition: all 0.3s ease;
        }

        .nav-btn-card:hover .nav-btn-arrow {
            color: #13738bff;
            transform: translateX(4px);
        }

        .nav-btn-arrow i {
            width: 20px;
            height: 20px;
        }

        /* Sección de gráfico */
        .chart-section {
            margin-bottom: 2rem;
        }

        .chart-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--soft-shadow-lg);
            border: 1px solid #e5e7eb;
        }

        .chart-card h3 {
            margin: 0 0 1.5rem 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-card h3 i {
            color: #8B4513;
        }

        .chart-container {
            position: relative;
            height: 400px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 1rem;
        }

        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        .nav-btn-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .nav-btn-card:nth-child(1) { animation-delay: 0.5s; }
        .nav-btn-card:nth-child(2) { animation-delay: 0.6s; }
        .nav-btn-card:nth-child(3) { animation-delay: 0.7s; }

        .chart-card {
            animation: fadeInUp 0.6s ease-out;
            animation-delay: 0.8s;
        }

        /* Responsividad */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .nav-buttons-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-icon {
                width: 60px;
                height: 60px;
            }

            .stat-number {
                font-size: 2rem;
            }

            .nav-btn-card {
                padding: 1.25rem;
            }

            .nav-btn-icon {
                width: 50px;
                height: 50px;
            }

            .nav-btn-content h4 {
                font-size: 1rem;
            }

            .nav-btn-content p {
                font-size: 0.8rem;
            }

            .chart-container {
                height: 300px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .reports-content {
                padding: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                width: 50px;
                height: 50px;
            }

            .stat-number {
                font-size: 1.75rem;
            }

            .nav-btn-card {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .nav-btn-arrow {
                display: none;
            }

            .chart-container {
                height: 250px;
            }
        }

        /* Efectos de hover en las tarjetas de estadísticas */
        .stat-card:hover .stat-icon {
            transform: scale(1.05);
        }

        .stat-card:hover .stat-number {
            transform: scale(1.02);
        }

        /* Efectos de hover en las tarjetas de navegación */
        .nav-btn-card:hover .nav-btn-icon {
            transform: scale(1.05);
        }

        .nav-btn-card:hover .nav-btn-content h4 {
            color: #8B4513;
        }

        /* Estilos para hacer activo el enlace de reportes en sidebar */
        .sidebar .nav-item .nav-link[href*="reports"] {
            color: var(--primary-color) !important;
            background: rgba(212, 175, 55, 0.1) !important;
            font-weight: 600 !important;
        }

        .sidebar .nav-item .nav-link[href*="reports"] i {
            color: var(--primary-color) !important;
        }

        /* Mejoras en accesibilidad */
        .nav-btn-card:focus {
            outline: 2px solid #8B4513;
            outline-offset: 2px;
        }

        /* Indicadores de carga para el gráfico */
        .chart-container canvas {
            border-radius: 8px;
        }

        /* Animaciones de entrada para elementos dinámicos */
        .reports-content > * {
            opacity: 0;
            animation: fadeInSequence 0.6s ease-out forwards;
        }

        .stats-grid { animation-delay: 0.1s; }
        .reports-nav-section { animation-delay: 0.3s; }
        .chart-section { animation-delay: 0.5s; }

        @keyframes fadeInSequence {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mejoras finales en la consistencia visual */
        .reports-nav-section,
        .chart-card {
            border: 1px solid rgba(139, 69, 19, 0.1);
        }

        .reports-nav-section h3,
        .chart-card h3 {
            color: #8B4513;
        }

        /* Estado final del diseño */
        body.dashboard-layout {
            background: #f8fafc;
        }

        .reports-content {
            background: transparent;
            padding: 2rem;
        }

        /* Mejoras específicas para el gráfico */
        .chart-container {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        /* Tooltips mejorados para los botones */
        .nav-btn-card {
            position: relative;
        }

        /* Estados de loading para datos dinámicos */
        .stat-number {
            transition: all 0.3s ease;
        }

        .stat-number:hover {
            transform: scale(1.02);
        }

        /* Mejoras en la visualización del gráfico */
        #activityChart {
            border-radius: 8px;
        }

        /* Ajustes finales para pantallas muy pequeñas */
        @media (max-width: 320px) {
            .stat-card {
                padding: 0.75rem;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .nav-btn-card {
                padding: 1rem;
            }
        }

        /* Efectos adicionales para mejorar la experiencia visual */
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 0;
            height: 0;
            border-left: 15px solid transparent;
            border-top: 15px solid rgba(255, 255, 255, 0.2);
            border-radius: 0 16px 0 0;
        }

        /* Animación de entrada para el contenido principal */
        .reports-content {
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Sombras mejoradas para mayor profundidad visual */
        .stat-card:hover,
        .nav-btn-card:hover,
        .chart-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Mejoras en la tipografía */
        .stat-label,
        .nav-btn-content p {
            letter-spacing: 0.025em;
        }

        /* Transiciones suaves para todos los elementos interactivos */
        .stat-card,
        .nav-btn-card,
        .chart-card,
        .stat-icon,
        .nav-btn-icon,
        .nav-btn-arrow {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>

</body>

</html>