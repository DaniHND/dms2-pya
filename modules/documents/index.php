<?php
/**
 * modules/documents/index.php - Documentos 
 * Sistema de gestión documental DMS2
 */

// Configuración y autenticación
require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar autenticación
SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Verificar permisos de administrador
if ($currentUser['role'] !== 'admin') {
    header('Location: ../../dashboard.php?error=access_denied');
    exit;
}

// Configurar respuesta
header('Content-Type: text/html; charset=utf-8');

// Configurar conexión a base de datos
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    error_log('Error de conexión en documents: ' . $e->getMessage());
    die('Error de conexión a la base de datos');
}

// Variables del módulo
$pageTitle = 'Documentos';
$currentModule = 'documents';

// Funciones helper necesarias
if (!function_exists('getFullName')) {
    function getFullName() {
        $user = SessionManager::getCurrentUser();
        return trim($user['first_name'] . ' ' . $user['last_name']);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y H:i') {
        if (empty($date)) return 'N/A';
        try {
            return date($format, strtotime($date));
        } catch (Exception $e) {
            return 'Fecha inválida';
        }
    }
}

if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($status) {
        return $status === 'active' ? 'status-active' : 'status-inactive';
    }
}

// Módulo documents configurado correctamente

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/modules.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .module-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .module-header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #007bff; }
        .module-content { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-active { background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .status-inactive { background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .btn { padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: 500; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-info { background: #17a2b8; color: white; }
        .actions-bar { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .data-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .data-table th { background: #f8f9fa; font-weight: 600; }
        .navigation-links { margin: 20px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="module-container">
        <div class="module-header">
            <h1>📁 Documentos</h1>
            <p>Gestión de documentos (Inbox)</p>
            <div class="user-info">
                <strong>Usuario:</strong> <?php echo htmlspecialchars(getFullName()); ?>
                <strong>Rol:</strong> <?php echo htmlspecialchars($currentUser['role']); ?>
                <strong>Fecha:</strong> <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>

        <div class="module-content">
            <div class="actions-bar">
                <a href="../../dashboard.php" class="btn btn-secondary">
                    <i data-feather="arrow-left"></i> Volver al Dashboard
                </a>
                <a href="../users/index.php" class="btn btn-info">
                    <i data-feather="users"></i> Usuarios
                </a>
                <a href="../companies/index.php" class="btn btn-primary">
                    <i data-feather="building"></i> Empresas
                </a>
                <a href="../reports/index.php" class="btn btn-success">
                    <i data-feather="bar-chart-2"></i> Reportes
                </a>
                <a href="../../logout.php" class="btn btn-danger" onclick="return confirm('¿Cerrar sesión?')">
                    <i data-feather="log-out"></i> Salir
                </a>
            </div>

            
            <h2>📁 Bandeja de Documentos</h2>
            <div class="document-stats">
                <div class="stat-card">
                    <h3>📄 Documentos Totales</h3>
                    <p>Sistema funcionando correctamente</p>
                </div>
                <div class="stat-card">
                    <h3>🔍 Explorador</h3>
                    <p>Navegar y gestionar documentos</p>
                </div>
            </div>
            
            <div class="inbox-actions">
                <button class="btn btn-primary" onclick="alert('Funcionalidad en desarrollo')">
                    <i data-feather="upload"></i> Subir Documento
                </button>
                <button class="btn btn-info" onclick="alert('Funcionalidad en desarrollo')">
                    <i data-feather="folder"></i> Crear Carpeta
                </button>
                <button class="btn btn-success" onclick="alert('Funcionalidad en desarrollo')">
                    <i data-feather="search"></i> Buscar
                </button>
            </div>
        </div>
    </div>

    <script>
        // Inicializar iconos Feather
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        // Log de inicialización
        console.log('✅ Módulo documents inicializado correctamente');
        
        // Funciones específicas del módulo
        
        // Funciones específicas para módulo documents
        function refreshModule() {
            window.location.reload();
        }
        
        function showComingSoon(feature) {
            alert('Funcionalidad "' + feature + '" próximamente disponible');
        }
        
        // Inicialización específica
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎯 Módulo documents completamente cargado');
        });
    </script>
</body>
</html>