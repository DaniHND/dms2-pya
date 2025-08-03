<?php
/*
 * test_groups_fix.php
 * Prueba rápida para verificar que el módulo de grupos funcione
 * Ejecutar desde: http://localhost/dms2-pya/test_groups_fix.php
 */

echo "<h1>🧪 Prueba del Módulo de Grupos Corregido</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
    .button { padding: 10px 15px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; }
    .btn-primary { background: #007bff; color: white; }
    .btn-success { background: #28a745; color: white; }
    .json-output { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
</style>";

// 1. VERIFICAR CONEXIÓN Y SESIÓN
echo "<div class='section'>";
echo "<h2>🔐 1. Verificación de Sesión</h2>";

session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<div class='warning'>⚠️ No hay sesión activa. <a href='login.php'>Iniciar sesión</a></div>";
} else {
    echo "<div class='success'>✅ Sesión activa - Usuario ID: " . $_SESSION['user_id'] . "</div>";
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        echo "<div class='success'>✅ Permisos de administrador</div>";
    } else {
        echo "<div class='warning'>⚠️ No tienes permisos de administrador</div>";
    }
}
echo "</div>";

// 2. VERIFICAR BASE DE DATOS
echo "<div class='section'>";
echo "<h2>🗄️ 2. Prueba de Base de Datos</h2>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo conectar");
    }
    
    echo "<div class='success'>✅ Conexión a BD exitosa</div>";
    
    // Contar grupos existentes
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_groups");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<div class='info'>📊 Grupos existentes: $count</div>";
    
    // Mostrar los primeros 3 grupos
    $stmt = $pdo->query("SELECT id, name, status, is_system_group FROM user_groups ORDER BY id LIMIT 3");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($groups)) {
        echo "<div class='info'>📋 Grupos disponibles para probar:</div>";
        echo "<ul>";
        foreach ($groups as $group) {
            $tipo = $group['is_system_group'] ? 'Sistema' : 'Personalizado';
            echo "<li>ID: {$group['id']} - {$group['name']} ({$group['status']}) - $tipo</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error de BD: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo "</div>";

// 3. PROBAR GET_GROUP_DETAILS
echo "<div class='section'>";
echo "<h2>🔧 3. Prueba de get_group_details.php</h2>";

if (isset($groups) && !empty($groups)) {
    $testGroupId = $groups[0]['id'];
    echo "<div class='info'>🎯 Probando con grupo ID: $testGroupId</div>";
    
    // Simular la llamada AJAX
    $_GET['id'] = $testGroupId;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    ob_start();
    try {
        include 'modules/groups/actions/get_group_details.php';
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    $response = ob_get_clean();
    
    $data = json_decode($response, true);
    
    if ($data && isset($data['success'])) {
        if ($data['success']) {
            echo "<div class='success'>✅ get_group_details.php funciona correctamente</div>";
            echo "<div class='json-output'>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</div>";
        } else {
            echo "<div class='error'>❌ Error en get_group_details.php: " . htmlspecialchars($data['message']) . "</div>";
        }
    } else {
        echo "<div class='error'>❌ Respuesta inválida de get_group_details.php</div>";
        echo "<div class='json-output'>Respuesta cruda: " . htmlspecialchars($response) . "</div>";
    }
} else {
    echo "<div class='warning'>⚠️ No hay grupos para probar</div>";
}
echo "</div>";

// 4. PROBAR CREATE_GROUP (solo simulación)
echo "<div class='section'>";
echo "<h2>🆕 4. Prueba de create_group.php (simulación)</h2>";

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    echo "<div class='info'>📝 Simulando creación de grupo de prueba...</div>";
    
    // Simular datos POST
    $_POST['group_name'] = 'Grupo de Prueba ' . date('Y-m-d H:i:s');
    $_POST['group_description'] = 'Grupo creado para pruebas del sistema';
    $_POST['group_status'] = 'active';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    ob_start();
    try {
        include 'modules/groups/actions/create_group.php';
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    $response = ob_get_clean();
    
    $data = json_decode($response, true);
    
    if ($data && isset($data['success'])) {
        if ($data['success']) {
            echo "<div class='success'>✅ create_group.php funciona correctamente</div>";
            echo "<div class='info'>🎉 Grupo creado con ID: " . $data['group_id'] . "</div>";
        } else {
            echo "<div class='error'>❌ Error en create_group.php: " . htmlspecialchars($data['message']) . "</div>";
        }
    } else {
        echo "<div class='error'>❌ Respuesta inválida de create_group.php</div>";
        echo "<div class='json-output'>Respuesta cruda: " . htmlspecialchars($response) . "</div>";
    }
} else {
    echo "<div class='warning'>⚠️ Necesitas estar logueado como admin para probar la creación</div>";
}
echo "</div>";

// 5. VERIFICAR ARCHIVOS NECESARIOS
echo "<div class='section'>";
echo "<h2>📁 5. Verificación de Archivos Necesarios</h2>";

$requiredFiles = [
    'modules/groups/index.php' => 'Página principal',
    'modules/groups/actions/create_group.php' => 'Crear grupo',
    'modules/groups/actions/get_group_details.php' => 'Obtener detalles',
    'modules/groups/actions/toggle_group_status.php' => 'Cambiar estado',
    'modules/groups/actions/update_group.php' => 'Actualizar grupo',
    'assets/css/groups.css' => 'Estilos CSS',
    'assets/js/modal.js' => 'Sistema de modales'
];

$allPresent = true;
foreach ($requiredFiles as $file => $desc) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $file</div>";
    } else {
        echo "<div class='error'>❌ $file - FALTA</div>";
        $allPresent = false;
    }
}

if ($allPresent) {
    echo "<div class='success'>🎉 Todos los archivos necesarios están presentes</div>";
} else {
    echo "<div class='warning'>⚠️ Algunos archivos faltan. Cópialos desde los artifacts generados.</div>";
}
echo "</div>";

// 6. ENLACES DE PRUEBA
echo "<div class='section'>";
echo "<h2>🔗 6. Enlaces para Probar</h2>";

echo "<p>Una vez que todo esté funcionando, prueba estos enlaces:</p>";
echo "<ul>";
echo "<li><a href='modules/groups/index.php' target='_blank' class='button btn-primary'>🏠 Página Principal de Grupos</a></li>";
echo "<li><a href='diagnostic_groups.php' target='_blank' class='button btn-success'>🔍 Diagnóstico Completo</a></li>";
echo "<li><a href='login.php' target='_blank' class='button btn-success'>🔐 Iniciar Sesión</a></li>";
echo "</ul>";

echo "<h3>💡 Consejos para debugging:</h3>";
echo "<ul>";
echo "<li>Abre F12 en el navegador para ver errores de JavaScript/AJAX</li>";
echo "<li>Revisa los logs de PHP en tu servidor</li>";
echo "<li>Asegúrate de estar logueado como administrador</li>";
echo "<li>Verifica que la base de datos tenga datos de grupos</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><em>Prueba completada: " . date('Y-m-d H:i:s') . "</em></p>";
?>