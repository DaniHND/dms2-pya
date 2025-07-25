<?php
// debug_user_reports_frontend.php
// Debug específico para la parte visual de user_reports.php

require_once 'config/session.php';
require_once 'config/database.php';

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtrado (igual que user_reports.php)
$selectedUserId = $_GET['user_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Copiar EXACTAMENTE las funciones de user_reports.php
function getUsersWithStats($currentUser, $dateFrom, $dateTo, $selectedUserId = '')
{
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
            $query .= " AND u.id = :selected_user_id";
            $params['selected_user_id'] = $selectedUserId;
        }
        
        $query .= " ORDER BY u.created_at DESC";
        
    } else {
        // Usuario normal solo puede ver sus propios datos
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
                  WHERE u.id = :user_id AND u.status = 'active'";
        
        $params = [
            'user_id' => $currentUser['id'],
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59'
        ];
    }

    return fetchAll($query, $params);
}

function getTotalUsers($currentUser, $dateFrom, $dateTo, $selectedUserId)
{
    if ($currentUser['role'] === 'admin') {
        if ($selectedUserId) {
            return 1; // Si hay un usuario específico, siempre es 1
        }
        $query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
        $params = [];
    } else {
        // Usuario normal solo puede ver sus propios datos
        return 1;
    }
    
    $result = fetchOne($query, $params);
    return $result['total'] ?? 0;
}

