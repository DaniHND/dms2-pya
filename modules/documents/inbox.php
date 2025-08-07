<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

function getUserPermissions($userId) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $query = "
            SELECT ug.module_permissions, ug.access_restrictions 
            FROM user_groups ug
            INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
            WHERE ugm.user_id = ? AND ug.status = 'active'
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $groupData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $mergedPermissions = ['view' => false, 'download' => false, 'create' => false, 'edit' => false, 'delete' => false];
        $mergedRestrictions = ['companies' => [], 'departments' => [], 'document_types' => []];
        
        foreach ($groupData as $group) {
            $permissions = json_decode($group['module_permissions'] ?: '{}', true);
            $restrictions = json_decode($group['access_restrictions'] ?: '{}', true);
            
            foreach ($mergedPermissions as $key => $value) {
                if (isset($permissions[$key]) && $permissions[$key] === true) {
                    $mergedPermissions[$key] = true;
                }
            }
            
            foreach (['companies', 'departments', 'document_types'] as $restrictionType) {
                if (isset($restrictions[$restrictionType]) && is_array($restrictions[$restrictionType])) {
                    $mergedRestrictions[$restrictionType] = array_unique(
                        array_merge($mergedRestrictions[$restrictionType], $restrictions[$restrictionType])
                    );
                }
            }
        }
        
        return ['permissions' => $mergedPermissions, 'restrictions' => $mergedRestrictions];
    } catch (Exception $e) {
        error_log("Error getting user permissions: " . $e->getMessage());
        return ['permissions' => ['view' => true], 'restrictions' => ['companies' => [], 'departments' => [], 'document_types' => []]];
    }
}

