<?php
/**
 * clear_activity_logs.php
 * Script simple para limpiar activity logs
 * Uso: php clear_activity_logs.php
 */

require_once __DIR__ . '/config/database.php';

echo "🧹 LIMPIADOR DE ACTIVITY LOGS\n";
echo "=============================\n\n";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Mostrar estadísticas actuales
    echo "📊 ESTADÍSTICAS ANTES DE LIMPIAR:\n";
    
    // Total de registros
    $totalQuery = "SELECT COUNT(*) as total FROM activity_logs";
    $totalResult = $pdo->query($totalQuery)->fetch(PDO::FETCH_ASSOC);
    $totalRecords = $totalResult['total'];
    echo "- Total de registros: " . number_format($totalRecords) . "\n";
    
    // Registros por acción (top 10)
    echo "\n🔥 TOP 10 ACCIONES MÁS FRECUENTES:\n";
    $actionsQuery = "SELECT action, COUNT(*) as count FROM activity_logs GROUP BY action ORDER BY count DESC LIMIT 10";
    $actionsResult = $pdo->query($actionsQuery);
    while ($row = $actionsResult->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("  %-20s: %s\n", $row['action'], number_format($row['count']));
    }
    
    // Registros recientes
    $recentQuery = "SELECT COUNT(*) as count FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $recentResult = $pdo->query($recentQuery)->fetch(PDO::FETCH_ASSOC);
    echo "\n- Últimas 24 horas: " . number_format($recentResult['count']) . "\n";
    
    $weekQuery = "SELECT COUNT(*) as count FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $weekResult = $pdo->query($weekQuery)->fetch(PDO::FETCH_ASSOC);
    echo "- Última semana: " . number_format($weekResult['count']) . "\n";
    
    // Si no hay registros, no hacer nada
    if ($totalRecords == 0) {
        echo "\n✅ La tabla activity_logs ya está vacía.\n";
        echo "🎯 Lista para nuevos registros.\n";
        exit(0);
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "⚠️  ¿ESTÁS SEGURO QUE QUIERES ELIMINAR TODOS LOS REGISTROS?\n";
    echo "   Esto eliminará " . number_format($totalRecords) . " registros permanentemente.\n";
    echo str_repeat("=", 50) . "\n\n";
    
    // Confirmación
    echo "Escribe 'SI' para confirmar (cualquier otra cosa cancela): ";
    $handle = fopen("php://stdin", "r");
    $confirmation = trim(fgets($handle));
    fclose($handle);
    
    if (strtoupper($confirmation) === 'SI') {
        echo "\n🧹 ELIMINANDO TODOS LOS REGISTROS...\n";
        
        // Backup opcional de los datos antes de eliminar
        $backupFile = 'activity_logs_backup_' . date('Y-m-d_H-i-s') . '.sql';
        echo "💾 Creando backup en: $backupFile\n";
        
        // Crear backup
        $backupQuery = "SELECT * FROM activity_logs ORDER BY created_at DESC";
        $backupResult = $pdo->query($backupQuery);
        
        $backupContent = "-- Backup de activity_logs creado el " . date('Y-m-d H:i:s') . "\n";
        $backupContent .= "-- Total de registros: " . number_format($totalRecords) . "\n\n";
        
        if ($totalRecords > 0) {
            $backupContent .= "INSERT INTO activity_logs (id, user_id, action, table_name, record_id, description, ip_address, user_agent, created_at) VALUES\n";
            
            $rows = [];
            while ($row = $backupResult->fetch(PDO::FETCH_ASSOC)) {
                $values = "(" . 
                    (int)$row['id'] . ", " .
                    (int)$row['user_id'] . ", " .
                    "'" . addslashes($row['action']) . "', " .
                    (isset($row['table_name']) ? "'" . addslashes($row['table_name']) . "'" : 'NULL') . ", " .
                    (isset($row['record_id']) ? (int)$row['record_id'] : 'NULL') . ", " .
                    (isset($row['description']) ? "'" . addslashes($row['description']) . "'" : 'NULL') . ", " .
                    (isset($row['ip_address']) ? "'" . addslashes($row['ip_address']) . "'" : 'NULL') . ", " .
                    (isset($row['user_agent']) ? "'" . addslashes($row['user_agent']) . "'" : 'NULL') . ", " .
                    "'" . $row['created_at'] . "'" .
                    ")";
                $rows[] = $values;
            }
            
            $backupContent .= implode(",\n", $rows) . ";\n";
        }
        
        file_put_contents($backupFile, $backupContent);
        echo "✅ Backup creado exitosamente\n";
        
        // Eliminar todos los registros
        $pdo->exec("TRUNCATE TABLE activity_logs");
        
        // Verificar que se eliminaron
        $verifyQuery = "SELECT COUNT(*) as count FROM activity_logs";
        $verifyResult = $pdo->query($verifyQuery)->fetch(PDO::FETCH_ASSOC);
        
        if ($verifyResult['count'] == 0) {
            echo "✅ LIMPIEZA COMPLETADA EXITOSAMENTE\n";
            echo "📊 Registros eliminados: " . number_format($totalRecords) . "\n";
            echo "📊 Registros restantes: 0\n";
            echo "💾 Backup guardado en: $backupFile\n";
            echo "🎯 ¡Listo para probar el nuevo sistema desde cero!\n\n";
            
            echo "💡 PRÓXIMOS PASOS:\n";
            echo "1. Modifica tu función logActivity con los filtros\n";
            echo "2. Haz algunas acciones (login, subir archivo, etc.)\n";
            echo "3. Verifica que solo se registren las acciones importantes\n";
            echo "4. Si algo sale mal, puedes restaurar con: mysql -u usuario -p base_datos < $backupFile\n";
        } else {
            echo "❌ ERROR: No se pudieron eliminar todos los registros\n";
            echo "📊 Registros restantes: " . number_format($verifyResult['count']) . "\n";
        }
        
    } else {
        echo "\n❌ OPERACIÓN CANCELADA\n";
        echo "📊 No se eliminó ningún registro.\n";
        echo "💡 Los registros permanecen intactos.\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "💡 Verifica tu conexión a la base de datos.\n";
}

echo "\n🏁 SCRIPT TERMINADO\n";
?>