/**
 * assets/js/inbox-visual.js
 * JavaScript para el Explorador Visual de Documentos
 * DMS2 - Sistema de Gestión Documental
 */

// ====================================================================
// VARIABLES GLOBALES
// ====================================================================

let searchTimeout;
let cutDocumentData = null;
let currentView = 'grid';

// ====================================================================
// FUNCIONES DE NAVEGACIÓN
// ====================================================================

function navigateTo(path) {
    window.location.href = `?path=${encodeURIComponent(path)}`;
}

function search(term) {
    const url = new URL(window.location);
    if (term.trim()) {
        url.searchParams.set('search', term.trim());
    } else {
        url.searchParams.delete('search');
    }
    url.searchParams.delete('path'); 
    window.location.href = url.toString();
}

function handleSearchInput(term) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (term.length >= 2) {
            search(term);
        }
    }, 500);
}

function clearSearch() {
    const url = new URL(window.location);
    url.searchParams.delete('search');
    window.location.href = url.toString();
}

// ====================================================================
// FUNCIONES DE VISTA
// ====================================================================

function changeView(view) {
    currentView = view;
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    const buttons = document.querySelectorAll('.view-btn');

    buttons.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === view);
    });

    if (view === 'grid') {
        gridView.style.display = 'grid';
        listView.style.display = 'none';
    } else {
        gridView.style.display = 'none';
        listView.style.display = 'block';
    }

    localStorage.setItem('explorer_view', view);
}

// ====================================================================
// FUNCIONES DE DOCUMENTOS
// ====================================================================

function viewDocument(documentId) {
    if (!canView) {
        showNotification('❌ No tienes permisos para ver documentos', 'error');
        return;
    }
    
    // Abrir en nueva ventana en lugar de modal
    window.open(`view.php?id=${documentId}`, '_blank');
}

