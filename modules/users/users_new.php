<?php
// modules/users/users_new.php - Módulo de gestión de usuarios NUEVO
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar autenticación
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Obtener usuario actual con consulta directa
try {
    $currentUser = fetchOne("SELECT * FROM users WHERE id = ? AND status = 'active'", [$_SESSION['user_id']]);
    if (!$currentUser) {
        session_destroy();
        header('Location: ../../login.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Error getting current user: " . $e->getMessage());
    header('Location: ../../login.php');
    exit;
}

// Verificar permisos - solo admin
if ($currentUser['role'] !== 'admin') {
    header('Location: ../../dashboard.php?error=access_denied');
    exit;
}

// Parámetros de filtrado y paginación
$search = $_GET['search'] ?? '';
$filterRole = $_GET['role'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterCompany = $_GET['company'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Construir consulta con filtros
$whereConditions = ["1=1"]; // Condición base
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($filterRole)) {
    $whereConditions[] = "u.role = ?";
    $params[] = $filterRole;
}

if (!empty($filterStatus)) {
    $whereConditions[] = "u.status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterCompany)) {
    $whereConditions[] = "u.company_id = ?";
    $params[] = intval($filterCompany);
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Consulta principal de usuarios
$sql = "
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.username,
        u.email,
        u.role,
        u.status,
        u.download_enabled,
        u.created_at,
        u.last_login,
        c.name as company_name,
        c.id as company_id
    FROM users u
    LEFT JOIN companies c ON u.company_id = c.id
    $whereClause
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
";

$users = [];
try {
    $users = fetchAll($sql, $params);
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
}

// Obtener estadísticas
$statsSQL = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN u.status = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN u.role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN u.role = 'user' THEN 1 ELSE 0 END) as user_count,
        SUM(CASE WHEN u.role = 'viewer' THEN 1 ELSE 0 END) as viewer_count
    FROM users u
    LEFT JOIN companies c ON u.company_id = c.id
    $whereClause
";

$stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'by_role' => ['admin' => 0, 'user' => 0, 'viewer' => 0]];
try {
    $statsResult = fetchOne($statsSQL, $params);
    if ($statsResult) {
        $stats = [
            'total' => $statsResult['total'] ?? 0,
            'active' => $statsResult['active'] ?? 0,
            'inactive' => $statsResult['inactive'] ?? 0,
            'by_role' => [
                'admin' => $statsResult['admin_count'] ?? 0,
                'user' => $statsResult['user_count'] ?? 0,
                'viewer' => $statsResult['viewer_count'] ?? 0
            ]
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

// Obtener empresas para filtros
$companies = [];
try {
    $companies = fetchAll("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name");
} catch (Exception $e) {
    error_log("Error fetching companies: " . $e->getMessage());
}

// Calcular paginación
$totalUsers = $stats['total'];
$totalPages = $totalUsers > 0 ? ceil($totalUsers / $limit) : 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - DMS</title>
    <base href="../../">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/users_new.css">
    
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Overlay para móvil -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <!-- Contenido principal -->
        <main class="main-content">
            <!-- Header principal -->
            <header class="main-header">
                <div class="header-content">
                    <div class="header-left">
                        <button class="sidebar-toggle" onclick="toggleSidebar()">
                            <i data-feather="menu"></i>
                        </button>
                        <div class="user-info">
                            <div class="current-user"><?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?></div>
                            <div class="current-time" id="currentTime"></div>
                        </div>
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

            <!-- Contenido del módulo de usuarios -->
            <div class="users-container">
                <!-- Título y botón principal -->
                <div class="page-header">
                    <div class="page-title-section">
                        <div>
                            <h1>Gestión de Usuarios</h1>
                            <p class="page-subtitle">Administrar usuarios del sistema</p>
                        </div>
                        <button class="btn btn-primary" onclick="openCreateUserModal()">
                            <i data-feather="user-plus"></i>
                            Nuevo Usuario
                        </button>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i data-feather="users"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                            <div class="stat-label">Total Usuarios</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon active">
                            <i data-feather="user-check"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['active']); ?></div>
                            <div class="stat-label">Usuarios Activos</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon admin">
                            <i data-feather="shield"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['by_role']['admin']); ?></div>
                            <div class="stat-label">Administradores</div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filters-row">
                            <div class="filter-group">
                                <label>Buscar</label>
                                <div class="search-input-wrapper">
                                    <i data-feather="search" class="search-icon"></i>
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nombre, usuario o email">
                                </div>
                            </div>

                            <div class="filter-group">
                                <label>Rol</label>
                                <div class="select-wrapper">
                                    <select name="role">
                                        <option value="">Todos los roles</option>
                                        <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                        <option value="user" <?php echo $filterRole === 'user' ? 'selected' : ''; ?>>Usuario</option>
                                        <option value="viewer" <?php echo $filterRole === 'viewer' ? 'selected' : ''; ?>>Visualizador</option>
                                    </select>
                                    <i data-feather="chevron-down" class="select-icon"></i>
                                </div>
                            </div>

                            <div class="filter-group">
                                <label>Estado</label>
                                <div class="select-wrapper">
                                    <select name="status">
                                        <option value="">Todos los estados</option>
                                        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Activo</option>
                                        <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                                    </select>
                                    <i data-feather="chevron-down" class="select-icon"></i>
                                </div>
                            </div>

                            <?php if (!empty($companies)): ?>
                            <div class="filter-group">
                                <label>Empresa</label>
                                <div class="select-wrapper">
                                    <select name="company">
                                        <option value="">Todas las empresas</option>
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?php echo $company['id']; ?>" <?php echo $filterCompany == $company['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($company['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i data-feather="chevron-down" class="select-icon"></i>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="filters-actions">
                            <button type="submit" class="btn btn-secondary">
                                <i data-feather="search"></i>
                                Buscar
                            </button>
                            <a href="modules/users/users_new.php" class="btn btn-outline">
                                <i data-feather="x"></i>
                                Limpiar
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Tabla de usuarios -->
                <div class="table-section">
                    <?php if (!empty($users)): ?>
                        <div class="table-header-info">
                            <h3>Lista de Usuarios</h3>
                            <p>Mostrando <?php echo count($users); ?> de <?php echo number_format($totalUsers); ?> usuarios</p>
                        </div>

                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Rol</th>
                                        <th>Empresa</th>
                                        <th>Estado</th>
                                        <th>Último Acceso</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1)); ?>
                                                    </div>
                                                    <div class="user-details">
                                                        <h4><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h4>
                                                        <p>@<?php echo htmlspecialchars($user['username'] ?? ''); ?> • <?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['role'] ?? 'user'; ?>">
                                                    <?php 
                                                    $roleNames = [
                                                        'admin' => 'Administrador',
                                                        'user' => 'Usuario',
                                                        'viewer' => 'Visualizador'
                                                    ];
                                                    echo $roleNames[$user['role']] ?? $user['role'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($user['company_name'] ?? 'Sin empresa'); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['status'] ?? 'inactive'; ?>">
                                                    <?php echo ($user['status'] ?? 'inactive') === 'active' ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($user['last_login'])): ?>
                                                    <div class="date-info">
                                                        <strong><?php echo date('d/m/Y', strtotime($user['last_login'])); ?></strong><br>
                                                        <small><?php echo date('H:i', strtotime($user['last_login'])); ?></small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Nunca</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="actions">
                                                    <button class="action-btn" onclick="viewUser(<?php echo $user['id']; ?>)">
                                                        <i data-feather="eye"></i>
                                                    </button>
                                                    <button class="action-btn" onclick="editUser(<?php echo $user['id']; ?>)">
                                                        <i data-feather="edit"></i>
                                                    </button>
                                                    <button class="action-btn <?php echo ($user['status'] ?? 'inactive') === 'active' ? 'danger' : ''; ?>" 
                                                        onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')">
                                                        <i data-feather="<?php echo ($user['status'] ?? 'inactive') === 'active' ? 'user-x' : 'user-check'; ?>"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php
                                $currentUrl = strtok($_SERVER['REQUEST_URI'], '?');
                                $queryParams = $_GET;
                                ?>
                                
                                <?php if ($page > 1): ?>
                                    <?php 
                                    $queryParams['page'] = $page - 1;
                                    $prevUrl = $currentUrl . '?' . http_build_query($queryParams);
                                    ?>
                                    <a href="<?php echo $prevUrl; ?>" class="pagination-btn">
                                        <i data-feather="chevron-left"></i>
                                        Anterior
                                    </a>
                                <?php endif; ?>

                                <span class="pagination-info">
                                    Página <?php echo $page; ?> de <?php echo $totalPages; ?> 
                                    (<?php echo number_format($totalUsers); ?> usuarios)
                                </span>

                                <?php if ($page < $totalPages): ?>
                                    <?php 
                                    $queryParams['page'] = $page + 1;
                                    $nextUrl = $currentUrl . '?' . http_build_query($queryParams);
                                    ?>
                                    <a href="<?php echo $nextUrl; ?>" class="pagination-btn">
                                        Siguiente
                                        <i data-feather="chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i data-feather="users"></i>
                            </div>
                            <h3>No se encontraron usuarios</h3>
                            <p>No hay usuarios que coincidan con los filtros seleccionados.</p>
                            <?php if (!empty($search) || !empty($filterRole) || !empty($filterStatus) || !empty($filterCompany)): ?>
                                <a href="modules/users/users_new.php" class="btn btn-secondary">
                                    <i data-feather="refresh-cw"></i>
                                    Limpiar filtros
                                </a>
                            <?php else: ?>
                                <button class="btn btn-primary" onclick="openCreateUserModal()">
                                    <i data-feather="user-plus"></i>
                                    Crear primer usuario
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/users_new.js"></script>
    
    <script>
        // Variables globales para JavaScript
        window.userData = {
            currentUserId: <?php echo $currentUser['id']; ?>,
            userRole: '<?php echo $currentUser['role']; ?>',
            companies: <?php echo json_encode($companies); ?>
        };
        
        // Inicializar
        feather.replace();
        
        // Actualizar hora
        function updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });
            const dateString = now.toLocaleDateString('es-ES', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = `${timeString} - ${dateString}`;
            }
        }
        
        updateCurrentTime();
        setInterval(updateCurrentTime, 60000);

        // Función para mostrar "próximamente"
        function showComingSoon(feature) {
            alert(`La función "${feature}" estará disponible próximamente.`);
        }

        // Función para toggle del sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar && overlay) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                
                if (sidebar.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
        }

        console.log('✅ Módulo de usuarios nuevo inicializado correctamente');
    </script>
</body>
</html>