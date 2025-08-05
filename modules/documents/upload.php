<?php
// modules/documents/upload.php
// Módulo para subir documentos - DMS2 con Sistema de Permisos Integrado

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/permission_functions.php'; // NUEVO: Sistema de permisos

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();
$error = '';
$success = '';

// NUEVO: Verificar permisos para crear documentos
if (!hasUserPermission('create')) {
    $error = 'No tienes permisos para subir documentos. Contacta con tu administrador.';
    $canUpload = false;
} else {
    $canUpload = true;
}

// NUEVO: Obtener datos con restricciones aplicadas
$accessibleCompanies = $canUpload ? getAccessibleCompanies() : [];
$accessibleDocumentTypes = $canUpload ? getAccessibleDocumentTypes() : [];

// NUEVO: Mensaje informativo sobre restricciones
$restrictionsMessage = $canUpload ? getRestrictionsMessage() : null;

// Procesar subida de archivo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_document']) && $canUpload) {
    $documentName = trim($_POST['document_name'] ?? '');
    $documentTypeId = $_POST['document_type_id'] ?? '';
    $companyId = $_POST['company_id'] ?? '';
    $departmentId = $_POST['department_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');

    // Validaciones básicas
    if (empty($documentName)) {
        $error = 'El nombre del documento es requerido';
    } elseif (empty($documentTypeId)) {
        $error = 'Debe seleccionar un tipo de documento';
    } elseif (empty($companyId)) {
        $error = 'Debe seleccionar una empresa';
    } elseif (empty($_FILES['document_file']['name'])) {
        $error = 'Debe seleccionar un archivo';
    } else {
        // NUEVO: Verificar permisos específicos para la empresa seleccionada
        $userPerms = getUserPermissions();
        if (!empty($userPerms['restrictions']['companies']) && 
            !in_array($companyId, $userPerms['restrictions']['companies'])) {
            $error = 'No tienes permisos para subir documentos a esta empresa';
        }
        // NUEVO: Verificar permisos para el departamento si se especificó
        elseif ($departmentId && !empty($userPerms['restrictions']['departments']) && 
                !in_array($departmentId, $userPerms['restrictions']['departments'])) {
            $error = 'No tienes permisos para subir documentos a este departamento';
        }
        // NUEVO: Verificar permisos para el tipo de documento
        elseif (!empty($userPerms['restrictions']['document_types']) && 
                !in_array($documentTypeId, $userPerms['restrictions']['document_types'])) {
            $error = 'No tienes permisos para subir documentos de este tipo';
        } else {
            // Verificar el archivo
            $file = $_FILES['document_file'];
            $fileName = $file['name'];
            $fileTmpName = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileError = $file['error'];

            if ($fileError !== UPLOAD_ERR_OK) {
                $error = 'Error al subir el archivo';
            } else {
                // Obtener configuración del sistema
                $maxFileSize = getSystemConfig('max_file_size') ?? 20971520; // 20MB por defecto
                $allowedExtensions = json_decode(getSystemConfig('allowed_extensions') ?? '["pdf","doc","docx","xlsx","jpg","jpeg","png","gif"]', true);

                // Validar tamaño
                if ($fileSize > $maxFileSize) {
                    $error = 'El archivo es muy grande. Tamaño máximo: ' . formatBytes($maxFileSize);
                } else {
                    // Validar extensión
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (!in_array($fileExtension, $allowedExtensions)) {
                        $error = 'Tipo de archivo no permitido. Extensiones permitidas: ' . implode(', ', $allowedExtensions);
                    } else {
                        // Crear directorio si no existe
                        $uploadDir = '../../uploads/documents/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        // Generar nombre único para el archivo
                        $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
                        $filePath = $uploadDir . $uniqueFileName;

                        // Mover archivo
                        if (move_uploaded_file($fileTmpName, $filePath)) {
                            // Procesar tags
                            $tagsArray = [];
                            if (!empty($tags)) {
                                $tagsArray = array_map('trim', explode(',', $tags));
                                $tagsArray = array_filter($tagsArray);
                            }

                            // Guardar en base de datos
                            $documentData = [
                                'company_id' => $companyId,
                                'department_id' => $departmentId ?: null,
                                'document_type_id' => $documentTypeId,
                                'user_id' => $currentUser['id'],
                                'name' => $documentName,
                                'original_name' => $fileName,
                                'file_path' => 'uploads/documents/' . $uniqueFileName,
                                'file_size' => $fileSize,
                                'mime_type' => mime_content_type($filePath),
                                'description' => $description,
                                'tags' => json_encode($tagsArray),
                                'status' => 'active'
                            ];

                            if (insertRecord('documents', $documentData)) {
                                $success = 'Documento subido exitosamente';

                                // Log de actividad
                                logActivity(
                                    $currentUser['id'],
                                    'upload',
                                    'documents',
                                    null,
                                    'Usuario subió documento: ' . $documentName
                                );

                                // Limpiar formulario
                                $documentName = '';
                                $description = '';
                                $tags = '';
                            } else {
                                $error = 'Error al guardar el documento en la base de datos';
                                unlink($filePath); // Eliminar archivo si falla la BD
                            }
                        } else {
                            $error = 'Error al mover el archivo al directorio de destino';
                        }
                    }
                }
            }
        }
    }
}

