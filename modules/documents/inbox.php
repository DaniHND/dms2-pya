<?php
// ===================================================================
// INICIO DEL ARCHIVO modules/documents/inbox.php - VERSIÓN CORREGIDA
// ===================================================================

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/group_permissions.php';

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

if (!$currentUser) {
    error_log("Error: getCurrentUser() retornó null");
    header('Location: ../../login.php');
    exit;
}

// ===================================================================
// FUNCIÓN getUserPermissions - MOVIDA AL INICIO PARA EVITAR ERROR
// ===================================================================
function getUserPermissions($userId)
{
    if (!$userId) {
        return [
            'permissions' => [
                'view' => false,
                'download' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
                'create_folders' => false
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

        // Verificar si es administrador
        $query = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && ($user['role'] === 'admin' || $user['role'] === 'super_admin')) {
            // ADMINISTRADORES: ACCESO TOTAL
            return [
                'permissions' => [
                    'view' => true,
                    'download' => true,
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                    'create_folders' => true
                ],
                'restrictions' => [
                    'companies' => [],
                    'departments' => [],
                    'document_types' => []
                ]
            ];
        }

        // USUARIOS NORMALES: SOLO CON GRUPOS Y PERMISO ACTIVADO
        $groupPermissions = getUserGroupPermissions($userId);

        if (!$groupPermissions['has_groups']) {
            // SIN GRUPOS = SIN ACCESO
            return [
                'permissions' => [
                    'view' => false,
                    'download' => false,
                    'create' => false,
                    'edit' => false,
                    'delete' => false,
                    'create_folders' => false
                ],
                'restrictions' => [
                    'companies' => [],
                    'departments' => [],
                    'document_types' => []
                ]
            ];
        }

        $permissions = $groupPermissions['permissions'];

        return [
            'permissions' => [
                'view' => $permissions['view_files'] ?? false,
                'download' => $permissions['download_files'] ?? false,
                'create' => $permissions['upload_files'] ?? false,
                'edit' => $permissions['edit_files'] ?? false,
                'delete' => $permissions['delete_files'] ?? false,
                'create_folders' => $permissions['create_folders'] ?? false
            ],
            'restrictions' => $groupPermissions['restrictions']
        ];
    } catch (Exception $e) {
        error_log("Error getting user permissions: " . $e->getMessage());
        return [
            'permissions' => [
                'view' => false,
                'download' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
                'create_folders' => false
            ],
            'restrictions' => [
                'companies' => [],
                'departments' => [],
                'document_types' => []
            ]
        ];
    }
}

// ===================================================================
// LÓGICA PRINCIPAL - VERIFICACIÓN DE ACCESO INMEDIATA
// ===================================================================

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Obtener permisos INMEDIATAMENTE
    $userPermissions = getUserPermissions($currentUser['id']);
    $canView = $userPermissions['permissions']['view'] ?? false;
    $canDownload = $userPermissions['permissions']['download'] ?? false;
    $canCreate = $userPermissions['permissions']['create'] ?? false;
    $canEdit = $userPermissions['permissions']['edit'] ?? false;
    $canDelete = $userPermissions['permissions']['delete'] ?? false;
    $canCreateFolders = $userPermissions['permissions']['create_folders'] ?? false;

    // Verificar acceso
    if (!$canView) {
        $items = [];
        $breadcrumbs = [['name' => 'Sin acceso', 'path' => '', 'icon' => 'lock']];
        $noAccess = true;
    } else {
        $currentPath = isset($_GET['path']) ? trim($_GET['path']) : '';
        $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

        // FILTROS
        $extensionFilter = $_GET['extension'] ?? '';
        $docTypeFilter = $_GET['doc_type'] ?? '';

        // Si hay búsqueda o filtros, ejecutar búsqueda
        if ($searchTerm || $extensionFilter || $docTypeFilter) {
            $items = searchItems($currentUser['id'], $currentUser['role'], $searchTerm, $currentPath, $extensionFilter, $docTypeFilter);
        } else {
            $items = getNavigationItems($currentUser['id'], $currentUser['role'], $currentPath);
        }

        $breadcrumbs = getBreadcrumbs($currentPath, $currentUser['id']);
        $noAccess = false;
    }

    logActivity($currentUser['id'], 'view', 'visual_explorer', null, 'Usuario navegó por el explorador visual');
} catch (Exception $e) {
    error_log("Error in visual explorer: " . $e->getMessage());
    $items = [];
    $breadcrumbs = [['name' => 'Error', 'path' => '', 'icon' => 'alert-circle']];
    $canView = $canDownload = $canCreate = $canEdit = $canDelete = $canCreateFolders = false;
    $noAccess = true;
}

// ===================================================================
// INICIALIZAR VARIABLES OBLIGATORIAMENTE - EVITAR ERRORES JS
// ===================================================================
$noAccess = $noAccess ?? false;
$canView = $canView ?? false;
$canDownload = $canDownload ?? false;
$canCreate = $canCreate ?? false;
$canEdit = $canEdit ?? false;
$canDelete = $canDelete ?? false;
$canCreateFolders = $canCreateFolders ?? false;
$currentPath = $currentPath ?? '';
$searchTerm = $searchTerm ?? '';
$extensionFilter = $extensionFilter ?? '';
$docTypeFilter = $docTypeFilter ?? '';
$items = $items ?? [];
$breadcrumbs = $breadcrumbs ?? [];

// VALIDAR PATH PARTS
$pathParts = $currentPath ? explode('/', trim($currentPath, '/')) : [];
$pathParts = array_filter($pathParts); // Remover elementos vacíos

