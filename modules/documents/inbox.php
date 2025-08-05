<?php
// modules/documents/inbox.php
// Bandeja de entrada de documentos - DMS2 CON PERMISOS INTEGRADOS

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/permission_functions.php';

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Verificar permisos básicos
$canView = hasUserPermission('view');
$canDownload = hasUserPermission('download') && SessionManager::canDownload();
$canEdit = hasUserPermission('edit');
$canDelete = hasUserPermission('delete');

// Parámetros de búsqueda y filtrado
$searchTerm = trim($_GET['search'] ?? '');
$selectedFolder = $_GET['folder'] ?? '';
$selectedType = $_GET['type'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'desc';

// Variables para datos
$documents = [];
$folders = [];
$documentsData = [];

if (!$canView) {
    $error_message = 'No tienes permisos para ver documentos. Contacta con tu administrador.';
} else {
    try {
        $database = new Database();
        $pdo = $database->getConnection();

        // Obtener condiciones de filtrado según permisos
        $filterData = getDocumentFilterConditions();
        $permissionConditions = $filterData['conditions'];
        $permissionParams = $filterData['params'];

        // Consulta base
        $baseQuery = "
            SELECT 
                d.id,
                d.name,
                d.original_name,
                d.file_size,
                d.mime_type,
                d.description,
                d.status,
                d.created_at,
                d.updated_at,
                d.user_id,
                d.file_path,
                c.name as company_name,
                dep.name as department_name,
                dt.name as document_type,
                dt.icon as document_type_icon,
                dt.color as document_type_color,
                CONCAT(u.first_name, ' ', u.last_name) as uploaded_by
            FROM documents d
            LEFT JOIN companies c ON d.company_id = c.id
            LEFT JOIN departments dep ON d.department_id = dep.id
            LEFT JOIN document_types dt ON d.document_type_id = dt.id
            LEFT JOIN users u ON d.user_id = u.id
        ";

        // Condiciones adicionales del usuario
        $userConditions = [];
        $userParams = [];

        // Filtro de búsqueda
        if (!empty($searchTerm)) {
            $userConditions[] = "(d.name LIKE ? OR d.description LIKE ? OR d.original_name LIKE ?)";
            $searchPattern = "%$searchTerm%";
            $userParams = array_merge($userParams, [$searchPattern, $searchPattern, $searchPattern]);
        }

        // Filtro por carpeta
        if (!empty($selectedFolder)) {
            $userConditions[] = "d.company_id = ?";
            $userParams[] = $selectedFolder;
        }

        // Filtro por tipo
        if (!empty($selectedType)) {
            $userConditions[] = "d.document_type_id = ?";
            $userParams[] = $selectedType;
        }

        // Combinar condiciones
        $allConditions = array_merge($permissionConditions, $userConditions);
        $allParams = array_merge($permissionParams, $userParams);

        if (!empty($allConditions)) {
            $baseQuery .= " WHERE " . implode(' AND ', $allConditions);
        }

        // Ordenamiento
        $validSortFields = ['name', 'created_at', 'updated_at', 'file_size'];
        $sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'created_at';
        $sortOrder = ($sortOrder === 'asc') ? 'ASC' : 'DESC';
        $baseQuery .= " ORDER BY d.{$sortBy} {$sortOrder}";

        // Ejecutar consulta
        $stmt = $pdo->prepare($baseQuery);
        $stmt->execute($allParams);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filtrar por acceso específico
        $documents = array_filter($documents, function($doc) {
            return canAccessDocument($doc['id']);
        });

        // Convertir a formato JavaScript
        $documentsData = array_map(function($doc) {
            return [
                'id' => (int)$doc['id'],
                'name' => $doc['name'],
                'original_name' => $doc['original_name'],
                'file_size' => (int)$doc['file_size'],
                'mime_type' => $doc['mime_type'],
                'description' => $doc['description'],
                'status' => $doc['status'],
                'created_at' => $doc['created_at'],
                'updated_at' => $doc['updated_at'],
                'user_id' => (int)$doc['user_id'],
                'company_name' => $doc['company_name'],
                'department_name' => $doc['department_name'],
                'document_type' => $doc['document_type'],
                'document_type_icon' => $doc['document_type_icon'],
                'document_type_color' => $doc['document_type_color'],
                'uploaded_by' => $doc['uploaded_by']
            ];
        }, $documents);

        // Obtener carpetas accesibles
        $accessibleCompanies = getAccessibleCompanies();
        $folders = [];
        foreach ($accessibleCompanies as $company) {
            $countQuery = "SELECT COUNT(*) as count FROM documents d WHERE d.company_id = ? AND d.status = 'active'";
            if (!empty($permissionConditions)) {
                $countQuery .= " AND " . implode(' AND ', $permissionConditions);
            }
            
            $countStmt = $pdo->prepare($countQuery);
            $countParams = array_merge([$company['id']], $permissionParams);
            $countStmt->execute($countParams);
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            
            $folders[] = [
                'id' => $company['id'],
                'name' => $company['name'],
                'count' => $countResult['count'] ?? 0
            ];
        }

    } catch (Exception $e) {
        error_log('Error cargando documentos: ' . $e->getMessage());
        $documents = [];
        $folders = [];
        $documentsData = [];
        $error_message = 'Error cargando documentos. Por favor, inténtalo de nuevo.';
    }
}

// Obtener tipos de documentos accesibles
$documentTypes = $canView ? getAccessibleDocumentTypes() : [];

// Mensaje de restricciones
$restrictionsMessage = $canView ? getRestrictionsMessage() : null;

// Funciones auxiliares
$formatSize = fn($bytes) => $bytes ? round($bytes / pow(1024, floor(log($bytes) / log(1024))), 2) . ' ' . ['B', 'KB', 'MB', 'GB'][floor(log($bytes) / log(1024))] : '0 B';
$formatDate = fn($date) => $date ? (new DateTime($date))->format('d/m/Y H:i') : 'Sin fecha';

function getDocumentIconClass($mimeType) {
    if (strpos($mimeType, 'pdf') !== false) return 'pdf';
    if (strpos($mimeType, 'word') !== false || strpos($mimeType, 'document') !== false) return 'word';
    if (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'sheet') !== false) return 'excel';
    if (strpos($mimeType, 'image') !== false) return 'image';
    return 'file';
}

