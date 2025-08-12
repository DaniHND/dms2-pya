<?php
// modules/reports/activity_log.php
// Log de actividades del sistema - DMS2
// VERSION CON SEGURIDAD - Usuarios no ven datos de administradores

require_once '../../config/session.php';
require_once '../../config/database.php';

// Función helper para obtener nombre completo si no existe
if (!function_exists('getFullName')) {
    function getFullName() {
        $currentUser = SessionManager::getCurrentUser();
        if ($currentUser) {
            return trim($currentUser['first_name'] . ' ' . $currentUser['last_name']);
        }
        return 'Usuario';
    }
}

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtrado y paginación
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$userId = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$recordsPerPage = 50;
$offset = ($page - 1) * $recordsPerPage;

// Función para obtener actividades con paginación (CON SEGURIDAD)
function getActivities($currentUser, $dateFrom, $dateTo, $userId, $action, $offset, $limit)
{
    $whereConditions = [];
    $params = [];

    // Filtro por fechas
    $whereConditions[] = "al.created_at >= :date_from";
    $whereConditions[] = "al.created_at <= :date_to";
    $params['date_from'] = $dateFrom . ' 00:00:00';
    $params['date_to'] = $dateTo . ' 23:59:59';

    // Filtro por empresa y EXCLUIR ADMINS si no es admin
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "u.company_id = :company_id";
        $whereConditions[] = "u.role != 'admin'";
        $params['company_id'] = $currentUser['company_id'];
    }

    // Filtro por usuario CON VALIDACIÓN DE SEGURIDAD
    if (!empty($userId)) {
        // SEGURIDAD: Verificar que el usuario no sea admin (si el usuario actual no es admin)
        if ($currentUser['role'] !== 'admin') {
            $checkQuery = "SELECT role, company_id FROM users WHERE id = :check_user_id";
            $checkResult = fetchOne($checkQuery, ['check_user_id' => $userId]);
            if (!$checkResult || 
                $checkResult['role'] === 'admin' || 
                $checkResult['company_id'] != $currentUser['company_id']) {
                // Si intenta ver un admin o usuario de otra empresa, no mostrar nada
                return [];
            }
        }
        
        $whereConditions[] = "al.user_id = :user_id";
        $params['user_id'] = $userId;
    }

    // Filtro por acción
    if (!empty($action)) {
        $whereConditions[] = "al.action = :action";
        $params['action'] = $action;
    }

    $whereClause = implode(' AND ', $whereConditions);

    $query = "SELECT al.*, u.first_name, u.last_name, u.username, c.name as company_name
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              LEFT JOIN companies c ON u.company_id = c.id
              WHERE $whereClause
              ORDER BY al.created_at DESC
              LIMIT :limit OFFSET :offset";

    $params['limit'] = $limit;
    $params['offset'] = $offset;

    try {
        return fetchAll($query, $params);
    } catch (Exception $e) {
        error_log("Error en getActivities: " . $e->getMessage());
        return []; // Devolver array vacío en caso de error
    }
}

// Función para obtener el total de registros (CON SEGURIDAD)
function getTotalActivities($currentUser, $dateFrom, $dateTo, $userId, $action)
{
    $whereConditions = [];
    $params = [];

    // Filtro por fechas
    $whereConditions[] = "al.created_at >= :date_from";
    $whereConditions[] = "al.created_at <= :date_to";
    $params['date_from'] = $dateFrom . ' 00:00:00';
    $params['date_to'] = $dateTo . ' 23:59:59';

    // Filtro por usuario
    if (!empty($userId)) {
        // SEGURIDAD: Verificar que el usuario no sea admin (si el usuario actual no es admin)
        if ($currentUser['role'] !== 'admin') {
            $checkQuery = "SELECT role, company_id FROM users WHERE id = :check_user_id";
            $checkResult = fetchOne($checkQuery, ['check_user_id' => $userId]);
            if (!$checkResult || 
                $checkResult['role'] === 'admin' || 
                $checkResult['company_id'] != $currentUser['company_id']) {
                return 0; // Si intenta ver un admin, retornar 0
            }
        }
        
        $whereConditions[] = "al.user_id = :user_id";
        $params['user_id'] = $userId;
    }

    // Filtro por acción
    if (!empty($action)) {
        $whereConditions[] = "al.action = :action";
        $params['action'] = $action;
    }

    // Filtro por empresa (si no es admin)
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "u.company_id = :company_id";
        $whereConditions[] = "u.role != 'admin'";
        $params['company_id'] = $currentUser['company_id'];
    }

    $whereClause = implode(' AND ', $whereConditions);

    $query = "SELECT COUNT(*) as total
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              LEFT JOIN companies c ON u.company_id = c.id
              WHERE $whereClause";

    $result = fetchOne($query, $params);
    return $result['total'] ?? 0;
}