// Ejecutar las funciones CON manejo de errores
try {
    $users = getUsersWithStats($currentUser, $dateFrom, $dateTo, $selectedUserId);
    $totalUsers = getTotalUsers($currentUser, $dateFrom, $dateTo, $selectedUserId);
    
    // Verificar si $users es válido
    if ($users === false) {
        $users = [];
        $errorMessage = "Error en la consulta getUsersWithStats - retornó false";
    } elseif (!is_array($users)) {
        $originalUsers = $users;
        $users = [];
        $errorMessage = "getUsersWithStats retornó tipo: " . gettype($originalUsers);
    } else {
        $errorMessage = null;
    }
} catch (Exception $e) {
    $users = [];
    $totalUsers = 0;
    $errorMessage = "Exception: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Frontend - User Reports</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .debug-box { background: #f0f8ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .original-code { background: #fffacd; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>

<h1>Debug Frontend - Reporte de Usuarios</h1>

<div class="section">
    <h2>1. Variables de estado</h2>
    <div class="debug-box">
        <strong>Información del usuario actual:</strong><br>
        • ID: <?php echo $currentUser['id']; ?><br>
        • Username: <?php echo htmlspecialchars($currentUser['username']); ?><br>
        • Rol: <?php echo $currentUser['role']; ?><br>
        • Empresa ID: <?php echo $currentUser['company_id'] ?? 'No definida'; ?><br><br>
        
        <strong>Parámetros de filtrado:</strong><br>
        • Usuario seleccionado: <?php echo $selectedUserId ? $selectedUserId : 'Ninguno'; ?><br>
        • Fecha desde: <?php echo $dateFrom; ?><br>
        • Fecha hasta: <?php echo $dateTo; ?><br><br>
        
        <strong>Resultados de las consultas:</strong><br>
        • Total de usuarios: <?php echo $totalUsers; ?><br>
        • Array $users count: <?php echo is_array($users) ? count($users) : 'NO ES ARRAY'; ?><br>
        • Array $users empty?: <?php echo empty($users) ? 'SÍ' : 'NO'; ?><br>
        • Tipo de $users: <?php echo gettype($users); ?><br>
        <?php if (isset($errorMessage) && $errorMessage): ?>
            <span style="color: red;"><strong>ERROR DETECTADO:</strong> <?php echo htmlspecialchars($errorMessage); ?></span><br>
        <?php endif; ?>
    </div>
    <h3>Test directo de la consulta SQL</h3>
    <div style="background: #f0f8ff; border: 1px solid #0066cc; padding: 15px; margin: 10px 0;">
        <?php
        // Test directo de la consulta
        echo "<h4>Ejecutando consulta directamente:</h4>";
        
        $testQuery = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                             u.last_login, u.created_at, c.name as company_name,
                             (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                              AND al.created_at >= ? AND al.created_at <= ?) as activity_count,
                             (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id 
                              AND d.created_at >= ? AND d.created_at <= ?) as documents_uploaded,
                             (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                              AND al.action = 'download' AND al.created_at >= ? AND al.created_at <= ?) as downloads_count
                      FROM users u
                      LEFT JOIN companies c ON u.company_id = c.id
                      WHERE u.status = 'active'
                      ORDER BY u.created_at DESC";
        
        $testParams = [
            $dateFrom . ' 00:00:00',
            $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00',
            $dateTo . ' 23:59:59',
            $dateFrom . ' 00:00:00',
            $dateTo . ' 23:59:59'
        ];
        
        echo "<strong>Consulta:</strong><br>";
        echo "<code>" . htmlspecialchars(str_replace('?', '%s', $testQuery)) . "</code><br><br>";
        
        echo "<strong>Parámetros:</strong><br>";
        foreach ($testParams as $i => $param) {
            echo "[$i] " . htmlspecialchars($param) . "<br>";
        }
        echo "<br>";
        
        try {
            $directResult = fetchAll($testQuery, $testParams);
            
            if ($directResult === false) {
                echo "<span style='color: red;'><strong>❌ fetchAll() retornó FALSE</strong></span><br>";
                echo "Esto indica un error en la consulta SQL.<br>";
            } elseif (is_array($directResult)) {
                echo "<span style='color: green;'><strong>✅ fetchAll() retornó array con " . count($directResult) . " elementos</strong></span><br>";
                echo "<h5>Primeros resultados:</h5>";
                echo "<pre>" . print_r(array_slice($directResult, 0, 2), true) . "</pre>";
            } else {
                echo "<span style='color: orange;'><strong>⚠️ fetchAll() retornó tipo: " . gettype($directResult) . "</strong></span><br>";
            }
            
        } catch (Exception $e) {
            echo "<span style='color: red;'><strong>❌ Excepción en consulta directa:</strong></span><br>";
            echo htmlspecialchars($e->getMessage()) . "<br>";
        }
        ?>
    </div>
</div>

<div class="section">
    <h2>2. Contenido del array $users y diagnóstico de error</h2>
    <div class="debug-box">
        <?php if (isset($errorMessage) && $errorMessage): ?>
            <div style="background: #ffebee; border: 2px solid red; padding: 10px; margin: 10px 0;">
                <strong>🚨 ERROR ENCONTRADO:</strong><br>
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
        <strong>Información técnica:</strong><br>
        • Tipo de variable: <?php echo gettype($users); ?><br>
        • Es array?: <?php echo is_array($users) ? 'SÍ' : 'NO'; ?><br>
        • Es false?: <?php echo $users === false ? 'SÍ' : 'NO'; ?><br>
        • Es null?: <?php echo $users === null ? 'SÍ' : 'NO'; ?><br><br>
        
        <strong>Contenido:</strong><br>
        <pre><?php 
        if (is_array($users)) {
            print_r($users); 
        } else {
            echo "Variable no es array. Valor: ";
            var_dump($users);
        }
        ?></pre>
    </div>
</div>

<div class="section">
    <h2>3. Test de la lógica condicional</h2>
    <div class="debug-box">
        <?php if (empty($users)): ?>
            <span class="warning">⚠️ La condición empty($users) es VERDADERA</span><br>
            <span class="info">Esto significa que se mostrará el mensaje "No se encontraron usuarios"</span>
        <?php else: ?>
            <span class="success">✅ La condición empty($users) es FALSA</span><br>
            <span class="info">Esto significa que se debería mostrar la tabla con usuarios</span>
        <?php endif; ?>
    </div>
</div>

<div class="section">
    <h2>4. Simulación de la tabla HTML original</h2>
    <div class="original-code">
        <h3>Así se vería la tabla en user_reports.php:</h3>
        
        <?php if (empty($users)): ?>
            <div style="border: 2px solid red; padding: 10px; background: #ffe6e6;">
                <strong>CASO: Array vacío</strong><br>
                <p>🚫 No se encontraron usuarios</p>
                <span class="error">Esto es lo que se está mostrando en tu página</span>
            </div>
        <?php else: ?>
            <div style="border: 2px solid green; padding: 10px; background: #e6ffe6;">
                <strong>CASO: Array con datos</strong><br>
                
                <h4>Usuarios encontrados (<?php echo count($users); ?>):</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Empresa</th>
                            <th>Rol</th>
                            <th>Actividades</th>
                            <th>Documentos</th>
                            <th>Descargas</th>
                            <th>Último Acceso</th>
                            <?php if ($currentUser['role'] === 'admin'): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong><br>
                                    <small>@<?php echo htmlspecialchars($user['username']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($user['company_name'] ?? 'Sin empresa'); ?></td>
                                <td><?php echo ucfirst($user['role']); ?></td>
                                <td><?php echo number_format($user['activity_count']); ?></td>
                                <td><?php echo number_format($user['documents_uploaded']); ?></td>
                                <td><?php echo number_format($user['downloads_count']); ?></td>
                                <td>
                                    <?php echo $user['last_login'] ? date('d/m/Y H:i:s', strtotime($user['last_login'])) : 'Nunca'; ?>
                                </td>
                                <?php if ($currentUser['role'] === 'admin'): ?>
                                    <td>
                                        <a href="?user_id=<?php echo $user['id']; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">
                                            Ver detalles
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <span class="success">✅ Esta tabla debería aparecer en tu página</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="section">
    <h2>5. Verificación de archivos CSS</h2>
    <div class="debug-box">
        <p>Verifica que estos archivos CSS existan:</p>
        <ul>
            <li><a href="assets/css/main.css" target="_blank">assets/css/main.css</a></li>
            <li><a href="assets/css/dashboard.css" target="_blank">assets/css/dashboard.css</a></li>
            <li><a href="assets/css/reports.css" target="_blank">assets/css/reports.css</a></li>
        </ul>
        <p>Si alguno no carga, podría estar afectando la visualización.</p>
    </div>
</div>

<div class="section">
    <h2>6. Test JavaScript</h2>
    <div class="debug-box">
        <p>Abre la consola del navegador (F12) y verifica si hay errores JavaScript.</p>
        <button onclick="testJS()">Test JavaScript</button>
        <div id="jsResult"></div>
        
        <script>
            function testJS() {
                document.getElementById('jsResult').innerHTML = '✅ JavaScript funciona correctamente';
            }
            
            // Verificar si Feather Icons está cargando
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof feather !== 'undefined') {
                    console.log('✅ Feather Icons cargado correctamente');
                } else {
                    console.log('❌ Error: Feather Icons no se cargó');
                }
            });
        </script>
    </div>
