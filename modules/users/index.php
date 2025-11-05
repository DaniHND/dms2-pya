<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar sesión
SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// VERIFICACIÓN SIMPLE Y DIRECTA PARA ADMIN
if ($currentUser["role"] !== "admin") {
    header("Location: ../../dashboard.php?error=access_denied");
    exit;
}

// Obtener conexión a la base de datos
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo establecer conexión con la base de datos");
    }
} catch (Exception $e) {
    error_log("Error de conexión a base de datos en users/index.php: " . $e->getMessage());
    die("Error de conexión a la base de datos. Por favor, contacte al administrador.");
}

// Configuración de paginación
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Filtros
$filters = [
    'search' => $_GET['search'] ?? '',
    'role' => $_GET['role'] ?? '',
    'status' => $_GET['status'] ?? '',
    'company_id' => $_GET['company_id'] ?? ''
];

// Construir consulta base
$whereConditions = ["u.status != 'deleted'"];
$params = [];

if (!empty($filters['search'])) {
    $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($filters['role'])) {
    $whereConditions[] = "u.role = ?";
    $params[] = $filters['role'];
}

if (!empty($filters['status'])) {
    $whereConditions[] = "u.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['company_id'])) {
    $whereConditions[] = "u.company_id = ?";
    $params[] = $filters['company_id'];
}

$whereClause = implode(' AND ', $whereConditions);

// Obtener estadísticas
try {
    $statsQuery = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN u.status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                    SUM(CASE WHEN u.role = 'admin' THEN 1 ELSE 0 END) as admin_count,
                    SUM(CASE WHEN u.role = 'manager' THEN 1 ELSE 0 END) as manager_count,
                    SUM(CASE WHEN u.role = 'user' THEN 1 ELSE 0 END) as user_count
                   FROM users u 
                   WHERE " . $whereClause;
    
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['by_role'] = [
        'admin' => $stats['admin_count'],
        'manager' => $stats['manager_count'],
        'user' => $stats['user_count']
    ];
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas de usuarios: " . $e->getMessage());
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'by_role' => ['admin' => 0, 'manager' => 0, 'user' => 0]
    ];
}

// Obtener usuarios con paginación
try {
    $usersQuery = "SELECT u.*, c.name as company_name,
                   COALESCE(u.download_enabled, 1) as download_enabled
                   FROM users u
                   LEFT JOIN companies c ON u.company_id = c.id
                   WHERE " . $whereClause . "
                   ORDER BY u.created_at DESC
                   LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($usersQuery);
    $stmt->execute(array_merge($params, [$itemsPerPage, $offset]));
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total de páginas
    $countQuery = "SELECT COUNT(*) FROM users u WHERE " . $whereClause;
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalUsers = $stmt->fetchColumn();
    $totalPages = ceil($totalUsers / $itemsPerPage);
    
} catch (Exception $e) {
    error_log("Error obteniendo usuarios: " . $e->getMessage());
    $users = [];
    $totalUsers = 0;
    $totalPages = 1;
}

// Obtener empresas para filtros y selects
try {
    $companiesQuery = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
    $stmt = $pdo->query($companiesQuery);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error obteniendo empresas: " . $e->getMessage());
    $companies = [];
}

// Función para obtener clase de badge de estado
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active': return 'status-active';
        case 'inactive': return 'status-inactive';
        case 'suspended': return 'status-suspended';
        default: return 'status-inactive';
    }
}

// Función para obtener clase de badge de rol
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'admin': return 'role-admin';
        case 'manager': return 'role-manager';
        case 'user': return 'role-user';
        default: return 'role-user';
    }
}

