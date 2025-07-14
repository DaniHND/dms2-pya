<?php
// modules/documents/inbox.php
// Bandeja de entrada de documentos - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Debug: verificar si el usuario está correctamente cargado
if (!$currentUser || !isset($currentUser['id'])) {
    // Redirigir al login si no hay usuario válido
    header('Location: ../../login.php');
    exit();
}

// Función para formatear bytes
function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}

// Función para obtener documentos según permisos del usuario
function getDocumentsByPermissions($userId, $companyId, $role, $groupId = null) {
    $documents = [];
    
    if ($role === 'admin') {
        // Admin puede ver todos los documentos
        $query = "SELECT d.*, c.name as company_name, dep.name as department_name, 
                         dt.name as document_type, u.first_name, u.last_name,
                         CASE 
                            WHEN d.mime_type LIKE 'image/%' THEN 'image'
                            WHEN d.mime_type = 'application/pdf' THEN 'pdf'
                            WHEN d.mime_type LIKE 'application/vnd.ms-excel%' OR d.mime_type LIKE 'application/vnd.openxmlformats-officedocument.spreadsheetml%' THEN 'excel'
                            WHEN d.mime_type LIKE 'application/msword%' OR d.mime_type LIKE 'application/vnd.openxmlformats-officedocument.wordprocessingml%' THEN 'word'
                            ELSE 'file'
                         END as file_icon
                  FROM documents d
                  LEFT JOIN companies c ON d.company_id = c.id
                  LEFT JOIN departments dep ON d.department_id = dep.id
                  LEFT JOIN document_types dt ON d.document_type_id = dt.id
                  LEFT JOIN users u ON d.user_id = u.id
                  WHERE d.status = 'active'
                  ORDER BY c.name, dep.name, d.created_at DESC";
        $documents = fetchAll($query);
    } else {
        // Usuario normal: solo documentos de su empresa
        $query = "SELECT d.*, c.name as company_name, dep.name as department_name, 
                         dt.name as document_type, u.first_name, u.last_name,
                         CASE 
                            WHEN d.mime_type LIKE 'image/%' THEN 'image'
                            WHEN d.mime_type = 'application/pdf' THEN 'pdf'
                            WHEN d.mime_type LIKE 'application/vnd.ms-excel%' OR d.mime_type LIKE 'application/vnd.openxmlformats-officedocument.spreadsheetml%' THEN 'excel'
                            WHEN d.mime_type LIKE 'application/msword%' OR d.mime_type LIKE 'application/vnd.openxmlformats-officedocument.wordprocessingml%' THEN 'word'
                            ELSE 'file'
                         END as file_icon
                  FROM documents d
                  LEFT JOIN companies c ON d.company_id = c.id
                  LEFT JOIN departments dep ON d.department_id = dep.id
                  LEFT JOIN document_types dt ON d.document_type_id = dt.id
                  LEFT JOIN users u ON d.user_id = u.id
                  WHERE d.status = 'active' AND d.company_id = :company_id";
        
        $params = ['company_id' => $companyId];
        $query .= " ORDER BY dep.name, d.created_at DESC";
        $documents = fetchAll($query, $params);
    }
    
    // Si no hay documentos, devolver array vacío
    return $documents ?: [];
}

// Función para organizar documentos por carpetas
function organizeDocumentsByFolders($documents) {
    $folders = [];
    
    foreach ($documents as $doc) {
        $companyName = $doc['company_name'] ?? 'Sin Empresa';
        $departmentName = $doc['department_name'] ?? 'General';
        
        $folderKey = $companyName . '/' . $departmentName;
        
        if (!isset($folders[$folderKey])) {
            $folders[$folderKey] = [
                'company' => $companyName,
                'department' => $departmentName,
                'documents' => [],
                'count' => 0
            ];
        }
        
        $folders[$folderKey]['documents'][] = $doc;
        $folders[$folderKey]['count']++;
    }
    
    return $folders;
}

// Función para verificar si el usuario puede descargar
function canUserDownload($userId) {
    $query = "SELECT download_enabled FROM users WHERE id = :id";
    $result = fetchOne($query, ['id' => $userId]);
    return $result ? ($result['download_enabled'] ?? true) : false;
}

// Obtener documentos
$documents = getDocumentsByPermissions(
    $currentUser['id'], 
    $currentUser['company_id'], 
    $currentUser['role'],
    $currentUser['group_id'] ?? null
);

