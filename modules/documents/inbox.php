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
                'create_folders' => $permissions['create_folders'] ?? false,
                'move' => true  // SIEMPRE PERMITIR MOVER
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
// FUNCIÓN getNavigationItems - VERSIÓN CORREGIDA
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
        // NIVEL 1: DEPARTAMENTOS + DOCUMENTOS DE EMPRESA - SECCIÓN CORREGIDA
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

        // DEPARTAMENTOS CON RESTRICCIONES DE GRUPO - SECCIÓN CORREGIDA
        $userGroupPermissions = getUserGroupPermissions($userId);

        if ($userGroupPermissions['has_groups'] && !empty($userGroupPermissions['restrictions']['departments'])) {
            // Usuario con grupos - aplicar restricciones de departamentos
            $allowedDepartmentIds = getUserAllowedDepartments($userId, $companyId);
            $departments = [];

            // CORRECCIÓN: Verificar que sea un array de IDs y no esté vacío
            if (!empty($allowedDepartmentIds) && is_array($allowedDepartmentIds)) {
                foreach ($allowedDepartmentIds as $deptId) {
                    // CORRECCIÓN: $deptId es un número, no un array
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

                    try {
                        $statsStmt = $pdo->prepare($statsQuery);
                        $statsStmt->execute([$deptId]); // ✅ CORRECCIÓN: Usar $deptId directamente
                        $deptStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

                        if ($deptStats) {
                            $departments[] = $deptStats;
                        }
                    } catch (Exception $e) {
                        error_log("Error obteniendo stats del departamento $deptId: " . $e->getMessage());
                    }
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

            try {
                $stmt = $pdo->prepare($deptQuery);
                $stmt->execute([$companyId]);
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Error obteniendo todos los departamentos: " . $e->getMessage());
                $departments = [];
            }
        }

        // Procesar departamentos obtenidos
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

   <!-- ESTILOS CSS COMPLETO -->
   <style>
       /* Variables CSS personalizadas */
       /* Variables CSS corregidas - COLORES ORIGINALES */
:root {
    /* Colores principales - Esquema café y dorado original */
    --primary-color: #8B4513;          /* Café principal original */
    --primary-rgb: 139, 69, 19;        /* RGB del café */
    --primary-light: #f5e6d3;          /* Café muy claro para fondos */
    --bg-secondary: #f8f9fa;           /* Fondo secundario gris claro */
    --border-color: #e9ecef;           /* Borde gris claro */
    --text-primary: #343a40;           /* Texto principal gris oscuro */
    --text-secondary: #6c757d;         /* Texto secundario gris */
    --spacing-8: 2rem;                 /* Espaciado */
}

/* Reemplazar en el <style> del archivo inbox.php estas secciones: */

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
    background: var(--primary-color);   /* Café al hover */
    color: white;
    border-color: var(--primary-color);
    transform: translateX(-2px);
}

/* Filtros avanzados */
.advanced-filters {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
}

.filter-group select:focus {
    outline: none;
    border-color: var(--primary-color);    /* Café en focus */
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
    background: var(--primary-light);     /* Café claro al hover */
    border-color: var(--primary-color);
}

.btn-filter-toggle.active {
    background: var(--primary-color);     /* Café cuando activo */
    color: white;
    border-color: var(--primary-color);
}

/* Formulario dentro del modal */
.form-control:focus {
    outline: none;
    border-color: var(--primary-color);   /* Café en focus */
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
}

/* Opciones de icono en modal */
.icon-options input[type="radio"]:checked+i {
    color: var(--primary-color);          /* Café para icono seleccionado */
}

.icon-options label:hover {
    border-color: var(--primary-color);   /* Café al hover */
}

.icon-options label:has(input[type="radio"]:checked) {
    border-color: var(--primary-color);   /* Café cuando seleccionado */
    background: rgba(var(--primary-rgb), 0.1);
}

/* Breadcrumb item current */
.breadcrumb-item.current {
    color: var(--primary-color);          /* Café para item activo */
    background: var(--primary-light);
    font-weight: 600;
}

/* Search input focus */
.search-input:focus {
    outline: none;
    border-color: var(--primary-color);   /* Café en focus */
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Drag and Drop con café */
.drag-over {
    background-color: rgba(var(--primary-rgb), 0.1) !important;  /* Café suave */
    border: 2px dashed var(--primary-color) !important;          /* Café en borde */
    transform: scale(1.02);
}

/* Explorer item hover con café */
.explorer-item:hover {
    transform: translateY(-4px);
    box-shadow: var(--card-shadow-hover);
    border-color: var(--primary-color);   /* Café en borde al hover */
}

/* Drop target drag over con verde (mantener el verde para mover documentos) */
.explorer-item.drop-target.drag-over {
    border-color: #27ae60;               /* Verde para drop target */
    background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(46, 204, 113, 0.1));
    transform: scale(1.05);
}

/* Breadcrumb drop target */
.breadcrumb-drop-target.drag-over {
    background: #27ae60 !important;      /* Verde para drop en breadcrumb */
    color: white !important;
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(39, 174, 96, 0.3);
}

/* Search info card */
.search-info-card {
    padding: var(--spacing-4) var(--spacing-6);
    display: flex;
    align-items: center;
    gap: var(--spacing-3);
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    border-left: 4px solid var(--primary-color);  /* Café en borde izquierdo */
}

.search-info-card i {
    color: var(--primary-color);          /* Café para icono */
}

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

       /* Modal de crear carpeta - ESTILOS COMPLETOS */
       .modal {
           position: fixed !important;
           top: 0 !important;
           left: 0 !important;
           width: 100% !important;
           height: 100% !important;
           background: rgba(0, 0, 0, 0.5) !important;
           z-index: 9999 !important;
           display: flex !important;
           align-items: center !important;
           justify-content: center !important;
       }

       .modal-content {
           background: white !important;
           border-radius: 8px !important;
           max-width: 500px !important;
           width: 90% !important;
           max-height: 90vh !important;
           overflow-y: auto !important;
           box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important;
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

       /* Drag and Drop Visual Feedback */
       .drag-over {
           background-color: rgba(var(--primary-rgb), 0.1) !important;
           border: 2px dashed var(--primary-color) !important;
           transform: scale(1.02);
       }

       .dragging {
           opacity: 0.6;
           transform: rotate(2deg);
       }

       /* Notificación de éxito */
       .success-notification {
           position: fixed;
           top: 20px;
           right: 20px;
           z-index: 9999;
           padding: 15px 20px;
           border-radius: 8px;
           background: linear-gradient(135deg, #10b981, #059669);
           color: white;
           font-weight: 500;
           box-shadow: 0 4px 12px rgba(0,0,0,0.15);
           max-width: 400px;
           display: flex;
           align-items: center;
           gap: 10px;
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

   <!-- JAVASCRIPT LIMPIO SIN DUPLICACIONES -->
   <script>
       // ===================================================================
       // VARIABLES GLOBALES - DECLARADAS CORRECTAMENTE
       // ===================================================================
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
           canView = canDownload = canCreate = canEdit = canDelete = canCreateFolders = true;
           currentPath = '';
           pathParts = [];
       }

       // Variables para debounce y filtros
       let searchTimeout;
       let filterTimeout;
       let searchDebounceTime = 1000;

       // ===================================================================
       // FUNCIÓN ÚNICA PARA CREAR CARPETA - VERSION MEJORADA
       // ===================================================================
       function createDocumentFolder() {
           console.log('🔥 createDocumentFolder() EJECUTADA');

           if (!canCreateFolders) {
               console.error('❌ Sin permisos para crear carpetas');
               alert('❌ No tienes permisos para crear carpetas');
               return;
           }

           const modal = document.getElementById('createDocumentFolderModal');
           if (!modal) {
               console.error('❌ Modal createDocumentFolderModal no encontrado');
               alert('❌ Error: Modal no encontrado');
               return;
           }

           // Forzar la visualización del modal
           modal.style.display = 'flex';
           modal.style.visibility = 'visible';
           modal.style.opacity = '1';
           
           // Asegurar que esté por encima de todo
           modal.style.zIndex = '9999';
           
           console.log('✅ Modal configurado para mostrarse');
           console.log('Modal display:', modal.style.display);
           console.log('Modal visibility:', modal.style.visibility);

           setTimeout(() => {
               const nameInput = modal.querySelector('input[name="name"]');
               if (nameInput) {
                   nameInput.focus();
                   console.log('✅ Focus en input name');
               } else {
                   console.log('❌ No se encontró input name');
               }
           }, 200);

           console.log('✅ Modal abierto exitosamente');
       }

       function closeDocumentFolderModal() {
           const modal = document.getElementById('createDocumentFolderModal');
           if (modal) {
               modal.style.display = 'none';
               modal.style.visibility = 'hidden';
               modal.style.opacity = '0';
               
               const form = document.getElementById('createDocumentFolderForm');
               if (form) {
                   form.reset();
                   // Resetear el primer radio button de color
                   const firstColorRadio = form.querySelector('input[name="folder_color"]');
                   if (firstColorRadio) firstColorRadio.checked = true;
                   
                   // Resetear el primer radio button de icono
                   const firstIconRadio = form.querySelector('input[name="folder_icon"]');
                   if (firstIconRadio) firstIconRadio.checked = true;
               }
               console.log('✅ Modal cerrado');
           }
       }

       function submitCreateDocumentFolder(event) {
           event.preventDefault();
           const form = event.target;
           const formData = new FormData(form);
           const submitBtn = form.querySelector('button[type="submit"]');

           const originalText = submitBtn.innerHTML;
           submitBtn.disabled = true;
           submitBtn.innerHTML = '<i data-feather="loader"></i><span>Creando...</span>';

           fetch('create_folder.php', {
                   method: 'POST',
                   body: formData
               })
               .then(response => response.text())
               .then(text => {
                   try {
                       const data = JSON.parse(text);
                       if (data.success) {
                           alert('✅ Carpeta creada exitosamente');
                           closeDocumentFolderModal();
                           setTimeout(() => window.location.reload(), 1000);
                       } else {
                           alert('❌ Error: ' + (data.message || 'Error desconocido'));
                       }
                   } catch (parseError) {
                       console.error('❌ Error al parsear JSON:', parseError);
                       alert('❌ Error de servidor');
                   }
               })
               .catch(error => {
                   console.error('💥 Error en la solicitud:', error);
                   alert('❌ Error de conexión: ' + error.message);
               })
               .finally(() => {
                   submitBtn.disabled = false;
                   submitBtn.innerHTML = originalText;
               });
       }

       // ===================================================================
       // FUNCIÓN ÚNICA PARA VER DOCUMENTOS
       // ===================================================================
       function viewDocument(documentId) {
           console.log('👁️ viewDocument() ejecutada para documento:', documentId);

           if (!canView) {
               alert('No tienes permisos para ver documentos');
               return;
           }

           if (!documentId) {
               alert('Error: ID de documento no válido');
               return;
           }

           showDocumentModal(documentId);
       }

       function showDocumentModal(documentId) {
           ensureDocumentModal();

           const modal = document.getElementById('documentModal');
           const title = document.getElementById('modalTitle');
           const content = document.getElementById('modalContent');

           title.innerHTML = `
               <span>📄 Vista de Documento</span>
               <button onclick="closeDocumentModal()" 
                       style="background:none;border:none;font-size:24px;cursor:pointer;color:#666;padding:5px;">
                   ✕
               </button>
           `;

           content.innerHTML = `
               <iframe src="view.php?id=${documentId}" 
                       style="width: 100%; height: 100%; border: none; border-radius: 8px; background: white;"
                       onload="console.log('✅ Documento cargado')"
                       onerror="console.error('❌ Error cargando documento')">
                   <p>Tu navegador no soporta iframes. <a href="view.php?id=${documentId}" target="_blank">Abrir documento</a></p>
               </iframe>
           `;

           modal.style.display = 'flex';
           document.body.style.overflow = 'hidden';
       }

       function ensureDocumentModal() {
           if (document.getElementById('documentModal')) return;

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

           modal.addEventListener('click', (e) => {
               if (e.target === modal) closeDocumentModal();
           });
       }

       function closeDocumentModal() {
           const modal = document.getElementById('documentModal');
           if (modal) {
               modal.style.display = 'none';
               document.body.style.overflow = '';
           }
       }

       // ===================================================================
       // FUNCIÓN ÚNICA PARA DESCARGAR DOCUMENTOS
       // ===================================================================
       function downloadDocument(documentId) {
           console.log('📥 downloadDocument() ejecutada para:', documentId);

           if (!documentId) {
               alert('Error: ID de documento no válido');
               return;
           }

           if (!canDownload) {
               alert('No tienes permisos para descargar documentos');
               return;
           }

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
           }, 1000);
       }
// ===================================================================
// FUNCIÓN deleteDocument CON MODAL PROFESIONAL MEJORADO (SIN ICONOS)
// Reemplazar la función deleteDocument en inbox.php
// ===================================================================

function deleteDocument(documentId, documentName) {
    console.log('🗑️ DELETE INICIADO:', { documentId, documentName });

    if (!documentId) {
        console.error('❌ ID de documento inválido:', documentId);
        alert('Error: ID de documento no válido');
        return;
    }

    if (!canDelete) {
        console.error('❌ Sin permisos de eliminación. canDelete:', canDelete);
        alert('No tienes permisos para eliminar documentos');
        return;
    }

    // ===== CREAR MODAL PROFESIONAL DE CONFIRMACIÓN =====
    const documentDisplayName = documentName || `Documento ID: ${documentId}`;
    
    console.log('🎯 Creando modal profesional de confirmación...');
    
    // Crear el modal
    const modal = document.createElement('div');
    modal.id = 'deleteConfirmModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.65);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        backdrop-filter: blur(4px);
        animation: fadeIn 0.2s ease-out;
    `;
    
    modal.innerHTML = `
        <div style="
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 16px;
            padding: 40px;
            max-width: 520px;
            width: 90%;
            box-shadow: 
                0 25px 80px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            text-align: center;
            position: relative;
            transform: scale(0.9);
            animation: modalSlideIn 0.3s ease-out forwards;
        ">
            <!-- Header -->
            <div style="
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #e9ecef;
            ">
                <h2 style="
                    margin: 0;
                    color: #2c3e50;
                    font-size: 28px;
                    font-weight: 700;
                    letter-spacing: -0.5px;
                ">Confirmar Eliminación</h2>
            </div>
            
            <!-- Content -->
            <div style="
                margin-bottom: 35px;
            ">
                <div style="
                    background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%);
                    border: 1px solid #fecaca;
                    border-radius: 12px;
                    padding: 25px;
                    margin: 25px 0;
                    position: relative;
                ">
                    <div style="
                        position: absolute;
                        left: 0;
                        top: 0;
                        bottom: 0;
                        width: 4px;
                        background: linear-gradient(to bottom, #dc2626, #ef4444);
                        border-radius: 2px 0 0 2px;
                    "></div>
                    
                    <p style="
                        margin: 0 0 15px 0;
                        color: #374151;
                        font-size: 16px;
                        font-weight: 600;
                    ">Documento seleccionado:</p>
                    
                    <p style="
                        margin: 0;
                        color: #1f2937;
                        font-size: 18px;
                        font-weight: 700;
                        word-break: break-word;
                        line-height: 1.4;
                    ">${documentDisplayName}</p>
                </div>
                
                <div style="
                    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
                    border: 1px solid #fbbf24;
                    border-radius: 12px;
                    padding: 20px;
                    margin: 20px 0;
                ">
                    <p style="
                        margin: 0;
                        color: #92400e;
                        font-size: 15px;
                        font-weight: 600;
                        line-height: 1.5;
                    ">
                        <strong>Advertencia:</strong> Esta acción no se puede deshacer.<br>
                        El documento será eliminado permanentemente del sistema.
                    </p>
                </div>
            </div>
            
            <!-- Actions -->
            <div style="
                display: flex;
                gap: 20px;
                justify-content: center;
                margin-top: 35px;
            ">
                <button id="cancelDelete" style="
                    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                    color: #495057;
                    border: 2px solid #dee2e6;
                    padding: 15px 35px;
                    border-radius: 10px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    min-width: 140px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                ">
                    Cancelar
                </button>
                
                <button id="confirmDelete" style="
                    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
                    color: white;
                    border: 2px solid #dc2626;
                    padding: 15px 35px;
                    border-radius: 10px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    min-width: 140px;
                    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
                ">
                    Eliminar
                </button>
            </div>
        </div>
    `;
    
    // Agregar estilos de animación
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes modalSlideIn {
            from { 
                transform: scale(0.9) translateY(20px); 
                opacity: 0;
            }
            to { 
                transform: scale(1) translateY(0); 
                opacity: 1;
            }
        }
        
        #deleteConfirmModal button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        
        #deleteConfirmModal #cancelDelete:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            border-color: #adb5bd;
        }
        
        #deleteConfirmModal #confirmDelete:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        }
        
        #deleteConfirmModal button:active {
            transform: translateY(0);
        }
        
        @media (max-width: 600px) {
            #deleteConfirmModal > div {
                padding: 30px 25px;
                margin: 20px;
            }
            
            #deleteConfirmModal .actions {
                flex-direction: column;
                gap: 15px;
            }
            
            #deleteConfirmModal button {
                width: 100%;
            }
        }
    `;
    
    document.head.appendChild(style);
    
    // Agregar el modal al DOM
    document.body.appendChild(modal);
    console.log('✅ Modal profesional creado y agregado al DOM');
    
    // Manejar clicks en los botones
    const cancelBtn = modal.querySelector('#cancelDelete');
    const confirmBtn = modal.querySelector('#confirmDelete');
    
    // Función para cerrar el modal con animación
    function closeModal() {
        modal.style.animation = 'fadeIn 0.2s ease-out reverse';
        setTimeout(() => {
            if (modal.parentNode) {
                modal.parentNode.removeChild(modal);
                document.head.removeChild(style);
                console.log('🚪 Modal cerrado');
            }
        }, 200);
    }
    
    // Si clickea "Cancelar"
    cancelBtn.addEventListener('click', function() {
        console.log('❌ Usuario canceló la eliminación desde modal profesional');
        closeModal();
    });
    
    // Si clickea "Eliminar"
    confirmBtn.addEventListener('click', function() {
        console.log('✅ Usuario confirmó eliminación desde modal profesional');
        
        // Cambiar el botón para mostrar estado de carga
        confirmBtn.style.background = 'linear-gradient(135deg, #6b7280 0%, #4b5563 100%)';
        confirmBtn.style.borderColor = '#6b7280';
        confirmBtn.textContent = 'Eliminando...';
        confirmBtn.disabled = true;
        cancelBtn.disabled = true;
        
        closeModal();
        
        // Proceder con la eliminación
        proceedWithDeletion(documentId, documentName);
    });
    
    // Cerrar modal si clickea fuera de él
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            console.log('❌ Usuario cerró modal clickeando fuera');
            closeModal();
        }
    });
    
    // Cerrar con tecla Escape
    function handleEscape(e) {
        if (e.key === 'Escape') {
            console.log('❌ Usuario cerró modal con Escape');
            closeModal();
            document.removeEventListener('keydown', handleEscape);
        }
    }
    document.addEventListener('keydown', handleEscape);
    
    // Focus en el botón de cancelar por defecto (para accesibilidad)
    setTimeout(() => {
        cancelBtn.focus();
    }, 300);
    
    console.log('🎯 Modal profesional listo para interacción');
}

