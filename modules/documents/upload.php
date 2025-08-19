<?php

/**
 * modules/documents/upload.php - VERSIÓN COMPLETA CORREGIDA
 * Sistema de subida de documentos con permisos de grupos
 */

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
// FUNCIÓN getUserPermissions - IGUAL QUE EN INBOX.PHP
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
            // ADMINISTRADORES: ACCESO TOTAL SIN RESTRICCIONES
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
                    'companies' => [], // VACÍO = ACCESO A TODAS
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
// VALIDACIÓN DE PERMISOS DE UPLOAD
// ===================================================================
$canUpload = false;
$noAccess = false;
$error = '';
$success = '';

try {
    // Obtener permisos del usuario usando la función unificada
    $userPermissions = getUserPermissions($currentUser['id']);
    $canUpload = $userPermissions['permissions']['create'] ?? false;

    error_log("=== DEBUG PERMISOS UPLOAD ===");
    error_log("Usuario ID: " . $currentUser['id']);
    error_log("Rol: " . $currentUser['role']);
    error_log("Can Upload: " . ($canUpload ? 'TRUE' : 'FALSE'));

    if (!$canUpload) {
        $noAccess = true;
        $error = 'No tiene permisos para subir archivos. Debe estar asignado a un grupo con el permiso "Subir Archivos" activado.';
    }
} catch (Exception $e) {
    $noAccess = true;
    $error = $e->getMessage();
    error_log("Error en validación de permisos upload: " . $e->getMessage());
}

// ===================================================================
// SI NO TIENE ACCESO, MOSTRAR PANTALLA DE ERROR
// ===================================================================
if ($noAccess) {
?>
    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Subir Documento - DMS2</title>
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
                    <h1>Subir Documento</h1>
                </div>
                <div class="header-right">
                    <div class="header-info">
                        <div class="user-name-header"><?php echo htmlspecialchars(trim($currentUser['first_name'] . ' ' . $currentUser['last_name'])); ?></div>
                        <div class="current-time" id="currentTime"></div>
                    </div>
                    <div class="header-actions">
                        <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                            <i data-feather="log-out"></i>
                        </a>
                    </div>
                </div>
            </header>
            <div class="container">
                <div class="content-section">
                    <div class="content-card">
                        <div class="content-body">
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i data-feather="lock"></i>
                                </div>
                                <h3>Sin permisos para subir archivos</h3>
                                <p>
                                    Su usuario no tiene permisos para subir documentos al sistema.
                                    <br>Para obtener acceso de subida, contacte al administrador del sistema.
                                </p>
                                <?php if ($error): ?>
                                    <div class="alert alert-error" style="margin-top: 1rem;">
                                        <i data-feather="alert-circle"></i>
                                        <?php echo htmlspecialchars($error); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="empty-actions">
                                    <a href="inbox.php" class="btn-secondary">
                                        <i data-feather="folder"></i>
                                        <span>Ver Documentos</span>
                                    </a>
                                    <a href="../../dashboard.php" class="btn-secondary">
                                        <i data-feather="home"></i>
                                        <span>Volver al Dashboard</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <script>
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
            document.addEventListener('DOMContentLoaded', function() {
                feather.replace();
                updateTime();
                setInterval(updateTime, 60000);
            });
        </script>
    </body>

    </html>
<?php
    exit; // IMPORTANTE: Salir aquí para no mostrar el resto del formulario
}

// ===================================================================
// OBTENER CONTEXTO DEL PATH ACTUAL
// ===================================================================
$currentPath = isset($_GET['path']) ? trim($_GET['path']) : '';
$pathParts = $currentPath ? explode('/', trim($currentPath, '/')) : [];
$pathParts = array_filter($pathParts); // Remover elementos vacíos

$uploadContext = [
    'type' => 'general',
    'company_id' => null,
    'department_id' => null,
    'folder_id' => null,
    'company_name' => '',
    'department_name' => '',
    'folder_name' => ''
];

error_log("=== DEBUG CONTEXTO ===");
error_log("Current Path: " . $currentPath);
error_log("Path Parts: " . json_encode($pathParts));

// Determinar contexto basado en el path
if (count($pathParts) >= 1 && is_numeric($pathParts[0])) {
    $uploadContext['company_id'] = (int)$pathParts[0];
    $uploadContext['type'] = 'company';
}

if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
    $uploadContext['department_id'] = (int)$pathParts[1];
    $uploadContext['type'] = 'department';
}

