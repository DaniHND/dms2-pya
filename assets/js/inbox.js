// assets/js/inbox.js - CÓDIGO COMPLETO CON ORDENACIÓN
// JavaScript completo para la Bandeja de Entrada - DMS2

console.log('🚀 INBOX DMS2 - Cargando módulo JavaScript COMPLETO');

// Variables globales
let currentView = 'grid';
let documentsData = [];
let currentUserId = null;
let currentUserRole = null;
let canDownload = true;

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('📱 DOM cargado - Inicializando Inbox COMPLETO');
    
    // Obtener datos del usuario desde PHP
    try {
        if (typeof phpUserData !== 'undefined') {
            currentUserId = phpUserData.id;
            currentUserRole = phpUserData.role;
            canDownload = phpUserData.canDownload || true;
            console.log('👤 Usuario cargado:', currentUserId, currentUserRole);
        }
        
        if (typeof phpDocumentsData !== 'undefined') {
            documentsData = phpDocumentsData;
            console.log('📄 Documentos cargados:', documentsData.length);
        }
    } catch (e) {
        console.warn('⚠️ No se pudieron cargar datos PHP:', e);
    }
    
    // Inicializar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
        console.log('✅ Iconos Feather inicializados');
    }
    
    // Inicializar reloj
    updateTime();
    setInterval(updateTime, 1000);
    
    // Configurar eventos
    setupEventListeners();
    
    // Manejar mensajes de URL
    handleURLMessages();
    
    // Configurar responsive
    setupResponsive();
    
    // Inicializar ordenación
    initializeSorting();
    
    console.log('✅ Inbox COMPLETO inicializado correctamente');
});

// ==========================================
// CONFIGURACIÓN DE EVENTOS
// ==========================================