async function showPreviewModal(documentId) {
    const modal = document.getElementById('previewModal');
    const title = document.getElementById('previewTitle');
    const content = document.getElementById('previewContent');

    try {
        const response = await fetch(`view.php?id=${documentId}&preview=1`);
        const data = await response.json();

        if (data.success) {
            title.querySelector('span').textContent = `Vista Previa - ${data.document.name}`;
            
            if (data.document.mime_type.startsWith('image/')) {
                content.innerHTML = `<img src="../../${data.document.file_path}" alt="Preview" style="max-width: 100%; height: auto; border-radius: 8px;">`;
            } else if (data.document.mime_type === 'application/pdf') {
                content.innerHTML = `<iframe src="../../${data.document.file_path}" style="width: 100%; height: 600px; border: none; border-radius: 8px;"></iframe>`;
            } else {
                content.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i data-feather="file" style="width: 64px; height: 64px; color: #64748b; margin-bottom: 16px;"></i>
                        <h3>${data.document.name}</h3>
                        <p>Tipo: ${data.document.document_type || 'Documento'}</p>
                        <p>Tamaño: ${formatBytes(data.document.file_size)}</p>
                        <p>Fecha: ${formatDate(data.document.created_at)}</p>
                        <a href="view.php?id=${documentId}" class="btn-create" style="margin-top: 16px;">
                            <i data-feather="external-link"></i>
                            Abrir Documento
                        </a>
                    </div>
                `;
            }
            
            modal.classList.add('active');
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        } else {
            showNotification('❌ Error al cargar vista previa', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        content.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <i data-feather="alert-circle" style="width: 64px; height: 64px; color: #ef4444; margin-bottom: 16px;"></i>
                <h3>Error al cargar vista previa</h3>
                <p>No se pudo cargar la vista previa del documento.</p>
                <a href="view.php?id=${documentId}" class="btn-create" style="margin-top: 16px;">
                    <i data-feather="external-link"></i>
                    Abrir Documento
                </a>
            </div>
        `;
        modal.classList.add('active');
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }
}

function closePreviewModal() {
    const modal = document.getElementById('previewModal');
    modal.classList.remove('active');
}

function downloadDocument(documentId) {
    if (!canDownload) {
        showNotification('❌ No tienes permisos para descargar documentos', 'error');
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
    }, 2000);
}

function cutDocument(docId, docName) {
    if (!canEdit) {
        showNotification('❌ No tienes permisos para mover documentos', 'error');
        return;
    }

    cutDocumentData = { 
        id: docId, 
        name: docName, 
        path: currentPath 
    };
    
    const indicator = document.getElementById('clipboardIndicator');
    const nameElement = document.getElementById('clipboardName');
    nameElement.textContent = docName;
    indicator.style.display = 'block';
    
    showNotification(`📁 "${docName}" marcado para mover. Navegue a la ubicación destino y presione Ctrl+V para pegar.`, 'info', 5000);
}

function pasteDocument() {
    if (!cutDocumentData) {
        showNotification('❌ No hay ningún archivo cortado', 'error');
        return;
    }

    if (!canEdit) {
        showNotification('❌ No tienes permisos para mover documentos', 'error');
        return;
    }

    if (cutDocumentData.path === currentPath) {
        showNotification('ℹ️ El archivo ya está en esta ubicación', 'warning');
        return;
    }

    moveDocumentToPath(cutDocumentData.id, currentPath, cutDocumentData.name);
}

function cancelCut() {
    cutDocumentData = null;
    const indicator = document.getElementById('clipboardIndicator');
    indicator.style.display = 'none';
    showNotification('❌ Operación cancelada', 'info');
}

async function moveDocumentToPath(docId, targetPath, docName) {
    try {
        const response = await fetch('move_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                document_id: parseInt(docId),
                target_path: targetPath
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification(`✅ "${docName}" movido exitosamente`, 'success');
            cutDocumentData = null;
            const indicator = document.getElementById('clipboardIndicator');
            indicator.style.display = 'none';
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(`❌ ${result.message}`, 'error');
        }
    } catch (error) {
        showNotification('❌ Error de conexión al mover documento', 'error');
        console.error('Error:', error);
    }
}

// ===================================================================
// BUSCAR Y REEMPLAZAR LA FUNCIÓN deleteDocument EN assets/js/inbox-visual.js
// ===================================================================

// ==========================================
// CORRECCIÓN COMPLETA DEL SISTEMA DE ELIMINACIÓN
// AGREGAR ESTE CÓDIGO AL FINAL DE assets/js/inbox-visual.js
// ==========================================

/**
 * SOBRESCRIBIR la función deleteDocument existente
 */
// BUSCAR esta función en assets/js/inbox-visual.js y REEMPLAZARLA por esta versión:

function deleteDocument(documentId, documentName) {
    console.log('🗑️ INICIO - Eliminar documento ID:', documentId);
    
    if (!documentId) {
        console.error('🗑️ ERROR: ID de documento vacío');
        alert('Error: ID de documento no válido');
        return;
    }
    
    // Verificar permisos básicos si la variable existe
    if (typeof canDelete !== 'undefined' && !canDelete) {
        console.log('🗑️ ERROR: Sin permisos para eliminar');
        alert('No tienes permisos para eliminar documentos');
        return;
    }
    
    // Preparar mensaje de confirmación
    let confirmMessage = `¿Eliminar documento?`;
    
    if (documentName) {
        confirmMessage = `¿Eliminar documento?

📄 ${documentName}

⚠️ Esta acción no se puede deshacer.`;
    } else {
        confirmMessage = `¿Eliminar documento ID: ${documentId}?

⚠️ Esta acción no se puede deshacer.`;
    }
    
    // Confirmaciones
    if (!confirm(confirmMessage)) {
        console.log('🗑️ Usuario canceló en primera confirmación');
        return;
    }
    
    if (!confirm('¿Está completamente seguro? Esta es la última oportunidad para cancelar.')) {
        console.log('🗑️ Usuario canceló en segunda confirmación');
        return;
    }
    
    console.log('🗑️ Usuario confirmó eliminación, procediendo...');
    
    // *** OBTENER PATH ACTUAL - Método simplificado ***
    function getSimpleCurrentPath() {
        // Método 1: Desde URL
        const urlParams = new URLSearchParams(window.location.search);
        const pathFromUrl = urlParams.get('path');
        
        if (pathFromUrl) {
            console.log('🗑️ Path de URL:', pathFromUrl);
            return pathFromUrl;
        }
        
        // Método 2: Desde variable global si existe
        if (typeof currentPath !== 'undefined' && currentPath) {
            console.log('🗑️ Path de variable:', currentPath);
            return currentPath;
        }
        
        // Método 3: Desde breadcrumbs
        const breadcrumbs = document.querySelectorAll('.breadcrumb-item');
        if (breadcrumbs.length > 0) {
            const lastBreadcrumb = breadcrumbs[breadcrumbs.length - 1];
            const pathFromBreadcrumb = lastBreadcrumb.dataset.breadcrumbPath || '';
            if (pathFromBreadcrumb) {
                console.log('🗑️ Path de breadcrumb:', pathFromBreadcrumb);
                return pathFromBreadcrumb;
            }
        }
        
        console.log('🗑️ No se pudo obtener path');
        return '';
    }
    
    const currentPath = getSimpleCurrentPath();
    console.log('🗑️ Path final obtenido:', currentPath);
    
    // Crear y enviar formulario de eliminación
    try {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete.php';
        form.style.display = 'none';
        
        // Campo del ID del documento
        const inputDocId = document.createElement('input');
        inputDocId.type = 'hidden';
        inputDocId.name = 'document_id';
        inputDocId.value = documentId.toString();
        form.appendChild(inputDocId);
        
        // *** AGREGAR return_path ***
        if (currentPath) {
            const inputReturnPath = document.createElement('input');
            inputReturnPath.type = 'hidden';
            inputReturnPath.name = 'return_path';
            inputReturnPath.value = currentPath;
            form.appendChild(inputReturnPath);
            console.log('🗑️ Agregando return_path:', currentPath);
        } else {
            console.log('🗑️ WARNING: No se pudo obtener currentPath');
        }
        
        document.body.appendChild(form);
        
        console.log('🗑️ Enviando formulario de eliminación');
        
        // Mostrar notificación si la función existe
        if (typeof showNotification === 'function') {
            showNotification('Eliminando documento...', 'warning', 3000);
        }
        
        form.submit();
        
    } catch (error) {
        console.error('🗑️ ERROR al crear/enviar formulario:', error);
        alert('Error al eliminar documento: ' + error.message);
    }
}// FUNCIONES DE CARPETAS
// ====================================================================

function createDocumentFolder() {
    if (!canCreate) {
        showNotification('❌ No tienes permisos para crear carpetas', 'error');
        return;
    }

    const pathParts = currentPath.split('/');
    if (pathParts.length !== 2 || !pathParts[0] || !pathParts[1]) {
        showNotification('❌ Solo se pueden crear carpetas dentro de un departamento', 'error');
        return;
    }

    const modal = document.getElementById('createDocumentFolderModal');
    modal.classList.add('active');

    setTimeout(() => {
        const nameInput = document.querySelector('#createDocumentFolderModal input[name="name"]');
        if (nameInput) nameInput.focus();
    }, 100);
}

function closeDocumentFolderModal() {
    const modal = document.getElementById('createDocumentFolderModal');
    modal.classList.remove('active');
    document.getElementById('createDocumentFolderForm').reset();
}

async function submitCreateDocumentFolder(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    try {
        submitBtn.innerHTML = '<i data-feather="loader"></i> <span>Creando...</span>';
        submitBtn.disabled = true;

        const response = await fetch('create_folder.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showNotification('✅ Carpeta creada exitosamente', 'success');
            closeDocumentFolderModal();
            window.location.reload();
        } else {
            showNotification('❌ ' + (data.message || 'Error al crear la carpeta'), 'error');
        }

    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error de conexión al crear la carpeta', 'error');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        feather.replace();
    }
}

// ====================================================================
// SISTEMA DE DRAG & DROP
// ====================================================================

class DocumentDragDrop {
    constructor() {
        this.draggedDocument = null;
        this.init();
    }

    init() {
        this.setupDraggers();
        this.setupDropZones();
        this.setupBreadcrumbDrops();
        console.log('📁 Sistema de drag & drop inicializado');
    }

    setupDraggers() {
        document.querySelectorAll('.draggable-item').forEach(item => {
            item.addEventListener('dragstart', (e) => {
                if (!canEdit) {
                    e.preventDefault();
                    showNotification('❌ No tienes permisos para mover documentos', 'error');
                    return;
                }

                const docId = item.dataset.itemId;
                const docType = item.dataset.itemType;
                
                if (docType !== 'document') {
                    e.preventDefault();
                    return;
                }

                this.draggedDocument = { 
                    id: docId, 
                    element: item,
                    name: item.querySelector('.item-name, .name-text')?.textContent || 'Documento'
                };
                item.classList.add('dragging');
                
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', docId);
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
            });
        });
    }

    setupDropZones() {
        document.querySelectorAll('.drop-target').forEach(target => {
            target.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            target.addEventListener('dragenter', (e) => {
                e.preventDefault();
                if (this.draggedDocument && target.dataset.itemType === 'document_folder') {
                    target.classList.add('drag-over');
                }
            });

            target.addEventListener('dragleave', (e) => {
                if (!target.contains(e.relatedTarget)) {
                    target.classList.remove('drag-over');
                }
            });

            target.addEventListener('drop', (e) => {
                e.preventDefault();
                target.classList.remove('drag-over');

                if (!this.draggedDocument || target.dataset.itemType !== 'document_folder') {
                    return;
                }

                const folderId = target.dataset.folderId;
                const folderName = target.querySelector('.item-name, .name-text')?.textContent || 'Carpeta';
                
                this.moveDocumentToFolder(this.draggedDocument.id, folderId, folderName);
            });
        });
    }

    setupBreadcrumbDrops() {
        document.querySelectorAll('.breadcrumb-drop-target').forEach(breadcrumb => {
            breadcrumb.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            breadcrumb.addEventListener('dragenter', (e) => {
                e.preventDefault();
                if (this.draggedDocument) {
                    breadcrumb.classList.add('drag-over');
                }
            });

            breadcrumb.addEventListener('dragleave', (e) => {
                if (!breadcrumb.contains(e.relatedTarget)) {
                    breadcrumb.classList.remove('drag-over');
                }
            });

            breadcrumb.addEventListener('drop', (e) => {
                e.preventDefault();
                breadcrumb.classList.remove('drag-over');

                if (!this.draggedDocument) {
                    return;
                }

                const targetPath = breadcrumb.dataset.breadcrumbPath;
                const locationName = breadcrumb.querySelector('span')?.textContent || 'Ubicación';
                
                this.moveDocumentToPath(this.draggedDocument.id, targetPath, locationName);
            });
        });
    }

    async moveDocumentToFolder(docId, folderId, folderName) {
        try {
            const response = await fetch('move_document.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    document_id: parseInt(docId),
                    folder_id: parseInt(folderId)
                })
            });

            const result = await response.json();
            
            if (result.success) {
                showNotification(`✅ Documento movido a: ${folderName}`, 'success');
                window.location.reload();
            } else {
                showNotification(`❌ ${result.message}`, 'error');
            }
        } catch (error) {
            showNotification('❌ Error de conexión al mover documento', 'error');
            console.error('Error:', error);
        }
    }

    async moveDocumentToPath(docId, targetPath, locationName) {
        try {
            const response = await fetch('move_document.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    document_id: parseInt(docId),
                    target_path: targetPath
                })
            });

            const result = await response.json();
            
            if (result.success) {
                showNotification(`✅ Documento movido a: ${locationName}`, 'success');
                window.location.reload();
            } else {
                showNotification(`❌ ${result.message}`, 'error');
            }
        } catch (error) {
            showNotification('❌ Error de conexión al mover documento', 'error');
            console.error('Error:', error);
        }
    }
}