// ===================================================================
// FUNCIÓN getNavigationItems - DESPUÉS DE LA LÓGICA PRINCIPAL
// ===================================================================
function getNavigationItems($userId, $userRole, $currentPath = '')
{
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

        if (!empty($restrictions['companies'])) {
            $placeholders = str_repeat('?,', count($restrictions['companies']) - 1) . '?';
            $whereConditions[] = "c.id IN ($placeholders)";
            $params = array_merge($params, $restrictions['companies']);
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

        // Verificar acceso a la empresa
        $hasAccess = true;
        if (!empty($restrictions['companies'])) {
            $hasAccess = in_array($companyId, $restrictions['companies']);
        }

        if (!$hasAccess) {
            return [];
        }

        // DEPARTAMENTOS CON RESTRICCIONES DE GRUPO
        $userGroupPermissions = getUserGroupPermissions($userId);

        if ($userGroupPermissions['has_groups'] && !empty($userGroupPermissions['restrictions']['departments'])) {
            // Usuario con grupos - aplicar restricciones de departamentos
            $allowedDepartments = getUserAllowedDepartments($userId, $companyId);
            $departments = [];

            foreach ($allowedDepartments as $dept) {
                $statsQuery = "
                    SELECT d.id, d.name, d.description,
                           COUNT(DISTINCT doc.id) as document_count,
                           COUNT(DISTINCT f.id) as folder_count
                    FROM departments d
                    LEFT JOIN documents doc ON d.id = doc.department_id AND doc.status = 'active'
                    LEFT JOIN document_folders f ON d.id = f.department_id AND f.is_active = 1
                    WHERE d.id = ? AND d.status = 'active'
                    GROUP BY d.id, d.name, d.description
                ";

                $statsStmt = $pdo->prepare($statsQuery);
                $statsStmt->execute([$dept['id']]);
                $deptStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

                if ($deptStats) {
                    $departments[] = $deptStats;
                }
            }
        } else {
            // Sin restricciones - mostrar todos los departamentos
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
        }

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

function getBreadcrumbs($currentPath, $userId)
{
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

function searchItems($userId, $userRole, $searchTerm, $currentPath = '', $extensionFilter = '', $docTypeFilter = '')
{
    if (empty($searchTerm) && empty($extensionFilter) && empty($docTypeFilter)) {
        return [];
    }

    $database = new Database();
    $pdo = $database->getConnection();
    $results = [];

    $userPermissions = getUserPermissions($userId);
    $restrictions = $userPermissions['restrictions'];

    $conditions = [];
    $params = [];

    // Condición de búsqueda por texto
    if (!empty($searchTerm)) {
        $conditions[] = "(doc.name LIKE ? OR doc.description LIKE ? OR doc.original_name LIKE ?)";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
    }

    // Filtro por extensión
    if (!empty($extensionFilter)) {
        $extensions = explode(',', $extensionFilter);
        $extensionConditions = [];
        foreach ($extensions as $ext) {
            $extensionConditions[] = "LOWER(doc.original_name) LIKE ?";
            $params[] = "%.{$ext}";
        }
        if (!empty($extensionConditions)) {
            $conditions[] = "(" . implode(" OR ", $extensionConditions) . ")";
        }
    }

    // Filtro por tipo de documento
    if (!empty($docTypeFilter)) {
        $conditions[] = "doc.document_type_id = ?";
        $params[] = $docTypeFilter;
    }

    // Restricciones de empresa
    $companyRestriction = '';
    if (!empty($restrictions['companies'])) {
        $placeholders = str_repeat('?,', count($restrictions['companies']) - 1) . '?';
        $companyRestriction = " AND doc.company_id IN ($placeholders)";
        $params = array_merge($params, $restrictions['companies']);
    }

    $whereClause = !empty($conditions) ? "AND (" . implode(" AND ", $conditions) . ")" : "";

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
        {$whereClause}
        {$companyRestriction}
        ORDER BY doc.name
    ";

    $stmt = $pdo->prepare($docsQuery);
    $stmt->execute($params);
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

// Funciones auxiliares
function formatBytes($bytes)
{
    if ($bytes == 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 1) . ' ' . $units[$pow];
}

function formatDate($date)
{
    return $date ? date('d/m/Y H:i', strtotime($date)) : '';
}

function getFileIcon($filename, $mimeType)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return 'image';
    if ($ext === 'pdf') return 'file-text';
    if (in_array($ext, ['doc', 'docx'])) return 'file-text';
    if (in_array($ext, ['xls', 'xlsx'])) return 'grid';
    if (in_array($ext, ['mp4', 'avi', 'mov'])) return 'video';
    return 'file';
}

function getFileTypeClass($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return 'image';
    if ($ext === 'pdf') return 'pdf';
    if (in_array($ext, ['doc', 'docx'])) return 'word';
    if (in_array($ext, ['xls', 'xlsx'])) return 'excel';
    return 'file';
}

function adjustBrightness($color, $percent)
{
    if (strlen($color) != 7) return $color;
    $red = hexdec(substr($color, 1, 2));
    $green = hexdec(substr($color, 3, 2));
    $blue = hexdec(substr($color, 5, 2));

    $red = max(0, min(255, $red + ($red * $percent / 100)));
    $green = max(0, min(255, $green + ($green * $percent / 100)));
    $blue = max(0, min(255, $blue + ($blue * $percent / 100)));

    return sprintf("#%02x%02x%02x", $red, $green, $blue);
}

if (!function_exists('logActivity')) {
    function logActivity($userId, $action, $table, $recordId, $description)
    {
        error_log("Activity: User $userId performed $action on $table: $description");
    }
}
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
                   <?php if ($noAccess): ?>
                       Su usuario no tiene permisos para ver documentos. Contacte al administrador.
                   <?php else: ?>
                       Navegue y gestione sus documentos organizados por empresas y departamentos
                   <?php endif; ?>
               </p>
           </div>

           <?php if (!$noAccess): ?>
               <!-- BREADCRUMB CON FLECHA DE REGRESO -->
               <div class="breadcrumb-section">
                   <?php if (!empty($currentPath)): ?>
                       <button class="btn-back-arrow" onclick="goBack()" title="Regresar">
                           <i data-feather="arrow-left"></i>
                       </button>
                   <?php endif; ?>

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

               <!-- TOOLBAR CON BOTÓN DE SUBIR Y CREAR CARPETA -->
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
                           <?php if ($canCreateFolders && count($pathParts) === 2 && is_numeric($pathParts[0]) && is_numeric($pathParts[1])): ?>
                               <button class="btn-create" onclick="createDocumentFolder()" type="button">
                                   <i data-feather="folder-plus"></i>
                                   <span>Nueva Carpeta</span>
                               </button>
                           <?php endif; ?>

                           <!-- SUBIR ARCHIVO -->
                           <?php if ($canCreate && count($pathParts) >= 2): ?>
                               <a href="upload.php<?= !empty($currentPath) ? '?path=' . urlencode($currentPath) : '' ?>" class="btn-secondary">
                                   <i data-feather="upload"></i>
                                   <span>Subir Archivo</span>
                               </a>
                           <?php endif; ?>

                           <!-- Botón para mostrar/ocultar filtros -->
                           <button class="btn-filter-toggle" onclick="toggleAdvancedFilters()">
                               <i data-feather="filter"></i>
                               <span>Filtros</span>
                           </button>
                       </div>

                       <div class="toolbar-right">
                           <div class="search-wrapper">
                               <i data-feather="search" class="search-icon"></i>
                               <input type="text" class="search-input" placeholder="Buscar documentos, carpetas..."
                                   value="<?= htmlspecialchars($searchTerm) ?>"
                                   onkeypress="if(event.key==='Enter') applyFiltersAuto()"
                                   oninput="handleSearchInput(this.value)">
                               <?php if ($searchTerm): ?>
                                   <button class="search-clear" onclick="clearSearch()">
                                       <i data-feather="x"></i>
                                   </button>
                               <?php endif; ?>
                           </div>
                       </div>
                   </div>
               </div>

               <!-- FILTROS AVANZADOS -->
               <div class="advanced-filters" id="advancedFilters" style="display: none;">
                   <div class="filter-row">
                       <div class="filter-group">
                           <label for="extensionFilter">Extensión</label>
                           <select id="extensionFilter" onchange="applyFiltersAuto()">
                               <option value="">Todas las extensiones</option>
                               <option value="pdf">PDF</option>
                               <option value="doc,docx">Word</option>
                               <option value="xls,xlsx">Excel</option>
                               <option value="ppt,pptx">PowerPoint</option>
                               <option value="jpg,jpeg,png,gif">Imágenes</option>
                               <option value="txt">Texto</option>
                               <option value="zip,rar">Comprimidos</option>
                           </select>
                       </div>

                       <div class="filter-group">
                           <label for="documentTypeFilter">Tipo de Documento</label>
                           <select id="documentTypeFilter" onchange="applyFiltersAuto()">
                               <option value="">Todos los tipos</option>
                               <?php
                               try {
                                   $typesQuery = "SELECT id, name FROM document_types WHERE status = 'active' ORDER BY name";
                                   $typesStmt = $pdo->prepare($typesQuery);
                                   $typesStmt->execute();
                                   $documentTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);
                                   foreach ($documentTypes as $type):
                               ?>
                                       <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                               <?php endforeach;
                               } catch (Exception $e) {
                                   // Silenciar errores de tipos
                               } ?>
                           </select>
                       </div>
                   </div>
               </div>

               <!-- RESULTADOS DE BÚSQUEDA -->
               <?php if ($searchTerm || $extensionFilter || $docTypeFilter): ?>
                   <div class="search-results-info">
                       <div class="search-info-card">
                           <i data-feather="search"></i>
                           <span>Mostrando <?= count($items) ?> resultado<?= count($items) !== 1 ? 's' : '' ?>
                               <?php if ($searchTerm): ?>para "<strong><?= htmlspecialchars($searchTerm) ?></strong>"<?php endif; ?>
                               <?php if ($extensionFilter || $docTypeFilter): ?>con filtros aplicados<?php endif; ?>
                           </span>
                       </div>
                   </div>
               <?php endif; ?>
               
               <!-- CONTENIDO PRINCIPAL -->
               <div class="content-section">
                   <div class="content-card">
                       <div class="content-header">
                           <h3>
                               <?php if (count($items) === 0): ?>
                                   <?= ($searchTerm || $extensionFilter || $docTypeFilter) ? 'Sin resultados' : 'Carpeta vacía' ?>
                               <?php else: ?>
                                   <?= count($items) ?> elemento<?= count($items) !== 1 ? 's' : '' ?> encontrado<?= count($items) !== 1 ? 's' : '' ?>
                               <?php endif; ?>
                           </h3>
                       </div>

                       <div class="content-body">
                           <?php if (count($items) === 0): ?>
                               <!-- ESTADO VACÍO -->
                               <div class="empty-state">
                                   <div class="empty-icon">
                                       <i data-feather="<?= ($searchTerm || $extensionFilter || $docTypeFilter) ? 'search' : 'folder' ?>"></i>
                                   </div>
                                   <h3><?= ($searchTerm || $extensionFilter || $docTypeFilter) ? 'Sin resultados' : 'Carpeta vacía' ?></h3>
                                   <p>
                                       <?php if ($searchTerm || $extensionFilter || $docTypeFilter): ?>
                                           No se encontraron elementos que coincidan con los filtros aplicados. Intente con otros términos o filtros.
                                       <?php else: ?>
                                           No hay elementos para mostrar en esta ubicación.
                                           <?php if ($canCreate || $canCreateFolders): ?>
                                               Puede crear una nueva carpeta o subir archivos para comenzar.
                                           <?php else: ?>
                                               No tiene permisos para crear contenido.
                                           <?php endif; ?>
                                       <?php endif; ?>
                                   </p>

                                   <?php if (($canCreate || $canCreateFolders) && !($searchTerm || $extensionFilter || $docTypeFilter)): ?>
                                       <div class="empty-actions">
                                           <?php if ($canCreateFolders && count($pathParts) === 2 && is_numeric($pathParts[0]) && is_numeric($pathParts[1])): ?>
                                               <button class="btn-create" onclick="createDocumentFolder()" type="button">
                                                   <i data-feather="folder-plus"></i>
                                                   <span>Crear Carpeta</span>
                                               </button>
                                           <?php endif; ?>
                                           <?php if ($canCreate && count($pathParts) >= 2): ?>
                                               <a href="<?= !empty($currentPath) ? 'upload.php?path=' . urlencode($currentPath) : 'upload.php' ?>" class="btn-secondary">
                                                   <i data-feather="upload"></i>
                                                   <span>Subir Archivo</span>
                                               </a>
                                           <?php endif; ?>
                                       </div>
                                   <?php endif; ?>
                               </div>
                           <?php else: ?>
                               <!-- VISTA EN CUADRÍCULA -->
                               <div class="items-grid" id="gridView">
                                   <?php foreach ($items as $item): ?>
                                       <div class="explorer-item <?= isset($item['draggable']) ? 'draggable-item' : '' ?>
                                        <?= isset($item['draggable_target']) ? 'drop-target' : '' ?>"
                                           onclick="<?= $item['can_enter'] ?? false ? "navigateTo('{$item['path']}')" : ($item['type'] === 'document' && $canView ? "viewDocument('{$item['id']}')" : 'console.log(\'Item no navegable\')') ?>"
                                           style="<?= $item['type'] === 'document' && $canView ? 'cursor: pointer;' : '' ?>"
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

                                                   <?php if (($searchTerm || $extensionFilter || $docTypeFilter) && isset($item['location'])): ?>
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
                                           onclick="<?= $item['can_enter'] ?? false ? "navigateTo('{$item['path']}')" : ($item['type'] === 'document' && $canView ? "viewDocument('{$item['id']}')" : 'console.log(\'Item no navegable\')') ?>"
                                           style="<?= $item['type'] === 'document' && $canView ? 'cursor: pointer;' : '' ?>"
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
                                                   <?php if (($searchTerm || $extensionFilter || $docTypeFilter) && isset($item['location'])): ?>
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
                                   <br>Para obtener acceso, debe estar asignado a un grupo con el permiso "Ver Archivos" activado.
                                   <br>Contacte al administrador del sistema para solicitar acceso.
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
   <!-- Modal de Crear Carpeta -->
   <div id="createDocumentFolderModal" class="modal" style="display: none;">
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


   <!-- ESTILOS ADICIONALES -->
   <style>
       /* Botón de regreso solo flecha */
       .btn-back-arrow {
           background: var(--bg-secondary);
           border: 1px solid var(--border-color);
           color: var(--text-primary);
           padding: 0.75rem;
           border-radius: 50%;
           cursor: pointer;
           display: flex;
           align-items: center;
           justify-content: center;
           transition: all 0.2s;
           width: 40px;
           height: 40px;
           margin-right: 1rem;
       }

       .btn-back-arrow:hover {
           background: var(--primary-color);
           color: white;
           border-color: var(--primary-color);
           transform: translateX(-2px);
       }

       .btn-back-arrow i {
           width: 18px;
           height: 18px;
       }

       /* Sección de breadcrumb con botón */
       .breadcrumb-section {
           display: flex;
           align-items: center;
           margin-bottom: var(--spacing-8);
       }

       /* Filtros avanzados */
       .advanced-filters {
           background: var(--bg-secondary);
           border: 1px solid var(--border-color);
           border-radius: 8px;
           padding: 1rem;
           margin: 1rem 0;
       }

       .filter-row {
           display: flex;
           gap: 1rem;
           align-items: end;
           flex-wrap: wrap;
       }

       .filter-group {
           display: flex;
           flex-direction: column;
           gap: 0.25rem;
           min-width: 160px
           }

       .filter-group label {
           font-size: 0.875rem;
           font-weight: 500;
           color: var(--text-secondary);
       }

       .filter-group select {
           padding: 0.5rem;
           border: 1px solid var(--border-color);
           border-radius: 4px;
           background: white;
           transition: border-color 0.2s;
       }

       .filter-group select:focus {
           outline: none;
           border-color: var(--primary-color);
           box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
       }

       /* Botón de filtros */
       .btn-filter-toggle {
           background: var(--bg-secondary);
           color: var(--text-primary);
           border: 1px solid var(--border-color);
           padding: 0.5rem 0.75rem;
           border-radius: 4px;
           cursor: pointer;
           display: flex;
           align-items: center;
           gap: 0.5rem;
           font-size: 0.875rem;
           transition: all 0.2s;
       }

       .btn-filter-toggle:hover {
           background: var(--primary-light);
           border-color: var(--primary-color);
       }

       .btn-filter-toggle.active {
           background: var(--primary-color);
           color: white;
           border-color: var(--primary-color);
       }

       /* Modal de crear carpeta - ESTILOS CORREGIDOS */
       .modal {
           position: fixed;
           top: 0;
           left: 0;
           width: 100%;
           height: 100%;
           background: rgba(0, 0, 0, 0.5);
           z-index: 1000;
           display: flex;
           align-items: center;
           justify-content: center;
       }

       .modal-content {
           background: white;
           border-radius: 8px;
           max-width: 500px;
           width: 90%;
           max-height: 90vh;
           overflow-y: auto;
       }

       .modal-header {
           padding: 1rem 1.5rem;
           border-bottom: 1px solid var(--border-color);
           display: flex;
           align-items: center;
           justify-content: space-between;
       }

       .modal-header h3 {
           margin: 0;
           display: flex;
           align-items: center;
           gap: 0.5rem;
       }

       .modal-close {
           background: none;
           border: none;
           font-size: 1.5rem;
           cursor: pointer;
           color: var(--text-secondary);
       }

       .modal-close:hover {
           color: var(--text-primary);
       }

       .modal-body {
           padding: 1.5rem;
       }

       .form-group {
           margin-bottom: 1rem;
       }

       .form-label {
           display: block;
           margin-bottom: 0.5rem;
           font-weight: 500;
           color: var(--text-primary);
       }

       .form-control {
           width: 100%;
           padding: 0.75rem;
           border: 1px solid var(--border-color);
           border-radius: 4px;
           font-size: 1rem;
       }

       .form-control:focus {
           outline: none;
           border-color: var(--primary-color);
           box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
       }

       /* Opciones de color */
       .color-options {
           display: flex;
           gap: 0.5rem;
           flex-wrap: wrap;
       }

       .color-options label {
           cursor: pointer;
           display: flex;
           align-items: center;
       }

       .color-options input[type="radio"] {
           display: none;
       }

       .color-options span {
           width: 30px;
           height: 30px;
           border-radius: 50%;
           border: 3px solid transparent;
           transition: border-color 0.2s;
       }

       .color-options input[type="radio"]:checked+span {
           border-color: var(--text-primary);
       }

       /* Opciones de icono */
       .icon-options {
           display: flex;
           gap: 0.5rem;
           flex-wrap: wrap;
       }

       .icon-options label {
           cursor: pointer;
           padding: 0.5rem;
           border: 2px solid var(--border-color);
           border-radius: 4px;
           display: flex;
           align-items: center;
           justify-content: center;
           transition: border-color 0.2s;
       }

       .icon-options input[type="radio"] {
           display: none;
       }

       .icon-options input[type="radio"]:checked+i {
           color: var(--primary-color);
       }

       .icon-options label:hover {
           border-color: var(--primary-color);
       }

       .icon-options input[type="radio"]:checked+i {
           color: var(--primary-color);
       }

       .icon-options label:has(input[type="radio"]:checked) {
           border-color: var(--primary-color);
           background: rgba(var(--primary-rgb), 0.1);
       }

       /* Acciones del modal */
       .modal-actions {
           display: flex;
           gap: 1rem;
           justify-content: flex-end;
           margin-top: 1.5rem;
           padding-top: 1rem;
           border-top: 1px solid var(--border-color);
       }

       /* Responsive */
       @media (max-width: 768px) {
           .breadcrumb-section {
               flex-direction: column;
               align-items: stretch;
               gap: 0.5rem;
           }

           .btn-back-arrow {
               align-self: flex-start;
               margin-right: 0;
               margin-bottom: 0.5rem;
           }

           .filter-row {
               flex-direction: column;
               align-items: stretch;
           }

           .filter-group {
               min-width: auto;
           }

           .modal-content {
               width: 95%;
               margin: 1rem;
           }

           .modal-actions {
               flex-direction: column;
           }
       }
   </style>

   <!-- JAVASCRIPT -->
   <script>
       // ===================================================================
       // VARIABLES GLOBALES - DECLARADAS CORRECTAMENTE PARA SCOPE GLOBAL
       // ===================================================================

       // Declarar variables globales primero
       let currentUserId, currentUserRole, canView, canDownload, canCreate, canEdit, canDelete, canCreateFolders, currentPath, pathParts;

       console.log('🚀 INBOX.PHP - SCRIPT INICIADO');

       try {
           // Asignar valores desde PHP a variables globales
           currentUserId = parseInt('<?= $currentUser['id'] ?? 0 ?>') || 0;
           currentUserRole = '<?= addslashes($currentUser['role'] ?? 'guest') ?>';
           canView = <?= $canView ? 'true' : 'false' ?>;
           canDownload = <?= $canDownload ? 'true' : 'false' ?>;
           canCreate = <?= $canCreate ? 'true' : 'false' ?>;
           canEdit = <?= $canEdit ? 'true' : 'false' ?>;
           canDelete = <?= $canDelete ? 'true' : 'false' ?>;
           canCreateFolders = <?= $canCreateFolders ? 'true' : 'false' ?>;
           currentPath = '<?= addslashes($currentPath ?? '') ?>';
           pathParts = <?= json_encode($pathParts ?? []) ?>;

           console.log('📊 Variables globales asignadas:', {
               currentUserId: currentUserId,
               currentUserRole: currentUserRole,
               canView: canView,
               canDownload: canDownload,
               canCreate: canCreate,
               canCreateFolders: canCreateFolders,
               currentPath: currentPath,
               pathParts: pathParts
           });

       } catch (error) {
           console.error('❌ ERROR AL ASIGNAR VARIABLES:', error);

           // Valores por defecto en caso de error
           currentUserId = 1;
           currentUserRole = 'admin';
           canView = true;
           canDownload = true;
           canCreate = true;
           canEdit = true;
           canDelete = true;
           canCreateFolders = true;
           currentPath = '';
           pathParts = [];
       }

       // Variables para debounce y filtros
       let searchTimeout;
       let filterTimeout;
       let searchDebounceTime = 1000;

       // ===================================================================
       // FUNCIÓN CREAR CARPETA
       // ===================================================================
       function createDocumentFolder() {
           console.log('🔥 createDocumentFolder() EJECUTADA');
           console.log('🔍 Verificando permisos...');
           console.log('  canCreateFolders:', canCreateFolders, typeof canCreateFolders);

           // Verificar permisos
           if (!canCreateFolders) {
               console.error('❌ Sin permisos para crear carpetas');
               alert('❌ No tienes permisos para crear carpetas');
               return;
           }

           console.log('✅ Permisos OK');

           // Buscar modal en el DOM
           console.log('🔍 Buscando modal en DOM...');
           const modal = document.getElementById('createDocumentFolderModal');

           if (!modal) {
               console.error('❌ Modal createDocumentFolderModal no encontrado en DOM');
               alert('❌ Error: Modal no encontrado. Revisa que el HTML esté completo.');
               return;
           }

           console.log('✅ Modal encontrado');
           console.log('📦 Modal actual display:', getComputedStyle(modal).display);

           // Abrir modal
           console.log('🚀 Abriendo modal...');
           modal.style.display = 'flex';
           modal.style.visibility = 'visible';
           modal.style.opacity = '1';

           console.log('📦 Modal después de abrir:', {
               display: modal.style.display,
               visibility: modal.style.visibility,
               opacity: modal.style.opacity
           });

           // Enfocar primer input después de un momento
           setTimeout(() => {
               const nameInput = modal.querySelector('input[name="name"]');
               if (nameInput) {
                   nameInput.focus();
                   console.log('✅ Input enfocado');
               } else {
                   console.warn('⚠️ Input name no encontrado para enfocar');
               }
           }, 100);

           console.log('✅ Modal abierto exitosamente');
       }

       // ===================================================================
       // CERRAR MODAL
       // ===================================================================
       function closeDocumentFolderModal() {
           console.log('🔥 closeDocumentFolderModal() EJECUTADA');
           const modal = document.getElementById('createDocumentFolderModal');
           if (modal) {
               modal.style.display = 'none';
               const form = document.getElementById('createDocumentFolderForm');
               if (form) {
                   form.reset();
                   console.log('✅ Formulario reseteado');
               }
               console.log('✅ Modal cerrado');
           } else {
               console.error('❌ Modal no encontrado para cerrar');
           }
       }

       // ===================================================================
       // SUBMIT FORMULARIO
       // ===================================================================
       function submitCreateDocumentFolder(event) {
           event.preventDefault();
           console.log('🔥 submitCreateDocumentFolder() EJECUTADA');

           const form = event.target;
           const formData = new FormData(form);
           const submitBtn = form.querySelector('button[type="submit"]');

           // Log de datos del formulario
           console.log('📋 Datos del formulario:');
           for (let [key, value] of formData.entries()) {
               console.log(`  ${key}: ${value}`);
           }

           // Deshabilitar botón y mostrar loading
           const originalText = submitBtn.innerHTML;
           submitBtn.disabled = true;
           submitBtn.innerHTML = '<i data-feather="loader"></i><span>Creando...</span>';

           console.log('🌐 Enviando solicitud a create_folder.php...');

           fetch('create_folder.php', {
                   method: 'POST',
                   body: formData
               })
               .then(response => {
                   console.log('📡 Respuesta recibida:', response.status, response.statusText);
                   return response.text(); // Usar text() primero para debugging
               })
               .then(text => {
                   console.log('📄 Respuesta cruda del servidor:', text);

                   try {
                       const data = JSON.parse(text);
                       console.log('✅ JSON parseado exitosamente:', data);

                       if (data.success) {
                           console.log('🎉 Carpeta creada exitosamente');
                           alert('✅ Carpeta creada exitosamente');
                           closeDocumentFolderModal();

                           // Recargar página después de un momento
                           setTimeout(() => {
                               console.log('🔄 Recargando página...');
                               window.location.reload();
                           }, 1000);
                       } else {
                           console.error('❌ Error del servidor:', data.message);
                           alert('❌ Error al crear carpeta: ' + (data.message || 'Error desconocido'));
                       }
                   } catch (parseError) {
                       console.error('❌ Error al parsear JSON:', parseError);
                       console.error('❌ Respuesta que no se pudo parsear:', text);
                       alert('❌ Error de servidor: Respuesta no válida');
                   }
               })
               .catch(error => {
                   console.error('💥 Error en la solicitud:', error);
                   alert('❌ Error de conexión: ' + error.message);
               })
               .finally(() => {
                   // Restaurar botón
                   submitBtn.disabled = false;
                   submitBtn.innerHTML = originalText;
                   console.log('🔄 Botón restaurado');
               });
       }

       // ===================================================================
       // SISTEMA DE VISTA DE DOCUMENTOS - SOLO MODAL DINÁMICO
       // ===================================================================
       function viewDocument(documentId) {
           console.log('👁️ viewDocument() ejecutada para documento:', documentId);

           if (!canView) {
               console.error('❌ Sin permisos para ver documentos');
               alert('No tienes permisos para ver documentos');
               return;
           }

           if (!documentId) {
               console.error('❌ ID de documento inválido');
               alert('Error: ID de documento no válido');
               return;
           }

           console.log('✅ Abriendo documento en modal:', documentId);
           showDocumentModal(documentId);
       }

       function showDocumentModal(documentId) {
           console.log('🔥 showDocumentModal() ejecutada');

           // Crear modal si no existe
           ensureDocumentModal();

           const modal = document.getElementById('documentModal');
           const title = document.getElementById('modalTitle');
           const content = document.getElementById('modalContent');

           // Mostrar loading
           title.innerHTML = `
               <span>🔄 Cargando documento...</span>
               <button onclick="closeDocumentModal()" 
                       style="background:none;border:none;font-size:24px;cursor:pointer;color:#666;padding:5px;">
                   ✕
               </button>
           `;
           content.innerHTML = '<div style="text-align: center; padding: 3rem; color: #64748b;">Cargando documento...</div>';

           // Mostrar modal
           modal.style.display = 'flex';
           document.body.style.overflow = 'hidden';

           try {
               // Título con botón cerrar
               title.innerHTML = `
                   <span>📄 Vista de Documento</span>
                   <button onclick="closeDocumentModal()" 
                           style="background:none;border:none;font-size:24px;cursor:pointer;color:#666;padding:5px;">
                       ✕
                   </button>
               `;

               // Mostrar documento en iframe - CONECTA CON view.php
               content.innerHTML = `
                   <iframe src="view.php?id=${documentId}" 
                           style="width: 100%; height: 100%; border: none; border-radius: 8px; background: white;"
                           onload="console.log('✅ Documento cargado en iframe')"
                           onerror="console.error('❌ Error cargando documento en iframe')">
                       <p>Tu navegador no soporta iframes. <a href="view.php?id=${documentId}" target="_blank">Abrir documento</a></p>
                   </iframe>
               `;

               console.log('✅ Modal de documento abierto con URL: view.php?id=' + documentId);

           } catch (error) {
               console.error('❌ Error al abrir documento:', error);
               content.innerHTML = `
                   <div style="text-align: center; padding: 2rem; color: #ef4444;">
                       <h3>❌ Error al cargar documento</h3>
                       <p>${error.message}</p>
                       <div style="margin: 1rem 0;">
                           <a href="view.php?id=${documentId}" target="_blank" 
                              style="background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;">
                               Abrir en nueva ventana
                           </a>
                       </div>
                       <button onclick="closeDocumentModal()" 
                               style="background: #6b7280; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer;">
                           Cerrar
                       </button>
                   </div>
               `;
           }
       }

       function ensureDocumentModal() {
           if (document.getElementById('documentModal')) {
               console.log('📦 Modal de documento ya existe');
               return;
           }

           console.log('🏗️ Creando modal de documento...');

           const modal = document.createElement('div');
           modal.id = 'documentModal';
           modal.style.cssText = `
               display: none;
               position: fixed;
               top: 0;
               left: 0;
               width: 100%;
               height: 100%;
               background: rgba(0, 0, 0, 0.8);
               z-index: 10000;
               align-items: center;
               justify-content: center;
               padding: 20px;
               box-sizing: border-box;
           `;

           modal.innerHTML = `
               <div style="
                   background: white; 
                   border-radius: 12px; 
                   width: 95vw; 
                   height: 95vh; 
                   max-width: 1400px; 
                   overflow: hidden; 
                   box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); 
                   display: flex; 
                   flex-direction: column;
               ">
                   <div style="
                       padding: 15px 20px; 
                       border-bottom: 1px solid #e5e7eb; 
                       display: flex; 
                       justify-content: space-between; 
                       align-items: center; 
                       flex-shrink: 0;
                       background: #f8f9fa;
                   ">
                       <h3 id="modalTitle" style="
                           margin: 0; 
                           flex: 1; 
                           display: flex; 
                           align-items: center; 
                           justify-content: space-between; 
                           font-size: 18px;
                           color: #2c3e50;
                       ">Vista de Documento</h3>
                   </div>
                   <div style="
                       flex: 1; 
                       overflow: hidden;
                       background: #ffffff;
                   ">
                       <div id="modalContent" style="
                           height: 100%;
                           width: 100%;
                       "></div>
                   </div>
               </div>
           `;

           document.body.appendChild(modal);

           // Cerrar al hacer click fuera del contenido del modal
           modal.addEventListener('click', (e) => {
               if (e.target === modal) {
                   closeDocumentModal();
               }
           });

           console.log('✅ Modal de documento creado con diseño optimizado');
       }

       function closeDocumentModal() {
           console.log('🔥 closeDocumentModal() ejecutada');
           const modal = document.getElementById('documentModal');
           if (modal) {
               modal.style.display = 'none';
               document.body.style.overflow = '';
               console.log('✅ Modal de documento cerrado');
           }
       }

       // ===================================================================
       // FUNCIÓN DE DESCARGA DE DOCUMENTOS
       // ===================================================================

       function downloadDocument(documentId) {
           console.log('🔍 downloadDocument() ejecutada para:', documentId);

           if (!documentId) {
               console.error('❌ ID de documento no válido para descarga');
               alert('Error: ID de documento no válido');
               return;
           }

           if (!canDownload) {
               console.error('❌ Sin permisos de descarga. canDownload =', canDownload);
               alert('No tienes permisos para descargar documentos. Contacta al administrador.');
               return;
           }

           console.log('✅ Permisos OK, iniciando descarga para documento:', documentId);

           // Crear formulario POST para descarga
           const form = document.createElement('form');
           form.method = 'POST';
           form.action = 'download.php';
           form.style.display = 'none';

           const input = document.createElement('input');
           input.type = 'hidden';
           input.name = 'document_id';
           input.value = documentId;
           form.appendChild(input);

           console.log('📋 Formulario de descarga creado:', {
               action: form.action,
               method: form.method,
               documentId: input.value
           });

           document.body.appendChild(form);
           form.submit();

           // Limpiar después de un momento
           setTimeout(() => {
               if (document.body.contains(form)) {
                   document.body.removeChild(form);
               }
           }, 1000);

           console.log('✅ Formulario de descarga enviado');
       }

       // ===================================================================
       // FUNCIONES AUXILIARES
       // ===================================================================

       // Función de búsqueda con debounce mejorado
       function handleSearchInput(value) {
           clearTimeout(searchTimeout);

           if (value.length === 0) {
               searchTimeout = setTimeout(() => {
                   applyFiltersAuto();
               }, 100);
           } else if (value.length >= 2) {
               searchTimeout = setTimeout(() => {
                   applyFiltersAuto();
               }, searchDebounceTime);
           }
       }

       // Aplicar filtros automáticamente
       function applyFiltersAuto() {
           clearTimeout(filterTimeout);

           filterTimeout = setTimeout(() => {
               const url = new URL(window.location);

               const extension = document.getElementById('extensionFilter')?.value || '';
               const docType = document.getElementById('documentTypeFilter')?.value || '';
               const searchTerm = document.querySelector('.search-input')?.value || '';

               url.searchParams.delete('extension');
               url.searchParams.delete('doc_type');
               url.searchParams.delete('search');

               if (searchTerm.trim()) url.searchParams.set('search', searchTerm);
               if (extension) url.searchParams.set('extension', extension);
               if (docType) url.searchParams.set('doc_type', docType);

               window.location.href = url.toString();
           }, 300);
       }

       // Limpiar búsqueda
       function clearSearch() {
           document.querySelector('.search-input').value = '';
           applyFiltersAuto();
       }

       // Toggle filtros avanzados
       function toggleAdvancedFilters() {
           const filters = document.getElementById('advancedFilters');
           const button = event.target.closest('.btn-filter-toggle');

           if (filters.style.display === 'none' || !filters.style.display) {
               filters.style.display = 'block';
               button.classList.add('active');
           } else {
               filters.style.display = 'none';
               button.classList.remove('active');
           }
       }

       // Navegación hacia atrás
       function goBack() {
           const currentPath = '<?= addslashes($currentPath) ?>';
           const pathParts = currentPath.split('/').filter(part => part);

           if (pathParts.length > 0) {
               pathParts.pop();
               const newPath = pathParts.join('/');
               window.location.href = '?path=' + encodeURIComponent(newPath);
           } else {
               window.location.href = '?';
           }
       }

       // Funciones de navegación
       function navigateTo(path) {
           if (path) {
               window.location.href = '?path=' + encodeURIComponent(path);
           } else {
               window.location.href = '?';
           }
       }

       function changeView(viewType) {
           console.log('🔄 Cambiando vista a:', viewType);
           const gridView = document.getElementById('gridView');
           const listView = document.getElementById('listView');
           const buttons = document.querySelectorAll('.view-btn');

           buttons.forEach(btn => btn.classList.remove('active'));

           if (viewType === 'grid') {
               if (gridView) gridView.style.display = 'grid';
               if (listView) listView.style.display = 'none';
               const gridBtn = document.querySelector('[data-view="grid"]');
               if (gridBtn) gridBtn.classList.add('active');
           } else {
               if (gridView) gridView.style.display = 'none';
               if (listView) listView.style.display = 'block';
               const listBtn = document.querySelector('[data-view="list"]');
               if (listBtn) listBtn.classList.add('active');
           }
           console.log('✅ Vista cambiada a:', viewType);
       }

       // Variables para cortar/pegar documentos
       var clipboardDocument = null;

       function cutDocument(documentId, documentName) {
           console.log('✂️ cutDocument() ejecutada:', documentId, documentName);
           clipboardDocument = {
               id: documentId,
               name: documentName
           };

           const indicator = document.getElementById('clipboardIndicator');
           const nameSpan = document.getElementById('clipboardName');

           if (nameSpan) nameSpan.textContent = documentName;
           if (indicator) indicator.style.display = 'block';

           console.log('✅ Documento marcado para mover:', documentName);
       }

async function pasteDocument() {
    if (!clipboardDocument) {
        alert('No hay documento marcado para mover');
        return;
    }

    console.log('📋 Intentando pegar documento:', clipboardDocument.name);
    const currentPathForMove = currentPath || '';
    console.log('📍 Path actual para mover:', currentPathForMove);

    try {
        console.log('🌐 Enviando datos:', {
            document_id: parseInt(clipboardDocument.id),
            target_path: currentPathForMove
        });

        const response = await fetch('move_document.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                document_id: parseInt(clipboardDocument.id),
                target_path: currentPathForMove
            })
        });

        console.log('📡 Response status:', response.status);
        
        // Obtener texto crudo primero para debug
        const responseText = await response.text();
        console.log('📄 Response text:', responseText);

        // Intentar parsear JSON
        let result;
        try {
            result = JSON.parse(responseText);
            console.log('✅ JSON parseado:', result);
        } catch (parseError) {
            console.error('❌ Error parseando JSON:', parseError);
            console.error('❌ Respuesta cruda:', responseText);
            alert('❌ Error del servidor: Respuesta no válida\n\n' + responseText.substring(0, 200));
            return;
        }

        if (result.success) {
            alert('✅ Documento movido exitosamente: ' + clipboardDocument.name);
            
            // Limpiar clipboard
            clipboardDocument = null;
            const indicator = document.getElementById('clipboardIndicator');
            if (indicator) indicator.style.display = 'none';
            
            // Recargar página para ver cambios
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            alert('❌ Error al mover documento: ' + result.message);
        }
    } catch (error) {
        console.error('❌ Error de conexión:', error);
        alert('❌ Error de conexión: ' + error.message);
    }
}
       // ===================================================================
       // FUNCIÓN DE ELIMINACIÓN DE DOCUMENTOS
       // ===================================================================

       window.deleteDocument = function(documentId, documentName) {
           console.log('🗑️ deleteDocument() ejecutada:', documentId, documentName);

           if (!documentId) {
               alert('Error: ID de documento no válido');
               return;
           }

           if (!canDelete) {
               alert('No tienes permisos para eliminar documentos');
               return;
           }

           let confirmMessage = 'Eliminar documento' + (documentName ? '\n\n📄 ' + documentName : ' ID: ' + documentId) + '?\n\n⚠️ Esta acción no se puede deshacer.';

           if (!confirm(confirmMessage)) {
               return;
           }

           if (!confirm('¿Está completamente seguro?\n\nEsta es la última oportunidad para cancelar.')) {
               return;
           }

           // Obtener path actual
           function getCurrentPath() {
               const urlParams = new URLSearchParams(window.location.search);
               const urlPath = urlParams.get('path');
               if (urlPath) return urlPath;

               if (typeof currentPath !== 'undefined' && currentPath) {
                   return currentPath;
               }

               const breadcrumbs = document.querySelectorAll('.breadcrumb-item[data-breadcrumb-path]');
               if (breadcrumbs.length > 0) {
                   const lastBreadcrumb = breadcrumbs[breadcrumbs.length - 1];
                   return lastBreadcrumb.dataset.breadcrumbPath || '';
               }

               return '';
           }

           const currentPath = getCurrentPath();

           // Crear formulario
           const form = document.createElement('form');
           form.method = 'POST';
           form.action = 'delete.php';
           form.style.display = 'none';

           const inputDoc = document.createElement('input');
           inputDoc.type = 'hidden';
           inputDoc.name = 'document_id';
           inputDoc.value = documentId;
           form.appendChild(inputDoc);

           if (currentPath) {
               const inputPath = document.createElement('input');
               inputPath.type = 'hidden';
               inputPath.name = 'return_path';
               inputPath.value = currentPath;
               form.appendChild(inputPath);
           }

           document.body.appendChild(form);
           form.submit();

           console.log('✅ Formulario de eliminación enviado');
       };

       // ===================================================================
       // INICIALIZACIÓN
       // ===================================================================

       // Inicializar cuando DOM esté listo
       document.addEventListener('DOMContentLoaded', function() {
           console.log('🚀 DOM LOADED');

           // DEBUG: Verificar que el botón existe
           const createBtn = document.querySelector('button[onclick="createDocumentFolder()"]');
           console.log('📋 Botón crear carpeta encontrado:', createBtn ? 'SÍ' : 'NO');

           if (createBtn) {
               
            


            console.log('  - Texto del botón:', createBtn.textContent.trim());
               console.log('  - Onclick atributo:', createBtn.getAttribute('onclick'));
               console.log('  - Disabled:', createBtn.disabled);

               // AGREGAR EVENT LISTENER ADICIONAL COMO BACKUP
               createBtn.addEventListener('click', function(e) {
                   console.log('🔥 Event listener ejecutado como backup');
                   e.preventDefault();
                   createDocumentFolder();
               });

               console.log('✅ Event listener de backup agregado');
           }

           // Verificar modal
           const modal = document.getElementById('createDocumentFolderModal');
           console.log('📦 Modal encontrado:', modal ? 'SÍ' : 'NO');

           const urlParams = new URLSearchParams(window.location.search);
           const extension = urlParams.get('extension');
           const docType = urlParams.get('doc_type');

           if (extension) {
               const extensionSelect = document.getElementById('extensionFilter');
               if (extensionSelect) extensionSelect.value = extension;
           }

           if (docType) {
               const docTypeSelect = document.getElementById('documentTypeFilter');
               if (docTypeSelect) docTypeSelect.value = docType;
           }

           // Mostrar filtros si hay alguno activo
           if (extension || docType) {
               const filters = document.getElementById('advancedFilters');
               if (filters) {
                   filters.style.display = 'block';
                   document.querySelector('.btn-filter-toggle')?.classList.add('active');
               }
           }

           // Cerrar modal al hacer click fuera
           document.addEventListener('click', function(event) {
               // Cerrar modal de crear carpeta
               const folderModal = document.getElementById('createDocumentFolderModal');
               if (event.target === folderModal) {
                   closeDocumentFolderModal();
               }

               // Cerrar modal de documento
               const documentModal = document.getElementById('documentModal');
               if (event.target === documentModal) {
                   closeDocumentModal();
               }
           });

           // Cerrar modales con tecla Escape
           document.addEventListener('keydown', function(event) {
               if (event.key === 'Escape') {
                   // Cerrar modal de crear carpeta
                   const folderModal = document.getElementById('createDocumentFolderModal');
                   if (folderModal && folderModal.style.display === 'flex') {
                       closeDocumentFolderModal();
                   }

                   // Cerrar modal de documento
                   closeDocumentModal();
               }
           });

           // Inicializar feather icons
           feather.replace();
           console.log('✅ Feather icons inicializados');
       });

       // ===================================================================
       // FUNCIONES AUXILIARES FINALES
       // ===================================================================

       function showSettings() {
           alert('Configuración estará disponible próximamente');
       }

       function toggleSidebar() {
           console.log('Toggle sidebar ejecutado');
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

       // Actualizar tiempo cada minuto
       updateTime();
       setInterval(updateTime, 60000);

       // FIX RÁPIDO DE ICONOS
       console.log('🔧 Aplicando fix rápido de iconos...');

       // Función para cargar y reinicializar iconos
       function fixFeatherIcons() {
           if (typeof feather === 'undefined') {
               console.log('📥 Cargando Feather Icons...');
               const script = document.createElement('script');
               script.src = 'https://unpkg.com/feather-icons';
               script.onload = function() {
                   console.log('✅ Feather cargado, inicializando...');
                   feather.replace();
                   setupIconRefresh();
               };
               document.head.appendChild(script);
           } else {
               console.log('🎨 Inicializando iconos existentes...');
               feather.replace();
               setupIconRefresh();
           }
       }

       // Configurar refresh automático
       function setupIconRefresh() {
           // Refrescar iconos cada vez que cambie el DOM
           const observer = new MutationObserver(() => {
               if (typeof feather !== 'undefined') {
                   feather.replace();
               }
           });

           observer.observe(document.body, {
               childList: true,
               subtree: true
           });
       }
       // Activar drag & drop en carpetas
document.addEventListener('DOMContentLoaded', function() {
    // Hacer documentos arrastrables
    document.querySelectorAll('[data-item-type="document"]').forEach(item => {
        item.addEventListener('dragstart', function(e) {
            if (!canEdit) {
                e.preventDefault();
                return;
            }
            
            const docId = this.dataset.itemId;
            const docName = this.querySelector('.item-name')?.textContent || 'Documento';
            
            clipboardDocument = { id: docId, name: docName };
            this.style.opacity = '0.5';
            
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', docId);
        });
        
        item.addEventListener('dragend', function() {
            this.style.opacity = '1';
        });
    });
    
    // Hacer carpetas receptoras
    document.querySelectorAll('[data-item-type="document_folder"]').forEach(folder => {
        folder.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
        
        folder.addEventListener('drop', function(e) {
            e.preventDefault();
            
            if (clipboardDocument) {
                const folderId = this.dataset.itemId;
                const folderName = this.querySelector('.item-name')?.textContent || 'Carpeta';
                
                // Mover directamente a carpeta
                moveDocumentToFolder(clipboardDocument.id, folderId, folderName);
            }
        });
    });
});

async function moveDocumentToFolder(docId, folderId, folderName) {
    try {
        // Construir path de la carpeta basado en el currentPath actual
        const targetPath = currentPath + '/folder_' + folderId;
        
        const response = await fetch('move_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                document_id: parseInt(docId),
                target_path: targetPath
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ Documento movido a: ' + folderName);
            window.location.reload();
        } else {
            alert('❌ Error: ' + result.message);
        }
    } catch (error) {
        alert('❌ Error de conexión: ' + error.message);
    }
}

       // Ejecutar fix
       document.addEventListener('DOMContentLoaded', fixFeatherIcons);

       // Ejecutar también con delay por si acaso
       setTimeout(fixFeatherIcons, 1000);
       setTimeout(() => {
           if (typeof feather !== 'undefined') {
               feather.replace();
               console.log('🔄 Refresh final de iconos');
           }
       }, 3000);

       console.log('✅ SCRIPT COMPLETO CARGADO - Modal dinámico que conecta con view.php implementado');

   </script>
</body>

</html>