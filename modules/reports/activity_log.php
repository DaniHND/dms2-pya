<?php
require_once '../../bootstrap.php';
// require_once '../../includes/init.php'; // Reemplazado por bootstrap
// modules/reports/activity_log.php
// Log de actividades del sistema - DMS2
// VERSION CON SEGURIDAD - Usuarios no ven datos de administradores

// require_once '../../config/session.php'; // Cargado por bootstrap
// require_once '../../config/database.php'; // Cargado por bootstrap

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

            <!-- Estadísticas rápidas -->
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
                        <div class="stat-number"><?php echo count($users); ?></div>
                        <div class="stat-label"><?php echo $currentUser['role'] === 'admin' ? 'Total Usuarios' : 'Usuarios de mi Empresa'; ?></div>
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
                            <label for="user_id">Usuario</label>
                            <select id="user_id" name="user_id">
                                <option value=""><?php echo $currentUser['role'] === 'admin' ? 'Todos los usuarios' : 'Usuarios de mi empresa'; ?></option>
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
                                    <option value="<?php echo htmlspecialchars($actionType['action']); ?>" <?php echo $action == $actionType['action'] ? 'selected' : ''; ?>>
                                        <?php echo translateAction($actionType['action']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filters-actions">
                        <button type="submit" class="btn-filter">
                            <i data-feather="search"></i>
                            Filtrar
                        </button>
                        <a href="activity_log.php" class="btn-filter secondary">
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

            <!-- Tabla de actividades -->
            <div class="reports-table">
                <div class="table-header">
                    <h3>Log de Actividades (<?php echo number_format($totalRecords); ?> registros)</h3>
                    <div class="pagination-info">
                        Página <?php echo $page; ?> de <?php echo $totalPages; ?>
                        (<?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $recordsPerPage, $totalRecords)); ?>)
                    </div>
                </div>

                <?php if (!empty($activities)): ?>
                    <div class="table-container">
                        <table class="data-table activity-table">
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Usuario</th>
                                    <?php if ($currentUser['role'] === 'admin'): ?>
                                    <th>Empresa</th>
                                    <?php endif; ?>
                                    <th>Acción</th>
                                    <th>Descripción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $activity): ?>
                                    <tr>
                                        <td class="datetime-cell">
                                            <div class="datetime">
                                                <div class="date"><?php echo date('d/m/Y', strtotime($activity['created_at'])); ?></div>
                                                <div class="time"><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></div>
                                            </div>
                                        </td>
                                        <td class="user-cell">
                                            <div class="user-info">
                                                <span class="user-name">
                                                    <?php echo htmlspecialchars(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')); ?>
                                                </span>
                                                <small class="username">@<?php echo htmlspecialchars($activity['username'] ?? 'usuario'); ?></small>
                                            </div>
                                        </td>
                                        <?php if ($currentUser['role'] === 'admin'): ?>
                                        <td class="company-cell">
                                            <?php echo htmlspecialchars($activity['company_name'] ?? 'Sin empresa'); ?>
                                        </td>
                                        <?php endif; ?>
                                        <td class="action-cell">
                                            <span class="action-badge action-<?php echo $activity['action']; ?>">
                                                <?php echo translateAction($activity['action']); ?>
                                            </span>
                                        </td>
                                        <td class="description-cell">
                                            <?php echo htmlspecialchars($activity['description'] ?? 'Sin descripción'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <div class="pagination-info">
                                Mostrando <?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $recordsPerPage, $totalRecords)); ?> 
                                de <?php echo number_format($totalRecords); ?> registros
                            </div>
                            <div class="pagination-controls">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-btn">
                                        <i data-feather="chevrons-left"></i>
                                    </a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                                        <i data-feather="chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <span class="pagination-current">
                                    <?php echo $page; ?> / <?php echo $totalPages; ?>
                                </span>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                                        <i data-feather="chevron-right"></i>
                                    </a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="pagination-btn">
                                        <i data-feather="chevrons-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-content">
                            <i data-feather="activity"></i>
                            <h4>No se encontraron actividades</h4>
                            <p>No hay actividades que coincidan con los filtros seleccionados.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal para vista previa del PDF -->
    <div id="pdfModal" class="pdf-modal">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header">
                <h3 class="pdf-modal-title">Vista Previa del PDF - Log de Actividades</h3>
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

        console.log('📊 Log de Actividades cargado con seguridad');
        console.log('🔒 Modo:', '<?php echo $currentUser['role'] === 'admin' ? 'Admin - Ve todas las actividades' : 'Usuario - Solo su empresa (sin admins)'; ?>');
    </script>
</body>

</html>