// ===== FUNCIÓN SEPARADA PARA PROCESAR LA ELIMINACIÓN =====
function proceedWithDeletion(documentId, documentName) {
    console.log('🚀 Procediendo con eliminación confirmada:', { documentId, documentName });
    
    // Obtener path actual
    function getCurrentPath() {
        const urlParams = new URLSearchParams(window.location.search);
        const urlPath = urlParams.get('path');
        if (urlPath) return urlPath;
        if (typeof currentPath !== 'undefined' && currentPath) return currentPath;
        
        const breadcrumbs = document.querySelectorAll('.breadcrumb-item[data-breadcrumb-path]');
        if (breadcrumbs.length > 0) {
            const lastBreadcrumb = breadcrumbs[breadcrumbs.length - 1];
            return lastBreadcrumb.dataset.breadcrumbPath || '';
        }
        return '';
    }

    const currentPathValue = getCurrentPath();
    console.log('📍 Path actual:', currentPathValue);

    // Mostrar indicador de carga en el botón original
    const button = event ? event.target.closest('.action-btn, .list-action-btn') : null;
    const originalContent = button ? button.innerHTML : null;
    
    if (button) {
        button.disabled = true;
        button.innerHTML = 'Eliminando...';
        button.style.backgroundColor = '#6b7280';
        button.style.color = 'white';
        button.style.opacity = '0.8';
        console.log('🔄 Botón original deshabilitado');
    }

    // Crear formulario
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'delete.php';
    form.style.display = 'none';

    // Campo document_id
    const inputDoc = document.createElement('input');
    inputDoc.type = 'hidden';
    inputDoc.name = 'document_id';
    inputDoc.value = documentId;
    form.appendChild(inputDoc);

    // Campo return_path
    if (currentPathValue) {
        const inputPath = document.createElement('input');
        inputPath.type = 'hidden';
        inputPath.name = 'return_path';
        inputPath.value = currentPathValue;
        form.appendChild(inputPath);
    }

    console.log('📤 Formulario creado para eliminación confirmada');

    // Timeout de seguridad
    const timeoutId = setTimeout(() => {
        console.error('⏰ TIMEOUT: Eliminación tardó más de 20 segundos');
        
        if (button && originalContent) {
            button.disabled = false;
            button.innerHTML = originalContent;
            button.style.backgroundColor = '';
            button.style.color = '';
            button.style.opacity = '';
        }
        
        alert('La eliminación está tardando más de lo esperado. La página se recargará para verificar el estado.');
        window.location.reload();
        
    }, 20000);

    // Limpiar timeout cuando la página se descargue
    window.addEventListener('beforeunload', () => {
        clearTimeout(timeoutId);
    });

    // Enviar formulario
    document.body.appendChild(form);
    console.log('📧 Enviando formulario de eliminación...');
    
    try {
        form.submit();
        console.log('✅ Eliminación enviada exitosamente');
    } catch (error) {
        console.error('❌ Error al enviar eliminación:', error);
        clearTimeout(timeoutId);
        
        if (button && originalContent) {
            button.disabled = false;
            button.innerHTML = originalContent;
            button.style.backgroundColor = '';
            button.style.color = '';
            button.style.opacity = '';
        }
        
        alert('Error al procesar eliminación: ' + error.message);
    }
}

