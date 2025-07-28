<?php
// modules/reports/documents_report.php
// Reportes de documentos del sistema con exportación PDF - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtrado
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$companyId = $_GET['company_id'] ?? '';
$documentType = $_GET['document_type'] ?? '';
$reportType = $_GET['report_type'] ?? 'summary';

// Función para obtener estadísticas de documentos
function getDocumentStats($currentUser, $dateFrom, $dateTo, $companyId, $documentType)
{
    $whereConditions = [];
    $params = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];

    $whereConditions[] = "d.created_at >= :date_from";
    $whereConditions[] = "d.created_at <= :date_to";
    $whereConditions[] = "d.status = 'active'";

    // Filtro por empresa
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "d.company_id = :user_company_id";
        $params['user_company_id'] = $currentUser['company_id'];
    } elseif (!empty($companyId)) {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    // Filtro por tipo de documento
    if (!empty($documentType)) {
        $whereConditions[] = "dt.name = :document_type";
        $params['document_type'] = $documentType;
    }

    $whereClause = implode(' AND ', $whereConditions);

    $stats = [];

    // Total de documentos
    $query = "SELECT COUNT(*) as total,
                     SUM(d.file_size) as total_size,
                     AVG(d.file_size) as avg_size
              FROM documents d
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause";

    $result = fetchOne($query, $params);
    $stats['total'] = $result['total'] ?? 0;
    $stats['total_size'] = $result['total_size'] ?? 0;
    $stats['avg_size'] = $result['avg_size'] ?? 0;

    // Documentos por tipo
    $query = "SELECT dt.name as type_name, COUNT(*) as count, SUM(d.file_size) as total_size
              FROM documents d
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause
              GROUP BY dt.name
              ORDER BY count DESC";

    $stats['by_type'] = fetchAll($query, $params);

    // Documentos por empresa
    if ($currentUser['role'] === 'admin') {
        $query = "SELECT c.name as company_name, COUNT(*) as count, SUM(d.file_size) as total_size
                  FROM documents d
                  LEFT JOIN companies c ON d.company_id = c.id
                  LEFT JOIN document_types dt ON d.document_type_id = dt.id
                  WHERE $whereClause
                  GROUP BY c.name
                  ORDER BY count DESC";

        $stats['by_company'] = fetchAll($query, $params);
    }

    // Documentos por día
    $query = "SELECT DATE(d.created_at) as date, COUNT(*) as count
              FROM documents d
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause
              GROUP BY DATE(d.created_at)
              ORDER BY date";

    $stats['by_date'] = fetchAll($query, $params);

    // Top usuarios que más suben documentos
    $query = "SELECT u.first_name, u.last_name, u.username, COUNT(*) as documents_count
              FROM documents d
              LEFT JOIN users u ON d.user_id = u.id
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause
              GROUP BY u.id
              ORDER BY documents_count DESC
              LIMIT 10";

    $stats['top_uploaders'] = fetchAll($query, $params);

    return $stats;
}

// Función para obtener actividad de documentos
function getDocumentActivity($currentUser, $dateFrom, $dateTo, $companyId, $documentType)
{
    $whereConditions = [];
    $params = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];

    $whereConditions[] = "al.created_at >= :date_from";
    $whereConditions[] = "al.created_at <= :date_to";
    $whereConditions[] = "al.table_name = 'documents'";
    $whereConditions[] = "al.action IN ('upload', 'download', 'view', 'delete')";

    // Filtro por empresa
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "d.company_id = :user_company_id";
        $params['user_company_id'] = $currentUser['company_id'];
    } elseif (!empty($companyId)) {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    // Filtro por tipo de documento
    if (!empty($documentType)) {
        $whereConditions[] = "dt.name = :document_type";
        $params['document_type'] = $documentType;
    }

    $whereClause = implode(' AND ', $whereConditions);

    $activity = [];

    // Actividad por tipo de acción
    $query = "SELECT al.action, COUNT(*) as count
              FROM activity_logs al
              LEFT JOIN documents d ON al.record_id = d.id
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause
              GROUP BY al.action
              ORDER BY count DESC";

    $activity['by_action'] = fetchAll($query, $params);

    // Documentos más descargados
    $query = "SELECT d.name as document_name, COUNT(*) as download_count
              FROM activity_logs al
              LEFT JOIN documents d ON al.record_id = d.id
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause AND al.action = 'download'
              GROUP BY d.id
              ORDER BY download_count DESC
              LIMIT 10";

    $activity['most_downloaded'] = fetchAll($query, $params);

    // Documentos más vistos
    $query = "SELECT d.name as document_name, COUNT(*) as view_count
              FROM activity_logs al
              LEFT JOIN documents d ON al.record_id = d.id
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause AND al.action = 'view'
              GROUP BY d.id
              ORDER BY view_count DESC
              LIMIT 10";

    $activity['most_viewed'] = fetchAll($query, $params);

    return $activity;
}

