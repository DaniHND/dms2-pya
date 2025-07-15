<?php
// modules/reports/export.php
// Sistema de exportación de reportes - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de exportación
$format = $_GET['format'] ?? 'csv';
$type = $_GET['type'] ?? 'activity_log';

// Función para exportar log de actividades
function exportActivityLog($currentUser, $params, $format) {
    $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $params['date_to'] ?? date('Y-m-d');
    $userId = $params['user_id'] ?? '';
    $action = $params['action'] ?? '';
    
    $whereConditions = [];
    $queryParams = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];
    
    $whereConditions[] = "al.created_at >= :date_from";
    $whereConditions[] = "al.created_at <= :date_to";
    
    if (!empty($userId)) {
        $whereConditions[] = "al.user_id = :user_id";
        $queryParams['user_id'] = $userId;
    }
    
    if (!empty($action)) {
        $whereConditions[] = "al.action = :action";
        $queryParams['action'] = $action;
    }
    
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "u.company_id = :company_id";
        $queryParams['company_id'] = $currentUser['company_id'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "SELECT al.created_at, u.first_name, u.last_name, u.username, 
                     c.name as company_name, al.action, al.description, al.ip_address
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              LEFT JOIN companies c ON u.company_id = c.id
              WHERE $whereClause
              ORDER BY al.created_at DESC";
    
    $data = fetchAll($query, $queryParams);
    
    $headers = [
        'Fecha/Hora',
        'Nombre',
        'Apellido',
        'Usuario',
        'Empresa',
        'Acción',
        'Descripción',
        'IP'
    ];
    
    $rows = [];
    foreach ($data as $row) {
        $rows[] = [
            date('d/m/Y H:i:s', strtotime($row['created_at'])),
            $row['first_name'],
            $row['last_name'],
            $row['username'],
            $row['company_name'],
            $row['action'],
            $row['description'],
            $row['ip_address']
        ];
    }
    
    return ['headers' => $headers, 'rows' => $rows, 'filename' => 'log_actividades_' . date('Y-m-d')];
}

// Función para exportar reportes de usuarios
function exportUserReports($currentUser, $params, $format) {
    $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $params['date_to'] ?? date('Y-m-d');
    
    if ($currentUser['role'] === 'admin') {
        $query = "SELECT u.username, u.first_name, u.last_name, u.email, u.role, 
                         u.last_login, u.created_at, c.name as company_name,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.created_at >= :date_from AND al.created_at <= :date_to) as activity_count,
                         (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id 
                          AND d.created_at >= :date_from AND d.created_at <= :date_to) as documents_uploaded
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.status = 'active'
                  ORDER BY activity_count DESC";
        $queryParams = [
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59'
        ];
    } else {
        $query = "SELECT u.username, u.first_name, u.last_name, u.email, u.role, 
                         u.last_login, u.created_at, c.name as company_name,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.created_at >= :date_from AND al.created_at <= :date_to) as activity_count,
                         (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id 
                          AND d.created_at >= :date_from AND d.created_at <= :date_to) as documents_uploaded
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.status = 'active' AND u.company_id = :company_id
                  ORDER BY activity_count DESC";
        $queryParams = [
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59',
            'company_id' => $currentUser['company_id']
        ];
    }
    
    $data = fetchAll($query, $queryParams);
    
    $headers = [
        'Usuario',
        'Nombre',
        'Apellido',
        'Email',
        'Rol',
        'Empresa',
        'Último Acceso',
        'Fecha Registro',
        'Actividades',
        'Documentos Subidos'
    ];
    
    $rows = [];
    foreach ($data as $row) {
        $rows[] = [
            $row['username'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['role'],
            $row['company_name'],
            $row['last_login'] ? date('d/m/Y H:i', strtotime($row['last_login'])) : 'Nunca',
            date('d/m/Y', strtotime($row['created_at'])),
            $row['activity_count'],
            $row['documents_uploaded']
        ];
    }
    
    return ['headers' => $headers, 'rows' => $rows, 'filename' => 'reporte_usuarios_' . date('Y-m-d')];
}

