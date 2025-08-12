<?php
// modules/reports/user_reports.php
// Reportes de usuarios - Versión corregida para errores

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar autenticación
SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtrado
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$selectedUserId = $_GET['user_id'] ?? '';

// Función helper para obtener nombre completo
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

// Obtener datos detallados de usuarios CON último acceso - CON VALIDACIÓN
$usersData = [];
try {
    $whereClause = "WHERE u.status = 'active'";
    $params = [];
    
    if ($currentUser['role'] !== 'admin') {
        $whereClause .= " AND u.company_id = ? AND u.role != 'admin'";
        $params[] = $currentUser['company_id'];
    }
    
    if (!empty($selectedUserId)) {
        $whereClause .= " AND u.id = ?";
        $params[] = $selectedUserId;
    }
    
    // Consulta básica sin subconsultas complejas
    $query = "SELECT 
                u.id,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                u.role,
                u.created_at as registration_date,
                COALESCE(c.name, 'Sin empresa') as company_name
              FROM users u
              LEFT JOIN companies c ON u.company_id = c.id
              $whereClause
              ORDER BY u.first_name, u.last_name";
    
    $result = fetchAll($query, $params);
    
    // VALIDAR QUE EL RESULTADO SEA UN ARRAY
    $usersData = is_array($result) ? $result : [];
    
    // Si tenemos usuarios, ahora calculamos las estadísticas una por una de forma segura
    if (!empty($usersData)) {
        foreach ($usersData as $index => $user) {
            
            // Documentos subidos en el período (corregir campo: uploaded_by en vez de user_id)
            try {
                $docsResult = fetchOne("SELECT COUNT(*) as count FROM documents WHERE uploaded_by = ? AND created_at >= ? AND created_at <= ? AND status = 'active'", 
                    [$user['id'], $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                $usersData[$index]['documents_uploaded_period'] = $docsResult['count'] ?? 0;
            } catch (Exception $e) {
                $usersData[$index]['documents_uploaded_period'] = 0;
            }
            
            // Total documentos (corregir campo: uploaded_by)
            try {
                $totalDocsResult = fetchOne("SELECT COUNT(*) as count FROM documents WHERE uploaded_by = ? AND status = 'active'", [$user['id']]);
                $usersData[$index]['total_documents'] = $totalDocsResult['count'] ?? 0;
            } catch (Exception $e) {
                $usersData[$index]['total_documents'] = 0;
            }
            
            // Descargas en el período (verificar si existe la tabla activity_logs)
            try {
                $downloadsResult = fetchOne("SELECT COUNT(*) as count FROM activity_logs WHERE user_id = ? AND action IN ('download', 'view_document') AND created_at >= ? AND created_at <= ?", 
                    [$user['id'], $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                $usersData[$index]['downloads_count'] = $downloadsResult['count'] ?? 0;
            } catch (Exception $e) {
                $usersData[$index]['downloads_count'] = 0;
            }
            
            // Último acceso (verificar si existe la tabla activity_logs)
            try {
                $lastAccessResult = fetchOne("SELECT MAX(created_at) as last_access FROM activity_logs WHERE user_id = ? AND action IN ('login', 'view', 'access')", [$user['id']]);
                if ($lastAccessResult && $lastAccessResult['last_access']) {
                    $usersData[$index]['last_access'] = $lastAccessResult['last_access'];
                } else {
                    // Si no hay registros de actividad, usar la fecha de registro
                    $usersData[$index]['last_access'] = $user['registration_date'];
                }
            } catch (Exception $e) {
                $usersData[$index]['last_access'] = $user['registration_date'];
            }
            
            // Total actividades en el período
            try {
                $activitiesResult = fetchOne("SELECT COUNT(*) as count FROM activity_logs WHERE user_id = ? AND created_at >= ? AND created_at <= ?", 
                    [$user['id'], $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                $usersData[$index]['total_activities'] = $activitiesResult['count'] ?? 0;
            } catch (Exception $e) {
                $usersData[$index]['total_activities'] = 0;
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Error obteniendo datos detallados: " . $e->getMessage());
    $usersData = [];
}
// Stats - CON VALIDACIONES
$totalUsers = is_array($usersData) ? count($usersData) : 0;

$activeUsers = 0;
if (is_array($usersData)) {
    $activeUsers = count(array_filter($usersData, function ($user) {
        return isset($user['last_access']) && $user['last_access'] && strtotime($user['last_access']) > strtotime('-30 days');
    }));
}

$totalDocs = 0;
$totalDownloads = 0;
if (is_array($usersData)) {
    $totalDocs = array_sum(array_column($usersData, 'documents_uploaded_period'));
    $totalDownloads = array_sum(array_column($usersData, 'downloads_count'));
}

// Registrar acceso
if (function_exists('logActivity')) {
    logActivity($currentUser['id'], 'view_user_reports', 'reports', null, 'Usuario accedió al reporte de usuarios');
}
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
            <!-- Debug Info - TEMPORAL -->
            <?php if (isset($_GET['debug'])): ?>
                <div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; font-family: monospace; font-size: 0.875rem;">
                    <strong>Debug Info:</strong><br>
                    Total usuarios: <?php echo count($users); ?><br>
                    Total datos detallados: <?php echo count($usersData); ?><br>
                    Usuario actual: <?php echo htmlspecialchars($currentUser['role']); ?><br>
                    Fechas: <?php echo htmlspecialchars($dateFrom); ?> a <?php echo htmlspecialchars($dateTo); ?><br>
                    <?php if (!empty($usersData)): ?>
                        Primer usuario: <?php echo htmlspecialchars($usersData[0]['first_name'] ?? 'N/A'); ?><br>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

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
                        <div class="stat-number"><?php echo number_format($totalUsers); ?></div>
                        <div class="stat-label"><?php echo $currentUser['role'] === 'admin' ? 'Total Usuarios' : 'Usuarios de mi Empresa'; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="user-check"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($activeUsers); ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="upload"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($totalDocs); ?></div>
                        <div class="stat-label">Docs. Subidos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i data-feather="download"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($totalDownloads); ?></div>
                        <div class="stat-label">Total Descargas</div>
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
                            <?php if (is_array($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (@' . $user['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
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

            <!-- Tabla de usuarios detallada -->
            <!-- Tabla de usuarios detallada CON CLASES DE ACTIVITY_LOG -->
            <div class="activity-table-container">
                <div class="activity-controls">
                    <h3>
                        <i data-feather="users"></i>
                        Actividad de Usuarios
                        <?php if ($selectedUserId): ?>
                            <?php
                            $selectedUser = array_filter($users, function ($u) use ($selectedUserId) {
                                return $u['id'] == $selectedUserId;
                            });
                            $selectedUser = reset($selectedUser);
                            if ($selectedUser) {
                                echo ' - ' . htmlspecialchars($selectedUser['first_name'] . ' ' . $selectedUser['last_name']);
                            }
                            ?>
                        <?php endif; ?>
                    </h3>
                    <div class="activity-info">
                        <span class="record-count"><?php echo number_format($totalUsers); ?> usuarios</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Empresa</th>
                                <th>Rol</th>
                                <th>Docs. Subidos</th>
                                <th>Descargas</th>
                                <th>Último Acceso</th>
                                <th>Fecha Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($usersData) && is_array($usersData)): ?>
                                <?php foreach ($usersData as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <span class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                                <small class="username">@<?php echo htmlspecialchars($user['username']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="ip-address">@<?php echo htmlspecialchars($user['username']); ?></code>
                                        </td>
                                        <td>
                                            <span class="company-name"><?php echo htmlspecialchars($user['email']); ?></span>
                                        </td>
                                        <td>
                                            <span class="company-name"><?php echo htmlspecialchars($user['company_name'] ?? 'Sin empresa'); ?></span>
                                        </td>
                                        <td>
                                            <span class="action-badge action-<?php echo strtolower($user['role']); ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="activity-details">
                                                <span class="badge badge-info"><?php echo number_format($user['documents_uploaded_period'] ?? 0); ?></span>
                                                <?php if (isset($user['total_documents']) && $user['total_documents'] > 0): ?>
                                                    <small style="display: block; color: #666; font-size: 0.7rem; margin-top: 2px;">(<?php echo number_format($user['total_documents']); ?> total)</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-success"><?php echo number_format($user['downloads_count'] ?? 0); ?></span>
                                        </td>
                                        <td>
                                            <?php if (isset($user['last_access']) && $user['last_access'] && $user['last_access'] != $user['registration_date']): ?>
                                                <div class="datetime-info">
                                                    <span class="date"><?php echo date('d/m/Y', strtotime($user['last_access'])); ?></span>
                                                    <small class="time"><?php echo date('H:i:s', strtotime($user['last_access'])); ?></small>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Sin acceso</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="datetime-info">
                                                <span class="date"><?php echo date('d/m/Y', strtotime($user['registration_date'])); ?></span>
                                                <small class="time"><?php echo date('H:i:s', strtotime($user['registration_date'])); ?></small>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="empty-state">
                                        <div class="empty-content">
                                            <i data-feather="search"></i>
                                            <h4>No se encontraron usuarios</h4>
                                            <p>No hay usuarios que coincidan con los filtros seleccionados.</p>
                                            <a href="user_reports.php" class="btn btn-primary">Ver todos los usuarios</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
            if (e.target.matches('#date_from, #date_to, #user_id')) {
                autoFilter();
            }
        });

        function autoFilter() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const userId = document.getElementById('user_id').value;

            const params = new URLSearchParams();
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);
            if (userId) params.set('user_id', userId);

            window.location.href = window.location.pathname + '?' + params.toString();
        }

        function exportarDatos(formato) {
            const urlParams = new URLSearchParams(window.location.search);
            const exportUrl = 'export.php?format=' + formato + '&type=user_reports&modal=1&' + urlParams.toString();

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

    <!-- CSS Incluido del mensaje anterior -->
    <style>
        /* Todo el CSS del mensaje anterior va aquí */
        :root {
            --primary-gradient: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            --secondary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            --success-gradient: linear-gradient(135deg, #10B981 0%, #059669 100%);
            --warning-gradient: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            --info-gradient: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            --soft-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --soft-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* [Todo el resto del CSS del mensaje anterior] */
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

        .username-badge {
            background: rgba(139, 69, 19, 0.1);
            color: #8B4513;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-family: monospace;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .company-name {
            color: #374151;
            font-weight: 500;
            font-size: 0.875rem;
        }

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

        .badge-documents {
            background: var(--info-gradient);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }

        .badge-downloads {
            background: var(--success-gradient);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }

        .total-documents {
            display: block;
            color: #6b7280;
            font-size: 0.7rem;
            margin-top: 2px;
        }

        .documents-stats {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
        }

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

        .no-access {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            color: #9ca3af;
            font-size: 0.8rem;
        }

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
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
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

        /* Responsive */
        @media (max-width: 1200px) {

            .enhanced-activity-table th:nth-child(4),
            .enhanced-activity-table td:nth-child(4) {
                display: none;
            }
        }

        @media (max-width: 968px) {

            .enhanced-activity-table th:nth-child(7),
            .enhanced-activity-table td:nth-child(7) {
                display: none;
            }

            .enhanced-activity-table th:nth-child(8),
            .enhanced-activity-table td:nth-child(8) {
                display: none;
            }
        }

        @media (max-width: 768px) {

            .enhanced-activity-table th:nth-child(3),
            .enhanced-activity-table td:nth-child(3) {
                display: none;
            }

            .enhanced-activity-table th:nth-child(6),
            .enhanced-activity-table td:nth-child(6) {
                display: none;
            }

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
        }

        @media (max-width: 580px) {

            .enhanced-activity-table th:nth-child(5),
            .enhanced-activity-table td:nth-child(5) {
                display: none;
            }

            .enhanced-activity-table th:nth-child(9),
            .enhanced-activity-table td:nth-child(9) {
                display: none;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .pdf-modal-content {
                width: 95%;
                height: 95%;
                margin: 2.5% auto;
            }

            .pdf-modal-header,
            .pdf-modal-body {
                padding: 15px;
            }

            .pdf-modal-header h3 {
                font-size: 1rem;
            }

            .pdf-actions {
                flex-direction: column;
            }

            .pdf-actions button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</body>

</html>