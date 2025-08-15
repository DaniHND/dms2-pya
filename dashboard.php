<?php
// dashboard.php - Modernizado con estilos unificados y gráficos

require_once 'config/session.php';
require_once 'config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Obtener estadísticas del dashboard
function getDashboardStats($userId, $companyId, $role)
{
    $stats = [];

    // Total de documentos
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM documents WHERE status = 'active'";
        $params = [];
    } else {
        $query = "SELECT COUNT(*) as total FROM documents WHERE company_id = :company_id AND status = 'active'";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['total_documents'] = $result['total'] ?? 0;

    // Documentos subidos hoy
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM documents WHERE DATE(created_at) = CURDATE() AND status = 'active'";
        $params = [];
    } else {
        $query = "SELECT COUNT(*) as total FROM documents WHERE company_id = :company_id AND DATE(created_at) = CURDATE() AND status = 'active'";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['documents_today'] = $result['total'] ?? 0;

    // Total de usuarios
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
        $result = fetchOne($query);
        $stats['total_users'] = $result['total'] ?? 0;
    } else {
        $query = "SELECT COUNT(*) as total FROM users WHERE company_id = :company_id AND status = 'active'";
        $result = fetchOne($query, ['company_id' => $companyId]);
        $stats['total_users'] = $result['total'] ?? 0;
    }

    // Total de empresas
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM companies WHERE status = 'active'";
        $result = fetchOne($query);
        $stats['total_companies'] = $result['total'] ?? 0;
    } else {
        $stats['total_companies'] = 1;  // El usuario solo ve su empresa
    }

    return $stats;
}

// Obtener datos para gráfico de documentos por día (últimos 7 días)
function getDocumentsByDay($userId, $role, $companyId)
{
    $whereConditions = [];
    $params = [];

    // Filtro por empresa si no es admin
    if ($role !== 'admin') {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) . ' AND' : 'WHERE';

    try {
        $query = "SELECT 
                    DATE(d.created_at) as date,
                    COUNT(*) as count
                  FROM documents d
                  $whereClause d.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND d.status = 'active'
                  GROUP BY DATE(d.created_at)
                  ORDER BY date ASC";

        return fetchAll($query, $params);
    } catch (Exception $e) {
        error_log("Error en getDocumentsByDay: " . $e->getMessage());
        return [];
    }
}

// Obtener actividad reciente
function getRecentActivity($userId, $role, $companyId, $limit = 8)
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

    $result = fetchAll($query, $params);
    return $result ?: [];
}

// Obtener documentos por tipo para gráfico
function getDocumentsByType($userId, $role, $companyId)
{
    $whereConditions = [];
    $params = [];

    if ($role !== 'admin') {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) . ' AND' : 'WHERE';

    try {
        $query = "SELECT COALESCE(dt.name, 'Sin tipo') as type, COUNT(*) as count
                  FROM documents d
                  LEFT JOIN document_types dt ON d.document_type_id = dt.id
                  $whereClause d.status = 'active'
                  GROUP BY dt.name
                  ORDER BY count DESC
                  LIMIT 5";

        return fetchAll($query, $params);
    } catch (Exception $e) {
        error_log("Error en getDocumentsByType: " . $e->getMessage());
        return [];
    }
}

// Obtener datos para el dashboard
$stats = getDashboardStats($currentUser['id'], $currentUser['company_id'], $currentUser['role']);
$recentActivity = getRecentActivity($currentUser['id'], $currentUser['role'], $currentUser['company_id']);
$documentsByDay = getDocumentsByDay($currentUser['id'], $currentUser['role'], $currentUser['company_id']);
$documentsByType = getDocumentsByType($currentUser['id'], $currentUser['role'], $currentUser['company_id']);

// Registrar acceso al dashboard
logActivity($currentUser['id'], 'dashboard_access', 'dashboard', null, 'Usuario accedió al dashboard');

