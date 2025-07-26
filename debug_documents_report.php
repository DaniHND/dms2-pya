<?php
// debug_documents_report.php
// Script para diagnosticar específicamente el problema del reporte de documentos

require_once 'config/session.php';
require_once 'config/database.php';

// Verificar sesión
SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

echo "<h1>🔍 DEBUG: Reporte de Documentos - DMS2</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .query-box { background: #f8f8f8; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .params { background: #e8f4fd; padding: 10px; border-radius: 5px; margin: 5px 0; }
</style>";

// Simular los mismos parámetros que en el reporte
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$companyId = $_GET['company_id'] ?? '';
$documentType = $_GET['document_type'] ?? '';

echo "<div class='section'>";
echo "<h2>📋 Información de Debug</h2>";
echo "<strong>Usuario actual:</strong> {$currentUser['username']} (ID: {$currentUser['id']})<br>";
echo "<strong>Rol:</strong> {$currentUser['role']}<br>";
echo "<strong>Empresa:</strong> {$currentUser['company_id']}<br>";
echo "<strong>Fecha desde:</strong> $dateFrom<br>";
echo "<strong>Fecha hasta:</strong> $dateTo<br>";
echo "<strong>Empresa seleccionada:</strong> " . ($companyId ?: 'Ninguna') . "<br>";
echo "<strong>Tipo documento:</strong> " . ($documentType ?: 'Ninguno') . "<br>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>1. 🗄️ Verificar Datos en la Base</h2>";

// Contar todos los documentos
$query = "SELECT COUNT(*) as total FROM documents";
$result = fetchOne($query);
echo "<span class='info'>📄 Total documentos en la BD: " . $result['total'] . "</span><br>";

// Contar documentos activos
$query = "SELECT COUNT(*) as total FROM documents WHERE status = 'active'";
$result = fetchOne($query);
echo "<span class='info'>✅ Documentos activos: " . $result['total'] . "</span><br>";

// Mostrar fechas de documentos
$query = "SELECT MIN(created_at) as min_date, MAX(created_at) as max_date FROM documents WHERE status = 'active'";
$result = fetchOne($query);
echo "<span class='info'>📅 Rango de fechas en BD: " . ($result['min_date'] ?? 'N/A') . " a " . ($result['max_date'] ?? 'N/A') . "</span><br>";