function setupEventListeners() {
    console.log('🎯 Configurando eventos del inbox');
    
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

// ==========================================
// FUNCIONES DE DOCUMENTOS
// ==========================================

// Ver documento
function viewDocument(documentId) {
    console.log('👁️ Ver documento ID:', documentId);
    
    if (documentsData.length > 0) {
        const document = documentsData.find(doc => doc.id == documentId);
        if (!document) {
            showNotification('Documento no encontrado', 'error');
            return;
        }
        console.log('📄 Abriendo documento:', document.name);
    }
    
    window.location.href = 'view.php?id=' + documentId;
}

// Descargar documento
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

// Eliminar documento
function deleteDocument(documentId) {
    console.log('🗑️ INICIO - Eliminar documento ID:', documentId);
    
    if (!documentId) {
        console.error('🗑️ ERROR: ID de documento vacío');
        showNotification('Error: ID de documento no válido', 'error');
        return;
    }
    
    // Buscar información del documento
    let docData = null;
    if (documentsData && documentsData.length > 0) {
        docData = documentsData.find(doc => doc.id == documentId);
        console.log('🗑️ Documento encontrado:', docData ? docData.name : 'NO ENCONTRADO');
    }
    
    // Verificar permisos de eliminación
    if (docData) {
        const canDelete = (currentUserRole === 'admin') || (docData.user_id == currentUserId);
        if (!canDelete) {
            console.log('🗑️ ERROR: Sin permisos para eliminar');
            showNotification('No tienes permisos para eliminar este documento', 'error');
            return;
        }
    }
    
    // Preparar mensaje de confirmación
    let confirmMessage = '¿Eliminar documento?';
    
    if (docData) {
        confirmMessage = `¿Eliminar documento?

📄 ${docData.name}
🏢 ${docData.company_name || 'Sin empresa'}
📁 ${docData.department_name || 'Sin departamento'}
📏 ${formatBytes(docData.file_size)}

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
    
    // Crear y enviar formulario de eliminación
    try {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete.php';
        form.style.display = 'none';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'document_id';
        input.value = documentId.toString();
        
        form.appendChild(input);
        document.body.appendChild(form);
        
        console.log('🗑️ Enviando formulario de eliminación');
        showNotification('Eliminando documento...', 'warning', 3000);
        
        form.submit();
        
    } catch (error) {
        console.error('🗑️ ERROR al crear/enviar formulario:', error);
        showNotification('Error al eliminar documento', 'error');
    }
}

// ==========================================
// FUNCIONES DE ORDENACIÓN
// ==========================================

// Función para cambiar ordenación
function sortDocuments() {
    const sortBy = document.getElementById('sortBy').value;
    console.log('📊 Ordenando documentos por:', sortBy);
    
    const url = new URL(window.location);
    url.searchParams.set('sort', sortBy);
    
    // Mantener el orden actual, o usar 'asc' por defecto
    if (!url.searchParams.has('order')) {
        url.searchParams.set('order', 'asc');
    }
    
    showNotification(`📊 Ordenando por ${getSortName(sortBy)}...`, 'info', 2000);
    window.location.href = url.toString();
}

// Función para alternar orden ascendente/descendente
function toggleSortOrder() {
    const url = new URL(window.location);
    const currentOrder = url.searchParams.get('order') || 'asc';
    const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
    
    url.searchParams.set('order', newOrder);
    
    console.log('🔄 Cambiando orden a:', newOrder);
    showNotification(`🔄 Orden ${newOrder === 'asc' ? 'ascendente' : 'descendente'}`, 'info', 2000);
    
    window.location.href = url.toString();
}

// Función auxiliar para obtener nombre descriptivo del campo
function getSortName(sortField) {
    const sortNames = {
        'name': 'nombre',
        'date': 'fecha',
        'size': 'tamaño',
        'type': 'tipo'
    };
    return sortNames[sortField] || sortField;
}

// Inicializar select de ordenación
function initializeSorting() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || 'name';
    const currentOrder = urlParams.get('order') || 'asc';
    
    const sortSelect = document.getElementById('sortBy');
    if (sortSelect) {
        sortSelect.value = currentSort;
        console.log('🔧 Select inicializado con:', currentSort);
    }
    
    // Actualizar icono de orden
    const orderIcon = document.querySelector('.sort-controls i[data-feather]');
    if (orderIcon) {
        orderIcon.setAttribute('data-feather', currentOrder === 'asc' ? 'arrow-up' : 'arrow-down');
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }
}

// ==========================================
// FUNCIONES DE VISTA
// ==========================================

// Cambiar vista entre grid y lista
function changeView(viewType) {
    console.log('👁️ Cambiando vista a:', viewType);
    
    const gridView = document.querySelector('.documents-grid');
    const listView = document.querySelector('.documents-list');
    const viewButtons = document.querySelectorAll('.view-btn');
    
    // Actualizar botones activos
    viewButtons.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === viewType);
    });
    
    // Mostrar/ocultar vistas
    if (gridView && listView) {
        if (viewType === 'grid') {
            gridView.style.display = 'grid';
            listView.style.display = 'none';
        } else {
            gridView.style.display = 'none';
            listView.style.display = 'flex';
        }
    }
    
    // Guardar preferencia
    try {
        localStorage.setItem('inbox_view_preference', viewType);
    } catch (e) {
        console.log('ℹ️ No se pudo guardar preferencia de vista');
    }
    
    showNotification(`👁️ Vista ${viewType === 'grid' ? 'cuadros' : 'lista'} activada`, 'info', 1500);
}

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

// Formatear bytes
function formatBytes(bytes, decimals = 2) {
    if (!bytes || bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Actualizar reloj
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

// Mostrar notificaciones
function showNotification(message, type = 'info', duration = 4000) {
    console.log(`📢 Notificación [${type}]: ${message}`);
    
    const notification = document.createElement('div');
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 8px;">
            <i data-feather="${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${getNotificationColor(type)};
        color: white;
        padding: 12px 16px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        font-size: 14px;
        font-weight: 500;
        max-width: 400px;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Animar entrada
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Auto-remover
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// Funciones auxiliares para notificaciones
function getNotificationIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'alert-circle',
        'warning': 'alert-triangle',
        'info': 'info'
    };
    return icons[type] || 'info';
}

function getNotificationColor(type) {
    const colors = {
        'success': '#10b981',
        'error': '#ef4444',
        'warning': '#f59e0b',
        'info': '#3b82f6'
    };
    return colors[type] || '#3b82f6';
}

// Alternar sidebar en móvil
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('active');
        if (overlay) {
            overlay.classList.toggle('active');
        }
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }
}

// Configurar responsive
function setupResponsive() {
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar) sidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
    
    // Cargar preferencia de vista
    try {
        const savedView = localStorage.getItem('inbox_view_preference');
        if (savedView && ['grid', 'list'].includes(savedView)) {
            changeView(savedView);
        }
    } catch (e) {
        console.log('ℹ️ No se pudo cargar preferencia de vista');
    }
}

// Limpiar filtros
function clearAllFilters() {
    console.log('🗑️ Limpiando todos los filtros');
    window.location.href = window.location.pathname;
}

// Manejar mensajes de URL
function handleURLMessages() {
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const error = urlParams.get('error');
    
    if (success === 'document_deleted') {
        const documentName = urlParams.get('name') || 'el documento';
        showNotification(`✅ ${documentName} eliminado exitosamente`, 'success', 5000);
        cleanURL();
    }
    
    if (error) {
        let message = '';
        switch(error) {
            case 'delete_failed':
                message = '❌ Error al eliminar el documento. Inténtelo nuevamente.';
                break;
            case 'document_not_found':
                message = '❌ Documento no encontrado o sin permisos.';
                break;
            case 'invalid_request':
                message = '❌ Solicitud inválida.';
                break;
            case 'download_disabled':
                message = '❌ No tienes permisos para descargar documentos.';
                break;
            case 'file_not_found':
                message = '❌ Archivo no encontrado en el servidor.';
                break;
            default:
                message = '❌ Error desconocido: ' + error;
        }
        showNotification(message, 'error', 6000);
        cleanURL();
    }
}

// Limpiar URL de parámetros de mensaje
function cleanURL() {
    const url = new URL(window.location);
    url.searchParams.delete('success');
    url.searchParams.delete('error');
    url.searchParams.delete('name');
    
    window.history.replaceState({}, document.title, url.pathname + url.search);
}

// Función para debugging
function debugInbox() {
    console.log('🔍 DEBUG INBOX:');
    console.log('- currentUserId:', currentUserId);
    console.log('- currentUserRole:', currentUserRole);
    console.log('- canDownload:', canDownload);
    console.log('- documentsData.length:', documentsData ? documentsData.length : 'undefined');
    console.log('- feather available:', typeof feather !== 'undefined');
    console.log('- Current sort:', new URLSearchParams(window.location.search).get('sort') || 'name');
    console.log('- Current order:', new URLSearchParams(window.location.search).get('order') || 'asc');
}