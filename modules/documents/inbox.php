<?php
// modules/documents/inbox.php - VERSIÓN UNIFICADA Y COMPLETA
require_once '../../config/session.php';
require_once '../../config/database.php';

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

if (!$currentUser || !isset($currentUser['id'])) {
    header('Location: ../../login.php');
    exit();
}

function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}

function getDocumentsByPermissions($userId, $companyId, $role) {
    if ($role === 'admin') {
        $query = "SELECT d.*, c.name as company_name, dep.name as department_name, 
                         dt.name as document_type, u.first_name, u.last_name,
                         CASE 
                            WHEN d.mime_type LIKE 'image/%' THEN 'image'
                            WHEN d.mime_type = 'application/pdf' THEN 'pdf'
                            WHEN d.mime_type LIKE '%excel%' OR d.mime_type LIKE '%spreadsheet%' THEN 'excel'
                            WHEN d.mime_type LIKE '%word%' OR d.mime_type LIKE '%document%' THEN 'word'
                            ELSE 'file'
                         END as file_icon
                  FROM documents d
                  LEFT JOIN companies c ON d.company_id = c.id
                  LEFT JOIN departments dep ON d.department_id = dep.id
                  LEFT JOIN document_types dt ON d.document_type_id = dt.id
                  LEFT JOIN users u ON d.user_id = u.id
                  WHERE d.status = 'active'
                  ORDER BY d.created_at DESC";
        return fetchAll($query) ?: [];
    } else {
        $query = "SELECT d.*, c.name as company_name, dep.name as department_name, 
                         dt.name as document_type, u.first_name, u.last_name,
                         CASE 
                            WHEN d.mime_type LIKE 'image/%' THEN 'image'
                            WHEN d.mime_type = 'application/pdf' THEN 'pdf'
                            WHEN d.mime_type LIKE '%excel%' OR d.mime_type LIKE '%spreadsheet%' THEN 'excel'
                            WHEN d.mime_type LIKE '%word%' OR d.mime_type LIKE '%document%' THEN 'word'
                            ELSE 'file'
                         END as file_icon
                  FROM documents d
                  LEFT JOIN companies c ON d.company_id = c.id
                  LEFT JOIN departments dep ON d.department_id = dep.id
                  LEFT JOIN document_types dt ON d.document_type_id = dt.id
                  LEFT JOIN users u ON d.user_id = u.id
                  WHERE d.status = 'active' AND d.company_id = :company_id
                  ORDER BY d.created_at DESC";
        return fetchAll($query, ['company_id' => $companyId]) ?: [];
    }
}

function organizeDocumentsByFolders($documents) {
    $folders = [];
    foreach ($documents as $doc) {
        $companyName = $doc['company_name'] ?? 'Sin Empresa';
        $departmentName = $doc['department_name'] ?? 'General';
        $folderKey = $companyName . '/' . $departmentName;
        
        if (!isset($folders[$folderKey])) {
            $folders[$folderKey] = [
                'company' => $companyName,
                'department' => $departmentName,
                'documents' => [],
                'count' => 0
            ];
        }
        
        $folders[$folderKey]['documents'][] = $doc;
        $folders[$folderKey]['count']++;
    }
    return $folders;
}

function canUserDownload($userId) {
    $query = "SELECT download_enabled FROM users WHERE id = :id";
    $result = fetchOne($query, ['id' => $userId]);
    return $result ? ($result['download_enabled'] ?? true) : false;
}

function canUserDelete($userId, $role, $documentOwnerId) {
    return ($role === 'admin') || ($userId == $documentOwnerId);
}

// Obtener datos
$documents = getDocumentsByPermissions($currentUser['id'], $currentUser['company_id'], $currentUser['role']);
$folders = organizeDocumentsByFolders($documents);
$canDownload = canUserDownload($currentUser['id']);