// Documentos por empresa
$query = "SELECT company_id, COUNT(*) as total FROM documents WHERE status = 'active' GROUP BY company_id";
$results = fetchAll($query);
echo "<span class='info'>🏢 Documentos por empresa:</span><br>";
if ($results) {
    foreach ($results as $row) {
        echo "&nbsp;&nbsp;- Empresa ID {$row['company_id']}: {$row['total']} documentos<br>";
    }
} else {
    echo "&nbsp;&nbsp;- Sin datos<br>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>2. 🧪 Probar Consulta de Estadísticas</h2>";

// Simular exactamente la función getDocumentStats
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

$query = "SELECT COUNT(*) as total,
                 SUM(d.file_size) as total_size,
                 AVG(d.file_size) as avg_size
          FROM documents d
          LEFT JOIN document_types dt ON d.document_type_id = dt.id
          WHERE $whereClause";

echo "<div class='query-box'>";
echo "<strong>Consulta SQL:</strong><br>";
echo htmlspecialchars($query);
echo "</div>";

echo "<div class='params'>";
echo "<strong>Parámetros:</strong><br>";
foreach ($params as $key => $value) {
    echo "- $key: " . htmlspecialchars($value) . "<br>";
}
echo "</div>";

try {
    $result = fetchOne($query, $params);
    echo "<span class='success'>✅ Consulta ejecutada correctamente</span><br>";
    echo "<strong>Resultados:</strong><br>";
    echo "- Total documentos (stats): " . ($result['total'] ?? 0) . "<br>";
    echo "- Tamaño total: " . ($result['total_size'] ?? 0) . " bytes<br>";
    echo "- Tamaño promedio: " . ($result['avg_size'] ?? 0) . " bytes<br>";
    
    if (($result['total'] ?? 0) == 0) {
        echo "<span class='error'>❌ PROBLEMA: La consulta no devuelve documentos</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error en consulta: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>3. 🔍 Análisis Detallado del Problema</h2>";

// Verificar paso a paso cada condición
echo "<h3>Verificando cada filtro por separado:</h3>";

// 1. Solo por fechas
$testQuery = "SELECT COUNT(*) as total FROM documents d WHERE d.created_at >= :date_from AND d.created_at <= :date_to";
$testParams = [
    'date_from' => $dateFrom . ' 00:00:00',
    'date_to' => $dateTo . ' 23:59:59'
];
$result = fetchOne($testQuery, $testParams);
echo "<span class='info'>1️⃣ Solo filtro de fechas: " . ($result['total'] ?? 0) . " documentos</span><br>";

// 2. Fechas + status
$testQuery = "SELECT COUNT(*) as total FROM documents d WHERE d.created_at >= :date_from AND d.created_at <= :date_to AND d.status = 'active'";
$result = fetchOne($testQuery, $testParams);
echo "<span class='info'>2️⃣ Fechas + status activo: " . ($result['total'] ?? 0) . " documentos</span><br>";

// 3. Si no es admin, agregar filtro de empresa
if ($currentUser['role'] !== 'admin') {
    $testQuery = "SELECT COUNT(*) as total FROM documents d WHERE d.created_at >= :date_from AND d.created_at <= :date_to AND d.status = 'active' AND d.company_id = :company_id";
    $testParams['company_id'] = $currentUser['company_id'];
    $result = fetchOne($testQuery, $testParams);
    echo "<span class='info'>3️⃣ Fechas + status + empresa ({$currentUser['company_id']}): " . ($result['total'] ?? 0) . " documentos</span><br>";
}

// 4. Verificar si hay documentos en esa empresa específicamente
if ($currentUser['role'] !== 'admin') {
    $testQuery = "SELECT COUNT(*) as total FROM documents WHERE company_id = :company_id AND status = 'active'";
    $testParams = ['company_id' => $currentUser['company_id']];
    $result = fetchOne($testQuery, $testParams);
    echo "<span class='info'>4️⃣ Todos los documentos de tu empresa: " . ($result['total'] ?? 0) . " documentos</span><br>";
    
    if (($result['total'] ?? 0) == 0) {
        echo "<span class='warning'>⚠️ CAUSA PROBABLE: No hay documentos en tu empresa (ID: {$currentUser['company_id']})</span><br>";
    }
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>4. 📊 Probar Consulta de Lista de Documentos</h2>";

// Probar la consulta de getDocumentsList
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
          LIMIT 10";

echo "<div class='query-box'>";
echo "<strong>Consulta de lista:</strong><br>";
echo htmlspecialchars($query);
echo "</div>";

try {
    $documents = fetchAll($query, $params);
    echo "<span class='success'>✅ Consulta de lista ejecutada</span><br>";
    echo "<strong>Total documentos (array): " . count($documents) . "</strong><br>";
    echo "<strong>Array documentos válido: " . (is_array($documents) ? 'SÍ' : 'NO') . "</strong><br>";
    
    if (count($documents) > 0) {
        echo "<h4>Primeros documentos encontrados:</h4>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Empresa</th><th>Fecha</th></tr>";
        foreach (array_slice($documents, 0, 5) as $doc) {
            echo "<tr>";
            echo "<td>" . $doc['id'] . "</td>";
            echo "<td>" . htmlspecialchars($doc['name']) . "</td>";
            echo "<td>" . htmlspecialchars($doc['company_name'] ?? 'Sin empresa') . "</td>";
            echo "<td>" . $doc['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<span class='error'>❌ No se encontraron documentos con los filtros aplicados</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error en consulta de lista: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>5. 🔧 Verificar Filtros Disponibles</h2>";

// Verificar tipos de documento
$query = "SELECT name FROM document_types WHERE status = 'active' ORDER BY name";
$types = fetchAll($query);
echo "<strong>Tipos disponibles: " . count($types) . "</strong><br>";
if ($types) {
    echo "Tipos: ";
    foreach ($types as $type) {
        echo htmlspecialchars($type['name']) . ", ";
    }
    echo "<br>";
} else {
    echo "<span class='warning'>⚠️ No hay tipos de documento configurados</span><br>";
}

// Verificar empresas (solo para admin)
if ($currentUser['role'] === 'admin') {
    $query = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
    $companies = fetchAll($query);
    echo "<strong>Empresas disponibles: " . count($companies) . "</strong><br>";
    if ($companies) {
        echo "Empresas: ";
        foreach ($companies as $company) {
            echo htmlspecialchars($company['name']) . " (ID: {$company['id']}), ";
        }
        echo "<br>";
    }
} else {
    echo "<strong>Empresas disponibles: 1</strong> (Solo tu empresa)<br>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>6. 💡 Diagnóstico y Soluciones</h2>";

$problems = [];
$solutions = [];

// Verificar si hay documentos en general
$query = "SELECT COUNT(*) as total FROM documents WHERE status = 'active'";
$result = fetchOne($query);
if (($result['total'] ?? 0) == 0) {
    $problems[] = "No hay documentos activos en el sistema";
    $solutions[] = "Crear documentos de prueba o verificar que los existentes tengan status = 'active'";
}

// Verificar si hay documentos en el rango de fechas
$query = "SELECT COUNT(*) as total FROM documents WHERE status = 'active' AND created_at >= :date_from AND created_at <= :date_to";
$params = [
    'date_from' => $dateFrom . ' 00:00:00',
    'date_to' => $dateTo . ' 23:59:59'
];
$result = fetchOne($query, $params);
if (($result['total'] ?? 0) == 0) {
    $problems[] = "No hay documentos en el rango de fechas seleccionado ($dateFrom a $dateTo)";
    $solutions[] = "Cambiar el rango de fechas o crear documentos en fechas más recientes";
}

// Verificar documentos de la empresa del usuario
if ($currentUser['role'] !== 'admin') {
    $query = "SELECT COUNT(*) as total FROM documents WHERE status = 'active' AND company_id = :company_id";
    $params = ['company_id' => $currentUser['company_id']];
    $result = fetchOne($query, $params);
    if (($result['total'] ?? 0) == 0) {
        $problems[] = "No hay documentos en tu empresa (ID: {$currentUser['company_id']})";
        $solutions[] = "Crear documentos asociados a tu empresa o verificar que tu usuario esté en la empresa correcta";
    }
}

if (empty($problems)) {
    echo "<span class='success'>✅ No se detectaron problemas obvios</span><br>";
    echo "<p>El problema podría estar en:</p>";
    echo "<ul>";
    echo "<li>Formato de fechas diferente al esperado</li>";
    echo "<li>Problemas con los LEFT JOIN</li>";
    echo "<li>Diferencias entre parámetros nombrados vs posicionales</li>";
    echo "</ul>";
} else {
    echo "<h3>🚨 Problemas detectados:</h3>";
    foreach ($problems as $index => $problem) {
        echo "<span class='error'>" . ($index + 1) . ". " . $problem . "</span><br>";
    }
    
    echo "<h3>💡 Soluciones sugeridas:</h3>";
    foreach ($solutions as $index => $solution) {
        echo "<span class='info'>" . ($index + 1) . ". " . $solution . "</span><br>";
    }
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>7. 🧪 Prueba Rápida de Solución</h2>";

if ($currentUser['role'] !== 'admin') {
    // Para usuarios no admin, mostrar documentos de cualquier fecha de su empresa
    echo "<h3>Documentos de tu empresa (cualquier fecha):</h3>";
    $query = "SELECT d.name, d.created_at, c.name as company_name 
              FROM documents d 
              LEFT JOIN companies c ON d.company_id = c.id 
              WHERE d.status = 'active' AND d.company_id = :company_id 
              ORDER BY d.created_at DESC 
              LIMIT 5";
    $params = ['company_id' => $currentUser['company_id']];
} else {
    // Para admin, mostrar documentos de cualquier fecha
    echo "<h3>Documentos del sistema (cualquier fecha):</h3>";
    $query = "SELECT d.name, d.created_at, c.name as company_name 
              FROM documents d 
              LEFT JOIN companies c ON d.company_id = c.id 
              WHERE d.status = 'active' 
              ORDER BY d.created_at DESC 
              LIMIT 5";
    $params = [];
}

try {
    $docs = fetchAll($query, $params);
    if ($docs) {
        echo "<table>";
        echo "<tr><th>Documento</th><th>Empresa</th><th>Fecha</th></tr>";
        foreach ($docs as $doc) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($doc['name']) . "</td>";
            echo "<td>" . htmlspecialchars($doc['company_name'] ?? 'Sin empresa') . "</td>";
            echo "<td>" . $doc['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<span class='success'>✅ Se encontraron documentos. El problema está en los filtros de fecha.</span><br>";
    } else {
        echo "<span class='error'>❌ No se encontraron documentos. El problema es más profundo.</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>🎯 Resumen Final</h2>";
echo "<p>Este debug te ayuda a identificar exactamente dónde está el problema en el reporte de documentos.</p>";
echo "<p><strong>Próximo paso:</strong> Revisa los resultados arriba y aplica las soluciones sugeridas.</p>";
echo "<p><a href='modules/reports/documents_report.php'>🔄 Volver al Reporte de Documentos</a></p>";
echo "</div>";
?>