// ====================================================================
// SISTEMA DE NOTIFICACIONES
// ====================================================================

function showNotification(message, type = 'info', duration = 5000) {
    // Eliminar notificaciones anteriores
    const existing = document.querySelectorAll('.notification');
    existing.forEach(n => n.remove());

    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="notification-close">×</button>
        </div>
    `;

    if (!document.querySelector('#notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                padding: 12px 16px;
                z-index: 10000;
                max-width: 400px;
                animation: slideInRight 0.3s ease;
            }
            .notification-success { border-left: 4px solid #10b981; }
            .notification-error { border-left: 4px solid #ef4444; }
            .notification-warning { border-left: 4px solid #f59e0b; }
            .notification-info { border-left: 4px solid #3b82f6; }
            .notification-content {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
            }
            .notification-close {
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                color: #6b7280;
            }
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styles);
    }

    document.body.appendChild(notification);

    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, duration);
}

// ====================================================================
// FUNCIONES AUXILIARES
// ====================================================================

function formatBytes(bytes) {
    if (bytes == 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const pow = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, pow) * 10) / 10 + ' ' + units[pow];
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showSettings() {
    showNotification('⚙️ Configuración estará disponible próximamente', 'info');
}

function toggleSidebar() {
    // Función para el menú móvil
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

// ====================================================================
// EVENTOS DE TECLADO
// ====================================================================

document.addEventListener('keydown', (e) => {
    // Escape para cerrar modales
    if (e.key === 'Escape') {
        closeDocumentFolderModal();
        closePreviewModal();
    }

    // Ctrl+F para enfocar búsqueda
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    // Ctrl+V para pegar documento cortado
    if (e.ctrlKey && e.key === 'v') {
        e.preventDefault();
        if (cutDocumentData) {
            pasteDocument();
        }
    }

    // Backspace para navegar hacia atrás
    if (e.key === 'Backspace' && !e.target.matches('input, textarea')) {
        e.preventDefault();
        const breadcrumbs = document.querySelectorAll('.breadcrumb-item');
        if (breadcrumbs.length > 1) {
            breadcrumbs[breadcrumbs.length - 2].click();
        }
    }
});

// ====================================================================
// EVENTOS DE CLIC EN MODALES
// ====================================================================

document.addEventListener('click', (e) => {
    const folderModal = document.getElementById('createDocumentFolderModal');
    const previewModal = document.getElementById('previewModal');
    
    if (e.target === folderModal) {
        closeDocumentFolderModal();
    }
    if (e.target === previewModal) {
        closePreviewModal();
    }
});

// ====================================================================
// INICIALIZACIÓN
// ====================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Verificar permisos de acceso
    if (!canView && currentUserRole !== 'admin') {
        showNotification('⚠️ Su usuario tiene permisos limitados. Contacte al administrador para obtener más acceso.', 'warning', 8000);
    }

    // Inicializar iconos Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Inicializar reloj
    updateTime();
    setInterval(updateTime, 60000);

    // Restaurar vista preferida
    const savedView = localStorage.getItem('explorer_view') || 'grid';
    changeView(savedView);

    // Inicializar drag & drop (solo si tiene permisos de edición)
    if (canEdit && document.querySelectorAll('.draggable-item, .drop-target, .breadcrumb-drop-target').length > 0) {
        new DocumentDragDrop();
    }

    console.log('📁 Explorador visual optimizado iniciado');
    console.log('👤 Usuario:', currentUserId, '| Rol:', currentUserRole);
    console.log('📂 Ruta actual:', currentPath || 'Raíz');
    console.log('🔐 Permisos:', {
        '👀 Ver': canView,
        '⬇️ Descargar': canDownload,
        '➕ Crear': canCreate,
        '📁 Mover': canEdit,
        '🗑️ Eliminar': canDelete
    });
});

// ==============================================================
// PARCHE RÁPIDO: Agregar al final de assets/js/inbox-visual.js
// ==============================================================

// Sobrescribir la función viewDocument para usar modal
(function() {
    console.log('🔧 Aplicando parche para viewDocument modal');
    
    // Sobrescribir función global
    window.viewDocument = function(documentId) {
        console.log('👁️ viewDocument interceptado - usando modal para ID:', documentId);
        
        if (typeof canView !== 'undefined' && !canView) {
            showNotification('❌ No tienes permisos para ver documentos', 'error');
            return;
        }
        
        // Usar modal existente
        if (typeof showPreviewModal === 'function') {
            showPreviewModal(documentId);
        } else {
            // Fallback si no existe showPreviewModal
            console.warn('⚠️ showPreviewModal no encontrada, usando nueva ventana');
            window.open(`view.php?id=${documentId}`, '_blank');
        }
    };
    
    console.log('✅ viewDocument sobrescrita correctamente');
})();

// ==============================================================
// MODAL DE VISTA PREVIA - SOLUCIÓN LIMPIA Y UNIFICADA
// ==============================================================

console.log('🔧 Configurando modal de documentos...');

// Sobrescribir viewDocument para usar modal
window.viewDocument = function(documentId) {
    console.log('👁️ Abriendo documento en modal:', documentId);
    
    if (typeof canView !== 'undefined' && !canView) {
        showNotification('❌ No tienes permisos para ver documentos', 'error');
        return;
    }
    
    showDocumentModal(documentId);
};

async function showDocumentModal(documentId) {
    console.log('📖 Mostrando modal para documento:', documentId);
    
    // Crear modal si no existe
    ensureModal();
    
    const modal = document.getElementById('documentModal');
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    
    // Mostrar loading
    title.textContent = '🔄 Cargando documento...';
    content.innerHTML = '<div style="text-align: center; padding: 3rem; color: #64748b;">Cargando documento...</div>';
    
    // Mostrar modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    try {
        console.log('📡 Haciendo petición a preview.php...');
console.log('🌐 URL completa:', window.location.origin + window.location.pathname.replace('inbox.php', 'preview.php') + `?id=${documentId}`);

const response = await fetch(`preview.php?id=${documentId}`);
console.log('📊 Response status:', response.status);
console.log('📊 Response headers:', Object.fromEntries(response.headers.entries()));

if (!response.ok) {
    const errorText = await response.text();
    console.log('❌ Error response text:', errorText);
    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
}

const responseText = await response.text();
console.log('📄 Response text raw:', responseText);

let data;
try {
    data = JSON.parse(responseText);
    console.log('📄 Datos parseados:', data);
} catch (e) {
    console.log('❌ Error parsing JSON:', e);
    console.log('📄 Raw response:', responseText);
    throw new Error('Respuesta no es JSON válido');
}
        
        if (!data.success) {
            throw new Error(data.message || 'Error desconocido');
        }
        
        const doc = data.document;
        
        // Actualizar título
        title.innerHTML = `
            📄 ${doc.name}
            <div style="margin-left: auto; display: flex; gap: 8px;">
                <button onclick="window.open('view.php?id=${documentId}', '_blank')" 
                        style="padding: 8px 12px; border: 1px solid #ddd; background: white; cursor: pointer; border-radius: 4px; font-size: 12px;">
                    🔗 Nueva ventana
                </button>
                <button onclick="closeDocumentModal()" 
                        style="padding: 8px 12px; border: 1px solid #ddd; background: white; cursor: pointer; border-radius: 4px; font-size: 12px;">
                    ✕ Cerrar
                </button>
            </div>
        `;
        
        // Mostrar contenido según tipo
        // Mostrar contenido según tipo
console.log('📋 Tipo de archivo detectado:', doc.file_type);
console.log('📂 Ruta del archivo:', doc.file_path);

if (doc.file_type === 'image') {
    console.log('🖼️ Mostrando imagen');
    content.innerHTML = `
        <div style="text-align: center; padding: 1rem;">
            <img src="../../${doc.file_path}" 
                 alt="${doc.name}" 
                 style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"
                 onload="console.log('✅ Imagen cargada exitosamente');"
                 onerror="console.error('❌ Error cargando imagen'); this.style.display='none'; document.getElementById('imageError').style.display='block';">
            
            <div id="imageError" style="display: none; text-align: center; color: #ef4444; padding: 2rem;">
                <h3>❌ Error al cargar la imagen</h3>
                <p style="font-size: 14px; color: #666; margin: 1rem 0;">Ruta: ../../${doc.file_path}</p>
                <button onclick="window.open('view.php?id=${documentId}', '_blank')" 
                        style="background: #3b82f6; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer;">
                    🔗 Ver en nueva ventana
                </button>
            </div>
        </div>
    `;
} else if (doc.file_type === 'pdf') {
    console.log('📄 Mostrando PDF');
    content.innerHTML = `
        <iframe src="../../${doc.file_path}" 
                style="width: 100%; height: 70vh; border: none; border-radius: 8px;"
                onload="console.log('✅ PDF cargado exitosamente')">
        </iframe>
    `;
} else {
    console.log('📄 Mostrando información del archivo');
    // Para otros tipos, mostrar información del archivo
    showDocumentInfo(doc, content, documentId);
}
        
        console.log('✅ Modal cargado exitosamente');
        
    } catch (error) {
        console.error('❌ Error al cargar documento:', error);
        title.textContent = '❌ Error al cargar documento';
        content.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: #ef4444;">
                <h3 style="color: #ef4444;">Error al cargar documento</h3>
                <p style="margin: 1rem 0;">${error.message}</p>
                <div style="margin-top: 2rem;">
                    <button onclick="window.open('view.php?id=${documentId}', '_blank')" 
                            style="background: #3b82f6; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; margin: 8px;">
                        🔗 Abrir en nueva ventana
                    </button>
                    <button onclick="closeDocumentModal()" 
                            style="background: #6b7280; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; margin: 8px;">
                        Cerrar
                    </button>
                </div>
            </div>
        `;
    }
}