// Función para obtener usuarios para filtro (CON SEGURIDAD)
function getUsers($currentUser)
{
    if ($currentUser['role'] === 'admin') {
        $query = "SELECT id, username, first_name, last_name 
                  FROM users 
                  WHERE status = 'active' 
                  ORDER BY first_name, last_name";
        return fetchAll($query);
    } else {
        // Usuario normal NO puede ver administradores
        $query = "SELECT id, username, first_name, last_name 
                  FROM users 
                  WHERE company_id = :company_id 
                  AND status = 'active' 
                  AND role != 'admin'
                  ORDER BY first_name, last_name";
        return fetchAll($query, ['company_id' => $currentUser['company_id']]);
    }
}

// Función para obtener tipos de acciones
function getActionTypes()
{
    $query = "SELECT DISTINCT action FROM activity_logs ORDER BY action";
    return fetchAll($query);
}

// Función para traducir acciones
function translateAction($action)
{
    $translations = [
        'login' => 'Iniciar Sesión',
        'logout' => 'Cerrar Sesión',
        'upload' => 'Subir Archivo',
        'download' => 'Descargar',
        'delete' => 'Eliminar',
        'create' => 'Crear',
        'update' => 'Actualizar',
        'view' => 'Ver',
        'share' => 'Compartir',
        'access_denied' => 'Acceso Denegado',
        'view_activity_log' => 'Ver Log de Actividades',
        'export_csv' => 'Exportar CSV',
        'export_pdf' => 'Exportar PDF',
        'export_excel' => 'Exportar Excel'
    ];

    return $translations[$action] ?? ucfirst($action);
}

// Función para obtener iconos de acciones
function getActionIcon($action)
{
    $icons = [
        'login' => 'log-in',
        'logout' => 'log-out',
        'upload' => 'upload',
        'download' => 'download',
        'delete' => 'trash-2',
        'create' => 'plus',
        'update' => 'edit',
        'view' => 'eye',
        'share' => 'share-2',
        'access_denied' => 'shield-off',
        'view_activity_log' => 'activity',
        'export_csv' => 'file-text',
        'export_pdf' => 'file',
        'export_excel' => 'grid'
    ];

    return $icons[$action] ?? 'circle';
}

// Obtener datos
$activities = getActivities($currentUser, $dateFrom, $dateTo, $userId, $action, $offset, $recordsPerPage);
$totalRecords = getTotalActivities($currentUser, $dateFrom, $dateTo, $userId, $action);
$totalPages = ceil($totalRecords / $recordsPerPage);
$users = getUsers($currentUser);
$actionTypes = getActionTypes();

