<?php
// Tu código existente...
require_once '../../config/session.php';
require_once '../../config/database.php';
// ... otros requires que ya tenías



SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// ===================================================================
// SISTEMA DE PERMISOS DE GRUPOS - PRIORIDAD SOBRE PERMISOS BÁSICOS
// ===================================================================

function isSuperUser($userId) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $query = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($user && $user['role'] === 'super_admin');
    } catch (Exception $e) {
        return false;
    }
}

// ===================================================================
// CORREGIR LA FUNCIÓN getUserPermissions EN INBOX.PHP
// ===================================================================

function getUserPermissions($userId)
{
    // Si es super usuario, acceso total
    if (isSuperUser($userId)) {
        return [
            'permissions' => [
                'view' => true,
                'download' => true,
                'create' => true,
                'edit' => true,
                'delete' => true
            ],
            'restrictions' => [
                'companies' => [],
                'departments' => [],
                'document_types' => []
            ]
        ];
    }

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

        // Permisos iniciales - RESTRICTIVOS por defecto
        $mergedPermissions = [
            'view' => false,
            'download' => false,
            'create' => false,
            'edit' => false,
            'delete' => false
        ];

        $mergedRestrictions = [
            'companies' => [],
            'departments' => [],
            'document_types' => []
        ];

        // Fusionar permisos de todos los grupos (OR lógico)
        foreach ($groupData as $group) {
            $permissions = json_decode($group['module_permissions'] ?: '{}', true);
            $restrictions = json_decode($group['access_restrictions'] ?: '{}', true);

            // ===== MAPEO CORREGIDO DE PERMISOS =====
            
            // Ver archivos (sistema nuevo y viejo)
            if (isset($permissions['view_files']) && $permissions['view_files'] === true) {
                $mergedPermissions['view'] = true;
            } elseif (isset($permissions['view']) && $permissions['view'] === true) {
                $mergedPermissions['view'] = true;
            }
            
            // ===== DESCARGA CORREGIDA =====
            if (isset($permissions['download_files']) && $permissions['download_files'] === true) {
                $mergedPermissions['download'] = true;
            } elseif (isset($permissions['download']) && $permissions['download'] === true) {
                $mergedPermissions['download'] = true;
            }
            
            // Crear/subir archivos Y crear carpetas
            if (isset($permissions['upload_files']) && $permissions['upload_files'] === true) {
                $mergedPermissions['create'] = true;
            } elseif (isset($permissions['create_folders']) && $permissions['create_folders'] === true) {
                $mergedPermissions['create'] = true;
            } elseif (isset($permissions['create']) && $permissions['create'] === true) {
                $mergedPermissions['create'] = true;
            }
            
            // Editar archivos
            if (isset($permissions['create_folders']) && $permissions['create_folders'] === true) {
                $mergedPermissions['edit'] = true;
            } elseif (isset($permissions['edit']) && $permissions['edit'] === true) {
                $mergedPermissions['edit'] = true;
            }
            
            // Eliminar archivos
            if (isset($permissions['delete_files']) && $permissions['delete_files'] === true) {
                $mergedPermissions['delete'] = true;
            } elseif (isset($permissions['delete']) && $permissions['delete'] === true) {
                $mergedPermissions['delete'] = true;
            }

            // Manejar restricciones
            foreach (['companies', 'departments', 'document_types'] as $restrictionType) {
                if (isset($restrictions[$restrictionType]) && is_array($restrictions[$restrictionType])) {
                    $mergedRestrictions[$restrictionType] = array_unique(
                        array_merge($mergedRestrictions[$restrictionType], $restrictions[$restrictionType])
                    );
                }
            }
        }

        return [
            'permissions' => $mergedPermissions,
            'restrictions' => $mergedRestrictions
        ];
    } catch (Exception $e) {
        error_log("Error getting user permissions: " . $e->getMessage());
        return [
            'permissions' => ['view' => false, 'download' => false, 'create' => false, 'edit' => false, 'delete' => false],
            'restrictions' => ['companies' => [], 'departments' => [], 'document_types' => []]
        ];
    }
}
function getNavigationItems($userId, $userRole, $currentPath = '') {
    $database = new Database();
    $pdo = $database->getConnection();
    $items = [];

    $pathParts = $currentPath ? explode('/', trim($currentPath, '/')) : [];
    $currentLevel = count($pathParts);

    if ($currentLevel === 0) {
        // NIVEL 0: EMPRESAS
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
                'icon' => 'home',
                'can_enter' => true,
                'can_create_inside' => true
            ];
        }
        
    } elseif ($currentLevel === 1) {
        // NIVEL 1: DEPARTAMENTOS + DOCUMENTOS DE EMPRESA
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

        // DEPARTAMENTOS
        $deptQuery = "
            SELECT d.id, d.name, d.description,
                   COUNT(doc.id) as document_count,
                   COUNT(DISTINCT f.id) as folder_count
            FROM departments d
            LEFT JOIN documents doc ON d.id = doc.department_id AND doc.status = 'active'
            LEFT JOIN document_folders f ON d.id = f.department_id AND f.is_active = 1
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
                'subfolder_count' => $dept['folder_count'],
                'icon' => 'folder',
                'can_enter' => true,
                'can_create_inside' => true
            ];
        }

        // DOCUMENTOS SIN DEPARTAMENTO
        $docQuery = "
            SELECT d.*, dt.name as document_type, u.first_name, u.last_name
            FROM documents d
            LEFT JOIN document_types dt ON d.document_type_id = dt.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.company_id = ? AND d.department_id IS NULL AND d.folder_id IS NULL AND d.status = 'active'
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
                'can_create_inside' => false,
                'draggable' => true
            ];
        }
        
    } elseif ($currentLevel === 2) {
        // NIVEL 2: CARPETAS + DOCUMENTOS DE DEPARTAMENTO
        $companyId = (int)$pathParts[0];
        $departmentId = (int)$pathParts[1];

        $foldersQuery = "
            SELECT f.id, f.name, f.description, f.folder_color, f.folder_icon,
                   COUNT(doc.id) as document_count
            FROM document_folders f
            LEFT JOIN documents doc ON f.id = doc.folder_id AND doc.status = 'active'
            WHERE f.company_id = ? AND f.department_id = ? AND f.is_active = 1
            GROUP BY f.id, f.name, f.description, f.folder_color, f.folder_icon
            ORDER BY f.name
        ";

        $stmt = $pdo->prepare($foldersQuery);
        $stmt->execute([$companyId, $departmentId]);
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($folders as $folder) {
            $items[] = [
                'type' => 'document_folder',
                'id' => $folder['id'],
                'name' => $folder['name'],
                'description' => $folder['description'],
                'path' => $companyId . '/' . $departmentId . '/folder_' . $folder['id'],
                'document_count' => $folder['document_count'],
                'subfolder_count' => 0,
                'icon' => $folder['folder_icon'] ?: 'folder',
                'folder_color' => $folder['folder_color'] ?: '#3498db',
                'can_enter' => true,
                'can_create_inside' => false,
                'draggable_target' => true
            ];
        }

        $docQuery = "
            SELECT d.*, dt.name as document_type, u.first_name, u.last_name
            FROM documents d
            LEFT JOIN document_types dt ON d.document_type_id = dt.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.company_id = ? AND d.department_id = ? AND d.folder_id IS NULL AND d.status = 'active'
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
                'can_create_inside' => false,
                'draggable' => true
            ];
        }
        
    } elseif ($currentLevel === 3) {
        // NIVEL 3: DOCUMENTOS DENTRO DE CARPETAS
        $companyId = (int)$pathParts[0];
        $departmentId = (int)$pathParts[1];
        $folderPart = $pathParts[2];

        if (strpos($folderPart, 'folder_') === 0) {
            $folderId = (int)substr($folderPart, 7);
            
            $docQuery = "
                SELECT d.*, dt.name as document_type, u.first_name, u.last_name,
                       f.name as folder_name, f.folder_color, f.folder_icon
                FROM documents d
                LEFT JOIN document_types dt ON d.document_type_id = dt.id
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN document_folders f ON d.folder_id = f.id
                WHERE d.folder_id = ? AND d.status = 'active'
                ORDER BY d.name
            ";

            $stmt = $pdo->prepare($docQuery);
            $stmt->execute([$folderId]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($documents as $doc) {
                $items[] = [
                    'type' => 'document',
                    'id' => $doc['id'],
                    'name' => $doc['name'],
                    'description' => $doc['description'],
                    'path' => $companyId . '/' . $departmentId . '/folder_' . $folderId . '/doc_' . $doc['id'],
                    'file_size' => $doc['file_size'],
                    'mime_type' => $doc['mime_type'],
                    'original_name' => $doc['original_name'],
                    'file_path' => $doc['file_path'],
                    'created_at' => $doc['created_at'],
                    'document_type' => $doc['document_type'],
                    'folder_name' => $doc['folder_name'],
                    'folder_color' => $doc['folder_color'],
                    'folder_icon' => $doc['folder_icon'],
                    'icon' => getFileIcon($doc['original_name'], $doc['mime_type']),
                    'can_enter' => false,
                    'can_create_inside' => false,
                    'draggable' => true
                ];
            }
        }
    }

    return $items;
}

