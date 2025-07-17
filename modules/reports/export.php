<?php
// modules/reports/export.php
// Sistema de exportación de reportes con DomPDF - DMS2 (VERSIÓN FINAL)

require_once '../../config/session.php';
require_once '../../config/database.php';

// Cargar DomPDF
require_once '../../vendor/autoload.php'; // Si usas Composer

use Dompdf\Dompdf;
use Dompdf\Options;

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de exportación
$format = $_GET['format'] ?? 'csv';
$type = $_GET['type'] ?? 'activity_log';
$forceDownload = isset($_GET['download']) && $_GET['download'] == '1';

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

// Función para exportar log de actividades
function exportActivityLog($currentUser, $params, $format) {
    $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $params['date_to'] ?? date('Y-m-d');
    $userId = $params['user_id'] ?? '';
    $action = $params['action'] ?? '';
    
    $whereConditions = [];
    $queryParams = [];
    
    $whereConditions[] = "al.created_at >= ?";
    $whereConditions[] = "al.created_at <= ?";
    $queryParams[] = $dateFrom . ' 00:00:00';
    $queryParams[] = $dateTo . ' 23:59:59';
    
    if (!empty($userId)) {
        $whereConditions[] = "al.user_id = ?";
        $queryParams[] = $userId;
    }
    
    if (!empty($action)) {
        $whereConditions[] = "al.action = ?";
        $queryParams[] = $action;
    }
    
    if ($currentUser['role'] !== 'admin') {
        $whereConditions[] = "u.company_id = ?";
        $queryParams[] = $currentUser['company_id'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "SELECT al.created_at, u.first_name, u.last_name, u.username, 
                     c.name as company_name, al.action, al.description
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              LEFT JOIN companies c ON u.company_id = c.id
              WHERE $whereClause
              ORDER BY al.created_at DESC";
    
    try {
        $data = fetchAll($query, $queryParams);
    } catch (Exception $e) {
        error_log("Error en consulta de exportación: " . $e->getMessage());
        $data = [];
    }
    
    $headers = [
        'Fecha/Hora',
        'Nombre Completo',
        'Usuario',
        'Empresa',
        'Descripción'
    ];
    
    $rows = [];
    foreach ($data as $row) {
        $rows[] = [
            date('d/m/Y H:i:s', strtotime($row['created_at'])),
            trim($row['first_name'] . ' ' . $row['last_name']),
            $row['username'],
            $row['company_name'] ?? 'N/A',
            $row['description'] ?? 'Sin descripción'
        ];
    }
    
    return [
        'headers' => $headers, 
        'rows' => $rows, 
        'filename' => 'log_actividades_' . date('Y-m-d_H-i-s'),
        'title' => 'Actividades del Sistema'
    ];
}

// Función para generar CSV
function generateCSV($headers, $rows, $filename) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Escribir headers
    fputcsv($output, $headers, ';');
    
    // Escribir datos
    foreach ($rows as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit();
}

// Función para generar Excel
function generateExcel($headers, $rows, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // BOM para UTF-8
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Actividades</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '</head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Headers
    echo '<tr style="background-color: #667eea; color: white; font-weight: bold;">';
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

// Función para generar PDF con DomPDF (VERSIÓN FINAL OPTIMIZADA)
function generatePDF($headers, $rows, $filename, $title, $currentUser, $forceDownload = false) {
    
    if ($forceDownload) {
        // Configurar DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Template HTML para PDF optimizado SIN GRADIENTES (compatible con DomPDF)
        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        @page {
            margin: 2cm 1.5cm;
            size: A4 portrait;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #2c3e50;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 3px solid #6d534eff;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #2c3e50;
            margin: 0 0 8px 0;
            font-size: 24px;
            font-weight: bold;
        }
        
        .header .subtitle {
            color: #6c757d;
            margin: 0;
            font-size: 14px;
            font-weight: 500;
        }
        
        .report-info {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 25px;
            border-left: 4px solid #6d534eff;
            border-radius: 8px;
            font-size: 10px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
        }
        
        .info-left, .info-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        
        .info-right {
            text-align: right;
        }
        
        .report-info strong {
            color: #495057;
            font-weight: bold;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        th, td {
            border: 1px solid #e1e5e9;
            padding: 8px 6px;
            text-align: left;
            vertical-align: top;
        }
        
        th {
            background-color: #4e342e;
            color: white;
            font-weight: 600;
            text-align: center;
            font-size: 10px;
            padding: 12px 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tr:nth-child(odd) {
            background-color: white;
        }
        
        .col-fecha {
            width: 15%;
            font-weight: bold;
            color: #495057;
            font-size: 8px;
        }
        
        .col-nombre {
            width: 25%;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .col-usuario {
            width: 15%;
            font-style: italic;
            color: #6c757d;
            font-size: 8px;
        }
        
        .col-empresa {
            width: 20%;
            color: #495057;
            font-weight: 500;
        }
        
        .col-descripcion {
            width: 25%;
            word-wrap: break-word;
            font-size: 8px;
            line-height: 1.3;
        }
        
        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 9px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($title) . '</h1>
        <div class="subtitle">Sistema de Gestión Documental - DMS2</div>
    </div>
    
    <div class="report-info">
        <div class="info-grid">
            <div class="info-left">
                <strong>Generado por:</strong> ' . htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) . '<br>
                <strong>Usuario:</strong> @' . htmlspecialchars($currentUser['username']) . '<br>
                <strong>Fecha:</strong> ' . date('d/m/Y H:i:s') . '
            </div>
            <div class="info-right">
                <strong>Total registros:</strong> ' . number_format(count($rows)) . '<br>
                <strong>Formato:</strong> PDF<br>
                <strong>Sistema:</strong> DMS2 v1.0
            </div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>';
        
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        
        $html .= '</tr>
        </thead>
        <tbody>';
        
        foreach ($rows as $index => $row) {
            $html .= '<tr>';
            $html .= '<td class="col-fecha">' . htmlspecialchars($row[0]) . '</td>';
            $html .= '<td class="col-nombre">' . htmlspecialchars($row[1]) . '</td>';
            $html .= '<td class="col-usuario">@' . htmlspecialchars($row[2]) . '</td>';
            $html .= '<td class="col-empresa">' . htmlspecialchars($row[3]) . '</td>';
            
            // Truncar descripción si es muy larga
            $descripcion = $row[4];
            if (strlen($descripcion) > 80) {
                $descripcion = substr($descripcion, 0, 80) . '...';
            }
            $html .= '<td class="col-descripcion">' . htmlspecialchars($descripcion) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>
    </table>
    
    <div class="footer">
        <strong>Sistema DMS2 - Gestión Documental</strong><br>
        Documento generado automáticamente el ' . date('d/m/Y') . ' a las ' . date('H:i:s') . '<br>
        Este documento contiene información confidencial del sistema.
    </div>
    
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("Arial");
            $pdf->page_text(520, 820, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, 8, array(0.5, 0.5, 0.5));
        }
    </script>
</body>
</html>';
        
        // Cargar HTML en DomPDF
        $dompdf->loadHtml($html);
        
        // Configurar papel y orientación
        $dompdf->setPaper('A4', 'portrait');
        
        // Renderizar PDF
        $dompdf->render();
        
        // Descargar PDF
        $dompdf->stream($filename . '.pdf', array(
            'Attachment' => 1,
            'compress' => 1
        ));
        
        exit();
    }
    
    // Vista previa (CON GRADIENTES para el navegador)
    header('Content-Type: text/html; charset=UTF-8');
    
    // Verificar si viene del modal
    $isModal = isset($_GET['modal']) && $_GET['modal'] == '1';
    
    $htmlContent = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body { 
            font-family: "Segoe UI", Arial, sans-serif; 
            margin: 0;
            padding: 12px;
            font-size: 11px;
            color: #2c3e50;
            line-height: 1.4;
            background-color: #ffffff;
        }
        
        .controls {
            position: fixed;
            top: 12px;
            right: 12px;
            background: white;
            padding: 0;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            z-index: 1000;
            display: flex;
            gap: 2px;
            border: 1px solid #e1e5e9;
        }
        
        .controls button {
            background: linear-gradient(135deg, #4e342e 0%, #6d534eff 100%);
            color: white;
            border: none;
            padding: 12px 18px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            min-width: 130px;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .controls button::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .controls button:hover::before {
            left: 100%;
        }
        
        .controls button:first-child {
            border-radius: 10px 0 0 10px;
        }
        
        .controls button:nth-child(2) {
            border-radius: 0 10px 10px 0;
            background: linear-gradient(135deg, #43a047 0%, #388e3c 100%);
        }
        
        .controls button:last-child {
            border-radius: 0 10px 10px 0;
            background: linear-gradient(135deg, #78909c 0%, #546e7a 100%);
        }
        
        .controls button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .controls button:active {
            transform: translateY(0);
        }
        
        .pdf-container {
            max-width: 100%;
            margin: 50px auto 20px auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #6d534eff;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 26px;
            font-weight: 700;
        }
        
        .header .subtitle {
            color: #6c757d;
            margin: 8px 0;
            font-size: 16px;
            font-weight: 500;
        }
        
        .report-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            font-size: 12px;
        }
        
        .report-info strong {
            color: #495057;
            font-weight: 600;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 10px;
            margin-top: 15px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        th, td { 
            border: 1px solid #e1e5e9; 
            padding: 10px 8px; 
            text-align: left; 
            vertical-align: top;
            word-wrap: break-word;
        }
        
        th { 
            background: linear-gradient(135deg, #4e342e 0%, #6d534eff 100%);
            color: white; 
            font-weight: 600;
            font-size: 11px;
            text-align: center;
            padding: 15px 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        tr:nth-child(even) { 
            background-color: #f8f9fa; 
        }
        
        tr:nth-child(odd) { 
            background-color: white; 
        }
        
        tr:hover {
            background-color: #e3f2fd;
            transform: scale(1.001);
            transition: all 0.2s ease;
        }
        
        .col-fecha {
            width: 18%;
            font-weight: 600;
            color: #495057;
            font-size: 9px;
            white-space: nowrap;
        }
        
        .col-nombre {
            width: 25%;
            font-weight: 600;
            font-size: 10px;
            color: #2c3e50;
        }
        
        .col-usuario {
            width: 15%;
            font-style: italic;
            color: #6c757d;
            font-size: 9px;
        }
        
        .col-empresa {
            width: 20%;
            color: #495057;
            font-size: 10px;
            font-weight: 500;
        }
        
        .col-descripcion {
            width: 22%;
            font-size: 9px;
            line-height: 1.4;
            word-break: break-word;
        }
        
        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 11px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
        }
        
        /* Estilos especiales para impresión */
        @media print {
            .controls { display: none !important; }
            .pdf-container { margin: 0; padding: 20px; box-shadow: none; border: none; }
            body { background: white; }
            th { 
                background: #667eea !important; 
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .report-info {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
    </style>
</head>
<body>';
    
    // Controles dinámicos según modal
    if (!$isModal) {
        $htmlContent .= '
    <div class="controls">
        <button onclick="window.print()" title="Imprimir documento">
            <span></span> Imprimir
        </button>
        <button onclick="descargarPDFReal()" title="Descargar archivo PDF">
            <span></span> Descargar PDF
        </button>
        <button onclick="window.history.back()" title="Volver al log de actividades">
            <span>←</span> Volver
        </button>
    </div>';
    } else {
        $htmlContent .= '
    <div class="controls" style="position: relative; top: 8px; right: 8px; margin-bottom: 18px;">
        <button onclick="window.print()" title="Imprimir documento">
            <span></span> Imprimir
        </button>
        <button onclick="descargarPDFReal()" title="Descargar archivo PDF">
            <span></span> Descargar PDF
        </button>
    </div>';
    }
    
    $htmlContent .= '
    <div class="pdf-container">
        <div class="header">
            <h1>' . htmlspecialchars($title) . '</h1>
            <div class="subtitle">Sistema de Gestión Documental - DMS2</div>
        </div>
        
        <div class="report-info">
            <div>
                <strong>Generado por:</strong> ' . htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) . '<br>
                <strong>Usuario:</strong> @' . htmlspecialchars($currentUser['username']) . '<br>
                <strong>Fecha:</strong> ' . date('d/m/Y H:i:s') . '
            </div>
            <div>
                <strong>Total registros:</strong> ' . number_format(count($rows)) . '<br>
                <strong>Formato:</strong> PDF<br>
                <strong>Sistema:</strong> DMS2 v1.0
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Fecha/Hora</th>
                    <th>Nombre Completo</th>
                    <th>Usuario</th>
                    <th>Empresa</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($rows as $row) {
        $htmlContent .= '<tr>';
        $htmlContent .= '<td class="col-fecha">' . htmlspecialchars($row[0]) . '</td>';
        $htmlContent .= '<td class="col-nombre">' . htmlspecialchars($row[1]) . '</td>';
        $htmlContent .= '<td class="col-usuario">@' . htmlspecialchars($row[2]) . '</td>';
        $htmlContent .= '<td class="col-empresa">' . htmlspecialchars($row[3]) . '</td>';
        
        // Descripción truncada para el modal
        $descripcion = $row[4];
        if (strlen($descripcion) > 100) {
            $descripcion = substr($descripcion, 0, 100) . '...';
        }
        $htmlContent .= '<td class="col-descripcion">' . htmlspecialchars($descripcion) . '</td>';
        $htmlContent .= '</tr>';
    }
    
    $htmlContent .= '</tbody>
        </table>
        
        <div class="footer">
            <strong>Sistema DMS2 - Gestión Documental</strong><br>
            Documento generado el ' . date('d/m/Y') . ' a las ' . date('H:i:s') . '<br>
            Este documento contiene información confidencial del sistema.
        </div>
    </div>
    
    <script type="text/javascript">
        function descargarPDFReal() {
            var urlActual = window.location.href;
            var nuevaUrl = urlActual + (urlActual.includes("?") ? "&" : "?") + "download=1";
            ';
            
    if ($isModal) {
        $htmlContent .= 'window.open(nuevaUrl, "_blank");';
    } else {
        $htmlContent .= 'window.location.href = nuevaUrl;';
    }
    
    $htmlContent .= '
        }';
        
    // JavaScript específico para modal
    if ($isModal) {
        $htmlContent .= '
        
        document.addEventListener("DOMContentLoaded", function() {
            document.body.style.margin = "0";
            document.body.style.padding = "10px";
            document.body.style.backgroundColor = "#ffffff";
            
            const container = document.querySelector(".pdf-container");
            if (container) {
                container.style.margin = "0";
                container.style.maxWidth = "100%";
                container.style.boxShadow = "none";
                container.style.border = "none";
                container.style.padding = "20px";
            }
            
            // Ajustar el tamaño del modal para que sea más compacto
            const modal = window.parent.document.getElementById("pdfModal");
            if (modal) {
                const modalContent = modal.querySelector(".pdf-modal-content");
                if (modalContent) {
                    modalContent.style.width = "75%";
                    modalContent.style.height = "75%";
                    modalContent.style.maxWidth = "900px";
                    modalContent.style.maxHeight = "600px";
                }
            }
        });';
    }
    
    $htmlContent .= '
    </script>
</body>
</html>';
    
    echo $htmlContent;
    exit();
}

// Registrar actividad de exportación
try {
    logActivity($currentUser['id'], 'export_' . $format, 'reports', null, "Usuario exportó reporte de {$type} en formato {$format}");
} catch (Exception $e) {
    error_log("Error registrando actividad: " . $e->getMessage());
}

// Procesar exportación según el tipo
try {
    switch ($type) {
        case 'activity_log':
            $exportData = exportActivityLog($currentUser, $_GET, $format);
            break;
        
        default:
            throw new Exception('Tipo de reporte no válido: ' . $type);
    }
    
    // Verificar que hay datos para exportar
    if (empty($exportData['rows'])) {
        throw new Exception('No hay datos para exportar con los filtros seleccionados');
    }
    
    // Generar archivo según formato
    switch ($format) {
        case 'csv':
            generateCSV($exportData['headers'], $exportData['rows'], $exportData['filename']);
            break;
            
        case 'excel':
            generateExcel($exportData['headers'], $exportData['rows'], $exportData['filename']);
            break;
            
        case 'pdf':
            generatePDF($exportData['headers'], $exportData['rows'], $exportData['filename'], $exportData['title'], $currentUser, $forceDownload);
            break;
            
        default:
            throw new Exception('Formato de exportación no válido: ' . $format);
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en exportación: " . $e->getMessage());
    error_log("Parámetros: " . print_r($_GET, true));
    
    // Mostrar error al usuario con diseño mejorado
    http_response_code(500);
    echo '<!DOCTYPE html>';
    echo '<html lang="es">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Error de Exportación - DMS2</title>';
    echo '<style>';
    echo 'body { font-family: "Segoe UI", Arial, sans-serif; margin: 40px; color: #2c3e50; text-align: center; background: #f8f9fa; }';
    echo '.error-container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }';
    echo '.error-box { background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); color: #721c24; padding: 25px; border-radius: 10px; margin: 25px 0; border: 1px solid #f5c6cb; }';
    echo '.btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px; font-weight: 500; transition: all 0.3s ease; }';
    echo '.btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }';
    echo '.btn.secondary { background: linear-gradient(135deg, #43a047 0%, #388e3c 100%); }';
    echo '.error-icon { font-size: 48px; margin-bottom: 20px; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="error-container">';
    echo '<div class="error-icon">❌</div>';
    echo '<h1 style="color: #dc3545; margin-bottom: 20px;">Error en la Exportación</h1>';
    echo '<div class="error-box">';
    echo '<h3>Se produjo un error al generar el reporte:</h3>';
    echo '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>';
    echo '</div>';
    echo '<div>';
    echo '<a href="javascript:history.back()" class="btn">← Volver</a>';
    echo '<a href="activity_log.php" class="btn secondary">🏠 Ir al Log de Actividades</a>';
    echo '</div>';
    echo '<div style="margin-top: 30px; font-size: 12px; color: #6c757d;">';
    echo 'Si el problema persiste, contacte al administrador del sistema.';
    echo '</div>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
    exit();
}
?>