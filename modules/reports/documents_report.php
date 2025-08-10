<?php
require_once '../../bootstrap.php';
// modules/reports/documents_report.php
// Reportes de documentos del sistema con tarjetas ordenadas - DMS2

// require_once '../../config/session.php'; // Cargado por bootstrap
// require_once '../../config/database.php'; // Cargado por bootstrap

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
/* Aplicar los mismos colores elegantes de user_reports.php */
:root {
    --primary-gradient: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
    --secondary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
    --success-gradient: linear-gradient(135deg, #10B981 0%, #059669 100%);
    --warning-gradient: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
    --info-gradient: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    --soft-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --soft-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* Sobrescribir estadísticas con diseño elegante */
.stat-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
    border-radius: 16px !important;
    box-shadow: var(--soft-shadow) !important;
    border: 1px solid #e5e7eb !important;
    position: relative !important;
    overflow: hidden !important;
    transition: all 0.3s ease !important;
    padding: 1.5rem !important;
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

.stat-card:nth-child(2)::before { background: var(--info-gradient) !important; }
.stat-card:nth-child(3)::before { background: var(--success-gradient) !important; }
.stat-card:nth-child(4)::before { background: var(--warning-gradient) !important; }

.stat-card:hover {
    transform: translateY(-2px) !important;
    box-shadow: var(--soft-shadow-lg) !important;
}

.stat-icon {
    background: var(--primary-gradient) !important;
    border-radius: 16px !important;
    box-shadow: 0 4px 8px rgba(139, 69, 19, 0.3) !important;
    width: 60px !important;
    height: 60px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: white !important;
}

.stat-card:nth-child(2) .stat-icon { 
    background: var(--info-gradient) !important; 
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3) !important; 
}
.stat-card:nth-child(3) .stat-icon { 
    background: var(--success-gradient) !important; 
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3) !important; 
}
.stat-card:nth-child(4) .stat-icon { 
    background: var(--warning-gradient) !important; 
    box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3) !important; 
}

.stat-number {
    background: var(--primary-gradient) !important;
    -webkit-background-clip: text !important;
    -webkit-text-fill-color: transparent !important;
    background-clip: text !important;
    font-weight: 700 !important;
    font-size: 2rem !important;
    line-height: 1 !important;
    margin-bottom: 0.25rem !important;
}

/* Filtros con diseño elegante */
.reports-filters {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
    border-radius: 16px !important;
    box-shadow: var(--soft-shadow) !important;
    border: 1px solid #e5e7eb !important;
    position: relative !important;
    overflow: hidden !important;
    padding: 2rem !important;
    margin-bottom: 2rem !important;
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
    margin: 0 0 1.5rem 0 !important;
    color: #1f2937 !important;
    font-size: 1.25rem !important;
    font-weight: 600 !important;
}

.filter-group input,
.filter-group select {
    padding: 0.75rem !important;
    border: 2px solid #e5e7eb !important;
    border-radius: 8px !important;
    font-size: 0.875rem !important;
    transition: border-color 0.3s ease, box-shadow 0.3s ease !important;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none !important;
    border-color: #8B4513 !important;
    box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1) !important;
}

/* Sección de exportar elegante */
.export-section {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
    border-radius: 16px !important;
    box-shadow: var(--soft-shadow) !important;
    border: 1px solid #e5e7eb !important;
    padding: 2rem !important;
    margin-bottom: 2rem !important;
}

.export-section h3 {
    margin: 0 0 1.5rem 0 !important;
    color: #1f2937 !important;
    font-size: 1.25rem !important;
    font-weight: 600 !important;
}

.export-btn {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
    border: 2px solid #e5e7eb !important;
    border-radius: 12px !important;
    box-shadow: var(--soft-shadow) !important;
    transition: all 0.3s ease !important;
    padding: 0.875rem 1.5rem !important;
    font-weight: 500 !important;
    color: #374151 !important;
}

.export-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: var(--soft-shadow-lg) !important;
    border-color: #8B4513 !important;
    color: #8B4513 !important;
}

/* Botones de filtro elegantes */
.btn-filter {
    background: var(--primary-gradient) !important;
    border: none !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 8px rgba(139, 69, 19, 0.3) !important;
    transition: all 0.3s ease !important;
    color: white !important;
    padding: 0.75rem 1.5rem !important;
    font-weight: 500 !important;
}

.btn-filter:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 12px rgba(139, 69, 19, 0.4) !important;
    color: white !important;
    text-decoration: none !important;
}

.btn-filter.secondary {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
    color: #374151 !important;
    border: 2px solid #e5e7eb !important;
    box-shadow: var(--soft-shadow) !important;
}

.btn-filter.secondary:hover {
    border-color: #8B4513 !important;
    color: #8B4513 !important;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
}