function getBreadcrumbs($currentPath, $userId) {
    if (empty($currentPath)) {
        return [['name' => 'Inicio', 'path' => '', 'icon' => 'home', 'drop_target' => true]];
    }

    $database = new Database();
    $pdo = $database->getConnection();
    $breadcrumbs = [['name' => 'Inicio', 'path' => '', 'icon' => 'home', 'drop_target' => true]];

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
                'icon' => 'home',
                'drop_target' => true
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
                'icon' => 'folder',
                'drop_target' => true
            ];
        }
    }

    if (count($pathParts) >= 3) {
        $folderPart = $pathParts[2];
        
        if (strpos($folderPart, 'folder_') === 0) {
            $folderId = (int)substr($folderPart, 7);
            $stmt = $pdo->prepare("SELECT name, folder_icon FROM document_folders WHERE id = ?");
            $stmt->execute([$folderId]);
            $folder = $stmt->fetch();

            if ($folder) {
                $breadcrumbs[] = [
                    'name' => $folder['name'],
                    'path' => $pathParts[0] . '/' . $pathParts[1] . '/' . $folderPart,
                    'icon' => $folder['folder_icon'] ?: 'folder',
                    'drop_target' => true
                ];
            }
        }
    }

    return $breadcrumbs;
}

function searchItems($userId, $userRole, $searchTerm, $currentPath = '') {
    if (empty($searchTerm)) {
        return [];
    }

    $database = new Database();
    $pdo = $database->getConnection();
    $results = [];
    
    $userPermissions = getUserPermissions($userId);
    $restrictions = $userPermissions['restrictions'];
    
    $companyRestriction = '';
    $params = ["%{$searchTerm}%", "%{$searchTerm}%", "%{$searchTerm}%"];
    
    if ($userRole !== 'admin' && !empty($restrictions['companies'])) {
        $placeholders = str_repeat('?,', count($restrictions['companies']) - 1) . '?';
        $companyRestriction = " AND company_id IN ($placeholders)";
        $params = array_merge($params, $restrictions['companies']);
    }
    
    $docsQuery = "
        SELECT 'document' as type, doc.id, doc.name, doc.description, doc.company_id, doc.department_id, doc.folder_id,
               doc.file_size, doc.mime_type, doc.original_name, doc.file_path, doc.created_at,
               c.name as company_name, d.name as department_name, f.name as folder_name,
               dt.name as document_type
        FROM documents doc
        INNER JOIN companies c ON doc.company_id = c.id
        LEFT JOIN departments d ON doc.department_id = d.id
        LEFT JOIN document_folders f ON doc.folder_id = f.id
        LEFT JOIN document_types dt ON doc.document_type_id = dt.id
        WHERE doc.status = 'active' AND c.status = 'active'
        AND (doc.name LIKE ? OR doc.description LIKE ? OR doc.original_name LIKE ?) 
        $companyRestriction
        ORDER BY doc.name
    ";
    $searchParams = ["%{$searchTerm}%", "%{$searchTerm}%", "%{$searchTerm}%"];
    if ($userRole !== 'admin' && !empty($restrictions['companies'])) {
        $searchParams = array_merge($searchParams, $restrictions['companies']);
    }
    
    $stmt = $pdo->prepare($docsQuery);
    $stmt->execute($searchParams);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($documents as $doc) {
        $locationParts = [$doc['company_name']];
        $pathParts = [$doc['company_id']];
        
        if ($doc['department_name']) {
            $locationParts[] = $doc['department_name'];
            $pathParts[] = $doc['department_id'];
        }
        
        if ($doc['folder_name']) {
            $locationParts[] = $doc['folder_name'];
            $pathParts[] = 'folder_' . $doc['folder_id'];
        }
        
        $pathParts[] = 'doc_' . $doc['id'];
        
        $results[] = [
            'type' => 'document',
            'id' => $doc['id'],
            'name' => $doc['name'],
            'description' => $doc['description'],
            'path' => implode('/', $pathParts),
            'file_size' => $doc['file_size'],
            'mime_type' => $doc['mime_type'],
            'original_name' => $doc['original_name'],
            'file_path' => $doc['file_path'],
            'created_at' => $doc['created_at'],
            'document_type' => $doc['document_type'],
            'icon' => getFileIcon($doc['original_name'], $doc['mime_type']),
            'location' => 'Documento en ' . implode(' → ', $locationParts),
            'draggable' => true
        ];
    }
    
    return $results;
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

function adjustBrightness($color, $percent) {
    if (strlen($color) != 7) return $color;
    $red = hexdec(substr($color, 1, 2));
    $green = hexdec(substr($color, 3, 2));
    $blue = hexdec(substr($color, 5, 2));
    
    $red = max(0, min(255, $red + ($red * $percent / 100)));
    $green = max(0, min(255, $green + ($green * $percent / 100)));
    $blue = max(0, min(255, $blue + ($blue * $percent / 100)));
    
    return sprintf("#%02x%02x%02x", $red, $green, $blue);
}

// ===================================================================
// LÓGICA PRINCIPAL
// ===================================================================

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $userPermissions = getUserPermissions($currentUser['id']);
    $canView = $userPermissions['permissions']['view'] ?? false;
    $canDownload = $userPermissions['permissions']['download'] ?? false;
    $canCreate = $userPermissions['permissions']['create'] ?? false;
    $canEdit = $userPermissions['permissions']['edit'] ?? false;
    $canDelete = $userPermissions['permissions']['delete'] ?? false;

    if ($currentUser['role'] === 'admin') {
        $canView = $canDownload = $canCreate = $canEdit = $canDelete = true;
    }

    
    

   /* if (!$canView && $currentUser['role'] !== 'admin') {
        $items = [];
        $breadcrumbs = [['name' => 'Sin acceso', 'path' => '', 'icon' => 'lock']];
        $noAccess = true;
    } else {
        $currentPath = isset($_GET['path']) ? trim($_GET['path']) : '';
        $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

        if ($searchTerm) {
            $items = searchItems($currentUser['id'], $currentUser['role'], $searchTerm, $currentPath);
        } else {
            $items = getNavigationItems($currentUser['id'], $currentUser['role'], $currentPath);
        }

        $breadcrumbs = getBreadcrumbs($currentPath, $currentUser['id']);
        $noAccess = false;
    }

    logActivity($currentUser['id'], 'view', 'visual_explorer', null, 'Usuario navegó por el explorador visual');*/

    // REEMPLAZAR CON ESTO (sin verificación de $canView):
$currentPath = isset($_GET['path']) ? trim($_GET['path']) : '';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($searchTerm) {
    $items = searchItems($currentUser['id'], $currentUser['role'], $searchTerm, $currentPath);
} else {
    $items = getNavigationItems($currentUser['id'], $currentUser['role'], $currentPath);
}

$breadcrumbs = getBreadcrumbs($currentPath, $currentUser['id']);
$noAccess = false;
    
} catch (Exception $e) {
    error_log("Error in visual explorer: " . $e->getMessage());
    $items = [];
    $breadcrumbs = [['name' => 'Error', 'path' => '', 'icon' => 'alert-circle']];
    $canView = $canDownload = $canCreate = $canEdit = $canDelete = false;
    $noAccess = true;
}

