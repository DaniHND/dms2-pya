<?php
// modules/reports/activity_log.php
// Log de actividades del sistema - DMS2
// VERSION CON SEGURIDAD Y DISEÑO DE DOCUMENTS_REPORT

require_once '../../config/session.php';
require_once '../../config/database.php';

// Asegurar que las funciones de base de datos estén disponibles
if (!function_exists('fetchOne')) {
    function fetchOne($query, $params = [])
    {
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
    function fetchAll($query, $params = [])
    {
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
    function logActivity($userId, $action, $tableName = null, $recordId = null, $description = null)
    {
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

// Función helper para obtener nombre completo si no existe
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
            if (
                !$checkResult ||
                $checkResult['role'] === 'admin' ||
                $checkResult['company_id'] != $currentUser['company_id']
            ) {
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
        return [];
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
            if (
                !$checkResult ||
                $checkResult['role'] === 'admin' ||
                $checkResult['company_id'] != $currentUser['company_id']
            ) {
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
        // === ACCIONES PRINCIPALES QUE QUIERES ===
        'login' => 'Inicio de Sesión',
        'logout' => 'Salida del Sistema',
        'upload' => 'Subió Archivo',
        'download' => 'Descargó Archivo',
        'delete' => 'Eliminó',
        'move' => 'Movió',
        'document_move' => 'Movió Documento',
        'folder_move' => 'Movió Carpeta',
        'export_csv' => 'Exportó Reporte CSV',
        'export_pdf' => 'Exportó Reporte PDF',
        'export_excel' => 'Exportó Reporte Excel',

        // === ACCIONES SECUNDARIAS ===
        'failed_login' => 'Intento de Login Fallido',
        'password_change' => 'Cambió Contraseña',
        'permanent_delete' => 'Eliminó Permanentemente',
        'user_create' => 'Creó Usuario',
        'user_delete' => 'Eliminó Usuario',
        'user_update' => 'Actualizó Usuario',
        'group_create' => 'Creó Grupo',
        'group_delete' => 'Eliminó Grupo',
        'permission_change' => 'Cambió Permisos',
        'company_create' => 'Creó Empresa',
        'company_delete' => 'Eliminó Empresa',
        'folder_create' => 'Creó Carpeta',
        'folder_delete' => 'Eliminó Carpeta',
        'document_share' => 'Compartió Documento',
        'document_unshare' => 'Dejó de Compartir',
        'backup_created' => 'Creó Respaldo',
        'backup_restored' => 'Restauró Respaldo',
        'system_config_change' => 'Cambió Configuración',

        // === ACCIONES ANTIGUAS (compatibilidad) ===
        'create' => 'Creó',
        'update' => 'Actualizó',
        'view' => 'Vio',
        'share' => 'Compartió',
        'access_denied' => 'Acceso Denegado',
        'view_activity_log' => 'Vio Log de Actividades'
    ];

    return $translations[$action] ?? ucfirst(str_replace('_', ' ', $action));
}

// Función para obtener iconos de acciones
function getActionIcon($action)
{
    $icons = [
        // === ICONOS PRINCIPALES ===
        'login' => 'log-in',
        'logout' => 'log-out',
        'upload' => 'upload',
        'download' => 'download',
        'delete' => 'trash-2',
        'move' => 'move',
        'document_move' => 'file-plus',
        'folder_move' => 'folder-plus',
        'export_csv' => 'file-text',
        'export_pdf' => 'file',
        'export_excel' => 'grid',

        // === ICONOS SECUNDARIOS ===
        'failed_login' => 'alert-triangle',
        'password_change' => 'key',
        'permanent_delete' => 'trash',
        'user_create' => 'user-plus',
        'user_delete' => 'user-minus',
        'user_update' => 'user-check',
        'group_create' => 'users',
        'group_delete' => 'user-x',
        'permission_change' => 'shield',
        'company_create' => 'building',
        'company_delete' => 'building',
        'folder_create' => 'folder-plus',
        'folder_delete' => 'folder-minus',
        'document_share' => 'share-2',
        'document_unshare' => 'share',
        'backup_created' => 'hard-drive',
        'backup_restored' => 'refresh-cw',
        'system_config_change' => 'settings',

        // === ICONOS ANTIGUOS (compatibilidad) ===
        'create' => 'plus',
        'update' => 'edit',
        'view' => 'eye',
        'share' => 'share-2',
        'access_denied' => 'shield-off',
        'view_activity_log' => 'activity'
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
if (function_exists('logActivity')) {
    logActivity($currentUser['id'], 'view_activity_log', 'reports', null, 'Usuario accedió al log de actividades');
}
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
            </div>

            <!-- Filtros de búsqueda -->
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

            <!-- Tabla de actividades -->
            <div class="reports-table enhanced-table">
                <div class="table-header">
                    <h3><i data-feather="activity"></i> Log de Actividades (<?php echo number_format($totalRecords); ?> registros)</h3>
                </div>

                <?php if (!empty($activities)): ?>
                    <div class="table-container">
                        <table class="data-table simple-activity-table">
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
                                        <td>
                                            <?php echo date('d/m/Y H:i:s', strtotime($activity['created_at'])); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')); ?>
                                            <br><small>@<?php echo htmlspecialchars($activity['username'] ?? 'usuario'); ?></small>
                                        </td>
                                        <?php if ($currentUser['role'] === 'admin'): ?>
                                            <td>
                                                <?php echo htmlspecialchars($activity['company_name'] ?? 'Sin empresa'); ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php echo translateAction($activity['action']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($activity['description'] ?? 'Sin descripción'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
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

        // Filtros automáticos
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

        // Exportación de datos
        function exportarDatos(formato) {
            const urlParams = new URLSearchParams(window.location.search);
            const exportUrl = 'export.php?format=' + formato + '&type=activity_log&modal=1&' + urlParams.toString();

            if (formato === 'pdf') {
                abrirModalPDF(exportUrl);
            } else {
                mostrarNotificacion('Preparando descarga...', 'info');
                window.open(exportUrl.replace('&modal=1', ''), '_blank');
            }
        }

        function abrirModalPDF(url) {
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
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            feather.replace();

            const iframe = document.getElementById('pdfFrame');
            iframe.onload = function() {
                document.querySelector('.pdf-loading').style.display = 'none';
                iframe.style.display = 'block';
            };

            iframe.onerror = function() {
                document.querySelector('.pdf-loading').innerHTML = '<div class="loading-spinner"></div><p style="color: #ef4444;">Error al cargar la vista previa. <button onclick="cerrarModalPDF()" style="margin-left: 10px; padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">Cerrar</button></p>';
            };

            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    cerrarModalPDF();
                }
            });
        }

        function imprimirPDF() {
            const iframe = document.getElementById('pdfFrame');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.print();
            } else {
                mostrarNotificacion('No se puede imprimir el documento', 'error');
            }
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

        function showComingSoon(feature) {
            alert(`${feature} - Próximamente`);
        }
    </script>

    <style>
        /* Estilos específicos para el log de actividades con diseño de documents_report */
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

        /* Navegación breadcrumb */
        .reports-nav-breadcrumb {
            margin-bottom: 2rem;
        }

        .breadcrumb-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            color: #374151;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: var(--soft-shadow);
        }

        .breadcrumb-link:hover {
            background: var(--primary-gradient);
            border-color: #8B4513;
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
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

        /* Filtros mejorados */
        .reports-filters {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
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
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            outline: none;
        }

        /* Sección de exportación */
        .export-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
        }

        .export-section h3 {
            margin: 0 0 1.5rem 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
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

        /* Tabla simple sin diseños decorativos para actividades */
        .simple-activity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .simple-activity-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1rem 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .simple-activity-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
            color: #1f2937;
            font-size: 0.875rem;
        }

        .simple-activity-table tbody tr:hover {
            background: #f8fafc;
        }

        .simple-activity-table small {
            color: #6b7280;
            font-size: 0.75rem;
        }

        /* Celdas específicas */
        .date-cell {
            min-width: 140px;
        }

        .date-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .date-info .date {
            font-weight: 500;
            color: #1f2937;
            font-size: 0.875rem;
        }

        .date-info .time {
            color: #6b7280;
            font-size: 0.75rem;
            font-family: monospace;
        }

        .recent-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: var(--info-gradient);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 500;
            margin-top: 0.25rem;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }

        .recent-badge i {
            width: 10px;
            height: 10px;
        }

        .recent-activity-row {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 5%, transparent 5%);
            border-left: 3px solid #0ea5e9;
        }

        .user-info {
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

        .action-view_activity_log {
            background: var(--secondary-gradient);
            color: white;
        }

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

        /* Modal PDF */
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
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
            flex-wrap: wrap;
        }

        .pdf-actions .export-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            min-width: 100px;
            justify-content: center;
            color: white;
        }

        /* Botón Imprimir - marrón/gris oscuro */
        .pdf-actions .export-btn:first-child {
            background: #6b7280;
        }

        .pdf-actions .export-btn:first-child:hover {
            background: #4b5563;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(107, 114, 128, 0.3);
        }

        /* Botón Descargar - verde */
        .pdf-actions .export-btn:last-child {
            background: #16a34a;
        }

        .pdf-actions .export-btn:last-child:hover {
            background: #15803d;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(22, 163, 74, 0.3);
        }

        .pdf-actions .export-btn i {
            width: 16px;
            height: 16px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
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

        .stat-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .stat-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .stat-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .stat-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        .activity-table tbody tr {
            animation: fadeInUp 0.4s ease-out;
        }

        /* Responsividad */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .filters-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .export-buttons {
                flex-direction: column;
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

            .simple-activity-table th:nth-child(3),
            .simple-activity-table td:nth-child(3) {
                display: none;
                /* Ocultar empresa en móvil */
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

            .simple-activity-table th:nth-child(5),
            .simple-activity-table td:nth-child(5) {
                display: none;
                /* Ocultar descripción en móvil pequeño */
            }

            .reports-content {
                padding: 1rem;
            }

            .pdf-actions {
                flex-direction: column;
                gap: 8px;
            }

            .pdf-actions .export-btn {
                width: 100%;
                min-width: auto;
            }
        }

        /* Efectos de hover mejorados */
        .breadcrumb-link,
        .export-btn,
        .btn-empty-action {
            position: relative;
            overflow: hidden;
        }

        .breadcrumb-link::before,
        .export-btn::before,
        .btn-empty-action::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .breadcrumb-link:hover::before,
        .export-btn:hover::before,
        .btn-empty-action:hover::before {
            left: 100%;
        }

        /* Mejoras en accesibilidad */
        .filter-group input:focus,
        .filter-group select:focus,
        .export-btn:focus,
        .btn-empty-action:focus {
            outline: 2px solid #8B4513;
            outline-offset: 2px;
        }

        /* Animaciones de entrada para elementos dinámicos */
        .stat-card,
        .reports-filters,
        .export-section,
        .enhanced-table {
            opacity: 0;
            animation: slideInUp 0.6s ease-out forwards;
        }

        .stat-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .stat-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .stat-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .stat-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        .reports-filters {
            animation-delay: 0.5s;
        }

        .export-section {
            animation-delay: 0.6s;
        }

        .enhanced-table {
            animation-delay: 0.7s;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        /* Indicadores adicionales para actividades recientes */
        .recent-activity-row::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--info-gradient);
            border-radius: 0 4px 4px 0;
        }

        /* Mejoras en la tabla de actividades */
        .activity-table tbody tr {
            transition: all 0.2s ease;
            position: relative;
        }

        .activity-table tbody tr:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            transform: translateX(2px);
        }

        /* Efectos de hover en las tarjetas de estadísticas */
        .stat-card:hover .stat-icon {
            transform: scale(1.05);
        }

        .stat-card:hover .stat-number {
            transform: scale(1.02);
        }

        /* Indicadores de carga mejorados */
        .loading-spinner {
            position: relative;
        }

        .loading-spinner::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            background: #3b82f6;
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(0);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: translate(-50%, -50%) scale(0);
                opacity: 1;
            }

            100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 0;
            }
        }

        /* Mejoras para badges de acciones */
        .action-badge {
            transition: all 0.2s ease;
        }

        .action-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Tooltips para información adicional */
        .user-info,
        .date-info,
        .action-badge {
            position: relative;
        }

        /* Mejoras en el estado de carga */
        .pdf-loading p {
            margin-top: 10px;
            font-size: 14px;
            color: #6b7280;
        }

        /* Estilos adicionales para navegación */
        .breadcrumb-link i {
            transition: transform 0.3s ease;
        }

        .breadcrumb-link:hover i {
            transform: translateX(-2px);
        }

        /* Mejoras en la información de paginación */
        .pagination-info i {
            color: #8B4513;
        }

        /* Estados de focus mejorados */
        .pagination-btn:focus {
            outline: 2px solid #8B4513;
            outline-offset: 2px;
        }

        /* Animaciones para elementos interactivos */
        .action-badge,
        .recent-badge {
            animation: fadeIn 0.5s ease-out;
        }

        /* Mejoras específicas para el diseño responsive */
        @media (max-width: 640px) {
            .table-header {
                padding: 1rem;
            }

            .table-header h3 {
                font-size: 1.125rem;
            }

            .simple-activity-table th,
            .simple-activity-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.825rem;
            }

            .stat-icon {
                width: 50px;
                height: 50px;
            }

            .stat-icon i {
                width: 24px;
                height: 24px;
            }

            .stat-number {
                font-size: 1.75rem;
            }

            .recent-badge {
                font-size: 0.5rem;
                padding: 0.125rem 0.375rem;
            }
        }

        /* Efectos adicionales para mejorar la experiencia visual */
        .reports-content>* {
            opacity: 0;
            animation: fadeInSequence 0.6s ease-out forwards;
        }

        .reports-nav-breadcrumb {
            animation-delay: 0.1s;
        }

        .stats-grid {
            animation-delay: 0.2s;
        }

        .reports-filters {
            animation-delay: 0.3s;
        }

        .export-section {
            animation-delay: 0.4s;
        }

        .enhanced-table {
            animation-delay: 0.5s;
        }

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
        .reports-filters,
        .export-section,
        .enhanced-table {
            border: 1px solid rgba(139, 69, 19, 0.1);
        }

        .reports-filters h3,
        .export-section h3,
        .table-header h3 {
            color: #8B4513;
        }

        /* Asegurar que los colores sean consistentes con documents_report */
        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        /* Estado final del diseño */
        body.dashboard-layout {
            background: #f8fafc;
        }

        .reports-content {
            background: transparent;
            padding: 2rem;
        }
    </style>
</body>

</html>