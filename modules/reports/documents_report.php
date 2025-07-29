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

    // Estadísticas de usuarios - subidas
    $query = "SELECT u.id, u.first_name, u.last_name, u.username, u.email, 
                     COUNT(d.id) as uploads_count, SUM(d.file_size) as total_size
              FROM users u
              LEFT JOIN documents d ON u.id = d.user_id AND d.status = 'active'
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE u.status = 'active' AND d.id IS NOT NULL AND ($whereClause)
              GROUP BY u.id, u.first_name, u.last_name, u.username, u.email
              ORDER BY uploads_count DESC, u.first_name ASC
              LIMIT 15";
    $stats['user_uploads'] = fetchAll($query, $params);

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

    // Estadísticas de descargas por usuario
    $query = "SELECT u.id, u.first_name, u.last_name, u.username, u.email,
                     COUNT(al.id) as downloads_count
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              LEFT JOIN documents d ON al.record_id = d.id
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              WHERE $whereClause AND al.action = 'download' AND u.status = 'active'
              GROUP BY u.id, u.first_name, u.last_name, u.username, u.email
              ORDER BY downloads_count DESC, u.first_name ASC
              LIMIT 15";

    return ['user_downloads' => fetchAll($query, $params)];
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
                            if (isset($activity['user_downloads']) && is_array($activity['user_downloads'])) {
                                foreach ($activity['user_downloads'] as $user) {
                                    $totalDownloads += $user['downloads_count'];
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
                        <div class="stat-number"><?php echo count($stats['user_uploads']); ?></div>
                        <div class="stat-label">Usuarios Activos</div>
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
                            <h3><i data-feather="pie-chart"></i> Documentos por Tipo</h3>
                            <div class="chart-container">
                                <canvas id="typeChart"></canvas>
                            </div>
                        </div>

                        <!-- Lista de usuarios que suben documentos -->
                        <?php if (!empty($stats['user_uploads'])): ?>
                        <div class="chart-card users-section">
                            <h3>
                                <i data-feather="upload"></i>
                                Usuarios - Subidas de Documentos
                            </h3>
                            <div class="users-compact-list">
                                <?php foreach ($stats['user_uploads'] as $index => $user): ?>
                                <div class="user-row rank-<?php echo min($index + 1, 3); ?>">
                                    <span class="user-rank">#<?php echo $index + 1; ?></span>
                                    <div class="user-details">
                                        <span class="user-name"><?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username']); ?></span>
                                        <span class="user-username">@<?php echo htmlspecialchars($user['username']); ?></span>
                                    </div>
                                    <div class="user-stats">
                                        <span class="stat-number"><?php echo number_format($user['uploads_count']); ?></span>
                                        <span class="stat-label">docs</span>
                                        <span class="stat-size"><?php echo formatBytes($user['total_size'] ?? 0); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Lista de usuarios que descargan documentos -->
                        <?php if (!empty($activity['user_downloads'])): ?>
                        <div class="chart-card users-section">
                            <h3>
                                <i data-feather="download"></i>
                                Usuarios - Descargas de Documentos
                            </h3>
                            <div class="users-compact-list">
                                <?php foreach ($activity['user_downloads'] as $index => $user): ?>
                                <div class="user-row rank-<?php echo min($index + 1, 3); ?>">
                                    <span class="user-rank">#<?php echo $index + 1; ?></span>
                                    <div class="user-details">
                                        <span class="user-name"><?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username']); ?></span>
                                        <span class="user-username">@<?php echo htmlspecialchars($user['username']); ?></span>
                                    </div>
                                    <div class="user-stats">
                                        <span class="stat-number"><?php echo number_format($user['downloads_count']); ?></span>
                                        <span class="stat-label">descargas</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Gráfico por día -->
                        <?php if (!empty($stats['by_date'])): ?>
                        <div class="chart-card full-width">
                            <h3><i data-feather="trending-up"></i> Documentos Subidos por Día</h3>
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
            // Crear modal para PDF
            const modal = document.createElement('div');
            modal.className = 'pdf-modal';
            modal.innerHTML = `
                <div class="pdf-modal-content">
                    <div class="pdf-modal-header">
                        <h3><i data-feather="file-text"></i> Reporte de Documentos - PDF</h3>
                        <button class="pdf-modal-close" onclick="cerrarModalPDF()">&times;</button>
                    </div>
                    <div class="pdf-modal-body">
                        <div class="pdf-preview-container">
                            <div class="pdf-loading">
                                <div class="loading-spinner"></div>
                                <p>Generando reporte PDF...</p>
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
            // Gráfico de tipos - MEJORADO
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
                                backgroundColor: [
                                    '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
                                    '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6b7280'
                                ],
                                borderWidth: 2,
                                borderColor: '#ffffff',
                                hoverBorderWidth: 3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true,
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = ((value / total) * 100).toFixed(1);
                                            return `${label}: ${value} (${percentage}%)`;
                                        }
                                    }
                                }
                            },
                            animation: {
                                animateRotate: true,
                                duration: 1000
                            }
                        }
                    });
                }
            }

            // Gráfico por día - MEJORADO
            const dateData = <?php echo json_encode($stats['by_date'] ?? []); ?>;
            if (dateData.length > 0) {
                const dateCtx = document.getElementById('dateChart');
                if (dateCtx) {
                    new Chart(dateCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: dateData.map(item => {
                                const date = new Date(item.date);
                                return date.toLocaleDateString('es-ES', { 
                                    day: '2-digit', 
                                    month: '2-digit' 
                                });
                            }),
                            datasets: [{
                                label: 'Documentos subidos',
                                data: dateData.map(item => item.count),
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#3b82f6',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                pointHoverRadius: 8,
                                pointHoverBackgroundColor: '#1d4ed8',
                                pointHoverBorderColor: '#ffffff',
                                pointHoverBorderWidth: 3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { 
                                    display: false 
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    titleColor: '#ffffff',
                                    bodyColor: '#ffffff',
                                    borderColor: '#3b82f6',
                                    borderWidth: 1
                                }
                            },
                            scales: { 
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: '#6b7280'
                                    }
                                },
                                y: { 
                                    beginAtZero: true, 
                                    ticks: { 
                                        stepSize: 1,
                                        color: '#6b7280'
                                    },
                                    grid: {
                                        color: 'rgba(107, 114, 128, 0.1)'
                                    }
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            },
                            animation: {
                                duration: 1000,
                                easing: 'easeInOutQuart'
                            }
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
        /* ESTILOS COMPACTOS PARA LISTAS DE USUARIOS */
        .users-section {
            grid-column: span 1;
        }
        
        .users-compact-list {
            max-height: 350px;
            overflow-y: auto;
            margin-top: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #f9fafb;
        }
        
        .user-row {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid #e5e7eb;
            background: white;
            transition: all 0.2s ease;
        }
        
        .user-row:last-child {
            border-bottom: none;
        }
        
        .user-row:hover {
            background: #f3f4f6;
            transform: translateX(2px);
        }
        
        .user-row.rank-1 {
            border-left: 3px solid #FFD700;
            background: linear-gradient(90deg, #fffbf0 0%, #ffffff 100%);
        }
        
        .user-row.rank-2 {
            border-left: 3px solid #C0C0C0;
            background: linear-gradient(90deg, #f8f9fa 0%, #ffffff 100%);
        }
        
        .user-row.rank-3 {
            border-left: 3px solid #CD7F32;
            background: linear-gradient(90deg, #fdf6f0 0%, #ffffff 100%);
        }
        
        .user-rank {
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            width: 30px;
            text-align: center;
            margin-right: 12px;
        }
        
        .rank-1 .user-rank {
            color: #D97706;
        }
        
        .rank-2 .user-rank {
            color: #6b7280;
        }
        
        .rank-3 .user-rank {
            color: #92400e;
        }
        
        .user-details {
            flex: 1;
            min-width: 0;
        }
        
        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: #1f2937;
            display: block;
            line-height: 1.2;
            margin-bottom: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .user-username {
            font-size: 11px;
            color: #6b7280;
            font-family: 'Courier New', monospace;
        }
        
        .user-stats {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            min-width: 80px;
        }
        
        .stat-number {
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-size {
            font-size: 10px;
            color: #3b82f6;
            font-weight: 500;
            margin-top: 1px;
        }
        
        /* Mejorar scroll */
        .users-compact-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .users-compact-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .users-compact-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .users-compact-list::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .users-section {
                grid-column: span 1;
            }
            
            .user-row {
                padding: 10px;
            }
            
            .user-name {
                font-size: 12px;
            }
            
            .stat-number {
                font-size: 13px;
            }
            
            .user-stats {
                min-width: 70px;
            }
        }
        
        @media (max-width: 480px) {
            .user-row {
                padding: 8px;
            }
            
            .user-rank {
                width: 25px;
                margin-right: 8px;
            }
            
            .user-stats {
                min-width: 60px;
            }
        }
        
        /* Mejorar gráficos */
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 15px;
        }
        
        .chart-card h3 {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 10px 0;
            color: #1f2937;
            font-size: 16px;
            font-weight: 600;
        }
        
        .chart-card h3 i {
            width: 20px;
            height: 20px;
            color: #3b82f6;
        }
        
        /* Animaciones */
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
        
        .user-item {
            animation: fadeInUp 0.3s ease-out;
        }
        
        .user-item:nth-child(1) { animation-delay: 0.1s; }
        .user-item:nth-child(2) { animation-delay: 0.2s; }
        .user-item:nth-child(3) { animation-delay: 0.3s; }
        .user-item:nth-child(4) { animation-delay: 0.4s; }
        .user-item:nth-child(5) { animation-delay: 0.5s; }

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

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 768px) {
            .pdf-modal-content {
                width: 95%;
                height: 90%;
            }
            
            .pdf-modal-header,
            .pdf-modal-body {
                padding: 15px;
            }
            
            .pdf-actions {
                flex-direction: column;
            }
            
            .pdf-actions button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</body>
</html>