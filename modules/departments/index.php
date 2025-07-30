<?php
// modules/departments/index.php
// Módulo de gestión de departamentos - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permisos de administrador
SessionManager::requireRole('admin');

$currentUser = SessionManager::getCurrentUser();

// Configuración de paginación
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Filtros
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'company_id' => $_GET['company_id'] ?? ''
];

// Construir consulta con filtros
$whereConditions = [];
$params = [];

if (!empty($filters['search'])) {
    $whereConditions[] = "(d.name LIKE :search OR d.description LIKE :search OR c.name LIKE :search_company OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search_manager)";
    $params['search'] = '%' . $filters['search'] . '%';
    $params['search_company'] = '%' . $filters['search'] . '%';
    $params['search_manager'] = '%' . $filters['search'] . '%';
}

if (!empty($filters['status'])) {
    $whereConditions[] = "d.status = :status";
    $params['status'] = $filters['status'];
}

if (!empty($filters['company_id'])) {
    $whereConditions[] = "d.company_id = :company_id";
    $params['company_id'] = $filters['company_id'];
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Contar total de registros
$countQuery = "SELECT COUNT(DISTINCT d.id) as total 
               FROM departments d 
               LEFT JOIN companies c ON d.company_id = c.id 
               LEFT JOIN users u ON d.manager_id = u.id 
               $whereClause";

$totalItems = fetchOne($countQuery, $params)['total'];
$totalPages = ceil($totalItems / $itemsPerPage);

// Obtener departamentos con información relacionada
$query = "SELECT d.*, 
                 c.name as company_name,
                 CONCAT(u.first_name, ' ', u.last_name) as manager_name,
                 u.email as manager_email,
                 (SELECT COUNT(*) FROM users WHERE department_id = d.id AND status = 'active') as total_users
          FROM departments d 
          LEFT JOIN companies c ON d.company_id = c.id 
          LEFT JOIN users u ON d.manager_id = u.id 
          $whereClause
          ORDER BY d.created_at DESC 
          LIMIT :limit OFFSET :offset";

$params['limit'] = $itemsPerPage;
$params['offset'] = $offset;

try {
    $database = new Database();
    $pdo = $database->getConnection();
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $value) {
        if ($key === 'limit' || $key === 'offset') {
            $stmt->bindValue(':' . $key, (int)$value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':' . $key, $value);
        }
    }
    
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
    $error = "Error al cargar departamentos: " . $e->getMessage();
}

// Obtener empresas para el filtro y formularios
$companies = fetchAll("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name");

// Funciones helper
function getStatusBadgeClass($status) {
    return $status === 'active' ? 'badge-success' : 'badge-danger';
}

