<?php
// Tu código existente...
require_once '../../config/session.php';
require_once '../../config/database.php';
// ... otros requires que ya tenías

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

function getUserPermissions($userId) {
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
                'companies' => [],      // Vacío = acceso a todas
                'departments' => [],    // Vacío = acceso a todos
                'document_types' => []  // Vacío = acceso a todos
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

            // Mapear permisos nuevos a los antiguos
            if (isset($permissions['view_files']) && $permissions['view_files'] === true) {
                $mergedPermissions['view'] = true;
            }
            if (isset($permissions['download_files']) && $permissions['download_files'] === true) {
                $mergedPermissions['download'] = true;
            }
            if (isset($permissions['upload_files']) && $permissions['upload_files'] === true) {
                $mergedPermissions['create'] = true;
            }
            if (isset($permissions['create_folders']) && $permissions['create_folders'] === true) {
                $mergedPermissions['edit'] = true;
            }
            if (isset($permissions['delete_files']) && $permissions['delete_files'] === true) {
                $mergedPermissions['delete'] = true;
            }

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

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Obtener la ruta actual del explorador
$currentPath = isset($_GET['path']) ? trim($_GET['path']) : '';
$pathParts = $currentPath ? explode('/', trim($currentPath, '/')) : [];

// Determinar contexto de subida basado en la ruta
$uploadContext = [
    'company_id' => null,
    'department_id' => null,
    'folder_id' => null,
    'context_name' => 'General'
];

try {
    $database = new Database();
    $pdo = $database->getConnection();

    if (count($pathParts) >= 1 && is_numeric($pathParts[0])) {
        $uploadContext['company_id'] = (int)$pathParts[0];

        $companyStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
        $companyStmt->execute([$uploadContext['company_id']]);
        $company = $companyStmt->fetch();
        if ($company) {
            $uploadContext['context_name'] = $company['name'];
        }

        if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
            $uploadContext['department_id'] = (int)$pathParts[1];

            $deptStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
            $deptStmt->execute([$uploadContext['department_id']]);
            $department = $deptStmt->fetch();
            if ($department) {
                $uploadContext['context_name'] = $company['name'] . ' → ' . $department['name'];
            }

            if (count($pathParts) >= 3 && strpos($pathParts[2], 'folder_') === 0) {
                $uploadContext['folder_id'] = (int)substr($pathParts[2], 7);

                $folderStmt = $pdo->prepare("SELECT name FROM document_folders WHERE id = ?");
                $folderStmt->execute([$uploadContext['folder_id']]);
                $folder = $folderStmt->fetch();
                if ($folder) {
                    $uploadContext['context_name'] = $company['name'] . ' → ' . $department['name'] . ' → ' . $folder['name'];
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error determining upload context: " . $e->getMessage());
}

// Procesar formulario
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $pdo = $database->getConnection();

        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo');
        }

        $file = $_FILES['document'];
        $maxFileSize = 20 * 1024 * 1024;

        if ($file['size'] > $maxFileSize) {
            throw new Exception('El archivo es demasiado grande (máximo 20MB)');
        }

        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip', 'rar'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Tipo de archivo no permitido');
        }

        $documentName = trim($_POST['document_name']) ?: pathinfo($file['name'], PATHINFO_FILENAME);
        $description = trim($_POST['description']) ?: '';
        $documentTypeId = intval($_POST['document_type_id']) ?: null;
        $tags = $_POST['tags'] ? explode(',', $_POST['tags']) : [];
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags);

        // Usar contexto del upload
        $companyId = intval($_POST['company_id']) ?: $uploadContext['company_id'];
        $departmentId = intval($_POST['department_id']) ?: $uploadContext['department_id'];
        $folderId = intval($_POST['folder_id']) ?: $uploadContext['folder_id'];

        $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $uploadDir = '../../uploads/documents/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filePath = $uploadDir . $uniqueFileName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Error al guardar el archivo');
        }

        $insertQuery = "
            INSERT INTO documents (
                company_id, department_id, folder_id, document_type_id, user_id,
                name, original_name, file_path, file_size, mime_type, 
                description, tags, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ";

        $relativePath = 'uploads/documents/' . $uniqueFileName;

        $stmt = $pdo->prepare($insertQuery);
        $result = $stmt->execute([
            $companyId,
            $departmentId,
            $folderId,
            $documentTypeId,
            $currentUser['id'],
            $documentName,
            $file['name'],
            $relativePath,
            $file['size'],
            $file['type'],
            $description,
            json_encode($tags)
        ]);

        if ($result) {
            $documentId = $pdo->lastInsertId();

            $logQuery = "
                INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
                VALUES (?, 'create', 'documents', ?, ?, NOW())
            ";
            $logStmt = $pdo->prepare($logQuery);
            $logStmt->execute([
                $currentUser['id'],
                $documentId,
                "Documento '{$documentName}' subido en " . $uploadContext['context_name']
            ]);

            $success = 'Documento subido exitosamente';

            // Redirigir de vuelta al explorador
            $redirectUrl = 'inbox.php';
            if ($currentPath) {
                $redirectUrl .= '?path=' . urlencode($currentPath);
            }

            header("Location: $redirectUrl");
            exit;
        } else {
            throw new Exception('Error al guardar el documento en la base de datos');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();

        if (isset($filePath) && file_exists($filePath)) {
            unlink($filePath);
        }
    }
}

