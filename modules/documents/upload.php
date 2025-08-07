<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

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
        
        // Obtener nombre de la empresa
        $companyStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
        $companyStmt->execute([$uploadContext['company_id']]);
        $company = $companyStmt->fetch();
        if ($company) {
            $uploadContext['context_name'] = $company['name'];
        }
        
        if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
            $uploadContext['department_id'] = (int)$pathParts[1];
            
            // Obtener nombre del departamento
            $deptStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
            $deptStmt->execute([$uploadContext['department_id']]);
            $department = $deptStmt->fetch();
            if ($department) {
                $uploadContext['context_name'] = $company['name'] . ' → ' . $department['name'];
            }
            
            if (count($pathParts) >= 3 && strpos($pathParts[2], 'folder_') === 0) {
                $uploadContext['folder_id'] = (int)substr($pathParts[2], 7);
                
                // Obtener nombre de la carpeta
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
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Validar archivo
        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo');
        }
        
        $file = $_FILES['document'];
        $maxFileSize = 20 * 1024 * 1024; // 20MB
        
        if ($file['size'] > $maxFileSize) {
            throw new Exception('El archivo es demasiado grande (máximo 20MB)');
        }
        
        // Validar extensión
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip', 'rar'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Tipo de archivo no permitido');
        }
        
        // Obtener datos del formulario
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
        
        // Generar nombre único para el archivo
        $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $uploadDir = '../../uploads/documents/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filePath = $uploadDir . $uniqueFileName;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Error al guardar el archivo');
        }
        
        // Guardar en la base de datos
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
            
            // Log de actividad
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
            
            $message = 'Documento subido exitosamente';
            $messageType = 'success';
            
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
        $message = $e->getMessage();
        $messageType = 'error';
        
        // Eliminar archivo si se subió pero falló la BD
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
    
    // Obtener empresas para el select (si no hay contexto específico)
    $companiesQuery = "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name";
    $companiesStmt = $pdo->prepare($companiesQuery);
    $companiesStmt->execute();
    $companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener departamentos para la empresa seleccionada
    $departments = [];
    if ($uploadContext['company_id']) {
        $deptsQuery = "SELECT id, name FROM departments WHERE company_id = ? AND status = 'active' ORDER BY name";
        $deptsStmt = $pdo->prepare($deptsQuery);
        $deptsStmt->execute([$uploadContext['company_id']]);
        $departments = $deptsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener carpetas para el departamento seleccionado
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
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        .upload-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .context-info {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-left: 4px solid var(--primary-color);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .context-info h3 {
            margin: 0 0 0.5rem 0;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .file-drop-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8fafc;
            cursor: pointer;
        }
        
        .file-drop-zone.dragover {
            border-color: var(--primary-color);
            background: rgba(212, 175, 55, 0.1);
        }
        
        .file-drop-zone.has-file {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }
        
        .drop-icon {
            font-size: 3rem;
            color: #94a3b8;
            margin-bottom: 1rem;
        }
        
        .file-info {
            display: none;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-top: 1rem;
        }
        
        .file-info.show {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .form-group-full {
            grid-column: 1 / -1;
        }
        
        .tags-input {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            min-height: 42px;
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
        }
        
        .tag-input {
            border: none;
            outline: none;
            flex: 1;
            min-width: 120px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                <a href="inbox.php<?= $currentPath ? '?path=' . urlencode($currentPath) : '' ?>" class="btn-secondary">
                    <i data-feather="arrow-left"></i>
                    <span>Volver al Explorador</span>
                </a>
            </div>
        </header>
        
        <div class="upload-container">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i data-feather="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="context-info">
                <h3>
                    <i data-feather="folder"></i>
                    Ubicación de subida
                </h3>
                <p><?= htmlspecialchars($uploadContext['context_name']) ?></p>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="file-drop-zone" id="dropZone">
                    <div class="drop-icon">
                        <i data-feather="upload-cloud" id="dropIcon"></i>
                    </div>
                    <h3 id="dropTitle">Arrastra tu archivo aquí</h3>
                    <p id="dropSubtitle">o haz clic para seleccionar</p>
                    <input type="file" name="document" id="fileInput" style="display: none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt,.zip,.rar">
                    
                    <div class="file-info" id="fileInfo">
                        <div class="file-details">
                            <strong id="fileName"></strong>
                            <span id="fileSize"></span>
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nombre del documento</label>
                        <input type="text" name="document_name" class="form-control" placeholder="Nombre personalizado (opcional)">
                        <small class="form-help">Si se deja vacío, se usará el nombre del archivo</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de documento</label>
                        <select name="document_type_id" class="form-control">
                            <option value="">Seleccionar tipo</option>
                            <?php foreach ($documentTypes as $type): ?>
                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Empresa</label>
                        <select name="company_id" id="companySelect" class="form-control" onchange="loadDepartments()" <?= $uploadContext['company_id'] ? 'disabled' : '' ?>>
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
                        <label class="form-label">Departamento</label>
                        <select name="department_id" id="departmentSelect" class="form-control" onchange="loadFolders()" <?= $uploadContext['department_id'] ? 'disabled' : '' ?>>
                            <option value="">Seleccionar departamento</option>
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
                    
                    <div class="form-group">
                        <label class="form-label">Carpeta (opcional)</label>
                        <select name="folder_id" id="folderSelect" class="form-control" <?= $uploadContext['folder_id'] ? 'disabled' : '' ?>>
                            <option value="">Sin carpeta</option>
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
                    
                    <div class="form-group form-group-full">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Descripción del documento"></textarea>
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label class="form-label">Etiquetas</label>
                        <div class="tags-input" id="tagsContainer">
                            <input type="text" class="tag-input" placeholder="Añadir etiqueta..." onkeypress="handleTagInput(event)">
                        </div>
                        <input type="hidden" name="tags" id="tagsValue">
                        <small class="form-help">Presiona Enter para añadir etiquetas</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="inbox.php<?= $currentPath ? '?path=' . urlencode($currentPath) : '' ?>" class="btn-secondary">
                        <i data-feather="x"></i>
                        <span>Cancelar</span>
                    </a>
                    <button type="submit" class="btn-create" id="submitBtn" disabled>
                        <i data-feather="upload"></i>
                        <span>Subir Documento</span>
                    </button>
                </div>
            </form>
        </div>
    </main>
    
    <script>
        let selectedFile = null;
        let tags = [];
        
        // Sistema de drag & drop
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const submitBtn = document.getElementById('submitBtn');
        
        dropZone.addEventListener('click', () => fileInput.click());
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0]);
            }
        });
        
        function handleFile(file) {
            selectedFile = file;
            
            // Actualizar UI
            document.getElementById('dropTitle').textContent = 'Archivo seleccionado';
            document.getElementById('dropSubtitle').textContent = 'Haz clic para cambiar';
            document.getElementById('dropIcon').setAttribute('data-feather', 'file');
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = formatFileSize(file.size);
            
            dropZone.classList.add('has-file');
            fileInfo.classList.add('show');
            submitBtn.disabled = false;
            
            // Auto-llenar nombre si está vacío
            const nameInput = document.querySelector('input[name="document_name"]');
            if (!nameInput.value) {
                nameInput.value = file.name.replace(/\.[^/.]+$/, "");
            }
            
            feather.replace();
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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
            const container = document.getElementById('tagsContainer');
            const tagInput = container.querySelector('.tag-input');
            
            const tagElement = document.createElement('span');
            tagElement.className = 'tag';
            tagElement.innerHTML = `
                ${tag}
                <span class="tag-remove" onclick="removeTag('${tag}', this.parentElement)">×</span>
            `;
            
            container.insertBefore(tagElement, tagInput);
        }
        
        function removeTag(tag, element) {
            tags = tags.filter(t => t !== tag);
            element.remove();
            updateTagsValue();
        }
        
        function updateTagsValue() {
            document.getElementById('tagsValue').value = tags.join(',');
        }
        
        // Cargar departamentos dinámicamente
        async function loadDepartments() {
            const companyId = document.getElementById('companySelect').value;
            const departmentSelect = document.getElementById('departmentSelect');
            const folderSelect = document.getElementById('folderSelect');
            
            // Limpiar selects
            departmentSelect.innerHTML = '<option value="">Seleccionar departamento</option>';
            folderSelect.innerHTML = '<option value="">Sin carpeta</option>';
            
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
        
        // Cargar carpetas dinámicamente
        async function loadFolders() {
            const companyId = document.getElementById('companySelect').value;
            const departmentId = document.getElementById('departmentSelect').value;
            const folderSelect = document.getElementById('folderSelect');
            
            folderSelect.innerHTML = '<option value="">Sin carpeta</option>';
            
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
        
        function toggleSidebar() {
            console.log('Toggle sidebar');
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
            
            // Deshabilitar el botón para evitar doble envío
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i data-feather="loader"></i> <span>Subiendo...</span>';
        });
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            
            // Si hay contexto predefinido, cargar departamentos y carpetas
            const companyId = document.querySelector('input[name="company_id"]')?.value;
            if (companyId && !<?= $uploadContext['department_id'] ? 'true' : 'false' ?>) {
                loadDepartments();
            }
            
            const departmentId = document.querySelector('input[name="department_id"]')?.value;
            if (departmentId && !<?= $uploadContext['folder_id'] ? 'true' : 'false' ?>) {
                loadFolders();
            }
        });
    </script>
</body>
</html>