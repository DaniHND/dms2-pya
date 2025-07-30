<?php
// modules/document-types/index.php
// Módulo de gestión de tipos de documentos - DMS2

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
    'status' => $_GET['status'] ?? ''
];

// Construir consulta con filtros
$whereConditions = [];
$params = [];

if (!empty($filters['search'])) {
    $whereConditions[] = "(dt.name LIKE :search OR dt.description LIKE :search)";
    $params['search'] = '%' . $filters['search'] . '%';
}

if (!empty($filters['status'])) {
    $whereConditions[] = "dt.status = :status";
    $params['status'] = $filters['status'];
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Contar total de registros
$countQuery = "SELECT COUNT(*) as total 
               FROM document_types dt 
               $whereClause";

try {
    $totalItems = fetchOne($countQuery, $params)['total'];
    $totalPages = ceil($totalItems / $itemsPerPage);

    // Obtener tipos de documentos con estadísticas
    $query = "SELECT dt.*, 
                     (SELECT COUNT(*) FROM documents d WHERE d.document_type_id = dt.id AND d.status = 'active') as documents_count
              FROM document_types dt 
              $whereClause
              ORDER BY dt.created_at DESC 
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
    $documentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $documentTypes = [];
    $totalItems = 0;
    $totalPages = 1;
    $error = "Error al cargar tipos de documentos: " . $e->getMessage();
}

// Funciones helper
function getStatusBadgeClass($status) {
    return $status === 'active' ? 'status-active' : 'status-inactive';
}

function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Registrar actividad
logActivity($currentUser['id'], 'view_document_types', 'document_types', null, 'Usuario accedió al módulo de tipos de documentos');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Documentos - DMS2</title>
    
    <!-- CSS Principal del sistema -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    
    <!-- CSS específico para tipos de documentos -->
    <link rel="stylesheet" href="../../assets/css/document-types.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">
    
    <!-- Feather Icons -->
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
                <h1>Tipos de Documentos</h1>
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

        <!-- Contenido del módulo -->
        <div class="container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i data-feather="alert-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Header de página con botón crear - MISMO ESTILO QUE OTROS MÓDULOS -->
            <div class="page-header">
                <div class="page-title-section">
                    <button class="btn btn-primary btn-create-company" onclick="openCreateDocumentTypeModal()">
                        <i data-feather="file-text"></i>
                        <span>Crear Tipo de Documento</span>
                    </button>
                </div>
            </div>

            <!-- Filtros de Búsqueda -->
            <div class="filters-card">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" class="filters-form" id="filtersForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Buscar Tipo de Documento</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   placeholder="Nombre, descripción..." 
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
                    </div>
                </form>
            </div>

            <!-- Tabla de tipos de documentos -->
            <div class="table-section">
                <div class="table-header">
                    <h3>Tipos de Documentos (<?php echo $totalItems; ?> registros)</h3>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tipo de Documento</th>
                                <th>Documentos</th>
                                <th>Estado</th>
                                <th>Fecha Creación</th>
                                <th class="actions-header">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documentTypes)): ?>
                                <tr>
                                    <td colspan="5" class="no-data">
                                        <i data-feather="file-text"></i>
                                        <p>No se encontraron tipos de documentos</p>
                                        <button class="btn btn-primary" onclick="openCreateDocumentTypeModal()">
                                            <i data-feather="plus"></i>
                                            Crear primer tipo de documento
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documentTypes as $docType): ?>
                                    <tr>
                                        <td>
                                            <div class="cell-content">
                                                <div class="primary-text"><?php echo htmlspecialchars($docType['name']); ?></div>
                                                <?php if (!empty($docType['description'])): ?>
                                                    <div class="secondary-text"><?php echo htmlspecialchars($docType['description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cell-content">
                                                <div class="primary-text"><?php echo $docType['documents_count']; ?> documentos</div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusBadgeClass($docType['status']); ?>">
                                                <?php echo $docType['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="cell-content">
                                                <div class="primary-text"><?php echo formatDate($docType['created_at']); ?></div>
                                            </div>
                                        </td>
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <button class="btn-action view" 
                                                        onclick="viewDocumentTypeDetails(<?php echo $docType['id']; ?>)"
                                                        title="Ver detalles">
                                                    <i data-feather="eye"></i>
                                                </button>
                                                <button class="btn-action edit" 
                                                        onclick="editDocumentType(<?php echo $docType['id']; ?>)"
                                                        title="Editar tipo de documento">
                                                    <i data-feather="edit"></i>
                                                </button>
                                                <button class="btn-action delete" 
                                                        onclick="toggleDocumentTypeStatus(<?php echo $docType['id']; ?>, '<?php echo $docType['status']; ?>')"
                                                        title="<?php echo $docType['status'] === 'active' ? 'Desactivar' : 'Activar'; ?>">
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
                            Mostrando <?php echo count($documentTypes); ?> de <?php echo $totalItems; ?> registros
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
    <script src="../../assets/js/document-types.js"></script>
    <script>
        // Inicializar iconos
        feather.replace();
        
        // Actualizar tiempo
        updateTime();
        
        // Función para manejar cambios en filtros (filtrado automático)
        function handleFilterChange() {
            const form = document.getElementById('filtersForm');
            if (form) {
                form.submit();
            }
        }
    </script>
</body>
</html>