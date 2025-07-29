<?php
// modules/users/index.php - Gestión de usuarios DMS2
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar autenticación
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Obtener usuario actual
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

// Verificar permisos
if ($currentUser['role'] !== 'admin') {
    header('Location: ../../dashboard.php?error=access_denied');
    exit;
}

// Parámetros de filtrado
$search = $_GET['search'] ?? '';
$filterRole = $_GET['role'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterCompany = $_GET['company'] ?? '';

// Parámetros de paginación
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir consulta
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
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

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Consulta principal
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

try {
    $users = fetchAll($sql, $params);
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
}

// Estadísticas
$statsSQL = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count,
        SUM(CASE WHEN role = 'viewer' THEN 1 ELSE 0 END) as viewer_count
    FROM users u
    $whereClause
";

try {
    $statsResult = fetchOne($statsSQL, $params);
    $stats = [
        'total' => $statsResult['total'] ?? 0,
        'active' => $statsResult['active'] ?? 0,
        'by_role' => [
            'admin' => $statsResult['admin_count'] ?? 0,
            'user' => $statsResult['user_count'] ?? 0,
            'viewer' => $statsResult['viewer_count'] ?? 0
        ]
    ];
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $stats = [
        'total' => 0,
        'active' => 0,
        'by_role' => ['admin' => 0, 'user' => 0, 'viewer' => 0]
    ];
}

// Empresas para filtro
$companies = [];
try {
    $companies = fetchAll("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name");
} catch (Exception $e) {
    error_log("Error fetching companies: " . $e->getMessage());
}

$totalUsers = $stats['total'];
$totalPages = ceil($totalUsers / $limit);
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
    <link rel="stylesheet" href="assets/css/users.css">
    <link rel="stylesheet" href="assets/css/users2.css">
    
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
            <!-- Header -->
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

            <div class="users-module">
                <!-- Header del módulo -->
                <div class="module-header">
                    <div class="module-title">
                        <div>
                            <h1>Gestión de Usuarios</h1>
                            <p class="module-subtitle">Administrar usuarios del sistema</p>
                        </div>
                        <button class="btn btn-primary" onclick="showCreateUserModal()">
                            <i data-feather="user-plus"></i>
                            Nuevo Usuario
                        </button>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="stats-row">
                    <div class="stat-box">
                        <span class="stat-number"><?php echo number_format($stats['total']); ?></span>
                        <div class="stat-label">Total Usuarios</div>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number"><?php echo number_format($stats['active']); ?></span>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number"><?php echo number_format($stats['by_role']['admin'] ?? 0); ?></span>
                        <div class="stat-label">Administradores</div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filters-container">
                    <form method="GET" action="">
                        <div class="filters-grid">
                            <div class="filter-item">
                                <label for="search">Buscar</label>
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nombre, usuario o email">
                            </div>

                            <div class="filter-item">
                                <label for="role">Rol</label>
                                <select id="role" name="role">
                                    <option value="">Todos los roles</option>
                                    <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                    <option value="user" <?php echo $filterRole === 'user' ? 'selected' : ''; ?>>Usuario</option>
                                    <option value="viewer" <?php echo $filterRole === 'viewer' ? 'selected' : ''; ?>>Visualizador</option>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label for="status">Estado</label>
                                <select id="status" name="status">
                                    <option value="">Todos los estados</option>
                                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>

                            <?php if (!empty($companies)): ?>
                            <div class="filter-item">
                                <label for="company">Empresa</label>
                                <select id="company" name="company">
                                    <option value="">Todas las empresas</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>" <?php echo $filterCompany == $company['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="filter-actions">
                                <button type="submit" class="btn btn-secondary">
                                    <i data-feather="search"></i>
                                    Filtrar
                                </button>
                                <a href="modules/users/index.php" class="btn btn-outline">
                                    <i data-feather="x"></i>
                                    Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tabla de usuarios -->
                <div class="table-container">
                    <?php if (!empty($users)): ?>
                        <table class="users-table">
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
                                                    <div class="user-name">
                                                        <?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
                                                    </div>
                                                    <div class="user-meta">
                                                        @<?php echo htmlspecialchars($user['username'] ?? ''); ?> • 
                                                        <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?php echo $user['role'] ?? 'user'; ?>">
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
                                            <span class="status-badge status-<?php echo $user['status'] ?? 'inactive'; ?>">
                                                <?php echo ($user['status'] ?? 'inactive') === 'active' ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if (!empty($user['last_login'])): ?>
                                                <div style="font-size: 12px;">
                                                    <strong><?php echo date('d/m/Y', strtotime($user['last_login'])); ?></strong><br>
                                                    <?php echo date('H:i', strtotime($user['last_login'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #dc2626; font-style: italic; font-size: 12px;">Nunca</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button class="action-btn view" onclick="showUserDetails(<?php echo $user['id']; ?>)">
                                                    <i data-feather="eye"></i>
                                                </button>
                                                <button class="action-btn edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                                    <i data-feather="edit"></i>
                                                </button>
                                                <button class="action-btn <?php echo ($user['status'] ?? 'inactive') === 'active' ? 'warning' : 'success'; ?>"
                                                    onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')">
                                                    <i data-feather="<?php echo ($user['status'] ?? 'inactive') === 'active' ? 'user-x' : 'user-check'; ?>"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Paginación -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php
                                $currentUrl = $_SERVER['REQUEST_URI'];
                                $urlParts = parse_url($currentUrl);
                                parse_str($urlParts['query'] ?? '', $queryParams);
                                ?>
                                
                                <?php if ($page > 1): ?>
                                    <?php 
                                    $queryParams['page'] = $page - 1;
                                    $prevUrl = $urlParts['path'] . '?' . http_build_query($queryParams);
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
                                    $nextUrl = $urlParts['path'] . '?' . http_build_query($queryParams);
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
                                <a href="modules/users/index.php" class="btn btn-secondary">
                                    <i data-feather="refresh-cw"></i>
                                    Limpiar filtros
                                </a>
                            <?php else: ?>
                                <button class="btn btn-primary" onclick="showCreateUserModal()">
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
    
    <script>
        // Inicializar feather icons
        feather.replace();
        
        // Actualizar hora actual
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
        
        // Actualizar cada minuto
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

        // MODAL DE CREAR USUARIO - CÓDIGO LIMPIO
        function showCreateUserModal() {
            console.log('🚀 Abriendo modal crear usuario...');
            
            const existingModal = document.getElementById('createUserModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            const modal = document.createElement('div');
            modal.id = 'createUserModal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            `;
            
            modal.innerHTML = `
                <div class="modal-content" style="
                    background: white;
                    border-radius: 12px;
                    max-width: 600px;
                    width: 90%;
                    max-height: 90vh;
                    overflow-y: auto;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                ">
                    <div class="modal-header" style="
                        padding: 24px 24px 16px;
                        border-bottom: 1px solid #f1f5f9;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    ">
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Crear Usuario</h3>
                        <button onclick="closeCreateUserModal()" style="
                            background: none;
                            border: none;
                            font-size: 24px;
                            cursor: pointer;
                            padding: 4px;
                            line-height: 1;
                        ">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 24px;">
                        <form id="createUserForm" onsubmit="handleCreateUser(event)">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Nombre *</label>
                                    <input type="text" name="first_name" required style="
                                        width: 100%;
                                        padding: 12px;
                                        border: 2px solid #e2e8f0;
                                        border-radius: 8px;
                                        box-sizing: border-box;
                                    ">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Apellido *</label>
                                    <input type="text" name="last_name" required style="
                                        width: 100%;
                                        padding: 12px;
                                        border: 2px solid #e2e8f0;
                                        border-radius: 8px;
                                        box-sizing: border-box;
                                    ">
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Usuario *</label>
                                    <input type="text" name="username" required style="
                                        width: 100%;
                                        padding: 12px;
                                        border: 2px solid #e2e8f0;
                                        border-radius: 8px;
                                        box-sizing: border-box;
                                    ">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Email *</label>
                                    <input type="email" name="email" required style="
                                        width: 100%;
                                        padding: 12px;
                                        border: 2px solid #e2e8f0;
                                        border-radius: 8px;
                                        box-sizing: border-box;
                                    ">
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Rol *</label>
                                    <select name="role" required style="
                                        width: 100%;
                                        padding: 12px;
                                        border: 2px solid #e2e8f0;
                                        border-radius: 8px;
                                        box-sizing: border-box;
                                    ">
                                        <option value="">Seleccionar rol</option>
                                        <option value="admin">Administrador</option>
                                        <option value="user">Usuario</option>
                                        <option value="viewer">Visualizador</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Empresa *</label>
                                    <select name="company_id" required style="
                                        width: 100%;
                                        padding: 12px;
                                        border: 2px solid #e2e8f0;
                                        border-radius: 8px;
                                        box-sizing: border-box;
                                    ">
                                        <option value="">Seleccionar empresa</option>
                                        <?php if (!empty($companies)): ?>
                                            <?php foreach ($companies as $company): ?>
                                                <option value="<?php echo $company['id']; ?>">
                                                    <?php echo htmlspecialchars($company['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="1">Empresa Principal</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Contraseña *</label>
                                    <input type="password" name="password" required minlength="6" style="
                                        width: 100%;
                                        padding: 12px;
                                        border: 2px solid #e2e8f0;
                                        border-radius: 8px;
                                        box-sizing: border-box;
                                    ">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Confirmar Contraseña *</label>
                                    <input type="password" name="confirm_password" required minlength="6" style="
                                        width: 100%;
                                        padding: 12px;
                                        border: 2px solid #e2e8f0;
                                        border-radius: 8px;
                                        box-sizing: border-box;
                                    ">
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                    <input type="checkbox" name="download_enabled" checked style="
                                        width: 20px;
                                        height: 20px;
                                    ">
                                    <span>Permitir descarga de documentos</span>
                                </label>
                            </div>
                            
                            <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                                <button type="button" onclick="closeCreateUserModal()" style="
                                    padding: 12px 24px;
                                    border: 2px solid #e2e8f0;
                                    border-radius: 8px;
                                    background: #f1f5f9;
                                    cursor: pointer;
                                ">Cancelar</button>
                                <button type="submit" style="
                                    padding: 12px 24px;
                                    border: none;
                                    border-radius: 8px;
                                    background: #8B4513;
                                    color: white;
                                    cursor: pointer;
                                    font-weight: 600;
                                ">Crear Usuario</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeCreateUserModal();
                }
            });
            
            console.log('✅ Modal creado y mostrado');
        }

        function closeCreateUserModal() {
            const modal = document.getElementById('createUserModal');
            if (modal) {
                modal.remove();
            }
        }

        function handleCreateUser(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            
            if (password !== confirmPassword) {
                alert('Las contraseñas no coinciden');
                return;
            }
            
            if (password.length < 6) {
                alert('La contraseña debe tener al menos 6 caracteres');
                return;
            }
            
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Creando...';
            submitBtn.disabled = true;
            
            fetch('actions/create_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    alert('Usuario creado exitosamente');
                    closeCreateUserModal();
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Error al crear usuario'));
                }
            })
            .catch(error => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                alert('Error de conexión');
                console.error('Error:', error);
            });
        }

        // Funciones placeholder para los botones de acciones
        function showUserDetails(userId) {
            alert('Ver detalles del usuario ID: ' + userId);
        }

        function editUser(userId) {
            alert('Editar usuario ID: ' + userId);
        }

        function toggleUserStatus(userId, currentStatus) {
            const action = currentStatus === 'active' ? 'desactivar' : 'activar';
            if (confirm(`¿Está seguro que desea ${action} este usuario?`)) {
                alert(`Usuario ${action}ado (ID: ${userId})`);
                // Aquí puedes agregar la lógica real para cambiar el estado
                // window.location.reload(); // Para recargar después del cambio
            }
        }

        console.log('✅ Módulo de usuarios inicializado correctamente');
    </script>
</body>
</html>