// ===================================================================
// FUNCIÓN AUXILIAR PARA VERIFICAR PERMISOS (AGREGAR AL SCRIPT)
// ===================================================================
function verifyDeletePermission() {
    console.log('🔐 Verificando permisos de eliminación...');
    console.log('canDelete:', canDelete);
    console.log('currentUserRole:', currentUserRole);
    console.log('currentUserId:', currentUserId);
    
    if (!canDelete) {
        console.warn('❌ Usuario sin permisos de eliminación');
        return false;
    }
    
    return true;
}

// ===================================================================
// FUNCIÓN MEJORADA PARA MANEJAR ERRORES DE ELIMINACIÓN
// ===================================================================
window.addEventListener('load', function() {
    // Verificar si hay parámetros de error en la URL
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    const success = urlParams.get('success');
    
    if (error) {
        let errorMessage = 'Error desconocido';
        
        switch (error) {
            case 'no_delete_permission':
                errorMessage = '❌ No tienes permisos para eliminar documentos.\nContacta al administrador para obtener permisos.';
                break;
            case 'document_not_found':
                errorMessage = '❌ El documento no fue encontrado o ya fue eliminado.';
                break;
            case 'not_document_owner':
                errorMessage = '❌ Solo puedes eliminar tus propios documentos.';
                break;
            case 'delete_failed':
                errorMessage = '❌ Error al eliminar el documento. Inténtalo nuevamente.';
                break;
            case 'invalid_request':
                errorMessage = '❌ Solicitud inválida.';
                break;
        }
        
        alert(errorMessage);
        
        // Limpiar URL
        const cleanUrl = new URL(window.location);
        cleanUrl.searchParams.delete('error');
        window.history.replaceState({}, '', cleanUrl);
    }
    
    if (success === 'document_deleted') {
        const deletedName = urlParams.get('deleted_name');
        const message = deletedName ? 
            `✅ Documento "${decodeURIComponent(deletedName)}" eliminado correctamente.` : 
            '✅ Documento eliminado correctamente.';
        
        showSuccessNotification(message);
        
        // Limpiar URL
        const cleanUrl = new URL(window.location);
        cleanUrl.searchParams.delete('success');
        cleanUrl.searchParams.delete('deleted_name');
        window.history.replaceState({}, '', cleanUrl);
    }
});

