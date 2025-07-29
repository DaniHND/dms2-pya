<?php
// modules/users/index.php - Módulo con bloqueador agresivo de popups
require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar autenticación y permisos
SessionManager::requireLogin();
SessionManager::requireRole('admin');

$currentUser = SessionManager::getCurrentUser();

// [... resto del código PHP igual ...]
// Parámetros de filtrado y paginación
$search = $_GET['search'] ?? '';
$filterRole = $_GET['role'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterCompany = $_GET['company'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Construir consulta con filtros
$whereConditions = ["1=1"];
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
$totalUsers = 0;
$companies = [];

try {
    $users = fetchAll($sql, $params);
    $countSql = "SELECT COUNT(*) as total FROM users u LEFT JOIN companies c ON u.company_id = c.id $whereClause";
    $countResult = fetchOne($countSql, $params);
    $totalUsers = $countResult['total'] ?? 0;
    $companies = fetchAll("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name");
} catch (Exception $e) {
    error_log("Error in users module: " . $e->getMessage());
    $error = "Error al cargar los datos de usuarios";
}

$totalPages = ceil($totalUsers / $limit);
logActivity($currentUser['id'], 'view_users_module', 'users', null, 'Usuario accedió al módulo de gestión de usuarios');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        /* BLOQUEADOR AGRESIVO DE POPUPS Y EXTENSIONES */
        
        /* Desactivar completamente tooltips y popups del navegador */
        * {
            /* Desactivar tooltips del navegador */
            title: none !important;
        }
        
        *::before,
        *::after {
            display: none !important;
        }
        
        /* Bloquear elementos conocidos de extensiones */
        [class*="tooltip"],
        [class*="popup"],
        [class*="extension"],
        [class*="translate"],
        [id*="tooltip"],
        [id*="popup"],
        [id*="extension"],
        [id*="translate"] {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
            position: absolute !important;
            left: -9999px !important;
            top: -9999px !important;
        }
        
        /* Bloquear overlays de terceros */
        div[style*="position: fixed"],
        div[style*="position: absolute"][style*="z-index"] {
            display: none !important;
        }
        
        /* Proteger nuestros elementos específicamente */
        .protected-button {
            position: relative !important;
            z-index: 999999 !important;
            pointer-events: auto !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
        }
        
        .protected-button:hover {
            transform: none !important;
        }
        
        /* Contenedor protegido para la tabla */
        .protected-table {
            position: relative !important;
            z-index: 99999 !important;
            isolation: isolate !important;
        }
        
        /* Estilos específicos para botones */
        .action-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 32px !important;
            height: 32px !important;
            border: none !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            font-size: 14px !important;
            margin: 1px !important;
            transition: background-color 0.2s !important;
            position: relative !important;
            z-index: 999999 !important;
            pointer-events: auto !important;
        }
        
        .btn-view { background-color: #3b82f6 !important; color: white !important; }
        .btn-view:hover { background-color: #2563eb !important; }
        .btn-edit { background-color: #f59e0b !important; color: white !important; }
        .btn-edit:hover { background-color: #d97706 !important; }
        .btn-toggle-activate { background-color: #10b981 !important; color: white !important; }
        .btn-toggle-activate:hover { background-color: #059669 !important; }
        .btn-toggle-deactivate { background-color: #ef4444 !important; color: white !important; }
        .btn-toggle-deactivate:hover { background-color: #dc2626 !important; }
        
        .actions-cell {
            position: relative !important;
            z-index: 999999 !important;
            isolation: isolate !important;
        }
        
        /* Prevenir interferencias del navegador */
        body {
            overflow-x: hidden !important;
        }
        
        /* Desactivar funciones del navegador que interfieren */
        .main-content {
            -webkit-touch-callout: none !important;
            -webkit-user-select: none !important;
            -khtml-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            user-select: none !important;
        }
        
        /* Permitir selección solo en inputs */
        input, textarea {
            -webkit-user-select: text !important;
            -moz-user-select: text !important;
            -ms-user-select: text !important;
            user-select: text !important;
        }
    </style>
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
                    <div class="user-name-header"><?php echo htmlspecialchars(SessionManager::getFullName()); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido de usuarios -->
        <div style="padding: 0 24px 24px 24px;">
            <!-- Header con botón crear -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
                <div>
                    <p style="color: #64748b; margin: 0;">Gestiona los usuarios del sistema</p>
                </div>
                <button class="btn-primary protected-button" 
                        onclick="event.stopPropagation(); event.preventDefault(); openCreateUserModal();" 
                        style="padding: 0.75rem 1.5rem; position: relative; z-index: 999999;">
                    <i data-feather="user-plus"></i>
                    Crear Usuario
                </button>
            </div>

            <!-- Filtros -->
            <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                    <div class="form-group">
                        <label>Buscar</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nombre, usuario o email">
                    </div>
                    
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="role">
                            <option value="">Todos los roles</option>
                            <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                            <option value="manager" <?php echo $filterRole === 'manager' ? 'selected' : ''; ?>>Gerente</option>
                            <option value="user" <?php echo $filterRole === 'user' ? 'selected' : ''; ?>>Usuario</option>
                            <option value="viewer" <?php echo $filterRole === 'viewer' ? 'selected' : ''; ?>>Visualizador</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="status">
                            <option value="">Todos los estados</option>
                            <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Empresa</label>
                        <select name="company">
                            <option value="">Todas las empresas</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>" <?php echo $filterCompany == $company['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn-secondary">
                            <i data-feather="search"></i>
                            Filtrar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabla de usuarios -->
            <div class="protected-table" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background-color: #f9fafb;">
                                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #374151;">Usuario</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #374151;">Email</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #374151;">Rol</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #374151;">Empresa</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #374151;">Estado</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #374151;">Descarga</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #374151;">Último Acceso</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #374151; width: 130px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 3rem; color: #6b7280;">
                                        <i data-feather="users" style="width: 48px; height: 48px; margin-bottom: 1rem;"></i>
                                        <br>
                                        No se encontraron usuarios
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr style="border-bottom: 1px solid #e5e7eb;">
                                        <td style="padding: 12px;">
                                            <div>
                                                <div style="font-weight: 500;">
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: #6b7280;">
                                                    @<?php echo htmlspecialchars($user['username']); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td style="padding: 12px;">
                                            <?php
                                            $roleColors = [
                                                'admin' => 'background-color: #fef3c7; color: #92400e;',
                                                'manager' => 'background-color: #dbeafe; color: #1e40af;',
                                                'user' => 'background-color: #e0e7ff; color: #3730a3;',
                                                'viewer' => 'background-color: #f3e8ff; color: #6b21a8;'
                                            ];
                                            $roleLabels = ['admin' => 'Administrador', 'manager' => 'Gerente', 'user' => 'Usuario', 'viewer' => 'Visualizador'];
                                            ?>
                                            <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500; <?php echo $roleColors[$user['role']] ?? ''; ?>">
                                                <?php echo $roleLabels[$user['role']] ?? $user['role']; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($user['company_name'] ?? 'Sin empresa'); ?></td>
                                        <td style="padding: 12px;">
                                            <?php
                                            $statusColor = $user['status'] === 'active' ? 'background-color: #d1fae5; color: #065f46;' : 'background-color: #fee2e2; color: #991b1b;';
                                            ?>
                                            <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; <?php echo $statusColor; ?>">
                                                <?php echo $user['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?php echo $user['download_enabled'] ? '✅ Sí' : '❌ No'; ?>
                                        </td>
                                        <td style="padding: 12px; font-size: 0.875rem; color: #6b7280;">
                                            <?php if ($user['last_login']): ?>
                                                <div><?php echo date('d/m/Y', strtotime($user['last_login'])); ?></div>
                                                <div style="font-size: 0.75rem; color: #9ca3af;"><?php echo date('H:i', strtotime($user['last_login'])); ?></div>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">Nunca</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px;" class="actions-cell">
                                            <div style="display: flex; gap: 2px; position: relative; z-index: 999999;">
                                                <button class="action-btn btn-view protected-button" 
                                                        onclick="event.stopPropagation(); viewUser(<?php echo $user['id']; ?>);">
                                                    <i data-feather="eye"></i>
                                                </button>
                                                <button class="action-btn btn-edit protected-button" 
                                                        onclick="event.stopPropagation(); editUser(<?php echo $user['id']; ?>);">
                                                    <i data-feather="edit"></i>
                                                </button>
                                                <?php if ($user['id'] != $currentUser['id']): ?>
                                                    <button class="action-btn <?php echo $user['status'] === 'active' ? 'btn-toggle-deactivate' : 'btn-toggle-activate'; ?> protected-button" 
                                                            onclick="event.stopPropagation(); toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>');">
                                                        <i data-feather="<?php echo $user['status'] === 'active' ? 'user-x' : 'user-check'; ?>"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                    <div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 2rem; padding: 1rem;">
                        <!-- Paginación igual que antes -->
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="../../assets/js/dashboard.js"></script>
    <script src="../../assets/js/users.js"></script>
    <script>
        // BLOQUEADOR AGRESIVO DE POPUPS Y EXTENSIONES
        
        // Desactivar tooltips y popups inmediatamente
        document.addEventListener('DOMContentLoaded', function() {
            // Eliminar todos los title attributes
            document.querySelectorAll('*[title]').forEach(el => {
                el.removeAttribute('title');
            });
            
            // Prevenir mouseover/mouseenter que crean tooltips
            document.addEventListener('mouseover', function(e) {
                if (e.target.hasAttribute('title')) {
                    e.target.removeAttribute('title');
                }
                e.stopPropagation();
            }, true);
            
            // Bloquear eventos de extensiones
            ['mouseenter', 'mouseleave', 'focus', 'blur'].forEach(eventType => {
                document.addEventListener(eventType, function(e) {
                    if (e.target.closest('.protected-button')) {
                        e.stopImmediatePropagation();
                    }
                }, true);
            });
            
            // Remover cualquier overlay existente cada 100ms
            setInterval(function() {
                // Buscar y eliminar popups/tooltips conocidos
                const popups = document.querySelectorAll([
                    '[class*="tooltip"]',
                    '[class*="popup"]', 
                    '[class*="extension"]',
                    '[class*="translate"]',
                    '[id*="tooltip"]',
                    '[id*="popup"]',
                    'div[style*="position: fixed"][style*="z-index"]'
                ].join(','));
                
                popups.forEach(popup => {
                    if (!popup.closest('.main-content')) {
                        popup.remove();
                    }
                });
            }, 100);
            
            feather.replace();
        });
        
        // Función de tiempo
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-ES', { 
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
        
        // Proteger clics en botones
        document.addEventListener('click', function(e) {
            if (e.target.closest('.protected-button')) {
                e.stopImmediatePropagation();
            }
        }, true);
        
        // Desactivar showComingSoon
        function showComingSoon() { return false; }
        
        // Bloquear eventos del navegador en elementos protegidos
        ['contextmenu', 'selectstart', 'dragstart'].forEach(eventType => {
            document.addEventListener(eventType, function(e) {
                if (e.target.closest('.protected-button, .protected-table')) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        });
    </script>
</body>
</html>