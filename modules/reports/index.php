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
                <h1>Reportes y Actividades</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(trim($currentUser['first_name'] . ' ' . $currentUser['last_name'])); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" onclick="showSettings()">
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
        

            <!-- Grid de estadísticas principales -->
            <div class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon brown-stat">
                                <i data-feather="activity"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo number_format($stats['activities_30_days'] ?? 0); ?></div>
                                <div class="stat-label">Total Actividades</div>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon blue-stat">
                                <i data-feather="users"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                                <div class="stat-label">Usuarios Activos</div>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon green-stat">
                                <i data-feather="calendar"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-number">8</div>
                                <div class="stat-label">Días Analizados</div>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon orange-stat">
                                <i data-feather="trending-up"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-number">8.4</div>
                                <div class="stat-label">Promedio Diario</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid principal de reportes -->
            <div class="reports-grid">
                <!-- Navegación de tipos de reportes -->
                <div class="reports-nav">
                    <h3><i data-feather="filter"></i> Tipos de Reportes</h3>
                    <div class="nav-buttons">
                        <a href="activity_log.php" class="nav-btn">
                            <i data-feather="list"></i>
                            <span>Actividades</span>
                        </a>
                        <a href="user_reports.php" class="nav-btn">
                            <i data-feather="users"></i>
                            <span>Reportes por Usuario</span>
                        </a>
                        <a href="documents_report.php" class="nav-btn">
                            <i data-feather="file-text"></i>
                            <span>Reportes de Documentos</span>
                        </a>
                    </div>
                </div>

                <!-- Área principal con gráfico -->
                <div class="charts-section">
                    <div class="chart-container">
                        <h3><i data-feather="bar-chart-2"></i> Actividad de los Últimos 7 Días</h3>
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
                                displayColors: false
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#64748b'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    color: '#64748b'
                                }
                            }
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
    </script>
    
    <style>
        /* ===== VARIABLES DE COLORES PROFESIONALES ===== */
        :root {
            /* Colores principales del sistema */
            --primary-color: #8b4513;
            --primary-hover: #a0522d;
            --primary-light: #f5e6d3;
            --secondary-color: #d4af37;
            --secondary-hover: #b8860b;
            --secondary-light: #faf0d9;
            
            /* Colores de estadísticas */
            --brown-gradient: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            --blue-gradient: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            --green-gradient: linear-gradient(135deg, #10B981 0%, #059669 100%);
            --orange-gradient: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            
            /* Fondos */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            
            /* Texto */
            --text-primary: #1f2937;
            --text-secondary: #374151;
            --text-muted: #6b7280;
            
            /* Grises profesionales */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            
            /* Sombras */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        /* Layout principal */
        .dashboard-layout {
            background: var(--bg-secondary);
        }

        .reports-content {
            padding: 24px;
            background: transparent;
        }

        /* ===== HEADER SIN COLOR CAFÉ ===== */
        .content-header {
    padding: var(--spacing-5) var(--spacing-6);
    border-bottom: 1px solid #e2e8f0;
    background: var(--bg-tertiary);
        }

        /* Remover la línea café del header */
        .content-header::before {
            display: none;
        }

        /* ===== NOMBRE DE USUARIO EN NEGRO ===== */
        .user-name-header {
            color: var(--text-primary) !important;
            font-weight: 600;
        }

        .current-time {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        /* ===== BOTONES DE HEADER PROFESIONALES ===== */
        .btn-icon {
            background: var(--gray-100);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 8px;
            color: var(--gray-700);
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-icon:hover {
            background: var(--gray-200);
            border-color: var(--gray-300);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .logout-btn {
            background: #fee2e2;
            border-color: #fecaca;
            color: #dc2626;
        }

        .logout-btn:hover {
            background: #fecaca;
            border-color: #f87171;
        }

        /* ===== ESTADÍSTICAS ===== */
        .stats-section {
            margin-bottom: 32px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
        }

        .stat-card {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--brown-gradient);
        }

        .stat-card:nth-child(2)::before {
            background: var(--blue-gradient);
        }

        .stat-card:nth-child(3)::before {
            background: var(--green-gradient);
        }

        .stat-card:nth-child(4)::before {
            background: var(--orange-gradient);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .brown-stat {
            background: var(--brown-gradient);
            box-shadow: 0 4px 12px rgba(207, 130, 75, 0.3);
        }

        .blue-stat {
            background: var(--blue-gradient);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .green-stat {
            background: var(--green-gradient);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .orange-stat {
            background: var(--orange-gradient);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .stat-info {
            flex: 1;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* ===== NAVEGACIÓN DE REPORTES PROFESIONAL ===== */
        .reports-nav {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
            height: fit-content;
        }

        .reports-nav h3 {
            background: var(--gray-100);
            color: var(--text-primary);
            margin: 0 0 24px 0;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--gray-200);
        }

        .nav-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* ===== BOTONES PROFESIONALES SIN COLOR ===== */
        .nav-btn {
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            padding: 16px 20px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .nav-btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            color: var(--text-primary);
            text-decoration: none;
        }

        .nav-btn:active {
            transform: translateY(0);
        }

        .nav-btn i {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .nav-btn span {
            font-size: 0.95rem;
            letter-spacing: 0.025em;
        }

        /* Efecto hover sutil */
        .nav-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }

        .nav-btn:hover::before {
            left: 100%;
        }

        /* ===== GRÁFICO SECTION ===== */
        .charts-section {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
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
            background: var(--blue-gradient);
        }

        .chart-container h3 {
            color: var(--text-primary);
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-canvas {
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--gray-200);
            width: 100%;
            height: 300px;
        }

        /* ===== GRID LAYOUT ===== */
        .reports-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 24px;
        }

        /* ===== SIDEBAR ACTIVO REPORTES - MEJORADO ===== */
        /* Aplicar solo al módulo de reportes */
        body.reports-page .sidebar .nav-item .nav-link[href*="reports"] {
            color: #D4AF37 !important;
            background: rgba(212, 175, 55, 0.1) !important;
            font-weight: 600 !important;
        }

        body.reports-page .sidebar .nav-item .nav-link[href*="reports"] i {
            color: #D4AF37 !important;
        }

        /* Asegurar que otros enlaces no se vean afectados */
        body.reports-page .sidebar .nav-item .nav-link:not([href*="reports"]) {
            color: #94a3b8 !important;
            background: transparent !important;
            font-weight: 500 !important;
        }

        body.reports-page .sidebar .nav-item .nav-link:not([href*="reports"]) i {
            color: #94a3b8 !important;
        }

        /* Hover para enlaces no activos */
        body.reports-page .sidebar .nav-item .nav-link:not([href*="reports"]):hover {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.1) !important;
        }

        body.reports-page .sidebar .nav-item .nav-link:not([href*="reports"]):hover i {
            color: #ffffff !important;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            .reports-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-header {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }

            .stat-icon {
                width: 50px;
                height: 50px;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .nav-btn {
                padding: 14px 16px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .reports-content {
                padding: 16px;
            }
        }
    </style>

</body>

</html>