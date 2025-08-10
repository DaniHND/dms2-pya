<?php
// modules/reports/user_reports.php
// Reportes por usuario del sistema - DMS2
// VERSION CON SEGURIDAD - Usuarios no ven datos de administradores

require_once '../../config/session.php';
require_once '../../config/database.php';

// Función helper para obtener nombre completo si no existe
if (!function_exists('getFullName')) {
    function getFullName() {
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
$selectedUserId = $_GET['user_id'] ?? '';
$reportType = $_GET['report_type'] ?? 'summary';

// Función para obtener usuarios para el filtro (CON SEGURIDAD)
function getUsers($currentUser)
{
    if ($currentUser['role'] === 'admin') {
        // Admin puede ver todos los usuarios
        $query = "SELECT id, username, first_name, last_name, email, company_id, role 
                  FROM users 
                  WHERE status = 'active' 
                  ORDER BY first_name, last_name";
        return fetchAll($query);
    } else {
        // Usuario normal NO puede ver administradores
        $query = "SELECT id, username, first_name, last_name, email, company_id, role 
                  FROM users 
                  WHERE company_id = :company_id 
                  AND status = 'active' 
                  AND role != 'admin'
                  ORDER BY first_name,

                  ORDER BY first_name, last_name";
       return fetchAll($query, ['company_id' => $currentUser['company_id']]);
   }
}

// Función para obtener estadísticas generales de usuarios (CON SEGURIDAD)
function getUsersStats($currentUser, $dateFrom, $dateTo, $selectedUserId = '')
{
   $whereCondition = "";
   $params = [
       'date_from' => $dateFrom . ' 00:00:00',
       'date_to' => $dateTo . ' 23:59:59'
   ];

   if ($currentUser['role'] !== 'admin') {
       $whereCondition = "WHERE u.company_id = :company_id AND u.role != 'admin'";
       $params['company_id'] = $currentUser['company_id'];
   } else {
       $whereCondition = "WHERE 1=1";
   }

   if (!empty($selectedUserId)) {
       // VALIDACIÓN: Solo admin o usuarios de la misma empresa
       if ($currentUser['role'] !== 'admin') {
           $checkQuery = "SELECT role, company_id FROM users WHERE id = :check_user_id";
           $checkResult = fetchOne($checkQuery, ['check_user_id' => $selectedUserId]);
           if (!$checkResult || 
               $checkResult['role'] === 'admin' || 
               $checkResult['company_id'] != $currentUser['company_id']) {
               return [
                   'total_users' => 0,
                   'active_users' => 0,
                   'users_with_activity' => 0,
                   'total_activities' => 0
               ]; // Usuario no válido
           }
       }
       
       $whereCondition .= " AND u.id = :selected_user_id";
       $params['selected_user_id'] = $selectedUserId;
   }

   $stats = [];

   try {
       // Total de usuarios
       $query = "SELECT COUNT(*) as total_users FROM users u $whereCondition";
       $result = fetchOne($query, $params);
       $stats['total_users'] = $result['total_users'] ?? 0;

       // Usuarios activos (con login reciente)
       $query = "SELECT COUNT(*) as active_users FROM users u $whereCondition 
                 AND u.last_login >= :active_date";
       $params['active_date'] = date('Y-m-d H:i:s', strtotime('-30 days'));
       $result = fetchOne($query, $params);
       $stats['active_users'] = $result['active_users'] ?? 0;

       // Usuarios con actividad en el rango de fechas
       $query = "SELECT COUNT(DISTINCT al.user_id) as users_with_activity
                 FROM activity_logs al 
                 LEFT JOIN users u ON al.user_id = u.id
                 WHERE al.created_at >= :date_from AND al.created_at <= :date_to";

       if ($currentUser['role'] !== 'admin') {
           $query .= " AND u.company_id = :company_id AND u.role != 'admin'";
       }

       if (!empty($selectedUserId)) {
           $query .= " AND al.user_id = :selected_user_id";
       }

       $result = fetchOne($query, $params);
       $stats['users_with_activity'] = $result['users_with_activity'] ?? 0;

       // Total de actividades en el período
       $query = "SELECT COUNT(*) as total_activities
                 FROM activity_logs al 
                 LEFT JOIN users u ON al.user_id = u.id
                 WHERE al.created_at >= :date_from AND al.created_at <= :date_to";

       if ($currentUser['role'] !== 'admin') {
           $query .= " AND u.company_id = :company_id AND u.role != 'admin'";
       }

       if (!empty($selectedUserId)) {
           $query .= " AND al.user_id = :selected_user_id";
       }

       $result = fetchOne($query, $params);
       $stats['total_activities'] = $result['total_activities'] ?? 0;

   } catch (Exception $e) {
       error_log("Error en getUsersStats: " . $e->getMessage());
       return [
           'total_users' => 0,
           'active_users' => 0,
           'users_with_activity' => 0,
           'total_activities' => 0
       ];
   }

   return $stats;
}

// Función para obtener actividad de usuarios (CON SEGURIDAD)
function getUsersActivity($currentUser, $dateFrom, $dateTo, $selectedUserId = '')
{
   $params = [
       'date_from' => $dateFrom . ' 00:00:00',
       'date_to' => $dateTo . ' 23:59:59'
   ];

   $whereCondition = "";

   if ($currentUser['role'] !== 'admin') {
       $whereCondition = "WHERE u.company_id = :company_id AND u.role != 'admin'";
       $params['company_id'] = $currentUser['company_id'];
   } else {
       $whereCondition = "WHERE u.status = 'active'";
   }

   if (!empty($selectedUserId)) {
       // VALIDACIÓN: Solo admin o usuarios de la misma empresa
       if ($currentUser['role'] !== 'admin') {
           $checkQuery = "SELECT role, company_id FROM users WHERE id = :check_user_id";
           $checkResult = fetchOne($checkQuery, ['check_user_id' => $selectedUserId]);
           if (!$checkResult || 
               $checkResult['role'] === 'admin' || 
               $checkResult['company_id'] != $currentUser['company_id']) {
               return []; // Usuario no válido
           }
       }
       
       $whereCondition .= " AND u.id = :selected_user_id";
       $params['selected_user_id'] = $selectedUserId;
   }

   $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.last_login, u.role,
                    (SELECT COUNT(*) FROM activity_logs al 
                     WHERE al.user_id = u.id 
                     AND al.created_at >= :date_from 
                     AND al.created_at <= :date_to) as total,
                    (SELECT MAX(al.created_at) FROM activity_logs al 
                     WHERE al.user_id = u.id 
                     AND al.created_at >= :date_from 
                     AND al.created_at <= :date_to) as last_activity
             FROM users u $whereCondition
             ORDER BY total DESC, u.first_name ASC";

   try {
       $result = fetchAll($query, $params);
       return is_array($result) ? $result : [];
   } catch (Exception $e) {
       error_log("Error en getUsersActivity: " . $e->getMessage());
       return [];
   }
}

// Función para obtener estadísticas de un usuario específico
function getSelectedUserStats($userId, $dateFrom, $dateTo)
{
   $params = [
       'user_id' => $userId,
       'date_from' => $dateFrom . ' 00:00:00',
       'date_to' => $dateTo . ' 23:59:59'
   ];

   $stats = [];

   // Total de actividades del usuario
   $query = "SELECT COUNT(*) as total FROM activity_logs WHERE user_id = :user_id AND created_at >= :date_from AND created_at <= :date_to";
   $result = fetchOne($query, $params);
   $stats['total_activities'] = $result['total'] ?? 0;

   // Actividades por acción
   $query = "SELECT action, COUNT(*) as count FROM activity_logs WHERE user_id = :user_id AND created_at >= :date_from AND created_at <= :date_to GROUP BY action ORDER BY count DESC";
   $stats['by_action'] = fetchAll($query, $params);

   // Actividades por día
   $query = "SELECT DATE(created_at) as date, COUNT(*) as count FROM activity_logs WHERE user_id = :user_id AND created_at >= :date_from AND created_at <= :date_to GROUP BY DATE(created_at) ORDER BY date";
   $stats['by_date'] = fetchAll($query, $params);

   // Actividades recientes
   $query = "SELECT * FROM activity_logs WHERE user_id = :user_id AND created_at >= :date_from AND created_at <= :date_to ORDER BY created_at DESC LIMIT 20";
   $stats['recent_activities'] = fetchAll($query, $params);

   return $stats;
}

// Obtener datos (PASANDO EL FILTRO DE USUARIO CON SEGURIDAD)
$users = getUsers($currentUser);
$generalStats = getUsersStats($currentUser, $dateFrom, $dateTo, $selectedUserId);
$selectedUserStats = [];
$selectedUserActionStats = [];

if (!empty($selectedUserId)) {
   // VALIDACIÓN ADICIONAL: Verificar que el usuario seleccionado sea válido
   $validUser = false;
   foreach ($users as $user) {
       if ($user['id'] == $selectedUserId) {
           $validUser = true;
           break;
       }
   }
   
   if ($validUser) {
       $selectedUserStats = getSelectedUserStats($selectedUserId, $dateFrom, $dateTo);
       $selectedUserActionStats = $selectedUserStats['by_action'] ?? [];
   }
}

$usersActivity = getUsersActivity($currentUser, $dateFrom, $dateTo, $selectedUserId);

// CORRECCIÓN: Asegurar que $usersActivity sea siempre un array
if (!is_array($usersActivity)) {
   $usersActivity = [];
}

// CORRECCIÓN: Asegurar que $generalStats sea siempre un array
if (!is_array($generalStats)) {
   $generalStats = [
       'total_users' => 0,
       'active_users' => 0,
       'users_with_activity' => 0,
       'total_activities' => 0
   ];
}

// Registrar acceso
logActivity($currentUser['id'], 'view_user_reports', 'reports', null, 'Usuario accedió al reporte de usuarios');
?>

<!DOCTYPE html>
<html lang="es">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Reportes de Usuarios - DMS2</title>
   <link rel="stylesheet" href="../../assets/css/main.css">
   <link rel="stylesheet" href="../../assets/css/dashboard.css">
   <link rel="stylesheet" href="../../assets/css/reports.css">
   <link rel="stylesheet" href="../../assets/css/modal.css">
   <script src="https://unpkg.com/feather-icons"></script>
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
               <h1>Reportes de Usuarios</h1>
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

       <!-- Contenido de reportes -->
       <div class="reports-content">
           <!-- Navegación de reportes -->
           <div class="reports-nav-breadcrumb">
               <a href="index.php" class="breadcrumb-link">
                   <i data-feather="arrow-left"></i>
                   Volver a Reportes
               </a>
           </div>

           <!-- Estadísticas generales -->
           <div class="stats-grid">
               <div class="stat-card">
                   <div class="stat-icon">
                       <i data-feather="users"></i>
                   </div>
                   <div class="stat-info">
                       <div class="stat-number"><?php echo number_format($generalStats['total_users'] ?? 0); ?></div>
                       <div class="stat-label"><?php echo $currentUser['role'] === 'admin' ? 'Total Usuarios' : 'Usuarios de mi Empresa'; ?></div>
                   </div>
               </div>

               <div class="stat-card">
                   <div class="stat-icon">
                       <i data-feather="user-check"></i>
                   </div>
                   <div class="stat-info">
                       <div class="stat-number"><?php echo number_format($generalStats['active_users'] ?? 0); ?></div>
                       <div class="stat-label">Usuarios Activos</div>
                   </div>
               </div>

               <div class="stat-card">
                   <div class="stat-icon">
                       <i data-feather="activity"></i>
                   </div>
                   <div class="stat-info">
                       <div class="stat-number"><?php echo number_format($generalStats['users_with_activity'] ?? 0); ?></div>
                       <div class="stat-label">Con Actividad</div>
                   </div>
               </div>

               <div class="stat-card">
                   <div class="stat-icon">
                       <i data-feather="bar-chart"></i>
                   </div>
                   <div class="stat-info">
                       <div class="stat-number"><?php echo number_format($generalStats['total_activities'] ?? 0); ?></div>
                       <div class="stat-label">Total Actividades</div>
                   </div>
               </div>
           </div>

           <!-- Filtros automáticos -->
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
                   <div class="filter-group">
                       <label for="user_id">Usuario</label>
                       <select id="user_id" name="user_id">
                           <option value="">Todos los usuarios</option>
                           <?php foreach ($users as $user): ?>
                               <option value="<?php echo $user['id']; ?>" <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                   <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (@' . $user['username'] . ')'); ?>
                               </option>
                           <?php endforeach; ?>
                       </select>
                   </div>
                   <div class="filter-group">
                       <label for="report_type">Tipo de Reporte</label>
                       <select id="report_type" name="report_type">
                           <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Resumen General</option>
                           <option value="detailed" <?php echo $reportType === 'detailed' ? 'selected' : ''; ?>>Detallado</option>
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
                   <button class="export-btn" onclick="exportarDatos('pdf')">
                       <i data-feather="file"></i>
                       Descargar PDF
                   </button>
               </div>
           </div>

           <!-- Tabla de usuarios mejorada -->
           <div class="reports-table enhanced-table">
               <div class="table-header">
                   <h3><i data-feather="users"></i> Actividad de Usuarios (<?php echo count($usersActivity); ?> usuarios)</h3>
                   <div class="table-actions">
                       <span class="status-indicator active">
                           <i data-feather="activity"></i>
                           Sistema Activo
                       </span>
                   </div>
               </div>

               <?php if (!empty($usersActivity)): ?>
                   <div class="table-container">
                       <table class="data-table activity-table enhanced-activity-table">
                           <thead>
                               <tr>
                                   <th><i data-feather="user" class="table-icon"></i> Usuario</th>
                                   <th><i data-feather="mail" class="table-icon"></i> Email</th>
                                   <?php if ($currentUser['role'] === 'admin'): ?>
                                   <th><i data-feather="shield" class="table-icon"></i> Rol</th>
                                   <?php endif; ?>
                                   <th><i data-feather="bar-chart-2" class="table-icon"></i> Actividades</th>
                                   <th><i data-feather="clock" class="table-icon"></i> Última Actividad</th>
                                   <th><i data-feather="check-circle" class="table-icon"></i> Estado</th>
                               </tr>
                           </thead>
                           <tbody>
                               <?php foreach ($usersActivity as $index => $user): ?>
                                   <?php 
                                   $userActivity = [
                                       'total' => $user['total'],
                                       'last_activity' => $user['last_activity']
                                   ];
                                   $isTopUser = $index < 3; // Destacar top 3 usuarios
                                   $rowClass = $isTopUser ? 'top-user-row' : '';
                                   ?>
                                   <tr class="<?php echo $rowClass; ?>" data-user-id="<?php echo $user['id']; ?>">
                                       <td class="user-cell enhanced-user-cell">
                                           <div class="user-info-enhanced">
                                               <div class="user-avatar" data-initials="<?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>">
                                                   <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                               </div>
                                               <div class="user-details">
                                                   <span class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                                   <small class="username">@<?php echo htmlspecialchars($user['username']); ?></small>
                                               </div>
                                               <?php if ($isTopUser): ?>
                                                   <div class="top-user-badge">
                                                       <i data-feather="star"></i>
                                                   </div>
                                               <?php endif; ?>
                                           </div>
                                       </td>
                                       <td class="email-cell">
                                           <span class="email-text"><?php echo htmlspecialchars($user['email']); ?></span>
                                       </td>
                                       <?php if ($currentUser['role'] === 'admin'): ?>
                                       <td class="role-cell">
                                           <span class="role-badge role-<?php echo $user['role']; ?>">
                                               <i data-feather="<?php echo $user['role'] === 'admin' ? 'shield' : ($user['role'] === 'manager' ? 'users' : 'user'); ?>"></i>
                                               <?php echo ucfirst($user['role']); ?>
                                           </span>
                                       </td>
                                       <?php endif; ?>
                                       <td class="activity-cell">
                                           <div class="activity-count">
                                               <span class="badge badge-activity"><?php echo number_format($userActivity['total'] ?? 0); ?></span>
                                               <div class="activity-bar">
                                                   <?php 
                                                   $maxActivity = !empty($usersActivity) ? max(array_column($usersActivity, 'total')) : 1;
                                                   $percentage = $maxActivity > 0 ? ($userActivity['total'] / $maxActivity) * 100 : 0;
                                                   ?>
                                                   <div class="activity-fill" style="width: <?php echo $percentage; ?>%"></div>
                                               </div>
                                           </div>
                                       </td>
                                       <td class="datetime-cell">
                                           <?php if ($userActivity['last_activity']): ?>
                                               <div class="datetime enhanced-datetime">
                                                   <div class="date"><?php echo date('d/m/Y', strtotime($userActivity['last_activity'])); ?></div>
                                                   <div class="time"><?php echo date('H:i', strtotime($userActivity['last_activity'])); ?></div>
                                                   <div class="relative-time" title="<?php echo date('d/m/Y H:i:s', strtotime($userActivity['last_activity'])); ?>">
                                                       <?php 
                                                       $diff = time() - strtotime($userActivity['last_activity']);
                                                       if ($diff < 3600) echo 'Hace ' . round($diff/60) . ' min';
                                                       elseif ($diff < 86400) echo 'Hace ' . round($diff/3600) . ' h';
                                                       else echo 'Hace ' . round($diff/86400) . ' días';
                                                       ?>
                                                   </div>
                                               </div>
                                           <?php else: ?>
                                               <span class="text-muted">
                                                   <i data-feather="minus-circle"></i>
                                                   Sin actividad
                                               </span>
                                           <?php endif; ?>
                                       </td>
                                       <td class="status-cell">
                                           <span class="status-badge status-active">
                                               <i data-feather="check-circle"></i>
                                               Activo
                                           </span>
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
                               <i data-feather="users"></i>
                           </div>
                           <h4>No se encontraron usuarios</h4>
                           <p>No hay usuarios que coincidan con los filtros seleccionados.</p>
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

       // Inicializar página
       document.addEventListener('DOMContentLoaded', function() {
           feather.replace();
           updateTime();
           setInterval(updateTime, 1000);
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

       // Filtros automáticos sin botones
       document.addEventListener('change', function(e) {
           if (e.target.matches('#date_from, #date_to, #user_id, #report_type')) {
               autoFilter();
           }
       });

       function autoFilter() {
           const dateFrom = document.getElementById('date_from').value;
           const dateTo = document.getElementById('date_to').value;
           const userId = document.getElementById('user_id').value;
           const reportType = document.getElementById('report_type').value;

           const params = new URLSearchParams();
           if (dateFrom) params.set('date_from', dateFrom);
           if (dateTo) params.set('date_to', dateTo);
           if (userId) params.set('user_id', userId);
           if (reportType) params.set('report_type', reportType);

           window.location.href = window.location.pathname + '?' + params.toString();
       }

       function exportarDatos(formato) {
           // Obtener parámetros actuales de la URL
           const urlParams = new URLSearchParams(window.location.search);

           // Construir URL de exportación
           const exportUrl = 'export.php?format=' + formato + '&type=user_reports&modal=1&' + urlParams.toString();

           if (formato === 'pdf') {
               // Para PDF, abrir modal
               abrirModalPDF(exportUrl);
           } else {
               // Para CSV y Excel, abrir en nueva ventana para descarga
               mostrarNotificacion('Preparando descarga...', 'info');
               window.open(exportUrl.replace('&modal=1', ''), '_blank');
           }
       }

       function abrirModalPDF(url) {
           // Crear modal para PDF dinámicamente (como documents_report.php)
           const modal = document.createElement('div');
           modal.className = 'pdf-modal';
           modal.innerHTML = `
               <div class="pdf-modal-content">
                   <div class="pdf-modal-header">
                       <h3><i data-feather="users"></i> Reportes de Usuarios - PDF</h3>
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
           
           // Manejar errores de carga
           iframe.onerror = function() {
               document.querySelector('.pdf-loading').innerHTML = '<div class="loading-spinner"></div><p style="color: #ef4444;">Error al cargar la vista previa. <button onclick="cerrarModalPDF()" style="margin-left: 10px; padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">Cerrar</button></p>';
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
        }

        .pdf-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 768px) {
            .pdf-modal-content {
                width: 95%;
                height: 90%;
            }
            
            .pdf-modal-header,
            .pdf-modal-body {
                padding: 15px;
            }
            
            .pdf-actions {
                flex-direction: column;
            }
            
            .pdf-actions button {
                width: 100%;
                justify-content: center;
            }
        }

        /* Colores suaves y congruentes para el sistema */
        :root {
            --primary-gradient: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            --secondary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            --success-gradient: linear-gradient(135deg, #10B981 0%, #059669 100%);
            --warning-gradient: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            --info-gradient: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            --soft-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --soft-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Mejoras en las estadísticas con gradientes suaves */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
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

        .stat-card:nth-child(2)::before {
            background: var(--info-gradient);
        }

        .stat-card:nth-child(3)::before {
            background: var(--success-gradient);
        }

        .stat-card:nth-child(4)::before {
            background: var(--warning-gradient);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--soft-shadow-lg);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-gradient);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 8px rgba(139, 69, 19, 0.3);
        }

        .stat-card:nth-child(2) .stat-icon {
            background: var(--info-gradient);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: var(--success-gradient);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: var(--warning-gradient);
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        /* Tabla mejorada con colores suaves */
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .table-header i {
            color: #8B4513;
        }

        .table-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--success-gradient);
            color: white;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }

        /* Avatares de usuario */
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 2px 4px rgba(139, 69, 19, 0.3);
            flex-shrink: 0;
        }

        .user-info-enhanced {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .top-user-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background: var(--warning-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
        }

        .top-user-row {
            background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 5%, transparent 5%);
        }

        /* Headers con iconos */
        .table-icon {
            width: 16px;
            height: 16px;
            margin-right: 0.5rem;
            color: #6b7280;
        }

        .enhanced-activity-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1rem 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            border-bottom: 2px solid #e5e7eb;
            white-space: nowrap;
        }

        .enhanced-activity-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .enhanced-activity-table tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        /* Badges mejorados con gradientes */
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            box-shadow: var(--soft-shadow);
        }

        .role-admin {
            background: var(--info-gradient);
            color: white;
        }

        .role-manager {
            background: var(--warning-gradient);
            color: white;
        }

        .role-user {
            background: var(--success-gradient);
            color: white;
        }

        .badge-activity {
            background: var(--primary-gradient);
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 2px 4px rgba(139, 69, 19, 0.3);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }

        /* Barra de actividad */
        .activity-count {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-start;
        }

        .activity-bar {
            width: 60px;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }

        .activity-fill {
            height: 100%;
            background: var(--primary-gradient);
            transition: width 0.3s ease;
            border-radius: 2px;
        }

        /* Datetime mejorado */
        .enhanced-datetime {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .relative-time {
            font-size: 0.7rem;
            color: #8B4513;
            font-weight: 500;
            background: rgba(139, 69, 19, 0.1);
            padding: 2px 6px;
            border-radius: 8px;
            align-self: flex-start;
        }

        /* Estado vacío mejorado */
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

        /* Filtros mejorados */
        .reports-filters {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
            position: relative;
            overflow: hidden;
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        /* Botones de exportación mejorados */
        .export-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--soft-shadow);
            border: 1px solid #e5e7eb;
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

        /* Responsividad mejorada */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-icon {
                width: 50px;
                height: 50px;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 0.75rem;
            }
            
            .enhanced-activity-table th:nth-child(3),
            .enhanced-activity-table td:nth-child(3) {
                display: none; /* Ocultar rol en móvil */
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .enhanced-activity-table th:nth-child(2),
            .enhanced-activity-table td:nth-child(2) {
                display: none; /* Ocultar email en móvil pequeño */
            }
        }

        .data-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .reports-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        /* Filtros mejorados */
        .reports-filters {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }

        .reports-filters h3 {
            margin: 0 0 20px 0;
            color: #1f2937;
            font-size: 18px;
            font-weight: 600;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-weight: 500;
            font-size: 14px;
            color: #374151;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>