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
                            foreach ($activity['by_action'] as $action) {
                                if ($action['action'] === 'download') {
                                    $totalDownloads = $action['count'];
                                    break;
                                }
                            }
                            echo number_format($totalDownloads);
                            ?>
                        </div>
                        <div class="stat-label">Total Descargas</div>
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

                        <?php if ($currentUser['role'] === 'admin' && !empty($filterOptions['companies'])): ?>
                            <div class="filter-group">
                                <label for="company_id">Empresa</label>
                                <select id="company_id" name="company_id">
                                    <option value="">Todas las empresas</option>
                                    <?php foreach ($filterOptions['companies'] as $company): ?>
                                        <option value="<?php echo $company['id']; ?>"
                                            <?php echo $companyId == $company['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="filter-group">
                            <label for="document_type">Tipo de Documento</label>
                            <select id="document_type" name="document_type">
                                <option value="">Todos los tipos</option>
                                <?php foreach ($filterOptions['document_types'] as $type): ?>
                                    <option value="<?php echo $type['name']; ?>"
                                        <?php echo $documentType == $type['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
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
                        <a href="documents_report.php" class="btn-filter secondary">
                            <i data-feather="x"></i>
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <?php if ($reportType === 'summary'): ?>
                <!-- Vista de resumen con gráficos -->
                <div class="charts-section">
                    <div class="chart-row">
                        <div class="chart-container">
                            <h3>Documentos por Tipo</h3>
                            <canvas id="documentsByTypeChart"></canvas>
                        </div>

                        <div class="chart-container">
                            <h3>Documentos por Día</h3>
                            <canvas id="documentsByDateChart"></canvas>
                        </div>
                    </div>

                    <?php if ($currentUser['role'] === 'admin' && !empty($stats['by_company'])): ?>
                        <div class="chart-row">
                            <div class="chart-container">
                                <h3>Documentos por Empresa</h3>
                                <canvas id="documentsByCompanyChart"></canvas>
                            </div>

                            <div class="top-uploaders-section">
                                <h3>Usuarios que Más Suben</h3>
                                <div class="top-users-list">
                                    <?php foreach ($stats['top_uploaders'] as $index => $uploader): ?>
                                        <div class="top-user-item">
                                            <div class="user-rank"><?php echo $index + 1; ?></div>
                                            <div class="user-info">
                                                <div class="user-name">
                                                    <?php echo htmlspecialchars($uploader['first_name'] . ' ' . $uploader['last_name']); ?>
                                                </div>
                                                <div class="user-username">@<?php echo htmlspecialchars($uploader['username']); ?></div>
                                            </div>
                                            <div class="user-count">
                                                <span class="count-number"><?php echo number_format($uploader['documents_count']); ?></span>
                                                <span class="count-label">documentos</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Documentos más populares -->
                    <div class="popular-documents-section">
                        <div class="popular-section">
                            <h3>Documentos Más Descargados</h3>
                            <div class="popular-list">
                                <?php if (empty($activity['most_downloaded'])): ?>
                                    <div class="empty-state">
                                        <i data-feather="download"></i>
                                        <p>No hay descargas registradas</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($activity['most_downloaded'] as $index => $doc): ?>
                                        <div class="popular-item">
                                            <div class="item-rank"><?php echo $index + 1; ?></div>
                                            <div class="item-info">
                                                <div class="item-name"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                                                <div class="item-count"><?php echo $doc['download_count']; ?> descargas</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="popular-section">
                            <h3>Documentos Más Vistos</h3>
                            <div class="popular-list">
                                <?php if (empty($activity['most_viewed'])): ?>
                                    <div class="empty-state">
                                        <i data-feather="eye"></i>
                                        <p>No hay visualizaciones registradas</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($activity['most_viewed'] as $index => $doc): ?>
                                        <div class="popular-item">
                                            <div class="item-rank"><?php echo $index + 1; ?></div>
                                            <div class="item-info">
                                                <div class="item-name"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                                                <div class="item-count"><?php echo $doc['view_count']; ?> visualizaciones</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="8" class="empty-state">
                                            <i data-feather="file-text"></i>
                                            <p>No se encontraron documentos con los filtros seleccionados</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td>
                                                <div class="document-cell">
                                                    <div class="document-icon">
                                                        <i data-feather="file-text"></i>
                                                    </div>
                                                    <div class="document-info">
                                                        <strong><?php echo htmlspecialchars($doc['name']); ?></strong>
                                                        <?php if ($doc['description']): ?>
                                                            <br><small><?php echo htmlspecialchars(substr($doc['description'], 0, 50)); ?>...</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="type-badge">
                                                    <?php echo htmlspecialchars($doc['document_type'] ?? 'Sin tipo'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($doc['company_name']); ?></td>
                                            <td>
                                                <div class="user-cell-small">
                                                    <strong><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></strong>
                                                    <br><small>@<?php echo htmlspecialchars($doc['username']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo formatBytes($doc['file_size']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?></td>
                                            <td>
                                                <span class="stat-badge"><?php echo number_format($doc['download_count']); ?></span>
                                            </td>
                                            <td>
                                                <span class="stat-badge"><?php echo number_format($doc['view_count']); ?></span>
                                            </td>
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

    <script>
        // Variables de configuración
        var currentFilters = <?php echo json_encode($_GET); ?>;
        var documentsByType = <?php echo json_encode($stats['by_type']); ?>;
        var documentsByDate = <?php echo json_encode($stats['by_date']); ?>;
        var documentsByCompany = <?php echo json_encode($stats['by_company'] ?? []); ?>;
        var reportType = '<?php echo $reportType; ?>';

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);

            if (reportType === 'summary') {
                initCharts();
            }
        });

        function initCharts() {
            initDocumentsByTypeChart();
            initDocumentsByDateChart();

            if (documentsByCompany.length > 0) {
                initDocumentsByCompanyChart();
            }
        }

        function initDocumentsByTypeChart() {
            const ctx = document.getElementById('documentsByTypeChart').getContext('2d');

            const labels = documentsByType.map(item => item.type_name || 'Sin tipo');
            const data = documentsByType.map(item => parseInt(item.count));

            const colors = ['#8B4513', '#A0522D', '#CD853F', '#D2B48C', '#DEB887', '#F4A460'];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors.slice(0, data.length),
                        borderWidth: 0
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
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }

        function initDocumentsByDateChart() {
            const ctx = document.getElementById('documentsByDateChart').getContext('2d');

            const labels = documentsByDate.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('es-ES', {
                    month: 'short',
                    day: 'numeric'
                });
            });
            const data = documentsByDate.map(item => parseInt(item.count));

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Documentos',
                        data: data,
                        borderColor: '#8B4513',
                        backgroundColor: 'rgba(139, 69, 19, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
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

        function initDocumentsByCompanyChart() {
            const ctx = document.getElementById('documentsByCompanyChart').getContext('2d');

            const labels = documentsByCompany.map(item => item.company_name);
            const data = documentsByCompany.map(item => parseInt(item.count));

            const colors = ['#8B4513', '#A0522D', '#CD853F', '#D2B48C', '#DEB887', '#F4A460', '#DAA520', '#B8860B'];

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Documentos',
                        data: data,
                        backgroundColor: colors.slice(0, data.length),
                        borderWidth: 0
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