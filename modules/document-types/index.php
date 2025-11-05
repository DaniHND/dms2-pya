<?php
// modules/document-types/index.php
// Módulo de gestión de tipos de documentos - DMS2 - VERSIÓN DEFINITIVA

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permisos de administrador
SessionManager::requireRole('admin');

$currentUser = SessionManager::getCurrentUser();

// Configuración de paginación
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Filtros
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status' => $_GET['status'] ?? ''
];

// Construir consulta con filtros
$whereConditions = [];
$params = [];

if (!empty($filters['search'])) {
    $whereConditions[] = "(dt.name LIKE :search OR dt.description LIKE :search)";
    $params['search'] = '%' . $filters['search'] . '%';
}

if (!empty($filters['status'])) {
    $whereConditions[] = "dt.status = :status";
    $params['status'] = $filters['status'];
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Contar total de registros
$countQuery = "SELECT COUNT(*) as total 
               FROM document_types dt 
               $whereClause";

try {
    $totalItems = fetchOne($countQuery, $params)['total'];
    $totalPages = ceil($totalItems / $itemsPerPage);

    // Obtener tipos de documentos con estadísticas
    $query = "SELECT dt.*, 
                     (SELECT COUNT(*) FROM documents d WHERE d.document_type_id = dt.id AND d.status = 'active') as documents_count
              FROM document_types dt 
              $whereClause
              ORDER BY dt.created_at DESC 
              LIMIT :limit OFFSET :offset";

    $params['limit'] = $itemsPerPage;
    $params['offset'] = $offset;

    $database = new Database();
    $pdo = $database->getConnection();
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $value) {
        if ($key === 'limit' || $key === 'offset') {
            $stmt->bindValue(':' . $key, (int)$value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':' . $key, $value);
        }
    }
    
    $stmt->execute();
    $documentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $documentTypes = [];
    $totalItems = 0;
    $totalPages = 1;
    $error = "Error al cargar tipos de documentos: " . $e->getMessage();
}

// Funciones helper
function getStatusBadgeClass($status) {
    return $status === 'active' ? 'status-active' : 'status-inactive';
}

function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Registrar actividad
logActivity($currentUser['id'], 'view_document_types', 'document_types', null, 'Usuario accedió al módulo de tipos de documentos');

