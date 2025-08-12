<?php
// dashboard.php - Actualizado con estadísticas de empresas

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

    // Total de empresas - ACTUALIZADO
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM companies WHERE status = 'active'";
        $result = fetchOne($query);
        $stats['total_companies'] = $result['total'] ?? 0;
    } else {
        $stats['total_companies'] = 1;  // El usuario solo ve su empresa
    }

    return $stats;
}

// Obtener actividad reciente
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

    $result = fetchAll($query, $params);
    return $result ?: [];
}

// Obtener datos para el dashboard
$stats = getDashboardStats($currentUser['id'], $currentUser['company_id'], $currentUser['role']);
$recentActivity = getRecentActivity($currentUser['id'], $currentUser['role'], $currentUser['company_id']);

// Registrar acceso al dashboard
logActivity($currentUser['id'], 'dashboard_access', 'dashboard', null, 'Usuario accedió al dashboard');
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
                    <div class="company-name"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
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
            <!-- Tarjetas de estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="stat-number"><?php echo number_format($stats['total_documents']); ?></div>
                        <div class="stat-label">Total Documentos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="stat-number"><?php echo number_format($stats['documents_today']); ?></div>
                        <div class="stat-label">Subidos Hoy</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="stat-number"><?php echo number_format($stats['total_companies']); ?></div>
                        <div class="stat-label"><?php echo $currentUser['role'] === 'admin' ? 'Empresas' : 'Mi Empresa'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Contenido principal en dos columnas -->
            <div class="dashboard-grid">
                <!-- Columna izquierda -->
                <div class="dashboard-column">
                    <!-- Acciones rápidas -->
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3>Acciones Rápidas</h3>
                            <i data-feather="zap"></i>
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

                    <!-- Estadísticas del Sistema -->
                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <div class="dashboard-widget">
                            <div class="widget-header">
                                <h3>Estadísticas del Sistema</h3>
                                <i data-feather="trending-up"></i>
                            </div>
                            <div class="widget-content">
                                <div class="system-stats">
                                    <div class="system-stat">
                                        <div class="system-stat-label">Empresas Activas</div>
                                        <div class="system-stat-value"><?php echo number_format($stats['total_companies']); ?></div>
                                    </div>
                                    <div class="system-stat">
                                        <div class="system-stat-label">Usuarios Totales</div>
                                        <div class="system-stat-value"><?php echo number_format($stats['total_users']); ?></div>
                                    </div>
                                    <div class="system-stat">
                                        <div class="system-stat-label">Documentos Totales</div>
                                        <div class="system-stat-value"><?php echo number_format($stats['total_documents']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Columna derecha -->
                <div class="dashboard-column">
                    <!-- Actividad reciente -->
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3>Actividad Reciente</h3>
                            <i data-feather="activity"></i>
                        </div>
                        <div class="widget-content">
                            <?php if (!empty($recentActivity)): ?>
                                <div class="activity-list">
                                    <?php foreach (array_slice($recentActivity, 0, 8) as $activity): ?>
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
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="assets/js/main.js"></script>
    <script>
        // Inicializar iconos PRIMERO
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar iconos de Feather
            if (typeof feather !== 'undefined') {
                feather.replace();
                console.log('✅ Iconos de Feather inicializados');
            } else {
                console.warn('⚠️ Feather Icons no está disponible');
            }
        });
        
        // Reinicializar iconos después de cualquier cambio
        function reinitializeIcons() {
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        }

        // Actualizar reloj
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('es-ES', {
                day: '2-digit',
                month: '2-digit', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }

        updateTime();
        setInterval(updateTime, 60000);

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