function ensureModal() {
    if (document.getElementById('documentModal')) return;
    
    console.log('📦 Creando modal de documentos...');
    
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
    `;
    
    modal.innerHTML = `
        <div style="background: white; border-radius: 12px; max-width: 90vw; max-height: 90vh; overflow: hidden; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
            <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <h3 id="modalTitle" style="margin: 0; flex: 1; display: flex; align-items: center; justify-content: space-between;">Vista Previa</h3>
            </div>
            <div style="padding: 20px; max-height: calc(90vh - 100px); overflow-y: auto;">
                <div id="modalContent"></div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Eventos
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeDocumentModal();
    });
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeDocumentModal();
        }
    });
    
    console.log('✅ Modal creado');
}

function closeDocumentModal() {
    const modal = document.getElementById('documentModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        console.log('🔒 Modal cerrado');
    }
}

console.log('✅ Sistema de modal de documentos configurado');

// Mensaje de confirmación
setTimeout(() => {
    console.log('🎯 viewDocument function:', typeof window.viewDocument);
    console.log('📋 Modal functions ready:', 
        typeof showDocumentModal, 
        typeof ensureModal, 
        typeof closeDocumentModal
    );
}, 1000);
// PARCHE RÁPIDO PARA PERMISOS DE USUARIO
// Agregar al final de assets/js/inbox-visual.js

