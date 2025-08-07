<?php
// modules/documents/upload.php
// Módulo para subir documentos - DMS2 con Prioridad de Grupos

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();
$error = '';
$success = '';

/**
 * Obtener restricciones del usuario basadas en sus grupos
 * Los grupos tienen PRIORIDAD sobre la empresa del usuario
 */
function getUserGroupRestrictions($userId) {
    try {
        // Verificar si el usuario está en algún grupo activo
        $query = "SELECT ug.access_restrictions, ug.name as group_name
                 FROM user_groups ug
                 INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
                 WHERE ugm.user_id = :user_id AND ug.status = 'active'
                 ORDER BY ugm.added_at ASC
                 LIMIT 1";
        
        $result = fetchOne($query, ['user_id' => $userId]);
        
        if (!$result) {
            return [
                'has_group' => false,
                'restrictions' => [],
                'group_name' => null,
                'message' => 'Sin grupo asignado - acceso según empresa del usuario'
            ];
        }
        
        $restrictions = [];
        if (!empty($result['access_restrictions'])) {
            $restrictions = json_decode($result['access_restrictions'], true) ?: [];
        }
        
        return [
            'has_group' => true,
            'restrictions' => $restrictions,
            'group_name' => $result['group_name'],
            'message' => !empty($restrictions) 
                ? "Restricciones aplicadas por grupo: {$result['group_name']}" 
                : "Sin restricciones en grupo: {$result['group_name']}"
        ];
        
    } catch (Exception $e) {
        error_log("Error obteniendo restricciones de grupo: " . $e->getMessage());
        return [
            'has_group' => false,
            'restrictions' => [],
            'group_name' => null,
            'message' => 'Error - usando acceso por empresa del usuario'
        ];
    }
}

/**
 * Obtener empresas accesibles considerando grupos vs empresa del usuario
 */
function getAccessibleCompaniesForUser($currentUser) {
    $groupInfo = getUserGroupRestrictions($currentUser['id']);
    
    // PRIORIDAD 1: Si tiene grupo con restricciones específicas
    if ($groupInfo['has_group'] && !empty($groupInfo['restrictions']['companies'])) {
        $allowedCompanies = $groupInfo['restrictions']['companies'];
        $placeholders = str_repeat('?,', count($allowedCompanies) - 1) . '?';
        $query = "SELECT * FROM companies WHERE id IN ($placeholders) AND status = 'active' ORDER BY name";
        return fetchAll($query, $allowedCompanies) ?: [];
    }
    
    // PRIORIDAD 2: Si es admin (con o sin grupo)
    if ($currentUser['role'] === 'admin') {
        return fetchAll("SELECT * FROM companies WHERE status = 'active' ORDER BY name") ?: [];
    }
    
    // PRIORIDAD 3: Usuario normal - solo su empresa
    if ($currentUser['company_id']) {
        return fetchAll(
            "SELECT * FROM companies WHERE id = :company_id AND status = 'active'",
            ['company_id' => $currentUser['company_id']]
        ) ?: [];
    }
    
    return [];
}

/**
 * Obtener departamentos accesibles considerando grupos vs empresa del usuario
 */
