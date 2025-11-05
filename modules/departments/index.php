<?php
// modules/departments/index.php
// Módulo de gestión de departamentos - DMS2 - VERSIÓN CORREGIDA

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Funciones helper agregadas automáticamente

if (!function_exists('getFullName')) {
    function getFullName() {
        $user = SessionManager::getCurrentUser();
        return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    }
}

if (!function_exists('logActivity')) {
    function logActivity($userId, $action, $tableName, $recordId = null, $description = '') {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $query = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$userId, $action, $tableName, $recordId, $description]);
        } catch (Exception $e) {
            error_log('Error logging activity: ' . $e->getMessage());
        }
    }
}

if (!function_exists('fetchAll')) {
    function fetchAll($query, $params = []) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error in fetchAll: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('fetchOne')) {
    function fetchOne($query, $params = []) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error in fetchOne: ' . $e->getMessage());
            return false;
        }
    }
}


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

// CORRECCIÓN AQUÍ - Usar placeholders únicos
if (!empty($filters['search'])) {
    $whereConditions[] = "(d.name LIKE :search OR d.description LIKE :search2 OR c.name LIKE :search3 OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search4)";
    $params['search'] = '%' . $filters['search'] . '%';
    $params['search2'] = '%' . $filters['search'] . '%';
    $params['search3'] = '%' . $filters['search'] . '%';
    $params['search4'] = '%' . $filters['search'] . '%';
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

try {
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
    $totalItems = 0;
    $totalPages = 1;
    $error = "Error al cargar departamentos: " . $e->getMessage();
}

// Obtener empresas para filtros y formularios
try {
    $companies = fetchAll("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name");
} catch (Exception $e) {
    $companies = [];
}

// Funciones helper
function getStatusBadgeClass($status)
{
    return $status === 'active' ? 'status-active' : 'status-inactive';
}

function formatDate($date)
{
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

    <!-- CSS Principal del sistema -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">

    <!-- CSS específico para departamentos -->
    <link rel="stylesheet" href="../../assets/css/departments.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">

    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<style>
     /* Botón crear departamento más destacado */
    .btn-create-departament {
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

    .btn-create-departament:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4);
        background: linear-gradient(135deg, var(--dms-primary-hover) 0%, #4a2c0a 100%);
    }

    .btn-create-departament span {
        margin-left: 2px;
    }
</style>

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
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido del módulo -->
        <div class="container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i data-feather="alert-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Botón Crear Departamento -->
            <div class="create-button-section">
                <button class="btn btn-primary btn-create-departament" onclick="openCreateDepartmentModal()">
                    <i data-feather="layers"></i>
                    <span>Crear Departamento</span>
                </button>
            </div>

            <!-- Filtros de Búsqueda -->
            <div class="filters-card">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" class="filters-form" id="filtersForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Buscar Departamento</label>
                            <input type="text"
                                id="search"
                                name="search"
                                placeholder="Nombre, descripción, empresa..."
                                value="<?php echo htmlspecialchars($filters['search']); ?>"
                                onkeyup="handleFilterChange()">
                        </div>

                        <div class="filter-group">
                            <label for="status">Estado</label>
                            <select id="status" name="status" onchange="handleFilterChange()">
                                <option value="">Todos los estados</option>
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>
                                    Activo
                                </option>
                                <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>
                                    Inactivo
                                </option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="company_id">Empresa</label>
                            <select id="company_id" name="company_id" onchange="handleFilterChange()">
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

            <!-- Tabla de departamentos -->
            <div class="table-section">
                <div class="table-header">
                    <h3>Departamentos (<?php echo $totalItems; ?> registros)</h3>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Departamento</th>
                                <th>Empresa</th>
                                <th>Manager</th>
                                <th>Usuarios</th>
                                <th>Estado</th>
                                <th>Fecha Creación</th>
                                <th class="actions-header">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($departments)): ?>
                                <tr>
                                    <td colspan="7" class="no-data">
                                        <i data-feather="building"></i>
                                        <p>No se encontraron departamentos</p>
                                        <button class="btn btn-primary" onclick="openCreateDepartmentModal()">
                                            <i data-feather="plus"></i>
                                            Crear primer departamento
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($departments as $department): ?>
                                    <tr>
                                        <td>
                                            <div class="cell-content">
                                                <div class="primary-text"><?php echo htmlspecialchars($department['name']); ?></div>
                                                <?php if (!empty($department['description'])): ?>
                                                    <div class="secondary-text"><?php echo htmlspecialchars($department['description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cell-content">
                                                <div class="primary-text"><?php echo htmlspecialchars($department['company_name'] ?? 'Sin empresa'); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cell-content">
                                                <div class="primary-text"><?php echo htmlspecialchars($department['manager_name'] ?? 'Sin asignar'); ?></div>
                                                <?php if (!empty($department['manager_email'])): ?>
                                                    <div class="secondary-text"><?php echo htmlspecialchars($department['manager_email']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cell-content">
                                                <div class="primary-text"><?php echo $department['total_users']; ?> usuarios</div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusBadgeClass($department['status']); ?>">
                                                <?php echo $department['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="cell-content">
                                                <div class="primary-text"><?php echo formatDate($department['created_at']); ?></div>
                                            </div>
                                        </td>
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <button class="btn-action view"
                                                    onclick="viewDepartmentDetails(<?php echo $department['id']; ?>)"
                                                    title="Ver detalles">
                                                    <i data-feather="eye"></i>
                                                </button>
                                                <button class="btn-action edit"
                                                    onclick="editDepartment(<?php echo $department['id']; ?>)"
                                                    title="Editar departamento">
                                                    <i data-feather="edit"></i>
                                                </button>
                                                <button class="btn-action delete"
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

                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-section">
                        <div class="pagination-info">
                            Mostrando <?php echo count($departments); ?> de <?php echo $totalItems; ?> registros
                        </div>

                        <div class="pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="?page=<?php echo $currentPage - 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>" class="pagination-btn">
                                    <i data-feather="chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($filters)); ?>"
                                    class="pagination-btn <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?page=<?php echo $currentPage + 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>" class="pagination-btn">
                                    <i data-feather="chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/departments.js"></script>
    <script>
        // Inicializar iconos
        feather.replace();

        // Actualizar tiempo
        updateTime();

        // Variable para almacenar el timeout del debounce
        let searchTimeout = null;

        // Función para manejar cambios en filtros con debounce
        function handleFilterChange() {
            // Limpiar timeout anterior
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            // Crear nuevo timeout de 800ms
            searchTimeout = setTimeout(() => {
                const form = document.getElementById('filtersForm');
                if (form) {
                    form.submit();
                }
            }, 800);
        }
    </script>
</body>

</html>