console.log('🔧 Aplicando corrección de permisos de usuario...');

// Debug inicial de permisos
console.log('🔍 Variables de permisos disponibles:');
console.log('- canView:', typeof canView !== 'undefined' ? canView : 'undefined');
console.log('- canDownload:', typeof canDownload !== 'undefined' ? canDownload : 'undefined');
console.log('- canCreate:', typeof canCreate !== 'undefined' ? canCreate : 'undefined');
console.log('- canEdit:', typeof canEdit !== 'undefined' ? canEdit : 'undefined');
console.log('- canDelete:', typeof canDelete !== 'undefined' ? canDelete : 'undefined');
console.log('- currentUserRole:', typeof currentUserRole !== 'undefined' ? currentUserRole : 'undefined');

// Función para verificar y corregir permisos
function checkAndFixPermissions() {
    // Si las variables no están definidas, probablemente hay un problema
    if (typeof canDelete === 'undefined') {
        console.error('❌ Variable canDelete no definida');
        window.canDelete = false;
    }
    
    if (typeof canEdit === 'undefined') {
        console.error('❌ Variable canEdit no definida');
        window.canEdit = false;
    }
    
    if (typeof canView === 'undefined') {
        console.error('❌ Variable canView no definida');
        window.canView = false;
    }
    
    // Si es admin, forzar todos los permisos
    if (typeof currentUserRole !== 'undefined' && currentUserRole === 'admin') {
        console.log('👑 Usuario admin detectado, habilitando todos los permisos');
        window.canView = true;
        window.canDownload = true;
        window.canCreate = true;
        window.canEdit = true;
        window.canDelete = true;
    }
    
    console.log('✅ Permisos verificados y corregidos:', {
        canView: window.canView,
        canDownload: window.canDownload,
        canCreate: window.canCreate,
        canEdit: window.canEdit,
        canDelete: window.canDelete
    });
}