// Función para obtener lista detallada de documentos
function getDocumentsList($currentUser, $dateFrom, $dateTo, $companyId, $documentType, $limit = 100)
{
    $whereConditions = [];
    $params = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];

    $whereConditions[] = "d.created_at >= :date_from";
    $whereConditions[] = "d.created_at <= :date_to";
    $whereConditions[] = "d.status = 'active'";

    // Filtro por empresa
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "d.company_id = :user_company_id";
        $params['user_company_id'] = $currentUser['company_id'];
    } elseif (!empty($companyId)) {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    // Filtro por tipo de documento
    if (!empty($documentType)) {
        $whereConditions[] = "dt.name = :document_type";
        $params['document_type'] = $documentType;
    }

    $whereClause = implode(' AND ', $whereConditions);

    $query = "SELECT d.*, dt.name as document_type, c.name as company_name,
                     u.first_name, u.last_name, u.username,
                     (SELECT COUNT(*) FROM activity_logs al WHERE al.record_id = d.id AND al.action = 'download') as download_count,
                     (SELECT COUNT(*) FROM activity_logs al WHERE al.record_id = d.id AND al.action = 'view') as view_count
              FROM documents d
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              LEFT JOIN companies c ON d.company_id = c.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE $whereClause
              ORDER BY d.created_at DESC
              LIMIT :limit";

    $params['limit'] = $limit;

    return fetchAll($query, $params);
}

// Función para obtener empresas y tipos de documento para filtros
function getFilterOptions($currentUser)
{
    $options = [];

    // Empresas
    if ($currentUser['role'] === 'admin') {
        $query = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
        $options['companies'] = fetchAll($query);
    }

    // Tipos de documento
    $query = "SELECT name FROM document_types WHERE status = 'active' ORDER BY name";
    $options['document_types'] = fetchAll($query);

    return $options;
}

// Obtener datos
$stats = getDocumentStats($currentUser, $dateFrom, $dateTo, $companyId, $documentType);
$activity = getDocumentActivity($currentUser, $dateFrom, $dateTo, $companyId, $documentType);
$documents = getDocumentsList($currentUser, $dateFrom, $dateTo, $companyId, $documentType);
$filterOptions = getFilterOptions($currentUser);

// Registrar acceso
logActivity($currentUser['id'], 'view_documents_report', 'reports', null, 'Usuario accedió al reporte de documentos');

