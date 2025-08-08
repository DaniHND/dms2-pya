// assets/js/inbox-preview-fix.js
// Corrección para la vista previa de documentos en inbox

console.log('🔧 Cargando corrección para vista previa de documentos');

// Función mejorada para mostrar vista previa
async function showPreviewModal(documentId, forceNewWindow = false) {
    console.log('👁️ Mostrando vista previa para documento:', documentId);
    
    // Si se fuerza nueva ventana o es móvil, abrir en nueva pestaña
    if (forceNewWindow || window.innerWidth < 768) {
        window.open(`view.php?id=${documentId}`, '_blank');
        return;
    }
    
    const modal = document.getElementById('previewModal');
    const title = document.getElementById('previewTitle');
    const content = document.getElementById('previewContent');
    
    if (!modal || !title || !content) {
        console.warn('⚠️ Modal de vista previa no encontrado, abriendo en nueva ventana');
        window.open(`view.php?id=${documentId}`, '_blank');
        return;
    }

    try {
        // Mostrar loading
        title.innerHTML = '<i data-feather="loader" class="spinning"></i> Cargando vista previa...';
        content.innerHTML = `
            <div class="preview-loading">
                <div class="loading-spinner"></div>
                <p>Cargando documento...</p>
            </div>
        `;
        
        // Mostrar modal
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Reemplazar iconos
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // Solicitar datos del documento
        const response = await fetch(`view.php?id=${documentId}&preview=1`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Error al cargar el documento');
        }

        const doc = data.document;
        const permissions = data.permissions;

        // Actualizar título
        title.innerHTML = `
            <span>Vista Previa - ${doc.name}</span>
            <div class="preview-actions">
                ${permissions.can_download ? 
                    `<button class="btn-icon" onclick="downloadDocument(${doc.id})" title="Descargar">
                        <i data-feather="download"></i>
                    </button>` : ''
                }
                <button class="btn-icon" onclick="openInNewWindow(${doc.id})" title="Abrir en nueva ventana">
                    <i data-feather="external-link"></i>
                </button>
                <button class="btn-icon" onclick="closePreviewModal()" title="Cerrar">
                    <i data-feather="x"></i>
                </button>
            </div>
        `;

        // Generar contenido según tipo de archivo
        let previewContent = '';
        
        if (!permissions.can_view) {
            previewContent = `
                <div class="preview-error">
                    <i data-feather="lock" style="width: 64px; height: 64px;"></i>
                    <h3>Sin permisos</h3>
                    <p>No tienes permisos para visualizar este documento.</p>
                </div>
            `;
        } else if (doc.mime_type.startsWith('image/')) {
            previewContent = `
                <div class="preview-image-container">
                    <img src="${doc.file_path}" 
                         alt="${doc.name}" 
                         class="preview-image"
                         onload="this.style.opacity=1"
                         onerror="showPreviewError(this, 'Error al cargar la imagen')">
                </div>
            `;
        } else if (doc.mime_type === 'application/pdf') {
            previewContent = `
                <div class="preview-pdf-container">
                    <iframe src="${doc.file_path}" 
                            class="preview-pdf"
                            title="${doc.name}"
                            onload="this.style.opacity=1"
                            onerror="showPreviewError(this, 'Error al cargar el PDF')">
                    </iframe>
                </div>
            `;
        } else if (doc.mime_type.startsWith('video/')) {
            previewContent = `
                <div class="preview-video-container">
                    <video controls class="preview-video">
                        <source src="${doc.file_path}" type="${doc.mime_type}">
                        Tu navegador no soporta la reproducción de video.
                    </video>
                </div>
            `;
        } else {
            previewContent = `
                <div class="preview-unsupported">
                    <i data-feather="file" style="width: 64px; height: 64px;"></i>
                    <h3>Vista previa no disponible</h3>
                    <p>Este tipo de archivo no puede ser visualizado en el navegador.</p>
                    <div class="file-info">
                        <p><strong>Nombre:</strong> ${doc.original_name || doc.name}</p>
                        <p><strong>Tamaño:</strong> ${formatBytes(doc.file_size)}</p>
                        <p><strong>Tipo:</strong> ${doc.mime_type}</p>
                    </div>
                    <button class="btn btn-primary" onclick="openInNewWindow(${doc.id})">
                        <i data-feather="external-link"></i>
                        Abrir en nueva ventana
                    </button>
                </div>
            `;
        }

        // Agregar información del documento
        previewContent += `
            <div class="preview-metadata">
                <div class="metadata-grid">
                    <div class="metadata-item">
                        <strong>Empresa:</strong> ${doc.company_name || 'N/A'}
                    </div>
                    <div class="metadata-item">
                        <strong>Departamento:</strong> ${doc.department_name || 'N/A'}
                    </div>
                    <div class="metadata-item">
                        <strong>Tipo:</strong> ${doc.document_type || 'General'}
                    </div>
                    <div class="metadata-item">
                        <strong>Subido por:</strong> ${doc.uploaded_by}
                    </div>
                    <div class="metadata-item">
                        <strong>Fecha:</strong> ${new Date(doc.upload_date).toLocaleDateString('es-ES')}
                    </div>
                    <div class="metadata-item">
                        <strong>Tamaño:</strong> ${formatBytes(doc.file_size)}
                    </div>
                </div>
            </div>
        `;

        content.innerHTML = previewContent;

        // Reemplazar iconos nuevamente
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        console.log('✅ Vista previa cargada exitosamente');

    } catch (error) {
        console.error('❌ Error al cargar vista previa:', error);
        
        title.innerHTML = `
            <span>Error al cargar vista previa</span>
            <button class="btn-icon" onclick="closePreviewModal()" title="Cerrar">
                <i data-feather="x"></i>
            </button>
        `;
        
        content.innerHTML = `
            <div class="preview-error">
                <i data-feather="alert-circle" style="width: 64px; height: 64px; color: #ef4444;"></i>
                <h3>Error al cargar documento</h3>
                <p>${error.message}</p>
                <button class="btn btn-primary" onclick="openInNewWindow(${documentId})">
                    <i data-feather="external-link"></i>
                    Abrir en nueva ventana
                </button>
            </div>
        `;
        
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }
}