$pathParts = isset($currentPath) ? ($currentPath ? explode('/', trim($currentPath, '/')) : []) : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explorador de Documentos - DMS2</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/inbox-visual.css">
    
    
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
</head>

<body class="dashboard-layout">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- HEADER -->
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
                <p class="page-subtitle">
                    <?php if (isset($noAccess) && $noAccess): ?>
                        Su usuario no tiene permisos para ver documentos. Contacte al administrador.
                    <?php else: ?>
                        Navegue y gestione sus documentos organizados por empresas y departamentos
                    <?php endif; ?>
                </p>
            </div>

            <?php if (!isset($noAccess) || !$noAccess): ?>
            <!-- BREADCRUMB -->
            <div class="breadcrumb-section">
                <div class="breadcrumb-card">
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <?php if ($index > 0): ?>
                            <span class="breadcrumb-separator">
                                <i data-feather="chevron-right"></i>
                            </span>
                        <?php endif; ?>
                        <a href="?path=<?= urlencode($crumb['path']) ?>"
                            class="breadcrumb-item <?= $index === count($breadcrumbs) - 1 ? 'current' : '' ?> <?= isset($crumb['drop_target']) ? 'breadcrumb-drop-target' : '' ?>"
                            data-breadcrumb-path="<?= htmlspecialchars($crumb['path']) ?>">
                            <i data-feather="<?= $crumb['icon'] ?>"></i>
                            <span><?= htmlspecialchars($crumb['name']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TOOLBAR -->
            <div class="toolbar-section">
                <div class="toolbar-card">
                    <div class="toolbar-left">
                        <!-- BOTONES DE VISTA -->
                        <div class="view-toggle">
                            <button class="view-btn active" onclick="changeView('grid')" data-view="grid" title="Vista en cuadrícula">
                                <i data-feather="grid"></i>
                            </button>
                            <button class="view-btn" onclick="changeView('list')" data-view="list" title="Vista en lista">
                                <i data-feather="list"></i>
                            </button>
                        </div>

                        <!-- CREAR CARPETA -->
                        <?php if ($canCreate && count($pathParts) === 2 && is_numeric($pathParts[0]) && is_numeric($pathParts[1])): ?>
                            <button class="btn-create" onclick="createDocumentFolder()">
                                <i data-feather="folder-plus"></i>
                                <span>Nueva Carpeta</span>
                            </button>
                        <?php endif; ?>

                        <!-- SUBIR ARCHIVO -->
                        <?php if ($canCreate): ?>
                            <a href="upload.php<?= !empty($currentPath) ? '?path=' . urlencode($currentPath) : '' ?>" class="btn-secondary">
                                <i data-feather="upload"></i>
                                <span>Subir Archivo</span>
                            </a>
                        <?php endif; ?>
                      
                    </div>

                    <div class="toolbar-right">
                        <div class="search-wrapper">
                            <i data-feather="search" class="search-icon"></i>
                            <input type="text" class="search-input" placeholder="Buscar documentos, carpetas..."
                                value="<?= htmlspecialchars($searchTerm ?? '') ?>"
                                onkeypress="if(event.key==='Enter') search(this.value)"
                                oninput="handleSearchInput(this.value)">
                            <?php if (isset($searchTerm) && $searchTerm): ?>
                                <button class="search-clear" onclick="clearSearch()">
                                    <i data-feather="x"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RESULTADOS DE BÚSQUEDA -->
            <?php if (isset($searchTerm) && $searchTerm): ?>
            <div class="search-results-info">
                <div class="search-info-card">
                    <i data-feather="search"></i>
                    <span>Mostrando <?= count($items) ?> resultado<?= count($items) !== 1 ? 's' : '' ?> para "<strong><?= htmlspecialchars($searchTerm) ?></strong>"</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="content-section">
                <div class="content-card">
                    <div class="content-header">
                        <h3>
                            <?php if (empty($items)): ?>
                                <?= isset($searchTerm) && $searchTerm ? 'Sin resultados' : 'Carpeta vacía' ?>
                            <?php else: ?>
                                <?= count($items) ?> elemento<?= count($items) !== 1 ? 's' : '' ?> encontrado<?= count($items) !== 1 ? 's' : '' ?>
                            <?php endif; ?>
                        </h3>
                    </div>

                    <div class="content-body">
                        <?php if (empty($items)): ?>
                            <!-- ESTADO VACÍO -->
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i data-feather="<?= isset($searchTerm) && $searchTerm ? 'search' : 'folder' ?>"></i>
                                </div>
                                <h3><?= isset($searchTerm) && $searchTerm ? 'Sin resultados' : 'Carpeta vacía' ?></h3>
                                <p>
                                    <?php if (isset($searchTerm) && $searchTerm): ?>
                                        No se encontraron elementos que coincidan con "<?= htmlspecialchars($searchTerm) ?>". Intente con otros términos de búsqueda.
                                    <?php else: ?>
                                        No hay elementos para mostrar en esta ubicación. 
                                        <?php if ($canCreate): ?>
                                            Puede crear una nueva carpeta o subir archivos para comenzar.
                                        <?php else: ?>
                                            No tiene permisos para crear contenido.
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                                
                                <?php if ($canCreate && (!isset($searchTerm) || !$searchTerm)): ?>
                                    <div class="empty-actions">
                                        <?php if (count($pathParts) === 2 && is_numeric($pathParts[1])): ?>
                                            <button class="btn-create" onclick="createDocumentFolder()">
                                                <i data-feather="folder-plus"></i>
                                                <span>Crear Carpeta</span>
                                            </button>
                                        <?php endif; ?>
                                        <a href="<?= !empty($currentPath) ? 'upload.php?path=' . urlencode($currentPath) : 'upload.php' ?>" class="btn-secondary">
                                            <i data-feather="upload"></i>
                                            <span>Subir Archivo</span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- VISTA EN CUADRÍCULA -->
                            <div class="items-grid" id="gridView">
                                <?php foreach ($items as $item): ?>
                                    <div class="explorer-item <?= isset($item['draggable']) ? 'draggable-item' : '' ?> <?= isset($item['draggable_target']) ? 'drop-target' : '' ?>" 
                                         onclick="<?= $item['can_enter'] ?? false ? "navigateTo('{$item['path']}')" : ($item['type'] === 'document' && $canView ? "viewDocument('{$item['id']}')" : '') ?>"
                                         <?= isset($item['draggable']) ? 'draggable="true"' : '' ?>
                                         data-item-type="<?= $item['type'] ?>"
                                         data-item-id="<?= $item['id'] ?>"
                                         data-folder-id="<?= $item['type'] === 'document_folder' ? $item['id'] : '' ?>">
                                        
                                        <div class="item-icon <?= $item['type'] === 'company' ? 'company' : ($item['type'] === 'department' ? 'folder' : ($item['type'] === 'document_folder' ? 'document-folder' : getFileTypeClass($item['original_name'] ?? ''))) ?>"
                                             <?= isset($item['folder_color']) ? 'style="background: linear-gradient(135deg, ' . $item['folder_color'] . ', ' . adjustBrightness($item['folder_color'], -20) . ');"' : '' ?>>
                                            <?php if ($item['type'] === 'document' && isset($item['mime_type']) && strpos($item['mime_type'], 'image/') === 0): ?>
                                                <img src="../../<?= htmlspecialchars($item['file_path']) ?>" alt="Preview" class="item-preview">
                                            <?php else: ?>
                                                <i data-feather="<?= $item['icon'] ?>"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="item-details">
                                            <div class="item-name" title="<?= htmlspecialchars($item['name']) ?>">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </div>

                                            <div class="item-info">
                                                <?php if ($item['type'] === 'document'): ?>
                                                    <span class="item-size"><?= formatBytes($item['file_size']) ?></span>
                                                    <span class="item-date"><?= formatDate($item['created_at']) ?></span>
                                                <?php elseif ($item['type'] === 'company'): ?>
                                                    <span class="item-count"><?= $item['document_count'] ?> documentos</span>
                                                    <span class="item-count"><?= $item['subfolder_count'] ?> departamentos</span>
                                                <?php elseif ($item['type'] === 'department'): ?>
                                                    <span class="item-count"><?= $item['document_count'] ?> documentos</span>
                                                    <span class="item-count"><?= $item['subfolder_count'] ?> carpetas</span>
                                                <?php elseif ($item['type'] === 'document_folder'): ?>
                                                    <span class="item-count"><?= $item['document_count'] ?> documentos</span>
                                                    <span class="item-folder-type">Carpeta de documentos</span>
                                                <?php endif; ?>
                                                
                                                <?php if (isset($searchTerm) && $searchTerm && isset($item['location'])): ?>
                                                    <span class="item-location">
                                                        <i data-feather="map-pin" style="width: 12px; height: 12px;"></i>
                                                        <?= htmlspecialchars($item['location']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- ACCIONES DE DOCUMENTO -->
                                        <?php if ($item['type'] === 'document'): ?>
                                            <div class="item-actions">
                                                <?php if ($canDownload): ?>
                                                    <button class="action-btn" onclick="event.stopPropagation(); downloadDocument('<?= $item['id'] ?>')" title="Descargar">
                                                        <i data-feather="download"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($canEdit): ?>
                                                <button class="action-btn cut-btn" onclick="event.stopPropagation(); cutDocument('<?= $item['id'] ?>', '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')" title="Mover">
                                                    <i data-feather="move"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($canDelete): ?>
                                                    <button class="action-btn delete-btn" onclick="event.stopPropagation(); deleteDocument('<?= $item['id'] ?>', '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')" title="Eliminar">
                                                        <i data-feather="trash-2"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- VISTA EN LISTA -->
                            <div class="items-list" id="listView" style="display: none;">
                                <div class="list-header">
                                    <div class="list-col col-name">Nombre</div>
                                    <div class="list-col col-type">Tipo</div>
                                    <div class="list-col col-size">Tamaño</div>
                                    <div class="list-col col-date">Fecha</div>
                                    <div class="list-col col-actions">Acciones</div>
                                </div>
                                
                                <?php foreach ($items as $item): ?>
                                    <div class="list-item <?= isset($item['draggable']) ? 'draggable-item' : '' ?> <?= isset($item['draggable_target']) ? 'drop-target' : '' ?>"
                                         onclick="<?= $item['can_enter'] ?? false ? "navigateTo('{$item['path']}')" : ($item['type'] === 'document' && $canView ? "viewDocument('{$item['id']}')" : '') ?>"
                                         <?= isset($item['draggable']) ? 'draggable="true"' : '' ?>
                                         data-item-type="<?= $item['type'] ?>"
                                         data-item-id="<?= $item['id'] ?>"
                                         data-folder-id="<?= $item['type'] === 'document_folder' ? $item['id'] : '' ?>">
                                        
                                        <div class="list-col col-name">
                                            <div class="list-item-icon <?= $item['type'] === 'company' ? 'company' : ($item['type'] === 'department' ? 'folder' : ($item['type'] === 'document_folder' ? 'document-folder' : getFileTypeClass($item['original_name'] ?? ''))) ?>"
                                                 <?= isset($item['folder_color']) ? 'style="background: linear-gradient(135deg, ' . $item['folder_color'] . ', ' . adjustBrightness($item['folder_color'], -20) . ');"' : '' ?>>
                                                <?php if ($item['type'] === 'document' && isset($item['mime_type']) && strpos($item['mime_type'], 'image/') === 0): ?>
                                                    <img src="../../<?= htmlspecialchars($item['file_path']) ?>" alt="Preview" class="list-preview">
                                                <?php else: ?>
                                                    <i data-feather="<?= $item['icon'] ?>"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="list-item-name">
                                                <div class="name-text"><?= htmlspecialchars($item['name']) ?></div>
                                                <?php if (isset($searchTerm) && $searchTerm && isset($item['location'])): ?>
                                                    <div class="location-text"><?= htmlspecialchars($item['location']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="list-col col-type">
                                            <?php if ($item['type'] === 'document'): ?>
                                                <?= htmlspecialchars($item['document_type'] ?: 'Documento') ?>
                                            <?php elseif ($item['type'] === 'company'): ?>
                                                Empresa
                                            <?php elseif ($item['type'] === 'department'): ?>
                                                Departamento
                                            <?php elseif ($item['type'] === 'document_folder'): ?>
                                                Carpeta
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="list-col col-size">
                                            <?php if ($item['type'] === 'document'): ?>
                                                <?= formatBytes($item['file_size']) ?>
                                            <?php else: ?>
                                                <?= $item['document_count'] ?> elementos
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="list-col col-date">
                                            <?php if ($item['type'] === 'document'): ?>
                                                <?= formatDate($item['created_at']) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="list-col col-actions">
                                            <?php if ($item['type'] === 'document'): ?>
                                                <?php if ($canDownload): ?>
                                                    <button class="list-action-btn" onclick="event.stopPropagation(); downloadDocument('<?= $item['id'] ?>')" title="Descargar">
                                                        <i data-feather="download"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($canEdit): ?>
                                                <button class="list-action-btn cut-btn" onclick="event.stopPropagation(); cutDocument('<?= $item['id'] ?>', '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')" title="Mover">
                                                    <i data-feather="move"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($canDelete): ?>
                                                    <button class="list-action-btn delete-btn" onclick="event.stopPropagation(); deleteDocument('<?= $item['id'] ?>', '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')" title="Eliminar">
                                                        <i data-feather="trash-2"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- MENSAJE DE SIN ACCESO -->
            <div class="content-section">
                <div class="content-card">
                    <div class="content-body">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i data-feather="lock"></i>
                            </div>
                            <h3>Sin permisos de acceso</h3>
                            <p>
                                Su usuario no tiene permisos para ver documentos en el sistema. 
                                <br>Para obtener acceso, contacte al administrador del sistema.
                            </p>
                            <div class="empty-actions">
                                <a href="../../dashboard.php" class="btn-secondary">
                                    <i data-feather="home"></i>
                                    <span>Volver al Dashboard</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- MODALES -->
    <!-- Modal de Vista Previa -->
    <div id="previewModal" class="modal">
        <div class="modal-content preview-modal-content">
            <div class="modal-header">
                <h3 id="previewTitle">
                    <i data-feather="eye"></i>
                    <span>Vista Previa</span>
                </h3>
                <button class="modal-close" onclick="closePreviewModal()">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body preview-modal-body">
                <div id="previewContent"></div>
            </div>
        </div>
    </div>

    <!-- Modal de Crear Carpeta -->
    <div id="createDocumentFolderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i data-feather="folder-plus"></i>
                    <span>Crear Carpeta de Documentos</span>
                </h3>
                <button class="modal-close" onclick="closeDocumentFolderModal()">
                    <i data-feather="x"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="createDocumentFolderForm" onsubmit="submitCreateDocumentFolder(event)">
                    <div class="form-group">
                        <label class="form-label">Nombre de la carpeta</label>
                        <input type="text" name="name" class="form-control" required placeholder="Ej: Contratos, Reportes, Facturas">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Descripción de la carpeta de documentos"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Color de la carpeta</label>
                        <div class="color-options">
                            <label><input type="radio" name="folder_color" value="#e74c3c" checked><span style="background: #e74c3c;"></span></label>
                            <label><input type="radio" name="folder_color" value="#3498db"><span style="background: #3498db;"></span></label>
                            <label><input type="radio" name="folder_color" value="#2ecc71"><span style="background: #2ecc71;"></span></label>
                            <label><input type="radio" name="folder_color" value="#f39c12"><span style="background: #f39c12;"></span></label>
                            <label><input type="radio" name="folder_color" value="#9b59b6"><span style="background: #9b59b6;"></span></label>
                            <label><input type="radio" name="folder_color" value="#34495e"><span style="background: #34495e;"></span></label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Icono</label>
                        <div class="icon-options">
                            <label><input type="radio" name="folder_icon" value="folder" checked><i data-feather="folder"></i></label>
                            <label><input type="radio" name="folder_icon" value="file-text"><i data-feather="file-text"></i></label>
                            <label><input type="radio" name="folder_icon" value="archive"><i data-feather="archive"></i></label>
                            <label><input type="radio" name="folder_icon" value="briefcase"><i data-feather="briefcase"></i></label>
                            <label><input type="radio" name="folder_icon" value="inbox"><i data-feather="inbox"></i></label>
                            <label><input type="radio" name="folder_icon" value="layers"><i data-feather="layers"></i></label>
                        </div>
                    </div>

                    <input type="hidden" name="company_id" value="<?= htmlspecialchars($pathParts[0] ?? '') ?>">
                    <input type="hidden" name="department_id" value="<?= htmlspecialchars($pathParts[1] ?? '') ?>">

                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeDocumentFolderModal()">
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

    <!-- Indicador de Mover Documentos -->
    <div id="clipboardIndicator" class="clipboard-indicator" style="display: none;">
        <div class="clipboard-content">
            <i data-feather="move"></i>
            <span>Documento marcado: <strong id="clipboardName"></strong></span>
            <div class="clipboard-actions">
                <button onclick="pasteDocument()" class="clipboard-btn paste-btn">
                    <i data-feather="corner-down-left"></i>
                    Mover aquí
                </button>
                <button onclick="cancelCut()" class="clipboard-btn cancel-btn">
                    <i data-feather="x"></i>
                    Cancelar
                </button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        // Variables globales para el explorador
        const currentUserId = <?= $currentUser['id'] ?>;
        const currentUserRole = '<?= $currentUser['role'] ?>';
        const canView = <?= $canView ? 'true' : 'false' ?>;
        const canDownload = <?= $canDownload ? 'true' : 'false' ?>;
        const canCreate = <?= $canCreate ? 'true' : 'false' ?>;
        const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
        const canDelete = <?= $canDelete ? 'true' : 'false' ?>;
        const currentPath = '<?= htmlspecialchars($currentPath ?? '') ?>';
    </script>
    <script src="../../assets/js/inbox-visual.js"></script>
    <!-- JAVASCRIPT DIRECTO PARA ELIMINACIÓN - AGREGAR ANTES DE </body> -->
<script>
console.log('🔧 JavaScript directo cargado');

// Sobrescribir completamente la función deleteDocument
window.deleteDocument = function(documentId, documentName) {
    console.log('🗑️ deleteDocument DIRECTO ejecutado:', documentId, documentName);
    
    if (!documentId) {
        console.error('🗑️ ERROR: ID vacío');
        alert('Error: ID de documento no válido');
        return;
    }
    
    // Confirmaciones
    let confirmMessage = `¿Eliminar documento${documentName ? '\n\n📄 ' + documentName : ' ID: ' + documentId}?\n\n⚠️ Esta acción no se puede deshacer.`;
    
    if (!confirm(confirmMessage)) {
        console.log('🗑️ Usuario canceló');
        return;
    }
    
    if (!confirm('¿Está completamente seguro?\n\nEsta es la última oportunidad para cancelar.')) {
        console.log('🗑️ Usuario canceló segunda confirmación');
        return;
    }
    
    console.log('🗑️ Procediendo con eliminación...');
    
    // Obtener path actual por múltiples métodos
    function getPathFromMultipleSources() {
        console.log('🔍 Buscando path actual...');
        
        // Método 1: URL params
        const urlParams = new URLSearchParams(window.location.search);
        const urlPath = urlParams.get('path');
        if (urlPath) {
            console.log('✅ Path desde URL:', urlPath);
            return urlPath;
        }
        
        // Método 2: Variable global
        if (typeof currentPath !== 'undefined' && currentPath) {
            console.log('✅ Path desde variable global:', currentPath);
            return currentPath;
        }
        
        // Método 3: Breadcrumbs
        const breadcrumbs = document.querySelectorAll('.breadcrumb-item[data-breadcrumb-path]');
        if (breadcrumbs.length > 0) {
            const lastBreadcrumb = breadcrumbs[breadcrumbs.length - 1];
            const breadcrumbPath = lastBreadcrumb.dataset.breadcrumbPath;
            if (breadcrumbPath && breadcrumbPath !== '') {
                console.log('✅ Path desde breadcrumb:', breadcrumbPath);
                return breadcrumbPath;
            }
        }
        
        // Método 4: Análisis de URL manual
        const currentUrl = window.location.href;
        const match = currentUrl.match(/[?&]path=([^&]+)/);
        if (match) {
            const decodedPath = decodeURIComponent(match[1]);
            console.log('✅ Path desde regex URL:', decodedPath);
            return decodedPath;
        }
        
        console.log('❌ No se encontró path');
        return '';
    }
    
    const currentPath = getPathFromMultipleSources();
    console.log('📍 Path final detectado:', currentPath || 'VACÍO');
    
    // Crear formulario
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'delete.php';
    form.style.display = 'none';
    
    // Document ID
    const inputDoc = document.createElement('input');
    inputDoc.type = 'hidden';
    inputDoc.name = 'document_id';
    inputDoc.value = documentId;
    form.appendChild(inputDoc);
    
    // Return path
    if (currentPath) {
        const inputPath = document.createElement('input');
        inputPath.type = 'hidden';
        inputPath.name = 'return_path';
        inputPath.value = currentPath;
        form.appendChild(inputPath);
        console.log('📤 Enviando return_path:', currentPath);
    } else {
        console.log('⚠️ Sin return_path - irá al inicio');
    }
    
    // Agregar al DOM y enviar
    document.body.appendChild(form);
    
    console.log('📤 Enviando formulario POST a delete.php');
    console.log('📋 Datos del formulario:');
    console.log('  - document_id:', documentId);
    console.log('  - return_path:', currentPath || 'no enviado');
    
    // Mostrar mensaje de carga
    const loadingMsg = document.createElement('div');
    loadingMsg.id = 'deleteLoading';
    loadingMsg.style.cssText = `
        position: fixed; top: 20px; right: 20px; z-index: 10000;
        background: #ffc107; color: #000; padding: 15px 20px;
        border-radius: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        font-weight: bold;
    `;
    loadingMsg.textContent = '🗑️ Eliminando documento...';
    document.body.appendChild(loadingMsg);
    
    // Enviar formulario
    form.submit();
};

// Verificar que se sobrescribió correctamente
if (typeof window.deleteDocument === 'function') {
    console.log('✅ Función deleteDocument sobrescrita exitosamente');
} else {
    console.error('❌ Error: No se pudo sobrescribir deleteDocument');
}

// Debug de estado actual
console.log('📊 Estado del sistema:');
console.log('- URL actual:', window.location.href);
console.log('- currentPath variable:', typeof currentPath !== 'undefined' ? currentPath : 'undefined');
console.log('- Breadcrumbs con path:', document.querySelectorAll('.breadcrumb-item[data-breadcrumb-path]').length);

// Función de test para debugging
window.testDeleteFunction = function() {
    console.log('🧪 TESTING deleteDocument function...');
    
    // Simular sin eliminar realmente
    const originalConfirm = window.confirm;
    let confirmCalls = 0;
    
    window.confirm = function(message) {
        confirmCalls++;
        console.log(`📋 Confirm ${confirmCalls}: ${message}`);
        return confirmCalls <= 2; // Simular aceptar ambas confirmaciones
    };
    
    // Interceptar submit para no enviar realmente
    const originalSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function() {
        console.log('📤 FORM SUBMIT interceptado (test mode)');
        
        const formData = new FormData(this);
        console.log('📋 Datos que se enviarían:');
        for (let [key, value] of formData.entries()) {
            console.log(`  ${key}: ${value}`);
        }
        
        // Restaurar funciones
        window.confirm = originalConfirm;
        HTMLFormElement.prototype.submit = originalSubmit;
        
        console.log('✅ Test completado - Ver log arriba');
        alert('Test completado - Ver consola para detalles');
    };
    
    // Ejecutar test
    deleteDocument(999, 'TEST_DOCUMENT');
};

console.log('🛠️ JavaScript directo inicializado. Usa testDeleteFunction() para probar.');
</script>

<!-- ESTILOS PARA NOTIFICACIONES -->
<style>
#deleteLoading {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    background: #ffc107;
    color: #000;
    padding: 15px 20px;
    border-radius: 5px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    font-weight: bold;
}
</style>
</body>
</html>