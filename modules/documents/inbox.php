<?php
// modules/documents/inbox.php - VERSIÓN COMPACTA
require_once '../../config/session.php';
require_once '../../config/database.php';
SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Funciones compactas
function getDocuments($userId, $companyId, $role) {
    $query = "SELECT d.*, dt.name as document_type, dt.icon as file_icon, c.name as company_name, 
              dep.name as department_name, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by
              FROM documents d
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              LEFT JOIN companies c ON d.company_id = c.id  
              LEFT JOIN departments dep ON d.department_id = dep.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE d.status = 'active'" . ($role !== 'admin' ? " AND d.company_id = :company_id" : "") . "
              ORDER BY d.created_at DESC";
    return fetchAll($query, $role !== 'admin' ? ['company_id' => $companyId] : []);
}

function getFolders($documents) {
    $folders = [];
    foreach ($documents as $doc) {
        $key = ($doc['company_name'] ?? 'Sin Empresa') . '/' . ($doc['department_name'] ?? 'General');
        $folders[$key] = ($folders[$key] ?? 0) + 1;
    }
    return $folders;
}

function sortDocs($docs, $sortBy, $order) {
    usort($docs, function($a, $b) use ($sortBy, $order) {
        $result = match($sortBy) {
            'date' => strtotime($a['created_at'] ?? '1970-01-01') - strtotime($b['created_at'] ?? '1970-01-01'),
            'size' => ($a['file_size'] ?? 0) - ($b['file_size'] ?? 0),
            'type' => strcasecmp($a['document_type'] ?? '', $b['document_type'] ?? ''),
            default => strcasecmp($a['name'] ?? '', $b['name'] ?? '')
        };
        return $order === 'desc' ? -$result : $result;
    });
    return $docs;
}

function filterDocs($docs, $folder, $search, $type) {
    return array_filter($docs, function($doc) use ($folder, $search, $type) {
        // Filtro carpeta
        if ($folder) {
            $docFolder = ($doc['company_name'] ?? 'Sin Empresa') . '/' . ($doc['department_name'] ?? 'General');
            if ($docFolder !== $folder) return false;
        }
        // Filtro búsqueda
        if ($search) {
            $fields = [$doc['name'] ?? '', $doc['description'] ?? '', $doc['document_type'] ?? ''];
            if (!array_filter($fields, fn($f) => stripos($f, $search) !== false)) return false;
        }
        // Filtro tipo
        if ($type) {
            $ext = strtolower(pathinfo($doc['original_name'] ?? '', PATHINFO_EXTENSION));
            $category = match(true) {
                in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) => 'imagen',
                $ext === 'pdf' => 'pdf',
                in_array($ext, ['doc', 'docx']) => 'document',
                in_array($ext, ['xls', 'xlsx']) => 'spreadsheet',
                default => 'other'
            };
            if ($category !== $type) return false;
        }
        return true;
    });
}

// Procesamiento
try {
    $documents = getDocuments($currentUser['id'], $currentUser['company_id'], $currentUser['role']);
    $folders = getFolders($documents);
    $canDownload = fetchOne("SELECT download_enabled FROM users WHERE id = :id", ['id' => $currentUser['id']])['download_enabled'] ?? true;
    
    // Filtros con verificación de existencia
    $selectedFolder = trim($_GET['folder'] ?? '');
    $searchTerm = trim($_GET['search'] ?? '');
    $selectedType = trim($_GET['type'] ?? '');
    $sortBy = in_array($_GET['sort'] ?? 'name', ['name', 'date', 'size', 'type']) ? ($_GET['sort'] ?? 'name') : 'name';
    $sortOrder = in_array($_GET['order'] ?? 'asc', ['asc', 'desc']) ? ($_GET['order'] ?? 'asc') : 'asc';
    
    // Aplicar filtros y ordenación
    $documents = filterDocs($documents, $selectedFolder, $searchTerm, $selectedType);
    $documents = sortDocs($documents, $sortBy, $sortOrder);
    
    logActivity($currentUser['id'], 'view', 'documents', null, 'Usuario accedió a la bandeja de entrada');
} catch (Exception $e) {
    error_log("Error in inbox.php: " . $e->getMessage());
    $documents = $folders = [];
    $canDownload = false;
}