function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Registrar actividad
logActivity($currentUser['id'], 'view_departments', 'departments', null, 'Usuario accedió al módulo de departamentos');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Departamentos - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/departments.css">
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
                <h1>Gestión de Departamentos</h1>
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
        <div class="container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i data-feather="alert-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Botón Crear Departamento -->
            <div class="create-button-section">
                <button class="btn btn-primary create-btn" onclick="openCreateDepartmentModal()">
                    <i data-feather="briefcase"></i>
                    Crear Departamento
                </button>
            </div>

            <!-- Filtros de Búsqueda -->
            <div class="filters-card">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" class="filters-form">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Buscar Departamento</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   placeholder="Nombre, descripción, empresa..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="status">Estado</label>
                            <select id="status" name="status">
                                <option value="">Todos los estados</option>
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>
                                    Activo
                                </option>
                                <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>
                                    Inactivo
                                </option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Lista de Departamentos -->
            <div class="list-card">
                <div class="list-header">
                    <h3>Lista de Departamentos</h3>
                    <p class="list-subtitle">Mostrando <?php echo count($departments); ?> de <?php echo $totalItems; ?> departamentos</p>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>DEPARTAMENTO</th>
                                <th>EMPRESA</th>
                                <th>MANAGER</th>
                                <th>USUARIOS</th>
                                <th>ESTADO</th>
                                <th>FECHA REGISTRO</th>
                                <th>ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($departments)): ?>
                                <tr>
                                    <td colspan="7" class="no-data">
                                        <div class="no-data-content">
                                            <div class="no-data-icon">
                                                <i data-feather="layers"></i>
                                            </div>
                                            <div class="no-data-text">
                                                <p>No se encontraron departamentos</p>
                                                <button class="btn btn-primary" onclick="openCreateDepartmentModal()">
                                                    <i data-feather="plus"></i>
                                                    Crear primer departamento
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($departments as $department): ?>
                                    <tr>
                                        <td>
                                            <div class="item-info">
                                                <div class="item-avatar">
                                                    <?php 
                                                    $initials = strtoupper(substr($department['name'], 0, 2));
                                                    echo $initials;
                                                    ?>
                                                </div>
                                                <div class="item-details">
                                                    <div class="item-name"><?php echo htmlspecialchars($department['name']); ?></div>
                                                    <?php if (!empty($department['description'])): ?>
                                                        <div class="item-subtitle"><?php echo htmlspecialchars($department['description']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="contact-info">
                                                <?php echo htmlspecialchars($department['company_name'] ?? 'Sin empresa'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($department['manager_name'])): ?>
                                                <div class="contact-info">
                                                    <div><?php echo htmlspecialchars($department['manager_name']); ?></div>
                                                    <div class="contact-detail"><?php echo htmlspecialchars($department['manager_email']); ?></div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Sin asignar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="count-badge">
                                                <?php echo number_format($department['total_users']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="status-badge <?php echo $department['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                                <?php echo $department['status'] === 'active' ? 'ACTIVE' : 'INACTIVE'; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="date-info">
                                                <?php echo formatDate($department['created_at']); ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <button class="btn-action btn-view" 
                                                        onclick="viewDepartmentDetails(<?php echo $department['id']; ?>)"
                                                        title="Ver detalles">
                                                    <i data-feather="eye"></i>
                                                </button>
                                                <button class="btn-action btn-edit" 
                                                        onclick="editDepartment(<?php echo $department['id']; ?>)"
                                                        title="Editar">
                                                    <i data-feather="edit-2"></i>
                                                </button>
                                                <button class="btn-action btn-toggle" 
                                                        onclick="toggleDepartmentStatus(<?php echo $department['id']; ?>, '<?php echo $department['status']; ?>')"
                                                        title="<?php echo $department['status'] === 'active' ? 'Desactivar' : 'Activar'; ?>">
                                                    <i data-feather="power"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modales -->
    <!-- Modal Crear/Editar Departamento -->
    <div id="departmentModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="departmentModalTitle">Nuevo Departamento</h5>
                    <button type="button" class="modal-close" onclick="closeDepartmentModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <form id="departmentForm">
                    <div class="modal-body">
                        <input type="hidden" id="departmentId" name="department_id">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="departmentName">Nombre del Departamento <span class="required">*</span></label>
                                <input type="text" 
                                       id="departmentName" 
                                       name="name" 
                                       required 
                                       placeholder="Ej: Recursos Humanos">
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label for="departmentCompany">Empresa <span class="required">*</span></label>
                                <select id="departmentCompany" name="company_id" required>
                                    <option value="">Seleccionar empresa</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>">
                                            <?php echo htmlspecialchars($company['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="form-group form-group-full">
                                <label for="departmentDescription">Descripción</label>
                                <textarea id="departmentDescription" 
                                          name="description" 
                                          rows="3" 
                                          placeholder="Describe las funciones y responsabilidades del departamento..."></textarea>
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label for="departmentManager">Manager/Jefe</label>
                                <select id="departmentManager" name="manager_id">
                                    <option value="">Sin asignar</option>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label for="departmentStatus">Estado</label>
                                <select id="departmentStatus" name="status">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeDepartmentModal()">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="saveDepartmentBtn">
                            <span class="btn-text">Guardar</span>
                            <span class="btn-spinner" style="display: none;">
                                <i data-feather="loader"></i>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ver Detalles -->
    <div id="viewDepartmentModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles del Departamento</h5>
                    <button type="button" class="modal-close" onclick="closeViewDepartmentModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="departmentDetails">
                        <!-- Contenido cargado dinámicamente -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeViewDepartmentModal()">
                        Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="editDepartmentFromView()">
                        <i data-feather="edit-2"></i>
                        Editar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/departments.js"></script>
    <script>
        // Inicializar iconos
        feather.replace();
        
        // Actualizar tiempo
        updateTime();
    </script>
</body>
</html>