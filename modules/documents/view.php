<?php
// modules/documents/view.php
// Vista de documento individual

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();
$documentId = $_GET['id'] ?? null;

if (!$documentId || !is_numeric($documentId)) {
    header('Location: inbox.php');
    exit();
}

// Obtener documento con verificación de permisos
if ($currentUser['role'] === 'admin') {
    $query = "SELECT d.*, c.name as company_name, dep.name as department_name, 
                     dt.name as document_type, u.first_name, u.last_name
              FROM documents d
              LEFT JOIN companies c ON d.company_id = c.id
              LEFT JOIN departments dep ON d.department_id = dep.id
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE d.id = :id AND d.status = 'active'";
    $params = ['id' => $documentId];
} else {
    $query = "SELECT d.*, c.name as company_name, dep.name as department_name, 
                     dt.name as document_type, u.first_name, u.last_name
              FROM documents d
              LEFT JOIN companies c ON d.company_id = c.id
              LEFT JOIN departments dep ON d.department_id = dep.id
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE d.id = :id AND d.company_id = :company_id AND d.status = 'active'";
    $params = ['id' => $documentId, 'company_id' => $currentUser['company_id']];
}

$document = fetchOne($query, $params);

if (!$document) {
    header('Location: inbox.php?error=document_not_found');
    exit();
}

// Verificar permisos de descarga
$query = "SELECT download_enabled FROM users WHERE id = :id";
$result = fetchOne($query, ['id' => $currentUser['id']]);
$canDownload = $result ? ($result['download_enabled'] ?? true) : false;

// Determinar tipo de archivo para vista previa
$fileExtension = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
$mimeType = $document['mime_type'];

$fileType = 'file';
if (strpos($mimeType, 'image/') === 0) {
    $fileType = 'image';
} elseif ($mimeType === 'application/pdf') {
    $fileType = 'pdf';
} elseif (strpos($mimeType, 'video/') === 0) {
    $fileType = 'video';
}

// Registrar vista
logActivity($currentUser['id'], 'view', 'documents', $documentId, 'Usuario visualizó documento: ' . $document['name']);

function formatBytes($size, $precision = 2)
{
    if ($size == 0) return '0 B';
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
    <title><?php echo htmlspecialchars($document['name']); ?> - DMS2</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/documents.css">
    <link rel="stylesheet" href="../../assets/css/view.css">
    <script src="https://unpkg.com/feather-icons"></script>
</head>

<body class="dashboard-layout">
    <!-- Sidebar -->

    <?php include '../../includes/sidebar.php'; ?>

    <!-- Contenido principal -->
    <main class="main-content">
        <!-- Header -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Vista de Documento</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(getFullName()); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>

                <div class="header-actions">
                    <button class="btn-icon" onclick="showUserMenu()">
                        <i data-feather="settings"></i>
                    </button>
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido del documento -->
        <div style="padding: 2rem; background: #f8fafc; min-height: calc(100vh - 80px);">
            <div class="document-viewer">
                <div class="document-header">
                    <div class="document-info">
                        <h1><?php echo htmlspecialchars($document['name']); ?></h1>
                        <p style="color: #64748b; margin: 0;"><?php echo htmlspecialchars($document['document_type'] ?? 'Sin tipo'); ?></p>
                    </div>
                    <div>
                        <a href="inbox.php" class="btn btn-secondary">
                            <i data-feather="arrow-left"></i>
                            Volver
                        </a>
                    </div>
                </div>

                <div class="document-meta">
                    <div class="meta-item">
                        <span class="meta-label">Tamaño</span>
                        <span class="meta-value"><?php echo formatBytes($document['file_size']); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Empresa</span>
                        <span class="meta-value"><?php echo htmlspecialchars($document['company_name']); ?></span>
                    </div>
                    <?php if ($document['department_name']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Departamento</span>
                            <span class="meta-value"><?php echo htmlspecialchars($document['department_name']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="meta-item">
                        <span class="meta-label">Subido por</span>
                        <span class="meta-value"><?php echo htmlspecialchars($document['first_name'] . ' ' . $document['last_name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Fecha de subida</span>
                        <span class="meta-value"><?php echo date('d/m/Y H:i', strtotime($document['created_at'])); ?></span>
                    </div>
                    <?php if ($document['description']): ?>
                        <div class="meta-item" style="grid-column: 1 / -1;">
                            <span class="meta-label">Descripción</span>
                            <span class="meta-value"><?php echo htmlspecialchars($document['description']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="document-preview-container">
                    <?php if ($fileType === 'image'): ?>
                        <img src="../../<?php echo htmlspecialchars($document['file_path']); ?>"
                            alt="<?php echo htmlspecialchars($document['name']); ?>"
                            class="preview-image">
                    <?php elseif ($fileType === 'pdf'): ?>
                        <iframe src="../../<?php echo htmlspecialchars($document['file_path']); ?>#toolbar=1"
                            class="preview-pdf"
                            title="<?php echo htmlspecialchars($document['name']); ?>">
                        </iframe>
                    <?php else: ?>
                        <div class="preview-placeholder">
                            <i data-feather="file"></i>
                            <h3>Vista previa no disponible</h3>
                            <p>Este tipo de archivo no se puede previsualizar en el navegador.</p>
                            <p>Puedes descargarlo para abrirlo en tu aplicación preferida.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="action-buttons">
                    <?php if ($canDownload): ?>
                        <button class="btn btn-primary" onclick="downloadDocument()">
                            <i data-feather="download"></i>
                            Descargar Documento
                        </button>
                    <?php else: ?>
                        <button class="btn btn-secondary disabled" title="Descarga deshabilitada">
                            <i data-feather="download-cloud"></i>
                            Descarga Deshabilitada
                        </button>
                    <?php endif; ?>

                    <a href="inbox.php" class="btn btn-secondary">
                        <i data-feather="list"></i>
                        Ver Todos los Documentos
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Overlay para móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <script>
        // Inicializar Feather icons
        feather.replace();

        // Inicializar reloj
        updateTime();
        setInterval(updateTime, 1000);

        function updateTime() {
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                const dateString = now.toLocaleDateString('es-ES', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
                timeElement.textContent = `${dateString} ${timeString}`;
            }
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            }
        }

        function downloadDocument() {
            <?php if ($canDownload): ?>
                // Crear enlace temporal para descarga
                const link = document.createElement('a');
                link.href = '../../<?php echo htmlspecialchars($document['file_path']); ?>';
                link.download = '<?php echo htmlspecialchars($document['original_name'] ?? $document['name']); ?>';
                link.style.display = 'none';

                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Registrar descarga
                fetch('log_activity.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'download',
                        document_id: <?php echo $documentId; ?>
                    })
                }).catch(console.error);
            <?php endif; ?>
        }

        function showUserMenu() {
            alert('Menú de usuario - Próximamente');
        }

        function showComingSoon(feature) {
            alert(`${feature} - Próximamente`);
        }

        // Responsive behavior
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    </script>
</body>

</html>