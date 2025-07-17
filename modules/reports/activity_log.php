<?php
// modules/reports/activity_log.php
// Log de actividades del sistema - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtrado - convertir formato de fecha dd/mm/yyyy a yyyy-mm-dd
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$userId = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';
$page = intval($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Función para obtener actividades con filtros - VERSIÓN CORREGIDA
function getActivities($currentUser, $dateFrom, $dateTo, $userId, $action, $limit, $offset)
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
        $params['company_id'] = $currentUser['company_id'];
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Query principal - SIN LIMIT en los parámetros
    $query = "SELECT al.*, u.first_name, u.last_name, u.username, c.name as company_name
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              LEFT JOIN companies c ON u.company_id = c.id
              WHERE $whereClause
              ORDER BY al.created_at DESC
              LIMIT $limit OFFSET $offset";

    // NO incluir limit y offset en $params
    try {
        $result = fetchAll($query, $params);
        return $result ?: []; // Devolver array vacío si es false
    } catch (Exception $e) {
        error_log("Error en getActivities: " . $e->getMessage());
        return []; // Devolver array vacío en caso de error
    }
}
// Función para obtener el total de registros
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

// Función para obtener usuarios para filtro
function getUsers($currentUser)
{
    if ($currentUser['role'] === 'admin') {
        $query = "SELECT id, username, first_name, last_name FROM users WHERE status = 'active' ORDER BY first_name, last_name";
        return fetchAll($query);
    } else {
        $query = "SELECT id, username, first_name, last_name FROM users WHERE company_id = :company_id AND status = 'active' ORDER BY first_name, last_name";
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

    return $translations[$action] ?? ucfirst(str_replace('_', ' ', $action));
}

$activities = getActivities($currentUser, $dateFrom, $dateTo, $userId, $action, $limit, $offset);
$totalActivities = getTotalActivities($currentUser, $dateFrom, $dateTo, $userId, $action);
$users = getUsers($currentUser);
$actionTypes = getActionTypes();

$totalPages = ceil($totalActivities / $limit);

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
    <link rel="stylesheet" href="../../assets/css/summary.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">
    <link rel="stylesheet" href="../../assets/css/vista_filtro.css">
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
                <h1>Actividades</h1>
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

            <!-- Resumen de resultados -->
            <div class="results-summary">
                <div class="summary-stats">
                    <div class="stat-item">
                        <i data-feather="activity"></i>
                        <span class="stat-number"><?php echo number_format($totalActivities); ?></span>
                        <span class="stat-label">Total Actividades</span>
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
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" required>
                        </div>
                        <div class="filter-group">
                            <label for="date_to">Hasta</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" required>
                        </div>
                        <div class="filter-group">
                            <label for="user_id">Usuario</label>
                            <select id="user_id" name="user_id">
                                <option value="">Todos los usuarios</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"
                                        <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="action">Acción</label>
                            <select id="action" name="action">
                                <option value="">Todas las acciones</option>
                                <?php foreach ($actionTypes as $actionType): ?>
                                    <option value="<?php echo $actionType['action']; ?>"
                                        <?php echo $action == $actionType['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(translateAction($actionType['action'])); ?>
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

                <!-- Vista Previa de Resultados (solo se muestra cuando hay filtros aplicados) -->
                <?php if (!empty($_GET['date_from']) || !empty($_GET['user_id']) || !empty($_GET['action'])): ?>
                    <div class="filter-preview">
                        <div class="preview-header">
                            <h4>
                                <i data-feather="list"></i>
                                Vista Previa de Resultados
                            </h4>
                            <span class="preview-count">
                                <?php echo number_format($totalActivities); ?> actividades encontradas
                            </span>
                        </div>

                        <div class="preview-content">
                            <?php if (!empty($activities)): ?>
                                <div class="preview-table-container">
                                    <table class="preview-table">
                                        <thead>
                                            <tr>
                                                <th>Fecha/Hora</th>
                                                <th>Usuario</th>
                                                <th>Empresa</th>
                                                <th>Descripción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Mostrar máximo 10 registros en la vista previa
                                            $previewActivities = array_slice($activities, 0, 10);
                                            foreach ($previewActivities as $activity):
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="datetime-preview">
                                                            <strong><?php echo date('d/m/Y', strtotime($activity['created_at'])); ?></strong>
                                                            <small><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="user-preview">
                                                            <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                                            <small>@<?php echo htmlspecialchars($activity['username']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="company-preview"><?php echo htmlspecialchars($activity['company_name'] ?? 'N/A'); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="description-preview" title="<?php echo htmlspecialchars($activity['description'] ?? 'Sin descripción'); ?>">
                                                            <?php
                                                            $description = $activity['description'] ?? 'Sin descripción';
                                                            echo htmlspecialchars(strlen($description) > 80 ? substr($description, 0, 80) . '...' : $description);
                                                            ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($totalActivities > 10): ?>
                                    <div class="preview-footer">
                                        <p>
                                            Mostrando 10 de <?php echo number_format($totalActivities); ?> registros.
                                            <strong>Use la exportación para obtener todos los resultados.</strong>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="preview-empty">
                                    <i data-feather="search"></i>
                                    <h4>No se encontraron resultados</h4>
                                    <p>No hay actividades que coincidan con los filtros aplicados.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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
                <h3 class="pdf-modal-title">Vista Previa del PDF - Actividades</h3>
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
                mostrarNotificacion('Preparando descarga de ' + formato.toUpperCase() + '...', 'info');
                window.open(exportUrl, '_blank');
            }
        }

        function abrirModalPDF(url) {
            const modal = document.getElementById('pdfModal');
            const iframe = document.getElementById('pdfIframe');
            const loading = document.getElementById('pdfLoading');

            // Mostrar modal
            modal.style.display = 'block';

            // Mostrar loading y ocultar iframe
            loading.style.display = 'flex';
            iframe.style.display = 'none';

            // Cargar PDF en iframe
            iframe.onload = function() {
                loading.style.display = 'none';
                iframe.style.display = 'block';
            };

            iframe.onerror = function() {
                loading.innerHTML = '<p style="color: #dc3545;">Error al cargar la vista previa del PDF</p>';
            };

            iframe.src = url;

            // Cerrar modal con Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cerrarModalPDF();
                }
            });

            // Cerrar modal al hacer clic fuera
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    cerrarModalPDF();
                }
            });
        }

        function cerrarModalPDF() {
            const modal = document.getElementById('pdfModal');
            const iframe = document.getElementById('pdfIframe');

            modal.style.display = 'none';
            iframe.src = 'about:blank'; // Limpiar iframe
        }

        function mostrarNotificacion(mensaje, tipo) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${tipo === 'info' ? '#17a2b8' : '#28a745'};
                color: white;
                padding: 12px 20px;
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

        // Responsive
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.remove('active');
                }
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
        'share' => 'warning',
        'access_denied' => 'error',
        'view_activity_log' => 'info',
        'export_csv' => 'info',
        'export_pdf' => 'info',
        'export_excel' => 'info'
    ];

    return $classes[$action] ?? 'info';
}

// Función auxiliar para obtener icono según acción
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

    return $icons[$action] ?? 'activity';
}
?>