function getNavigationItems($userId, $userRole, $currentPath = '') {
    $database = new Database();
    $pdo = $database->getConnection();
    $items = [];
    
    $pathParts = $currentPath ? explode('/', trim($currentPath, '/')) : [];
    $currentLevel = count($pathParts);
    
    if ($currentLevel === 0) {
        $userPermissions = getUserPermissions($userId);
        $restrictions = $userPermissions['restrictions'];
        
        $whereConditions = ["c.status = 'active'"];
        $params = [];
        
        if ($userRole !== 'admin' && !empty($restrictions['companies'])) {
            $placeholders = str_repeat('?,', count($restrictions['companies']) - 1) . '?';
            $whereConditions[] = "c.id IN ($placeholders)";
            $params = array_merge($params, $restrictions['companies']);
        } elseif ($userRole !== 'admin' && empty($restrictions['companies'])) {
            $userStmt = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $userInfo = $userStmt->fetch();
            if ($userInfo && $userInfo['company_id']) {
                $whereConditions[] = "c.id = ?";
                $params[] = $userInfo['company_id'];
            }
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $query = "
            SELECT c.id, c.name, c.description,
                   COUNT(DISTINCT d.id) as document_count,
                   COUNT(DISTINCT dept.id) as department_count
            FROM companies c
            LEFT JOIN documents d ON c.id = d.company_id AND d.status = 'active'
            LEFT JOIN departments dept ON c.id = dept.company_id AND dept.status = 'active'
            WHERE $whereClause
            GROUP BY c.id, c.name, c.description
            ORDER BY c.name
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($companies as $company) {
            $items[] = [
                'type' => 'company',
                'id' => $company['id'],
                'name' => $company['name'],
                'description' => $company['description'],
                'path' => $company['id'],
                'document_count' => $company['document_count'],
                'subfolder_count' => $company['department_count'],
                'icon' => 'building',
                'can_enter' => true,
                'can_create_inside' => true
            ];
        }
        
    } elseif ($currentLevel === 1) {
        $companyId = (int)$pathParts[0];
        
        $userPermissions = getUserPermissions($userId);
        $restrictions = $userPermissions['restrictions'];
        
        $hasAccess = $userRole === 'admin';
        if (!$hasAccess) {
            if (!empty($restrictions['companies'])) {
                $hasAccess = in_array($companyId, $restrictions['companies']);
            } else {
                $userStmt = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
                $userStmt->execute([$userId]);
                $userInfo = $userStmt->fetch();
                $hasAccess = $userInfo && $userInfo['company_id'] == $companyId;
            }
        }
        
        if (!$hasAccess) {
            return [];
        }
        
        $deptQuery = "
            SELECT d.id, d.name, d.description,
                   COUNT(doc.id) as document_count
            FROM departments d
            LEFT JOIN documents doc ON d.id = doc.department_id AND doc.status = 'active'
            WHERE d.company_id = ? AND d.status = 'active'
            GROUP BY d.id, d.name, d.description
            ORDER BY d.name
        ";
        
        $stmt = $pdo->prepare($deptQuery);
        $stmt->execute([$companyId]);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($departments as $dept) {
            $items[] = [
                'type' => 'department',
                'id' => $dept['id'],
                'name' => $dept['name'],
                'description' => $dept['description'],
                'path' => $companyId . '/' . $dept['id'],
                'document_count' => $dept['document_count'],
                'subfolder_count' => 0,
                'icon' => 'folder',
                'can_enter' => true,
                'can_create_inside' => false
            ];
        }
        
        $docQuery = "
            SELECT d.*, dt.name as document_type, u.first_name, u.last_name
            FROM documents d
            LEFT JOIN document_types dt ON d.document_type_id = dt.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.company_id = ? AND d.department_id IS NULL AND d.status = 'active'
            ORDER BY d.name
        ";
        
        $stmt = $pdo->prepare($docQuery);
        $stmt->execute([$companyId]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($documents as $doc) {
            $items[] = [
                'type' => 'document',
                'id' => $doc['id'],
                'name' => $doc['name'],
                'description' => $doc['description'],
                'path' => $companyId . '/doc_' . $doc['id'],
                'file_size' => $doc['file_size'],
                'mime_type' => $doc['mime_type'],
                'original_name' => $doc['original_name'],
                'file_path' => $doc['file_path'],
                'created_at' => $doc['created_at'],
                'document_type' => $doc['document_type'],
                'icon' => getFileIcon($doc['original_name'], $doc['mime_type']),
                'can_enter' => false,
                'can_create_inside' => false
            ];
        }
        
    } elseif ($currentLevel === 2) {
        $companyId = (int)$pathParts[0];
        $departmentId = (int)$pathParts[1];
        
        $docQuery = "
            SELECT d.*, dt.name as document_type, u.first_name, u.last_name
            FROM documents d
            LEFT JOIN document_types dt ON d.document_type_id = dt.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.company_id = ? AND d.department_id = ? AND d.status = 'active'
            ORDER BY d.name
        ";
        
        $stmt = $pdo->prepare($docQuery);
        $stmt->execute([$companyId, $departmentId]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($documents as $doc) {
            $items[] = [
                'type' => 'document',
                'id' => $doc['id'],
                'name' => $doc['name'],
                'description' => $doc['description'],
                'path' => $companyId . '/' . $departmentId . '/doc_' . $doc['id'],
                'file_size' => $doc['file_size'],
                'mime_type' => $doc['mime_type'],
                'original_name' => $doc['original_name'],
                'file_path' => $doc['file_path'],
                'created_at' => $doc['created_at'],
                'document_type' => $doc['document_type'],
                'icon' => getFileIcon($doc['original_name'], $doc['mime_type']),
                'can_enter' => false,
                'can_create_inside' => false
            ];
        }
    }
    
    return $items;
}

function getBreadcrumbs($currentPath, $userId) {
    if (empty($currentPath)) {
        return [['name' => 'Inicio', 'path' => '', 'icon' => 'home']];
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    $breadcrumbs = [['name' => 'Inicio', 'path' => '', 'icon' => 'home']];
    
    $pathParts = explode('/', trim($currentPath, '/'));
    
    if (count($pathParts) >= 1 && is_numeric($pathParts[0])) {
        $companyId = (int)$pathParts[0];
        $stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch();
        
        if ($company) {
            $breadcrumbs[] = [
                'name' => $company['name'],
                'path' => $companyId,
                'icon' => 'building'
            ];
        }
    }
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $departmentId = (int)$pathParts[1];
        $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
        $stmt->execute([$departmentId]);
        $department = $stmt->fetch();
        
        if ($department) {
            $breadcrumbs[] = [
                'name' => $department['name'],
                'path' => $pathParts[0] . '/' . $departmentId,
                'icon' => 'folder'
            ];
        }
    }
    
    return $breadcrumbs;
}

function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 1) . ' ' . $units[$pow];
}

function formatDate($date) {
    return $date ? date('d/m/Y H:i', strtotime($date)) : '';
}

function getFileIcon($filename, $mimeType) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return 'image';
    if ($ext === 'pdf') return 'file-text';
    if (in_array($ext, ['doc', 'docx'])) return 'file-text';
    if (in_array($ext, ['xls', 'xlsx'])) return 'grid';
    if (in_array($ext, ['mp4', 'avi', 'mov'])) return 'video';
    return 'file';
}