// Función para formatear bytes
function formatBytes($size, $precision = 2)
{
    if ($size == 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Documentos - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reports.css">
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
                <h1>Reportes de Documentos</h1>
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

            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="file-text"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                        <div class="stat-label">Total Documentos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="hard-drive"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo formatBytes($stats['total_size']); ?></div>
                        <div class="stat-label">Espacio Total</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="download"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number">
                            <?php
                            $totalDownloads = 0;
                            if (isset($activity['by_action']) && is_array($activity['by_action'])) {
                                foreach ($activity['by_action'] as $action) {
                                    if ($action['action'] === 'download') {
                                        $totalDownloads = $action['count'];
                                        break;
                                    }
                                }
                            }
                            echo number_format($totalDownloads);
                            ?>
                        </div>
                        <div class="stat-label">Total Descargas</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number">
                            <?php
                            $uniqueUsers = 0;
                            if (isset($stats['top_uploaders']) && is_array($stats['top_uploaders'])) {
                                $uniqueUsers = count($stats['top_uploaders']);
                            }
                            echo number_format($uniqueUsers);
                            ?>
                        </div>
                        <div class="stat-label">Usuarios Únicos</div>
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
                            <label for="company_id">Empresa</label>
                            <select id="company_id" name="company_id">
                                <option value="">Todas las empresas</option>
                                <?php if (isset($filterOptions['companies']) && is_array($filterOptions['companies'])): ?>
                                    <?php foreach ($filterOptions['companies'] as $company): ?>
                                        <option value="<?php echo $company['id']; ?>" <?php echo $companyId == $company['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="filter-group">
                            <label for="document_type">Tipo de Documento</label>
                            <select id="document_type" name="document_type">
                                <option value="">Todos los tipos</option>
                                <?php if (isset($filterOptions['document_types']) && is_array($filterOptions['document_types'])): ?>
                                    <?php foreach ($filterOptions['document_types'] as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type['name']); ?>" <?php echo $documentType == $type['name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
                        <a href="documents_report.php" class="btn-filter secondary">
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
                <!-- Vista resumen con gráficos -->
                <div class="reports-charts">
                    <div class="chart-grid">
                        <!-- Documentos por tipo -->
                        <div class="chart-card">
                            <h3>Documentos por Tipo</h3>
                            <div class="chart-container">
                                <canvas id="typeChart"></canvas>
                            </div>
                            <div class="chart-data">
                                <?php if (isset($stats['by_type']) && is_array($stats['by_type']) && !empty($stats['by_type'])): ?>
                                    <?php foreach ($stats['by_type'] as $type): ?>
                                        <div class="data-item">
                                            <div class="item-name"><?php echo htmlspecialchars($type['type_name'] ?? 'Sin tipo'); ?></div>
                                            <div class="item-count"><?php echo number_format($type['count']); ?> documentos</div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">Sin datos para mostrar</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Documentos por empresa (solo para admin) -->
                        <?php if ($currentUser['role'] === 'admin'): ?>
                        <div class="chart-card">
                            <h3>Documentos por Empresa</h3>
                            <div class="chart-container">
                                <canvas id="companyChart"></canvas>
                            </div>
                            <div class="chart-data">
                                <?php if (isset($stats['by_company']) && is_array($stats['by_company']) && !empty($stats['by_company'])): ?>
                                    <?php foreach ($stats['by_company'] as $company): ?>
                                        <div class="data-item">
                                            <div class="item-name"><?php echo htmlspecialchars($company['company_name'] ?? 'Sin empresa'); ?></div>
                                            <div class="item-count"><?php echo number_format($company['count']); ?> documentos</div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">Sin datos para mostrar</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Actividad por día -->
                        <div class="chart-card full-width">
                            <h3>Documentos Subidos por Día</h3>
                            <div class="chart-container">
                                <canvas id="dateChart"></canvas>
                            </div>
                        </div>

                        <!-- Top usuarios -->
                        <div class="chart-card">
                            <h3>Top Usuarios que Suben Documentos</h3>
                            <div class="chart-data">
                                <?php if (isset($stats['top_uploaders']) && is_array($stats['top_uploaders']) && !empty($stats['top_uploaders'])): ?>
                                    <?php foreach ($stats['top_uploaders'] as $uploader): ?>
                                        <div class="data-item">
                                            <div class="item-name"><?php echo htmlspecialchars(($uploader['first_name'] ?? '') . ' ' . ($uploader['last_name'] ?? '')); ?></div>
                                            <div class="item-count"><?php echo number_format($uploader['documents_count']); ?> documentos</div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">Sin datos para mostrar</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Documentos más descargados -->
                        <div class="chart-card">
                            <h3>Documentos Más Descargados</h3>
                            <div class="chart-data">
                                <?php if (isset($activity['most_downloaded']) && is_array($activity['most_downloaded']) && !empty($activity['most_downloaded'])): ?>
                                    <?php foreach ($activity['most_downloaded'] as $doc): ?>
                                        <div class="data-item">
                                            <div class="item-name"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                                            <div class="item-count"><?php echo $doc['download_count']; ?> descargas</div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">Sin datos para mostrar</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Documentos más vistos -->
                        <div class="chart-card">
                            <h3>Documentos Más Vistos</h3>
                            <div class="chart-data">
                                <?php if (isset($activity['most_viewed']) && is_array($activity['most_viewed']) && !empty($activity['most_viewed'])): ?>
                                    <?php foreach ($activity['most_viewed'] as $doc): ?>
                                        <div class="data-item">
                                            <div class="item-name"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                                            <div class="item-count"><?php echo $doc['view_count']; ?> visualizaciones</div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">Sin datos para mostrar</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Vista detallada con tabla de documentos -->
                <div class="reports-table">
                    <h3>Lista Detallada de Documentos (<?php echo count($documents); ?> registros)</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Documento</th>
                                    <th>Tipo</th>
                                    <th>Empresa</th>
                                    <th>Usuario</th>
                                    <th>Tamaño</th>
                                    <th>Fecha Subida</th>
                                    <th>Descargas</th>
                                    <th>Vistas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($documents)): ?>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td>
                                                <div class="document-info">
                                                    <i data-feather="file-text"></i>
                                                    <span><?php echo htmlspecialchars($doc['name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($doc['document_type'] ?? 'Sin tipo'); ?></td>
                                            <td><?php echo htmlspecialchars($doc['company_name'] ?? 'Sin empresa'); ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <span class="user-name"><?php echo htmlspecialchars(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? '')); ?></span>
                                                    <small class="username">@<?php echo htmlspecialchars($doc['username'] ?? 'usuario'); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo formatBytes($doc['file_size'] ?? 0); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?></td>
                                            <td>
                                                <span class="badge badge-info"><?php echo number_format($doc['download_count'] ?? 0); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-success"><?php echo number_format($doc['view_count'] ?? 0); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="empty-state">
                                            <div class="empty-content">
                                                <i data-feather="inbox"></i>
                                                <h4>No se encontraron documentos</h4>
                                                <p>No hay documentos que coincidan con los filtros seleccionados.</p>
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
                <h3 class="pdf-modal-title">Vista Previa del PDF - Reportes de Documentos</h3>
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

    <!-- Scripts -->
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // Variables de configuración
        var currentFilters = <?php echo json_encode($_GET); ?>;

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);
            
            <?php if ($reportType === 'summary'): ?>
            initCharts();
            <?php endif; ?>
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
            const exportUrl = 'export.php?format=' + formato + '&type=documents_report&modal=1&' + urlParams.toString();

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

        <?php if ($reportType === 'summary'): ?>
        function initCharts() {
            // Datos para gráficos
            const typeData = <?php echo json_encode($stats['by_type'] ?? []); ?>;
            const companyData = <?php echo json_encode($stats['by_company'] ?? []); ?>;
            const dateData = <?php echo json_encode($stats['by_date'] ?? []); ?>;

            // Gráfico de documentos por tipo
            if (typeData.length > 0) {
                const typeCtx = document.getElementById('typeChart');
                if (typeCtx) {
                    new Chart(typeCtx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: typeData.map(item => item.type_name || 'Sin tipo'),
                            datasets: [{
                                data: typeData.map(item => item.count),
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
            }

            // Gráfico de documentos por empresa (solo admin)
            <?php if ($currentUser['role'] === 'admin'): ?>
            if (companyData.length > 0) {
                const companyCtx = document.getElementById('companyChart');
                if (companyCtx) {
                    new Chart(companyCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: companyData.map(item => item.company_name || 'Sin empresa'),
                            datasets: [{
                                label: 'Documentos',
                                data: companyData.map(item => item.count),
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
            }
            <?php endif; ?>

            // Gráfico de documentos por día
            if (dateData.length > 0) {
                const dateCtx = document.getElementById('dateChart');
                if (dateCtx) {
                    new Chart(dateCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: dateData.map(item => {
                                const date = new Date(item.date);
                                return date.toLocaleDateString('es-ES');
                            }),
                            datasets: [{
                                label: 'Documentos subidos',
                                data: dateData.map(item => item.count),
                                borderColor: '#4e342e',
                                backgroundColor: 'rgba(78, 52, 46, 0.1)',
                                tension: 0.4,
                                fill: true
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
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
            }
        }
        <?php endif; ?>

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