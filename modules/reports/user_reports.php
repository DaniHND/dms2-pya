<?php
// modules/reports/user_reports.php
// Reportes por usuario con seguridad y sin vista previa - DMS2

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
function getUsersWithStats($currentUser, $dateFrom, $dateTo, $selectedUserId = '')
{
    if ($currentUser['role'] === 'admin') {
        // Admin puede ver todos los usuarios
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
                  WHERE u.status = 'active'";
        
        $params = [
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59'
        ];
        
        if (!empty($selectedUserId)) {
            $query .= " AND u.id = :selected_user_id";
            $params['selected_user_id'] = $selectedUserId;
        }
        
        $query .= " ORDER BY activity_count DESC, u.first_name";
        
    } else {
        // Usuario normal solo puede ver sus propios datos
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
                  WHERE u.status = 'active' AND u.id = :current_user_id";
        
        $params = [
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59',
            'current_user_id' => $currentUser['id']
        ];
    }

    return fetchAll($query, $params);
}

// Función para obtener actividad detallada de un usuario
function getUserActivity($userId, $dateFrom, $dateTo, $currentUser, $limit = 20)
{
    // Solo admin puede ver actividad de otros usuarios
    if ($currentUser['role'] !== 'admin' && $userId != $currentUser['id']) {
        return []; // Usuario normal no puede ver actividad de otros
    }
    
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
function getUserActionStats($userId, $dateFrom, $dateTo, $currentUser)
{
    // Solo admin puede ver estadísticas de otros usuarios
    if ($currentUser['role'] !== 'admin' && $userId != $currentUser['id']) {
        return []; // Usuario normal no puede ver estadísticas de otros
    }
    
    // Filtrar solo las acciones que queremos mostrar en el gráfico
    $query = "SELECT action, COUNT(*) as count
              FROM activity_logs
              WHERE user_id = :user_id 
              AND created_at >= :date_from 
              AND created_at <= :date_to
              AND action IN ('upload', 'download', 'view')
              GROUP BY action
              ORDER BY count DESC";

    $params = [
        'user_id' => $userId,
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];

    return fetchAll($query, $params);
}

// Función para obtener total de usuarios
function getTotalUsers($currentUser, $dateFrom, $dateTo, $selectedUserId)
{
    if ($currentUser['role'] === 'admin') {
        if ($selectedUserId) {
            return 1; // Si hay un usuario específico, siempre es 1
        }
        $query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
        $params = [];
    } else {
        // Usuario normal solo puede ver sus propios datos
        return 1;
    }
    
    $result = fetchOne($query, $params);
    return $result['total'] ?? 0;
}

$users = getUsersWithStats($currentUser, $dateFrom, $dateTo, $selectedUserId);
$totalUsers = getTotalUsers($currentUser, $dateFrom, $dateTo, $selectedUserId);
$selectedUserActivity = [];
$selectedUserActionStats = [];
$selectedUserInfo = null;

// Validación de seguridad para usuario seleccionado
if ($selectedUserId) {
    // Si no es admin, solo puede ver sus propios datos
    if ($currentUser['role'] !== 'admin' && $selectedUserId != $currentUser['id']) {
        // Redirigir con mensaje de error de seguridad
        header('Location: user_reports.php?error=access_denied');
        exit();
    }
    
    $selectedUserActivity = getUserActivity($selectedUserId, $dateFrom, $dateTo, $currentUser);
    $selectedUserActionStats = getUserActionStats($selectedUserId, $dateFrom, $dateTo, $currentUser);

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
    <link rel="stylesheet" href="../../assets/css/modal.css">
    <link rel="stylesheet" href="../../assets/css/summary.css">

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

            <!-- Mensaje de error de acceso denegado -->
            <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
                <div class="error-message">
                    <i data-feather="alert-triangle"></i>
                    <span>Acceso denegado: Solo puede ver sus propios datos de usuario.</span>
                </div>
            <?php endif; ?>

            <!-- Resumen de resultados -->
            <div class="results-summary">
                <div class="summary-stats">
                    <div class="stat-item">
                        <i data-feather="users"></i>
                        <span class="stat-number"><?php echo number_format($totalUsers); ?></span>
                        <span class="stat-label"><?php echo $currentUser['role'] === 'admin' ? 'Total Usuarios' : 'Mis Datos'; ?></span>
                    </div>
                    <div class="stat-item">
                        <i data-feather="calendar"></i>
                        <span class="stat-number"><?php echo date('d/m/Y', strtotime($dateFrom)) . ' - ' . date('d/m/Y', strtotime($dateTo)); ?></span>
                        <span class="stat-label">Período</span>
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
                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <div class="filter-group">
                                <label for="user_id">Usuario Específico</label>
                                <select id="user_id" name="user_id">
                                    <option value="">Ver todos los usuarios</option>
                                    <?php 
                                    // Obtener todos los usuarios para el select (solo admin)
                                    $allUsers = getUsersWithStats($currentUser, $dateFrom, $dateTo);
                                    foreach ($allUsers as $user): 
                                    ?>
                                        <option value="<?php echo $user['id']; ?>"
                                            <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
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
                    <?php if (!empty($selectedUserActionStats)): ?>
                        <div class="user-chart-section">
                            <h3>Distribución de Acciones</h3>
                            <canvas id="userActionsChart"></canvas>
                        </div>
                    <?php endif; ?>

                    
                </div>
            <?php else: ?>
                <!-- Tabla de usuarios con estadísticas -->
                <div class="reports-table">
                    <h3><?php echo $currentUser['role'] === 'admin' ? 'Usuarios y sus Estadísticas (' . count($users) . ' usuarios)' : 'Mis Estadísticas'; ?></h3>
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
                                    <?php if ($currentUser['role'] === 'admin'): ?>
                                        <th>Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="<?php echo $currentUser['role'] === 'admin' ? '8' : '7'; ?>" class="empty-state">
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
                                            <?php if ($currentUser['role'] === 'admin'): ?>
                                                <td>
                                                    <a href="?user_id=<?php echo $user['id']; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>"
                                                        class="btn-action" title="Ver detalles">
                                                        <i data-feather="eye"></i>
                                                    </a>
                                                </td>
                                            <?php endif; ?>
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

            <?php if ($selectedUserId && !empty($selectedUserActionStats)): ?>
                initUserActionsChart();
            <?php endif; ?>
        });

        function initUserActionsChart() {
            const ctx = document.getElementById('userActionsChart').getContext('2d');

            // Mapear acciones a etiquetas más descriptivas
            const actionLabels = {
                'upload': 'Subir Archivos',
                'download': 'Descargar Archivos', 
                'view': 'Ver Documentos'
            };

            const labels = userActionStats.map(item => actionLabels[item.action] || item.action);
            const data = userActionStats.map(item => parseInt(item.count));

            // Colores específicos para cada acción
            const actionColors = {
                'upload': '#10b981',    // Verde para subidas
                'download': '#3b82f6',  // Azul para descargas
                'view': '#8b5cf6'       // Morado para visualizaciones
            };

            const colors = userActionStats.map(item => actionColors[item.action] || '#8B4513');

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Cantidad de Acciones',
                        data: data,
                        backgroundColor: colors,
                        borderColor: colors.map(color => color + 'CC'), // Añadir transparencia al borde
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false // Ocultar leyenda en gráfico de barras
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed.y || 0;
                                    return `${label}: ${value} acciones`;
                                }
                            },
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            cornerRadius: 6
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 11
                                },
                                color: '#6b7280'
                            },
                            grid: {
                                color: '#e5e7eb',
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: 'Número de Acciones',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                },
                                color: '#374151'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11,
                                    weight: '500'
                                },
                                color: '#374151'
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    elements: {
                        bar: {
                            borderRadius: {
                                topLeft: 8,
                                topRight: 8,
                                bottomLeft: 0,
                                bottomRight: 0
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    },
                    onHover: {
                        mode: 'nearest',
                        intersect: false
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
                loading.innerHTML = '<div class="spinner"></div><p>Error al cargar la vista previa. <a href="' + url.replace('&modal=1', '&download=1') + '" target="_blank">Descargar PDF directamente</a></p>';
            };

            iframe.src = url;
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
            notification.className = `notification-toast ${tipo}`;
            notification.innerHTML = `
                <i data-feather="${getNotificationIcon(tipo)}"></i>
                <span>${mensaje}</span>
                <button onclick="this.parentElement.remove()">
                    <i data-feather="x"></i>
                </button>
            `;

            // Agregar al DOM
            document.body.appendChild(notification);
            feather.replace();

            // Mostrar animación
            setTimeout(() => notification.classList.add('visible'), 100);

            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.classList.remove('visible');
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        function getNotificationIcon(tipo) {
            const icons = {
                'success': 'check-circle',
                'error': 'alert-circle',
                'warning': 'alert-triangle',
                'info': 'info'
            };
            return icons[tipo] || 'info';
        }

        function showComingSoon(feature) {
            mostrarNotificacion(`${feature} - Próximamente`, 'info');
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('pdfModal');
            if (event.target === modal) {
                cerrarModalPDF();
            }
        }

        // Cerrar modal con Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModalPDF();
            }
        });

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
        'view' => 'info',
        'admin' => 'error',
        'user' => 'info'
    ];

    return $classes[$action] ?? 'info';
}
?>