</div>

<div class="section">
    <h2>7. Conclusiones</h2>
    <div class="debug-box">
        <?php if (empty($users)): ?>
            <span class="error"><strong>❌ PROBLEMA IDENTIFICADO:</strong></span><br>
            <p>El array $users está vacío, aunque el diagnóstico anterior mostró que SÍ hay usuarios en la base de datos.</p>
            
            <p><strong>Posibles causas:</strong></p>
            <ul>
                <li>Error en la función getUsersWithStats() en el archivo user_reports.php real</li>
                <li>Los parámetros de fecha están excluyendo todos los usuarios</li>
                <li>Hay un filtro adicional en el archivo original que no se está aplicando aquí</li>
                <li>Problema con la función fetchAll() o las conexiones de base de datos</li>
            </ul>
            
        <?php else: ?>
            <span class="success"><strong>✅ DATOS CORRECTOS:</strong></span><br>
            <p>Los usuarios se están obteniendo correctamente. Si no se muestran en user_reports.php, el problema es:</p>
            <ul>
                <li>Archivos CSS no se cargan correctamente (afecta la visualización)</li>
                <li>Errores JavaScript que impiden el renderizado</li>
                <li>Problema con includes/requires en el archivo original</li>
                <li>Cache del navegador</li>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="section">
    <h2>8. Acciones recomendadas</h2>
    <div class="debug-box">
        <h4>Pasos inmediatos:</h4>
        <ol>
            <li><strong>Compara este resultado con user_reports.php:</strong> Accede a modules/reports/user_reports.php y verifica si muestra lo mismo</li>
            <li><strong>Revisa la consola del navegador:</strong> Presiona F12 y busca errores en rojo</li>
            <li><strong>Verifica los archivos CSS:</strong> Haz clic en los enlaces de CSS arriba</li>
            <li><strong>Limpia cache:</strong> Ctrl+F5 o Shift+F5 para refrescar sin cache</li>
            <li><strong>Compara el código:</strong> Asegúrate de que user_reports.php tenga exactamente las mismas funciones que este debug</li>
        </ol>
    </div>
</div>

</body>
</html>