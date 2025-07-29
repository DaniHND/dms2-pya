<?php
// modules/reports/user_reports.php
// Reportes por usuario del sistema - DMS2
// VERSION CON SEGURIDAD - Usuarios no ven datos de administradores

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtrado
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$selectedUserId = $_GET['user_id'] ?? '';
$reportType = $_GET['report_type'] ?? 'summary';

// Función para obtener usuarios para el filtro (CON SEGURIDAD)
function getUsers($currentUser)
{
    if ($currentUser['role'] === 'admin') {
        // Admin puede ver todos los usuarios
        $query = "SELECT id, username, first_name, last_name, email, company_id, role 
                  FROM users 
                  WHERE status = 'active' 
                  ORDER BY first_name, last_name";
        return fetchAll($query);
    } else {
        // Usuario normal NO puede ver administradores
        $query = "SELECT id, username, first_name, last_name, email, company_id, role 
                  FROM users 
                  WHERE company_id = :company_id 
                  AND status = 'active' 
                  AND role != 'admin'
                  ORDER BY first_name, last_name";
        return fetchAll($query, ['company_id' => $currentUser['company_id']]);
    }
}

// Función para obtener estadísticas generales de usuarios (CON SEGURIDAD)
function getUsersStats($currentUser, $dateFrom, $dateTo, $selectedUserId = '')
{
    $whereCondition = "";
    $params = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];

    // Filtro por empresa y EXCLUIR ADMINS si no es admin
    if ($currentUser['role'] !== 'admin') {
        $whereCondition = "AND u.company_id = :company_id AND u.role != 'admin'";
        $params['company_id'] = $currentUser['company_id'];
    }

    // Filtro por usuario específico CON VALIDACIÓN DE SEGURIDAD
    if (!empty($selectedUserId)) {
        // SEGURIDAD: Verificar que el usuario seleccionado no sea admin (si el usuario actual no es admin)
        if ($currentUser['role'] !== 'admin') {
            $checkQuery = "SELECT role FROM users WHERE id = :check_user_id";
            $checkResult = fetchOne($checkQuery, ['check_user_id' => $selectedUserId]);
            if ($checkResult && $checkResult['role'] === 'admin') {
                // Si intenta ver un admin, resetear el filtro
                $selectedUserId = '';
            }
        }
        
        if (!empty($selectedUserId)) {
            $whereCondition .= " AND al.user_id = :selected_user_id";
            $params['selected_user_id'] = $selectedUserId;
        }
    }

    $stats = [];

    // Total de usuarios activos (sin filtro de usuario específico, pero SIN ADMINS para users)
    $userWhereCondition = "";
    $userParams = [];
    if ($currentUser['role'] !== 'admin') {
        $userWhereCondition = "AND u.company_id = :company_id AND u.role != 'admin'";
        $userParams['company_id'] = $currentUser['company_id'];
    }
    
    $query = "SELECT COUNT(*) as total FROM users u WHERE u.status = 'active' $userWhereCondition";
    $result = fetchOne($query, $userParams);
    $stats['total_users'] = $result['total'] ?? 0;

    // Usuarios con actividad reciente (CON SEGURIDAD)
    $query = "SELECT COUNT(DISTINCT al.user_id) as active_users
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereCondition";

    $result = fetchOne($query, $params);
    $stats['active_users'] = $result['active_users'] ?? 0;

    // Top usuarios por actividad (CON SEGURIDAD)
    $query = "SELECT u.first_name, u.last_name, u.username, COUNT(*) as activity_count
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereCondition
              GROUP BY al.user_id, u.first_name, u.last_name, u.username
              ORDER BY activity_count DESC
              LIMIT 10";

    $stats['top_users'] = fetchAll($query, $params);

    // Actividad por tipo de acción (CON SEGURIDAD)
    $query = "SELECT al.action, COUNT(*) as count
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.created_at >= :date_from 
              AND al.created_at <= :date_to
              $whereCondition
              GROUP BY al.action
              ORDER BY count DESC";

    $stats['by_action'] = fetchAll($query, $params);

    return $stats;
}

