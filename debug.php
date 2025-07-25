<?php
// debug_user_reports.php
// Script para diagnosticar problemas en user_reports.php

require_once 'config/session.php';
require_once 'config/database.php';

echo "<h1>Diagnóstico de Reportes de Usuario - DMS2</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .debug-data { background: #f8f8f8; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace; }
</style>";

// 1. Verificar sesión
echo "<div class='section'>";
echo "<h2>1. Verificando sesión del usuario</h2>";
try {
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();
    echo "<span class='success'>✅ Sesión válida</span><br>";
    echo "<span class='info'>Usuario: {$currentUser['username']}</span><br>";
    echo "<span class='info'>Rol: {$currentUser['role']}</span><br>";
    echo "<span class='info'>ID: {$currentUser['id']}</span><br>";
    echo "<span class='info'>Empresa ID: " . ($currentUser['company_id'] ?? 'No definida') . "</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Error de sesión: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    exit();
}
echo "</div>";

// 2. Simular parámetros de user_reports.php
echo "<div class='section'>";
echo "<h2>2. Configurando parámetros de reportes</h2>";

$selectedUserId = $_GET['user_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

echo "<span class='info'>Usuario seleccionado: " . ($selectedUserId ?: 'Todos') . "</span><br>";
echo "<span class='info'>Fecha desde: $dateFrom</span><br>";
echo "<span class='info'>Fecha hasta: $dateTo</span><br>";
echo "</div>";

// 3. Probar función getUsersWithStats
echo "<div class='section'>";
echo "<h2>3. Probando función getUsersWithStats</h2>";

function debugGetUsersWithStats($currentUser, $dateFrom, $dateTo, $selectedUserId = '')
{
    echo "<h3>🔧 Ejecutando consulta getUsersWithStats...</h3>";
    
    if ($currentUser['role'] === 'admin') {
        // Admin puede ver todos los usuarios
        $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                         u.last_login, u.created_at, c.name as company_name,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.created_at >= :date_from AND al.created_at <= :date_to) as activity_count,
                         (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id 
                          AND d.created_at >= :date_from AND d.created_at <= :date_to) as documents_uploaded,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.action = 'download' AND al.created_at >= :date_from AND al.created_at <= :date_to) as downloads_count
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.status = 'active'";
        
        $params = [
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59'
        ];
        
        if ($selectedUserId) {
            $query .= " AND u.id = :user_id";
            $params['user_id'] = $selectedUserId;
        }
        
        echo "<span class='info'>Rol admin: Puede ver todos los usuarios</span><br>";
        
    } else {
        // Usuario normal solo puede ver sus propios datos y de su empresa
        $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                         u.last_login, u.created_at, c.name as company_name,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.created_at >= :date_from AND al.created_at <= :date_to) as activity_count,
                         (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id 
                          AND d.created_at >= :date_from AND d.created_at <= :date_to) as documents_uploaded,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.action = 'download' AND al.created_at >= :date_from AND al.created_at <= :date_to) as downloads_count
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.status = 'active' AND u.company_id = :company_id";
        
        $params = [
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59',
            'company_id' => $currentUser['company_id']
        ];
        
        if ($selectedUserId) {
            $query .= " AND u.id = :user_id";
            $params['user_id'] = $selectedUserId;
        }
        
        echo "<span class='info'>Usuario normal: Solo puede ver datos de su empresa</span><br>";
    }
    
    $query .= " ORDER BY u.first_name, u.last_name";
    
    echo "<div class='debug-data'>";
    echo "<strong>Consulta SQL:</strong><br>";
    echo htmlspecialchars($query);
    echo "<br><br><strong>Parámetros:</strong><br>";
    echo "<pre>" . print_r($params, true) . "</pre>";
    echo "</div>";
    
    try {
        $result = fetchAll($query, $params);
        echo "<span class='success'>✅ Consulta ejecutada exitosamente</span><br>";
        echo "<span class='info'>Usuarios encontrados: " . count($result) . "</span><br>";
        
        if (!empty($result)) {
            echo "<h4>📊 Primeros resultados:</h4>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Empresa</th><th>Actividades</th><th>Documentos</th><th>Descargas</th></tr>";
            
            foreach (array_slice($result, 0, 3) as $user) {
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['username']}</td>";
                echo "<td>{$user['first_name']} {$user['last_name']}</td>";
                echo "<td>" . ($user['company_name'] ?? 'Sin empresa') . "</td>";
                echo "<td>{$user['activity_count']}</td>";
                echo "<td>{$user['documents_uploaded']}</td>";
                echo "<td>{$user['downloads_count']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        return $result;
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error en consulta: " . htmlspecialchars($e->getMessage()) . "</span><br>";
        return [];
    }
}

$users = debugGetUsersWithStats($currentUser, $dateFrom, $dateTo, $selectedUserId);
echo "</div>";

// 4. Verificar datos en activity_logs
echo "<div class='section'>";
echo "<h2>4. Verificando datos en activity_logs</h2>";

