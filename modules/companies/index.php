<?php
/*
 * modules/companies/index.php
 * Módulo de Gestión de Empresas - DMS2
 * Estructura basada en el módulo de usuarios exitoso
 */

require_once '../../config/session.php';
require_once '../../config/database.php';

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


// Verificar sesión y permisos
SessionManager::requireRole('admin');
$currentUser = SessionManager::getCurrentUser();

// Obtener conexión a la base de datos
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo establecer conexión con la base de datos");
    }
} catch (Exception $e) {
    error_log("Error de conexión a base de datos en companies/index.php: " . $e->getMessage());
    die("Error de conexión a la base de datos. Por favor, contacte al administrador.");
}

// Configuración de paginación
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Filtros
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// Construir consulta base
$whereConditions = ["c.status != 'deleted'"];
$params = [];

if (!empty($filters['search'])) {
    $whereConditions[] = "(c.name LIKE ? OR c.description LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($filters['status'])) {
    $whereConditions[] = "c.status = ?";
    $params[] = $filters['status'];
}

$whereClause = implode(' AND ', $whereConditions);

// Obtener estadísticas
try {
    $statsQuery = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN c.status = 'inactive' THEN 1 ELSE 0 END) as inactive
                   FROM companies c 
                   WHERE " . $whereClause;
    
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas de empresas: " . $e->getMessage());
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0
    ];
}

// Obtener empresas con paginación
try {
    $companiesQuery = "SELECT c.*,
                       (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.status != 'deleted') as user_count,
                       (SELECT COUNT(*) FROM documents d JOIN users u ON d.user_id = u.id WHERE u.company_id = c.id) as document_count
                       FROM companies c
                       WHERE " . $whereClause . "
                       ORDER BY c.created_at DESC
                       LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($companiesQuery);
    $stmt->execute(array_merge($params, [$itemsPerPage, $offset]));
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total de páginas
    $countQuery = "SELECT COUNT(*) FROM companies c WHERE " . $whereClause;
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalCompanies = $stmt->fetchColumn();
    $totalPages = ceil($totalCompanies / $itemsPerPage);
    
} catch (Exception $e) {
    error_log("Error obteniendo empresas: " . $e->getMessage());
    $companies = [];
    $totalCompanies = 0;
    $totalPages = 1;
}

// Función para obtener clase de badge de estado
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active': return 'status-active';
        case 'inactive': return 'status-inactive';
        default: return 'status-inactive';
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
    <title>Gestión de Empresas - DMS2</title>
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    
    <!-- CSS del Módulo de Empresas -->
    <link rel="stylesheet" href="../../assets/css/companies.css">
    
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
        /* Estilos adicionales específicos para empresas */
        
        /* Botón crear empresa más destacado */
        .btn-create-company {
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
        
        .btn-create-company:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4);
            background: linear-gradient(135deg, var(--dms-primary-hover) 0%, #4a2c0a 100%);
        }
        
        .btn-create-company span {
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
        
        /* Información de empresa */
        .company-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .company-avatar {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--dms-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .company-details {
            min-width: 0;
            flex: 1;
        }
        
        .company-name {
            font-weight: 600;
            color: var(--dms-text);
            margin: 0;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .company-description {
            color: var(--dms-text-muted);
            font-size: 13px;
            margin: 2px 0 0 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .company-details-grid {
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
        
        @media (max-width: 768px) {
            .company-details-grid {
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
                <h1>Gestión de Empresas</h1>
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

        <!-- Contenido del módulo de empresas -->
        <div class="companies-container">
            <!-- Título y botón principal -->
            <div class="page-header">

                <div class="page-title-section">
                    <button class="btn btn-primary btn-create-company" onclick="openCreateCompanyModal()">
                        <i data-feather="briefcase"></i>
                        <span>Crear Empresa</span>
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-section">
                <h3>Filtros de Búsqueda</h3>
                <form class="filters-form" method="GET">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="search">Buscar Empresa</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   class="form-input"
                                   placeholder="Nombre, descripción, email..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Estado</label>
                            <select id="status" name="status" class="form-input">
                                <option value="">Todos los estados</option>
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                        
                      
                    </div>
                </form>
            </div>

            <!-- Tabla de empresas -->
            <div class="table-section">
                <div class="table-header-info">
                    <div>
                        <h3>Lista de Empresas</h3>
                        <p>Mostrando <?php echo count($companies); ?> de <?php echo $totalCompanies; ?> empresas</p>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <?php if (empty($companies)): ?>
                        <div class="table-empty">
                            <i data-feather="briefcase"></i>
                            <h4>No se encontraron empresas</h4>
                            <p>No hay empresas que coincidan con los filtros aplicados.</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th>Contacto</th>
                                    <th>Estado</th>
                                    <th>Usuarios</th>
                                    <th>Documentos</th>
                                    <th>Fecha Registro</th>
                                    <th class="actions-cell">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($companies as $company): ?>
                                    <tr>
                                        <td>
                                            <div class="company-info">
                                                <div class="company-avatar">
                                                    <?php echo strtoupper(substr($company['name'], 0, 2)); ?>
                                                </div>
                                                <div class="company-details">
                                                    <div class="company-name">
                                                        <?php echo htmlspecialchars($company['name']); ?>
                                                    </div>
                                                    <div class="company-description">
                                                        <?php echo htmlspecialchars($company['description'] ?? 'Sin descripción'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php if (!empty($company['email'])): ?>
                                                    <div style="font-size: 13px;"><?php echo htmlspecialchars($company['email']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($company['phone'])): ?>
                                                    <div style="font-size: 12px; color: #6b7280;"><?php echo htmlspecialchars($company['phone']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusBadgeClass($company['status']); ?>">
                                                <?php echo htmlspecialchars($company['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-count"><?php echo number_format($company['user_count']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge-count"><?php echo number_format($company['document_count']); ?></span>
                                        </td>
                                        <td><?php echo formatDate($company['created_at']); ?></td>
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <button class="btn-action btn-view" 
                                                        onclick="showCompanyDetails(<?php echo $company['id']; ?>)"
                                                        title="Ver detalles">
                                                    <i data-feather="eye"></i>
                                                </button>
                                                
                                                <button class="btn-action btn-edit" 
                                                        onclick="editCompany(<?php echo $company['id']; ?>)"
                                                        title="Editar empresa">
                                                    <i data-feather="edit-2"></i>
                                                </button>
                                                
                                                <button class="btn-action btn-toggle" 
                                                        onclick="toggleCompanyStatus(<?php echo $company['id']; ?>, '<?php echo $company['status']; ?>')"
                                                        title="<?php echo $company['status'] === 'active' ? 'Desactivar' : 'Activar'; ?> empresa">
                                                    <i data-feather="<?php echo $company['status'] === 'active' ? 'pause-circle' : 'play-circle'; ?>"></i>
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
    <script src="../../assets/js/companies.js"></script>
    
    <script>
        // Datos globales para JavaScript
        window.companyData = {
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
        
        console.log('✅ Módulo de empresas cargado correctamente');
        
        // Debug para verificar que las funciones están disponibles
        console.log('Funciones disponibles:');
        console.log('- openCreateCompanyModal:', typeof window.openCreateCompanyModal);
        console.log('- showCompanyDetails:', typeof window.showCompanyDetails);
        console.log('- editCompany:', typeof window.editCompany);
        console.log('- toggleCompanyStatus:', typeof window.toggleCompanyStatus);
    </script>
</body>
</html>