// Función para obtener estadísticas de un usuario específico
function getSelectedUserStats($userId, $dateFrom, $dateTo)
{
    $params = [
        'user_id' => $userId,
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];

    $stats = [];

    // Total de actividades del usuario
    $query = "SELECT COUNT(*) as total FROM activity_logs WHERE user_id = :user_id AND created_at >= :date_from AND created_at <= :date_to";
    $result = fetchOne($query, $params);
    $stats['total_activities'] = $result['total'] ?? 0;

    // Actividades por acción
    $query = "SELECT action, COUNT(*) as count FROM activity_logs WHERE user_id = :user_id AND created_at >= :date_from AND created_at <= :date_to GROUP BY action ORDER BY count DESC";
    $stats['by_action'] = fetchAll($query, $params);

    // Actividades por día
    $query = "SELECT DATE(created_at) as date, COUNT(*) as count FROM activity_logs WHERE user_id = :user_id AND created_at >= :date_from AND created_at <= :date_to GROUP BY DATE(created_at) ORDER BY date";
    $stats['by_date'] = fetchAll($query, $params);

    // Actividades recientes
    $query = "SELECT * FROM activity_logs WHERE user_id = :user_id AND created_at >= :date_from AND created_at <= :date_to ORDER BY created_at DESC LIMIT 20";
    $stats['recent_activities'] = fetchAll($query, $params);

    return $stats;
}

// Obtener datos (PASANDO EL FILTRO DE USUARIO CON SEGURIDAD)
$users = getUsers($currentUser);
$generalStats = getUsersStats($currentUser, $dateFrom, $dateTo, $selectedUserId);
$selectedUserStats = [];
$selectedUserActionStats = [];

if (!empty($selectedUserId)) {
    // VALIDACIÓN ADICIONAL: Verificar que el usuario seleccionado sea válido
    $validUser = false;
    foreach ($users as $user) {
        if ($user['id'] == $selectedUserId) {
            $validUser = true;
            break;
        }
    }
    
    if ($validUser) {
        $selectedUserStats = getSelectedUserStats($selectedUserId, $dateFrom, $dateTo);
        $selectedUserActionStats = $selectedUserStats['by_action'] ?? [];
    }
}