// Organizar por carpetas
$folders = organizeDocumentsByFolders($documents);

// Verificar permisos de descarga
$canDownload = canUserDownload($currentUser['id']);

// Variables para filtros
$selectedFolder = $_GET['folder'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$selectedType = $_GET['type'] ?? '';

// Filtrar documentos si hay filtros activos
if ($selectedFolder || $searchTerm || $selectedType) {
    $filteredDocuments = [];
    
    foreach ($documents as $doc) {
        $include = true;
        
        // Filtro por carpeta
        if ($selectedFolder) {
            $docFolder = ($doc['company_name'] ?? 'Sin Empresa') . '/' . ($doc['department_name'] ?? 'General');
            if ($docFolder !== $selectedFolder) {
                $include = false;
            }
        }
        
        // Filtro por búsqueda
        if ($searchTerm && $include) {
            $searchFields = [
                $doc['name'],
                $doc['description'],
                $doc['document_type'],
                $doc['company_name'],
                $doc['department_name']
            ];
            
            $found = false;
            foreach ($searchFields as $field) {
                if ($field && stripos($field, $searchTerm) !== false) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $include = false;
            }
        }
        
        // Filtro por tipo
        if ($selectedType && $include) {
            if ($doc['file_icon'] !== $selectedType) {
                $include = false;
            }
        }
        
        if ($include) {
            $filteredDocuments[] = $doc;
        }
    }
    
    $documents = $filteredDocuments;
}

// Log de acceso
logActivity($currentUser['id'], 'view', 'documents', null, 'Usuario accedió a la bandeja de entrada');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bandeja de Entrada - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/documents.css">
    <link rel="stylesheet" href="../../assets/css/inbox.css">
    <script src="https://unpkg.com/feather-icons"></script>
</head>

<body class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="https://perdomoyasociados.com/wp-content/uploads/2023/09/logo_perdomo_2023_dorado-768x150.png" alt="Perdomo y Asociados" class="logo-image">
            </div>
        </div>

        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="../../dashboard.php" class="nav-link">
                        <i data-feather="home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="upload.php" class="nav-link">
                        <i data-feather="upload"></i>
                        <span>Subir Documentos</span>
                    </a>
                </li>

                <li class="nav-item active">
                    <a href="inbox.php" class="nav-link">
                        <i data-feather="inbox"></i>
                        <span>Bandeja de Entrada</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="search.php" class="nav-link">
                        <i data-feather="search"></i>
                        <span>Búsqueda Avanzada</span>
                    </a>
                </li>

                <li class="nav-divider"></li>

                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showComingSoon('Reportes')">
                        <i data-feather="bar-chart-2"></i>
                        <span>Reportes</span>
                    </a>
                </li>

                <?php if ($currentUser['role'] === 'admin'): ?>
                    <li class="nav-section">
                        <span>ADMINISTRACIÓN</span>
                    </li>

                    <li class="nav-item">
                        <a href="../users/list.php" class="nav-link">
                            <i data-feather="users"></i>
                            <span>Usuarios</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="../companies/list.php" class="nav-link">
                            <i data-feather="briefcase"></i>
                            <span>Empresas</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="../departments/list.php" class="nav-link">
                            <i data-feather="layers"></i>
                            <span>Departamentos</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="../groups/list.php" class="nav-link">
                            <i data-feather="shield"></i>
                            <span>Grupos</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </aside>

    <!-- Contenido principal -->
    <main class="main-content">
        <!-- Header -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Archivo</h1>
                <div class="header-stats">
                    <span class="stat-item">
                        <i data-feather="folder"></i>
                        <?php echo count($folders); ?> carpetas
                    </span>
                    <span class="stat-item">
                        <i data-feather="file"></i>
                        <?php echo count($documents); ?> documentos
                    </span>
                </div>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(getFullName()); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>

                <div class="header-actions">
                    <button class="btn-icon" onclick="showNotifications()">
                        <i data-feather="bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    <button class="btn-icon" onclick="showUserMenu()">
                        <i data-feather="settings"></i>
                    </button>
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido de la bandeja -->
        <div class="inbox-container">
            <!-- Panel de filtros -->
            <aside class="filters-panel">
                <div class="filters-header">
                    <h3>Explorar</h3>
                    <button class="btn-icon-sm" onclick="clearAllFilters()">
                        <i data-feather="refresh-cw"></i>
                    </button>
                </div>

                <!-- Búsqueda rápida -->
                <div class="search-section">
                    <form method="GET" action="" class="search-form">
                        <div class="search-input-group">
                            <i data-feather="search"></i>
                            <input type="text" name="search" placeholder="Buscar documentos..." 
                                   value="<?php echo htmlspecialchars($searchTerm); ?>">
                            <button type="submit" class="btn-search">
                                <i data-feather="arrow-right"></i>
                            </button>
                        </div>
                        <!-- Mantener otros filtros -->
                        <?php if ($selectedFolder): ?>
                            <input type="hidden" name="folder" value="<?php echo htmlspecialchars($selectedFolder); ?>">
                        <?php endif; ?>
                        <?php if ($selectedType): ?>
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($selectedType); ?>">
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Carpetas -->
                <div class="folders-section">
                    <h4>Carpetas</h4>
                    <div class="folders-list">
                        <a href="?" class="folder-item <?php echo !$selectedFolder ? 'active' : ''; ?>">
                            <i data-feather="home"></i>
                            <span>Todos los archivos</span>
                            <span class="count"><?php echo array_sum(array_column($folders, 'count')); ?></span>
                        </a>
                        
                        <?php foreach ($folders as $folderKey => $folder): ?>
                            <a href="?folder=<?php echo urlencode($folderKey); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $selectedType ? '&type=' . urlencode($selectedType) : ''; ?>" 
                               class="folder-item <?php echo $selectedFolder === $folderKey ? 'active' : ''; ?>">
                                <i data-feather="folder"></i>
                                <div class="folder-info">
                                    <span class="folder-name"><?php echo htmlspecialchars($folder['company']); ?></span>
                                    <small class="folder-dept"><?php echo htmlspecialchars($folder['department']); ?></small>
                                </div>
                                <span class="count"><?php echo $folder['count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Filtros por tipo -->
                <div class="types-section">
                    <h4>Tipos de archivo</h4>
                    <div class="types-list">
                        <a href="?<?php echo $selectedFolder ? 'folder=' . urlencode($selectedFolder) . '&' : ''; ?><?php echo $searchTerm ? 'search=' . urlencode($searchTerm) . '&' : ''; ?>" 
                           class="type-item <?php echo !$selectedType ? 'active' : ''; ?>">
                            <i data-feather="file"></i>
                            <span>Todos</span>
                        </a>
                        
                        <a href="?type=pdf<?php echo $selectedFolder ? '&folder=' . urlencode($selectedFolder) : ''; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" 
                           class="type-item <?php echo $selectedType === 'pdf' ? 'active' : ''; ?>">
                            <i data-feather="file-text"></i>
                            <span>PDF</span>
                        </a>
                        
                        <a href="?type=word<?php echo $selectedFolder ? '&folder=' . urlencode($selectedFolder) : ''; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" 
                           class="type-item <?php echo $selectedType === 'word' ? 'active' : ''; ?>">
                            <i data-feather="file-text"></i>
                            <span>Word</span>
                        </a>
                        
                        <a href="?type=excel<?php echo $selectedFolder ? '&folder=' . urlencode($selectedFolder) : ''; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" 
                           class="type-item <?php echo $selectedType === 'excel' ? 'active' : ''; ?>">
                            <i data-feather="grid"></i>
                            <span>Excel</span>
                        </a>
                        
                        <a href="?type=image<?php echo $selectedFolder ? '&folder=' . urlencode($selectedFolder) : ''; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" 
                           class="type-item <?php echo $selectedType === 'image' ? 'active' : ''; ?>">
                            <i data-feather="image"></i>
                            <span>Imágenes</span>
                        </a>
                    </div>
                </div>

                <!-- Información del usuario -->
                <div class="user-info-section">
                    <div class="permission-status">
                        <i data-feather="<?php echo $canDownload ? 'download' : 'download-cloud'; ?>"></i>
                        <span><?php echo $canDownload ? 'Descarga habilitada' : 'Descarga deshabilitada'; ?></span>
                    </div>
                </div>
            </aside>

            <!-- Panel principal de documentos -->
            <main class="documents-panel">
                <div class="documents-header">
                    <div class="view-controls">
                        <button class="view-btn active" data-view="grid" onclick="changeView('grid')">
                            <i data-feather="grid"></i>
                        </button>
                        <button class="view-btn" data-view="list" onclick="changeView('list')">
                            <i data-feather="list"></i>
                        </button>
                    </div>
                    
                    <div class="sort-controls">
                        <select id="sortBy" onchange="sortDocuments()">
                            <option value="name">Ordenar por nombre</option>
                            <option value="date">Ordenar por fecha</option>
                            <option value="size">Ordenar por tamaño</option>
                            <option value="type">Ordenar por tipo</option>
                        </select>
                    </div>
                </div>

                <div class="documents-content">
                    <?php if (empty($documents)): ?>
                        <div class="empty-state">
                            <i data-feather="folder-x"></i>
                            <h3>No se encontraron documentos</h3>
                            <p>
                                <?php if ($selectedFolder || $searchTerm || $selectedType): ?>
                                    No hay documentos que coincidan con los filtros seleccionados.
                                    <a href="?" class="clear-filters">Limpiar filtros</a>
                                <?php else: ?>
                                    Aún no hay documentos en tu bandeja de entrada.
                                    <a href="upload.php" class="upload-link">Subir tu primer documento</a>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="documents-grid" id="documentsGrid">
                            <?php foreach ($documents as $doc): ?>
                                <div class="document-card" data-id="<?php echo $doc['id']; ?>" onclick="showDocumentPreview(<?php echo $doc['id']; ?>)">
                                    <div class="document-preview">
                                        <div class="document-icon <?php echo $doc['file_icon']; ?>">
                                            <?php
                                            $iconMap = [
                                                'pdf' => 'file-text',
                                                'word' => 'file-text',
                                                'excel' => 'grid',
                                                'image' => 'image',
                                                'file' => 'file'
                                            ];
                                            $icon = $iconMap[$doc['file_icon']] ?? 'file';
                                            ?>
                                            <i data-feather="<?php echo $icon; ?>"></i>
                                        </div>
                                        <?php if ($doc['file_icon'] === 'image'): ?>
                                            <img src="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($doc['name']); ?>"
                                                 class="image-preview"
                                                 onerror="this.style.display='none'">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="document-info">
                                        <h4 class="document-name" title="<?php echo htmlspecialchars($doc['name']); ?>">
                                            <?php echo htmlspecialchars($doc['name']); ?>
                                        </h4>
                                        <div class="document-meta">
                                            <span class="document-type">
                                                <?php echo htmlspecialchars($doc['document_type'] ?? 'Sin tipo'); ?>
                                            </span>
                                            <span class="document-size">
                                                <?php echo formatBytes($doc['file_size']); ?>
                                            </span>
                                        </div>
                                        <div class="document-location">
                                            <i data-feather="map-pin"></i>
                                            <span><?php echo htmlspecialchars($doc['company_name']); ?></span>
                                            <?php if ($doc['department_name']): ?>
                                                <span> / <?php echo htmlspecialchars($doc['department_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="document-date">
                                            <i data-feather="calendar"></i>
                                            <span><?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="document-actions" onclick="event.stopPropagation()">
                                        <button class="action-btn" onclick="showDocumentPreview(<?php echo $doc['id']; ?>)" title="Ver">
                                            <i data-feather="eye"></i>
                                        </button>
                                        
                                        <?php if ($canDownload): ?>
                                            <button class="action-btn" onclick="downloadDocument(<?php echo $doc['id']; ?>)" title="Descargar">
                                                <i data-feather="download"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="action-btn disabled" title="Descarga deshabilitada">
                                                <i data-feather="download-cloud"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="action-btn" onclick="shareDocument(<?php echo $doc['id']; ?>)" title="Compartir">
                                            <i data-feather="share-2"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>

            <!-- Panel de vista previa -->
            <aside class="preview-panel" id="previewPanel">
                <div class="preview-header">
                    <h3>Vista Previa</h3>
                    <button class="btn-icon-sm" onclick="closePreview()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                
                <div class="preview-content" id="previewContent">
                    <div class="preview-placeholder">
                        <i data-feather="eye"></i>
                        <p>Selecciona un documento para ver la vista previa</p>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <!-- Overlay para móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <script>
        // Variables globales
        let currentView = 'grid';
        let currentSort = 'name';
        let documentsData = <?php echo json_encode($documents); ?>;

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);
            initializeInbox();
        });
    </script>
    <script src="../../assets/js/inbox.js"></script>
</body>
</html>