// Funciones helper necesarias
if (!function_exists('getFullName')) {
    function getFullName() {
        $user = SessionManager::getCurrentUser();
        return trim($user['first_name'] . ' ' . $user['last_name']);
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

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Documentos - DMS2</title>
    
    <!-- CSS Principal del sistema -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    
    <!-- CSS específico para tipos de documentos -->
    <link rel="stylesheet" href="../../assets/css/document-types.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">
    
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
        /* Estilos adicionales para iconos y botones */
        .btn-action {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: none;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            margin: 0 2px;
            transition: all 0.2s ease;
        }
        .btn-action:hover {
            background: rgba(59, 130, 246, 0.2);
        }
        .btn-action.edit {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        .btn-action.edit:hover {
            background: rgba(245, 158, 11, 0.2);
        }
        .btn-action.delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        .btn-action.delete:hover {
            background: rgba(239, 68, 68, 0.2);
        }
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
    </style>
</head>

<body class="dashboard-layout">
    <!-- DEFINIR FUNCIONES JAVASCRIPT INMEDIATAMENTE -->
    <script>
        console.log('🚀 Definiendo funciones globales...');
        
        // Variables globales
        var currentModal = null;
        var currentDocumentTypeId = null;

        // ==========================================
        // FUNCIÓN PARA FILTROS
        // ==========================================
        
        function handleFilterChange() {
            console.log('🔍 Cambio en filtros detectado');
            const form = document.getElementById('filtersForm');
            if (form) {
                form.submit();
            }
        }

        // ==========================================
        // FUNCIÓN CREAR TIPO DE DOCUMENTO
        // ==========================================
        
        function openCreateDocumentTypeModal() {
            console.log('🆕 Abriendo modal crear tipo de documento');
            
            currentDocumentTypeId = null;
            
            const modalHTML = `
                <div class="modal active" id="createDocumentTypeModal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                ">
                    <div style="
                        background: white;
                        border-radius: 12px;
                        padding: 24px;
                        width: 90%;
                        max-width: 500px;
                        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="margin: 0; color: #1e293b;">Nuevo Tipo de Documento</h3>
                            <button onclick="closeCreateDocumentTypeModal()" style="
                                background: none;
                                border: none;
                                font-size: 24px;
                                cursor: pointer;
                                color: #64748b;
                            ">&times;</button>
                        </div>
                        <form id="createDocumentTypeForm" onsubmit="handleCreateDocumentType(event)">
                            <div style="margin-bottom: 16px;">
                                <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151;">Nombre del Tipo *</label>
                                <input type="text" id="name" name="name" required 
                                       placeholder="Ej: Contrato, Factura, Reporte"
                                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box;">
                            </div>
                            
                            <div style="margin-bottom: 16px;">
                                <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151;">Descripción</label>
                                <textarea id="description" name="description" rows="3"
                                          placeholder="Describe el tipo de documento y su uso..."
                                          style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; resize: vertical; box-sizing: border-box;"></textarea>
                            </div>
                            
                            <div style="margin-bottom: 16px;">
                                <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151;">Estado</label>
                                <select id="status" name="status" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box;">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </div>
                            
                            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                                <button type="button" onclick="closeCreateDocumentTypeModal()" style="
                                    padding: 10px 20px;
                                    border: 1px solid #d1d5db;
                                    background: white;
                                    color: #374151;
                                    border-radius: 6px;
                                    cursor: pointer;
                                ">Cancelar</button>
                                <button type="submit" style="
                                    padding: 10px 20px;
                                    border: none;
                                    background: #8B4513;
                                    color: white;
                                    border-radius: 6px;
                                    cursor: pointer;
                                ">Crear Tipo de Documento</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            // Insertar modal en el DOM
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            currentModal = document.getElementById('createDocumentTypeModal');
            
            console.log('✅ Modal de crear tipo de documento abierto');
        }

        function closeCreateDocumentTypeModal() {
            console.log('❌ Cerrando modal de crear tipo de documento...');
            const modal = document.getElementById('createDocumentTypeModal');
            if (modal) {
                modal.remove();
                currentModal = null;
            }
        }

        function handleCreateDocumentType(event) {
            event.preventDefault();
            console.log('💾 Creando nuevo tipo de documento...');
            
            const form = event.target;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            
            // Deshabilitar botón
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creando...';
            
            fetch('actions/create_document_type.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Tipo de documento creado correctamente');
                    closeCreateDocumentTypeModal();
                    // Recargar página para mostrar el nuevo tipo
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Error al crear tipo de documento'));
                }
            })
            .catch(error => {
                console.error('Error creando tipo de documento:', error);
                alert('Error de conexión al servidor');
            })
            .finally(() => {
                // Rehabilitar botón
                submitBtn.disabled = false;
                submitBtn.textContent = 'Crear Tipo de Documento';
            });
        }

        // ==========================================
        // FUNCIONES DE ACCIONES
        // ==========================================

        function viewDocumentTypeDetails(documentTypeId) {
            console.log('👁️ Viendo detalles del tipo de documento:', documentTypeId);
            
            // Crear modal de carga
            const loadingModalHTML = `
                <div class="modal active" id="viewDocumentTypeModal" style="
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;
                ">
                    <div style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 600px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                        <div style="text-align: center;">
                            <h3>Cargando detalles...</h3>
                            <p>⏳ Obteniendo información del tipo de documento</p>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', loadingModalHTML);
            
            // Obtener detalles del servidor
            fetch(`actions/get_document_type_details.php?id=${documentTypeId}`)
                .then(response => response.json())
                .then(data => {
                    const modal = document.getElementById('viewDocumentTypeModal');
                    if (data.success) {
                        const details = data.document_type;
                        const stats = data.statistics;
                        
                        modal.innerHTML = `
                            <div style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 600px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3 style="margin: 0; color: #1e293b;">Detalles del Tipo de Documento</h3>
                                    <button onclick="closeViewDocumentTypeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">&times;</button>
                                </div>
                                
                                <div style="grid-template-columns: 1fr 1fr; gap: 20px; display: grid;">
                                    <div>
                                        <h4 style="color: #374151; margin-bottom: 12px;">Información General</h4>
                                        <div style="margin-bottom: 12px;">
                                            <strong>Nombre:</strong><br>
                                            <span style="color: #6b7280;">${details.name}</span>
                                        </div>
                                        <div style="margin-bottom: 12px;">
                                            <strong>Estado:</strong><br>
                                            <span class="status-${details.status}" style="padding: 2px 8px; border-radius: 4px; font-size: 0.8rem;">
                                                ${details.status === 'active' ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </div>
                                        ${details.description ? `
                                        <div style="margin-bottom: 12px;">
                                            <strong>Descripción:</strong><br>
                                            <span style="color: #6b7280;">${details.description}</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                    
                                    <div>
                                        <h4 style="color: #374151; margin-bottom: 12px;">Estadísticas</h4>
                                        <div style="margin-bottom: 12px;">
                                            <strong>Total Documentos:</strong><br>
                                            <span style="color: #6b7280; font-size: 1.2em;">${stats.total_documents}</span>
                                        </div>
                                        <div style="margin-bottom: 12px;">
                                            <strong>Documentos Activos:</strong><br>
                                            <span style="color: #10b981; font-size: 1.2em;">${stats.active_documents}</span>
                                        </div>
                                        ${stats.deleted_documents ? `
                                        <div style="margin-bottom: 12px;">
                                            <strong>Documentos Eliminados:</strong><br>
                                            <span style="color: #ef4444; font-size: 1.2em;">${stats.deleted_documents}</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                
                                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                                    <h4 style="color: #374151; margin-bottom: 12px;">Información de Fechas</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                        <div>
                                            <strong>Creado:</strong><br>
                                            <span style="color: #6b7280;">${details.formatted_created_date || 'N/A'}</span>
                                        </div>
                                        ${details.formatted_updated_date ? `
                                        <div>
                                            <strong>Actualizado:</strong><br>
                                            <span style="color: #6b7280;">${details.formatted_updated_date}</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                
                                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                                    <button onclick="closeViewDocumentTypeModal()" style="padding: 10px 20px; border: 1px solid #d1d5db; background: white; color: #374151; border-radius: 6px; cursor: pointer;">
                                        Cerrar
                                    </button>
                                    <button onclick="closeViewDocumentTypeModal(); editDocumentType(${documentTypeId})" style="padding: 10px 20px; border: none; background: #8B4513; color: white; border-radius: 6px; cursor: pointer;">
                                        Editar
                                    </button>
                                </div>
                            </div>
                        `;
                    } else {
                        modal.innerHTML = `
                            <div style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                                <div style="text-align: center; color: #ef4444;">
                                    <h3>Error</h3>
                                    <p>${data.message || 'No se pudieron cargar los detalles'}</p>
                                    <button onclick="closeViewDocumentTypeModal()" style="padding: 10px 20px; border: none; background: #ef4444; color: white; border-radius: 6px; cursor: pointer;">
                                        Cerrar
                                    </button>
                                </div>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error cargando detalles:', error);
                    const modal = document.getElementById('viewDocumentTypeModal');
                    modal.innerHTML = `
                        <div style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                            <div style="text-align: center; color: #ef4444;">
                                <h3>Error de Conexión</h3>
                                <p>No se pudo conectar con el servidor</p>
                                <button onclick="closeViewDocumentTypeModal()" style="padding: 10px 20px; border: none; background: #ef4444; color: white; border-radius: 6px; cursor: pointer;">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    `;
                });
        }

        function closeViewDocumentTypeModal() {
            const modal = document.getElementById('viewDocumentTypeModal');
            if (modal) {
                modal.remove();
            }
        }

        function editDocumentType(documentTypeId) {
            console.log('✏️ Editando tipo de documento:', documentTypeId);
            
            // Crear modal de carga
            const loadingModalHTML = `
                <div class="modal active" id="editDocumentTypeModal" style="
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;
                ">
                    <div style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                        <div style="text-align: center;">
                            <h3>Cargando...</h3>
                            <p>⏳ Obteniendo datos para editar</p>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', loadingModalHTML);
            
            // Obtener datos actuales del servidor
            fetch(`actions/get_document_type_details.php?id=${documentTypeId}`)
                .then(response => response.json())
                .then(data => {
                    const modal = document.getElementById('editDocumentTypeModal');
                    if (data.success) {
                        const details = data.document_type;
                        
                        modal.innerHTML = `
                            <div style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3 style="margin: 0; color: #1e293b;">Editar Tipo de Documento</h3>
                                    <button onclick="closeEditDocumentTypeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">&times;</button>
                                </div>
                                
                                <form id="editDocumentTypeForm" onsubmit="handleUpdateDocumentType(event)">
                                    <input type="hidden" name="document_type_id" value="${documentTypeId}">
                                    
                                    <div style="margin-bottom: 16px;">
                                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151;">Nombre del Tipo *</label>
                                        <input type="text" name="name" value="${details.name}" required 
                                               placeholder="Ej: Contrato, Factura, Reporte"
                                               style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box;">
                                    </div>
                                    
                                    <div style="margin-bottom: 16px;">
                                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151;">Descripción</label>
                                        <textarea name="description" rows="3" placeholder="Describe el tipo de documento y su uso..."
                                                  style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; resize: vertical; box-sizing: border-box;">${details.description || ''}</textarea>
                                    </div>
                                    
                                    <div style="margin-bottom: 16px;">
                                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #374151;">Estado</label>
                                        <select name="status" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box;">
                                            <option value="active" ${details.status === 'active' ? 'selected' : ''}>Activo</option>
                                            <option value="inactive" ${details.status === 'inactive' ? 'selected' : ''}>Inactivo</option>
                                        </select>
                                    </div>
                                    
                                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                                        <button type="button" onclick="closeEditDocumentTypeModal()" style="padding: 10px 20px; border: 1px solid #d1d5db; background: white; color: #374151; border-radius: 6px; cursor: pointer;">
                                            Cancelar
                                        </button>
                                        <button type="submit" style="padding: 10px 20px; border: none; background: #8B4513; color: white; border-radius: 6px; cursor: pointer;">
                                            Guardar Cambios
                                        </button>
                                    </div>
                                </form>
                            </div>
                        `;
                    } else {
                        modal.innerHTML = `
                            <div style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                                <div style="text-align: center; color: #ef4444;">
                                    <h3>Error</h3>
                                    <p>${data.message || 'No se pudieron cargar los datos'}</p>
                                    <button onclick="closeEditDocumentTypeModal()" style="padding: 10px 20px; border: none; background: #ef4444; color: white; border-radius: 6px; cursor: pointer;">
                                        Cerrar
                                    </button>
                                </div>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error cargando datos para editar:', error);
                    const modal = document.getElementById('editDocumentTypeModal');
                    modal.innerHTML = `
                        <div style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                            <div style="text-align: center; color: #ef4444;">
                                <h3>Error de Conexión</h3>
                                <p>No se pudo conectar con el servidor</p>
                                <button onclick="closeEditDocumentTypeModal()" style="padding: 10px 20px; border: none; background: #ef4444; color: white; border-radius: 6px; cursor: pointer;">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    `;
                });
        }

        function closeEditDocumentTypeModal() {
            const modal = document.getElementById('editDocumentTypeModal');
            if (modal) {
                modal.remove();
            }
        }

        function handleUpdateDocumentType(event) {
            event.preventDefault();
            console.log('💾 Actualizando tipo de documento...');
            
            const form = event.target;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            
            // Deshabilitar botón
            submitBtn.disabled = true;
            submitBtn.textContent = 'Guardando...';
            
            fetch('actions/update_document_type.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Tipo de documento actualizado correctamente');
                    closeEditDocumentTypeModal();
                    // Recargar página para mostrar los cambios
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Error al actualizar tipo de documento'));
                }
            })
            .catch(error => {
                console.error('Error actualizando tipo de documento:', error);
                alert('Error de conexión al servidor');
            })
            .finally(() => {
                // Rehabilitar botón
                submitBtn.disabled = false;
                submitBtn.textContent = 'Guardar Cambios';
            });
        }

        function toggleDocumentTypeStatus(documentTypeId, currentStatus) {
            const action = currentStatus === 'active' ? 'desactivar' : 'activar';
            const confirmMessage = `¿Está seguro que desea ${action} este tipo de documento?`;
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            console.log(`🔄 Cambiando estado del tipo de documento ${documentTypeId} de ${currentStatus}`);
            
            const formData = new FormData();
            formData.append('document_type_id', documentTypeId);
            formData.append('current_status', currentStatus);
            
            fetch('actions/toggle_document_type_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Tipo de documento ${action}do correctamente`);
                    // Recargar página para mostrar los cambios
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || `Error al ${action} tipo de documento`));
                }
            })
            .catch(error => {
                console.error('Error cambiando estado del tipo de documento:', error);
                alert('Error de conexión al servidor');
            });
        }

        // ==========================================
        // EVENTOS GLOBALES
        // ==========================================

        // Cerrar modales con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (currentModal) {
                    closeCreateDocumentTypeModal();
                }
                if (document.getElementById('viewDocumentTypeModal')) {
                    closeViewDocumentTypeModal();
                }
                if (document.getElementById('editDocumentTypeModal')) {
                    closeEditDocumentTypeModal();
                }
            }
        });

        // Cerrar modales al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (e.target.classList && e.target.classList.contains('modal')) {
                if (e.target.id === 'createDocumentTypeModal') {
                    closeCreateDocumentTypeModal();
                }
                if (e.target.id === 'viewDocumentTypeModal') {
                    closeViewDocumentTypeModal();
                }
                if (e.target.id === 'editDocumentTypeModal') {
                    closeEditDocumentTypeModal();
                }
            }
        });

        console.log('✅ Funciones JavaScript definidas globalmente');
    </script>

    <!-- Scripts principales -->
    <script src="../../assets/js/main.js"></script>

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
                <h1>Tipos de Documentos</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(getFullName()); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido del módulo -->
        <div class="container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i data-feather="alert-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Header de página con botón crear -->
            <div class="page-header">
                <div class="page-title-section">
                    <button class="btn btn-primary btn-create-company" onclick="openCreateDocumentTypeModal()">
                        <i data-feather="file-text"></i>
                        <span>Crear Tipo de Documento</span>
                    </button>
                </div>
            </div>

            <!-- Filtros de Búsqueda -->
            <div class="filters-card">
                <h3>Filtros de Búsqueda</h3>
                <form method="GET" class="filters-form" id="filtersForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Buscar Tipo de Documento</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   placeholder="Nombre, descripción..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>"
                                   onkeyup="handleFilterChange()">
                        </div>

                        <div class="filter-group">
                            <label for="status">Estado</label>
                            <select id="status" name="status" onchange="handleFilterChange()">
                                <option value="">Todos los estados</option>
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>
                                    Activo
                                </option>
                                <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>
                                    Inactivo
                                </option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabla de tipos de documentos -->
            <div class="table-section">
                <div class="table-header">
                    <h3>Tipos de Documentos (<?php echo $totalItems; ?> registros)</h3>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tipo de Documento</th>
                                <th>Documentos</th>
                                <th>Estado</th>
                                <th>Fecha Creación</th>
                                <th class="actions-header">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documentTypes)): ?>
                                <tr>
                                    <td colspan="5" class="no-data">
                                        <i data-feather="file-text"></i>
                                        <p>No se encontraron tipos de documentos</p>
                                        <button class="btn btn-primary" onclick="openCreateDocumentTypeModal()">
                                            <i data-feather="plus"></i>
                                            Crear primer tipo de documento
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documentTypes as $docType): ?>
                                    <tr>
                                        <td>
                                            <div class="cell-content">
                                                <div class="primary-text">
                                                    <?php if (!empty($docType['icon'])): ?>
                                                        <i data-feather="<?php echo htmlspecialchars($docType['icon']); ?>" style="color: <?php echo htmlspecialchars($docType['color'] ?? '#6b7280'); ?>; margin-right: 8px;"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($docType['name']); ?>
                                                </div>
                                                <?php if (!empty($docType['description'])): ?>
                                                    <div class="secondary-text"><?php echo htmlspecialchars($docType['description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cell-content">
                                                <div class="primary-text"><?php echo $docType['documents_count']; ?> documentos</div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusBadgeClass($docType['status']); ?>">
                                                <?php echo $docType['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="cell-content">
                                                <div class="primary-text"><?php echo formatDate($docType['created_at']); ?></div>
                                            </div>
                                        </td>
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <button class="btn-action view" 
                                                        onclick="viewDocumentTypeDetails(<?php echo $docType['id']; ?>)"
                                                        title="Ver detalles">
                                                    <i data-feather="eye"></i>
                                                </button>
                                                <button class="btn-action edit" 
                                                        onclick="editDocumentType(<?php echo $docType['id']; ?>)"
                                                        title="Editar tipo de documento">
                                                    <i data-feather="edit"></i>
                                                </button>
                                                <button class="btn-action delete" 
                                                        onclick="toggleDocumentTypeStatus(<?php echo $docType['id']; ?>, '<?php echo $docType['status']; ?>')"
                                                        title="<?php echo $docType['status'] === 'active' ? 'Desactivar' : 'Activar'; ?>">
                                                    <i data-feather="power"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-section">
                        <div class="pagination-info">
                            Mostrando <?php echo count($documentTypes); ?> de <?php echo $totalItems; ?> registros
                        </div>
                        
                        <div class="pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="?page=<?php echo $currentPage - 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>" class="pagination-btn">
                                    <i data-feather="chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($filters)); ?>" 
                                   class="pagination-btn <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?page=<?php echo $currentPage + 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>" class="pagination-btn">
                                    <i data-feather="chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Script de inicialización final -->
    <script>
        // Inicialización cuando todo esté cargado
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📄 DOM cargado - Inicializando iconos...');
            
            // Inicializar iconos de Feather
            if (typeof feather !== 'undefined') {
                feather.replace();
                console.log('✅ Iconos Feather inicializados');
            } else {
                console.warn('⚠️ Feather Icons no disponible');
            }
            
            // Actualizar tiempo si la función existe
            if (typeof updateTime === 'function') {
                updateTime();
                console.log('✅ Reloj actualizado');
            }
            
            console.log('✅ Módulo completamente inicializado');
        });
    </script>
</body>
</html>