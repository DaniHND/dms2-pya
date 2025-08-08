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

function deleteDocument(documentId, documentName) {
    if (!canDelete) {
        showNotification('❌ No tienes permisos para eliminar documentos', 'error');
        return;
    }

    if (!confirm(`¿Eliminar "${documentName}"?\n\n⚠️ Esta acción no se puede deshacer.`)) {
        return;
    }

    if (!confirm('¿Está completamente seguro? Esta es la última oportunidad.')) {
        return;
    }

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
}

// ====================================================================
// FUNCIONES DE CARPETAS
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