// Función para cerrar el modal de vista previa
function closePreviewModal() {
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Función para abrir en nueva ventana
function openInNewWindow(documentId) {
    window.open(`view.php?id=${documentId}`, '_blank');
}

// Función para mostrar error en preview
function showPreviewError(element, message) {
    element.style.display = 'none';
    const errorDiv = document.createElement('div');
    errorDiv.className = 'preview-error';
    errorDiv.innerHTML = `
        <i data-feather="alert-circle" style="width: 48px; height: 48px; color: #ef4444;"></i>
        <p>${message}</p>
    `;
    element.parentNode.appendChild(errorDiv);
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

// Función para formatear bytes
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Función mejorada para ver documento (reemplaza la existente)
function viewDocument(documentId) {
    showPreviewModal(documentId, false);
}

// Función para descargar documento desde la vista previa
async function downloadDocument(documentId) {
    try {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'download.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'document_id';
        input.value = documentId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
        
        showNotification('📥 Descarga iniciada', 'success');
    } catch (error) {
        console.error('Error al descargar:', error);
        showNotification('❌ Error al descargar documento', 'error');
    }
}

// Eventos del teclado para el modal
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('previewModal');
    if (modal && modal.style.display === 'flex') {
        if (e.key === 'Escape') {
            closePreviewModal();
        }
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            // TODO: Implementar navegación entre documentos
        }
    }
});

// Eventos de clic en el modal
document.addEventListener('click', function(e) {
    const modal = document.getElementById('previewModal');
    if (e.target === modal) {
        closePreviewModal();
    }
});

