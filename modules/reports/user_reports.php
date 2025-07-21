<?php
// modules/reports/user_reports.php
// Reportes por usuario - VERSIÓN FINAL CORREGIDA

require_once '../../config/session.php';
require_once '../../config/database.php';

// Incluir funciones auxiliares si existe
if (file_exists('../../includes/functions.php')) {
    require_once '../../includes/functions.php';
}

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de filtrado
$selectedUserId = $_GET['user_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$filterRole = $_GET['role'] ?? '';
$filterCompany = $_GET['company'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Función para obtener usuarios con estadísticas y filtros
function getUsersWithStats($currentUser, $dateFrom, $dateTo, $selectedUserId = '', $search = '', $filterRole = '', $filterCompany = '', $filterStatus = '', $limit = 20, $offset = 0)
{
    try {
        $whereConditions = ["u.status != 'deleted'"];
        $params = [
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59'
        ];

        // Filtro de búsqueda por texto
        if (!empty($search)) {
            $whereConditions[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.username LIKE :search OR u.email LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        // Filtro por rol
        if (!empty($filterRole)) {
            $whereConditions[] = "u.role = :filter_role";
            $params['filter_role'] = $filterRole;
        }

        // Filtro por empresa
        if (!empty($filterCompany)) {
            $whereConditions[] = "u.company_id = :filter_company";
            $params['filter_company'] = $filterCompany;
        }

        // Filtro por estado
        if (!empty($filterStatus)) {
            $whereConditions[] = "u.status = :filter_status";
            $params['filter_status'] = $filterStatus;
        } else {
            $whereConditions[] = "u.status = 'active'";
        }

        if ($currentUser['role'] === 'admin') {
            if ($selectedUserId) {
                $whereConditions[] = "u.id = :selected_user_id";
                $params['selected_user_id'] = $selectedUserId;
            }
        } else {
            $whereConditions[] = "u.id = :current_user_id";
            $params['current_user_id'] = $currentUser['id'];
        }

        $whereClause = implode(' AND ', $whereConditions);

        $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, 
                         u.last_login, u.created_at, u.status, c.name as company_name,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.created_at >= :date_from AND al.created_at <= :date_to) as activity_count,
                         (SELECT COUNT(*) FROM documents d WHERE d.uploaded_by = u.id 
                          AND d.created_at >= :date_from AND d.created_at <= :date_to) as documents_uploaded,
                         (SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id 
                          AND al.action = 'download' AND al.created_at >= :date_from AND al.created_at <= :date_to) as downloads_count
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE $whereClause
                  ORDER BY u.first_name, u.last_name
                  LIMIT $limit OFFSET $offset";
        
        $result = fetchAll($query, $params);
        return is_array($result) ? $result : [];
        
    } catch (Exception $e) {
        error_log("Error en getUsersWithStats: " . $e->getMessage());
        return [];
    }
}

// Función para obtener actividad de un usuario
function getUserActivity($userId, $dateFrom, $dateTo, $currentUser, $limit = 50)
{
    try {
        if ($currentUser['role'] !== 'admin' && $userId != $currentUser['id']) {
            return [];
        }

        $query = "SELECT al.*, u.first_name, u.last_name, u.username
                  FROM activity_logs al
                  LEFT JOIN users u ON al.user_id = u.id
                  WHERE al.user_id = :user_id 
                  AND al.created_at >= :date_from 
                  AND al.created_at <= :date_to
                  ORDER BY al.created_at DESC
                  LIMIT :limit";

        $params = [
            'user_id' => $userId,
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59',
            'limit' => intval($limit)
        ];

        $result = fetchAll($query, $params);
        return is_array($result) ? $result : [];
        
    } catch (Exception $e) {
        error_log("Error en getUserActivity: " . $e->getMessage());
        return [];
    }
}

// Función para obtener estadísticas por acción de un usuario
function getUserActionStats($userId, $dateFrom, $dateTo, $currentUser)
{
    try {
        if ($currentUser['role'] !== 'admin' && $userId != $currentUser['id']) {
            return [];
        }
        
        $query = "SELECT action, COUNT(*) as count
                  FROM activity_logs
                  WHERE user_id = :user_id 
                  AND created_at >= :date_from 
                  AND created_at <= :date_to
                  AND action IN ('upload', 'download', 'view', 'login', 'logout')
                  GROUP BY action
                  ORDER BY count DESC";

        $params = [
            'user_id' => $userId,
            'date_from' => $dateFrom . ' 00:00:00',
            'date_to' => $dateTo . ' 23:59:59'
        ];

        $result = fetchAll($query, $params);
        return is_array($result) ? $result : [];
        
    } catch (Exception $e) {
        error_log("Error en getUserActionStats: " . $e->getMessage());
        return [];
    }
}

// Función para obtener total de usuarios con filtros
function getTotalUsers($currentUser, $dateFrom, $dateTo, $selectedUserId, $search = '', $filterRole = '', $filterCompany = '', $filterStatus = '')
{
    try {
        $whereConditions = ["u.status != 'deleted'"];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.username LIKE :search OR u.email LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if (!empty($filterRole)) {
            $whereConditions[] = "u.role = :filter_role";
            $params['filter_role'] = $filterRole;
        }

        if (!empty($filterCompany)) {
            $whereConditions[] = "u.company_id = :filter_company";
            $params['filter_company'] = $filterCompany;
        }

        if (!empty($filterStatus)) {
            $whereConditions[] = "u.status = :filter_status";
            $params['filter_status'] = $filterStatus;
        } else {
            $whereConditions[] = "u.status = 'active'";
        }

        if ($currentUser['role'] === 'admin') {
            if ($selectedUserId) {
                return 1;
            }
        } else {
            return 1;
        }

        $whereClause = implode(' AND ', $whereConditions);
        
        $query = "SELECT COUNT(*) as total FROM users u WHERE $whereClause";
        $result = fetchOne($query, $params);
        
        if ($result === false || !isset($result['total'])) {
            return 0;
        }
        
        return intval($result['total']);
        
    } catch (Exception $e) {
        error_log("Error en getTotalUsers: " . $e->getMessage());
        return 0;
    }
}

// Función para obtener opciones de filtros
function getFilterOptions($currentUser)
{
    $options = [];
    
    try {
        $options['roles'] = [
            'admin' => 'Administrador',
            'manager' => 'Gerente',
            'user' => 'Usuario',
            'viewer' => 'Visualizador'
        ];

        $options['statuses'] = [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'suspended' => 'Suspendido'
        ];

        if ($currentUser['role'] === 'admin') {
            $query = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
            $result = fetchAll($query);
            $options['companies'] = is_array($result) ? $result : [];

            $query = "SELECT id, first_name, last_name, username FROM users WHERE status = 'active' ORDER BY first_name, last_name";
            $result = fetchAll($query);
            $options['users'] = is_array($result) ? $result : [];
        } else {
            $options['companies'] = [];
            $options['users'] = [];
        }

        return $options;
        
    } catch (Exception $e) {
        error_log("Error en getFilterOptions: " . $e->getMessage());
        return [
            'roles' => [],
            'statuses' => [],
            'companies' => [],
            'users' => []
        ];
    }
}

// Obtener datos con manejo de errores
try {
    $filterOptions = getFilterOptions($currentUser);
    $users = getUsersWithStats($currentUser, $dateFrom, $dateTo, $selectedUserId, $search, $filterRole, $filterCompany, $filterStatus, $limit, $offset);
    $totalUsers = getTotalUsers($currentUser, $dateFrom, $dateTo, $selectedUserId, $search, $filterRole, $filterCompany, $filterStatus);
    $selectedUserActivity = [];
    $selectedUserActionStats = [];
    $selectedUserInfo = null;

    // Calcular paginación
    $totalPages = ceil($totalUsers / $limit);
    $startRecord = $totalUsers > 0 ? (($page - 1) * $limit) + 1 : 0;
    $endRecord = min($page * $limit, $totalUsers);

    // Validación de seguridad para usuario seleccionado
    if ($selectedUserId) {
        if ($currentUser['role'] !== 'admin' && $selectedUserId != $currentUser['id']) {
            header('Location: user_reports.php?error=access_denied');
            exit();
        }
        
        $selectedUserActivity = getUserActivity($selectedUserId, $dateFrom, $dateTo, $currentUser);
        $selectedUserActionStats = getUserActionStats($selectedUserId, $dateFrom, $dateTo, $currentUser);

        if (is_array($users)) {
            foreach ($users as $user) {
                if ($user['id'] == $selectedUserId) {
                    $selectedUserInfo = $user;
                    break;
                }
            }
        }
    }

    logActivity($currentUser['id'], 'view_user_reports', 'reports', null, 'Usuario accedió a reportes por usuario');
    
} catch (Exception $e) {
    error_log("Error general en user_reports.php: " . $e->getMessage());
    $filterOptions = ['roles' => [], 'statuses' => [], 'companies' => [], 'users' => []];
    $users = [];
    $totalUsers = 0;
    $totalPages = 0;
    $startRecord = 0;
    $endRecord = 0;
    $selectedUserActivity = [];
    $selectedUserActionStats = [];
    $selectedUserInfo = null;
}
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
    <link rel="stylesheet" href="../../assets/css/modal.css">
    <link rel="stylesheet" href="../../assets/css/summary.css">
    <link rel="stylesheet" href="../../assets/css/users2.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <h1>Reportes por Usuario</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(SessionManager::getFullName()); ?></div>
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

            <!-- Mensaje de error de acceso denegado -->
            <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
                <div class="error-message">
                    <i data-feather="alert-triangle"></i>
                    <span>Acceso denegado: Solo puede ver sus propios datos de usuario.</span>
                </div>
            <?php endif; ?>

            <!-- Resumen de resultados -->
            <div class="results-summary">
                <div class="summary-stats">
                    <div class="stat-item">
                        <i data-feather="users"></i>
                        <span class="stat-number"><?php echo number_format($totalUsers); ?></span>
                        <span class="stat-label"><?php echo $currentUser['role'] === 'admin' ? 'Total Usuarios' : 'Mis Datos'; ?></span>
                    </div>
                    <div class="stat-item">
                        <i data-feather="calendar"></i>
                        <span class="stat-number"><?php echo date('d/m/Y', strtotime($dateFrom)) . ' - ' . date('d/m/Y', strtotime($dateTo)); ?></span>
                        <span class="stat-label">Período Analizado</span>
                    </div>
                    <div class="stat-item">
                        <i data-feather="activity"></i>
                        <span class="stat-number"><?php echo is_array($users) ? count($users) : 0; ?></span>
                        <span class="stat-label">Usuarios Mostrados</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="reports-filters">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" action="">
                    <div class="filters-row">
                        <!-- Filtro de búsqueda por texto -->
                        <div class="filter-group">
                            <label for="search">Buscar Usuario</label>
                            <input type="text" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Nombre, usuario o email...">
                        </div>

                        <!-- Filtro por fechas -->
                        <div class="filter-group">
                            <label for="date_from">Desde</label>
                            <input type="date" id="date_from" name="date_from" 
                                   value="<?php echo htmlspecialchars($dateFrom); ?>" required>
                        </div>

                        <div class="filter-group">
                            <label for="date_to">Hasta</label>
                            <input type="date" id="date_to" name="date_to" 
                                   value="<?php echo htmlspecialchars($dateTo); ?>" required>
                        </div>

                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <!-- Filtro por rol -->
                            <div class="filter-group">
                                <label for="role">Rol</label>
                                <select id="role" name="role">
                                    <option value="">Todos los roles</option>
                                    <?php foreach ($filterOptions['roles'] as $roleKey => $roleName): ?>
                                        <option value="<?php echo $roleKey; ?>" <?php echo $filterRole === $roleKey ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($roleName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Filtro por empresa -->
                            <div class="filter-group">
                                <label for="company">Empresa</label>
                                <select id="company" name="company">
                                    <option value="">Todas las empresas</option>
                                    <?php if (is_array($filterOptions['companies'])): ?>
                                        <?php foreach ($filterOptions['companies'] as $company): ?>
                                            <option value="<?php echo $company['id']; ?>" <?php echo $filterCompany == $company['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($company['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Filtro por estado -->
                            <div class="filter-group">
                                <label for="status">Estado</label>
                                <select id="status" name="status">
                                    <option value="">Todos los estados</option>
                                    <?php foreach ($filterOptions['statuses'] as $statusKey => $statusName): ?>
                                        <option value="<?php echo $statusKey; ?>" <?php echo $filterStatus === $statusKey ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($statusName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Usuario específico -->
                            <div class="filter-group">
                                <label for="user_id">Usuario Específico</label>
                                <select id="user_id" name="user_id">
                                    <option value="">Seleccionar usuario...</option>
                                    <?php if (is_array($filterOptions['users'])): ?>
                                        <?php foreach ($filterOptions['users'] as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (@' . $user['username'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Botones de filtros -->
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="search"></i>
                            Filtrar
                        </button>
                        <a href="user_reports.php" class="btn btn-secondary">
                            <i data-feather="x"></i>
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Detalles del usuario seleccionado -->
            <?php if ($selectedUserInfo): ?>
                <div class="selected-user-details">
                    <div class="user-detail-header">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($selectedUserInfo['first_name'], 0, 1) . substr($selectedUserInfo['last_name'], 0, 1)); ?>
                        </div>
                        <div class="user-detail-info">
                            <h2><?php echo htmlspecialchars($selectedUserInfo['first_name'] . ' ' . $selectedUserInfo['last_name']); ?></h2>
                            <p>@<?php echo htmlspecialchars($selectedUserInfo['username']); ?></p>
                            <p><?php echo htmlspecialchars($selectedUserInfo['email']); ?></p>
                            <p><?php echo htmlspecialchars($selectedUserInfo['company_name'] ?? 'Sin empresa'); ?> - <?php echo ucfirst($selectedUserInfo['role']); ?></p>
                        </div>
                    </div>

                    <div class="user-stats-grid">
                        <div class="user-stat">
                            <div class="stat-number"><?php echo number_format($selectedUserInfo['activity_count']); ?></div>
                            <div class="stat-label">Actividades</div>
                        </div>
                        <div class="user-stat">
                            <div class="stat-number"><?php echo number_format($selectedUserInfo['documents_uploaded']); ?></div>
                            <div class="stat-label">Documentos Subidos</div>
                        </div>
                        <div class="user-stat">
                            <div class="stat-number"><?php echo number_format($selectedUserInfo['downloads_count']); ?></div>
                            <div class="stat-label">Descargas</div>
                        </div>
                        <div class="user-stat">
                            <div class="stat-number">
                                <?php 
                                if ($selectedUserInfo['last_login']) {
                                    echo function_exists('formatDate') ? formatDate($selectedUserInfo['last_login']) : date('d/m/Y', strtotime($selectedUserInfo['last_login']));
                                } else {
                                    echo 'Nunca';
                                }
                                ?>
                            </div>
                            <div class="stat-label">Último Acceso</div>
                        </div>
                    </div>
                </div>

                <!-- Gráfico de acciones del usuario -->
                <?php if (!empty($selectedUserActionStats) && is_array($selectedUserActionStats)): ?>
                    <div class="user-chart-section">
                        <h3>Distribución de Acciones</h3>
                        <canvas id="userActionsChart"></canvas>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const userActionsCtx = document.getElementById('userActionsChart').getContext('2d');
                            new Chart(userActionsCtx, {
                                type: 'doughnut',
                                data: {
                                    labels: [
                                        <?php 
                                        $labels = [];
                                        foreach ($selectedUserActionStats as $stat) {
                                            $labels[] = "'" . ucfirst($stat['action']) . "'";
                                        }
                                        echo implode(', ', $labels);
                                        ?>
                                    ],
                                    datasets: [{
                                        data: [
                                            <?php 
                                            $values = [];
                                            foreach ($selectedUserActionStats as $stat) {
                                                $values[] = $stat['count'];
                                            }
                                            echo implode(', ', $values);
                                            ?>
                                        ],
                                        backgroundColor: [
                                            '#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336'
                                        ]
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            position: 'right'
                                        }
                                    }
                                }
                            });
                        });
                    </script>
                <?php endif; ?>

                <!-- Actividad reciente del usuario -->
                <?php if (!empty($selectedUserActivity) && is_array($selectedUserActivity)): ?>
                    <div class="user-activity-section">
                        <h3>Actividad Reciente</h3>
                        <div class="activity-list">
                            <?php foreach ($selectedUserActivity as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i data-feather="<?php 
                                            $iconMap = [
                                                'login' => 'log-in',
                                                'logout' => 'log-out',
                                                'upload' => 'upload',
                                                'download' => 'download',
                                                'view' => 'eye',
                                                'edit' => 'edit',
                                                'delete' => 'trash'
                                            ];
                                            echo $iconMap[$activity['action']] ?? 'activity';
                                        ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <strong><?php echo ucfirst($activity['action']); ?></strong>
                                        <?php if ($activity['description']): ?>
                                            <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                        <?php endif; ?>
                                        <small>
                                            <?php 
                                            if (function_exists('timeAgo')) {
                                                echo timeAgo($activity['created_at']);
                                            } else {
                                                echo date('d/m/Y H:i', strtotime($activity['created_at']));
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Tabla de usuarios con estadísticas -->
                <div class="reports-table">
                    <div class="table-header">
                        <h3>
                            <?php 
                            echo $currentUser['role'] === 'admin' ? 
                                "Usuarios y sus Estadísticas" : 
                                'Mis Estadísticas'; 
                            ?>
                        </h3>
                        <div class="pagination-info">
                            <?php if ($totalUsers > 0): ?>
                                Mostrando <?php echo number_format($startRecord); ?>-<?php echo number_format($endRecord); ?> 
                                de <?php echo number_format($totalUsers); ?> usuarios
                            <?php else: ?>
                                No se encontraron usuarios
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Empresa</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
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
                                <?php if (empty($users) || !is_array($users)): ?>
                                    <tr>
                                        <td colspan="<?php echo $currentUser['role'] === 'admin' ? '9' : '8'; ?>" class="empty-state">
                                            <i data-feather="users"></i>
                                            <p>No se encontraron usuarios</p>
                                            <?php if (!empty($search) || !empty($filterRole) || !empty($filterCompany) || !empty($filterStatus)): ?>
                                                <small>Intenta ajustar los filtros de búsqueda</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-small">
                                                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                        <br>
                                                        <small>@<?php echo htmlspecialchars($user['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['company_name'] ?? 'Sin empresa'); ?></td>
                                            <td>
                                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $user['status']; ?>">
                                                    <?php 
                                                    $statusLabels = [
                                                        'active' => 'Activo',
                                                        'inactive' => 'Inactivo',
                                                        'suspended' => 'Suspendido'
                                                    ];
                                                    echo $statusLabels[$user['status']] ?? ucfirst($user['status']);
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($user['activity_count']); ?></td>
                                            <td><?php echo number_format($user['documents_uploaded']); ?></td>
                                            <td><?php echo number_format($user['downloads_count']); ?></td>
                                            <td>
                                                <?php 
                                                if ($user['last_login']) {
                                                    if (function_exists('timeAgo')) {
                                                        echo timeAgo($user['last_login']);
                                                    } else {
                                                        echo date('d/m/Y', strtotime($user['last_login']));
                                                    }
                                                } else {
                                                    echo 'Nunca';
                                                }
                                                ?>
                                            </td>
                                            <?php if ($currentUser['role'] === 'admin'): ?>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="?user_id=<?php echo $user['id']; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>" 
                                                           class="btn btn-sm btn-primary" title="Ver Detalles">
                                                            <i data-feather="eye"></i>
                                                        </a>
                                                        <a href="../users/edit.php?id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-sm btn-secondary" title="Editar Usuario">
                                                            <i data-feather="edit"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <div class="pagination-info">
                                Página <?php echo $page; ?> de <?php echo $totalPages; ?>
                            </div>
                            <div class="pagination-controls">
                                <?php
                                // Construir URL base para paginación
                                $baseUrl = 'user_reports.php?';
                                $params = $_GET;
                                unset($params['page']);
                                $baseUrl .= http_build_query($params);
                                $baseUrl .= $params ? '&' : '';
                                ?>

                                <?php if ($page > 1): ?>
                                    <a href="<?php echo $baseUrl; ?>page=1" class="btn btn-sm btn-secondary" title="Primera página">
                                        <i data-feather="chevrons-left"></i>
                                    </a>
                                    <a href="<?php echo $baseUrl; ?>page=<?php echo ($page - 1); ?>" class="btn btn-sm btn-secondary" title="Página anterior">
                                        <i data-feather="chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <!-- Páginas numéricas -->
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <a href="<?php echo $baseUrl; ?>page=<?php echo $i; ?>" 
                                       class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?php echo $baseUrl; ?>page=<?php echo ($page + 1); ?>" class="btn btn-sm btn-secondary" title="Página siguiente">
                                        <i data-feather="chevron-right"></i>
                                    </a>
                                    <a href="<?php echo $baseUrl; ?>page=<?php echo $totalPages; ?>" class="btn btn-sm btn-secondary" title="Última página">
                                        <i data-feather="chevrons-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Sección de Exportación -->
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
        </div>
    </main>

    <!-- Modal para vista previa del PDF -->
    <div id="pdfModal" class="pdf-modal">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header">
                <h3 class="pdf-modal-title">Vista Previa del PDF - Reportes de Usuarios</h3>
                <button class="pdf-modal-close" onclick="cerrarModalPDF()">&times;</button>
            </div>
            <div class="pdf-modal-body">
                <div class="pdf-loading" id="pdfLoading">
                    <div class="spinner"></div>
                    <p>Generando vista previa del PDF...</p>
                </div>
                <iframe id="pdfIframe" class="pdf-iframe" style="display: none;"></iframe>
            </div>
            <div class="pdf-modal-footer">
                <button class="btn btn-secondary" onclick="cerrarModalPDF()">Cerrar</button>
                <button class="btn btn-primary" onclick="descargarPDF()">
                    <i data-feather="download"></i>
                    Descargar
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variables de configuración
        var currentFilters = <?php echo json_encode($_GET); ?>;
        var userActionStats = <?php echo json_encode($selectedUserActionStats); ?>;

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);

            <?php if ($selectedUserId && !empty($selectedUserActionStats)): ?>
                initUserActionsChart();
            <?php endif; ?>
        });

        // Actualizar hora
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString();
            }
        }

        // Toggle sidebar
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-collapsed');
        }

        // Función placeholder para características futuras
        function showComingSoon(feature) {
            alert(feature + ' - Próximamente disponible');
        }

        // Inicializar gráfico de acciones del usuario
        function initUserActionsChart() {
            if (userActionStats && userActionStats.length > 0) {
                const ctx = document.getElementById('userActionsChart');
                if (ctx) {
                    new Chart(ctx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: userActionStats.map(stat => stat.action.charAt(0).toUpperCase() + stat.action.slice(1)),
                            datasets: [{
                                data: userActionStats.map(stat => stat.count),
                                backgroundColor: [
                                    '#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336', '#00BCD4', '#795548'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'right'
                                }
                            }
                        }
                    });
                }
            }
        }

        // Función para exportar datos
        function exportarDatos(format) {
            mostrarCargando('Preparando exportación...');
            
            // Construir URL con filtros actuales
            var params = new URLSearchParams(currentFilters);
            params.set('type', 'user_reports');
            params.set('format', format);
            
            if (format === 'pdf') {
                // Para PDF, mostrar vista previa
                params.set('preview', '1');
                mostrarModalPDF('export.php?' + params.toString());
            } else {
                // Para CSV/Excel, descargar directamente
                params.set('download', '1');
                window.location.href = 'export.php?' + params.toString();
            }
            
            ocultarCargando();
        }

        // Funciones para el modal PDF
        function mostrarModalPDF(url) {
            const modal = document.getElementById('pdfModal');
            const iframe = document.getElementById('pdfIframe');
            const loading = document.getElementById('pdfLoading');
            
            if (modal && iframe && loading) {
                modal.style.display = 'block';
                loading.style.display = 'block';
                iframe.style.display = 'none';
                
                iframe.onload = function() {
                    loading.style.display = 'none';
                    iframe.style.display = 'block';
                };
                
                iframe.src = url;
                document.body.style.overflow = 'hidden';
            }
        }

        function cerrarModalPDF() {
            const modal = document.getElementById('pdfModal');
            const iframe = document.getElementById('pdfIframe');
            
            if (modal && iframe) {
                modal.style.display = 'none';
                iframe.src = '';
                document.body.style.overflow = 'auto';
            }
        }

        function descargarPDF() {
            var params = new URLSearchParams(currentFilters);
            params.set('type', 'user_reports');
            params.set('format', 'pdf');
            params.set('download', '1');
            
            window.open('export.php?' + params.toString(), '_blank');
        }

        // Funciones de carga
        function mostrarCargando(mensaje) {
            // Implementar indicador de carga si es necesario
            console.log(mensaje);
        }

        function ocultarCargando() {
            // Ocultar indicador de carga
            console.log('Carga completada');
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('pdfModal');
            if (event.target === modal) {
                cerrarModalPDF();
            }
        }

        // Escape key para cerrar modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModalPDF();
            }
        });
    </script>
</body>
</html>