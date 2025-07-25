<?php
// modules/documents/inbox.php - CÓDIGO COMPLETO REPARADO
// Bandeja de entrada de documentos - DMS2

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

function getDocumentsByPermissions($userId, $userCompanyId, $userRole)
{
    try {
        if ($userRole === 'admin') {
            // Admin puede ver todos los documentos
            $query = "SELECT d.*, 
                             dt.name as document_type, dt.icon as file_icon,
                             c.name as company_name, 
                             dep.name as department_name,
                             CONCAT(u.first_name, ' ', u.last_name) as uploaded_by
                      FROM documents d
                      LEFT JOIN document_types dt ON d.document_type_id = dt.id
                      LEFT JOIN companies c ON d.company_id = c.id  
                      LEFT JOIN departments dep ON d.department_id = dep.id
                      LEFT JOIN users u ON d.user_id = u.id
                      WHERE d.status = 'active'
                      ORDER BY d.created_at DESC";
            
            return fetchAll($query);
        } else {
            // Usuario normal solo ve documentos de su empresa
            $query = "SELECT d.*, 
                             dt.name as document_type, dt.icon as file_icon,
                             c.name as company_name, 
                             dep.name as department_name,
                             CONCAT(u.first_name, ' ', u.last_name) as uploaded_by
                      FROM documents d
                      LEFT JOIN document_types dt ON d.document_type_id = dt.id
                      LEFT JOIN companies c ON d.company_id = c.id  
                      LEFT JOIN departments dep ON d.department_id = dep.id
                      LEFT JOIN users u ON d.user_id = u.id
                      WHERE d.status = 'active' AND d.company_id = :company_id
                      ORDER BY d.created_at DESC";
            
            return fetchAll($query, ['company_id' => $userCompanyId]);
        }
    } catch (Exception $e) {
        error_log("Error getting documents: " . $e->getMessage());
        return [];
    }
}