// CSS adicional para la vista previa
const previewStyles = `
<style>
.preview-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.spinning {
    animation: spin 1s linear infinite;
}

.preview-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.btn-icon {
    background: none;
    border: none;
    padding: 0.5rem;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    color: var(--text-muted);
}

.btn-icon:hover {
    background: var(--bg-light);
    color: var(--text-dark);
}

.preview-image-container,
.preview-pdf-container,
.preview-video-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem;
    min-height: 400px;
}

.preview-image {
    max-width: 100%;
    max-height: 70vh;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    opacity: 0;
    transition: opacity 0.3s;
}

.preview-pdf {
    width: 100%;
    height: 70vh;
    border: none;
    border-radius: 8px;
    opacity: 0;
    transition: opacity 0.3s;
}

.preview-video {
    max-width: 100%;
    max-height: 70vh;
    border-radius: 8px;
}

.preview-error,
.preview-unsupported {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.preview-error i,
.preview-unsupported i {
    margin-bottom: 1rem;
    color: var(--text-muted);
}

.preview-error h3,
.preview-unsupported h3 {
    margin: 1rem 0;
    color: var(--text-dark);
}

.file-info {
    background: var(--bg-light);
    border-radius: 8px;
    padding: 1.5rem;
    margin: 1.5rem 0;
    text-align: left;
}

.file-info p {
    margin: 0.5rem 0;
}

.preview-metadata {
    background: var(--bg-light);
    border-top: 1px solid var(--border-light);
    padding: 1.5rem;
}

.metadata-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.metadata-item {
    font-size: 0.875rem;
    color: var(--text-muted);
}

.metadata-item strong {
    color: var(--text-dark);
    display: block;
    margin-bottom: 0.25rem;
}

#previewModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

#previewModal.active {
    opacity: 1;
}

#previewModal .modal-content {
    background: white;
    border-radius: 12px;
    max-width: 90vw;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

#previewModal .modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-light);
}

#previewModal .modal-body {
    flex: 1;
    overflow-y: auto;
    background: white;
}

@media (max-width: 768px) {
    #previewModal .modal-content {
        max-width: 95vw;
        max-height: 95vh;
    }
    
    .metadata-grid {
        grid-template-columns: 1fr;
    }
    
    .preview-actions {
        gap: 0.25rem;
    }
    
    .btn-icon {
        padding: 0.375rem;
    }
}
</style>
`;

// Inyectar estilos si no existen
if (!document.getElementById('preview-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'preview-styles';
    styleElement.innerHTML = previewStyles;
    document.head.appendChild(styleElement);
}

// Función para inicializar la corrección
function initializePreviewFix() {
    console.log('🔧 Inicializando corrección de vista previa');
    
    // Verificar que existe el modal de vista previa
    let modal = document.getElementById('previewModal');
    if (!modal) {
        // Crear modal si no existe
        modal = document.createElement('div');
        modal.id = 'previewModal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="previewTitle">Vista Previa</h3>
                </div>
                <div class="modal-body">
                    <div id="previewContent"></div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Interceptar clics en elementos de documentos
    document.addEventListener('click', function(e) {
        // Buscar si el clic fue en un elemento de documento
        const documentElement = e.target.closest('[data-document-id]');
        if (documentElement) {
            const action = e.target.closest('[data-action]');
            if (action && action.dataset.action === 'view') {
                e.preventDefault();
                e.stopPropagation();
                const documentId = documentElement.dataset.documentId;
                showPreviewModal(documentId);
                return;
            }
        }
        
        // También interceptar clics en botones de vista
        const viewButton = e.target.closest('.view-document, .document-view, [onclick*="viewDocument"]');
        if (viewButton) {
            e.preventDefault();
            e.stopPropagation();
            
            // Extraer ID del documento de varios posibles atributos
            let documentId = viewButton.dataset.documentId || 
                           viewButton.dataset.id ||
                           extractDocumentIdFromOnclick(viewButton.getAttribute('onclick'));
            
            if (documentId) {
                showPreviewModal(documentId);
            }
        }
    });
    
    console.log('✅ Corrección de vista previa inicializada');
}

// Función auxiliar para extraer ID del onclick
function extractDocumentIdFromOnclick(onclickStr) {
    if (!onclickStr) return null;
    const match = onclickStr.match(/viewDocument\((\d+)\)/);
    return match ? match[1] : null;
}

// Función para mostrar notificaciones
function showNotification(message, type = 'info', duration = 5000) {
    // Buscar sistema de notificaciones existente
    if (typeof mostrarNotificacion === 'function') {
        mostrarNotificacion(message, type, duration);
        return;
    }
    
    // Crear notificación simple si no existe sistema
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        z-index: 10001;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateX(100%);
        transition: transform 0.3s;
    `;
    
    // Colores según tipo
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    notification.style.background = colors[type] || colors.info;
    
    document.body.appendChild(notification);
    
    // Mostrar
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Ocultar
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePreviewFix);
} else {
    initializePreviewFix();
}

console.log('🚀 Corrección de vista previa de documentos cargada');