// Función para formatear bytes
function formatBytes($size, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Documentos - DMS2</title>
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
                <h1>Subir Documentos</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(SessionManager::getFullName()); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>

                <div class="header-actions">
                    <button class="btn-icon" onclick="alert('Configuración próximamente')">
                        <i data-feather="settings"></i>
                    </button>
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <div class="upload-content">
            <div class="upload-container">
                <div class="upload-card">
                    <div class="upload-header">
                        <h2>Subir Nuevo Documento</h2>
                        <p>Seleccione un archivo y complete la información requerida</p>
                    </div>

                    <?php if (!$canUpload): ?>
                        <div class="alert alert-error">
                            <i data-feather="alert-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php else: ?>
                        
                        <!-- NUEVO: Mostrar mensaje de restricciones si las hay -->
                        <?php if ($restrictionsMessage): ?>
                            <div class="alert alert-info">
                                <i data-feather="info"></i>
                                <strong>Restricciones activas:</strong> <?php echo htmlspecialchars($restrictionsMessage); ?>
                            </div>
                        <?php endif; ?>

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

                        <form method="POST" enctype="multipart/form-data" class="upload-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="document_name">Nombre del Documento <span class="text-danger">*</span></label>
                                    <input type="text" id="document_name" name="document_name"
                                        class="form-control" required
                                        value="<?php echo htmlspecialchars($documentName ?? ''); ?>"
                                        placeholder="Ej: Factura 001-2024">
                                </div>

                                <div class="form-group">
                                    <label for="document_type_id">Tipo de Documento <span class="text-danger">*</span></label>
                                    <select id="document_type_id" name="document_type_id" class="form-control" required>
                                        <option value="">Seleccionar tipo...</option>
                                        <?php foreach ($accessibleDocumentTypes as $type): ?>
                                            <option value="<?php echo $type['id']; ?>">
                                                <?php echo htmlspecialchars($type['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($accessibleDocumentTypes)): ?>
                                        <small class="form-help text-muted">Sin tipos de documentos disponibles según tus permisos</small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="company_id">Empresa <span class="text-danger">*</span></label>
                                    <select id="company_id" name="company_id" class="form-control" required>
                                        <option value="">Seleccionar empresa...</option>
                                        <?php foreach ($accessibleCompanies as $company): ?>
                                            <option value="<?php echo $company['id']; ?>">
                                                <?php echo htmlspecialchars($company['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($accessibleCompanies)): ?>
                                        <small class="form-help text-muted">Sin empresas disponibles según tus permisos</small>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label for="department_id">Departamento</label>
                                    <select id="department_id" name="department_id" class="form-control">
                                        <option value="">Seleccionar departamento...</option>
                                    </select>
                                    <small class="form-help">Opcional - Se carga según la empresa seleccionada</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="document_file">Archivo <span class="text-danger">*</span></label>
                                <div class="file-upload-area" id="fileUploadArea">
                                    <input type="file" name="document_file" id="document_file" 
                                           class="file-input" required accept=".pdf,.doc,.docx,.xlsx,.jpg,.jpeg,.png,.gif">
                                    
                                    <div class="file-upload-content" id="fileUploadContent">
                                        <i data-feather="upload-cloud"></i>
                                        <p>Arrastra tu archivo aquí o <span class="file-browse">haz clic para seleccionar</span></p>
                                        <small>Tamaño máximo: 20MB | Formatos: PDF, DOC, DOCX, XLSX, JPG, PNG, GIF</small>
                                    </div>
                                    
                                    <div class="file-preview" id="filePreview" style="display: none;">
                                        <div class="file-info">
                                            <i data-feather="file"></i>
                                            <div class="file-details">
                                                <span class="file-name" id="fileName"></span>
                                                <span class="file-size" id="fileSize"></span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-remove" onclick="clearFile()">
                                            <i data-feather="x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Descripción</label>
                                <textarea id="description" name="description" class="form-control" rows="3" 
                                          placeholder="Descripción opcional del documento"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="tags">Etiquetas</label>
                                <input type="text" id="tags" name="tags" class="form-control" 
                                       value="<?php echo htmlspecialchars($tags ?? ''); ?>"
                                       placeholder="Ej: factura, 2024, cliente (separadas por comas)">
                                <small class="form-help">Separa las etiquetas con comas para facilitar la búsqueda</small>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="upload_document" class="btn btn-primary btn-upload" 
                                        <?php echo (!$canUpload || empty($accessibleCompanies) || empty($accessibleDocumentTypes)) ? 'disabled' : ''; ?>>
                                    <i data-feather="upload"></i>
                                    Subir Documento
                                </button>
                                <a href="inbox.php" class="btn btn-secondary">
                                    <i data-feather="arrow-left"></i>
                                    Volver a la Bandeja
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script>
        // Actualizar hora
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleDateString('es-ES', {
                day: '2-digit', month: '2-digit', year: 'numeric'
            }) + ' ' + now.toLocaleTimeString('es-ES', {
                hour: '2-digit', minute: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 60000);
            
            setupFileUpload();
            setupDepartmentLoader();
        });

        // NUEVO: Configurar carga de departamentos con permisos
        function setupDepartmentLoader() {
            const companySelect = document.getElementById('company_id');
            const departmentSelect = document.getElementById('department_id');
            
            if (companySelect && departmentSelect) {
                companySelect.addEventListener('change', function() {
                    const companyId = this.value;
                    
                    // Limpiar opciones
                    departmentSelect.innerHTML = '<option value="">Cargando...</option>';
                    
                    if (companyId) {
                        // NUEVO: Usar API con permisos aplicados
                        fetch('../../api/get_departments_with_permissions.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                company_id: companyId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            departmentSelect.innerHTML = '<option value="">Seleccionar departamento...</option>';
                            
                            if (data.success && data.departments) {
                                data.departments.forEach(dept => {
                                    const option = document.createElement('option');
                                    option.value = dept.id;
                                    option.textContent = dept.name;
                                    departmentSelect.appendChild(option);
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            departmentSelect.innerHTML = '<option value="">Error cargando departamentos</option>';
                        });
                    } else {
                        departmentSelect.innerHTML = '<option value="">Seleccionar departamento...</option>';
                    }
                });
            }
        }

        // Configurar subida de archivos
        function setupFileUpload() {
            const fileInput = document.getElementById('document_file');
            const fileUploadArea = document.getElementById('fileUploadArea');
            const fileUploadContent = document.getElementById('fileUploadContent');
            const filePreview = document.getElementById('filePreview');
            
            if (!fileInput || !fileUploadArea) return;

            // Click en área para abrir selector
            fileUploadArea.addEventListener('click', function(e) {
                if (e.target.closest('.file-preview')) return;
                fileInput.click();
            });

            // Drag and drop
            fileUploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('drag-over');
            });

            fileUploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!this.contains(e.relatedTarget)) {
                    this.classList.remove('drag-over');
                }
            });

            fileUploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleFileSelect(files[0]);
                }
            });

            // Cambio en input
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    handleFileSelect(this.files[0]);
                }
            });
        }

        function handleFileSelect(file) {
            const filePreview = document.getElementById('filePreview');
            const fileUploadContent = document.getElementById('fileUploadContent');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');

            if (fileName) fileName.textContent = file.name;
            if (fileSize) fileSize.textContent = formatFileSize(file.size);

            if (fileUploadContent) fileUploadContent.style.display = 'none';
            if (filePreview) filePreview.style.display = 'flex';

            feather.replace();
        }

        function clearFile() {
            const fileInput = document.getElementById('document_file');
            const filePreview = document.getElementById('filePreview');
            const fileUploadContent = document.getElementById('fileUploadContent');

            if (fileInput) fileInput.value = '';
            if (filePreview) filePreview.style.display = 'none';
            if (fileUploadContent) fileUploadContent.style.display = 'flex';

            feather.replace();
        }

        function formatFileSize(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('collapsed');
            }
        }
    </script>
</body>
</html>