// Filtros
$selectedFolder = $_GET['folder'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$selectedType = $_GET['type'] ?? '';

if ($selectedFolder || $searchTerm || $selectedType) {
    $filteredDocuments = [];
    foreach ($documents as $doc) {
        $include = true;
        
        if ($selectedFolder) {
            $docFolder = ($doc['company_name'] ?? 'Sin Empresa') . '/' . ($doc['department_name'] ?? 'General');
            if ($docFolder !== $selectedFolder) {
                $include = false;
            }
        }
        
        if ($searchTerm && $include) {
            $searchFields = [$doc['name'], $doc['description'], $doc['document_type'], $doc['company_name'], $doc['department_name']];
            $found = false;
            foreach ($searchFields as $field) {
                if ($field && stripos($field, $searchTerm) !== false) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $include = false;
            }
        }
        
        if ($selectedType && $include) {
            if ($doc['file_icon'] !== $selectedType) {
                $include = false;
            }
        }
        
        if ($include) {
            $filteredDocuments[] = $doc;
        }
    }
    $documents = $filteredDocuments;
}

logActivity($currentUser['id'], 'view', 'documents', null, 'Usuario accedió a la bandeja de entrada');
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
    <script src="https://unpkg.com/feather-icons"></script>
</head>

<body class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="https://perdomoyasociados.com/wp-content/uploads/2023/09/logo_perdomo_2023_dorado-768x150.png" alt="Perdomo y Asociados" class="logo-image">
            </div>
        </div>

        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="../../dashboard.php" class="nav-link">
                        <i data-feather="home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="upload.php" class="nav-link">
                        <i data-feather="upload"></i>
                        <span>Subir Documentos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="inbox.php" class="nav-link">
                        <i data-feather="inbox"></i>
                        <span>Archivos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showComingSoon('Búsqueda')">
                        <i data-feather="search"></i>
                        <span>Búsqueda</span>
                    </a>
                </li>
                <li class="nav-divider"></li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showComingSoon('Reportes')">
                        <i data-feather="bar-chart-2"></i>
                        <span>Reportes</span>
                    </a>
                </li>
                
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <li class="nav-section"><span>ADMINISTRACIÓN</span></li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="showComingSoon('Usuarios')">
                            <i data-feather="users"></i>
                            <span>Usuarios</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="showComingSoon('Empresas')">
                            <i data-feather="briefcase"></i>
                            <span>Empresas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="showComingSoon('Departamentos')">
                            <i data-feather="layers"></i>
                            <span>Departamentos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="showComingSoon('Grupos')">
                            <i data-feather="shield"></i>
                            <span>Grupos</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </aside>

    <!-- Contenido principal -->
    <main class="main-content">
        <!-- Header -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Archivos</h1>
                <div class="header-stats">
                    <span class="stat-item">
                        <i data-feather="folder"></i>
                        <?php echo count($folders); ?> carpetas
                    </span>
                    <span class="stat-item">
                        <i data-feather="file"></i>
                        <?php echo count($documents); ?> documentos
                    </span>
                </div>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(getFullName()); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" onclick="showComingSoon('Configuración')">
                        <i data-feather="settings"></i>
                    </button>
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido de la bandeja -->
        <div class="inbox-container">
            <!-- Panel de filtros -->
            <aside class="filters-panel">
                <div class="filters-header">
                    <h3>Explorar</h3>
                    <button class="btn-icon-sm" onclick="clearAllFilters()">
                        <i data-feather="refresh-cw"></i>
                    </button>
                </div>

                <!-- Búsqueda -->
                <div class="search-section">
                    <form method="GET" action="">
                        <div class="search-input-group">
                            <i data-feather="search"></i>
                            <input type="text" name="search" placeholder="Buscar documentos..." 
                                   value="<?php echo htmlspecialchars($searchTerm); ?>">
                            <button type="submit" class="btn-search">
                                <i data-feather="arrow-right"></i>
                            </button>
                        </div>
                        <?php if ($selectedFolder): ?>
                            <input type="hidden" name="folder" value="<?php echo htmlspecialchars($selectedFolder); ?>">
                        <?php endif; ?>
                        <?php if ($selectedType): ?>
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($selectedType); ?>">
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Carpetas -->
                <div class="folders-section">
                    <h4>Carpetas</h4>
                    <div class="folders-list">
                        <a href="?" class="folder-item <?php echo !$selectedFolder ? 'active' : ''; ?>">
                            <i data-feather="home"></i>
                            <span>Todos los archivos</span>
                            <span class="count"><?php echo array_sum(array_column($folders, 'count')); ?></span>
                        </a>
                        
                        <?php foreach ($folders as $folderKey => $folder): ?>
                            <a href="?folder=<?php echo urlencode($folderKey); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" 
                               class="folder-item <?php echo $selectedFolder === $folderKey ? 'active' : ''; ?>">
                                <i data-feather="folder"></i>
                                <div class="folder-info">
                                    <span class="folder-name"><?php echo htmlspecialchars($folder['company']); ?></span>
                                    <small class="folder-dept"><?php echo htmlspecialchars($folder['department']); ?></small>
                                </div>
                                <span class="count"><?php echo $folder['count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tipos -->
                <div class="types-section">
                    <h4>Tipos de archivo</h4>
                    <div class="types-list">
                        <a href="?" class="type-item <?php echo !$selectedType ? 'active' : ''; ?>">
                            <i data-feather="file"></i>
                            <span>Todos</span>
                        </a>
                        <a href="?type=pdf" class="type-item <?php echo $selectedType === 'pdf' ? 'active' : ''; ?>">
                            <i data-feather="file-text"></i>
                            <span>PDF</span>
                        </a>
                        <a href="?type=image" class="type-item <?php echo $selectedType === 'image' ? 'active' : ''; ?>">
                            <i data-feather="image"></i>
                            <span>Imágenes</span>
                        </a>
                    </div>
                </div>

                <!-- Info usuario -->
                <div class="user-info-section">
                    <div class="permission-status">
                        <i data-feather="<?php echo $canDownload ? 'download' : 'download-cloud'; ?>"></i>
                        <span><?php echo $canDownload ? 'Descarga habilitada' : 'Descarga deshabilitada'; ?></span>
                    </div>
                </div>
            </aside>

            <!-- Panel de documentos -->
            <main class="documents-panel-full">
                <div class="documents-header">
                    <div class="view-controls">
                        <button class="view-btn active" data-view="grid" onclick="changeView('grid')">
                            <i data-feather="grid"></i>
                            <span>Cuadros</span>
                        </button>
                        <button class="view-btn" data-view="list" onclick="changeView('list')">
                            <i data-feather="list"></i>
                            <span>Lista</span>
                        </button>
                    </div>
                    <div class="sort-controls">
                        <select id="sortBy" onchange="sortDocuments()">
                            <option value="name">Ordenar por nombre</option>
                            <option value="date">Ordenar por fecha</option>
                            <option value="size">Ordenar por tamaño</option>
                        </select>
                    </div>
                </div>

                <div class="documents-content">
                    <?php if (empty($documents)): ?>
                        <div class="empty-state">
                            <i data-feather="folder-x"></i>
                            <h3>No se encontraron documentos</h3>
                            <p>
                                <?php if ($selectedFolder || $searchTerm || $selectedType): ?>
                                    No hay documentos que coincidan con los filtros.
                                    <a href="?" class="clear-filters">Limpiar filtros</a>
                                <?php else: ?>
                                    Aún no hay documentos.
                                    <a href="upload.php" class="upload-link">Subir documento</a>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="documents-grid" id="documentsGrid">
                            <?php foreach ($documents as $doc): ?>
                                <?php $canDelete = canUserDelete($currentUser['id'], $currentUser['role'], $doc['user_id']); ?>
                                <div class="document-card" data-id="<?php echo $doc['id']; ?>">
                                    <div class="document-preview">
                                        <div class="document-icon <?php echo $doc['file_icon']; ?>">
                                            <?php
                                            $iconMap = [
                                                'pdf' => 'file-text',
                                                'word' => 'file-text',
                                                'excel' => 'grid',
                                                'image' => 'image',
                                                'file' => 'file'
                                            ];
                                            $icon = $iconMap[$doc['file_icon']] ?? 'file';
                                            ?>
                                            <i data-feather="<?php echo $icon; ?>"></i>
                                        </div>
                                        <?php if ($doc['file_icon'] === 'image'): ?>
                                            <img src="../../<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($doc['name']); ?>"
                                                 class="image-preview"
                                                 onerror="this.style.display='none'">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="document-info">
                                        <h4 class="document-name" title="<?php echo htmlspecialchars($doc['name']); ?>">
                                            <?php echo htmlspecialchars($doc['name']); ?>
                                        </h4>
                                        <div class="document-meta">
                                            <span class="document-type">
                                                <?php echo htmlspecialchars($doc['document_type'] ?? 'Sin tipo'); ?>
                                            </span>
                                            <span class="document-size">
                                                <?php echo formatBytes($doc['file_size']); ?>
                                            </span>
                                        </div>
                                        <div class="document-location">
                                            <i data-feather="map-pin"></i>
                                            <span><?php echo htmlspecialchars($doc['company_name']); ?></span>
                                            <?php if ($doc['department_name']): ?>
                                                <span> / <?php echo htmlspecialchars($doc['department_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="document-date">
                                            <i data-feather="calendar"></i>
                                            <span><?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- BOTONES DE ACCIÓN -->
                                    <div class="document-actions">
                                        <!-- VER -->
                                        <button class="action-btn view-btn" data-doc-id="<?php echo $doc['id']; ?>" title="Ver documento">
                                            <i data-feather="eye"></i>
                                        </button>
                                        
                                        <!-- DESCARGAR -->
                                        <?php if ($canDownload): ?>
                                            <button class="action-btn download-btn" data-doc-id="<?php echo $doc['id']; ?>" title="Descargar">
                                                <i data-feather="download"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="action-btn disabled" title="Descarga deshabilitada">
                                                <i data-feather="download-cloud"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!-- ELIMINAR -->
                                        <?php if ($canDelete): ?>
                                            <button class="action-btn delete-btn" data-doc-id="<?php echo $doc['id']; ?>" title="Eliminar">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </main>

    <!-- Variables JavaScript -->
    <script>
        var documentsData = <?php echo json_encode($documents); ?>;
        var canDownload = <?php echo $canDownload ? 'true' : 'false'; ?>;
        var currentUserId = <?php echo $currentUser['id']; ?>;
        var currentUserRole = '<?php echo $currentUser['role']; ?>';
        
        console.log('🚀 INBOX DMS2 - Inicializando');
        
        // Variables globales
        let currentView = 'grid';

        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            updateTime();
            setInterval(updateTime, 1000);
            setupEventListeners();
            handleURLMessages();
            console.log('✅ Inbox inicializado correctamente');
        });

        // Configurar eventos
        function setupEventListeners() {
            // Delegación de eventos para botones de acción
            document.addEventListener('click', function(e) {
                // Botón VER
                if (e.target.closest('.view-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const docId = e.target.closest('.view-btn').dataset.docId;
                    if (docId) {
                        viewDocument(docId);
                    }
                }
                
                // Botón DESCARGAR
                if (e.target.closest('.download-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const docId = e.target.closest('.download-btn').dataset.docId;
                    if (docId) {
                        downloadDocument(docId);
                    }
                }
                
                // Botón ELIMINAR
                if (e.target.closest('.delete-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const docId = e.target.closest('.delete-btn').dataset.docId;
                    if (docId) {
                        deleteDocument(docId);
                    }
                }
                
                // Click en vista previa del documento
                if (e.target.closest('.document-preview')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const card = e.target.closest('.document-card');
                    if (card) {
                        const docId = card.dataset.id;
                        if (docId) {
                            viewDocument(docId);
                        }
                    }
                }
            });
        }

        // FUNCIÓN VER DOCUMENTO
        function viewDocument(documentId) {
            console.log('👁️ Ver documento ID:', documentId);
            
            // Verificar que el documento existe
            if (typeof documentsData !== 'undefined') {
                const document = documentsData.find(doc => doc.id == documentId);
                if (!document) {
                    showNotification('Documento no encontrado', 'error');
                    return;
                }
                console.log('📄 Abriendo documento:', document.name);
            }
            
            // Abrir en la misma ventana
            window.location.href = 'view.php?id=' + documentId;
        }

        // FUNCIÓN DESCARGAR
        function downloadDocument(documentId) {
            console.log('⬇️ Descargar documento ID:', documentId);
            
            if (!canDownload) {
                showNotification('No tienes permisos para descargar', 'error');
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
            
            showNotification('Iniciando descarga...', 'info');
            
            setTimeout(() => {
                if (document.body.contains(form)) {
                    document.body.removeChild(form);
                }
            }, 2000);
        }

        // FUNCIÓN ELIMINAR - CORREGIDA
        function deleteDocument(documentId) {
            console.log('🗑️ Eliminar documento ID:', documentId);
            
            // CORREGIDO: Usar 'docData' en lugar de 'document' para evitar conflicto
            const docData = documentsData.find(doc => doc.id == documentId);
            if (!docData) {
                showNotification('Documento no encontrado', 'error');
                return;
            }
            
            // Verificar permisos
            const canDelete = (currentUserRole === 'admin') || (docData.user_id == currentUserId);
            if (!canDelete) {
                showNotification('No tienes permisos para eliminar', 'error');
                return;
            }
            
            // Confirmación detallada
            const confirmMsg = `¿Eliminar documento?

📄 ${docData.name}
🏢 ${docData.company_name || 'Sin empresa'}
📁 ${docData.department_name || 'Sin departamento'}
📏 ${formatBytes(docData.file_size)}

⚠️ Esta acción no se puede deshacer.`;
            
            if (!confirm(confirmMsg)) return;
            
            // Confirmación final
            if (!confirm('¿Está completamente seguro? Esta es la última oportunidad para cancelar.')) return;
            
            // CORREGIDO: Ahora document se refiere al DOM correctamente
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
            
            showNotification('Eliminando documento...', 'warning');
        }

        // Función para formatear bytes
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        // Funciones auxiliares
        function changeView(view) {
            currentView = view;
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.view === view) {
                    btn.classList.add('active');
                }
            });
            const grid = document.getElementById('documentsGrid');
            if (grid) {
                grid.className = view === 'grid' ? 'documents-grid' : 'documents-list';
            }
        }

        function updateTime() {
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                const dateString = now.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
                timeElement.textContent = `${dateString} ${timeString}`;
            }
        }

        function showNotification(message, type = 'info') {
            console.log(`📢 ${type.toUpperCase()}: ${message}`);
            
            // Crear notificación visual
            const notification = document.createElement('div');
            notification.className = `notification-toast ${type}`;
            
            const colors = {
                'info': '#3b82f6',
                'success': '#10b981',
                'warning': '#f59e0b',
                'error': '#ef4444'
            };
            
            const icons = {
                'info': 'info',
                'success': 'check-circle',
                'warning': 'alert-triangle',
                'error': 'alert-circle'
            };
            
            notification.innerHTML = `
                <i data-feather="${icons[type] || 'info'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()">
                    <i data-feather="x"></i>
                </button>
            `;
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${colors[type] || colors.info};
                color: white;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 9999;
                font-size: 14px;
                font-weight: 500;
                max-width: 350px;
                display: flex;
                align-items: center;
                gap: 10px;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            `;
            
            document.body.appendChild(notification);
            feather.replace();
            
            // Animar entrada
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            // Auto-remover después de 4 segundos
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 4000);
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('active');
            }
        }

        function clearAllFilters() {
            window.location.href = window.location.pathname;
        }

        function sortDocuments() {
            const sortBy = document.getElementById('sortBy').value;
            const url = new URL(window.location);
            url.searchParams.set('sort', sortBy);
            window.location.href = url.toString();
        }

        function handleURLMessages() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === 'document_deleted') {
                const name = urlParams.get('name') || 'el documento';
                showNotification(`${name} eliminado exitosamente`, 'success');
                
                // Limpiar URL
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
            
            if (urlParams.get('error')) {
                const error = urlParams.get('error');
                let message = 'Error desconocido';
                
                switch(error) {
                    case 'delete_failed':
                        message = 'Error al eliminar el documento';
                        break;
                    case 'document_not_found':
                        message = 'Documento no encontrado';
                        break;
                    case 'download_disabled':
                        message = 'Descarga deshabilitada';
                        break;
                    case 'file_not_found':
                        message = 'Archivo no encontrado';
                        break;
                }
                
                showNotification(message, 'error');
                
                // Limpiar URL
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        }

        function showComingSoon(feature) {
            showNotification(`${feature} - Próximamente`, 'info');
        }

        // Configurar eventos responsive
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });
    </script>
</body>
</html>