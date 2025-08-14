<?php
// modules/documents/view.php
// Vista de documento individual optimizada para modal

require_once '../../config/session.php';
require_once '../../config/database.php';

// Funciones helper
if (!function_exists('getFullName')) {
    function getFullName() {
        $user = SessionManager::getCurrentUser();
        return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    }
}

if (!function_exists('logActivity')) {
    function logActivity($userId, $action, $tableName, $recordId = null, $description = '') {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $query = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$userId, $action, $tableName, $recordId, $description]);
        } catch (Exception $e) {
            error_log('Error logging activity: ' . $e->getMessage());
        }
    }
}

if (!function_exists('fetchOne')) {
    function fetchOne($query, $params = []) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error in fetchOne: ' . $e->getMessage());
            return false;
        }
    }
}

// Verificar usuario logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();
$documentId = $_GET['id'] ?? null;

if (!$documentId || !is_numeric($documentId)) {
    echo json_encode(['success' => false, 'message' => 'ID de documento no válido']);
    exit();
}

// Obtener documento
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
    echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
    exit();
}

// Verificar permisos de descarga
$query = "SELECT download_enabled FROM users WHERE id = :id";
$result = fetchOne($query, ['id' => $currentUser['id']]);
$canDownload = $result ? ($result['download_enabled'] ?? true) : false;

// Determinar tipo de archivo
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

function formatBytes($size, $precision = 2) {
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        .document-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 1.5rem;
            background: white;
            min-height: 100vh;
        }

        .document-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .document-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .document-type {
            color: #64748b;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .document-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .meta-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-value {
            font-size: 0.875rem;
            color: #1e293b;
            font-weight: 500;
        }

        .document-preview {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
            padding: 1rem;
        }

        .preview-image {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .preview-pdf {
            width: 100%;
            height: 70vh;
            border: none;
            border-radius: 8px;
        }

        .preview-video {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 8px;
        }

        .preview-placeholder {
            text-align: center;
            color: #64748b;
            padding: 3rem;
        }

        .preview-placeholder svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            color: #94a3b8;
        }

        .preview-placeholder h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #475569;
        }

        .preview-placeholder p {
            margin-bottom: 0.5rem;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .document-container {
                padding: 1rem;
            }

            .document-meta {
                grid-template-columns: 1fr;
            }

            .preview-image,
            .preview-pdf {
                max-height: 50vh;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>

<body>
    <div class="document-container">
        <!-- Header del documento -->
        <div class="document-header">
            <h1 class="document-title"><?php echo htmlspecialchars($document['name']); ?></h1>
            <p class="document-type"><?php echo htmlspecialchars($document['document_type'] ?? 'Sin categoría'); ?></p>
        </div>

        <!-- Metadatos del documento -->
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
                <span class="meta-label">Fecha</span>
                <span class="meta-value"><?php echo date('d/m/Y H:i', strtotime($document['created_at'])); ?></span>
            </div>
            <?php if ($document['description']): ?>
                <div class="meta-item" style="grid-column: 1 / -1;">
                    <span class="meta-label">Descripción</span>
                    <span class="meta-value"><?php echo htmlspecialchars($document['description']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Vista previa del documento -->
        <div class="document-preview">
            <?php if ($fileType === 'image'): ?>
                <img src="../../<?php echo htmlspecialchars($document['file_path']); ?>"
                     alt="<?php echo htmlspecialchars($document['name']); ?>"
                     class="preview-image">
            <?php elseif ($fileType === 'pdf'): ?>
                <iframe src="../../<?php echo htmlspecialchars($document['file_path']); ?>#toolbar=1"
                        class="preview-pdf"
                        title="<?php echo htmlspecialchars($document['name']); ?>">
                </iframe>
            <?php elseif ($fileType === 'video'): ?>
                <video controls class="preview-video">
                    <source src="../../<?php echo htmlspecialchars($document['file_path']); ?>" type="<?php echo $mimeType; ?>">
                    Tu navegador no soporta la reproducción de video.
                </video>
            <?php else: ?>
                <div class="preview-placeholder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14,2 14,8 20,8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10,9 9,9 8,9"></polyline>
                    </svg>
                    <h3>Vista previa no disponible</h3>
                    <p>Este tipo de archivo no se puede previsualizar en el navegador.</p>
                    <p><strong>Archivo:</strong> <?php echo htmlspecialchars($document['original_name']); ?></p>
                    <p><strong>Tipo:</strong> <?php echo htmlspecialchars($mimeType); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Botones de acción -->
        <div class="action-buttons">
            <?php if ($canDownload): ?>
                <button class="btn btn-primary" onclick="downloadDocument()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7,10 12,15 17,10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Descargar
                </button>
            <?php else: ?>
                <button class="btn btn-secondary disabled" title="Descarga deshabilitada">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7,10 12,15 17,10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Descarga Deshabilitada
                </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
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
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'download',
                        document_id: <?php echo $documentId; ?>
                    })
                }).catch(console.error);
            <?php endif; ?>
        }

        // Prevenir que se abra en nueva ventana
        window.addEventListener('beforeunload', function(e) {
            // Mantener en el mismo contexto
        });
    </script>
</body>
</html>