if (count($pathParts) >= 3 && strpos($pathParts[2], 'folder_') === 0) {
    $uploadContext['folder_id'] = (int)substr($pathParts[2], 7);
    $uploadContext['type'] = 'folder';
}

// ===================================================================
// CONFIGURACIÓN DE SUBIDA
// ===================================================================
$maxFileSize = 20971520; // 20MB
$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];

// ===================================================================
// PROCESAMIENTO DEL FORMULARIO
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canUpload) {
    try {
        error_log("=== PROCESANDO FORMULARIO ===");

        // Validar que se enviaron archivos
        if (!isset($_FILES['documents']) || empty($_FILES['documents']['name'][0])) {
            throw new Exception('No se seleccionaron archivos para subir');
        }

        $files = $_FILES['documents'];
        $documentName = trim($_POST['document_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $documentTypeId = intval($_POST['document_type_id'] ?? 0);
        $tags = isset($_POST['tags']) && $_POST['tags'] ? explode(',', $_POST['tags']) : [];
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags);

        // Usar datos del formulario o contexto del path
        $companyId = intval($_POST['company_id'] ?? 0) ?: $uploadContext['company_id'];
        $departmentId = intval($_POST['department_id'] ?? 0) ?: $uploadContext['department_id'];
        $folderId = intval($_POST['folder_id'] ?? 0) ?: $uploadContext['folder_id'];

        error_log("=== DATOS PROCESADOS ===");
        error_log("Company ID: $companyId");
        error_log("Department ID: $departmentId");
        error_log("Document Type ID: $documentTypeId");

        // VALIDACIONES OBLIGATORIAS
        if (!$companyId || !$documentTypeId) {
            throw new Exception('Empresa y Tipo de Documento son obligatorios');
        }

        // VALIDACIONES DE PERMISOS (solo si tiene grupos)
        $groupPermissions = getUserGroupPermissions($currentUser['id']);

        if ($groupPermissions['has_groups']) {
            if (!canUserAccessCompany($currentUser['id'], $companyId)) {
                throw new Exception('Sin acceso a la empresa seleccionada');
            }
            if ($departmentId && !canUserAccessDepartment($currentUser['id'], $departmentId)) {
                throw new Exception('Sin acceso al departamento seleccionado');
            }
            if (!canUserAccessDocumentType($currentUser['id'], $documentTypeId)) {
                throw new Exception('Sin acceso al tipo de documento seleccionado');
            }
        }

        // Crear directorio de subida si no existe
        $uploadDir = '../../uploads/documents/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('No se pudo crear el directorio de subida');
            }
        }

        $database = new Database();
        $pdo = $database->getConnection();

        $uploadedFiles = [];
        $errors = [];
        $successCount = 0;

        // Procesar cada archivo
        $fileCount = count($files['name']);
        error_log("=== PROCESANDO $fileCount ARCHIVO(S) ===");

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "Error al subir archivo: " . $files['name'][$i];
                continue;
            }

            $file = [
                'name' => $files['name'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'size' => $files['size'][$i],
                'type' => $files['type'][$i]
            ];

            // Validaciones para cada archivo
            if ($file['size'] > $maxFileSize) {
                $errors[] = "Archivo demasiado grande: {$file['name']} (máximo 20MB)";
                continue;
            }

            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $allowedExtensions)) {
                $errors[] = "Tipo de archivo no permitido: {$file['name']}";
                continue;
            }

            // Determinar nombre del documento
            $currentDocName = $documentName ?: pathinfo($file['name'], PATHINFO_FILENAME);
            if ($fileCount > 1 && $documentName) {
                $currentDocName = $documentName . " (" . ($i + 1) . ")";
            }

            // Generar nombre de archivo único y seguro
            $systemName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $currentDocName);
            $systemFileName = $systemName . '.' . $fileExtension;

            // Si ya existe, agregar número
            $counter = 1;
            while (file_exists($uploadDir . $systemFileName)) {
                $systemFileName = $systemName . '_' . $counter . '.' . $fileExtension;
                $counter++;
            }

            $filePath = $uploadDir . $systemFileName;

            // Mover archivo subido
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                $errors[] = "Error al guardar archivo: {$file['name']}";
                continue;
            }

            error_log("✅ Archivo guardado: $systemFileName");

            // Insertar en base de datos
            $insertQuery = "
                INSERT INTO documents (
                    company_id, department_id, folder_id, document_type_id, user_id,
                    name, original_name, file_path, file_size, mime_type, 
                    description, tags, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ";

            $relativePath = 'uploads/documents/' . $systemFileName;
            $tagsJson = !empty($tags) ? json_encode($tags) : null;

            $stmt = $pdo->prepare($insertQuery);
            $insertResult = $stmt->execute([
                $companyId,
                $departmentId,
                $folderId,
                $documentTypeId,
                $currentUser['id'],
                $currentDocName,
                $file['name'],
                $relativePath,
                $file['size'],
                $file['type'],
                $description,
                $tagsJson
            ]);

            if ($insertResult) {
                $uploadedFiles[] = [
                    'name' => $currentDocName,
                    'path' => $relativePath
                ];
                $successCount++;
                error_log("✅ Documento guardado en BD: $currentDocName");
            } else {
                $errors[] = "Error al guardar en base de datos: {$file['name']}";
                // Eliminar archivo físico si falla la BD
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }

        // Mensaje de resultado
        if ($successCount > 0) {
            $success = "Se subieron exitosamente $successCount archivo(s)";
            if (!empty($errors)) {
                $success .= ". Errores: " . implode(', ', $errors);
            }

            error_log("✅ UPLOAD COMPLETADO: $successCount archivos subidos");

            // Redirigir de vuelta al explorador
            $redirectUrl = 'inbox.php';
            if ($currentPath) {
                $redirectUrl .= '?path=' . urlencode($currentPath);
            }

            header("Location: $redirectUrl");
            exit;
        } else {
            $error = "No se pudo subir ningún archivo. Errores: " . implode(', ', $errors);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("❌ ERROR EN UPLOAD: " . $e->getMessage());

        // Limpiar archivos subidos en caso de error
        if (isset($uploadedFiles)) {
            foreach ($uploadedFiles as $uploadedFile) {
                $filePath = $uploadDir . basename($uploadedFile['path'] ?? '');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }
    }
}


// ===================================================================
// CORRECCIÓN PARA UPLOAD.PHP - Línea 735 aproximadamente
// Reemplazar la sección de "CARGAR DATOS PARA EL FORMULARIO"
// ===================================================================

// CARGAR DATOS PARA EL FORMULARIO
$documentTypes = [];
$companies = [];
$departments = [];
$folders = [];

if ($canUpload) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();

        $userPermissions = getUserPermissions($currentUser['id']);
        $restrictions = $userPermissions['restrictions'] ?? [];
        $isAdmin = ($currentUser['role'] === 'admin' || $currentUser['role'] === 'super_admin');

        error_log("=== DEBUG CARGA DATOS UPLOAD ===");
        error_log("Es Admin: " . ($isAdmin ? 'SI' : 'NO'));
        error_log("Restricciones: " . json_encode($restrictions));

        // ===== CARGAR TIPOS DE DOCUMENTOS - CORREGIDO =====
        try {
            if ($isAdmin || empty($restrictions['document_types'])) {
                // Admin O sin restricciones - todos los tipos
                $typesQuery = "SELECT id, name, description FROM document_types WHERE status = 'active' ORDER BY name";
                $typesStmt = $pdo->prepare($typesQuery);
                $typesStmt->execute();
                $documentTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Usuario con restricciones de tipos
                if (!empty($restrictions['document_types']) && is_array($restrictions['document_types'])) {
                    $placeholders = str_repeat('?,', count($restrictions['document_types']) - 1) . '?';
                    $typesQuery = "SELECT id, name, description FROM document_types WHERE id IN ($placeholders) AND status = 'active' ORDER BY name";
                    $typesStmt = $pdo->prepare($typesQuery);
                    $typesStmt->execute($restrictions['document_types']);
                    $documentTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        } catch (Exception $e) {
            error_log("Error cargando tipos de documentos: " . $e->getMessage());
            $documentTypes = [];
        }

        // ===== CARGAR EMPRESAS - CORREGIDO =====
        try {
            if ($isAdmin || empty($restrictions['companies'])) {
                // Admin O sin restricciones - todas las empresas
                $companiesQuery = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
                $companiesStmt = $pdo->prepare($companiesQuery);
                $companiesStmt->execute();
                $companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Usuario con restricciones de empresas
                if (!empty($restrictions['companies']) && is_array($restrictions['companies'])) {
                    $placeholders = str_repeat('?,', count($restrictions['companies']) - 1) . '?';
                    $companiesQuery = "SELECT id, name FROM companies WHERE id IN ($placeholders) AND status = 'active' ORDER BY name";
                    $companiesStmt = $pdo->prepare($companiesQuery);
                    $companiesStmt->execute($restrictions['companies']);
                    $companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        } catch (Exception $e) {
            error_log("Error cargando empresas: " . $e->getMessage());
            $companies = [];
        }

        // ===== CARGAR DEPARTAMENTOS - CORREGIDO =====
        if ($uploadContext['company_id']) {
            try {
                if ($isAdmin || empty($restrictions['departments'])) {
                    // Admin O sin restricciones - todos los departamentos de la empresa
                    $deptQuery = "SELECT id, name FROM departments WHERE company_id = ? AND status = 'active' ORDER BY name";
                    $deptStmt = $pdo->prepare($deptQuery);
                    $deptStmt->execute([$uploadContext['company_id']]);
                    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Usuario con restricciones de departamentos
                    if (!empty($restrictions['departments']) && is_array($restrictions['departments'])) {
                        $placeholders = str_repeat('?,', count($restrictions['departments']) - 1) . '?';
                        $deptQuery = "SELECT id, name FROM departments WHERE id IN ($placeholders) AND company_id = ? AND status = 'active' ORDER BY name";
                        $params = array_merge($restrictions['departments'], [$uploadContext['company_id']]);
                        $deptStmt = $pdo->prepare($deptQuery);
                        $deptStmt->execute($params);
                        $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                }
            } catch (Exception $e) {
                error_log("Error cargando departamentos: " . $e->getMessage());
                $departments = [];
            }
        }

        // ===== CARGAR CARPETAS - IGUAL QUE ANTES =====
        if ($uploadContext['department_id']) {
            try {
                $foldersQuery = "SELECT id, name, folder_color FROM document_folders WHERE company_id = ? AND department_id = ? AND is_active = 1 ORDER BY name";
                $foldersStmt = $pdo->prepare($foldersQuery);
                $foldersStmt->execute([$uploadContext['company_id'], $uploadContext['department_id']]);
                $folders = $foldersStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Error cargando carpetas: " . $e->getMessage());
                $folders = [];
            }
        }

        // ===== OBTENER NOMBRES PARA CONTEXTO - IGUAL QUE ANTES =====
        if ($uploadContext['company_id']) {
            try {
                $companyQuery = "SELECT name FROM companies WHERE id = ?";
                $companyStmt = $pdo->prepare($companyQuery);
                $companyStmt->execute([$uploadContext['company_id']]);
                $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
                $uploadContext['company_name'] = $company['name'] ?? '';
            } catch (Exception $e) {
                error_log("Error obteniendo nombre de empresa: " . $e->getMessage());
                $uploadContext['company_name'] = '';
            }
        }

        if ($uploadContext['department_id']) {
            try {
                $deptQuery = "SELECT name FROM departments WHERE id = ?";
                $deptStmt = $pdo->prepare($deptQuery);
                $deptStmt->execute([$uploadContext['department_id']]);
                $dept = $deptStmt->fetch(PDO::FETCH_ASSOC);
                $uploadContext['department_name'] = $dept['name'] ?? '';
            } catch (Exception $e) {
                error_log("Error obteniendo nombre de departamento: " . $e->getMessage());
                $uploadContext['department_name'] = '';
            }
        }

        if ($uploadContext['folder_id']) {
            try {
                $folderQuery = "SELECT name FROM document_folders WHERE id = ?";
                $folderStmt = $pdo->prepare($folderQuery);
                $folderStmt->execute([$uploadContext['folder_id']]);
                $folder = $folderStmt->fetch(PDO::FETCH_ASSOC);
                $uploadContext['folder_name'] = $folder['name'] ?? '';
            } catch (Exception $e) {
                error_log("Error obteniendo nombre de carpeta: " . $e->getMessage());
                $uploadContext['folder_name'] = '';
            }
        }

        error_log("=== DATOS CARGADOS CORRECTAMENTE ===");
        error_log("Tipos documentos: " . count($documentTypes));
        error_log("Empresas: " . count($companies));
        error_log("Departamentos: " . count($departments));
        error_log("Carpetas: " . count($folders));

    } catch (Exception $e) {
        $error = "Error al cargar datos: " . $e->getMessage();
        error_log("Error crítico cargando datos para formulario: " . $e->getMessage());
        
        // Asegurar arrays vacíos en caso de error
        $documentTypes = [];
        $companies = [];
        $departments = [];
        $folders = [];
    }
}

// ===== VALIDACIONES FINALES ANTES DEL FORMULARIO =====
// Asegurar que los arrays nunca sean null
$documentTypes = $documentTypes ?? [];
$companies = $companies ?? [];
$departments = $departments ?? [];
$folders = $folders ?? [];

// Si no hay datos esenciales para usuarios normales, mostrar error específico
if ($canUpload && !$isAdmin && empty($companies)) {
    $noAccess = true;
    $error = 'No hay empresas disponibles para subir documentos. Contacte al administrador.';
}

if ($canUpload && empty($documentTypes)) {
    $noAccess = true;
    $error = 'No hay tipos de documentos configurados. Contacte al administrador.';
}

error_log("=== VERIFICACIÓN FINAL ===");
error_log("documentTypes count: " . count($documentTypes));
error_log("companies count: " . count($companies));
error_log("canUpload: " . ($canUpload ? 'true' : 'false'));
error_log("noAccess: " . ($noAccess ? 'true' : 'false'));
error_log("isAdmin: " . ($isAdmin ? 'true' : 'false'));
// ===================================================================
// FUNCIONES AUXILIARES
// ===================================================================
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
    <title>Subir Documento - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/documents.css">
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
                <h1>Subir Documento</h1>
            </div>
            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(trim($currentUser['first_name'] . ' ' . $currentUser['last_name'])); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" onclick="showSettings()" title="Configuración">
                        <i data-feather="settings"></i>
                    </button>
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')" title="Cerrar sesión">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <div class="upload-content">
            <div class="upload-container">
                <!-- MOSTRAR CONTEXTO DE SUBIDA -->
                <?php if ($uploadContext['type'] !== 'general'): ?>
                    <div class="upload-context">
                        <div class="context-info">
                            <i data-feather="map-pin"></i>
                            <span>Ubicación de subida:</span>
                            <div class="context-path">
                                <?php if ($uploadContext['company_name']): ?>
                                    <span class="context-item">
                                        <i data-feather="home"></i>
                                        <?= htmlspecialchars($uploadContext['company_name']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($uploadContext['department_name']): ?>
                                    <i data-feather="chevron-right" class="context-separator"></i>
                                    <span class="context-item">
                                        <i data-feather="folder"></i>
                                        <?= htmlspecialchars($uploadContext['department_name']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($uploadContext['folder_name']): ?>
                                    <i data-feather="chevron-right" class="context-separator"></i>
                                    <span class="context-item">
                                        <i data-feather="folder"></i>
                                        <?= htmlspecialchars($uploadContext['folder_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="upload-card">
                    <div class="upload-header">
                        <p>Seleccione archivos y complete la información requerida</p>
                    </div>

                    <!-- MENSAJES -->
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i data-feather="alert-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i data-feather="check-circle"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm" onsubmit="return validateForm()">

                        <!-- ZONA DE ARCHIVOS -->
                        <div class="form-group">
                            <label>Archivos *</label>
                            <div class="file-upload-area" id="fileUploadArea" onclick="document.getElementById('fileInput').click()">
                                <input type="file" name="documents[]" id="fileInput" class="file-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt,.zip,.rar" multiple required>
                                <div class="file-upload-content" id="uploadContent">
                                    <i data-feather="upload-cloud" id="uploadIcon"></i>
                                    <p id="uploadText">Haz clic para seleccionar o arrastra tus archivos aquí</p>
                                    <small id="uploadHint">Puedes seleccionar múltiples archivos. Formatos: PDF, DOC, XLS, PPT, imágenes, TXT, ZIP (máx. 20MB cada uno)</small>
                                </div>
                                <div class="file-preview" id="filePreview" style="display: none;">
                                    <div id="fileList"></div>
                                </div>
                            </div>
                        </div>

                        <!-- CAMPOS DEL FORMULARIO -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="document_name">Nombre del Documento</label>
                                <input type="text" id="document_name" name="document_name" class="form-control" placeholder="Nombre personalizado (opcional)">
                                <small>Si no se especifica, se usará el nombre del archivo</small>
                            </div>

                            <div class="form-group">
                                <label for="document_type_id">Tipo de Documento *</label>
                                <select id="document_type_id" name="document_type_id" class="form-control" required>
                                    <option value="">Seleccionar tipo</option>
                                    <?php foreach ($documentTypes as $type): ?>
                                        <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="company_id">Empresa *</label>
                                <select name="company_id" id="companySelect" class="form-control" onchange="loadDepartments()" <?= $uploadContext['company_id'] ? 'disabled' : '' ?> required>
                                    <option value="">Seleccionar empresa</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company['id'] ?>" <?= $uploadContext['company_id'] == $company['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($company['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($uploadContext['company_id']): ?>
                                    <input type="hidden" name="company_id" value="<?= $uploadContext['company_id'] ?>">
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="department_id">Departamento</label>
                                <select name="department_id" id="departmentSelect" class="form-control" onchange="loadFolders()" <?= $uploadContext['department_id'] ? 'disabled' : '' ?>>
                                    <option value="">Sin departamento</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= $uploadContext['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($uploadContext['department_id']): ?>
                                    <input type="hidden" name="department_id" value="<?= $uploadContext['department_id'] ?>">
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="folder_id">Carpeta (opcional)</label>
                            <select name="folder_id" id="folderSelect" class="form-control" <?= $uploadContext['folder_id'] ? 'disabled' : '' ?>>
                                <option value="">Sin carpeta específica</option>
                                <?php foreach ($folders as $folder): ?>
                                    <option value="<?= $folder['id'] ?>" <?= $uploadContext['folder_id'] == $folder['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($folder['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($uploadContext['folder_id']): ?>
                                <input type="hidden" name="folder_id" value="<?= $uploadContext['folder_id'] ?>">
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="description">Descripción</label>
                            <textarea id="description" name="description" class="form-control" rows="3" placeholder="Descripción opcional del documento o conjunto de documentos"></textarea>
                        </div>
                        <div class="form-actions">
                            <a href="inbox.php<?= $currentPath ? '?path=' . urlencode($currentPath) : '' ?>" class="btn btn-secondary">
                                <i data-feather="arrow-left"></i>
                                Volver al Explorador
                            </a>
                            <button type="submit" class="btn btn-primary btn-upload" id="submitBtn" disabled>
                                <i data-feather="upload"></i>
                                Subir Documentos
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- ESTILOS CSS -->
    <style>
        /* Contexto de subida */
        .upload-context {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: var(--spacing-6);
            padding: 1rem 1.5rem;
        }

        .context-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .context-path {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }

        .context-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            font-size: 0.75rem;
        }

        .context-separator {
            color: var(--text-secondary);
            width: 16px;
            height: 16px;
        }

        /* Card de upload */
        .upload-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .upload-header {
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-bottom: 2px solid #90caf9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: 'Segoe UI', Tahoma, sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
            color: #0d47a1;
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.15);
            border-radius: 10px 10px 0 0;
            letter-spacing: 0.5px;
            transition: background 0.3s ease, box-shadow 0.3s ease;
        }

        .upload-header:hover {
            background: linear-gradient(135deg, #bbdefb, #90caf9);
            box-shadow: 0 6px 18px rgba(33, 150, 243, 0.25);
        }

        .upload-header h2 {
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
            font-size: 1.5rem;
        }

        .upload-header p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Formulario */
        .upload-form {
            padding: 2rem;
        }

        /* Zona de archivos */
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            min-height: 200px;
            background: var(--bg-light);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .file-upload-area:hover,
        .file-upload-area.drag-over {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }

        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: -1;
        }

        .file-upload-content {
            text-align: center;
            padding: 2rem;
        }

        .file-upload-content i {
            width: 48px;
            height: 48px;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .file-upload-content p {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
            font-weight: 500;
        }

        .file-upload-content small {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Vista previa de archivos */
        .file-preview {
            width: 100%;
            padding: 1rem;
        }

        .file-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            transition: all 0.2s;
        }

        .file-item:hover {
            background: var(--bg-light);
        }

        .file-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 1rem;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .file-size {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .file-remove {
            background: var(--error-color);
            color: white;
            border: none;
            border-radius: 4px;
            width: 32px;
            height: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .file-remove:hover {
            background: var(--error-dark);
        }

        /* Grid del formulario */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }

        .form-group small {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
            display: block;
        }

        /* Acciones del formulario */
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            align-items: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        /* Alertas */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 6px;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background: var(--error-light);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }

        .alert-success {
            background: var(--success-light);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .upload-form {
                padding: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .context-path {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .context-separator {
                display: none;
            }
        }
    </style>

    <!-- JAVASCRIPT -->
    <script>
        // Variables globales
        let selectedFiles = [];
        const maxFileSize = <?= $maxFileSize ?>;
        const allowedExtensions = <?= json_encode($allowedExtensions) ?>;

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Upload script iniciado');
            setupUploadZone();
            feather.replace();
            updateTime();
            setInterval(updateTime, 60000);

            // Debug inicial
            console.log('=== DEBUG PERMISOS INICIO ===');
            console.log('Permisos del usuario:', {
                canUpload: <?= $canUpload ? 'true' : 'false' ?>,
                noAccess: <?= $noAccess ? 'true' : 'false' ?>
            });

            console.log('Departamentos cargados:', <?= json_encode($departments) ?>);
            console.log('ID usuario actual:', <?= $currentUser['id'] ?>);
            console.log('Contexto upload:', <?= json_encode($uploadContext) ?>);

            console.log('=== DEBUG PERMISOS FIN ===');
            console.log('📤 Sistema de upload iniciado');
        });

        // Configurar zona de upload
        function setupUploadZone() {
            const uploadZone = document.getElementById('fileUploadArea');
            const fileInput = document.getElementById('fileInput');

            if (!uploadZone || !fileInput) {
                console.error('❌ Elementos de upload no encontrados');
                return;
            }

            // Drag & Drop
            uploadZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadZone.classList.add('drag-over');
            });

            uploadZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadZone.classList.remove('drag-over');
            });

            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadZone.classList.remove('drag-over');

                const files = Array.from(e.dataTransfer.files);
                handleFiles(files);
            });

            // Cambio en input
            fileInput.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                handleFiles(files);
            });
        }

        // Manejar archivos seleccionados
        function handleFiles(files) {
            console.log('📁 Archivos seleccionados:', files.length);

            selectedFiles = [];
            const validFiles = [];
            const errors = [];

            files.forEach(file => {
                console.log('📄 Procesando:', file.name, 'Tamaño:', file.size);

                // Validar tamaño
                if (file.size > maxFileSize) {
                    errors.push(`${file.name}: Archivo demasiado grande (máximo 20MB)`);
                    return;
                }

                // Validar extensión
                const extension = file.name.split('.').pop().toLowerCase();
                if (!allowedExtensions.includes(extension)) {
                    errors.push(`${file.name}: Tipo de archivo no permitido`);
                    return;
                }

                validFiles.push(file);
            });

            if (errors.length > 0) {
                alert('Errores en los archivos:\n\n' + errors.join('\n'));
            }

            if (validFiles.length > 0) {
                selectedFiles = validFiles;
                showFilePreview();

                // Actualizar el input file
                const dt = new DataTransfer();
                validFiles.forEach(file => dt.items.add(file));
                document.getElementById('fileInput').files = dt.files;

                // Habilitar botón submit
                document.getElementById('submitBtn').disabled = false;
            }

            console.log('✅ Archivos válidos:', validFiles.length);
        }

        // Mostrar vista previa
        function showFilePreview() {
            const uploadContent = document.getElementById('uploadContent');
            const filePreview = document.getElementById('filePreview');
            const fileList = document.getElementById('fileList');

            if (selectedFiles.length === 0) {
                uploadContent.style.display = 'block';
                filePreview.style.display = 'none';
                return;
            }

            fileList.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';

                const extension = file.name.split('.').pop().toLowerCase();
                const fileIcon = getFileIcon(extension);

                fileItem.innerHTML = `
                    <div class="file-icon">
                        <i data-feather="${fileIcon}"></i>
                    </div>
                    <div class="file-info">
                        <div class="file-name">${file.name}</div>
                        <div class="file-size">${formatBytes(file.size)}</div>
                    </div>
                    <button type="button" class="file-remove" onclick="removeFile(${index})">
                        <i data-feather="x"></i>
                    </button>
                `;

                fileList.appendChild(fileItem);
            });

            uploadContent.style.display = 'none';
            filePreview.style.display = 'block';
            feather.replace();
        }

        // Remover archivo
        function removeFile(index) {
            selectedFiles.splice(index, 1);

            // Actualizar input file
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            document.getElementById('fileInput').files = dt.files;

            showFilePreview();

            // Deshabilitar botón si no hay archivos
            if (selectedFiles.length === 0) {
                document.getElementById('submitBtn').disabled = true;
            }
        }

        // Validar formulario
        function validateForm() {
            console.log('🔍 Validando formulario...');

            // Verificar archivos
            if (selectedFiles.length === 0) {
                alert('❌ Debe seleccionar al menos un archivo');
                return false;
            }

            // Verificar campos obligatorios
            const company = document.querySelector('select[name="company_id"]').value ||
                document.querySelector('input[name="company_id"]')?.value;
            const docType = document.querySelector('select[name="document_type_id"]').value;

            if (!company || !docType) {
                alert('❌ Empresa y Tipo de Documento son obligatorios');
                return false;
            }

            console.log('✅ Formulario válido');

            // Mostrar loading
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i data-feather="loader"></i> Subiendo...';

            return true;
        }

        // Cargar departamentos
        async function loadDepartments() {
            const companyId = document.getElementById('companySelect').value;
            const departmentSelect = document.getElementById('departmentSelect');
            const folderSelect = document.getElementById('folderSelect');

            console.log('🏢 Cargando departamentos para empresa:', companyId);

            // Limpiar departamentos y carpetas
            departmentSelect.innerHTML = '<option value="">Cargando...</option>';
            folderSelect.innerHTML = '<option value="">Sin carpeta específica</option>';

            if (!companyId) {
                departmentSelect.innerHTML = '<option value="">Sin departamento</option>';
                return;
            }

            try {
                const response = await fetch('get_departments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'company_id=' + encodeURIComponent(companyId)
                });

                const data = await response.json();
                console.log('📋 Departamentos recibidos:', data);

                departmentSelect.innerHTML = '<option value="">Sin departamento</option>';

                if (data.success && data.departments) {
                    data.departments.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.id;
                        option.textContent = dept.name;
                        departmentSelect.appendChild(option);
                    });

                    console.log('✅ Departamentos cargados:', data.departments.length);
                } else {
                    console.warn('⚠️ No se encontraron departamentos:', data.message);
                }
            } catch (error) {
                console.error('❌ Error cargando departamentos:', error);
                departmentSelect.innerHTML = '<option value="">Error cargando departamentos</option>';
            }
        }

        // Cargar carpetas
        async function loadFolders() {
            const companyId = document.querySelector('select[name="company_id"]').value ||
                document.querySelector('input[name="company_id"]')?.value;
            const departmentId = document.querySelector('select[name="department_id"]').value ||
                document.querySelector('input[name="department_id"]')?.value;
            const folderSelect = document.getElementById('folderSelect');

            console.log('📁 Cargando carpetas para:', {
                companyId,
                departmentId
            });

            folderSelect.innerHTML = '<option value="">Sin carpeta específica</option>';

            if (!companyId || !departmentId) {
                return;
            }

            try {
                const response = await fetch('get_folders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `company_id=${encodeURIComponent(companyId)}&department_id=${encodeURIComponent(departmentId)}`
                });

                const data = await response.json();
                console.log('📂 Carpetas recibidas:', data);

                if (data.success && data.folders) {
                    data.folders.forEach(folder => {
                        const option = document.createElement('option');
                        option.value = folder.id;
                        option.textContent = folder.name;
                        folderSelect.appendChild(option);
                    });

                    console.log('✅ Carpetas cargadas:', data.folders.length);
                }
            } catch (error) {
                console.error('❌ Error cargando carpetas:', error);
            }
        }

        // Funciones auxiliares
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function getFileIcon(extension) {
            const iconMap = {
                'pdf': 'file-text',
                'doc': 'file-text',
                'docx': 'file-text',
                'xls': 'grid',
                'xlsx': 'grid',
                'ppt': 'monitor',
                'pptx': 'monitor',
                'jpg': 'image',
                'jpeg': 'image',
                'png': 'image',
                'gif': 'image',
                'zip': 'archive',
                'rar': 'archive',
                'txt': 'file-text'
            };
            return iconMap[extension] || 'file';
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

        function toggleSidebar() {
            // Solo funciona en móvil
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                const body = document.body;

                if (!sidebar) return;

                const isActive = sidebar.classList.contains('active');
                if (isActive) {
                    sidebar.classList.remove('active');
                    if (overlay) overlay.classList.remove('show');
                    body.style.overflow = '';
                } else {
                    sidebar.classList.add('active');
                    if (overlay) overlay.classList.add('show');
                    body.style.overflow = 'hidden';
                }
            }
        }

        function showSettings() {
            alert('Configuración estará disponible próximamente');
        }
    </script>
</body>

</html>