// Función de eliminación corregida
function deleteDocumentFixed(documentId, documentName) {
    console.log('🗑️ Función de eliminación corregida ejecutada');
    console.log('📋 Datos recibidos:', { documentId, documentName });
    console.log('🔑 Permisos actuales:', { canDelete: window.canDelete, role: currentUserRole });
    
    if (!documentId) {
        console.error('❌ ID de documento vacío');
        alert('Error: ID de documento no válido');
        return;
    }
    
    // Verificar permisos
    if (typeof canDelete !== 'undefined' && !canDelete && currentUserRole !== 'admin') {
        console.log('🚫 Sin permisos para eliminar');
        alert('No tienes permisos para eliminar documentos');
        return;
    }
    
    // Mensaje de confirmación
    const message = documentName ? 
        `¿Eliminar documento "${documentName}"?\n\n⚠️ Esta acción no se puede deshacer.` :
        `¿Eliminar documento ID: ${documentId}?\n\n⚠️ Esta acción no se puede deshacer.`;
    
    if (!confirm(message)) {
        console.log('❌ Usuario canceló eliminación');
        return;
    }
    
    if (!confirm('¿Está completamente seguro? Esta es la última oportunidad para cancelar.')) {
        console.log('❌ Usuario canceló en segunda confirmación');
        return;
    }
    
    console.log('✅ Usuario confirmó eliminación, procediendo...');
    
    // Obtener path actual
    const currentPath = getCurrentPath();
    console.log('📂 Path actual:', currentPath);
    
    // Crear formulario de eliminación
    try {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete.php';
        form.style.display = 'none';
        
        // ID del documento
        const inputDocId = document.createElement('input');
        inputDocId.type = 'hidden';
        inputDocId.name = 'document_id';
        inputDocId.value = documentId.toString();
        form.appendChild(inputDocId);
        
        // Path de retorno
        if (currentPath) {
            const inputReturnPath = document.createElement('input');
            inputReturnPath.type = 'hidden';
            inputReturnPath.name = 'return_path';
            inputReturnPath.value = currentPath;
            form.appendChild(inputReturnPath);
        }
        
        document.body.appendChild(form);
        
        // Mostrar notificación
        if (typeof showNotification === 'function') {
            showNotification('🗑️ Eliminando documento...', 'warning', 3000);
        }
        
        console.log('📤 Enviando formulario de eliminación');
        form.submit();
        
    } catch (error) {
        console.error('❌ Error al crear formulario:', error);
        alert('Error al eliminar documento: ' + error.message);
    }
}