try {
    $activityCount = fetchOne("SELECT COUNT(*) as total FROM activity_logs");
    echo "<span class='info'>📊 Total actividades: " . ($activityCount['total'] ?? 0) . "</span><br>";
    
    $recentActivity = fetchAll("SELECT al.*, u.username 
                                FROM activity_logs al 
                                LEFT JOIN users u ON al.user_id = u.id 
                                ORDER BY al.created_at DESC 
                                LIMIT 5");
    
    if ($recentActivity && count($recentActivity) > 0) {
        echo "<h3>📋 Actividades recientes:</h3>";
        echo "<table>";
        echo "<tr><th>Usuario</th><th>Acción</th><th>Fecha</th><th>Detalles</th></tr>";
        foreach ($recentActivity as $activity) {
            echo "<tr>";
            echo "<td>" . ($activity['username'] ?? 'Usuario eliminado') . "</td>";
            echo "<td>{$activity['action']}</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($activity['created_at'])) . "</td>";
            echo "<td>" . ($activity['details'] ?? 'Sin detalles') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<span class='warning'>⚠️ No hay actividades registradas</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error consultando activity_logs: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 5. Verificar datos en documents
echo "<div class='section'>";
echo "<h2>5. Verificando datos en documents</h2>";

try {
    $documentsCount = fetchOne("SELECT COUNT(*) as total FROM documents");
    echo "<span class='info'>📊 Total documentos: " . ($documentsCount['total'] ?? 0) . "</span><br>";
    
    $recentDocs = fetchAll("SELECT d.*, u.username 
                            FROM documents d 
                            LEFT JOIN users u ON d.user_id = u.id 
                            ORDER BY d.created_at DESC 
                            LIMIT 5");
    
    if ($recentDocs && count($recentDocs) > 0) {
        echo "<h3>📄 Documentos recientes:</h3>";
        echo "<table>";
        echo "<tr><th>Nombre</th><th>Usuario</th><th>Fecha</th><th>Tamaño</th></tr>";
        foreach ($recentDocs as $doc) {
            echo "<tr>";
            echo "<td>{$doc['name']}</td>";
            echo "<td>" . ($doc['username'] ?? 'Usuario eliminado') . "</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($doc['created_at'])) . "</td>";
            echo "<td>" . number_format($doc['size'] ?? 0) . " bytes</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<span class='warning'>⚠️ No hay documentos registrados</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error consultando documents: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 6. Probar función getTotalUsers
echo "<div class='section'>";
echo "<h2>6. Probando función getTotalUsers</h2>";

function debugGetTotalUsers($currentUser, $dateFrom, $dateTo, $selectedUserId)
{
    echo "<h3>🔧 Ejecutando función getTotalUsers...</h3>";
    
    if ($currentUser['role'] === 'admin') {
        if ($selectedUserId) {
            echo "<span class='info'>Admin con usuario específico: retornando 1</span><br>";
            return 1;
        }
        $query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
        $params = [];
        echo "<span class='info'>Admin sin filtro: contando todos los usuarios activos</span><br>";
    } else {
        echo "<span class='info'>Usuario normal: retornando 1 (solo sus datos)</span><br>";
        return 1;
    }
    
    try {
        $result = fetchOne($query, $params);
        $total = $result['total'] ?? 0;
        echo "<span class='success'>✅ Total de usuarios: $total</span><br>";
        return $total;
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
        return 0;
    }
}

$totalUsers = debugGetTotalUsers($currentUser, $dateFrom, $dateTo, $selectedUserId);
echo "</div>";

// 7. Verificar archivos CSS y JS necesarios
echo "<div class='section'>";
echo "<h2>7. Verificando archivos necesarios para user_reports.php</h2>";

$requiredFiles = [
    'modules/reports/user_reports.php' => 'Archivo principal',
    'assets/css/reports.css' => 'CSS de reportes',
    'assets/css/modal.css' => 'CSS de modales',
    'assets/css/summary.css' => 'CSS de resúmenes',
    'assets/js/reports.js' => 'JavaScript de reportes (opcional)',
    'includes/sidebar.php' => 'Sidebar del sistema'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file</span> - $description<br>";
    } else {
        echo "<span class='error'>❌ $file</span> - $description <strong>(FALTA)</strong><br>";
    }
}
echo "</div>";

// 8. Resumen del diagnóstico
echo "<div class='section'>";
echo "<h2>8. Resumen del diagnóstico</h2>";

echo "<h3>📊 Resultados obtenidos:</h3>";
echo "<ul>";
echo "<li><strong>Usuarios encontrados:</strong> " . count($users) . "</li>";
echo "<li><strong>Total calculado:</strong> $totalUsers</li>";
echo "<li><strong>Rol del usuario:</strong> {$currentUser['role']}</li>";
echo "<li><strong>Rango de fechas:</strong> $dateFrom a $dateTo</li>";
echo "</ul>";

echo "<h3>🔍 Posibles problemas:</h3>";
if (count($users) === 0) {
    echo "<span class='error'>❌ No se encontraron usuarios en la consulta</span><br>";
    echo "<span class='warning'>Posibles causas:</span><br>";
    echo "<ul>";
    echo "<li>No hay usuarios activos en el sistema</li>";
    echo "<li>El usuario no tiene permisos para ver los datos</li>";
    echo "<li>Error en la consulta SQL</li>";
    echo "<li>Problema con los filtros de fecha</li>";
    echo "</ul>";
} else {
    echo "<span class='success'>✅ Se encontraron datos de usuarios correctamente</span><br>";
}

echo "<h3>✅ Próximos pasos:</h3>";
echo "<ul>";
echo "<li>Si no hay datos: Verifica que existan usuarios activos</li>";
echo "<li>Si hay errores SQL: Revisa la estructura de las tablas</li>";
echo "<li>Si los archivos faltan: Crea los archivos CSS faltantes</li>";
echo "<li>Accede a: <code>modules/reports/user_reports.php</code> para probar</li>";
echo "</ul>";
echo "</div>";

echo "<p><em>Diagnóstico completado: " . date('Y-m-d H:i:s') . "</em></p>";
?>