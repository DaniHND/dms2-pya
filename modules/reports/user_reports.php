<?php
// modules/reports/user_reports.php
// Reportes por usuario con diseño consistente - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtrado
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$selectedUserId = $_GET['user_id'] ?? '';

// Función para obtener usuarios con estadísticas - VERSIÓN FINAL
function getUsersWithStats($currentUser, $dateFrom, $dateTo, $selectedUserId = '')
{
    try {
        // PASO 1: Primero obtener usuarios básicos sin subconsultas
        if ($currentUser['role'] === 'admin') {
            $whereCondition = '';
            $params = [];
            
            if (!empty($selectedUserId)) {
                $whereCondition = ' AND u.id = :selected_user_id';
                $params['selected_user_id'] = $selectedUserId;
            }
            
            $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                             u.last_login, u.created_at, u.status,
                             COALESCE(c.name, 'Sin empresa') as company_name
                      FROM users u
                      LEFT JOIN companies c ON u.company_id = c.id
                      WHERE 1=1 $whereCondition
                      ORDER BY u.first_name, u.last_name";
            
        } else {
            // Usuario normal solo puede ver sus propios datos
            $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                             u.last_login, u.created_at, u.status,
                             COALESCE(c.name, 'Sin empresa') as company_name
                      FROM users u
                      LEFT JOIN companies c ON u.company_id = c.id
                      WHERE u.id = :current_user_id";
            
            $params = ['current_user_id' => $currentUser['id']];
        }

        $result = fetchAll($query, $params);
        
        if (!is_array($result) || empty($result)) {
            return [];
        }
        
        // PASO 2: Agregar estadísticas a cada usuario
        foreach ($result as &$user) {
            // Contar actividades
            $activityQuery = "SELECT COUNT(*) as count FROM activity_logs 
                             WHERE user_id = :user_id 
                             AND created_at >= :date_from 
                             AND created_at <= :date_to";
            $activityParams = [
                'user_id' => $user['id'],
                'date_from' => $dateFrom . ' 00:00:00',
                'date_to' => $dateTo . ' 23:59:59'
            ];
            $activityResult = fetchOne($activityQuery, $activityParams);
            $user['activity_count'] = $activityResult['count'] ?? 0;
            
            // Contar documentos (verificar si la tabla existe)
            try {
                $docsQuery = "SELECT COUNT(*) as count FROM documents 
                             WHERE uploaded_by = :user_id 
                             AND created_at >= :date_from 
                             AND created_at <= :date_to";
                $docsResult = fetchOne($docsQuery, $activityParams);
                $user['documents_uploaded'] = $docsResult['count'] ?? 0;
            } catch (Exception $e) {
                $user['documents_uploaded'] = 0;
            }
            
            // Contar descargas
            $downloadQuery = "SELECT COUNT(*) as count FROM activity_logs 
                             WHERE user_id = :user_id 
                             AND (action LIKE '%download%' OR action = 'download_document')
                             AND created_at >= :date_from 
                             AND created_at <= :date_to";
            $downloadResult = fetchOne($downloadQuery, $activityParams);
            $user['downloads_count'] = $downloadResult['count'] ?? 0;
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error en getUsersWithStats: " . $e->getMessage());
        return [];
    }
}

// Función para obtener usuarios para el filtro
function getUsersForFilter($currentUser)
{
    try {
        if ($currentUser['role'] === 'admin') {
            $query = "SELECT id, first_name, last_name, username FROM users WHERE status = 'active' ORDER BY first_name, last_name";
            $result = fetchAll($query, []);
            return is_array($result) ? $result : [];
        }
        return []; // Usuarios normales no necesitan filtro
    } catch (Exception $e) {
        error_log("Error en getUsersForFilter: " . $e->getMessage());
        return [];
    }
}

// Obtener datos - VERSIÓN FINAL SIN DEBUG
try {
    $users = getUsersWithStats($currentUser, $dateFrom, $dateTo, $selectedUserId);
    if ($users === false || !is_array($users)) {
        $users = [];
    }
} catch (Exception $e) {
    error_log("Error en getUsersWithStats: " . $e->getMessage());
    $users = [];
}

// No obtener filtros si no es admin
if ($currentUser['role'] === 'admin') {
    try {
        $usersForFilter = getUsersForFilter($currentUser);
        if ($usersForFilter === false || !is_array($usersForFilter)) {
            $usersForFilter = [];
        }
    } catch (Exception $e) {
        error_log("Error en getUsersForFilter: " . $e->getMessage());
        $usersForFilter = [];
    }
} else {
    $usersForFilter = [];
}

$totalUsers = count($users);

// Calcular estadísticas generales
$totalActivities = 0;
$totalDocuments = 0;
$totalDownloads = 0;

if (!empty($users) && is_array($users)) {
    foreach ($users as $user) {
        $totalActivities += (int)($user['activity_count'] ?? 0);
        $totalDocuments += (int)($user['documents_uploaded'] ?? 0);
        $totalDownloads += (int)($user['downloads_count'] ?? 0);
    }
}

// Registrar acceso
logActivity($currentUser['id'], 'view_user_reports', 'reports', null, 'Usuario accedió a reportes por usuario');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes por Usuario - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reports.css">
    <script src="https://unpkg.com/feather-icons"></script>
</head>

<body class="dashboard-layout reports-page">
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
                <h1>Reportes por Usuario</h1>
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

        <!-- Contenido -->
        <div class="reports-content">
            <!-- Navegación de reportes -->
            <div class="reports-nav-breadcrumb">
                <a href="index.php" class="breadcrumb-link">
                    <i data-feather="arrow-left"></i>
                    Volver a Reportes
                </a>
            </div>

            <!-- Estadísticas principales con diseño consistente -->
            <div class="reports-stats-grid">
                <div class="reports-stat-card">
                    <div class="reports-stat-icon">
                        <i data-feather="users"></i>
                    </div>
                    <div class="reports-stat-info">
                        <div class="reports-stat-number"><?php echo number_format($totalUsers); ?></div>
                        <div class="reports-stat-label"><?php echo $currentUser['role'] === 'admin' ? 'Total Usuarios' : 'Mis Datos'; ?></div>
                    </div>
                </div>

                <div class="reports-stat-card">
                    <div class="reports-stat-icon">
                        <i data-feather="activity"></i>
                    </div>
                    <div class="reports-stat-info">
                        <div class="reports-stat-number"><?php echo number_format($totalActivities); ?></div>
                        <div class="reports-stat-label">Total Actividades</div>
                    </div>
                </div>

                <div class="reports-stat-card">
                    <div class="reports-stat-icon">
                        <i data-feather="file-text"></i>
                    </div>
                    <div class="reports-stat-info">
                        <div class="reports-stat-number"><?php echo number_format($totalDocuments); ?></div>
                        <div class="reports-stat-label">Documentos Subidos</div>
                    </div>
                </div>

                <div class="reports-stat-card">
                    <div class="reports-stat-icon">
                        <i data-feather="download"></i>
                    </div>
                    <div class="reports-stat-info">
                        <div class="reports-stat-number"><?php echo number_format($totalDownloads); ?></div>
                        <div class="reports-stat-label">Total Descargas</div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="reports-filters">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" action="">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="date_from">Desde</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" required>
                        </div>
                        <div class="filter-group">
                            <label for="date_to">Hasta</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" required>
                        </div>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                        <div class="filter-group">
                            <label for="user_id">Usuario Específico</label>
                            <select id="user_id" name="user_id">
                                <option value="">Todos los usuarios</option>
                                <?php foreach ($usersForFilter as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"
                                        <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="filters-actions">
                        <button type="submit" class="btn-filter">
                            <i data-feather="search"></i>
                            Filtrar
                        </button>
                        <a href="user_reports.php" class="btn-filter secondary">
                            <i data-feather="x"></i>
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabla de usuarios - Mostrar siempre con datos -->
            <?php if (!empty($users)): ?>
            <div class="activity-table-container">
                <div class="activity-table-wrapper">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>USUARIO</th>
                                <th>EMPRESA</th>
                                <th>ROL</th>
                                <th>ACTIVIDADES</th>
                                <th>DOCUMENTOS</th>
                                <th>DESCARGAS</th>
                                <th>ÚLTIMO ACCESO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="user-column">
                                    <div class="user-primary"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></div>
                                    <div class="user-secondary">@<?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
                                    <div class="user-secondary"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                                </td>
                                <td class="company-column">
                                    <?php echo htmlspecialchars($user['company_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="description-column">
                                    <span class="role-badge role-<?php echo $user['role'] ?? 'user'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($user['role'] ?? 'user')); ?>
                                    </span>
                                </td>
                                <td class="date-column">
                                    <div class="date-primary"><?php echo number_format($user['activity_count'] ?? 0); ?></div>
                                    <div class="time-secondary">actividades</div>
                                </td>
                                <td class="date-column">
                                    <div class="date-primary"><?php echo number_format($user['documents_uploaded'] ?? 0); ?></div>
                                    <div class="time-secondary">subidos</div>
                                </td>
                                <td class="date-column">
                                    <div class="date-primary"><?php echo number_format($user['downloads_count'] ?? 0); ?></div>
                                    <div class="time-secondary">descargas</div>
                                </td>
                                <td class="date-column">
                                    <?php if (!empty($user['last_login'])): ?>
                                        <div class="date-primary"><?php echo date('d/m/Y', strtotime($user['last_login'])); ?></div>
                                        <div class="time-secondary"><?php echo date('H:i', strtotime($user['last_login'])); ?></div>
                                    <?php else: ?>
                                        <div class="user-secondary">Nunca</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
                <!-- Mensaje cuando no hay datos -->
                <div class="empty-state">
                    <i data-feather="users"></i>
                    <p>No se encontraron usuarios en el sistema.</p>
                    <p>Contacte al administrador del sistema.</p>
                </div>
            <?php endif; ?>

            <!-- Exportar datos - Mostrar siempre si hay usuarios -->
            <?php if (!empty($users)): ?>
            <div class="export-section">
                <h3>Exportar Datos</h3>
                <div class="export-buttons">
                    <a href="export.php?type=user_reports&format=csv&<?php echo http_build_query($_GET); ?>" class="export-btn csv-btn">
                        <i data-feather="file-text"></i>
                        Descargar CSV
                    </a>
                    <a href="export.php?type=user_reports&format=excel&<?php echo http_build_query($_GET); ?>" class="export-btn excel-btn">
                        <i data-feather="grid"></i>
                        Descargar Excel
                    </a>
                    <a href="export.php?type=user_reports&format=pdf&<?php echo http_build_query($_GET); ?>" class="export-btn pdf-btn">
                        <i data-feather="file"></i>
                        Descargar PDF
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Scripts -->
    <script>
        // Inicializar Feather Icons
        feather.replace();

        // Actualizar tiempo
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('es-ES', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }

        updateTime();
        setInterval(updateTime, 60000);

        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebar && mainContent) {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
            }
        }

        // Función placeholder para próximamente
        function showComingSoon(feature) {
            alert(`${feature} estará disponible próximamente.`);
        }

        // Validación de fechas
        document.getElementById('date_from').addEventListener('change', function() {
            const dateFrom = new Date(this.value);
            const dateTo = new Date(document.getElementById('date_to').value);
            
            if (dateFrom > dateTo) {
                document.getElementById('date_to').value = this.value;
            }
        });

        document.getElementById('date_to').addEventListener('change', function() {
            const dateFrom = new Date(document.getElementById('date_from').value);
            const dateTo = new Date(this.value);
            
            if (dateTo < dateFrom) {
                document.getElementById('date_from').value = this.value;
            }
        });
    </script>
</body>
</html>