// Registrar acceso
logActivity($currentUser['id'], 'view_user_reports', 'reports', null, 'Usuario accedió al reporte de usuarios');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Usuarios - DMS2</title>
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
                <h1>Reportes de Usuarios</h1>
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
            <!-- Navegación de reportes -->
            <div class="reports-nav-breadcrumb">
                <a href="index.php" class="breadcrumb-link">
                    <i data-feather="arrow-left"></i>
                    Volver a Reportes
                </a>
            </div>

            <!-- Estadísticas generales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($generalStats['total_users']); ?></div>
                        <div class="stat-label"><?php echo $currentUser['role'] === 'admin' ? 'Total Usuarios' : 'Usuarios de mi Empresa'; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="user-check"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($generalStats['active_users']); ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="activity"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number">
                            <?php
                            $totalActivity = 0;
                            foreach ($generalStats['by_action'] as $action) {
                                $totalActivity += $action['count'];
                            }
                            echo number_format($totalActivity);
                            ?>
                        </div>
                        <div class="stat-label">Total Actividades</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="trending-up"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number">
                            <?php
                            $days = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400);
                            echo number_format($totalActivity / $days, 1);
                            ?>
                        </div>
                        <div class="stat-label">Promedio Diario</div>
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
                            <label for="user_id">Usuario Específico</label>
                            <select id="user_id" name="user_id">
                                <option value=""><?php echo $currentUser['role'] === 'admin' ? 'Todos los usuarios' : 'Usuarios de mi empresa'; ?></option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (@' . $user['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="report_type">Vista</label>
                            <select id="report_type" name="report_type">
                                <option value="summary" <?php echo $reportType == 'summary' ? 'selected' : ''; ?>>Resumen</option>
                                <option value="detailed" <?php echo $reportType == 'detailed' ? 'selected' : ''; ?>>Detallado</option>
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

            <!-- Exportación -->
            <div class="export-section">
                <h3>Exportar Datos</h3>
                <div class="export-buttons">
                    <button class="export-btn csv" onclick="exportarDatos('csv')">
                        <i data-feather="file-text"></i>
                        Descargar CSV
                    </button>
                    <button class="export-btn excel" onclick="exportarDatos('excel')">
                        <i data-feather="grid"></i>
                        Descargar Excel
                    </button>
                    <button class="export-btn pdf" onclick="exportarDatos('pdf')">
                        <i data-feather="file"></i>
                        Descargar PDF
                    </button>
                </div>
            </div>

            <?php if ($reportType === 'summary'): ?>
                <!-- Vista resumen -->
                <div class="reports-charts">
                    <div class="chart-grid">
                        <!-- Top usuarios más activos -->
                        <div class="chart-card">
                            <h3><?php echo !empty($selectedUserId) ? 'Actividad del Usuario Seleccionado' : ($currentUser['role'] === 'admin' ? 'Usuarios Más Activos' : 'Usuarios Más Activos de mi Empresa'); ?></h3>
                            <div class="chart-data">
                                <?php if (!empty($generalStats['top_users'])): ?>
                                    <?php foreach ($generalStats['top_users'] as $user): ?>
                                        <div class="data-item">
                                            <div class="item-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                            <div class="item-count"><?php echo number_format($user['activity_count']); ?> actividades</div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">Sin datos para mostrar</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actividades por tipo -->
                        <div class="chart-card">
                            <h3><?php echo !empty($selectedUserId) ? 'Actividades por Tipo del Usuario' : 'Actividades por Tipo'; ?></h3>
                            <div class="chart-container">
                                <canvas id="activityTypeChart"></canvas>
                            </div>
                        </div>

                        <?php if (!empty($selectedUserId) && !empty($selectedUserStats)): ?>
                        <!-- Estadísticas del usuario seleccionado -->
                        <div class="chart-card full-width">
                            <h3>Actividad Detallada del Usuario Seleccionado</h3>
                            <div class="user-stats">
                                <div class="user-metric">
                                    <div class="metric-number"><?php echo number_format($selectedUserStats['total_activities']); ?></div>
                                    <div class="metric-label">Total Actividades</div>
                                </div>
                                <div class="chart-container">
                                    <canvas id="userActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Vista detallada -->
                <div class="reports-table">
                    <h3><?php echo $currentUser['role'] === 'admin' ? 'Lista Detallada de Usuarios' : 'Lista de Usuarios de mi Empresa'; ?></h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Email</th>
                                    <th>Actividades</th>
                                    <th>Última Actividad</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): ?>
                                        <?php
                                        // Obtener actividades del usuario con filtro por usuario específico
                                        $userActivityParams = [
                                            'user_id' => $user['id'],
                                            'date_from' => $dateFrom . ' 00:00:00',
                                            'date_to' => $dateTo . ' 23:59:59'
                                        ];
                                        
                                        // Si hay un usuario seleccionado y no es el actual, saltar
                                        if (!empty($selectedUserId) && $selectedUserId != $user['id']) {
                                            continue;
                                        }
                                        
                                        $userActivityQuery = "SELECT COUNT(*) as total, MAX(created_at) as last_activity FROM activity_logs WHERE user_id = :user_id AND created_at >= :date_from AND created_at <= :date_to";
                                        $userActivity = fetchOne($userActivityQuery, $userActivityParams);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <span class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                                    <small class="username">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge badge-info"><?php echo number_format($userActivity['total'] ?? 0); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($userActivity['last_activity']): ?>
                                                    <?php echo date('d/m/Y H:i', strtotime($userActivity['last_activity'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin actividad</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-success">Activo</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <div class="empty-content">
                                                <i data-feather="users"></i>
                                                <h4>No se encontraron usuarios</h4>
                                                <p>No hay usuarios que coincidan con los filtros seleccionados.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal para vista previa del PDF -->
    <div id="pdfModal" class="pdf-modal">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header">
                <h3 class="pdf-modal-title">Vista Previa del PDF - Reportes de Usuarios</h3>
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
        var userActionStats = <?php echo json_encode($selectedUserActionStats); ?>;

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);

            <?php if ($reportType === 'summary'): ?>
            initCharts();
            <?php endif; ?>
        });

        function initCharts() {
            // Gráfico de actividades por tipo (AHORA CON FILTRO Y SEGURIDAD CORRECTOS)
            const activityData = <?php echo json_encode($generalStats['by_action'] ?? []); ?>;
            
            if (activityData.length > 0) {
                const ctx = document.getElementById('activityTypeChart').getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: activityData.map(item => item.action),
                        datasets: [{
                            data: activityData.map(item => item.count),
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

            // Gráfico del usuario seleccionado
            <?php if (!empty($selectedUserId) && !empty($selectedUserActionStats)): ?>
            if (userActionStats.length > 0) {
                const userCtx = document.getElementById('userActivityChart').getContext('2d');
                new Chart(userCtx, {
                    type: 'bar',
                    data: {
                        labels: userActionStats.map(item => item.action),
                        datasets: [{
                            label: 'Actividades',
                            data: userActionStats.map(item => item.count),
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
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            <?php endif; ?>
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
            const exportUrl = 'export.php?format=' + formato + '&type=user_reports&modal=1&' + urlParams.toString();

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

        console.log('📊 Seguridad aplicada:', '<?php echo $currentUser['role'] === 'admin' ? 'Admin - Ve todos los usuarios' : 'Usuario - No ve administradores'; ?>');
        console.log('📊 Filtro de usuario:', '<?php echo !empty($selectedUserId) ? 'Usuario ID: ' . $selectedUserId : 'Todos los usuarios permitidos'; ?>');
    </script>
</body>

</html>