// Obtener tipos de documentos para el select
try {
    $database = new Database();
    $pdo = $database->getConnection();
    $typesQuery = "SELECT id, name, description FROM document_types WHERE status = 'active' ORDER BY name";
    $typesStmt = $pdo->prepare($typesQuery);
    $typesStmt->execute();
    $documentTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);

    $companiesQuery = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
    $companiesStmt = $pdo->prepare($companiesQuery);
    $companiesStmt->execute();
    $companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);

    $departments = [];
    if ($uploadContext['company_id']) {
        $deptsQuery = "SELECT id, name FROM departments WHERE company_id = ? AND status = 'active' ORDER BY name";
        $deptsStmt = $pdo->prepare($deptsQuery);
        $deptsStmt->execute([$uploadContext['company_id']]);
        $departments = $deptsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $folders = [];
    if ($uploadContext['department_id']) {
        $foldersQuery = "SELECT id, name, folder_color FROM document_folders WHERE company_id = ? AND department_id = ? AND is_active = 1 ORDER BY name";
        $foldersStmt = $pdo->prepare($foldersQuery);
        $foldersStmt->execute([$uploadContext['company_id'], $uploadContext['department_id']]);
        $folders = $foldersStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $documentTypes = [];
    $companies = [];
    $departments = [];
    $folders = [];
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
                <div class="upload-card">
                    <div class="upload-header">
                        <p>Seleccione un archivo y complete la información requerida</p>

                        <?php if ($uploadContext['context_name'] !== 'General'): ?>
                            <div class="upload-context">
                                <div class="context-info">
                                    <i data-feather="map-pin"></i>
                                    <span>Subiendo a: <strong><?= htmlspecialchars($uploadContext['context_name']) ?></strong></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

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

                    <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="document_name">Nombre del Documento *</label>
                                <input type="text" id="document_name" name="document_name" class="form-control" required placeholder="Nombre del documento">
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
                            <label>Archivo *</label>
                            <div class="file-upload-area" id="fileUploadArea" onclick="document.getElementById('fileInput').click()">
                                <input type="file" name="document" id="fileInput" class="file-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt,.zip,.rar" required>
                                <div class="file-upload-content" id="uploadContent">
                                    <i data-feather="upload-cloud" id="uploadIcon"></i>
                                    <p id="uploadText">Haz clic para seleccionar o arrastra tu archivo aquí</p>
                                    <small id="uploadHint">Tamaños permitidos: PDF, DOC, XLS, PPT, imágenes, TXT, ZIP (máx. 20MB)</small>
                                </div>
                                <div class="file-preview" id="filePreview" style="display: none;">
                                    <div class="file-info">
                                        <div class="file-icon" id="fileIcon">
                                            <i data-feather="file"></i>
                                        </div>
                                        <div class="file-details">
                                            <div class="file-name" id="fileName"></div>
                                            <div class="file-size" id="fileSize"></div>
                                        </div>
                                    </div>
                                    <button type="button" class="remove-file" onclick="removeFile(event)">
                                        <i data-feather="x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Descripción</label>
                            <textarea id="description" name="description" class="form-control" rows="3" placeholder="Descripción opcional del documento"></textarea>
                        </div>

                        <div class="form-actions">
                            <a href="inbox.php<?= $currentPath ? '?path=' . urlencode($currentPath) : '' ?>" class="btn btn-secondary">
                                <i data-feather="x"></i>
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-upload" id="submitBtn" disabled>
                                <i data-feather="upload"></i>
                                Subir Documento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal" style="display: none;">
        <div class="modal-content preview-modal">
            <div class="modal-header">
                <h3 id="previewTitle">Vista Previa del Archivo</h3>
                <button class="modal-close" onclick="closePreview()">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="previewContent"></div>
            </div>
        </div>
    </div>

    <style>
        .upload-context {
            margin-top: 1rem;
            padding: 1rem;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .context-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            font-size: 0.875rem;
        }

        .file-upload-area {
            position: relative;
            min-height: 180px;
            border: 2px dashed var(--upload-border);
            border-radius: var(--radius-lg);
            background: var(--upload-bg);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-upload-area:hover {
            border-color: var(--primary-color);
            background: var(--upload-hover);
        }

        .file-upload-area.drag-over {
            border-color: var(--primary-color);
            background: var(--upload-active);
            transform: scale(1.01);
        }

        .file-upload-area.has-file {
            border-color: var(--success-color);
            background: var(--success-light);
        }

        .file-upload-content {
            text-align: center;
            padding: 2rem;
        }

        .file-upload-content i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .file-upload-content p {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
            font-weight: 500;
        }

        .file-upload-content small {
            color: var(--text-muted);
            font-size: 0.875rem;
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

        .file-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 1.5rem;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .file-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .file-details {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .file-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .file-size {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .remove-file {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .remove-file:hover {
            background: #dc2626;
            transform: scale(1.1);
        }

        .tags-container {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.5rem;
            background: var(--bg-primary);
            min-height: 42px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }

        .tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .tag {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .tag-remove {
            cursor: pointer;
            color: #ef4444;
            font-weight: bold;
            margin-left: 0.25rem;
        }

        .tag-remove:hover {
            color: #dc2626;
        }

        .tag-input {
            border: none;
            outline: none;
            flex: 1;
            min-width: 120px;
            padding: 0.25rem;
            font-size: 0.875rem;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .preview-modal {
            max-width: 90vw;
            max-height: 90vh;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow: auto;
        }

        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: #f3f4f6;
        }

        #previewContent img {
            max-width: 100%;
            max-height: 60vh;
            object-fit: contain;
        }
    </style>

    <script>
        let selectedFile = null;
        let tags = [];

        // Drag & Drop
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('fileInput');
        const uploadContent = document.getElementById('uploadContent');
        const filePreview = document.getElementById('filePreview');
        const submitBtn = document.getElementById('submitBtn');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            fileUploadArea.classList.add('drag-over');
        }

        function unhighlight() {
            fileUploadArea.classList.remove('drag-over');
        }

        fileUploadArea.addEventListener('drop', handleDrop, false);
        fileInput.addEventListener('change', handleFileSelect);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        }

        function handleFileSelect(e) {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0]);
            }
        }

        function handleFile(file) {
            selectedFile = file;
            displayFilePreview(file);

            // Auto-completar nombre si está vacío
            const nameInput = document.getElementById('document_name');
            if (!nameInput.value.trim()) {
                nameInput.value = file.name.replace(/\.[^/.]+$/, "");
            }

            submitBtn.disabled = false;
        }

        function displayFilePreview(file) {
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const fileIcon = document.getElementById('fileIcon');

            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);

            // Cambiar icono según el tipo
            const extension = file.name.split('.').pop().toLowerCase();
            let iconName = 'file';

            if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
                iconName = 'image';
                // Mostrar vista previa de imagen
                if (file.size < 5 * 1024 * 1024) { // Solo para archivos menores a 5MB
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        fileIcon.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;">`;
                    };
                    reader.readAsDataURL(file);
                }
            } else if (extension === 'pdf') {
                iconName = 'file-text';
                fileIcon.style.background = '#ef4444';
            } else if (['doc', 'docx'].includes(extension)) {
                iconName = 'file-text';
                fileIcon.style.background = '#2563eb';
            } else if (['xls', 'xlsx'].includes(extension)) {
                iconName = 'grid';
                fileIcon.style.background = '#10b981';
            }

            if (!fileIcon.querySelector('img')) {
                fileIcon.innerHTML = `<i data-feather="${iconName}"></i>`;
            }

            uploadContent.style.display = 'none';
            filePreview.style.display = 'flex';
            fileUploadArea.classList.add('has-file');

            feather.replace();
        }

        function removeFile(e) {
            e.stopPropagation();
            selectedFile = null;
            fileInput.value = '';

            uploadContent.style.display = 'block';
            filePreview.style.display = 'none';
            fileUploadArea.classList.remove('has-file');

            submitBtn.disabled = true;

            // Limpiar vista previa de imagen si existe
            const fileIcon = document.getElementById('fileIcon');
            fileIcon.innerHTML = '<i data-feather="file"></i>';
            fileIcon.style.background = 'var(--primary-color)';

            feather.replace();
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Vista previa de archivos
        function previewFile() {
            if (!selectedFile) return;

            const modal = document.getElementById('previewModal');
            const title = document.getElementById('previewTitle');
            const content = document.getElementById('previewContent');

            title.textContent = `Vista Previa: ${selectedFile.name}`;

            if (selectedFile.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    content.innerHTML = `<img src="${e.target.result}" alt="Vista previa">`;
                };
                reader.readAsDataURL(selectedFile);
            } else if (selectedFile.type === 'application/pdf') {
                const reader = new FileReader();
                reader.onload = function(e) {
                    content.innerHTML = `<embed src="${e.target.result}" type="application/pdf" width="100%" height="500px">`;
                };
                reader.readAsDataURL(selectedFile);
            } else {
                content.innerHTML = `<div style="text-align: center; padding: 2rem;">
                    <i data-feather="file" style="width: 48px; height: 48px; color: var(--text-muted);"></i>
                    <p style="margin-top: 1rem; color: var(--text-muted);">Vista previa no disponible para este tipo de archivo</p>
                    <p><strong>Nombre:</strong> ${selectedFile.name}</p>
                    <p><strong>Tamaño:</strong> ${formatFileSize(selectedFile.size)}</p>
                    <p><strong>Tipo:</strong> ${selectedFile.type}</p>
                </div>`;
            }

            modal.style.display = 'flex';
            feather.replace();
        }

        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
        }

        // Sistema de etiquetas
        function handleTagInput(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const input = e.target;
                const tag = input.value.trim();

                if (tag && !tags.includes(tag)) {
                    tags.push(tag);
                    addTagToUI(tag);
                    input.value = '';
                    updateTagsValue();
                }
            }
        }

        function addTagToUI(tag) {
            const tagsList = document.getElementById('tagsList');
            const tagElement = document.createElement('span');
            tagElement.className = 'tag';
            tagElement.innerHTML = `
                ${tag}
                <span class="tag-remove" onclick="removeTag('${tag}', this.parentElement)">×</span>
            `;
            tagsList.appendChild(tagElement);
        }

        function removeTag(tag, element) {
            tags = tags.filter(t => t !== tag);
            element.remove();
            updateTagsValue();
        }

        function updateTagsValue() {
            document.getElementById('tagsValue').value = tags.join(',');
        }

        // Cargar departamentos y carpetas dinámicamente
        async function loadDepartments() {
            const companyId = document.getElementById('companySelect').value;
            const departmentSelect = document.getElementById('departmentSelect');
            const folderSelect = document.getElementById('folderSelect');

            departmentSelect.innerHTML = '<option value="">Sin departamento</option>';
            folderSelect.innerHTML = '<option value="">Sin carpeta específica</option>';

            if (!companyId) return;

            try {
                const response = await fetch(`get_departments.php?company_id=${companyId}`);
                const data = await response.json();

                if (data.success) {
                    data.departments.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.id;
                        option.textContent = dept.name;
                        departmentSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading departments:', error);
            }
        }

        async function loadFolders() {
            const companyId = document.getElementById('companySelect').value;
            const departmentId = document.getElementById('departmentSelect').value;
            const folderSelect = document.getElementById('folderSelect');

            folderSelect.innerHTML = '<option value="">Sin carpeta específica</option>';

            if (!companyId || !departmentId) return;

            try {
                const response = await fetch(`get_folders.php?company_id=${companyId}&department_id=${departmentId}`);
                const data = await response.json();

                if (data.success) {
                    data.folders.forEach(folder => {
                        const option = document.createElement('option');
                        option.value = folder.id;
                        option.textContent = folder.name;
                        folderSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading folders:', error);
            }
        }

        // Funciones de sistema
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

        // Cortar y pegar archivos (funcionalidad futura)
        let clipboard = {
            file: null,
            operation: null // 'cut' o 'copy'
        };

        function cutFile(fileId) {
            clipboard.file = fileId;
            clipboard.operation = 'cut';
            console.log('Archivo cortado:', fileId);
            // Aquí podrías agregar indicadores visuales
        }

        function copyFile(fileId) {
            clipboard.file = fileId;
            clipboard.operation = 'copy';
            console.log('Archivo copiado:', fileId);
        }

        async function pasteFile(targetFolderId = null) {
            if (!clipboard.file) {
                alert('No hay archivo para pegar');
                return;
            }

            try {
                const response = await fetch('move_document.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        document_id: clipboard.file,
                        folder_id: targetFolderId,
                        operation: clipboard.operation
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert(`Archivo ${clipboard.operation === 'cut' ? 'movido' : 'copiado'} exitosamente`);
                    if (clipboard.operation === 'cut') {
                        clipboard.file = null;
                        clipboard.operation = null;
                    }
                    location.reload();
                } else {
                    alert(`Error: ${result.message}`);
                }
            } catch (error) {
                alert('Error de conexión');
                console.error(error);
            }
        }

        // Validación del formulario
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (!selectedFile) {
                e.preventDefault();
                alert('Por favor selecciona un archivo');
                return;
            }

            const companyId = document.querySelector('select[name="company_id"]').value ||
                document.querySelector('input[name="company_id"]')?.value;

            if (!companyId) {
                e.preventDefault();
                alert('Por favor selecciona una empresa');
                return;
            }

            // Deshabilitar botón y mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i data-feather="loader"></i> Subiendo...';

            // Simular progreso (opcional)
            const progressBar = document.createElement('div');
            progressBar.className = 'upload-progress';
            progressBar.innerHTML = '<div class="upload-progress-bar"></div>';
            document.body.appendChild(progressBar);
            progressBar.style.display = 'block';

            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                progressBar.querySelector('.upload-progress-bar').style.width = progress + '%';
            }, 200);

            // Limpiar intervalo después de un tiempo
            setTimeout(() => {
                clearInterval(interval);
                progressBar.querySelector('.upload-progress-bar').style.width = '100%';
            }, 3000);
        });

        // Funciones de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl+V para pegar
            if (e.ctrlKey && e.key === 'v' && clipboard.file) {
                e.preventDefault();
                pasteFile();
            }

            // Escape para cerrar modal
            if (e.key === 'Escape') {
                closePreview();
            }

            // Ctrl+P para vista previa
            if (e.ctrlKey && e.key === 'p' && selectedFile) {
                e.preventDefault();
                previewFile();
            }
        });

        // Cerrar modal al hacer click fuera
        document.getElementById('previewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreview();
            }
        });

        // Agregar botón de vista previa al archivo seleccionado
        function addPreviewButton() {
            if (!selectedFile) return;

            const previewBtn = document.createElement('button');
            previewBtn.type = 'button';
            previewBtn.className = 'preview-btn';
            previewBtn.innerHTML = '<i data-feather="eye"></i>';
            previewBtn.onclick = previewFile;
            previewBtn.title = 'Vista previa (Ctrl+P)';

            const fileInfo = document.querySelector('.file-info');
            if (fileInfo && !fileInfo.querySelector('.preview-btn')) {
                fileInfo.appendChild(previewBtn);
                feather.replace();
            }
        }

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 60000);

            // Si hay contexto predefinido, cargar datos
            const companyId = document.querySelector('input[name="company_id"]')?.value;
            if (companyId && !<?= $uploadContext['department_id'] ? 'true' : 'false' ?>) {
                loadDepartments();
            }

            const departmentId = document.querySelector('input[name="department_id"]')?.value;
            if (departmentId && !<?= $uploadContext['folder_id'] ? 'true' : 'false' ?>) {
                loadFolders();
            }

            console.log('📤 Sistema de upload iniciado con diseño original');
            console.log('Contexto actual:', '<?= $uploadContext['context_name'] ?>');
        });

        // Agregar estilos CSS adicionales para los nuevos elementos
        const additionalStyles = `
            <style>
                .preview-btn {
                    background: var(--info-color);
                    color: white;
                    border: none;
                    border-radius: 4px;
                    width: 32px;
                    height: 32px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-left: 1rem;
                    transition: all 0.2s;
                }
                
                .preview-btn:hover {
                    background: var(--info-light);
                    transform: scale(1.1);
                }
                
                .upload-progress {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 3px;
                    background: rgba(0,0,0,0.1);
                    z-index: 9998;
                    display: none;
                }
                
                .upload-progress-bar {
                    height: 100%;
                    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
                    width: 0%;
                    transition: width 0.3s ease;
                }
                
                .file-actions {
                    display: flex;
                    gap: 0.5rem;
                    margin-left: auto;
                }
                
                .btn.loading {
                    pointer-events: none;
                    opacity: 0.7;
                }
                
                .btn.loading i {
                    animation: spin 1s linear infinite;
                }
                
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                
                /* Mejoras responsive */
                @media (max-width: 768px) {
                    .form-row {
                        grid-template-columns: 1fr;
                    }
                    
                    .file-upload-area {
                        min-height: 120px;
                    }
                    
                    .file-upload-content {
                        padding: 1rem;
                    }
                    
                    .preview-modal {
                        max-width: 95vw;
                        margin: 1rem;
                    }
                }
            </style>
        `;

        document.head.insertAdjacentHTML('beforeend', additionalStyles);
    </script>
</body>

</html>