// Función para exportar reportes de operaciones
function exportOperationsReport($currentUser, $params, $format) {
    $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
    $dateTo = $params['date_to'] ?? date('Y-m-d');
    $operation = $params['operation'] ?? '';
    
    $whereConditions = [];
    $queryParams = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];
    
    $whereConditions[] = "al.created_at >= :date_from";
    $whereConditions[] = "al.created_at <= :date_to";
    
    if (!empty($operation)) {
        $whereConditions[] = "al.action = :operation";
        $queryParams['operation'] = $operation;
    }
    
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "u.company_id = :company_id";
        $queryParams['company_id'] = $currentUser['company_id'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "SELECT al.created_at, u.first_name, u.last_name, u.username, c.name as company_name,
                     al.action, al.description, al.ip_address, al.table_name, al.record_id
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              LEFT JOIN companies c ON u.company_id = c.id
              WHERE $whereClause
              ORDER BY al.created_at DESC
              LIMIT 1000";
    
    $data = fetchAll($query, $queryParams);
    
    $headers = [
        'Fecha/Hora',
        'Usuario',
        'Empresa',
        'Operación',
        'Tabla',
        'Registro ID',
        'Descripción',
        'IP'
    ];
    
    $rows = [];
    foreach ($data as $row) {
        $rows[] = [
            date('d/m/Y H:i:s', strtotime($row['created_at'])),
            $row['first_name'] . ' ' . $row['last_name'] . ' (@' . $row['username'] . ')',
            $row['company_name'],
            $row['action'],
            $row['table_name'],
            $row['record_id'],
            $row['description'],
            $row['ip_address']
        ];
    }
    
    return ['headers' => $headers, 'rows' => $rows, 'filename' => 'reporte_operaciones_' . date('Y-m-d')];
}

// Función para exportar reportes de documentos
function exportDocumentsReport($currentUser, $params, $format) {
    $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $params['date_to'] ?? date('Y-m-d');
    $companyId = $params['company_id'] ?? '';
    $documentType = $params['document_type'] ?? '';
    
    $whereConditions = [];
    $queryParams = [
        'date_from' => $dateFrom . ' 00:00:00',
        'date_to' => $dateTo . ' 23:59:59'
    ];
    
    $whereConditions[] = "d.created_at >= :date_from";
    $whereConditions[] = "d.created_at <= :date_to";
    $whereConditions[] = "d.status = 'active'";
    
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "d.company_id = :user_company_id";
        $queryParams['user_company_id'] = $currentUser['company_id'];
    } elseif (!empty($companyId)) {
        $whereConditions[] = "d.company_id = :company_id";
        $queryParams['company_id'] = $companyId;
    }
    
    if (!empty($documentType)) {
        $whereConditions[] = "dt.name = :document_type";
        $queryParams['document_type'] = $documentType;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "SELECT d.name as document_name, dt.name as document_type, c.name as company_name,
                     u.first_name, u.last_name, u.username, d.file_size, d.created_at, d.description,
                     (SELECT COUNT(*) FROM activity_logs al WHERE al.record_id = d.id AND al.action = 'download') as download_count,
                     (SELECT COUNT(*) FROM activity_logs al WHERE al.record_id = d.id AND al.action = 'view') as view_count
              FROM documents d
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              LEFT JOIN companies c ON d.company_id = c.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE $whereClause
              ORDER BY d.created_at DESC
              LIMIT 1000";
    
    $data = fetchAll($query, $queryParams);
    
    $headers = [
        'Documento',
        'Tipo',
        'Empresa',
        'Usuario',
        'Tamaño (bytes)',
        'Fecha Subida',
        'Descripción',
        'Descargas',
        'Visualizaciones'
    ];
    
    $rows = [];
    foreach ($data as $row) {
        $rows[] = [
            $row['document_name'],
            $row['document_type'],
            $row['company_name'],
            $row['first_name'] . ' ' . $row['last_name'] . ' (@' . $row['username'] . ')',
            $row['file_size'],
            date('d/m/Y H:i', strtotime($row['created_at'])),
            $row['description'],
            $row['download_count'],
            $row['view_count']
        ];
    }
    
    return ['headers' => $headers, 'rows' => $rows, 'filename' => 'reporte_documentos_' . date('Y-m-d')];
}