function getFileTypeClass($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return 'image';
    if ($ext === 'pdf') return 'pdf';
    if (in_array($ext, ['doc', 'docx'])) return 'word';
    if (in_array($ext, ['xls', 'xlsx'])) return 'excel';
    return 'file';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $userPermissions = getUserPermissions($currentUser['id']);
    $canDownload = $userPermissions['permissions']['download'] ?? true;
    $canCreate = $userPermissions['permissions']['create'] ?? true;
    $canEdit = $userPermissions['permissions']['edit'] ?? false;
    $canDelete = $userPermissions['permissions']['delete'] ?? false;
    
    if ($currentUser['role'] === 'admin') {
        $canDownload = $canCreate = $canEdit = $canDelete = true;
    }
    
    $downloadStmt = $pdo->prepare("SELECT download_enabled FROM users WHERE id = ?");
    $downloadStmt->execute([$currentUser['id']]);
    $downloadResult = $downloadStmt->fetch();
    $canDownload = $canDownload && ($downloadResult['download_enabled'] ?? true);
    
    $currentPath = isset($_GET['path']) ? trim($_GET['path']) : '';
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    $items = getNavigationItems($currentUser['id'], $currentUser['role'], $currentPath);
    
    if ($searchTerm) {
        $items = array_filter($items, function($item) use ($searchTerm) {
            return stripos($item['name'], $searchTerm) !== false || 
                   stripos($item['description'] ?? '', $searchTerm) !== false;
        });
    }
    
    $breadcrumbs = getBreadcrumbs($currentPath, $currentUser['id']);
    
    logActivity($currentUser['id'], 'view', 'visual_explorer', null, 'Usuario navegó por el explorador visual');
    
} catch (Exception $e) {
    error_log("Error in visual explorer: " . $e->getMessage());
    $items = [];
    $breadcrumbs = [['name' => 'Inicio', 'path' => '', 'icon' => 'home']];
    $canDownload = $canCreate = $canEdit = $canDelete = false;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explorador de Documentos - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <script src="https://unpkg.com/feather-icons"></script>
</head>

<body class="dashboard-layout">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Explorador de Documentos</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(trim($currentUser['first_name'] . ' ' . $currentUser['last_name'])); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" onclick="showSettings()">
                        <i data-feather="settings"></i>
                    </button>
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="page-header">
                <h1>Explorador de Documentos</h1>
                <p class="page-subtitle">Navegue y gestione sus documentos organizados por empresas y departamentos</p>
            </div>

            <div class="breadcrumb-section">
                <div class="breadcrumb-card">
                    <?php foreach($breadcrumbs as $index => $crumb): ?>
                        <?php if($index > 0): ?>
                            <span class="breadcrumb-separator">
                                <i data-feather="chevron-right"></i>
                            </span>
                        <?php endif; ?>
                        <a href="?path=<?= urlencode($crumb['path']) ?>" 
                           class="breadcrumb-item <?= $index === count($breadcrumbs) - 1 ? 'current' : '' ?>">
                            <i data-feather="<?= $crumb['icon'] ?>"></i>
                            <span><?= htmlspecialchars($crumb['name']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="toolbar-section">
                <div class="toolbar-card">
                    <div class="toolbar-left">
                        <?php if($canCreate && !empty($currentPath)): ?>
                        <button class="btn-create" onclick="createNewFolder()">
                            <i data-feather="folder-plus"></i>
                            <span>Nueva Carpeta</span>
                        </button>
                        <?php endif; ?>
                        
                        <?php if($canCreate): ?>
                        <a href="upload.php" class="btn-secondary">
                            <i data-feather="upload"></i>
                            <span>Subir Archivo</span>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="toolbar-right">
                        <div class="search-wrapper">
                            <i data-feather="search" class="search-icon"></i>
                            <input type="text" class="search-input" placeholder="Buscar documentos..." 
                                   value="<?= htmlspecialchars($searchTerm) ?>" 
                                   onkeypress="if(event.key==='Enter') search(this.value)">
                            <?php if($searchTerm): ?>
                            <button class="search-clear" onclick="clearSearch()">
                                <i data-feather="x"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-section">
                <div class="content-card">
                    <div class="content-header">
                        <h3>
                            <?php if(empty($items)): ?>
                                Carpeta vacía
                            <?php else: ?>
                                <?= count($items) ?> elemento<?= count($items) !== 1 ? 's' : '' ?> encontrado<?= count($items) !== 1 ? 's' : '' ?>
                            <?php endif; ?>
                        </h3>
                    </div>
                    
                    <div class="content-body">
                        <?php if(empty($items)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i data-feather="folder"></i>
                                </div>
                                <h3>Carpeta vacía</h3>
                                <p>No hay elementos para mostrar en esta ubicación. <?php if($canCreate): ?>Puede crear una nueva carpeta o subir archivos para comenzar.<?php endif; ?></p>
                                <?php if($canCreate): ?>
                                    <div class="empty-actions">
                                        <?php if(!empty($currentPath)): ?>
                                        <button class="btn-create" onclick="createNewFolder()">
                                            <i data-feather="folder-plus"></i>
                                            <span>Crear Carpeta</span>
                                        </button>
                                        <?php endif; ?>
                                        <a href="upload.php" class="btn-secondary">
                                            <i data-feather="upload"></i>
                                            <span>Subir Archivo</span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="items-grid">
                                <?php foreach($items as $item): ?>
                                    <div class="explorer-item" onclick="<?= $item['can_enter'] ? "navigateTo('{$item['path']}')" : ($item['type'] === 'document' ? "viewDocument('{$item['id']}')" : '') ?>">
                                        <div class="item-icon <?= $item['type'] === 'company' ? 'company' : ($item['type'] === 'department' ? 'folder' : getFileTypeClass($item['original_name'] ?? '')) ?>">
                                            <?php if($item['type'] === 'document' && strpos($item['mime_type'], 'image/') === 0): ?>
                                                <img src="<?= htmlspecialchars($item['file_path']) ?>" alt="Preview" class="item-preview">
                                            <?php else: ?>
                                                <i data-feather="<?= $item['icon'] ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="item-details">
                                            <div class="item-name" title="<?= htmlspecialchars($item['name']) ?>">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </div>
                                            
                                            <div class="item-info">
                                                <?php if($item['type'] === 'document'): ?>
                                                    <span class="item-size"><?= formatBytes($item['file_size']) ?></span>
                                                    <span class="item-date"><?= formatDate($item['created_at']) ?></span>
                                                <?php elseif($item['type'] === 'company'): ?>
                                                    <span class="item-count"><?= $item['document_count'] ?> documentos</span>
                                                    <span class="item-count"><?= $item['subfolder_count'] ?> departamentos</span>
                                                <?php elseif($item['type'] === 'department'): ?>
                                                    <span class="item-count"><?= $item['document_count'] ?> documentos</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if($item['type'] === 'document'): ?>
                                        <div class="item-actions">
                                            <?php if($canDownload): ?>
                                            <button class="action-btn" onclick="event.stopPropagation(); downloadDocument('<?= $item['id'] ?>')" title="Descargar">
                                                <i data-feather="download"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if($canDelete || $currentUser['role'] === 'admin'): ?>
                                            <button class="action-btn delete-btn" onclick="event.stopPropagation(); deleteDocument('<?= $item['id'] ?>', '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')" title="Eliminar">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i data-feather="folder-plus"></i>
                    <span>Crear Nueva Carpeta</span>
                </h3>
                <button class="modal-close" onclick="closeCreateModal()">
                    <i data-feather="x"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="createFolderForm" onsubmit="submitCreateFolder(event)">
                    <div class="form-group">
                        <label class="form-label">Tipo de carpeta</label>
                        <select name="folder_type" id="folderType" class="form-control" onchange="updateFolderForm()">
                            <option value="">Seleccione el tipo</option>
                            <?php if(empty($currentPath)): ?>
                            <option value="company">Nueva Empresa</option>
                            <?php elseif(count(explode('/', trim($currentPath, '/'))) === 1): ?>
                            <option value="department">Nuevo Departamento</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" required placeholder="Ingrese el nombre de la carpeta">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Descripción (opcional)</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Descripción de la carpeta"></textarea>
                    </div>
                    
                    <input type="hidden" name="current_path" value="<?= htmlspecialchars($currentPath) ?>">
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeCreateModal()">
                            <span>Cancelar</span>
                        </button>
                        <button type="submit" class="btn-create">
                            <i data-feather="plus"></i>
                            <span>Crear Carpeta</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .container {
            padding: var(--spacing-8);
            background: var(--bg-secondary);
        }

        .page-header {
            margin-bottom: var(--spacing-8);
        }

        .page-header h1 {
            color: var(--text-primary);
            font-size: 1.875rem;
            font-weight: 700;
            margin: 0 0 var(--spacing-2) 0;
        }

        .page-subtitle {
            color: var(--text-secondary);
            margin: 0;
        }

        .breadcrumb-section,
        .toolbar-section,
        .content-section {
            margin-bottom: var(--spacing-8);
        }

        .breadcrumb-card,
        .toolbar-card,
        .content-card {
            background: var(--bg-primary);
            border-radius: var(--radius-xl);
            border: 1px solid #e2e8f0;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .breadcrumb-card {
            padding: var(--spacing-5) var(--spacing-6);
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            flex-wrap: wrap;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            padding: var(--spacing-2) var(--spacing-3);
            border-radius: var(--radius-lg);
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .breadcrumb-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            text-decoration: none;
        }

        .breadcrumb-item.current {
            color: var(--primary-color);
            background: var(--primary-light);
            font-weight: 600;
        }

        .breadcrumb-separator {
            color: #cbd5e1;
            margin: 0 var(--spacing-1);
        }

        .toolbar-card {
            padding: var(--spacing-5) var(--spacing-6);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--spacing-4);
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: var(--spacing-4);
        }

        .toolbar-right {
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
        }

        .btn-create {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--text-light);
            border: none;
            padding: var(--spacing-3) var(--spacing-5);
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-2);
            text-decoration: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-create:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            color: var(--text-light);
            text-decoration: none;
        }

        .btn-secondary {
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid #e2e8f0;
            padding: var(--spacing-3) var(--spacing-5);
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-2);
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
            color: var(--primary-color);
            text-decoration: none;
        }

        .search-wrapper {
            position: relative;
        }

        .search-input {
            width: 300px;
            padding: var(--spacing-3) var(--spacing-4) var(--spacing-3) 44px;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            background: var(--bg-primary);
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .search-icon {
            position: absolute;
            left: var(--spacing-4);
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        .search-clear {
            position: absolute;
            right: var(--spacing-3);
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: var(--spacing-1);
            border-radius: var(--radius-md);
            transition: var(--transition);
        }

        .search-clear:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .content-header {
            padding: var(--spacing-5) var(--spacing-6);
            border-bottom: 1px solid #e2e8f0;
            background: var(--bg-tertiary);
        }

        .content-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.125rem;
            font-weight: 600;
        }

        .content-body {
            padding: var(--spacing-8);
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--spacing-6);
        }

        .explorer-item {
            background: var(--bg-primary);
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-xl);
            padding: var(--spacing-6) var(--spacing-5);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 200px;
        }

        .explorer-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
            border-color: var(--primary-color);
        }

        .item-icon {
            width: 64px;
            height: 64px;
            margin-bottom: var(--spacing-4);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-xl);
            position: relative;
        }

        .item-icon.folder {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: var(--text-light);
            box-shadow: 0 4px 6px -1px rgba(251, 191, 36, 0.4);
        }

        .item-icon.company {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--text-light);
            box-shadow: 0 4px 6px -1px rgba(212, 175, 55, 0.4);
        }

        .item-icon.pdf {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: var(--text-light);
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.4);
        }

        .item-icon.word {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: var(--text-light);
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.4);
        }

        .item-icon.excel {
            background: linear-gradient(135deg, #10b981, #059669);
            color: var(--text-light);
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.4);
        }

        .item-icon.image {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: var(--text-light);
            box-shadow: 0 4px 6px -1px rgba(139, 92, 246, 0.4);
        }

        .item-icon.file {
            background: linear-gradient(135deg, #64748b, #475569);
            color: var(--text-light);
            box-shadow: 0 4px 6px -1px rgba(100, 116, 139, 0.4);
        }

        .item-icon i {
            font-size: 28px;
        }

        .item-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--radius-lg);
        }

        .item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 100%;
        }

        .item-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-2);
            line-height: 1.4;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-box-orient: vertical;
        }

        .item-info {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-1);
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
        }

        .item-actions {
            position: absolute;
            top: var(--spacing-3);
            right: var(--spacing-3);
            display: flex;
            gap: var(--spacing-1);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .explorer-item:hover .item-actions {
            opacity: 1;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-md);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #e2e8f0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .action-btn:hover {
            background: var(--bg-primary);
            color: var(--text-primary);
            box-shadow: var(--shadow-sm);
            border-color: var(--primary-color);
        }

        .action-btn.delete-btn:hover {
            background: #ef4444;
            color: var(--text-light);
            border-color: #ef4444;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: var(--spacing-12) var(--spacing-8);
            color: var(--text-muted);
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--spacing-6);
            color: #cbd5e1;
        }

        .empty-icon i {
            font-size: 2.5rem;
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-3);
            font-size: 1.25rem;
            font-weight: 600;
        }

        .empty-state p {
            margin-bottom: var(--spacing-6);
            line-height: 1.6;
            max-width: 400px;
        }

        .empty-actions {
            display: flex;
            gap: var(--spacing-3);
            flex-wrap: wrap;
            justify-content: center;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--bg-primary);
            border-radius: var(--radius-xl);
            box-shadow: var(--card-shadow-hover);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
            transform: scale(0.95) translateY(20px);
            transition: all 0.3s ease;
        }

        .modal.active .modal-content {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            padding: var(--spacing-6) var(--spacing-6) var(--spacing-4);
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-tertiary);
        }

        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .modal-close:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .modal-body {
            padding: var(--spacing-6);
        }

        .form-group {
            margin-bottom: var(--spacing-5);
        }

        .form-label {
            display: block;
            margin-bottom: var(--spacing-2);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: var(--spacing-3) var(--spacing-4);
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            transition: var(--transition);
            background: var(--bg-primary);
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: var(--spacing-3);
            margin-top: var(--spacing-6);
            padding-top: var(--spacing-5);
            border-top: 1px solid #e2e8f0;
        }

        @media (max-width: 768px) {
            .container {
                padding: var(--spacing-4);
            }

            .items-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: var(--spacing-4);
            }

            .toolbar-card {
                flex-direction: column;
                align-items: stretch;
                gap: var(--spacing-4);
            }

            .search-input {
                width: 100%;
                max-width: 300px;
            }

            .modal-content {
                margin: var(--spacing-5);
                width: calc(100% - var(--spacing-10));
            }
        }
    </style>

    <script>
        const currentUserId = <?= $currentUser['id'] ?>;
        const currentUserRole = '<?= $currentUser['role'] ?>';
        const canDownload = <?= $canDownload ? 'true' : 'false' ?>;
        const canCreate = <?= $canCreate ? 'true' : 'false' ?>;
        const canDelete = <?= $canDelete ? 'true' : 'false' ?>;
        const currentPath = '<?= htmlspecialchars($currentPath) ?>';
        
        function navigateTo(path) {
            console.log('📁 Navegando a:', path);
            window.location.href = `?path=${encodeURIComponent(path)}`;
        }
        
        function search(term) {
            const url = new URL(window.location);
            if (term.trim()) {
                url.searchParams.set('search', term.trim());
            } else {
                url.searchParams.delete('search');
            }
            window.location.href = url.toString();
        }
        
        function clearSearch() {
            const url = new URL(window.location);
            url.searchParams.delete('search');
            window.location.href = url.toString();
        }
        
        function viewDocument(documentId) {
            console.log('👁️ Ver documento:', documentId);
            window.location.href = `view.php?id=${documentId}`;
        }
        
        function downloadDocument(documentId) {
            if (!canDownload) {
                alert('No tienes permisos para descargar');
                return;
            }
            
            console.log('⬇️ Descargar documento:', documentId);
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'download.php';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'document_id';
            input.value = documentId;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            
            setTimeout(() => {
                if (document.body.contains(form)) {
                    document.body.removeChild(form);
                }
            }, 2000);
        }
        
        function deleteDocument(documentId, documentName) {
            if (!canDelete && currentUserRole !== 'admin') {
                alert('No tienes permisos para eliminar');
                return;
            }
            
            if (!confirm(`¿Eliminar "${documentName}"?\n\n⚠️ Esta acción no se puede deshacer.`)) {
                return;
            }
            
            if (!confirm('¿Está completamente seguro? Esta es la última oportunidad.')) {
                return;
            }
            
            console.log('🗑️ Eliminar documento:', documentId);
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete.php';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'document_id';
            input.value = documentId;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
        
        function createNewFolder() {
            if (!canCreate) {
                alert('No tienes permisos para crear carpetas');
                return;
            }
            
            const modal = document.getElementById('createFolderModal');
            modal.classList.add('active');
            updateFolderForm();
            
            setTimeout(() => {
                const nameInput = document.querySelector('#createFolderModal input[name="name"]');
                if (nameInput) nameInput.focus();
            }, 100);
        }
        
        function closeCreateModal() {
            const modal = document.getElementById('createFolderModal');
            modal.classList.remove('active');
            document.getElementById('createFolderForm').reset();
        }
        
        function updateFolderForm() {
            const folderType = document.getElementById('folderType').value;
            console.log('Tipo de carpeta seleccionado:', folderType);
        }
        
        async function submitCreateFolder(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            try {
                submitBtn.innerHTML = '<i data-feather="loader"></i> <span>Creando...</span>';
                submitBtn.disabled = true;
                
                const folderType = formData.get('folder_type');
                if (folderType === 'company') {
                    formData.append('action', 'create_company');
                } else if (folderType === 'department') {
                    formData.append('action', 'create_department');
                    const pathParts = currentPath.split('/');
                    if (pathParts.length >= 1 && pathParts[0]) {
                        formData.append('company_id', pathParts[0]);
                    }
                }
                
                const response = await fetch('folder_actions.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ Carpeta creada exitosamente');
                    closeCreateModal();
                    window.location.reload();
                } else {
                    alert('❌ ' + (data.message || 'Error al crear la carpeta'));
                }
                
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error de conexión al crear la carpeta');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                feather.replace();
            }
        }
        
        function showSettings() {
            alert('Configuración estará disponible próximamente');
        }
        
        function toggleSidebar() {
            console.log('Toggle sidebar');
        }
        
        function updateTime() {
            const now = new Date();
            const options = {
                weekday: 'short',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            };
            const timeString = now.toLocaleDateString('es-ES', options);
            const element = document.getElementById('currentTime');
            if (element) element.textContent = timeString;
        }
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeCreateModal();
            }
            
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.querySelector('.search-input');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            if (e.key === 'Backspace' && !e.target.matches('input, textarea')) {
                e.preventDefault();
                const breadcrumbs = document.querySelectorAll('.breadcrumb-item');
                if (breadcrumbs.length > 1) {
                    breadcrumbs[breadcrumbs.length - 2].click();
                }
            }
        });
        
        document.addEventListener('click', (e) => {
            const modal = document.getElementById('createFolderModal');
            if (e.target === modal) {
                closeCreateModal();
            }
        });
        
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
            updateTime();
            setInterval(updateTime, 60000);
            
            console.log('📁 Explorador visual iniciado');
            console.log('Ruta actual:', currentPath);
            console.log('Permisos:', {canDownload, canCreate, canDelete});
        });
    </script>
</body>
</html>