function organizeDocumentsByFolders($documents)
{
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

function canUserDownload($userId)
{
    $query = "SELECT download_enabled FROM users WHERE id = :id";
    $result = fetchOne($query, ['id' => $userId]);
    return $result ? ($result['download_enabled'] ?? true) : false;
}

function canUserDelete($userId, $role, $documentOwnerId)
{
    return ($role === 'admin') || ($userId == $documentOwnerId);
}

// Función para ordenar documentos
function sortDocuments($documents, $sortBy, $sortOrder) {
    if (empty($documents)) {
        return $documents;
    }
    
    usort($documents, function($a, $b) use ($sortBy, $sortOrder) {
        $result = 0;
        
        switch ($sortBy) {
            case 'name':
                $result = strcasecmp($a['name'], $b['name']);
                break;
                
            case 'date':
                $dateA = strtotime($a['created_at']);
                $dateB = strtotime($b['created_at']);
                $result = $dateA - $dateB;
                break;
                
            case 'size':
                $sizeA = intval($a['file_size'] ?? 0);
                $sizeB = intval($b['file_size'] ?? 0);
                $result = $sizeA - $sizeB;
                break;
                
            case 'type':
                $typeA = $a['document_type'] ?? '';
                $typeB = $b['document_type'] ?? '';
                $result = strcasecmp($typeA, $typeB);
                break;
        }
        
        // Aplicar orden ascendente o descendente
        return $sortOrder === 'desc' ? -$result : $result;
    });
    
    return $documents;
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function formatDate($dateString) {
    $date = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days == 0) {
        return 'Hoy ' . $date->format('H:i');
    } elseif ($diff->days == 1) {
        return 'Ayer ' . $date->format('H:i');
    } elseif ($diff->days < 7) {
        return $diff->days . ' días';
    } else {
        return $date->format('d/m/Y');
    }
}

// ==========================================
// OBTENER Y PROCESAR DATOS
// ==========================================

// Obtener datos
$documents = getDocumentsByPermissions($currentUser['id'], $currentUser['company_id'], $currentUser['role']);
$folders = organizeDocumentsByFolders($documents);
$canDownload = canUserDownload($currentUser['id']);

// Filtros
$selectedFolder = $_GET['folder'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$selectedType = $_GET['type'] ?? '';

// Parámetros de ordenación
$sortBy = $_GET['sort'] ?? 'name';
$sortOrder = $_GET['order'] ?? 'asc';

// Validar parámetros de ordenación
$validSortFields = ['name', 'date', 'size', 'type'];
$validSortOrders = ['asc', 'desc'];

if (!in_array($sortBy, $validSortFields)) {
    $sortBy = 'name';
}

if (!in_array($sortOrder, $validSortOrders)) {
    $sortOrder = 'asc';
}

// Aplicar filtros
if ($selectedFolder || $searchTerm || $selectedType) {
    $filteredDocuments = [];
    foreach ($documents as $doc) {
        $include = true;

        if ($selectedFolder) {
            $docFolder = ($doc['company_name'] ?? 'Sin Empresa') . '/' . ($doc['department_name'] ?? 'General');
            if ($docFolder !== $selectedFolder) {
                $include = false;
            }
        }

        if ($searchTerm && $include) {
            $searchFields = [$doc['name'], $doc['description'], $doc['document_type'], $doc['company_name'], $doc['department_name']];
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

// Aplicar ordenación después de filtros
$documents = sortDocuments($documents, $sortBy, $sortOrder);

// Registrar acceso
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
    <link rel="stylesheet" href="../../assets/css/inbox.css">
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
                <h1>Bandeja de Entrada</h1>
                <div class="header-stats">
                    <div class="stat-item">
                        <i data-feather="file"></i>
                        <span><?php echo count($documents); ?> documentos</span>
                    </div>
                    <div class="stat-item">
                        <i data-feather="folder"></i>
                        <span><?php echo count($folders); ?> carpetas</span>
                    </div>
                </div>
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

                <!-- Búsqueda -->
                <div class="search-section">
                    <form method="GET" action="">
                        <div class="search-input-group">
                            <i data-feather="search"></i>
                            <input type="text" name="search" placeholder="Buscar documentos..."
                                value="<?php echo htmlspecialchars($searchTerm); ?>">
                            <button type="submit" class="btn-search">
                                <i data-feather="arrow-right"></i>
                            </button>
                        </div>
                        <?php if ($selectedFolder): ?>
                            <input type="hidden" name="folder" value="<?php echo htmlspecialchars($selectedFolder); ?>">
                        <?php endif; ?>
                        <?php if ($selectedType): ?>
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($selectedType); ?>">
                        <?php endif; ?>
                        <?php if ($sortBy !== 'name'): ?>
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortBy); ?>">
                        <?php endif; ?>
                        <?php if ($sortOrder !== 'asc'): ?>
                            <input type="hidden" name="order" value="<?php echo htmlspecialchars($sortOrder); ?>">
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
                            <a href="?folder=<?php echo urlencode($folderKey); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $sortBy !== 'name' ? '&sort=' . urlencode($sortBy) : ''; ?><?php echo $sortOrder !== 'asc' ? '&order=' . urlencode($sortOrder) : ''; ?>"
                                class="folder-item <?php echo $selectedFolder === $folderKey ? 'active' : ''; ?>">
                                <i data-feather="folder"></i>
                                <div class="folder-info">
                                    <span class="folder-name"><?php echo htmlspecialchars($folder['company']); ?></span>
                                    <span class="folder-dept"><?php echo htmlspecialchars($folder['department']); ?></span>
                                </div>
                                <span class="count"><?php echo $folder['count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tipos de archivo -->
                <div class="types-section">
                    <h4>Tipos</h4>
                    <div class="types-list">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => ''])); ?>" 
                           class="type-item <?php echo !$selectedType ? 'active' : ''; ?>">
                            <i data-feather="file"></i>
                            <span>Todos</span>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'pdf'])); ?>" 
                           class="type-item <?php echo $selectedType === 'pdf' ? 'active' : ''; ?>">
                            <i data-feather="file-text"></i>
                            <span>PDF</span>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'word'])); ?>" 
                           class="type-item <?php echo $selectedType === 'word' ? 'active' : ''; ?>">
                            <i data-feather="file-text"></i>
                            <span>Word</span>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'excel'])); ?>" 
                           class="type-item <?php echo $selectedType === 'excel' ? 'active' : ''; ?>">
                            <i data-feather="grid"></i>
                            <span>Excel</span>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'image'])); ?>" 
                           class="type-item <?php echo $selectedType === 'image' ? 'active' : ''; ?>">
                            <i data-feather="image"></i>
                            <span>Imágenes</span>
                        </a>
                    </div>
                </div>

                <!-- Info usuario -->
                <div class="user-info-section">
                    <div class="permission-status">
                        <i data-feather="<?php echo $canDownload ? 'download' : 'download-cloud'; ?>"></i>
                        <span><?php echo $canDownload ? 'Descarga habilitada' : 'Descarga deshabilitada'; ?></span>
                    </div>
                </div>
            </aside>

            <!-- Panel de documentos -->
            <main class="documents-panel-full">
                <div class="documents-header">
                    <div class="view-controls">
                        <button class="view-btn active" data-view="grid" onclick="changeView('grid')">
                            <i data-feather="grid"></i>
                            <span>Cuadros</span>
                        </button>
                        <button class="view-btn" data-view="list" onclick="changeView('list')">
                            <i data-feather="list"></i>
                            <span>Lista</span>
                        </button>
                    </div>
                    
                    <div class="sort-controls">
                        <select id="sortBy" onchange="sortDocuments()">
                            <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>
                                📝 Ordenar por nombre <?php echo $sortBy === 'name' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?>
                            </option>
                            <option value="date" <?php echo $sortBy === 'date' ? 'selected' : ''; ?>>
                                📅 Ordenar por fecha <?php echo $sortBy === 'date' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?>
                            </option>
                            <option value="size" <?php echo $sortBy === 'size' ? 'selected' : ''; ?>>
                                📏 Ordenar por tamaño <?php echo $sortBy === 'size' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?>
                            </option>
                            <option value="type" <?php echo $sortBy === 'type' ? 'selected' : ''; ?>>
                                📁 Ordenar por tipo <?php echo $sortBy === 'type' ? ($sortOrder === 'asc' ? '↑' : '↓') : ''; ?>
                            </option>
                        </select>
                        
                        <!-- Botón para alternar orden -->
                        <button type="button" class="btn-icon-sm" onclick="toggleSortOrder()" title="Cambiar orden">
                            <i data-feather="<?php echo $sortOrder === 'asc' ? 'arrow-up' : 'arrow-down'; ?>"></i>
                        </button>
                    </div>
                </div>

                <div class="documents-content">
                    <?php if (empty($documents)): ?>
                        <div class="empty-state">
                            <i data-feather="folder-x"></i>
                            <h3>No se encontraron documentos</h3>
                            <p>
                                <?php if ($selectedFolder || $searchTerm || $selectedType): ?>
                                    No hay documentos que coincidan con los filtros.
                                    <a href="?" class="clear-filters">Limpiar filtros</a>
                                <?php else: ?>
                                    Aún no hay documentos.
                                    <a href="upload.php" class="upload-link">Subir documento</a>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="documents-grid" id="documentsGrid">
                            <?php foreach ($documents as $doc): ?>
                                <?php $canDelete = canUserDelete($currentUser['id'], $currentUser['role'], $doc['user_id']); ?>
                                <div class="document-card" data-id="<?php echo $doc['id']; ?>"
                                     data-date="<?php echo strtotime($doc['created_at']); ?>"
                                     data-size="<?php echo $doc['file_size'] ?? 0; ?>"
                                     data-type="<?php echo htmlspecialchars($doc['document_type'] ?? ''); ?>">
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
                                            <img src="../../<?php echo htmlspecialchars($doc['file_path']); ?>"
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
                                                <?php echo htmlspecialchars($doc['document_type'] ?? 'Documento'); ?>
                                            </span>
                                            <span class="document-size">
                                                <?php echo formatFileSize($doc['file_size'] ?? 0); ?>
                                            </span>
                                            <span class="document-date">
                                                <?php echo formatDate($doc['created_at']); ?>
                                            </span>
                                        </div>
                                        <div class="document-company">
                                            <?php echo htmlspecialchars($doc['company_name'] . ' • ' . $doc['department_name']); ?>
                                        </div>
                                    </div>

                                    <div class="document-actions">
                                        <button class="action-btn view-btn" data-doc-id="<?php echo $doc['id']; ?>" title="Ver">
                                            <i data-feather="eye"></i>
                                        </button>
                                        
                                        <?php if ($canDownload): ?>
                                            <button class="action-btn download-btn" data-doc-id="<?php echo $doc['id']; ?>" title="Descargar">
                                                <i data-feather="download"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($canDelete): ?>
                                            <button class="action-btn delete-btn" data-doc-id="<?php echo $doc['id']; ?>" title="Eliminar">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </main>

    <!-- DATOS PARA JAVASCRIPT -->
    <script>
        // Datos del usuario actual para JavaScript
        const phpUserData = {
            id: <?php echo json_encode($currentUser['id']); ?>,
            role: <?php echo json_encode($currentUser['role']); ?>,
            canDownload: <?php echo json_encode($canDownload); ?>,
            firstName: <?php echo json_encode($currentUser['first_name']); ?>,
            lastName: <?php echo json_encode($currentUser['last_name']); ?>
        };

        // Datos de documentos para JavaScript  
        const phpDocumentsData = <?php echo json_encode($documents); ?>;

        // Variables globales para compatibilidad
        const currentUserId = phpUserData.id;
        const currentUserRole = phpUserData.role;
        const canDownload = phpUserData.canDownload;
        
        console.log('🔧 DATOS PHP CARGADOS:');
        console.log('- Usuario ID:', currentUserId);
        console.log('- Rol:', currentUserRole);
        console.log('- Puede descargar:', canDownload);
        console.log('- Documentos:', phpDocumentsData.length);
        console.log('- Ordenado por:', '<?php echo $sortBy; ?>', '<?php echo $sortOrder; ?>');
    </script>

    <!-- Scripts principales -->
    <script src="../../assets/js/inbox.js"></script>
    <script>
        // Inicializar iconos Feather después de cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof feather !== 'undefined') {
                feather.replace();
                console.log('✅ Iconos Feather renderizados');
            }
        });

        // Función para mostrar mensajes "próximamente"
        function showComingSoon(feature) {
            showNotification(`🚧 ${feature} estará disponible próximamente`, 'info', 3000);
        }
    </script>

</body>
</html>