// Función para generar CSV
function generateCSV($headers, $rows, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, $headers, ';');
    
    // Data rows
    foreach ($rows as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit();
}

// Función para generar Excel (HTML que Excel puede interpretar)
function generateExcel($headers, $rows, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<meta name="ProgId" content="Excel.Sheet">';
    echo '<meta name="Generator" content="Microsoft Excel 11">';
    echo '<style>';
    echo 'table { border-collapse: collapse; }';
    echo 'th, td { border: 1px solid #ccc; padding: 5px; text-align: left; }';
    echo 'th { background-color: #8B4513; color: white; font-weight: bold; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<table>';
    
    // Headers
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';
    
    // Data rows
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}

// Función para generar PDF básico
function generatePDF($headers, $rows, $filename) {
    // Para una implementación completa de PDF, se necesitaría una librería como TCPDF o FPDF
    // Por ahora, generamos HTML que se puede imprimir como PDF
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>';
    echo '<html lang="es">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>' . $filename . '</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
    echo 'h1 { color: #8B4513; text-align: center; margin-bottom: 30px; }';
    echo 'table { width: 100%; border-collapse: collapse; margin: 20px 0; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }';
    echo 'th { background-color: #8B4513; color: white; font-weight: bold; }';
    echo 'tr:nth-child(even) { background-color: #f9f9f9; }';
    echo '.header-info { margin-bottom: 20px; }';
    echo '@media print { body { margin: 0; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h1>Reporte DMS2</h1>';
    echo '<div class="header-info">';
    echo '<strong>Generado:</strong> ' . date('d/m/Y H:i:s') . '<br>';
    echo '<strong>Usuario:</strong> ' . htmlspecialchars($GLOBALS['currentUser']['first_name'] . ' ' . $GLOBALS['currentUser']['last_name']) . '<br>';
    echo '<strong>Total registros:</strong> ' . count($rows);
    echo '</div>';
    
    echo '<table>';
    
    // Headers
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';
    
    // Data rows (limitar a 1000 para PDF)
    $limitedRows = array_slice($rows, 0, 1000);
    foreach ($limitedRows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    
    if (count($rows) > 1000) {
        echo '<p><em>Nota: Se muestran solo los primeros 1000 registros. Para ver todos los datos, use exportación CSV o Excel.</em></p>';
    }
    
    echo '<script>';
    echo 'window.onload = function() { window.print(); };';
    echo '</script>';
    
    echo '</body>';
    echo '</html>';
    exit();
}

// Validar parámetros
if (!in_array($format, ['csv', 'excel', 'pdf'])) {
    die('Formato no válido');
}

if (!in_array($type, ['activity_log', 'user_reports', 'operations_report', 'documents_report'])) {
    die('Tipo de reporte no válido');
}

// Obtener datos según el tipo de reporte
$exportData = [];

try {
    switch ($type) {
        case 'activity_log':
            $exportData = exportActivityLog($currentUser, $_GET, $format);
            break;
        case 'user_reports':
            $exportData = exportUserReports($currentUser, $_GET, $format);
            break;
        case 'operations_report':
            $exportData = exportOperationsReport($currentUser, $_GET, $format);
            break;
        case 'documents_report':
            $exportData = exportDocumentsReport($currentUser, $_GET, $format);
            break;
    }
    
    // Registrar actividad de exportación
    logActivity($currentUser['id'], 'export_report', 'reports', null, 
               "Usuario exportó reporte: $type en formato $format");
    
    // Generar archivo según el formato
    switch ($format) {
        case 'csv':
            generateCSV($exportData['headers'], $exportData['rows'], $exportData['filename']);
            break;
        case 'excel':
            generateExcel($exportData['headers'], $exportData['rows'], $exportData['filename']);
            break;
        case 'pdf':
            generatePDF($exportData['headers'], $exportData['rows'], $exportData['filename']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error en exportación: " . $e->getMessage());
    
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>';
    echo '<html><head><title>Error de Exportación</title></head><body>';
    echo '<h1>Error de Exportación</h1>';
    echo '<p>Ocurrió un error al generar el reporte. Por favor, intente nuevamente.</p>';
    echo '<p><a href="javascript:history.back()">Volver</a></p>';
    echo '</body></html>';
    exit();
}
?>