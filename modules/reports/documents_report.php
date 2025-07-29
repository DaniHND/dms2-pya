<?php
// modules/reports/documents_report.php
// Reportes de documentos del sistema con tarjetas ordenadas - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

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

    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "d.company_id = :user_company_id";
        $params['user_company_id'] = $currentUser['company_id'];
    } elseif (!empty($companyId)) {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    if (!empty($documentType)) {
        $whereConditions[] = "dt.name = :document_type";
        $params['document_type'] = $documentType;
    }

    $whereClause = implode(' AND ', $whereConditions);
    $stats = [];

    // Total de documentos
    $query = "SELECT COUNT(*) as total, SUM(d.file_size) as total_size, AVG(d.file_size) as avg_size
              FROM documents d LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause";
    $result = fetchOne($query, $params);
    $stats['total'] = $result['total'] ?? 0;
    $stats['total_size'] = $result['total_size'] ?? 0;
    $stats['avg_size'] = $result['avg_size'] ?? 0;

    // Documentos por tipo
    $query = "SELECT dt.name as type_name, COUNT(*) as count, SUM(d.file_size) as total_size
              FROM documents d LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause GROUP BY dt.name ORDER BY count DESC, dt.name ASC";
    $stats['by_type'] = fetchAll($query, $params);

    // Documentos por empresa (solo admin)
    if ($currentUser['role'] === 'admin') {
        $query = "SELECT c.name as company_name, COUNT(*) as count, SUM(d.file_size) as total_size
                  FROM documents d LEFT JOIN companies c ON d.company_id = c.id
                  LEFT JOIN document_types dt ON d.document_type_id = dt.id
                  WHERE $whereClause GROUP BY c.name ORDER BY count DESC, c.name ASC";
        $stats['by_company'] = fetchAll($query, $params);
    }

    // Documentos por día
    $query = "SELECT DATE(d.created_at) as date, COUNT(*) as count
              FROM documents d LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause GROUP BY DATE(d.created_at) ORDER BY date ASC";
    $stats['by_date'] = fetchAll($query, $params);

    // Top usuarios
    $query = "SELECT u.first_name, u.last_name, u.username, u.email, COUNT(*) as documents_count, SUM(d.file_size) as total_size
              FROM documents d LEFT JOIN users u ON d.user_id = u.id
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause GROUP BY u.id, u.first_name, u.last_name, u.username, u.email
              ORDER BY documents_count DESC, u.first_name ASC, u.last_name ASC LIMIT 10";
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

    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "d.company_id = :user_company_id";
        $params['user_company_id'] = $currentUser['company_id'];
    } elseif (!empty($companyId)) {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    if (!empty($documentType)) {
        $whereConditions[] = "dt.name = :document_type";
        $params['document_type'] = $documentType;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Documentos más vistos
    $query = "SELECT d.name as document_name, dt.name as document_type,
                     u.first_name, u.last_name, d.file_size, d.created_at, COUNT(*) as view_count
              FROM activity_logs al LEFT JOIN documents d ON al.record_id = d.id
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE $whereClause AND al.action = 'view'
              GROUP BY d.id, d.name, dt.name, u.first_name, u.last_name, d.file_size, d.created_at
              ORDER BY view_count DESC, d.name ASC LIMIT 10";

    return ['most_viewed' => fetchAll($query, $params)];
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

    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "d.company_id = :user_company_id";
        $params['user_company_id'] = $currentUser['company_id'];
    } elseif (!empty($companyId)) {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    if (!empty($documentType)) {
        $whereConditions[] = "dt.name = :document_type";
        $params['document_type'] = $documentType;
    }

    $whereClause = implode(' AND ', $whereConditions);

    $query = "SELECT d.*, dt.name as document_type, c.name as company_name,
                     u.first_name, u.last_name, u.username,
                     (SELECT COUNT(*) FROM activity_logs al WHERE al.record_id = d.id AND al.action = 'download') as download_count,
                     (SELECT COUNT(*) FROM activity_logs al WHERE al.record_id = d.id AND al.action = 'view') as view_count
              FROM documents d LEFT JOIN document_types dt ON d.document_type_id = dt.id
              LEFT JOIN companies c ON d.company_id = c.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE $whereClause ORDER BY d.created_at DESC, d.name ASC LIMIT :limit";

    $params['limit'] = $limit;
    return fetchAll($query, $params);
}

// Función para obtener filtros
function getFilterOptions($currentUser)
{
    $options = [];
    if ($currentUser['role'] === 'admin') {
        $query = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name ASC";
        $options['companies'] = fetchAll($query);
    }
    $query = "SELECT name FROM document_types WHERE status = 'active' ORDER BY name ASC";
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
function formatBytes($size, $precision = 2) {
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
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
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

        <div class="reports-content">
            <div class="reports-nav-breadcrumb">
                <a href="index.php" class="breadcrumb-link">
                    <i data-feather="arrow-left"></i>
                    Volver a Reportes
                </a>
            </div>

            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i data-feather="file-text"></i></div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                        <div class="stat-label">Total Documentos</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i data-feather="hard-drive"></i></div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo formatBytes($stats['total_size']); ?></div>
                        <div class="stat-label">Espacio Total</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i data-feather="download"></i></div>
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
                    <div class="stat-icon"><i data-feather="users"></i></div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo count($stats['top_uploaders']); ?></div>
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
                                <?php if (isset($filterOptions['companies'])): ?>
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
                                <?php if (isset($filterOptions['document_types'])): ?>
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
                <div class="reports-charts">
                    <div class="chart-grid">
                        <!-- Gráfico de tipos -->
                        <div class="chart-card">
                            <h3>Documentos por Tipo</h3>
                            <div class="chart-container">
                                <canvas id="typeChart"></canvas>
                            </div>
                            <div class="chart-data">
                                <?php if (!empty($stats['by_type'])): ?>
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

                        <!-- Documentos más vistos con TARJETAS -->
                        <?php if (!empty($activity['most_viewed'])): ?>
                        <div class="chart-card documents-section">
                            <h3>
                                <i data-feather="eye"></i>
                                Documentos Más Vistos
                            </h3>
                            <div class="documents-cards">
                                <?php foreach (array_slice($activity['most_viewed'], 0, 6) as $index => $doc): ?>
                                <div class="document-card rank-<?php echo $index + 1; ?>">
                                    <div class="card-header">
                                        <span class="rank-badge">#<?php echo $index + 1; ?></span>
                                        <span class="view-count"><?php echo number_format($doc['view_count']); ?> vistas</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="doc-icon">
                                            <i data-feather="file-text"></i>
                                        </div>
                                        <div class="doc-info">
                                            <h4 class="doc-name"><?php echo htmlspecialchars($doc['document_name'] ?? 'Sin nombre'); ?></h4>
                                            <div class="doc-meta">
                                                <span class="doc-type"><?php echo htmlspecialchars($doc['document_type'] ?? 'N/A'); ?></span>
                                                <span class="doc-size"><?php echo formatBytes($doc['file_size'] ?? 0); ?></span>
                                            </div>
                                            <div class="doc-user">
                                                <i data-feather="user"></i>
                                                <?php echo htmlspecialchars(trim(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? ''))); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Top usuarios con TARJETAS -->
                        <?php if (!empty($stats['top_uploaders'])): ?>
                        <div class="chart-card users-section">
                            <h3>
                                <i data-feather="users"></i>
                                Top Usuarios que Suben Documentos
                            </h3>
                            <div class="users-cards">
                                <?php foreach (array_slice($stats['top_uploaders'], 0, 6) as $index => $user): ?>
                                <div class="user-card rank-<?php echo $index + 1; ?>">
                                    <div class="card-header">
                                        <span class="rank-badge">#<?php echo $index + 1; ?></span>
                                        <span class="doc-count"><?php echo number_format($user['documents_count']); ?> docs</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="user-avatar">
                                            <i data-feather="user"></i>
                                        </div>
                                        <div class="user-info">
                                            <h4 class="user-name"><?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></h4>
                                            <div class="user-username">@<?php echo htmlspecialchars($user['username'] ?? 'usuario'); ?></div>
                                            <div class="user-stats">
                                                <span><?php echo formatBytes($user['total_size'] ?? 0); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Gráfico por día -->
                        <?php if (!empty($stats['by_date'])): ?>
                        <div class="chart-card full-width">
                            <h3>Documentos Subidos por Día</h3>
                            <div class="chart-container">
                                <canvas id="dateChart"></canvas>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Vista detallada -->
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
                                            <td><span class="badge badge-info"><?php echo number_format($doc['download_count'] ?? 0); ?></span></td>
                                            <td><span class="badge badge-success"><?php echo number_format($doc['view_count'] ?? 0); ?></span></td>
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

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        var currentFilters = <?php echo json_encode($_GET); ?>;

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
                const timeString = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                const dateString = now.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
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
            const urlParams = new URLSearchParams(window.location.search);
            const exportUrl = 'export.php?format=' + formato + '&type=documents_report&modal=1&' + urlParams.toString();

            if (formato === 'pdf') {
                abrirModalPDF(exportUrl);
            } else {
                mostrarNotificacion('Preparando descarga...', 'info');
                window.open(exportUrl.replace('&modal=1', ''), '_blank');
            }
        }

        function abrirModalPDF(url) {
            // Implementación del modal PDF (simplificada)
            window.open(url.replace('&modal=1', ''), '_blank');
        }

        function mostrarNotificacion(mensaje, tipo = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed; top: 20px; right: 20px; padding: 15px 20px;
                background: ${tipo === 'error' ? '#dc3545' : tipo === 'success' ? '#28a745' : '#17a2b8'};
                color: white; border-radius: 4px; z-index: 1000;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2); font-family: Arial, sans-serif;
            `;
            notification.textContent = mensaje;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        function showComingSoon(feature) {
            alert(`${feature} - Próximamente`);
        }

        <?php if ($reportType === 'summary'): ?>
        function initCharts() {
            // Gráfico de tipos
            const typeData = <?php echo json_encode($stats['by_type'] ?? []); ?>;
            if (typeData.length > 0) {
                const typeCtx = document.getElementById('typeChart');
                if (typeCtx) {
                    new Chart(typeCtx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: typeData.map(item => item.type_name || 'Sin tipo'),
                            datasets: [{
                                data: typeData.map(item => item.count),
                                backgroundColor: ['#4e342e', '#A0522D', '#654321', '#D2B48C', '#CD853F', '#DEB887', '#8B4513', '#A0522D']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
            }

            // Gráfico por día
            const dateData = <?php echo json_encode($stats['by_date'] ?? []); ?>;
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
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                        }
                    });
                }
            }
        }
        <?php endif; ?>

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) sidebar.classList.remove('active');
            }
        });
    </script>
    
    <style>
        /* ESTILOS COMPACTOS PARA LAS TARJETAS */
        .documents-section, .users-section {
            grid-column: span 2;
        }
        
        .documents-cards, .users-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .document-card, .user-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .document-card:hover, .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .document-card.rank-1, .user-card.rank-1 {
            border-left: 4px solid #FFD700;
            background: linear-gradient(135deg, #fffbf0 0%, #fff8e1 100%);
        }
        
        .document-card.rank-2, .user-card.rank-2 {
            border-left: 4px solid #C0C0C0;
        }
        
        .document-card.rank-3, .user-card.rank-3 {
            border-left: 4px solid #CD7F32;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        
        .rank-badge {
            background: #6c757d;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .rank-1 .rank-badge { background: #FFD700; color: #333; }
        .rank-2 .rank-badge { background: #C0C0C0; color: #333; }
        .rank-3 .rank-badge { background: #CD7F32; color: white; }
        
        .view-count, .doc-count {
            font-size: 0.9rem;
            font-weight: 600;
            color: #28a745;
        }
        
        .card-body {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .doc-icon, .user-avatar {
            width: 40px;
            height: 40px;
            background: #4e342e;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        
        .user-avatar {
            border-radius: 50%;
        }
        
        .doc-info, .user-info {
            flex: 1;
            min-width: 0;
        }
        
        .doc-name, .user-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: #212529;
            margin: 0 0 0.25rem 0;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .doc-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .doc-type {
            background: #e9ecef;
            color: #495057;
            padding: 0.125rem 0.375rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .doc-size {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .doc-user {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .doc-user i {
            width: 12px;
            height: 12px;
        }
        
        .user-username {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
        
        .user-stats {
            font-size: 0.8rem;
            color: #495057;
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .documents-cards, .users-cards {
                grid-template-columns: 1fr;
            }
            
            .documents-section, .users-section {
                grid-column: span 1;
            }
        }
        
        @media (max-width: 480px) {
            .card-body {
                flex-direction: column;
                text-align: center;
            }
            
            .doc-icon, .user-avatar {
                margin: 0 auto;
            }
        }
    </style>
</body>
</html>