/* Gráficos y tarjetas elegantes */
.chart-card,
.users-section {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
    border-radius: 16px !important;
    box-shadow: var(--soft-shadow-lg) !important;
    border: 1px solid #e5e7eb !important;
    padding: 1.5rem !important;
    position: relative !important;
    overflow: hidden !important;
}

.chart-card::before,
.users-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--info-gradient);
}

.users-section:nth-child(odd)::before {
    background: var(--success-gradient);
}

.chart-card h3,
.users-section h3 {
    color: #1f2937 !important;
    font-size: 1.125rem !important;
    font-weight: 600 !important;
    margin: 0 0 1rem 0 !important;
}

.chart-card h3 i,
.users-section h3 i {
    color: #8B4513 !important;
}

/* Tabla elegante */
.reports-table {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
    border-radius: 16px !important;
    box-shadow: var(--soft-shadow-lg) !important;
    border: 1px solid #e5e7eb !important;
    overflow: hidden !important;
}

.reports-table h3 {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
    padding: 1.5rem !important;
    margin: 0 !important;
    color: #1f2937 !important;
    font-size: 1.25rem !important;
    font-weight: 600 !important;
    border-bottom: 1px solid #e5e7eb !important;
}

.data-table th {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
    padding: 1rem 0.75rem !important;
    font-weight: 600 !important;
    color: #374151 !important;
    border-bottom: 2px solid #e5e7eb !important;
    font-size: 0.875rem !important;
}

.data-table td {
    padding: 1rem 0.75rem !important;
    border-bottom: 1px solid #f3f4f6 !important;
    vertical-align: middle !important;
}

.data-table tbody tr:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
}

/* Badges elegantes */
.badge {
    padding: 0.25rem 0.75rem !important;
    border-radius: 20px !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    display: inline-block !important;
}

.badge-info {
    background: var(--info-gradient) !important;
    color: white !important;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3) !important;
}

.badge-success {
    background: var(--success-gradient) !important;
    color: white !important;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3) !important;
}

/* Lista de usuarios elegante */
.users-compact-list {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 12px !important;
}

.user-row {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
    border-bottom: 1px solid #e5e7eb !important;
    transition: all 0.3s ease !important;
}

.user-row:hover {
    background: linear-gradient(135deg, #f1f5f9 0%, #e5e7eb 100%) !important;
    transform: translateX(3px) !important;
}

.user-row.rank-1 {
    border-left: 4px solid #8B4513 !important;
    background: linear-gradient(90deg, rgba(139, 69, 19, 0.05) 0%, #ffffff 100%) !important;
}

.user-row.rank-2 {
    border-left: 4px solid #3B82F6 !important;
    background: linear-gradient(90deg, rgba(59, 130, 246, 0.05) 0%, #ffffff 100%) !important;
}

.user-row.rank-3 {
    border-left: 4px solid #10B981 !important;
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.05) 0%, #ffffff 100%) !important;
}

.rank-1 .user-rank {
    color: #8B4513 !important;
    font-weight: 700 !important;
}

.rank-2 .user-rank {
    color: #3B82F6 !important;
    font-weight: 700 !important;
}

.rank-3 .user-rank {
    color: #10B981 !important;
    font-weight: 700 !important;
}

.user-name {
    color: #1f2937 !important;
    font-weight: 600 !important;
}

.user-username {
    color: #8B4513 !important;
    background: rgba(139, 69, 19, 0.1) !important;
    padding: 2px 6px !important;
    border-radius: 8px !important;
    font-size: 0.65rem !important;
}

.stat-number {
    color: #1f2937 !important;
    font-weight: 700 !important;
}

.stat-size {
    color: #8B4513 !important;
    font-weight: 500 !important;
}

/* Estado vacío elegante */
.empty-state {
    text-align: center !important;
    padding: 4rem 2rem !important;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
}

.empty-content i {
    color: #8B4513 !important;
    opacity: 0.5 !important;
}

.empty-content h4 {
    color: #1f2937 !important;
    margin: 1rem 0 0.5rem 0 !important;
}

.empty-content p {
    color: #6b7280 !important;
}

/* Animaciones suaves */
@keyframes elegantFadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card,
.chart-card,
.users-section,
.reports-filters,
.export-section,
.reports-table {
    animation: elegantFadeIn 0.6s ease-out !important;
}

.stat-card:nth-child(1) { animation-delay: 0.1s !important; }
.stat-card:nth-child(2) { animation-delay: 0.2s !important; }
.stat-card:nth-child(3) { animation-delay: 0.3s !important; }
.stat-card:nth-child(4) { animation-delay: 0.4s !important; }

/* Responsive mejorado */
@media (max-width: 768px) {
    .stat-card {
        padding: 1rem !important;
    }
    
    .stat-icon {
        width: 50px !important;
        height: 50px !important;
    }
    
    .stat-number {
        font-size: 1.5rem !important;
    }
    
    .reports-filters,
    .export-section {
        padding: 1.5rem !important;
    }
}
</style>
</body>
</html>