// Función para formatear fecha
function formatDate($dateString) {
    if (!$dateString) return 'N/A';
    $date = new DateTime($dateString);
    return $date->format('d/m/Y H:i');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - DMS2</title>
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    
    <!-- CSS del Módulo de Usuarios - ARCHIVO ÚNICO -->
    <link rel="stylesheet" href="../../assets/css/users.css">
    
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
        /* Estilos adicionales específicos para esta página */
        
        /* Botón crear usuario más destacado */
        .btn-create-user {
            background: linear-gradient(135deg, var(--dms-primary) 0%, var(--dms-primary-hover) 100%);
            border: none;
            box-shadow: 0 4px 12px rgba(139, 69, 19, 0.3);
            padding: 14px 28px;
            font-weight: 600;
            font-size: 15px;
            text-transform: none;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-create-user:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4);
            background: linear-gradient(135deg, var(--dms-primary-hover) 0%, #4a2c0a 100%);
        }
        
        .btn-create-user span {
            margin-left: 2px;
        }
        
        /* Grupo de botones de filtros más ordenado */
        .filter-actions-group {
            align-self: end;
        }
        
        .filter-buttons {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .btn-filter {
            padding: 12px 20px;
            font-weight: 600;
            font-size: 14px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: 2px solid transparent;
            min-width: 110px;
            justify-content: center;
        }
        
        .btn-filter.btn-primary {
            background: var(--dms-primary);
            color: white;
            border-color: var(--dms-primary);
        }
        
        .btn-filter.btn-primary:hover {
            background: var(--dms-primary-hover);
            border-color: var(--dms-primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(139, 69, 19, 0.3);
        }
        
        .btn-filter.btn-secondary {
            background: #f8fafc;
            color: var(--dms-text);
            border-color: var(--dms-border);
        }
        
        .btn-filter.btn-secondary:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Botones de acción más profesionales */
        .btn-action {
            width: 36px;
            height: 36px;
            padding: 0;
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-action i {
            width: 18px;
            height: 18px;
        }
        
        .btn-view {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border-color: rgba(59, 130, 246, 0.2);
        }
        
        .btn-view:hover {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(59, 130, 246, 0.3);
        }
        
        .btn-edit {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border-color: rgba(245, 158, 11, 0.2);
        }
        
        .btn-edit:hover {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(245, 158, 11, 0.3);
        }
        
        .btn-toggle {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.2);
        }
        
        .btn-toggle:hover {
            background: #10b981;
            color: white;
            border-color: #10b981;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(16, 185, 129, 0.3);
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--dms-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .user-details {
            min-width: 0;
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dms-text);
            margin: 0;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-email {
            color: var(--dms-text-muted);
            font-size: 13px;
            margin: 2px 0 0 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .detail-item label {
            font-weight: 600;
            color: var(--dms-text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-item span {
            color: var(--dms-text);
            font-size: 14px;
        }
        
        .loading-spinner {
            width: 32px;
            height: 32px;
            border: 3px solid var(--dms-border);
            border-top: 3px solid var(--dms-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .user-details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="dashboard-layout">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Header -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Gestión de Usuarios</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
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

                    <button class="btn btn-primary btn-create-user" onclick="openCreateUserModal()">
                        <i data-feather="user-plus"></i>
                        <span>Crear Usuario</span>
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-section">
                <h3>Filtros de Búsqueda</h3>
                <form class="filters-form" method="GET">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="search">Buscar Usuario</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   class="form-input"
                                   placeholder="Nombre, usuario o email..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="role">Rol</label>
                            <select id="role" name="role" class="form-input">
                                <option value="">Todos los roles</option>
                                <option value="admin" <?php echo $filters['role'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                <option value="user" <?php echo $filters['role'] === 'user' ? 'selected' : ''; ?>>Usuario</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Estado</label>
                            <select id="status" name="status" class="form-input">
                                <option value="">Todos los estados</option>
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                                <option value="suspended" <?php echo $filters['status'] === 'suspended' ? 'selected' : ''; ?>>Suspendido</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="company_id">Empresa</label>
                            <select id="company_id" name="company_id" class="form-input">
                                <option value="">Todas las empresas</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>" 
                                            <?php echo $filters['company_id'] == $company['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($company['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                       
                    </div>
                </form>
            </div>

            <!-- Tabla de usuarios -->
            <div class="table-section">
                <div class="table-header-info">
                    <div>
                        <h3>Lista de Usuarios</h3>
                        <p>Mostrando <?php echo count($users); ?> de <?php echo $totalUsers; ?> usuarios</p>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <?php if (empty($users)): ?>
                        <div class="table-empty">
                            <i data-feather="users"></i>
                            <h4>No se encontraron usuarios</h4>
                            <p>No hay usuarios que coincidan con los filtros aplicados.</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Empresa</th>
                                    <th>Fecha Registro</th>
                                  
                                 
                                    <th class="actions-cell"> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                </div>
                                                <div class="user-details">
                                                    <div class="user-name">
                                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                    </div>
                                                    <div class="user-email">
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge <?php echo getRoleBadgeClass($user['role']); ?>">
                                                <?php echo htmlspecialchars($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusBadgeClass($user['status']); ?>">
                                                <?php echo htmlspecialchars($user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['company_name'] ?? 'Sin empresa'); ?></td>
                                        <td><?php echo formatDate($user['created_at']); ?></td>
           
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <button class="btn-action btn-view" 
                                                        onclick="showUserDetails(<?php echo $user['id']; ?>)"
                                                        title="Ver detalles">
                                                    <i data-feather="eye"></i>
                                                </button>
                                                
                                                <button class="btn-action btn-edit" 
                                                        onclick="editUser(<?php echo $user['id']; ?>)"
                                                        title="Editar usuario">
                                                    <i data-feather="edit-2"></i>
                                                </button>
                                                
                                                <button class="btn-action btn-toggle" 
                                                        onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')"
                                                        title="<?php echo $user['status'] === 'active' ? 'Desactivar' : 'Activar'; ?> usuario">
                                                    <i data-feather="<?php echo $user['status'] === 'active' ? 'user-x' : 'user-check'; ?>"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Página <?php echo $currentPage; ?> de <?php echo $totalPages; ?>
                        </div>
                        <div class="pagination-controls">
                            <?php
                            $queryParams = $_GET;
                            unset($queryParams['page']);
                            $baseUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($queryParams);
                            ?>
                            
                            <?php if ($currentPage > 1): ?>
                                <a href="<?php echo $baseUrl; ?>&page=1" class="pagination-btn">
                                    <i data-feather="chevrons-left"></i>
                                </a>
                                <a href="<?php echo $baseUrl; ?>&page=<?php echo $currentPage - 1; ?>" class="pagination-btn">
                                    <i data-feather="chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="<?php echo $baseUrl; ?>&page=<?php echo $i; ?>" 
                                   class="pagination-btn <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="<?php echo $baseUrl; ?>&page=<?php echo $currentPage + 1; ?>" class="pagination-btn">
                                    <i data-feather="chevron-right"></i>
                                </a>
                                <a href="<?php echo $baseUrl; ?>&page=<?php echo $totalPages; ?>" class="pagination-btn">
                                    <i data-feather="chevrons-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/users.js"></script>
    
    <script>
        // Datos globales para JavaScript
        window.userData = {
            companies: <?php echo json_encode($companies); ?>,
            currentUser: <?php echo json_encode($currentUser); ?>
        };
        
        // Inicializar iconos
        feather.replace();
        
        // Actualizar tiempo actual
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        updateTime();
        setInterval(updateTime, 60000);
        
        // Función para mostrar "próximamente"
        function showComingSoon(feature) {
            alert('La función "' + feature + '" estará disponible próximamente.');
        }
        
        // Función para toggle del sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (sidebar) {
                sidebar.classList.toggle('collapsed');
                
                if (mainContent) {
                    mainContent.classList.toggle('sidebar-collapsed');
                }
                
                if (overlay) {
                    overlay.classList.toggle('active');
                }
            }
        }
        
        console.log('✅ Módulo de usuarios cargado correctamente');
        
        // Debug para verificar que las funciones están disponibles
        console.log('Funciones disponibles:');
        console.log('- openCreateUserModal:', typeof window.openCreateUserModal);
        console.log('- showUserDetails:', typeof window.showUserDetails);
        console.log('- editUser:', typeof window.editUser);
        console.log('- toggleUserStatus:', typeof window.toggleUserStatus);
        console.log('- deleteUser:', typeof window.deleteUser);
        
        // Verificar que todos los datos están disponibles
        console.log('Datos disponibles:');
        console.log('- Empresas:', window.userData.companies.length);
        console.log('- Usuario actual:', window.userData.currentUser.username);
    </script>
</body>
</html>