function getAccessibleDepartmentsForUser($currentUser) {
    $groupInfo = getUserGroupRestrictions($currentUser['id']);
    
    // PRIORIDAD 1: Si tiene grupo con restricciones específicas de departamentos
    if ($groupInfo['has_group'] && !empty($groupInfo['restrictions']['departments'])) {
        $allowedDepartments = $groupInfo['restrictions']['departments'];
        $placeholders = str_repeat('?,', count($allowedDepartments) - 1) . '?';
        $query = "SELECT d.*, c.name as company_name FROM departments d 
                 LEFT JOIN companies c ON d.company_id = c.id 
                 WHERE d.id IN ($placeholders) AND d.status = 'active' 
                 ORDER BY c.name, d.name";
        return fetchAll($query, $allowedDepartments) ?: [];
    }
    
    // PRIORIDAD 2: Si tiene grupo pero sin restricciones de departamentos 
    // (puede ver departamentos de las empresas permitidas)
    if ($groupInfo['has_group'] && !empty($groupInfo['restrictions']['companies'])) {
        $allowedCompanies = $groupInfo['restrictions']['companies'];
        $placeholders = str_repeat('?,', count($allowedCompanies) - 1) . '?';
        $query = "SELECT d.*, c.name as company_name FROM departments d 
                 LEFT JOIN companies c ON d.company_id = c.id 
                 WHERE d.company_id IN ($placeholders) AND d.status = 'active' 
                 ORDER BY c.name, d.name";
        return fetchAll($query, $allowedCompanies) ?: [];
    }
    
    // PRIORIDAD 3: Si es admin (con o sin grupo)
    if ($currentUser['role'] === 'admin') {
        return fetchAll("SELECT d.*, c.name as company_name FROM departments d 
                        LEFT JOIN companies c ON d.company_id = c.id 
                        WHERE d.status = 'active' ORDER BY c.name, d.name") ?: [];
    }
    
    // PRIORIDAD 4: Usuario normal - solo departamentos de su empresa
    if ($currentUser['company_id']) {
        return fetchAll(
            "SELECT d.*, c.name as company_name FROM departments d 
             LEFT JOIN companies c ON d.company_id = c.id 
             WHERE d.company_id = :company_id AND d.status = 'active' 
             ORDER BY d.name",
            ['company_id' => $currentUser['company_id']]
        ) ?: [];
    }
    
    return [];
}

/**
 * Obtener tipos de documento accesibles considerando grupos
 */
function getAccessibleDocumentTypesForUser($currentUser) {
    $groupInfo = getUserGroupRestrictions($currentUser['id']);
    
    // Si tiene grupo con restricciones específicas de tipos de documento
    if ($groupInfo['has_group'] && !empty($groupInfo['restrictions']['document_types'])) {
        $allowedTypes = $groupInfo['restrictions']['document_types'];
        $placeholders = str_repeat('?,', count($allowedTypes) - 1) . '?';
        $query = "SELECT * FROM document_types WHERE id IN ($placeholders) AND status = 'active' ORDER BY name";
        return fetchAll($query, $allowedTypes) ?: [];
    }
    
    // Sin restricciones específicas - todos los tipos
    return fetchAll("SELECT * FROM document_types WHERE status = 'active' ORDER BY name") ?: [];
}

// Obtener datos usando el nuevo sistema de prioridades
$groupInfo = getUserGroupRestrictions($currentUser['id']);
$companies = getAccessibleCompaniesForUser($currentUser);
$departments = getAccessibleDepartmentsForUser($currentUser);
$documentTypes = getAccessibleDocumentTypesForUser($currentUser);

// Debug: Mostrar información del sistema (con ?debug=1)
if (isset($_GET['debug'])) {
    echo "<div style='background: #f8f9fa; padding: 15px; margin: 10px; border: 1px solid #dee2e6; border-radius: 5px; font-family: monospace; font-size: 12px;'>";
    echo "<strong>🔍 DEBUG - Sistema de Permisos con Prioridad de Grupos</strong><br><br>";
    
    echo "<strong>👤 USUARIO:</strong><br>";
    echo "Usuario: {$currentUser['username']} (ID: {$currentUser['id']})<br>";
    echo "Rol: {$currentUser['role']}<br>";
    echo "Empresa del usuario: " . ($currentUser['company_id'] ?: 'N/A') . "<br><br>";
    
    echo "<strong>👥 INFORMACIÓN DE GRUPOS:</strong><br>";
    echo "Tiene grupo: " . ($groupInfo['has_group'] ? 'SÍ' : 'NO') . "<br>";
    if ($groupInfo['has_group']) {
        echo "Nombre del grupo: {$groupInfo['group_name']}<br>";
        echo "Restricciones activas: " . (!empty($groupInfo['restrictions']) ? 'SÍ' : 'NO') . "<br>";
        if (!empty($groupInfo['restrictions'])) {
            if (!empty($groupInfo['restrictions']['companies'])) {
                echo "- Empresas restringidas a: " . implode(', ', $groupInfo['restrictions']['companies']) . "<br>";
            }
            if (!empty($groupInfo['restrictions']['departments'])) {
                echo "- Departamentos restringidos a: " . implode(', ', $groupInfo['restrictions']['departments']) . "<br>";
            }
            if (!empty($groupInfo['restrictions']['document_types'])) {
                echo "- Tipos doc restringidos a: " . implode(', ', $groupInfo['restrictions']['document_types']) . "<br>";
            }
        }
    }
    echo "Mensaje: {$groupInfo['message']}<br><br>";
    
    echo "<strong>📊 ACCESO RESULTANTE:</strong><br>";
    echo "Empresas accesibles: " . count($companies) . "<br>";
    echo "Departamentos accesibles: " . count($departments) . "<br>";
    echo "Tipos de documento: " . count($documentTypes) . "<br><br>";
    
    if (count($companies) > 0) {
        echo "<strong>🏢 EMPRESAS ACCESIBLES:</strong><br>";
        foreach ($companies as $company) {
            echo "- ID: {$company['id']}, Nombre: {$company['name']}<br>";
        }
        echo "<br>";
    }
    
    if (count($departments) > 0) {
        echo "<strong>🏪 DEPARTAMENTOS ACCESIBLES:</strong><br>";
        foreach ($departments as $dept) {
            echo "- ID: {$dept['id']}, Nombre: {$dept['name']}, Empresa: " . ($dept['company_name'] ?: 'N/A') . "<br>";
        }
        echo "<br>";
    } else {
        echo "<strong>⚠️ No se encontraron departamentos accesibles.</strong><br><br>";
    }
    
    echo "<strong>🔄 LÓGICA APLICADA:</strong><br>";
    if ($groupInfo['has_group'] && !empty($groupInfo['restrictions'])) {
        echo "✅ PRIORIDAD 1: Restricciones de grupo aplicadas<br>";
    } elseif ($currentUser['role'] === 'admin') {
        echo "✅ PRIORIDAD 2: Acceso de administrador (todos los recursos)<br>";
    } else {
        echo "✅ PRIORIDAD 3: Usuario normal (solo su empresa)<br>";
    }
    
    echo "</div>";
}

// Procesar subida de archivo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_document'])) {
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
        // Verificar permisos usando el sistema de grupos
        $hasPermission = true;
        $permissionError = '';
        
        // Verificar acceso a empresa
        $accessibleCompanyIds = array_column($companies, 'id');
        if (!in_array($companyId, $accessibleCompanyIds)) {
            $hasPermission = false;
            $permissionError = 'No tienes permisos para subir documentos a esta empresa según las restricciones de tu grupo';
        }
        
        // Verificar acceso a departamento (si se especificó)
        if ($hasPermission && $departmentId) {
            $accessibleDepartmentIds = array_column($departments, 'id');
            if (!in_array($departmentId, $accessibleDepartmentIds)) {
                $hasPermission = false;
                $permissionError = 'No tienes permisos para subir documentos a este departamento según las restricciones de tu grupo';
            }
        }
        
        // Verificar acceso a tipo de documento
        if ($hasPermission) {
            $accessibleDocumentTypeIds = array_column($documentTypes, 'id');
            if (!in_array($documentTypeId, $accessibleDocumentTypeIds)) {
                $hasPermission = false;
                $permissionError = 'No tienes permisos para subir documentos de este tipo según las restricciones de tu grupo';
            }
        }
        
        if (!$hasPermission) {
            $error = $permissionError;
        } else {
            // Procesar archivo (código existente...)
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

        <!-- Contenido -->
        <div class="upload-content">
            <div class="upload-container">
                <div class="upload-card">
                    <div class="upload-header">
                        <h2>Subir Nuevo Documento</h2>
                        <p>Seleccione un archivo y complete la información requerida</p>
                    </div>

                    <!-- Mostrar información de permisos si es relevante -->
                    <?php if ($groupInfo['has_group']): ?>
                        <div class="alert alert-info">
                            <i data-feather="users"></i>
                            <strong>Permisos de grupo:</strong> <?php echo htmlspecialchars($groupInfo['message']); ?>
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
                                <label for="document_name">Nombre del Documento *</label>
                                <input type="text" id="document_name" name="document_name"
                                    class="form-control" required
                                    value="<?php echo htmlspecialchars($documentName ?? ''); ?>"
                                    placeholder="Ej: Factura 001-2024">
                            </div>

                            <div class="form-group">
                                <label for="document_type_id">Tipo de Documento *</label>
                                <select id="document_type_id" name="document_type_id" class="form-control" required>
                                    <option value="">Seleccionar tipo</option>
                                    <?php foreach ($documentTypes as $type): ?>
                                        <option value="<?php echo $type['id']; ?>"
                                            <?php echo (isset($documentTypeId) && $documentTypeId == $type['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($documentTypes)): ?>
                                    <small class="form-help text-muted">Sin tipos de documentos disponibles según tus permisos de grupo</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="company_id">Empresa *</label>
                                <select id="company_id" name="company_id" class="form-control" required>
                                    <option value="">Seleccionar empresa</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>"
                                            <?php echo (isset($companyId) && $companyId == $company['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($companies)): ?>
                                    <small class="form-help text-muted">Sin empresas disponibles según tus permisos de grupo</small>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="department_id">Departamento</label>
                                <select id="department_id" name="department_id" class="form-control">
                                    <option value="">Seleccionar departamento (opcional)</option>
                                    <?php if (is_array($departments) && count($departments) > 0): ?>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept['id']); ?>" 
                                                    data-company="<?php echo htmlspecialchars($dept['company_id']); ?>"
                                                    <?php echo (isset($departmentId) && $departmentId == $dept['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['name']); ?>
                                                <?php if ($currentUser['role'] === 'admin' && isset($dept['company_name'])): ?>
                                                    (<?php echo htmlspecialchars($dept['company_name']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay departamentos disponibles</option>
                                    <?php endif; ?>
                                </select>
                                <small class="form-help">
                                    <?php if (count($departments) == 0): ?>
                                        <span style="color: orange;">⚠️ No hay departamentos disponibles según tus permisos de grupo.</span>
                                    <?php else: ?>
                                        Los departamentos se filtran automáticamente según la empresa seleccionada
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="document_file">Archivo *</label>
                            <div class="file-upload-area" id="fileUploadArea">
                                <input type="file" id="document_file" name="document_file"
                                    class="file-input" required accept=".pdf,.doc,.docx,.xlsx,.jpg,.jpeg,.png,.gif">
                                <div class="file-upload-content">
                                    <i data-feather="upload-cloud" class="icon-grande"></i>
                                    <p style="font-size: 1.4rem; font-weight: 500; text-align: center;">Haz clic aquí para seleccionar un archivo<br><small style="font-size: 1.3rem; font-weight: 500; text-align: center;">o arrastra y suelta un archivo</small></p>
                                    <small style="font-size: 0.8rem; font-weight: 500; text-align: center;">Tamaño máximo: <?php echo formatBytes(getSystemConfig('max_file_size') ?? 20971520); ?></small>
                                </div>
                                <div class="file-preview" id="filePreview" style="display: none;">
                                    <div class="file-info">
                                        <i data-feather="file"></i>
                                        <div class="file-details">
                                            <span class="file-name"></span>
                                            <span class="file-size"></span>
                                        </div>
                                    </div>
                                    <button type="button" class="remove-file" onclick="removeFile()">
                                        <i data-feather="x"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-help">Haz clic en cualquier parte del área para cambiar el archivo</small>
                        </div>

                        <div class="form-group">
                            <label for="description">Descripción</label>
                            <textarea id="description" name="description" class="form-control"
                                rows="3" placeholder="Descripción opcional del documento"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="upload_document" class="btn btn-primary btn-upload"
                                    <?php echo (empty($companies) || empty($documentTypes)) ? 'disabled' : ''; ?>>
                                <i data-feather="upload"></i>
                                Subir Documento
                            </button>
                            <a href="../../dashboard.php" class="btn btn-secondary">
                                <i data-feather="arrow-left"></i>
                                Volver al Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script>
        console.log('🚀 Iniciando upload con sistema de grupos...');

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
            console.log('📄 DOM cargado con sistema de prioridad de grupos');
            
            feather.replace();
            updateTime();
            setInterval(updateTime, 60000);
            
            setupFileUpload();
            setupDepartmentFilter();
            
            // Debug inicial
            const deptSelect = document.getElementById('department_id');
            if (deptSelect) {
                const deptOptions = Array.from(deptSelect.options).filter(opt => opt.value !== '');
                console.log(`📊 Departamentos cargados: ${deptOptions.length}`);
                
                if (deptOptions.length === 0) {
                    console.warn('⚠️ No hay departamentos disponibles según permisos de grupo');
                } else {
                    console.log('📋 Departamentos disponibles según grupos:');
                    deptOptions.forEach(option => {
                        const name = option.textContent.trim();
                        const company = option.getAttribute('data-company');
                        console.log(`  • ${name} (empresa: ${company})`);
                    });
                }
            }

            // Verificar información de grupos
            const groupAlert = document.querySelector('.alert-info');
            if (groupAlert) {
                console.log('👥 Sistema de grupos activo:', groupAlert.textContent.trim());
            } else {
                console.log('👤 Usuario sin grupo - usando acceso por empresa del usuario');
            }
        });

        // Configurar filtro de departamentos
        function setupDepartmentFilter() {
            const companySelect = document.getElementById('company_id');
            const departmentSelect = document.getElementById('department_id');
            
            if (!companySelect || !departmentSelect) {
                console.warn('⚠️ No se encontraron los selects de empresa o departamento');
                return;
            }
            
            console.log('🔧 Configurando filtro de departamentos con prioridad de grupos...');
            
            companySelect.addEventListener('change', function() {
                const selectedCompany = this.value;
                console.log(`🏢 Empresa seleccionada: ${selectedCompany}`);
                filterDepartmentsByCompany(selectedCompany);
            });
            
            // Aplicar filtro inicial si hay empresa pre-seleccionada
            if (companySelect.value) {
                console.log('🔄 Aplicando filtro inicial...');
                filterDepartmentsByCompany(companySelect.value);
            }
        }

        function filterDepartmentsByCompany(companyId) {
            const departmentSelect = document.getElementById('department_id');
            if (!departmentSelect) return;
            
            console.log(`🔄 Filtrando departamentos para empresa: ${companyId} (con restricciones de grupo)`);
            
            const options = Array.from(departmentSelect.options);
            let visibleCount = 0;
            let hiddenCount = 0;
            
            options.forEach(option => {
                if (option.value === '') {
                    // Opción vacía siempre visible
                    option.style.display = 'block';
                    option.disabled = false;
                } else {
                    const optionCompany = option.getAttribute('data-company');
                    
                    if (!companyId || optionCompany === companyId) {
                        // Mostrar departamentos de la empresa seleccionada
                        // (ya filtrados por restricciones de grupo en PHP)
                        option.style.display = 'block';
                        option.disabled = false;
                        visibleCount++;
                    } else {
                        // Ocultar departamentos de otras empresas
                        option.style.display = 'none';
                        option.disabled = true;
                        hiddenCount++;
                    }
                }
            });
            
            console.log(`✅ Filtro aplicado: ${visibleCount} visibles, ${hiddenCount} ocultos`);
            
            // Resetear selección si la opción actual no es válida
            const currentOption = departmentSelect.options[departmentSelect.selectedIndex];
            if (currentOption && currentOption.value && 
                currentOption.getAttribute('data-company') !== companyId) {
                departmentSelect.value = '';
                console.log('🔄 Selección de departamento reseteada');
            }
            
            // Mostrar mensaje si no hay departamentos para esta empresa
            if (visibleCount === 0 && companyId) {
                console.log('⚠️ No hay departamentos disponibles para esta empresa según restricciones de grupo');
                showMessage('No hay departamentos disponibles para esta empresa según tus permisos de grupo', 'info');
            }
        }

        // Configurar subida de archivos
        function setupFileUpload() {
            const fileInput = document.getElementById('document_file');
            const fileUploadArea = document.getElementById('fileUploadArea');
            
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
            const fileUploadContent = document.querySelector('.file-upload-content');
            const fileName = document.querySelector('.file-name');
            const fileSize = document.querySelector('.file-size');

            if (fileName) fileName.textContent = file.name;
            if (fileSize) fileSize.textContent = formatFileSize(file.size);

            if (fileUploadContent) fileUploadContent.style.display = 'none';
            if (filePreview) filePreview.style.display = 'flex';

            feather.replace();
        }

        function removeFile() {
            const fileInput = document.getElementById('document_file');
            const filePreview = document.getElementById('filePreview');
            const fileUploadContent = document.querySelector('.file-upload-content');

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

        function showMessage(message, type = 'info') {
            console.log(`${type.toUpperCase()}: ${message}`);
            
            // Crear alerta visual simple
            const alertClass = type === 'error' ? 'alert-danger' : 
                              type === 'success' ? 'alert-success' : 
                              type === 'warning' ? 'alert-warning' : 'alert-info';
            
            // Buscar contenedor para el mensaje
            let container = document.querySelector('.upload-card, .upload-container, .container');
            if (!container) container = document.body;
            
            // Crear elemento de alerta
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${alertClass}`;
            alertDiv.style.cssText = `
                padding: 10px 15px;
                margin: 10px 0;
                border: 1px solid transparent;
                border-radius: 4px;
                background-color: ${type === 'warning' ? '#fff3cd' : type === 'error' ? '#f8d7da' : '#d1ecf1'};
                border-color: ${type === 'warning' ? '#ffeaa7' : type === 'error' ? '#f5c6cb' : '#bee5eb'};
                color: ${type === 'warning' ? '#856404' : type === 'error' ? '#721c24' : '#0c5460'};
            `;
            alertDiv.innerHTML = `<i data-feather="${type === 'error' ? 'alert-circle' : 'info'}"></i> <strong>${type.toUpperCase()}:</strong> ${message}`;
            
            // Insertar al inicio del contenedor
            container.insertBefore(alertDiv, container.firstChild);
            
            // Reinicializar iconos
            feather.replace();
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }

        console.log('✅ Sistema de upload con prioridad de grupos inicializado');
    </script>
</body>
</html>