// Función para obtener path actual
function getCurrentPath() {
    // Método 1: Desde URL
    const urlParams = new URLSearchParams(window.location.search);
    const pathFromUrl = urlParams.get('path');
    if (pathFromUrl) return pathFromUrl;
    
    // Método 2: Desde variable global
    if (typeof currentPath !== 'undefined' && currentPath) return currentPath;
    
    // Método 3: Desde breadcrumbs
    const breadcrumbs = document.querySelectorAll('.breadcrumb-item');
    if (breadcrumbs.length > 0) {
        const lastBreadcrumb = breadcrumbs[breadcrumbs.length - 1];
        return lastBreadcrumb.dataset.breadcrumbPath || '';
    }
    
    return '';
}

// Función de mover documento corregida
function cutDocumentFixed(docId, docName) {
    console.log('✂️ Función de cortar documento corregida');
    
    if (typeof canEdit !== 'undefined' && !canEdit && currentUserRole !== 'admin') {
        console.log('🚫 Sin permisos para mover');
        alert('No tienes permisos para mover documentos');
        return;
    }
    
    // Llamar función original si existe
    if (typeof cutDocument === 'function') {
        cutDocument(docId, docName);
    } else {
        console.log('⚠️ Función cutDocument original no encontrada');
    }
}

// Sobrescribir funciones problemáticas
function overrideProblematicFunctions() {
    // Sobrescribir deleteDocument
    const originalDelete = window.deleteDocument;
    window.deleteDocument = function(documentId, documentName) {
        console.log('🔄 Usando función de eliminación corregida');
        deleteDocumentFixed(documentId, documentName);
    };
    
    // Sobrescribir cutDocument si existe
    if (typeof window.cutDocument === 'function') {
        const originalCut = window.cutDocument;
        window.cutDocument = function(docId, docName) {
            console.log('🔄 Usando función de cortar corregida');
            cutDocumentFixed(docId, docName);
        };
    }
    
    console.log('✅ Funciones problemáticas sobrescritas');
}

// Ejecutar correcciones
checkAndFixPermissions();
overrideProblematicFunctions();

// Verificar que las funciones funcionan
console.log('🧪 Probando funciones:');
console.log('- deleteDocument type:', typeof window.deleteDocument);
console.log('- cutDocument type:', typeof window.cutDocument);

console.log('✅ Corrección de permisos de usuario aplicada');