// Funciones auxiliares
$formatSize = fn($bytes) => $bytes ? round($bytes / pow(1024, floor(log($bytes) / log(1024))), 2) . ' ' . ['B', 'KB', 'MB', 'GB'][floor(log($bytes) / log(1024))] : '0 B';
$formatDate = fn($date) => $date ? (new DateTime($date))->format('d/m/Y H:i') : 'Sin fecha';
$canDelete = fn($userId, $role, $ownerId) => $role === 'admin' || $userId == $ownerId;
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
                    <div class="stat-item"><i data-feather="file"></i><span><?= count($documents) ?> documentos</span></div>
                    <div class="stat-item"><i data-feather="folder"></i><span><?= count($folders) ?> carpetas</span></div>
                </div>
            </div>
            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?= htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))) ?></div>
                    <div class="current-datetime" id="currentDateTime"></div>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" onclick="alert('Configuración estará disponible próximamente.')">
                        <i data-feather="settings"></i>
                    </button>
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

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
                            <button type="submit" class="btn-search"><i data-feather="arrow-right"></i></button>
                        </div>
                        <?php 
                        $hiddenFields = [
                            'folder' => $selectedFolder, 
                            'type' => $selectedType, 
                            'sort' => ($sortBy !== 'name' ? $sortBy : ''), 
                            'order' => ($sortOrder !== 'asc' ? $sortOrder : '')
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
                            <i data-feather="inbox"></i><span>Todos los documentos</span><small>(<?= count($documents) ?>)</small>
                        </a>
                        <?php foreach($folders as $folderKey => $count): ?>
                            <a href="?folder=<?= urlencode($folderKey) ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?>" 
                               class="folder-item <?= $selectedFolder === $folderKey ? 'active' : '' ?>">
                                <i data-feather="folder"></i>
                                <div>
                                    <span><?= htmlspecialchars(explode('/', $folderKey)[0]) ?></span>
                                    <?php if(explode('/', $folderKey)[1] !== 'General'): ?>
                                        <small><?= htmlspecialchars(explode('/', $folderKey)[1]) ?></small>
                                    <?php endif; ?>
                                </div>
                                <small>(<?= $count ?>)</small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tipos de archivo -->
                <div class="file-types-section">
                    <h4>Tipos de archivo</h4>
                    <div class="file-types-list">
                        <a href="?" class="type-item <?= !$selectedType ? 'active' : '' ?>">
                            <i data-feather="file"></i><span>Todos</span>
                        </a>
                        <?php 
                        $types = ['imagen' => ['image', 'Imágenes'], 'pdf' => ['file-text', 'PDF'], 'document' => ['file-text', 'Documentos'], 'spreadsheet' => ['grid', 'Hojas de cálculo'], 'other' => ['file', 'Otros']];
                        foreach($types as $typeKey => [$icon, $name]): 
                            if(array_filter($documents, function($doc) use ($typeKey) {
                                $ext = strtolower(pathinfo($doc['original_name'] ?? '', PATHINFO_EXTENSION));
                                return match($typeKey) {
                                    'imagen' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']),
                                    'pdf' => $ext === 'pdf',
                                    'document' => in_array($ext, ['doc', 'docx']),
                                    'spreadsheet' => in_array($ext, ['xls', 'xlsx']),
                                    'other' => !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'])
                                };
                            })):
                        ?>
                            <a href="?type=<?= $typeKey ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?= $selectedFolder ? '&folder=' . urlencode($selectedFolder) : '' ?>" 
                               class="type-item <?= $selectedType === $typeKey ? 'active' : '' ?>">
                                <i data-feather="<?= $icon ?>"></i><span><?= $name ?></span>
                            </a>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            </aside>

            <!-- Área principal -->
            <main class="documents-area">
                <div class="toolbar">
                    <div class="toolbar-left">
                        <div class="view-controls">
                            <button class="btn-view active" data-view="grid" onclick="changeView('grid')">
                                <i data-feather="grid"></i>Cuadros
                            </button>
                            <button class="btn-view" data-view="list" onclick="changeView('list')">
                                <i data-feather="list"></i>Lista  
                            </button>
                        </div>
                    </div>
                    <div class="toolbar-right">
                        <div class="sort-controls">
                            <select id="sortBy" onchange="changeSorting()" class="form-control-sm">
                                <?php foreach(['name' => 'nombre', 'date' => 'fecha', 'size' => 'tamaño', 'type' => 'tipo'] as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $sortBy === $value ? 'selected' : '' ?>>Ordenar por <?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button onclick="toggleSortOrder()" class="btn-icon-sm">
                                <i data-feather="<?= $sortOrder === 'asc' ? 'arrow-up' : 'arrow-down' ?>"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Documentos -->
                <div class="documents-grid" id="documentsContainer">
                    <?php if(empty($documents)): ?>
                        <div class="empty-state">
                            <i data-feather="inbox" class="empty-icon"></i>
                            <h3>No hay documentos</h3>
                            <p><?= ($searchTerm || $selectedFolder || $selectedType) ? 'No se encontraron documentos que coincidan con los filtros aplicados.' : 'Aún no tienes documentos. <a href="upload.php">Sube tu primer documento</a>' ?></p>
                            <?php if($searchTerm || $selectedFolder || $selectedType): ?>
                                <a href="?" class="clear-filters">Limpiar filtros</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach($documents as $doc): 
                            $ext = strtolower(pathinfo($doc['original_name'] ?? '', PATHINFO_EXTENSION));
                            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            $imageUrl = $isImage && $doc['file_path'] ? (strpos($doc['file_path'], 'uploads/') === 0 ? '../../' . $doc['file_path'] : $doc['file_path']) : '';
                        ?>
                            <div class="document-card" data-id="<?= $doc['id'] ?>">
                                <div class="document-preview" onclick="viewDocument(<?= $doc['id'] ?>)">
                                    <?php if($imageUrl): ?>
                                        <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($doc['name']) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                        <div class="document-icon fallback" style="display:none;"><i data-feather="image"></i><span class="preview-label">IMG</span></div>
                                    <?php else: ?>
                                        <div class="document-icon <?= match($ext) { 'pdf' => 'pdf-preview', 'doc', 'docx' => 'doc-preview', 'xls', 'xlsx' => 'xls-preview', default => 'generic-preview' } ?>">
                                            <i data-feather="<?= match($ext) { 'pdf' => 'file-text', 'doc', 'docx' => 'file-text', 'xls', 'xlsx' => 'grid', default => $doc['file_icon'] ?? 'file' } ?>"></i>
                                            <span class="preview-label"><?= strtoupper($ext ?: 'FILE') ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="document-info">
                                    <h4 class="document-name" title="<?= htmlspecialchars($doc['name']) ?>">
                                        <?= htmlspecialchars($doc['name']) ?>
                                    </h4>
                                    <div class="document-meta">
                                        <span class="document-type"><?= htmlspecialchars($doc['document_type'] ?? 'Sin tipo') ?></span>
                                        <span class="document-size"><?= $formatSize($doc['file_size'] ?? 0) ?></span>
                                        <span class="document-date"><?= $formatDate($doc['created_at'] ?? '') ?></span>
                                    </div>
                                    <div class="document-location">
                                        <?= htmlspecialchars($doc['company_name'] ?? 'Sin empresa') ?>
                                        <?php if($doc['department_name']): ?> • <?= htmlspecialchars($doc['department_name']) ?><?php endif; ?>
                                    </div>
                                </div>

                                <div class="document-actions">
                                    <button class="action-btn view-btn" onclick="viewDocument(<?= $doc['id'] ?>)" title="Ver documento">
                                        <i data-feather="eye"></i>
                                    </button>
                                    <?php if($canDownload): ?>
                                        <form method="POST" action="download.php" style="display:inline;">
                                            <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                            <button type="submit" class="action-btn download-btn" title="Descargar">
                                                <i data-feather="download"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="action-btn disabled" disabled><i data-feather="download"></i></button>
                                    <?php endif; ?>
                                    <?php if($canDelete($currentUser['id'], $currentUser['role'], $doc['user_id'])): ?>
                                        <button class="action-btn delete-btn" onclick="deleteDocument(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['name'], ENT_QUOTES) ?>')" title="Eliminar">
                                            <i data-feather="trash-2"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="action-btn disabled" disabled><i data-feather="trash-2"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </main>

    <script>
        // JavaScript compacto
        const updateTime = () => {
            const now = new Date();
            document.getElementById('currentDateTime').textContent = 
                now.toLocaleDateString('es-ES', {day:'2-digit',month:'2-digit',year:'numeric'}) + ' ' + 
                now.toLocaleTimeString('es-ES', {hour:'2-digit',minute:'2-digit'});
        };

        const changeSorting = () => {
            const url = new URL(window.location);
            url.searchParams.set('sort', document.getElementById('sortBy').value);
            window.location.href = url.toString();
        };

        const toggleSortOrder = () => {
            const url = new URL(window.location);
            const currentOrder = url.searchParams.get('order');
            const newOrder = (currentOrder === null || currentOrder === 'asc') ? 'desc' : 'asc';
            url.searchParams.set('order', newOrder);
            window.location.href = url.toString();
        };

        const changeView = (viewType) => {
            document.querySelectorAll('.btn-view').forEach(btn => 
                btn.classList.toggle('active', btn.dataset.view === viewType));
            document.getElementById('documentsContainer').className = 
                viewType === 'grid' ? 'documents-grid' : 'documents-list';
            try { localStorage.setItem('inbox_view_preference', viewType); } catch(e) {}
        };

        const viewDocument = (id) => window.location.href = `view.php?id=${id}`;

        const deleteDocument = (id, name) => {
            if(confirm(`¿Está seguro que desea eliminar "${name}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete.php';
                form.innerHTML = `<input type="hidden" name="document_id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        };

        const toggleSidebar = () => document.querySelector('.sidebar')?.classList.toggle('active');

        // Inicialización
        document.addEventListener('DOMContentLoaded', () => {
            if(typeof feather !== 'undefined') feather.replace();
            updateTime();
            setInterval(updateTime, 1000);
            
            // Eventos
            document.addEventListener('click', (e) => {
                if(e.target.closest('.document-preview')) {
                    const card = e.target.closest('.document-card');
                    if(card) viewDocument(card.dataset.id);
                }
            });

            // Restaurar vista
            try {
                const savedView = localStorage.getItem('inbox_view_preference');
                if(savedView && ['grid', 'list'].includes(savedView)) changeView(savedView);
            } catch(e) {}
        });
    </script>

    <!-- CSS compacto inline -->
    <style>
        .inbox-container{display:grid;grid-template-columns:280px 1fr;height:calc(100vh - 80px);background:#f8fafc;gap:0;overflow:hidden}
        .filters-panel{background:#fff;border-right:1px solid #e2e8f0;overflow-y:auto;padding:1.5rem;height:calc(100vh - 80px);width:280px;flex-shrink:0}
        .filters-panel::-webkit-scrollbar{width:6px}.filters-panel::-webkit-scrollbar-track{background:#f1f5f9}.filters-panel::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}
        .header-info{display:flex;flex-direction:column;align-items:flex-end;gap:0.1rem;text-align:right}
        .user-name-header{font-size:0.875rem;font-weight:600;color:#1e293b;line-height:1.1;margin:0}
        .current-datetime{font-size:0.75rem;color:#64748b;font-weight:400;line-height:1.1;margin:0;letter-spacing:0.01em}
        .documents-area{flex:1;display:flex;flex-direction:column;background:#fff;overflow:hidden}
        .documents-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;padding:1.5rem;overflow-y:auto;flex:1;align-content:start}
        .documents-grid::-webkit-scrollbar{width:8px}.documents-grid::-webkit-scrollbar-track{background:#f1f5f9}.documents-grid::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
        .document-card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);overflow:hidden;transition:all 0.3s ease;cursor:pointer;position:relative;display:flex;flex-direction:column;height:400px}
        .document-card:hover{transform:translateY(-4px);box-shadow:0 8px 25px rgba(0,0,0,0.15)}
        .document-preview{width:100%;height:200px;display:flex;align-items:center;justify-content:center;background:#f8fafc;border-bottom:1px solid #e2e8f0;cursor:pointer;overflow:hidden;position:relative;flex-shrink:0}
        .document-preview img{width:100%;height:100%;object-fit:cover;transition:transform 0.3s ease}
        .document-preview:hover img{transform:scale(1.05)}
        .document-icon{display:flex;flex-direction:column;align-items:center;justify-content:center;width:100px;height:100px;background:rgba(139,69,19,0.1);border-radius:16px;color:#8B4513;transition:all 0.3s ease}
        .document-icon:hover{transform:scale(1.05);background:rgba(139,69,19,0.15)}
        .document-icon i{width:40px;height:40px;margin-bottom:0.5rem}
        .preview-label{font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:inherit}
        .pdf-preview{background:rgba(220,38,38,0.1)!important;color:#dc2626!important}
        .doc-preview{background:rgba(59,130,246,0.1)!important;color:#3b82f6!important}
        .xls-preview{background:rgba(16,185,129,0.1)!important;color:#10b981!important}
        .generic-preview{background:rgba(107,114,128,0.1)!important;color:#6b7280!important}
        .fallback{background:rgba(239,68,68,0.1)!important;color:#ef4444!important}
        .document-info{padding:1rem;flex:1;display:flex;flex-direction:column;justify-content:space-between;min-height:0}
        .document-name{font-size:1rem;font-weight:600;color:#1e293b;margin:0 0 0.5rem 0;display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis;line-height:1.3}
        .document-meta{display:flex;flex-direction:column;gap:0.25rem;margin-bottom:0.75rem;flex:1}
        .document-meta span{font-size:0.875rem;color:#64748b}
        .document-type{background:#8B4513;color:#fff!important;padding:0.25rem 0.5rem;border-radius:4px;font-size:0.75rem!important;font-weight:500;text-transform:uppercase;display:inline-block;width:fit-content}
        .document-location{font-size:0.8rem;color:#94a3b8;margin-top:auto}
        .document-actions{position:absolute;top:0.75rem;right:0.75rem;display:flex;gap:0.5rem;opacity:0;transition:opacity 0.3s ease}
        .document-card:hover .document-actions{opacity:1}
        .action-btn{background:rgba(255,255,255,0.9);border:1px solid rgba(226,232,240,0.8);color:#64748b;cursor:pointer;padding:0.5rem;border-radius:6px;transition:all 0.2s ease;display:flex;align-items:center;justify-content:center;width:36px;height:36px;backdrop-filter:blur(8px);box-shadow:0 2px 4px rgba(0,0,0,0.1)}
        .action-btn:hover{background:#8B4513;color:#fff;border-color:#8B4513;transform:scale(1.1);box-shadow:0 4px 8px rgba(0,0,0,0.15)}
        .action-btn.delete-btn{background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.3);color:#ef4444}
        .action-btn.delete-btn:hover{background:#ef4444;color:#fff;border-color:#dc2626}
        .action-btn.disabled{opacity:0.5;cursor:not-allowed;background:rgba(255,255,255,0.7)}
        .action-btn.disabled:hover{background:rgba(255,255,255,0.7);color:#64748b;transform:none}
        .action-btn i{width:16px;height:16px}
        .documents-list{display:flex;flex-direction:column;gap:1rem;padding:1.5rem;overflow-y:auto;flex:1}
        .documents-list .document-card{display:flex;flex-direction:row;align-items:center;height:80px;min-height:80px;max-height:80px;padding:1rem;gap:1rem}
        .documents-list .document-preview{width:60px;height:60px;border-bottom:none;border-radius:8px;margin:0;flex-shrink:0}
        .documents-list .document-info{flex:1;padding:0;display:flex;flex-direction:column;justify-content:center;min-height:60px;gap:0.25rem}
        .documents-list .document-name{font-size:0.9rem;margin:0;-webkit-line-clamp:1;line-clamp:1;line-height:1.2}
        .documents-list .document-meta{display:flex;flex-direction:row;gap:0.5rem;margin:0;flex-wrap:wrap;align-items:center}
        .documents-list .document-meta span{font-size:0.75rem;white-space:nowrap}
        .documents-list .document-type{font-size:0.65rem!important;padding:0.125rem 0.375rem}
        .documents-list .document-location{font-size:0.7rem;margin:0}
        .documents-list .document-actions{position:static;opacity:1;margin:0;flex-shrink:0}
        .empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:3rem;color:#94a3b8;min-height:300px}
        .empty-state i{width:64px;height:64px;margin-bottom:1.5rem;opacity:0.5}
        .empty-state h3{margin-bottom:0.75rem;color:#1e293b;font-size:1.25rem}
        .empty-state p{margin-bottom:1.5rem;line-height:1.6}
        .upload-link,.clear-filters{color:#8B4513;text-decoration:none;font-weight:500}
        .upload-link:hover,.clear-filters:hover{text-decoration:underline}
        .toolbar{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem;background:#fff;border-bottom:1px solid #e2e8f0}
        .toolbar-left{display:flex;align-items:center;gap:1rem}
        .toolbar-right{display:flex;align-items:center;gap:1rem}
        .view-controls{display:flex;gap:0.5rem}
        .btn-view{background:#f1f5f9;border:1px solid #e2e8f0;color:#64748b;cursor:pointer;padding:0.5rem 1rem;border-radius:6px;transition:all 0.2s ease;display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;font-weight:500}
        .btn-view:hover{background:#e2e8f0;color:#475569}
        .btn-view.active{background:#8B4513;color:#fff;border-color:#8B4513}
        .btn-view i{width:16px;height:16px}
        .sort-controls{display:flex;align-items:center;gap:0.5rem}
        .form-control-sm{padding:0.5rem;border:1px solid #e2e8f0;border-radius:6px;font-size:0.875rem;color:#64748b;background:#fff}
        .btn-icon-sm{background:#f1f5f9;border:none;color:#64748b;cursor:pointer;padding:0.5rem;border-radius:6px;transition:all 0.2s}
        .btn-icon-sm:hover{background:#e2e8f0;color:#475569}
        .filters-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}
        .filters-header h3{margin:0;color:#1e293b;font-size:1.125rem;font-weight:600}
        .search-section{margin-bottom:1.5rem}
        .search-input-group{position:relative;display:flex;align-items:center}
        .search-input-group i{position:absolute;left:0.75rem;color:#64748b;width:16px;height:16px}
        .search-input-group input{width:100%;padding:0.75rem 0.75rem 0.75rem 2.5rem;border:1px solid #e2e8f0;border-radius:6px;font-size:0.875rem;background:#fff}
        .btn-search{background:#8B4513;color:#fff;border:none;padding:0.5rem;border-radius:0 6px 6px 0;cursor:pointer;margin-left:-1px}
        .folders-section,.file-types-section{margin-bottom:1.5rem}
        .folders-section h4,.file-types-section h4{margin:0 0 0.75rem 0;color:#1e293b;font-size:0.875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em}
        .folders-list,.file-types-list{display:flex;flex-direction:column;gap:0.25rem}
        .folder-item,.type-item{display:flex;align-items:center;gap:0.75rem;padding:0.75rem;border-radius:8px;text-decoration:none;color:#64748b;transition:all 0.2s ease;font-size:0.875rem}
        .folder-item:hover,.type-item:hover{background:#f1f5f9;color:#1e293b}
        .folder-item.active,.type-item.active{background:#fff7ed;color:#8B4513;font-weight:500}
        .folder-item i,.type-item i{width:16px;height:16px;flex-shrink:0}
        .folder-item span,.type-item span{flex:1}
        .folder-item small{color:#94a3b8;font-size:0.75rem}
        .header-stats{display:flex;gap:1rem;margin-left:1rem}
        .stat-item{display:flex;align-items:center;gap:0.25rem;color:#64748b;font-size:0.875rem}
        .stat-item i{width:14px;height:14px}
        @media (max-width:768px){
            .inbox-container{grid-template-columns:1fr;height:calc(100vh - 80px)}
            .filters-panel{height:auto;max-height:200px}
            .documents-grid{grid-template-columns:1fr;gap:1rem;padding:1rem;height:calc(100vh - 300px)}
            .documents-list{height:calc(100vh - 300px);padding:1rem}
            .toolbar{flex-direction:column;gap:1rem;align-items:stretch;padding:1rem}
        }
    </style>
</body>
</html>