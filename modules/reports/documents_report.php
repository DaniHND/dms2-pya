<?php
// modules/reports/documents_reports2.php
// Reporte completo de documentos - DMS2
// VERSION NUEVA CON DISEÑO MEJORADO

require_once '../../config/session.php';
require_once '../../config/database.php';

// Asegurar que las funciones de base de datos estén disponibles
if (!function_exists('fetchOne')) {
    function fetchOne($query, $params = []) {
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
    function fetchAll($query, $params = []) {
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
    function logActivity($userId, $action, $tableName = null, $recordId = null, $description = null) {
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

// Parámetros de filtrado
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$companyId = $_GET['company_id'] ?? '';
$documentType = $_GET['document_type'] ?? '';
$extension = $_GET['extension'] ?? '';

// Función para obtener estadísticas generales
function getDocumentStats($currentUser, $dateFrom, $dateTo, $companyId, $documentType, $extension)
{
    $whereConditions = [];
    $params = [];

    // Filtro por fechas
    $whereConditions[] = "d.created_at >= :date_from";
    $whereConditions[] = "d.created_at <= :date_to";
    $whereConditions[] = "d.status = 'active'";
    $params['date_from'] = $dateFrom . ' 00:00:00';
    $params['date_to'] = $dateTo . ' 23:59:59';

    // Filtro por empresa (si no es admin)
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "d.company_id = :current_company_id";
        $params['current_company_id'] = $currentUser['company_id'];
    } elseif (!empty($companyId)) {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    // Filtro por tipo de documento
    if (!empty($documentType)) {
        $whereConditions[] = "d.document_type_id = :document_type";
        $params['document_type'] = $documentType;
    }

    // Filtro por extensión
    if (!empty($extension)) {
        $whereConditions[] = "d.mime_type LIKE :extension";
        $params['extension'] = "%$extension%";
    }

    $whereClause = implode(' AND ', $whereConditions);

    try {
        // Total documentos y tamaño
        $totalQuery = "SELECT COUNT(*) as total, 
                             COALESCE(SUM(d.file_size), 0) as total_size
                      FROM documents d
                      WHERE $whereClause";
        $totalResult = fetchOne($totalQuery, $params);

        // Total descargas
        $downloadsQuery = "SELECT COUNT(*) as total_downloads
                          FROM activity_logs al
                          LEFT JOIN users u ON al.user_id = u.id
                          WHERE al.action = 'download' AND al.created_at >= :date_from AND al.created_at <= :date_to";
        
        $downloadParams = ['date_from' => $params['date_from'], 'date_to' => $params['date_to']];
        
        if ($currentUser['role'] !== 'admin') {
            $downloadsQuery .= " AND u.company_id = :current_company_id";
            $downloadParams['current_company_id'] = $currentUser['company_id'];
        } elseif (!empty($companyId)) {
            $downloadsQuery .= " AND u.company_id = :company_id";
            $downloadParams['company_id'] = $companyId;
        }

        $downloadsResult = fetchOne($downloadsQuery, $downloadParams);

        return [
            'total_documents' => $totalResult['total'] ?? 0,
            'total_size' => $totalResult['total_size'] ?? 0,
            'total_downloads' => $downloadsResult['total_downloads'] ?? 0
        ];
    } catch (Exception $e) {
        error_log("Error en getDocumentStats: " . $e->getMessage());
        return [
            'total_documents' => 0,
            'total_size' => 0,
            'total_downloads' => 0
        ];
    }
}

// Función para obtener documentos por tipo (para gráfico)
function getDocumentsByType($currentUser, $dateFrom, $dateTo, $companyId, $documentType, $extension)
{
    $whereConditions = [];
    $params = [];

    $whereConditions[] = "d.created_at >= :date_from";
    $whereConditions[] = "d.created_at <= :date_to";
    $whereConditions[] = "d.status = 'active'";
    $params['date_from'] = $dateFrom . ' 00:00:00';
    $params['date_to'] = $dateTo . ' 23:59:59';

    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "d.company_id = :current_company_id";
        $params['current_company_id'] = $currentUser['company_id'];
    } elseif (!empty($companyId)) {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    if (!empty($documentType)) {
        $whereConditions[] = "d.document_type_id = :document_type";
        $params['document_type'] = $documentType;
    }

    if (!empty($extension)) {
        $whereConditions[] = "d.mime_type LIKE :extension";
        $params['extension'] = "%$extension%";
    }

    $whereClause = implode(' AND ', $whereConditions);

    try {
        $query = "SELECT COALESCE(dt.name, 'Sin tipo') as type, COUNT(*) as count
                  FROM documents d
                  LEFT JOIN document_types dt ON d.document_type_id = dt.id
                  WHERE $whereClause
                  GROUP BY dt.name
                  ORDER BY count DESC
                  LIMIT 10";

        return fetchAll($query, $params);
    } catch (Exception $e) {
        error_log("Error en getDocumentsByType: " . $e->getMessage());
        return [];
    }
}

// Función para obtener documentos por extensión (para gráfico)
function getDocumentsByExtension($currentUser, $dateFrom, $dateTo, $companyId, $documentType, $extension)
{
    $whereConditions = [];
    $params = [];

    $whereConditions[] = "d.created_at >= :date_from";
    $whereConditions[] = "d.created_at <= :date_to";
    $whereConditions[] = "d.status = 'active'";
    $params['date_from'] = $dateFrom . ' 00:00:00';
    $params['date_to'] = $dateTo . ' 23:59:59';

    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "d.company_id = :current_company_id";
        $params['current_company_id'] = $currentUser['company_id'];
    } elseif (!empty($companyId)) {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    if (!empty($documentType)) {
        $whereConditions[] = "d.document_type_id = :document_type";
        $params['document_type'] = $documentType;
    }

    if (!empty($extension)) {
        $whereConditions[] = "d.mime_type LIKE :extension";
        $params['extension'] = "%$extension%";
    }

    $whereClause = implode(' AND ', $whereConditions);

    try {
        $query = "SELECT 
                    CASE 
                        WHEN d.mime_type LIKE '%pdf%' THEN 'PDF'
                        WHEN d.mime_type LIKE '%word%' OR d.mime_type LIKE '%document%' THEN 'DOC'
                        WHEN d.mime_type LIKE '%excel%' OR d.mime_type LIKE '%spreadsheet%' THEN 'XLS'
                        WHEN d.mime_type LIKE '%image%' THEN 'IMG'
                        ELSE 'OTROS'
                    END as extension,
                    COUNT(*) as count
                  FROM documents d
                  WHERE $whereClause
                  GROUP BY 
                    CASE 
                        WHEN d.mime_type LIKE '%pdf%' THEN 'PDF'
                        WHEN d.mime_type LIKE '%word%' OR d.mime_type LIKE '%document%' THEN 'DOC'
                        WHEN d.mime_type LIKE '%excel%' OR d.mime_type LIKE '%spreadsheet%' THEN 'XLS'
                        WHEN d.mime_type LIKE '%image%' THEN 'IMG'
                        ELSE 'OTROS'
                    END
                  ORDER BY count DESC";

        return fetchAll($query, $params);
    } catch (Exception $e) {
        error_log("Error en getDocumentsByExtension: " . $e->getMessage());
        return [];
    }
}

// Función para obtener datos de subidas y descargas por mes
function getUploadDownloadData($currentUser, $dateFrom, $dateTo)
{
    try {
        $params = ['date_from' => $dateFrom . ' 00:00:00', 'date_to' => $dateTo . ' 23:59:59'];
        $companyFilter = '';

        if ($currentUser['role'] !== 'admin') {
            $companyFilter = " AND d.company_id = :current_company_id";
            $params['current_company_id'] = $currentUser['company_id'];
        }

        // Subidas por mes
        $uploadsQuery = "SELECT 
                            DATE_FORMAT(d.created_at, '%Y-%m') as month,
                            COUNT(*) as uploads
                         FROM documents d
                         WHERE d.created_at >= :date_from AND d.created_at <= :date_to 
                               AND d.status = 'active' $companyFilter
                         GROUP BY DATE_FORMAT(d.created_at, '%Y-%m')
                         ORDER BY month";

        $uploads = fetchAll($uploadsQuery, $params);

        // Descargas por mes
        $downloadsQuery = "SELECT 
                              DATE_FORMAT(al.created_at, '%Y-%m') as month,
                              COUNT(*) as downloads
                           FROM activity_logs al
                           LEFT JOIN users u ON al.user_id = u.id
                           WHERE al.action = 'download' 
                                 AND al.created_at >= :date_from AND al.created_at <= :date_to";

        if ($currentUser['role'] !== 'admin') {
            $downloadsQuery .= " AND u.company_id = :current_company_id";
        }

        $downloadsQuery .= " GROUP BY DATE_FORMAT(al.created_at, '%Y-%m') ORDER BY month";

        $downloads = fetchAll($downloadsQuery, $params);

        return ['uploads' => $uploads, 'downloads' => $downloads];
    } catch (Exception $e) {
        error_log("Error en getUploadDownloadData: " . $e->getMessage());
        return ['uploads' => [], 'downloads' => []];
    }
}

// Función para obtener documentos con filtros (para la tabla)
function getFilteredDocuments($currentUser, $dateFrom, $dateTo, $companyId, $documentType, $extension, $limit = 50)
{
    $whereConditions = [];
    $params = [];

    $whereConditions[] = "d.created_at >= :date_from";
    $whereConditions[] = "d.created_at <= :date_to";
    $whereConditions[] = "d.status = 'active'";
    $params['date_from'] = $dateFrom . ' 00:00:00';
    $params['date_to'] = $dateTo . ' 23:59:59';

    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "d.company_id = :current_company_id";
        $params['current_company_id'] = $currentUser['company_id'];
    } elseif (!empty($companyId)) {
        $whereConditions[] = "d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    if (!empty($documentType)) {
        $whereConditions[] = "d.document_type_id = :document_type";
        $params['document_type'] = $documentType;
    }

    if (!empty($extension)) {
        $whereConditions[] = "d.mime_type LIKE :extension";
        $params['extension'] = "%$extension%";
    }

    $whereClause = implode(' AND ', $whereConditions);

    try {
        $query = "SELECT d.*, 
                         dt.name as document_type_name,
                         c.name as company_name,
                         dept.name as department_name,
                         u.first_name, u.last_name
                  FROM documents d
                  LEFT JOIN document_types dt ON d.document_type_id = dt.id
                  LEFT JOIN companies c ON d.company_id = c.id
                  LEFT JOIN departments dept ON d.department_id = dept.id
                  LEFT JOIN users u ON d.user_id = u.id
                  WHERE $whereClause
                  ORDER BY d.created_at DESC
                  LIMIT :limit";

        $params['limit'] = $limit;
        return fetchAll($query, $params);
    } catch (Exception $e) {
        error_log("Error en getFilteredDocuments: " . $e->getMessage());
        return [];
    }
}

// Función para obtener opciones de filtros
function getFilterOptions($currentUser)
{
    try {
        // Empresas
        $companies = [];
        if ($currentUser['role'] === 'admin') {
            $companies = fetchAll("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name");
        }

        // Tipos de documentos
        $documentTypes = fetchAll("SELECT id, name FROM document_types WHERE status = 'active' ORDER BY name");

        // Extensiones más comunes
        $extensions = [
            'pdf' => 'PDF',
            'word' => 'Word (DOC/DOCX)',
            'excel' => 'Excel (XLS/XLSX)',
            'image' => 'Imágenes',
            'text' => 'Texto'
        ];

        return [
            'companies' => $companies,
            'document_types' => $documentTypes,
            'extensions' => $extensions
        ];
    } catch (Exception $e) {
        error_log("Error en getFilterOptions: " . $e->getMessage());
        return [
            'companies' => [],
            'document_types' => [],
            'extensions' => []
        ];
    }
}

// Función para formatear bytes
function formatBytes($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

// Obtener todos los datos
$stats = getDocumentStats($currentUser, $dateFrom, $dateTo, $companyId, $documentType, $extension);
$documentsByType = getDocumentsByType($currentUser, $dateFrom, $dateTo, $companyId, $documentType, $extension);
$documentsByExtension = getDocumentsByExtension($currentUser, $dateFrom, $dateTo, $companyId, $documentType, $extension);
$uploadDownloadData = getUploadDownloadData($currentUser, $dateFrom, $dateTo);
$documents = getFilteredDocuments($currentUser, $dateFrom, $dateTo, $companyId, $documentType, $extension);
$filterOptions = getFilterOptions($currentUser);

// Registrar acceso
if (function_exists('logActivity')) {
    logActivity($currentUser['id'], 'view_documents_report', 'reports', null, 'Usuario accedió al reporte de documentos');
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Documentos - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reports.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">
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
                <h1>Reporte de Documentos</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(getFullName()); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
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
                        <i data-feather="file-text"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['total_documents']); ?></div>
                        <div class="stat-label">Total Documentos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="download"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['total_downloads']); ?></div>
                        <div class="stat-label">Total Descargas</div>
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
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <div class="filter-group">
                        <label for="company_id">Empresa</label>
                        <select id="company_id" name="company_id">
                            <option value="">Todas las empresas</option>
                            <?php foreach ($filterOptions['companies'] as $company): ?>
                                <option value="<?php echo $company['id']; ?>" <?php echo $companyId == $company['id'] ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $type['id']; ?>" <?php echo $documentType == $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="extension">Extensión</label>
                        <select id="extension" name="extension">
                            <option value="">Todas las extensiones</option>
                            <?php foreach ($filterOptions['extensions'] as $ext => $label): ?>
                                <option value="<?php echo $ext; ?>" <?php echo $extension === $ext ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
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

            <!-- Gráficos -->
            <div class="charts-section">
                <div class="charts-grid">
                    <!-- Gráfico por tipo -->
                    <div class="chart-card">
                        <h3><i data-feather="pie-chart"></i> Documentos por Tipo</h3>
                        <div class="chart-container">
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico por extensión -->
                    <div class="chart-card">
                        <h3><i data-feather="bar-chart-2"></i> Documentos por Extensión</h3>
                        <div class="chart-container">
                            <canvas id="extensionChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Gráfico de subidas y descargas -->
                <div class="chart-card full-width">
                    <h3><i data-feather="trending-up"></i> Subidas y Descargas por Mes</h3>
                    <div class="chart-container">
                        <canvas id="uploadDownloadChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tabla de documentos -->
            <div class="reports-table enhanced-table">
                <div class="table-header">
                    <h3><i data-feather="file-text"></i> Documentos Recientes (<?php echo count($documents); ?> registros)</h3>
                </div>

                <?php if (!empty($documents)): ?>
                    <div class="table-container">
                        <table class="data-table documents-table">
                            <thead>
                                <tr>
                                    <th><i data-feather="file" class="table-icon"></i> Documento</th>
                                    <th><i data-feather="tag" class="table-icon"></i> Tipo</th>
                                    <?php if ($currentUser['role'] === 'admin'): ?>
                                        <th><i data-feather="building" class="table-icon"></i> Empresa</th>
                                    <?php endif; ?>
                                    <th><i data-feather="hard-drive" class="table-icon"></i> Tamaño</th>
                                    <th><i data-feather="user" class="table-icon"></i> Subido por</th>
                                    <th><i data-feather="calendar" class="table-icon"></i> Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td class="document-cell">
                                            <div class="document-info">
                                                <div class="document-name"><?php echo htmlspecialchars($doc['name']); ?></div>
                                                <small class="document-original"><?php echo htmlspecialchars($doc['original_name']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="type-badge">
                                                <?php echo htmlspecialchars($doc['document_type_name'] ?? 'Sin tipo'); ?>
                                            </span>
                                        </td>
                                        <?php if ($currentUser['role'] === 'admin'): ?>
                                            <td class="company-cell">
                                                <div class="company-info">
                                                    <i data-feather="building" class="company-icon"></i>
                                                    <span><?php echo htmlspecialchars($doc['company_name'] ?? 'Sin empresa'); ?></span>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        <td class="size-cell">
                                            <?php echo formatBytes($doc['file_size'] ?? 0); ?>
                                        </td>
                                        <td class="user-cell">
                                            <div class="user-info">
                                                <span class="user-name">
                                                    <?php echo htmlspecialchars(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? '')); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="date-cell">
                                            <div class="date-info">
                                                <div class="date"><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></div>
                                                <div class="time"><?php echo date('H:i', strtotime($doc['created_at'])); ?></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state enhanced-empty-state">
                        <div class="empty-content">
                            <div class="empty-icon">
                                <i data-feather="file-text"></i>
                            </div>
                            <h4>No se encontraron documentos</h4>
                            <p>No hay documentos que coincidan con los filtros seleccionados.</p>
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

        // Datos para gráficos
        var typeData = <?php echo json_encode($documentsByType); ?>;
        var extensionData = <?php echo json_encode($documentsByExtension); ?>;
        var uploadDownloadData = <?php echo json_encode($uploadDownloadData); ?>;

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);
            initCharts();
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

        // Inicializar gráficos
        function initCharts() {
            initTypeChart();
            initExtensionChart();
            initUploadDownloadChart();
        }

        // Gráfico de documentos por tipo (pie chart)
        function initTypeChart() {
            const ctx = document.getElementById('typeChart').getContext('2d');
            
            const labels = typeData.map(item => item.type);
            const data = typeData.map(item => item.count);
            const colors = [
                '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
                '#EC4899', '#6B7280', '#14B8A6', '#F97316', '#84CC16'
            ];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors.slice(0, data.length),
                        borderWidth: 2,
                        borderColor: '#ffffff'
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
                                usePointStyle: true
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
                    }
                }
            });
        }

        // Gráfico de documentos por extensión (bar chart)
        function initExtensionChart() {
            const ctx = document.getElementById('extensionChart').getContext('2d');
            
            const labels = extensionData.map(item => item.extension);
            const data = extensionData.map(item => item.count);

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Cantidad',
                        data: data,
                        backgroundColor: [
                            '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6'
                        ],
                        borderColor: [
                            '#2563EB', '#DC2626', '#059669', '#D97706', '#7C3AED'
                        ],
                        borderWidth: 1,
                        borderRadius: 4
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
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed.y} documentos`;
                                }
                            }
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

        // Gráfico de subidas y descargas por mes
        function initUploadDownloadChart() {
            const ctx = document.getElementById('uploadDownloadChart').getContext('2d');
            
            // Preparar datos
            const allMonths = new Set();
            uploadDownloadData.uploads.forEach(item => allMonths.add(item.month));
            uploadDownloadData.downloads.forEach(item => allMonths.add(item.month));
            
            const sortedMonths = Array.from(allMonths).sort();
            
            const uploadsMap = new Map(uploadDownloadData.uploads.map(item => [item.month, item.uploads]));
            const downloadsMap = new Map(uploadDownloadData.downloads.map(item => [item.month, item.downloads]));
            
            const uploadsData = sortedMonths.map(month => uploadsMap.get(month) || 0);
            const downloadsData = sortedMonths.map(month => downloadsMap.get(month) || 0);
            
            // Formatear labels de mes
            const monthLabels = sortedMonths.map(month => {
                const [year, monthNum] = month.split('-');
                const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                                  'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                return `${monthNames[parseInt(monthNum) - 1]} ${year}`;
            });

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Subidas',
                        data: uploadsData,
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#10B981',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6
                    }, {
                        label: 'Descargas',
                        data: downloadsData,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#3B82F6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                title: function(context) {
                                    return context[0].label;
                                },
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }

        // Filtros automáticos
        document.addEventListener('change', function(e) {
            if (e.target.matches('#date_from, #date_to, #company_id, #document_type, #extension')) {
                autoFilter();
            }
        });

        function autoFilter() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const companyId = document.getElementById('company_id') ? document.getElementById('company_id').value : '';
            const documentType = document.getElementById('document_type').value;
            const extension = document.getElementById('extension').value;

            const params = new URLSearchParams();
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);
            if (companyId) params.set('company_id', companyId);
            if (documentType) params.set('document_type', documentType);
            if (extension) params.set('extension', extension);

            window.location.href = window.location.pathname + '?' + params.toString();
        }

        // Exportación de datos
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
        /* Estilos específicos para el reporte de documentos */
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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

        /* Sección de gráficos */
        .charts-section {
            margin-bottom: 2rem;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
        }

        .chart-card.full-width {
            grid-column: 1 / -1;
        }

        .chart-card h3 {
            margin: 0 0 1.5rem 0;
            color: #1f2937;
            font-size: 1.125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .chart-card.full-width .chart-container {
            height: 400px;
        }

        /* Tabla mejorada */
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

        .documents-table {
            width: 100%;
            border-collapse: collapse;
        }

        .documents-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1rem 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .documents-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .documents-table tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .table-icon {
            width: 16px;
            height: 16px;
            margin-right: 0.5rem;
            color: #6b7280;
        }

        /* Celdas específicas */
        .document-cell {
            min-width: 250px;
        }

        .document-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .document-name {
            font-weight: 500;
            color: #1f2937;
        }

        .document-original {
            color: #6b7280;
            font-size: 0.75rem;
        }

        .type-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            background: var(--primary-gradient);
            color: white;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(139, 69, 19, 0.3);
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

        .size-cell {
            font-family: monospace;
            font-weight: 500;
            color: #6b7280;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-name {
            font-weight: 500;
            color: #1f2937;
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

        /* Estado vacío */
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

        /* Responsive para botones del modal */
        @media (max-width: 480px) {
            .pdf-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .pdf-actions .export-btn {
                width: 100%;
                min-width: auto;
            }
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

        .chart-card {
            animation: fadeInUp 0.6s ease-out;
            animation-delay: 0.4s;
        }

        .documents-table tbody tr {
            animation: fadeInUp 0.4s ease-out;
        }

        /* Responsividad */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .charts-grid {
                grid-template-columns: 1fr;
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

            .chart-container {
                height: 250px;
            }

            .documents-table th:nth-child(3),
            .documents-table td:nth-child(3) {
                display: none; /* Ocultar empresa en móvil */
            }

            .documents-table th:nth-child(4),
            .documents-table td:nth-child(4) {
                display: none; /* Ocultar tamaño en móvil */
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .documents-table th:nth-child(5),
            .documents-table td:nth-child(5) {
                display: none; /* Ocultar usuario en móvil pequeño */
            }

            .reports-content {
                padding: 1rem;
            }

            .chart-container {
                height: 200px;
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

        /* Mejorar visibilidad de los gráficos */
        .chart-card {
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        /* Estilos para botones de filtro activos */
        .filter-group select:focus,
        .filter-group input:focus {
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        /* Mejoras en la tabla de documentos */
        .documents-table tbody tr {
            transition: all 0.2s ease;
        }

        .documents-table tbody tr:hover {
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

        /* Mejoras en tooltips para gráficos */
        .chart-container canvas {
            cursor: crosshair;
        }

        /* Estilos para enlaces y botones con efectos */
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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .breadcrumb-link:hover::before,
        .export-btn:hover::before,
        .btn-empty-action:hover::before {
            left: 100%;
        }

        /* Indicadores de estado para documentos */
        .document-cell::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--success-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .documents-table tbody tr:hover .document-cell::before {
            opacity: 1;
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
        .chart-card,
        .reports-filters,
        .export-section,
        .enhanced-table {
            opacity: 0;
            animation: slideInUp 0.6s ease-out forwards;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .reports-filters { animation-delay: 0.4s; }
        .export-section { animation-delay: 0.5s; }
        .chart-card { animation-delay: 0.6s; }
        .enhanced-table { animation-delay: 0.7s; }

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
    </style>
</body>

</html>