// Función helper para obtener nombre completo
function getFullName()
{
    global $currentUser;
    return trim($currentUser['first_name'] . ' ' . $currentUser['last_name']);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DMS2</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="dashboard-layout">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Contenido principal -->
    <main class="main-content">
        <!-- Header del dashboard -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Dashboard</h1>
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
                    <a href="logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido del dashboard -->
        <div class="dashboard-content">
            <!-- Sección de bienvenida profesional -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-text">
                        <h2 class="welcome-title">¡Bienvenido, <?php echo htmlspecialchars($currentUser['first_name']); ?>!</h2>
                        <p class="welcome-subtitle">Gestiona documentos, usuarios y empresas desde tu panel de control centralizado</p>
                        <div class="welcome-time">
                            <i data-feather="clock"></i>
                            <span id="welcomeTime"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjetas de estadísticas estilo reportes -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="file-text"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['total_documents']); ?></div>
                        <div class="stat-label">Total Documentos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="upload"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['documents_today']); ?></div>
                        <div class="stat-label">Subidos Hoy</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="briefcase"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['total_companies']); ?></div>
                        <div class="stat-label"><?php echo $currentUser['role'] === 'admin' ? 'Empresas' : 'Mi Empresa'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Contenido principal con gráficos -->
            <div class="dashboard-main-grid">
                <!-- Columna izquierda -->
                <div class="dashboard-column">
                    <!-- Acciones rápidas -->
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3><i data-feather="zap"></i> Acciones Rápidas</h3>
                        </div>
                        <div class="widget-content">
                            <div class="quick-actions">
                                <a href="modules/documents/upload.php" class="quick-action-btn">
                                    <i data-feather="upload"></i>
                                    <span>Subir Documento</span>
                                </a>

                                <a href="modules/documents/inbox.php" class="quick-action-btn">
                                    <i data-feather="inbox"></i>
                                    <span>Ver Archivos</span>
                                </a>

                                <a href="modules/reports/index.php" class="quick-action-btn">
                                    <i data-feather="bar-chart"></i>
                                    <span>Ver Reportes</span>
                                </a>

                                <?php if ($currentUser['role'] === 'admin'): ?>
                                    <a href="modules/users/index.php" class="quick-action-btn">
                                        <i data-feather="users"></i>
                                        <span>Gestionar Usuarios</span>
                                    </a>

                                    <a href="modules/companies/index.php" class="quick-action-btn">
                                        <i data-feather="briefcase"></i>
                                        <span>Gestionar Empresas</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de documentos por día -->
                    <div class="chart-card">
                        <h3><i data-feather="trending-up"></i> Documentos Subidos (Últimos 7 días)</h3>
                        <div class="chart-container">
                            <canvas id="documentsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha -->
                <div class="dashboard-column">
                    <!-- Actividad reciente -->
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3><i data-feather="activity"></i> Actividad Reciente</h3>
                        </div>
                        <div class="widget-content">
                            <?php if (!empty($recentActivity)): ?>
                                <div class="activity-list">
                                    <?php foreach ($recentActivity as $activity): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon">
                                                <i data-feather="<?php echo getActivityIcon($activity['action']); ?>"></i>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-text">
                                                    <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                                    <?php echo htmlspecialchars(getActivityDescription($activity['action'])); ?>
                                                </div>
                                                <div class="activity-time">
                                                    <?php echo formatTimeAgo($activity['created_at']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="widget-footer">
                                    <a href="modules/reports/activity_log.php" class="view-all-link">
                                        Ver toda la actividad
                                        <i data-feather="arrow-right"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i data-feather="activity"></i>
                                    <p>No hay actividad reciente</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Gráfico de documentos por tipo -->
                    <div class="chart-card">
                        <h3><i data-feather="pie-chart"></i> Documentos por Tipo</h3>
                        <div class="chart-container">
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>            
        </div>
    </main>

    <!-- JavaScript -->
    <script src="assets/js/main.js"></script>
    <script>
        // Variables de datos para gráficos
        var documentsByDay = <?php echo json_encode($documentsByDay); ?>;
        var documentsByType = <?php echo json_encode($documentsByType); ?>;

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar iconos de Feather
            if (typeof feather !== 'undefined') {
                feather.replace();
                console.log('✅ Iconos de Feather inicializados');
            }

            // Inicializar gráficos
            initDocumentsChart();
            initTypeChart();

            // Actualizar reloj
            updateTime();
            setInterval(updateTime, 60000);
        });

        // Inicializar gráfico de documentos por día (barras)
        function initDocumentsChart() {
            const ctx = document.getElementById('documentsChart').getContext('2d');

            // Preparar datos para los últimos 7 días
            const today = new Date();
            const last7Days = [];
            for (let i = 6; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(date.getDate() - i);
                last7Days.push(date.toISOString().split('T')[0]);
            }

            // Mapear datos existentes
            const dataMap = new Map(documentsByDay.map(item => [item.date, parseInt(item.count)]));
            
            // Crear array de datos para todos los días
            const chartData = last7Days.map(date => dataMap.get(date) || 0);
            
            // Formatear labels
            const chartLabels = last7Days.map(date => {
                const d = new Date(date);
                const days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                return days[d.getDay()];
            });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Documentos',
                        data: chartData,
                        backgroundColor: '#3B82F6',
                        borderColor: '#2563EB',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
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
                            callbacks: {
                                label: function(context) {
                                    return `${context.parsed.y} documento${context.parsed.y !== 1 ? 's' : ''}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                color: '#6b7280'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#6b7280'
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Inicializar gráfico de documentos por tipo (dona)
        function initTypeChart() {
            const ctx = document.getElementById('typeChart').getContext('2d');
            
            if (documentsByType.length === 0) {
                ctx.fillStyle = '#6b7280';
                ctx.textAlign = 'center';
                ctx.font = '14px Inter';
                ctx.fillText('No hay datos disponibles', ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }

            const labels = documentsByType.map(item => item.type);
            const data = documentsByType.map(item => parseInt(item.count));
            const colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors.slice(0, data.length),
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Actualizar reloj en bienvenida y header
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('es-ES', {
                day: '2-digit',
                month: '2-digit', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const welcomeTimeString = now.toLocaleString('es-ES', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const timeElement = document.getElementById('currentTime');
            const welcomeTimeElement = document.getElementById('welcomeTime');
            
            if (timeElement) {
                timeElement.textContent = timeString;
            }
            
            if (welcomeTimeElement) {
                welcomeTimeElement.textContent = welcomeTimeString;
            }
        }

        // Función para toggle del sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (sidebar) {
                sidebar.classList.toggle('collapsed');
                
                if (mainContent) {
                    mainContent.classList.toggle('sidebar-collapsed');
                }
                
                if (overlay) {
                    overlay.classList.toggle('active');
                }
            }
        }

        // Función placeholder para "próximamente"
        function showComingSoon(feature) {
            alert(`${feature} estará disponible próximamente.`);
        }

        console.log('✅ Dashboard cargado correctamente');
    </script>

    <style>
        /* ===== ESTILOS UNIFICADOS CON REPORTES ===== */
        :root {
            --primary-gradient: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            --blue-gradient: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            --green-gradient: linear-gradient(135deg, #10B981 0%, #059669 100%);
            --orange-gradient: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            --soft-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --soft-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Sección de bienvenida profesional */
        .welcome-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 2rem 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--soft-shadow);
            transition: all 0.3s ease;
        }

        .welcome-section:hover {
            box-shadow: var(--soft-shadow-lg);
            transform: translateY(-1px);
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6, #06b6d4);
            border-radius: 16px 16px 0 0;
        }

        .welcome-section::after {
            content: '';
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
            border-radius: 50%;
            z-index: 0;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .welcome-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .welcome-icon i {
            width: 28px;
            height: 28px;
            stroke-width: 1.5;
        }

        .welcome-text {
            flex: 1;
        }

        .welcome-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }

        .welcome-subtitle {
            font-size: 1rem;
            color: #6b7280;
            line-height: 1.5;
            font-weight: 400;
        }

        .welcome-time {
            margin-top: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #9ca3af;
            font-size: 0.875rem;
        }

        .welcome-time i {
            width: 16px;
            height: 16px;
        }

        /* Estadísticas estilo reportes */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            box-shadow: var(--soft-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
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

        /* Grid principal mejorado */
        .dashboard-main-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .dashboard-column {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        /* Widgets estilo reportes */
        .dashboard-widget {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .widget-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .widget-header h3 {
            margin: 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .widget-content {
            padding: 1.5rem;
        }

        .widget-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        /* Acciones rápidas */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .quick-action-btn {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e5e7eb;
            color: #374151;
            padding: 1rem;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: var(--soft-shadow);
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
            border-color: #8B4513;
            color: #8B4513;
        }

        .quick-action-btn i {
            width: 24px;
            height: 24px;
        }

        /* Gráficos estilo reportes */
        .chart-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
        }

        .chart-card h3 {
            margin: 0 0 1.5rem 0;
            color: #1f2937;
            font-size: 1.125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Actividad reciente */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-gradient);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(139, 69, 19, 0.3);
        }

        .activity-icon i {
            width: 20px;
            height: 20px;
        }

        .activity-content {
            flex: 1;
            min-width: 0;
        }

        .activity-text {
            color: #1f2937;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
            line-height: 1.4;
        }

        .activity-time {
            color: #6b7280;
            font-size: 0.75rem;
        }

        .view-all-link {
            color: #8B4513;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .view-all-link:hover {
            color: #a0522d;
            transform: translateX(2px);
        }

        .view-all-link i {
            width: 16px;
            height: 16px;
        }

        /* Estado vacío */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }

        .empty-state i {
            width: 48px;
            height: 48px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Estadísticas del sistema */
        .system-stats-section {
            margin-top: 2rem;
        }

        .system-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .system-stat {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }

        .system-stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 0.5rem;
        }

        .system-stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Header actualizado */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 24px;
            height: 80px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-left h1 {
            margin: 0;
            color: #1f2937;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .header-info {
            text-align: right;
        }

        .user-name-header {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.875rem;
            margin-bottom: 2px;
        }

        .current-time {
            font-size: 0.75rem;
            color: #6b7280;
            font-family: monospace;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-icon {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e5e7eb;
            color: #374151;
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-icon:hover {
            border-color: #8B4513;
            color: #8B4513;
            transform: translateY(-1px);
            box-shadow: var(--soft-shadow);
        }

        .btn-icon i {
            width: 20px;
            height: 20px;
        }

        .mobile-menu-toggle {
            display: none;
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

        .dashboard-widget {
            animation: fadeInUp 0.6s ease-out;
            animation-delay: 0.5s;
        }

        .chart-card {
            animation: fadeInUp 0.6s ease-out;
            animation-delay: 0.6s;
        }

        /* Responsividad */
        @media (max-width: 1024px) {
            .dashboard-main-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
                background: none;
                border: none;
                color: #374151;
                padding: 0.5rem;
                cursor: pointer;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }

            .chart-container {
                height: 250px;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .welcome-section {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .welcome-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .welcome-icon {
                width: 50px;
                height: 50px;
                align-self: center;
            }

            .welcome-icon i {
                width: 24px;
                height: 24px;
            }

            .welcome-title {
                font-size: 1.5rem;
            }

            .welcome-subtitle {
                font-size: 0.875rem;
            }

            .welcome-time {
                justify-content: center;
                margin-top: 0.5rem;
            }

            .stat-card {
                padding: 1.5rem;
                gap: 1rem;
            }

            .stat-icon {
                width: 60px;
                height: 60px;
            }

            .stat-icon i {
                width: 30px;
                height: 30px;
            }

            .stat-number {
                font-size: 2rem;
            }

            .system-stats {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .header-right {
                gap: 0.5rem;
            }

            .header-info {
                display: none;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 200px;
            }
        }

        /* Efectos hover mejorados */
        .chart-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .dashboard-widget:hover {
            transform: translateY(-1px);
            box-shadow: var(--soft-shadow-lg);
        }

        /* Scrollbar personalizado */
        .activity-list::-webkit-scrollbar {
            width: 4px;
        }

        .activity-list::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 2px;
        }

        .activity-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }

        .activity-list::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Mejoras de accesibilidad */
        .quick-action-btn:focus,
        .btn-icon:focus,
        .view-all-link:focus {
            outline: 2px solid #8B4513;
            outline-offset: 2px;
        }

        /* Indicadores de estado */
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #2563eb);
            border-radius: 16px 16px 0 0;
        }
    </style>
</body>

</html>

<?php
// Funciones auxiliares para la actividad
function getActivityIcon($action) {
    switch ($action) {
        case 'login': return 'log-in';
        case 'logout': return 'log-out';
        case 'document_upload': return 'upload';
        case 'document_download': return 'download';
        case 'user_created': return 'user-plus';
        case 'company_created': return 'building';
        case 'document_deleted': return 'trash-2';
        case 'dashboard_access': return 'home';
        default: return 'activity';
    }
}

function getActivityDescription($action) {
    switch ($action) {
        case 'login': return 'inició sesión';
        case 'logout': return 'cerró sesión';
        case 'document_upload': return 'subió un documento';
        case 'document_download': return 'descargó un documento';
        case 'user_created': return 'creó un usuario';
        case 'company_created': return 'creó una empresa';
        case 'document_deleted': return 'eliminó un documento';
        case 'dashboard_access': return 'accedió al dashboard';
        default: return 'realizó una acción';
    }
}

function formatTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Hace menos de 1 minuto';
    if ($time < 3600) return 'Hace ' . floor($time/60) . ' minutos';
    if ($time < 86400) return 'Hace ' . floor($time/3600) . ' horas';
    if ($time < 2592000) return 'Hace ' . floor($time/86400) . ' días';
    
    return date('d/m/Y', strtotime($datetime));
}
?>