// ===================================================================
// AGREGAR ESTILOS PARA BOTÓN DE CARGA
// ===================================================================
const loadingStyles = `
<style>
.action-btn:disabled,
.list-action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
</style>
`;

if (!document.querySelector('#loading-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'loading-styles';
    styleElement.innerHTML = loadingStyles;
    document.head.appendChild(styleElement);
}
       // ===================================================================
       // FUNCIÓN ÚNICA PARA MOVER DOCUMENTOS - SIN DUPLICACIONES
       // ===================================================================
       window.moveDocument = async function(docId, folderId, folderName) {
           if (!docId || !folderId) {
               console.log('❌ IDs inválidos:', docId, folderId);
               return;
           }

           try {
               console.log('📡 Moviendo documento:', docId, '→', folderId, '(', folderName, ')');

               const response = await fetch('api/move_document.php', {
                   method: 'POST',
                   headers: {
                       'Content-Type': 'application/json'
                   },
                   body: JSON.stringify({
                       document_id: parseInt(docId),
                       folder_id: parseInt(folderId)
                   })
               });

               const text = await response.text();

               // Verificar si es HTML (error) vs JSON
               if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
                   console.log('⚠️ Respuesta HTML ignorada');
                   return;
               }

               const result = JSON.parse(text);

               if (result.success) {
                   showSuccessNotification(`✅ ${result.message}`);
                   setTimeout(() => window.location.reload(), 1500);
               } else {
                   alert(`❌ ${result.message}`);
               }

           } catch (error) {
               if (!error.message.includes('Unexpected token') && !error.message.includes('<!DOCTYPE')) {
                   console.error('❌ Error real:', error);
                   alert(`❌ Error: ${error.message}`);
               }
           }
       };

       // ===================================================================
       // CONFIGURACIÓN ÚNICA DE DRAG & DROP
       // ===================================================================
       function setupDragDrop() {
           console.log('🔧 Configurando drag & drop...');

           // Hacer documentos arrastrables
           const documentItems = document.querySelectorAll('[data-item-type="document"]');
           documentItems.forEach((item) => {
               const documentId = item.dataset.itemId;
               if (!documentId) return;

               item.draggable = true;
               item.style.cursor = 'move';

               item.addEventListener('dragstart', (e) => {
                   window.draggedDocumentId = documentId;
                   window.draggedDocumentName = item.querySelector('.name-text, .item-name')?.textContent || 'Documento';

                   item.style.opacity = '0.5';
                   item.classList.add('dragging');
                   e.dataTransfer.effectAllowed = 'move';
                   e.dataTransfer.setData('text/plain', documentId);
               });

               item.addEventListener('dragend', () => {
                   item.style.opacity = '1';
                   item.classList.remove('dragging');
               });
           });

           // Hacer carpetas receptivas
           const folderItems = document.querySelectorAll('[data-item-type="document_folder"]');
           folderItems.forEach((folder) => {
               const folderId = folder.dataset.itemId;
               if (!folderId) return;

               folder.addEventListener('dragover', (e) => {
                   e.preventDefault();
                   e.dataTransfer.dropEffect = 'move';
               });

               folder.addEventListener('dragenter', (e) => {
                   e.preventDefault();
                   if (window.draggedDocumentId) {
                       folder.classList.add('drag-over');
                   }
               });

               folder.addEventListener('dragleave', (e) => {
                   if (!folder.contains(e.relatedTarget)) {
                       folder.classList.remove('drag-over');
                   }
               });

               folder.addEventListener('drop', (e) => {
                   e.preventDefault();
                   folder.classList.remove('drag-over');

                   if (!window.draggedDocumentId) return;

                   const folderName = folder.querySelector('.name-text, .item-name')?.textContent || 'Carpeta';
                   moveDocument(window.draggedDocumentId, folderId, folderName);

                   window.draggedDocumentId = null;
                   window.draggedDocumentName = null;
               });
           });

           console.log(`✅ Drag & Drop configurado: ${documentItems.length} docs, ${folderItems.length} carpetas`);
       }

       // ===================================================================
       // FUNCIONES AUXILIARES
       // ===================================================================
       function handleSearchInput(value) {
           clearTimeout(searchTimeout);
           if (value.length === 0) {
               searchTimeout = setTimeout(() => applyFiltersAuto(), 100);
           } else if (value.length >= 2) {
               searchTimeout = setTimeout(() => applyFiltersAuto(), searchDebounceTime);
           }
       }

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

       function clearSearch() {
           document.querySelector('.search-input').value = '';
           applyFiltersAuto();
       }

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

       function navigateTo(path) {
           if (path) {
               window.location.href = '?path=' + encodeURIComponent(path);
           } else {
               window.location.href = '?';
           }
       }

       function changeView(viewType) {
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
       }

       function showSuccessNotification(message) {
           const existing = document.querySelector('.success-notification');
           if (existing) existing.remove();

           const notification = document.createElement('div');
           notification.className = 'success-notification';
           notification.innerHTML = `
               <i data-feather="check-circle" style="width: 20px; height: 20px;"></i>
               <span>${message}</span>
           `;

           document.body.appendChild(notification);

           if (typeof feather !== 'undefined') {
               feather.replace();
           }

           setTimeout(() => {
               if (notification.parentNode) {
                   notification.remove();
               }
           }, 4000);
       }

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

       
       // ===================================================================
        // INICIALIZACIÓN
        // ===================================================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM LOADED');

            // Inicializar filtros desde URL
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

            // Configurar drag & drop
            setTimeout(setupDragDrop, 1000);

            // Cerrar modales con click fuera o escape
            document.addEventListener('click', function(event) {
                const folderModal = document.getElementById('createDocumentFolderModal');
                if (event.target === folderModal) closeDocumentFolderModal();

                const documentModal = document.getElementById('documentModal');
                if (event.target === documentModal) closeDocumentModal();
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const folderModal = document.getElementById('createDocumentFolderModal');
                    if (folderModal && folderModal.style.display === 'flex') {
                        closeDocumentFolderModal();
                    }
                    closeDocumentModal();
                }
            });

            // Inicializar iconos
            if (typeof feather !== 'undefined') {
                feather.replace();
            }

            // Actualizar tiempo
            updateTime();
            setInterval(updateTime, 60000);

            console.log('✅ Inicialización completa');
        });

        // Cargar Feather Icons si no está disponible
        if (typeof feather === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/feather-icons';
            script.onload = function() {
                feather.replace();
                console.log('✅ Feather Icons cargado');
            };
            document.head.appendChild(script);
        }

        console.log('✅ SCRIPT COMPLETO CARGADO SIN DUPLICACIONES');
    </script>
</body>

</html