// Registrar acceso
logActivity($currentUser['id'], 'view_activity_log', 'reports', null, 'Usuario accedió al log de actividades');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Actividades - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reports.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">
    <script src="https://unpkg.com/feather-icons"></script>
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
                <h1>Log de Actividades</h1>
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

            <!-- Estadísticas resumen -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="activity"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($totalRecords); ?></div>
                        <div class="stat-label">Total Actividades</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format(count($users)); ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="calendar"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number">
                            <?php
                            $days = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1);
                            echo number_format($days);
                            ?>
                        </div>
                        <div class="stat-label">Días Analizados</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="trending-up"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number">
                            <?php
                            $days = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1);
                            echo number_format($totalRecords / $days, 1);
                            ?>
                        </div>
                        <div class="stat-label">Promedio Diario</div>
                    </div>
                </div>
            </div>

            <!-- Filtros automáticos -->
            <div class="reports-filters">
                <h3>Filtros de Búsqueda</h3>
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
                        <label for="user_id">Usuario</label>
                        <select id="user_id" name="user_id">
                            <option value="">Todos los usuarios</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (@' . $user['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="action">Acción</label>
                        <select id="action" name="action">
                            <option value="">Todas las acciones</option>
                            <?php foreach ($actionTypes as $actionType): ?>
                                <option value="<?php echo htmlspecialchars($actionType['action']); ?>" <?php echo $action === $actionType['action'] ? 'selected' : ''; ?>>
                                    <?php echo translateAction($actionType['action']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

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
                    <button class="export-btn pdf" onclick="exportarDatos('pdf')">
                        <i data-feather="file"></i>
                        Descargar PDF
                    </button>
                </div>
            </div>

            <!-- Tabla de actividades con diseño mejorado -->
            <div class="reports-table enhanced-table">
                <div class="table-header">
                    <h3><i data-feather="activity"></i> Log de Actividades (<?php echo number_format($totalRecords); ?> registros)</h3>
                    <div class="table-actions">
                        <span class="pagination-info-header">
                            Página <?php echo $page; ?> de <?php echo $totalPages; ?>
                        </span>
                        <span class="status-indicator active">
                            <i data-feather="database"></i>
                            Sistema Activo
                        </span>
                    </div>
                </div>

                <?php if (!empty($activities)): ?>
                    <div class="table-container">
                        <table class="data-table activity-table enhanced-activity-table">
                            <thead>
                                <tr>
                                    <th><i data-feather="calendar" class="table-icon"></i> Fecha/Hora</th>
                                    <th><i data-feather="user" class="table-icon"></i> Usuario</th>
                                    <?php if ($currentUser['role'] === 'admin'): ?>
                                    <th><i data-feather="building" class="table-icon"></i> Empresa</th>
                                    <?php endif; ?>
                                    <th><i data-feather="zap" class="table-icon"></i> Acción</th>
                                    <th><i data-feather="file-text" class="table-icon"></i> Descripción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $index => $activity): ?>
                                    <?php 
                                    $isRecentActivity = (time() - strtotime($activity['created_at'])) < 3600; // Última hora
                                    $rowClass = $isRecentActivity ? 'recent-activity-row' : '';
                                    $actionClass = 'action-' . $activity['action'];
                                    ?>
                                    <tr class="<?php echo $rowClass; ?>" data-activity-id="<?php echo $activity['id']; ?>">
                                        <td class="datetime-cell enhanced-datetime-cell">
                                            <div class="datetime enhanced-datetime">
                                                <div class="date"><?php echo date('d/m/Y', strtotime($activity['created_at'])); ?></div>
                                                <div class="time"><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></div>
                                                <div class="relative-time" title="<?php echo date('d/m/Y H:i:s', strtotime($activity['created_at'])); ?>">
                                                    <?php 
                                                    $diff = time() - strtotime($activity['created_at']);
                                                    if ($diff < 60) echo 'Hace ' . $diff . ' seg';
                                                    elseif ($diff < 3600) echo 'Hace ' . round($diff/60) . ' min';
                                                    elseif ($diff < 86400) echo 'Hace ' . round($diff/3600) . ' h';
                                                    else echo 'Hace ' . round($diff/86400) . ' días';
                                                    ?>
                                                </div>
                                            </div>
                                            <?php if ($isRecentActivity): ?>
                                                <div class="recent-badge">
                                                    <i data-feather="clock"></i>
                                                    Reciente
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="user-cell enhanced-user-cell">
                                            <div class="user-info-enhanced">
                                                <div class="user-avatar" data-initials="<?php echo strtoupper(substr($activity['first_name'] ?? 'U', 0, 1) . substr($activity['last_name'] ?? 'S', 0, 1)); ?>">
                                                    <?php echo strtoupper(substr($activity['first_name'] ?? 'U', 0, 1) . substr($activity['last_name'] ?? 'S', 0, 1)); ?>
                                                </div>
                                                <div class="user-details">
                                                    <span class="user-name">
                                                        <?php echo htmlspecialchars(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')); ?>
                                                    </span>
                                                    <small class="username">@<?php echo htmlspecialchars($activity['username'] ?? 'usuario'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <?php if ($currentUser['role'] === 'admin'): ?>
                                        <td class="company-cell">
                                            <div class="company-info">
                                                <i data-feather="building" class="company-icon"></i>
                                                <span><?php echo htmlspecialchars($activity['company_name'] ?? 'Sin empresa'); ?></span>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                        <td class="action-cell">
                                            <span class="action-badge <?php echo $actionClass; ?>">
                                                <i data-feather="<?php echo getActionIcon($activity['action']); ?>"></i>
                                                <?php echo translateAction($activity['action']); ?>
                                            </span>
                                        </td>
                                        <td class="description-cell">
                                            <div class="description-content">
                                                <?php echo htmlspecialchars($activity['description'] ?? 'Sin descripción'); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación mejorada -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination enhanced-pagination">
                            <div class="pagination-info">
                                <i data-feather="info"></i>
                                Mostrando <?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $recordsPerPage, $totalRecords)); ?> 
                                de <?php echo number_format($totalRecords); ?> registros
                            </div>
                            <div class="pagination-controls">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-btn" title="Primera página">
                                        <i data-feather="chevrons-left"></i>
                                    </a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn" title="Página anterior">
                                        <i data-feather="chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <span class="pagination-current">
                                    <i data-feather="bookmark"></i>
                                    <?php echo $page; ?> / <?php echo $totalPages; ?>
                                </span>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn" title="Página siguiente">
                                        <i data-feather="chevron-right"></i>
                                    </a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="pagination-btn" title="Última página">
                                        <i data-feather="chevrons-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state enhanced-empty-state">
                        <div class="empty-content">
                            <div class="empty-icon">
                                <i data-feather="activity"></i>
                            </div>
                            <h4>No se encontraron actividades</h4>
                            <p>No hay actividades que coincidan con los filtros seleccionados.</p>
                            <button class="btn-empty-action" onclick="autoFilter()">
                                <i data-feather="refresh-cw"></i>
                                Recargar datos
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Variables de configuración
        var currentFilters = <?php echo json_encode($_GET); ?>;

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);
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

        function exportarDatos(formato) {
            // Obtener parámetros actuales de la URL
            const urlParams = new URLSearchParams(window.location.search);

            // Construir URL de exportación
            const exportUrl = 'export.php?format=' + formato + '&type=activity_log&modal=1&' + urlParams.toString();

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
            // Crear modal para PDF dinámicamente (como documents_report.php)
            const modal = document.createElement('div');
            modal.className = 'pdf-modal';
            modal.innerHTML = `
                <div class="pdf-modal-content">
                    <div class="pdf-modal-header">
                        <h3><i data-feather="activity"></i> Log de Actividades - PDF</h3>
                        <button class="pdf-modal-close" onclick="cerrarModalPDF()">&times;</button>
                    </div>
                    <div class="pdf-modal-body">
                        <div class="pdf-preview-container">
                            <div class="pdf-loading">
                                <div class="loading-spinner"></div>
                                <p>Generando vista previa del PDF...</p>
                            </div>
                            <iframe id="pdfFrame" src="${url.replace('&modal=1', '')}" style="display: none;"></iframe>
                        </div>
                        <div class="pdf-actions">
                            <button class="btn-primary" onclick="descargarPDF('${url.replace('&modal=1', '')}')">
                                <i data-feather="download"></i> Descargar PDF
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            feather.replace();
            
            // Mostrar iframe cuando cargue
            const iframe = document.getElementById('pdfFrame');
            iframe.onload = function() {
                document.querySelector('.pdf-loading').style.display = 'none';
                iframe.style.display = 'block';
            };
            
            // Manejar errores de carga
            iframe.onerror = function() {
                document.querySelector('.pdf-loading').innerHTML = '<div class="loading-spinner"></div><p style="color: #ef4444;">Error al cargar la vista previa. <button onclick="cerrarModalPDF()" style="margin-left: 10px; padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">Cerrar</button></p>';
            };
            
            // Cerrar modal al hacer clic fuera
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    cerrarModalPDF();
                }
            });
        }

        function cerrarModalPDF() {
            const modal = document.querySelector('.pdf-modal');
            if (modal) {
                modal.remove();
            }
        }

        function descargarPDF(url) {
            window.open(url, '_blank');
            mostrarNotificacion('Descargando PDF...', 'success');
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
            setTimeout(() => notification.remove(), 3000);
        }

        // Filtros automáticos sin botones
        // Filtros automáticos sin botones
        document.addEventListener('change', function(e) {
            if (e.target.matches('#date_from, #date_to, #user_id, #action')) {
                autoFilter();
            }
        });

        function autoFilter() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const userId = document.getElementById('user_id').value;
            const action = document.getElementById('action').value;

            const params = new URLSearchParams();
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);
            if (userId) params.set('user_id', userId);
            if (action) params.set('action', action);

            window.location.href = window.location.pathname + '?' + params.toString();
        }

        function showComingSoon(feature) {
            alert(`${feature} - Próximamente`);
        }
    </script>

    <style>
        /* Colores suaves y congruentes para el sistema */
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

        /* Mejoras en las estadísticas con gradientes suaves */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
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
            background: var(--primary-gradient);
        }

        .stat-card:nth-child(2)::before {
            background: var(--info-gradient);
        }

        .stat-card:nth-child(3)::before {
            background: var(--success-gradient);
        }

        .stat-card:nth-child(4)::before {
            background: var(--warning-gradient);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
        }

        .stat-icon {
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

        .stat-card:nth-child(2) .stat-icon {
            background: var(--info-gradient);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: var(--success-gradient);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: var(--warning-gradient);
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        /* Tabla mejorada con colores suaves */
        .enhanced-table {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: var(--soft-shadow-lg);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            margin: 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-header i {
            color: #8B4513;
        }

        .table-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .pagination-info-header {
            background: var(--info-gradient);
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 16px;
            font-size: 0.75rem;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--success-gradient);
            color: white;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }

        /* Avatares de usuario */
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 2px 4px rgba(139, 69, 19, 0.3);
            flex-shrink: 0;
        }

        .user-info-enhanced {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-name {
            font-weight: 500;
            color: #1f2937;
        }

        .username {
            color: #6b7280;
            font-size: 0.75rem;
        }

        /* Actividades recientes */
        .recent-activity-row {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 5%, transparent 5%);
            border-left: 3px solid #0ea5e9;
        }

        .recent-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--info-gradient);
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.6rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 2px;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }

        .recent-badge i {
            width: 10px;
            height: 10px;
        }

        /* Headers con iconos */
        .table-icon {
            width: 16px;
            height: 16px;
            margin-right: 0.5rem;
            color: #6b7280;
        }

        .enhanced-activity-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1rem 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            border-bottom: 2px solid #e5e7eb;
            white-space: nowrap;
        }

        .enhanced-activity-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .enhanced-activity-table tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        /* Badges de acciones mejorados */
        .action-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            box-shadow: var(--soft-shadow);
        }

        .action-login {
            background: var(--success-gradient);
            color: white;
        }

        .action-logout {
            background: var(--danger-gradient);
            color: white;
        }

        .action-upload {
            background: var(--info-gradient);
            color: white;
        }

        .action-download {
            background: var(--warning-gradient);
            color: white;
        }

        .action-delete {
            background: var(--danger-gradient);
            color: white;
        }

        .action-create {
            background: var(--success-gradient);
            color: white;
        }

        .action-update {
            background: var(--secondary-gradient);
            color: white;
        }

        .action-view {
            background: var(--info-gradient);
            color: white;
        }

        .action-share {
            background: var(--warning-gradient);
            color: white;
        }

        /* Datetime mejorado */
        .enhanced-datetime {
            display: flex;
            flex-direction: column;
            gap: 2px;
            position: relative;
        }

        .enhanced-datetime .date {
            font-weight: 500;
            color: #1f2937;
            font-size: 0.875rem;
        }

        .enhanced-datetime .time {
            color: #6b7280;
            font-size: 0.75rem;
            font-family: monospace;
        }

        .relative-time {
            font-size: 0.7rem;
            color: #8B4513;
            font-weight: 500;
            background: rgba(139, 69, 19, 0.1);
            padding: 2px 6px;
            border-radius: 8px;
            align-self: flex-start;
        }

        /* Empresa info */
        .company-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #4f46e5;
            font-weight: 500;
        }

        .company-icon {
            width: 16px;
            height: 16px;
            color: #6b7280;
        }

        /* Descripción */
        .description-content {
            max-width: 300px;
            word-wrap: break-word;
            line-height: 1.4;
        }

        /* Paginación mejorada */
        .enhanced-pagination {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pagination-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: var(--soft-shadow);
        }

        .pagination-btn:hover {
            background: var(--primary-gradient);
            border-color: #8B4513;
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
        }

        .pagination-current {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-gradient);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 4px 8px rgba(139, 69, 19, 0.3);
        }

        /* Estado vacío mejorado */
        .enhanced-empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            box-shadow: 0 4px 8px rgba(139, 69, 19, 0.3);
        }

        .empty-icon i {
            width: 40px;
            height: 40px;
        }

        .btn-empty-action {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            box-shadow: 0 4px 8px rgba(139, 69, 19, 0.3);
            transition: all 0.3s ease;
        }

        .btn-empty-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(139, 69, 19, 0.4);
        }

        /* Filtros mejorados */
        .reports-filters {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
            position: relative;
            overflow: hidden;
        }

        .reports-filters::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
        }

        .reports-filters h3 {
            margin: 0 0 1.5rem 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .reports-filters h3::before {
            content: '';
            width: 24px;
            height: 24px;
            background: var(--primary-gradient);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 600;
            font-size: 0.875rem;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .filter-group label::before {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--primary-gradient);
            border-radius: 50%;
            flex-shrink: 0;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.875rem;
            background: white;
            transition: all 0.3s ease;
            box-shadow: var(--soft-shadow);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #8B4513;
            box-shadow: 0 0 0 4px rgba(139, 69, 19, 0.1);
            transform: translateY(-1px);
            outline: none;
        }

        /* Botones de exportación mejorados */
        .export-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
        }

        .export-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .export-btn {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e5e7eb;
            color: #374151;
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: var(--soft-shadow);
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
            border-color: #8B4513;
            color: #8B4513;
        }

        /* Modal PDF estilos mantenidos */
        .pdf-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .pdf-modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 900px;
            height: 80%;
            max-height: 700px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .pdf-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .pdf-modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1f2937;
        }

        .pdf-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            padding: 5px;
            border-radius: 4px;
        }

        .pdf-modal-close:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .pdf-modal-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        .pdf-preview-container {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }

        .pdf-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6b7280;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f4f6;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #pdfFrame {
            width: 100%;
            height: 100%;
            border: none;
        }

        .pdf-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }

        .pdf-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Animaciones suaves */
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

        .enhanced-activity-table tbody tr {
            animation: fadeInUp 0.4s ease-out;
        }

        /* Responsividad mejorada */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-icon {
                width: 50px;
                height: 50px;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 0.75rem;
            }
            
            .enhanced-activity-table th:nth-child(3),
            .enhanced-activity-table td:nth-child(3) {
                display: none; /* Ocultar empresa en móvil */
            }
            
            .table-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .enhanced-pagination {
                flex-direction: column;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .enhanced-activity-table th:nth-child(5),
            .enhanced-activity-table td:nth-child(5) {
                display: none; /* Ocultar descripción en móvil pequeño */
            }
            
            .filters-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>