function getDocumentIcon($mimeType) {
    if (strpos($mimeType, 'pdf') !== false) return 'file-text';
    if (strpos($mimeType, 'word') !== false || strpos($mimeType, 'document') !== false) return 'file-text';
    if (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'sheet') !== false) return 'grid';
    if (strpos($mimeType, 'image') !== false) return 'image';
    return 'file';
}
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
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>

<body class="dashboard-layout">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Bandeja de Entrada</h1>
                <div class="header-stats">
                    <div class="stat-item">
                        <i data-feather="file"></i>
                        <span><?= count($documents) ?> documentos</span>
                    </div>
                    <div class="stat-item">
                        <i data-feather="folder"></i>
                        <span><?= count($folders) ?> carpetas</span>
                    </div>
                    <?php if ($restrictionsMessage): ?>
                        <div class="stat-item" style="color: #f59e0b;" title="Restricciones activas">
                            <i data-feather="filter"></i>
                            <span><?= htmlspecialchars($restrictionsMessage) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header">
                        <?= htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))) ?>
                    </div>
                    <div class="current-datetime" id="currentDateTime"></div>
                </div>
                <div class="header-actions">
                    <?php if (hasUserPermission('create')): ?>
                        <a href="upload.php" class="btn-icon" title="Subir documento">
                            <i data-feather="upload"></i>
                        </a>
                    <?php endif; ?>
                    <button class="btn-icon" onclick="alert('Configuración próximamente')">
                        <i data-feather="settings"></i>
                    </button>
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <?php if (isset($error_message)): ?>
            <div style="padding: 2rem;">
                <div style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem;">
                    <i data-feather="alert-circle" style="width: 20px; height: 20px;"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="inbox-container">
            <!-- Panel de filtros -->
            <aside class="filters-panel">
                <div class="filters-header">
                    <h3>Explorar</h3>
                    <button class="btn-icon-sm" onclick="window.location.href=window.location.pathname">
                        <i data-feather="refresh-cw"></i>
                    </button>
                </div>

                <!-- Búsqueda -->
                <div class="search-section">
                    <form method="GET">
                        <div class="search-input-group">
                            <i data-feather="search"></i>
                            <input type="text" name="search" placeholder="Buscar documentos..." value="<?= htmlspecialchars($searchTerm) ?>">
                            <button type="submit" class="btn-search">
                                <i data-feather="arrow-right"></i>
                            </button>
                        </div>
                        <?php 
                        $hiddenFields = [
                            'folder' => $selectedFolder, 
                            'type' => $selectedType, 
                            'sort' => ($sortBy !== 'created_at' ? $sortBy : ''), 
                            'order' => ($sortOrder !== 'desc' ? $sortOrder : '')
                        ];
                        foreach($hiddenFields as $key => $value): 
                            if($value): 
                        ?>
                            <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php 
                            endif; 
                        endforeach; 
                        ?>
                    </form>
                </div>

                <!-- Carpetas -->
                <div class="folders-section">
                    <h4>Carpetas</h4>
                    <div class="folders-list">
                        <a href="?" class="folder-item <?= !$selectedFolder ? 'active' : '' ?>">
                            <i data-feather="inbox"></i>
                            <div class="folder-info">
                                <span class="folder-name">Todos los documentos</span>
                            </div>
                            <span class="count"><?= count($documents) ?></span>
                        </a>

                        <?php foreach ($folders as $folder): ?>
                            <a href="?folder=<?= $folder['id'] ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?= $selectedType ? '&type=' . $selectedType : '' ?>" 
                               class="folder-item <?= $selectedFolder == $folder['id'] ? 'active' : '' ?>">
                                <i data-feather="folder"></i>
                                <div class="folder-info">
                                    <span class="folder-name"><?= htmlspecialchars($folder['name']) ?></span>
                                </div>
                                <span class="count"><?= $folder['count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tipos de documentos -->
                <div class="types-section">
                    <h4>Tipos</h4>
                    <div class="types-list">
                        <?php foreach ($documentTypes as $type): ?>
                            <a href="?type=<?= $type['id'] ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?= $selectedFolder ? '&folder=' . $selectedFolder : '' ?>" 
                               class="type-item <?= $selectedType == $type['id'] ? 'active' : '' ?>">
                                <i data-feather="<?= $type['icon'] ?: 'file' ?>" style="color: <?= $type['color'] ?: '#6c757d' ?>"></i>
                                <span><?= htmlspecialchars($type['name']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Información del usuario -->
                <div class="user-info-section">
                    <div class="user-card">
                        <div class="user-avatar">
                            <?= strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name">
                                <?= htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))) ?>
                            </div>
                            <div class="user-role"><?= htmlspecialchars(ucfirst($currentUser['role'] ?? 'Usuario')) ?></div>
                        </div>
                    </div>
                    
                    <div class="permission-status">
                        <i data-feather="<?= $canView ? 'check-circle' : 'x-circle' ?>"></i>
                        <span><?= $canView ? 'Acceso completo' : 'Acceso limitado' ?></span>
                    </div>
                </div>
            </aside>

            <!-- Panel principal de documentos -->
            <div class="documents-panel-full">
                <div class="documents-header">
                    <div class="view-controls">
                        <button class="view-btn active" data-view="grid" onclick="changeView('grid')">
                            <i data-feather="grid"></i>
                            Cuadrícula
                        </button>
                        <button class="view-btn" data-view="list" onclick="changeView('list')">
                            <i data-feather="list"></i>
                            Lista
                        </button>
                    </div>

                    <div class="sort-controls">
                        <form method="GET" onchange="this.submit()">
                            <?php foreach(['search' => $searchTerm, 'folder' => $selectedFolder, 'type' => $selectedType] as $key => $value): ?>
                                <?php if($value): ?>
                                    <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <select name="sort">
                                <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Fecha de creación</option>
                                <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Nombre</option>
                                <option value="updated_at" <?= $sortBy === 'updated_at' ? 'selected' : '' ?>>Última modificación</option>
                                <option value="file_size" <?= $sortBy === 'file_size' ? 'selected' : '' ?>>Tamaño</option>
                            </select>
                            
                            <select name="order">
                                <option value="desc" <?= $sortOrder === 'desc' ? 'selected' : '' ?>>Descendente</option>
                                <option value="asc" <?= $sortOrder === 'asc' ? 'selected' : '' ?>>Ascendente</option>
                            </select>
                        </form>
                    </div>
                </div>

                <div class="documents-content">
                    <?php if (empty($documents)): ?>
                        <div class="empty-state">
                            <i data-feather="file-text"></i>
                            <h3>No hay documentos disponibles</h3>
                            <?php if (!empty($searchTerm) || !empty($selectedFolder) || !empty($selectedType)): ?>
                                <p>No se encontraron documentos que coincidan con los filtros aplicados.</p>
                                <a href="?" class="clear-filters">Limpiar filtros</a>
                            <?php elseif ($restrictionsMessage): ?>
                                <p>No hay documentos disponibles según tus permisos actuales.</p>
                            <?php else: ?>
                                <p>Aún no se han subido documentos al sistema.</p>
                                <?php if (hasUserPermission('create')): ?>
                                    <a href="upload.php" class="upload-link">Subir el primer documento</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="documents-grid" id="documentsContainer">
                            <?php foreach ($documents as $doc): ?>
                                <?php
                                // Verificar permisos específicos para cada documento
                                $docCanDownload = $canDownload && canAccessDocument($doc['id']);
                                $docCanEdit = $canEdit && canAccessDocument($doc['id']);
                                $docCanDelete = $canDelete && canAccessDocument($doc['id']) && 
                                                ($currentUser['role'] === 'admin' || $doc['user_id'] == $currentUser['id']);
                                ?>
                                
                                <div class="document-card" data-id="<?= $doc['id'] ?>">
                                    <div class="document-preview">
                                        <?php if (strpos($doc['mime_type'], 'image/') === 0 && !empty($doc['file_path'])): ?>
                                            <img src="../../<?= htmlspecialchars($doc['file_path']) ?>" 
                                                 alt="<?= htmlspecialchars($doc['name']) ?>" 
                                                 class="image-preview"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <?php endif; ?>
                                        
                                        <div class="document-icon <?= getDocumentIconClass($doc['mime_type']) ?>">
                                            <i data-feather="<?= getDocumentIcon($doc['mime_type']) ?>"></i>
                                        </div>
                                    </div>

                                    <!-- Botones de acción -->
                                    <div class="document-actions">
                                        <button class="action-btn view-btn" data-doc-id="<?= $doc['id'] ?>" title="Ver documento">
                                            <i data-feather="eye"></i>
                                        </button>
                                        
                                        <?php if ($docCanDownload): ?>
                                            <button class="action-btn download-btn" data-doc-id="<?= $doc['id'] ?>" title="Descargar">
                                                <i data-feather="download"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($docCanDelete): ?>
                                            <button class="action-btn delete-btn" data-doc-id="<?= $doc['id'] ?>" title="Eliminar">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="document-info">
                                        <h3 class="document-name" title="<?= htmlspecialchars($doc['name']) ?>">
                                            <?= htmlspecialchars($doc['name']) ?>
                                        </h3>

                                        <div class="document-meta">
                                            <span class="document-type">
                                                <?= htmlspecialchars($doc['document_type'] ?: 'Documento') ?>
                                            </span>
                                            <span class="document-size"><?= $formatSize($doc['file_size']) ?></span>
                                        </div>

                                        <div class="document-location">
                                            <i data-feather="building"></i>
                                            <span><?= htmlspecialchars($doc['company_name'] ?: 'Sin empresa') ?></span>
                                        </div>

                                        <?php if ($doc['department_name']): ?>
                                            <div class="document-location">
                                                <i data-feather="layers"></i>
                                                <span><?= htmlspecialchars($doc['department_name']) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="document-date">
                                            <i data-feather="calendar"></i>
                                            <span><?= $formatDate($doc['created_at']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Variables globales
        const currentUserId = <?= json_encode($currentUser['id']) ?>;
        const currentUserRole = <?= json_encode($currentUser['role']) ?>;
        const canDownload = <?= json_encode($canDownload) ?>;
        const documentsData = <?= json_encode($documentsData) ?>;

        // Funciones principales
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleDateString('es-ES', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }) + ' ' + now.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('currentDateTime').textContent = timeString;
        }

        function changeView(viewType) {
            document.querySelectorAll('.view-btn').forEach(btn => 
                btn.classList.toggle('active', btn.dataset.view === viewType));
            document.getElementById('documentsContainer').className = 
                viewType === 'grid' ? 'documents-grid' : 'documents-list';
            try { 
                localStorage.setItem('inbox_view_preference', viewType); 
            } catch(e) {}
        }

        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('active');
        }

        // Funciones de documentos con verificación de permisos
        function viewDocument(documentId) {
            fetch('../../api/check_document_access.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ document_id: documentId, action: 'view' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = `view.php?id=${documentId}`;
                } else {
                    alert('No tienes permisos para ver este documento: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                window.location.href = `view.php?id=${documentId}`;
            });
        }

        function downloadDocument(documentId) {
            if (!canDownload) {
                alert('No tienes permisos para descargar documentos');
                return;
            }

            fetch('../../api/check_document_access.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ document_id: documentId, action: 'download' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar información de límites si está disponible
                    if (data.download_info && data.download_info.daily_limit !== null) {
                        const remaining = data.download_info.remaining;
                        if (remaining !== null && remaining <= 5) {
                            alert(`Descargas restantes hoy: ${remaining}`);
                        }
                    }

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'download.php';
                    form.innerHTML = `<input type="hidden" name="document_id" value="${documentId}">`;
                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                } else {
                    alert('No puedes descargar este documento: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error verificando permisos de descarga');
            });
        }

        function deleteDocument(documentId) {
            const docData = documentsData.find(doc => doc.id == documentId);
            const docName = docData ? docData.name : 'este documento';
            
            if (confirm(`¿Está seguro que desea eliminar "${docName}"?`)) {
                fetch('../../api/check_document_access.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ document_id: documentId, action: 'delete' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'delete.php';
                        form.innerHTML = `<input type="hidden" name="document_id" value="${documentId}">`;
                        document.body.appendChild(form);
                        form.submit();
                    } else {
                        alert('No tienes permisos para eliminar este documento: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error verificando permisos de eliminación');
                });
            }
        }

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            if(typeof feather !== 'undefined') feather.replace();
            updateTime();
            setInterval(updateTime, 60000);
            
            // Event listeners para botones
            document.addEventListener('click', function(e) {
                // Botón VER
                if (e.target.closest('.view-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const docId = e.target.closest('.view-btn').dataset.docId;
                    if (docId) viewDocument(docId);
                }
                
                // Botón DESCARGAR
                if (e.target.closest('.download-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const docId = e.target.closest('.download-btn').dataset.docId;
                    if (docId) downloadDocument(docId);
                }
                
                // Botón ELIMINAR
                if (e.target.closest('.delete-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const docId = e.target.closest('.delete-btn').dataset.docId;
                    if (docId) deleteDocument(docId);
                }
                
                // Click en vista previa del documento
                if (e.target.closest('.document-preview')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const card = e.target.closest('.document-card');
                    if (card && card.dataset.id) {
                        viewDocument(card.dataset.id);
                    }
                }
            });

            // Restaurar vista guardada
            try {
                const savedView = localStorage.getItem('inbox_view_preference');
                if (savedView && ['grid', 'list'].includes(savedView)) {
                    changeView(savedView);
                }
            } catch(e) {
                console.log('No se pudo cargar preferencia de vista');
            }
        });

        // Responsive
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                const sidebar = document.querySelector('.sidebar');
                if (sidebar && !sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                }
            }
        });
    </script>

    <style>
        /* Estilos para mantener el diseño original */
        .document-actions {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: 10;
        }

        .document-card:hover .document-actions {
            opacity: 1;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid #e2e8f0;
            color: #64748b;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
            width: 36px;
            height: 36px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .action-btn:hover {
            background: #8B4513;
            color: white;
            border-color: #8B4513;
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .action-btn.delete-btn {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .action-btn.delete-btn:hover {
            background: #ef4444;
            color: white;
            border-color: #dc2626;
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }

        .action-btn i {
            width: 16px;
            height: 16px;
        }

        /* Vista previa de documento */
        .document-preview {
            position: relative;
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafbfc;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
        }

        .document-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .document-icon.pdf { background: #ef4444; }
        .document-icon.word { background: #2563eb; }
        .document-icon.excel { background: #059669; }
        .document-icon.image { background: #7c3aed; }
        .document-icon.file { background: #64748b; }

        .document-icon i {
            width: 32px;
            height: 32px;
        }

        .image-preview {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }

        /* Información del documento */
        .document-info {
            padding: 1rem;
            min-height: 120px;
        }

        .document-name {
            margin: 0 0 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .document-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .document-type {
            background: #8B4513;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.625rem;
            font-weight: 500;
        }

        .document-size {
            color: #94a3b8;
            font-size: 0.75rem;
        }

        .document-location,
        .document-date {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-bottom: 0.25rem;
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .document-location i,
        .document-date i {
            width: 12px;
            height: 12px;
        }

        /* Estado vacío */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
            min-height: 300px;
        }

        .empty-state i {
            width: 64px;
            height: 64px;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 0.75rem;
            color: #1e293b;
            font-size: 1.25rem;
        }

        .empty-state p {
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .clear-filters,
        .upload-link {
            color: #8B4513;
            text-decoration: none;
            font-weight: 500;
        }

        .clear-filters:hover,
        .upload-link:hover {
            text-decoration: underline;
        }

        /* Vista de lista específica */
        .documents-list .document-card {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            height: auto;
            border-radius: 8px;
            min-height: 80px;
        }

        .documents-list .document-preview {
            width: 48px;
            height: 48px;
            margin-right: 0.75rem;
            border-bottom: none;
            flex-shrink: 0;
        }

        .documents-list .document-icon {
            width: 48px;
            height: 48px;
        }

        .documents-list .document-icon i {
            width: 24px;
            height: 24px;
        }

        .documents-list .document-info {
            flex: 1;
            min-height: auto;
            padding: 0;
        }

        .documents-list .document-actions {
            position: static;
            flex-direction: row;
            opacity: 1;
            margin-left: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .inbox-container {
                grid-template-columns: 1fr;
            }
            
            .filters-panel {
                display: none;
            }
            
            .documents-grid {
                grid-template-columns: 1fr;
            }
            
            .header-stats {
                display: none;
            }
            
            .document-actions {
                opacity: 1;
            }
        }
    </style>
</body>
</html>