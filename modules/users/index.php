<?php
// modules/users/index.php
// Módulo de gestión de usuarios - DMS2 (VERSIÓN SIMPLIFICADA)

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado y sea admin
SessionManager::requireLogin();
SessionManager::requireRole('admin');

$currentUser = SessionManager::getCurrentUser();

// Parámetros de búsqueda y filtrado
$search = $_GET['search'] ?? '';
$filterRole = $_GET['role'] ?? '';
$filterCompany = $_GET['company'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Función para obtener usuarios con filtros (CORREGIDA)
function getUsers($search, $filterRole, $filterCompany, $filterStatus, $limit, $offset) {
    $whereConditions = ["u.status != 'deleted'"];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.username LIKE :search OR u.email LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    
    if (!empty($filterRole)) {
        $whereConditions[] = "u.role = :role";
        $params['role'] = $filterRole;
    }
    
    if (!empty($filterCompany)) {
        $whereConditions[] = "u.company_id = :company_id";
        $params['company_id'] = $filterCompany;
    }
    
    if (!empty($filterStatus)) {
        $whereConditions[] = "u.status = :status";
        $params['status'] = $filterStatus;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $query = "SELECT u.*, c.name as company_name,
                     COALESCE((SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id), 0) as document_count,
                     COALESCE((SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.id), 0) as activity_count
              FROM users u
              LEFT JOIN companies c ON u.company_id = c.id
              $whereClause
              ORDER BY u.created_at DESC";
    
    $allUsers = fetchAll($query, $params);
    
    if ($allUsers) {
        return array_slice($allUsers, $offset, $limit);
    }
    
    return [];
}

// Función para obtener el total de usuarios
function getTotalUsers($search, $filterRole, $filterCompany, $filterStatus) {
    $whereConditions = ["u.status != 'deleted'"];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.username LIKE :search OR u.email LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    
    if (!empty($filterRole)) {
        $whereConditions[] = "u.role = :role";
        $params['role'] = $filterRole;
    }
    
    if (!empty($filterCompany)) {
        $whereConditions[] = "u.company_id = :company_id";
        $params['company_id'] = $filterCompany;
    }
    
    if (!empty($filterStatus)) {
        $whereConditions[] = "u.status = :status";
        $params['status'] = $filterStatus;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $query = "SELECT COUNT(*) as total FROM users u $whereClause";
    $result = fetchOne($query, $params);
    
    return $result['total'] ?? 0;
}

// Función para obtener empresas
function getCompanies() {
    $query = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
    return fetchAll($query) ?: [];
}

// Función para obtener estadísticas
function getUsersStats() {
    $stats = [];
    
    $query = "SELECT COUNT(*) as total FROM users WHERE status != 'deleted'";
    $result = fetchOne($query);
    $stats['total'] = $result['total'] ?? 0;
    
    $query = "SELECT COUNT(*) as active FROM users WHERE status = 'active'";
    $result = fetchOne($query);
    $stats['active'] = $result['active'] ?? 0;
    
    $query = "SELECT COUNT(*) as download_enabled FROM users WHERE download_enabled = 1 AND status = 'active'";
    $result = fetchOne($query);
    $stats['download_enabled'] = $result['download_enabled'] ?? 0;
    
    $query = "SELECT role, COUNT(*) as count FROM users WHERE status != 'deleted' GROUP BY role";
    $roles = fetchAll($query);
    $stats['by_role'] = [];
    if ($roles) {
        foreach ($roles as $role) {
            $stats['by_role'][$role['role']] = $role['count'];
        }
    }
    
    return $stats;
}

// Obtener datos
try {
    $users = getUsers($search, $filterRole, $filterCompany, $filterStatus, $limit, $offset);
    $totalUsers = getTotalUsers($search, $filterRole, $filterCompany, $filterStatus);
    $companies = getCompanies();
    $stats = getUsersStats();
} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    $users = [];
    $totalUsers = 0;
    $companies = [];
    $stats = ['total' => 0, 'active' => 0, 'download_enabled' => 0, 'by_role' => []];
}

// Calcular paginación
$totalPages = $totalUsers > 0 ? ceil($totalUsers / $limit) : 1;

// Registrar acceso
logActivity($currentUser['id'], 'view_users', 'users', null, 'Usuario accedió al módulo de usuarios');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/users.css">
    <link rel="stylesheet" href="../../assets/css/users2.css">
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
                <h1>Gestión de Usuarios</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?></div>
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
                            <label for="company">Empresa</label>
                            <select id="company" name="company">
                                <option value="">Todas las empresas</option>
                                <?php if ($companies): ?>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>" <?php echo $filterCompany == $company['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
                    </div>

                    <div class="filters-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="search"></i>
                            Filtrar
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i data-feather="x"></i>
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabla de usuarios -->
            <div class="table-container">
                <div class="table-header-info">
                    <h3>Lista de Usuarios</h3>
                    <p>Mostrando <?php echo count($users); ?> de <?php echo $totalUsers; ?> usuarios</p>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Empresa</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Permisos</th>
                            <th>Actividad</th>
                            <th>Último Acceso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i data-feather="users" style="width: 48px; height: 48px; margin-bottom: 16px;"></i>
                                    <h4 style="margin: 0 0 8px 0;">No se encontraron usuarios</h4>
                                    <p style="margin: 0;">No hay usuarios que coincidan con los filtros seleccionados.</p>
                                </td>
                            </tr>
                        <?php else: ?>
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
                                    <td><?php echo htmlspecialchars($user['company_name'] ?? 'Sin empresa'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['role'] ?? 'user'; ?>">
                                            <?php echo ucfirst($user['role'] ?? 'user'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['status'] ?? 'inactive'; ?>">
                                            <?php echo ($user['status'] ?? 'inactive') === 'active' ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($user['download_enabled']) && $user['download_enabled']): ?>
                                            <span class="badge badge-active">Descarga</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Sin descarga</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="font-size: 12px;">
                                            <strong><?php echo number_format($user['activity_count'] ?? 0); ?></strong> acciones<br>
                                            <strong><?php echo number_format($user['document_count'] ?? 0); ?></strong> documentos
                                        </div>
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
                                            <button class="action-btn" onclick="showUserDetails(<?php echo $user['id']; ?>)" title="Ver detalles">
                                                <i data-feather="eye"></i>
                                            </button>
                                            <button class="action-btn" onclick="editUser(<?php echo $user['id']; ?>)" title="Editar">
                                                <i data-feather="edit"></i>
                                            </button>
                                            <button class="action-btn" onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')" title="Cambiar estado">
                                                <i data-feather="<?php echo ($user['status'] ?? 'inactive') === 'active' ? 'user-x' : 'user-check'; ?>"></i>
                                            </button>
                                            <button class="action-btn danger" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Eliminar">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                    <div style="padding: 20px; background: #f9fafb; border-top: 1px solid #e5e7eb; display: flex; justify-content: center; gap: 8px;">
                        <?php
                        $currentUrl = $_SERVER['REQUEST_URI'];
                        $currentUrl = strtok($currentUrl, '?');
                        $queryParams = $_GET;
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="<?php echo $currentUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $page - 1])); ?>" 
                               style="padding: 8px 12px; text-decoration: none; color: #6b7280; border: 1px solid #d1d5db; border-radius: 6px; background: white;">
                                <i data-feather="chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="<?php echo $currentUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $i])); ?>" 
                               style="padding: 8px 12px; text-decoration: none; border-radius: 6px; <?php echo $i === $page ? 'background: #6366f1; color: white; border: 1px solid #6366f1;' : 'color: #6b7280; border: 1px solid #d1d5db; background: white;'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo $currentUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $page + 1])); ?>" 
                               style="padding: 8px 12px; text-decoration: none; color: #6b7280; border: 1px solid #d1d5db; border-radius: 6px; background: white;">
                                Siguiente <i data-feather="chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Overlay para sidebar móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Scripts -->
    <script src="../../assets/js/users.js"></script>
    <script>
        // Variables globales para JavaScript
        window.companiesData = <?php echo json_encode($companies); ?>;
        
        // Configuración inicial
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);
            
            // Manejar mensajes de URL
            handleURLMessages();
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
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            }
        }

        function refreshData() {
            showNotification('Actualizando datos...', 'info');
            window.location.reload();
        }

        function showComingSoon(feature) {
            showNotification(`${feature} - Próximamente`, 'info');
        }

        function handleURLMessages() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            if (success) {
                let message = '';
                switch(success) {
                    case 'user_created':
                        message = '✅ Usuario creado exitosamente';
                        break;
                    case 'user_updated':
                        message = '✅ Usuario actualizado exitosamente';
                        break;
                    case 'user_deleted':
                        message = '✅ Usuario eliminado exitosamente';
                        break;
                    case 'status_changed':
                        message = '✅ Estado del usuario actualizado';
                        break;
                }
                if (message) {
                    showNotification(message, 'success');
                    cleanURL();
                }
            }
            
            if (error) {
                let message = '';
                switch(error) {
                    case 'user_not_found':
                        message = '❌ Usuario no encontrado';
                        break;
                    case 'access_denied':
                        message = '❌ Acceso denegado';
                        break;
                    case 'invalid_data':
                        message = '❌ Datos inválidos';
                        break;
                    case 'email_exists':
                        message = '❌ El email ya está registrado';
                        break;
                    case 'username_exists':
                        message = '❌ El nombre de usuario ya existe';
                        break;
                }
                if (message) {
                    showNotification(message, 'error');
                    cleanURL();
                }
            }
        }

        function cleanURL() {
            const url = new URL(window.location);
            url.searchParams.delete('success');
            url.searchParams.delete('error');
            window.history.replaceState({}, document.title, url.toString());
        }

        function showNotification(message, type = 'info', duration = 5000) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                z-index: 1001;
                max-width: 400px;
                border-left: 4px solid ${type === 'success' ? '#16a34a' : type === 'error' ? '#dc2626' : '#6366f1'};
                display: flex;
                align-items: center;
                gap: 12px;
            `;
            
            notification.innerHTML = `
                <i data-feather="${type === 'success' ? 'check-circle' : type === 'error' ? 'alert-circle' : 'info'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; padding: 4px; margin-left: auto;">
                    <i data-feather="x"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            feather.replace();
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, duration);
        }
        
    </script>
</body>
</html>