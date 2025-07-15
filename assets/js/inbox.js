// assets/js/inbox.js
// JavaScript completo para la Bandeja de Entrada - DMS2

console.log('🚀 INBOX DMS2 - Cargando módulo JavaScript');

// Variables globales
let currentView = 'grid';

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('📱 DOM cargado - Inicializando Inbox');
    
    // Inicializar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
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
    
    console.log('✅ Inbox inicializado correctamente');
});

// Configurar eventos principales
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
    
    // Verificar permisos globales
    if (typeof canDownload !== 'undefined' && !canDownload) {
        showNotification('No tienes permisos para descargar', 'error');
        return;
    }

    // Crear formulario para descarga
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
    
    // Limpiar formulario después de un tiempo
    setTimeout(() => {
        if (document.body.contains(form)) {
            document.body.removeChild(form);
        }
    }, 2000);
}

// FUNCIÓN ELIMINAR CORREGIDA - Sin conflicto de nombres
function deleteDocument(documentId) {
    console.log('🗑️ INICIO - Eliminar documento ID:', documentId);
    console.log('🗑️ Tipo de documentId:', typeof documentId);
    console.log('🗑️ documentsData disponible:', typeof documentsData !== 'undefined');
    
    // CORREGIDO: Usar 'docData' en lugar de 'document' para evitar conflicto
    let docData = null;
    if (typeof documentsData !== 'undefined') {
        docData = documentsData.find(doc => doc.id == documentId);
        console.log('🗑️ Documento encontrado:', docData ? docData.name : 'NO ENCONTRADO');
    } else {
        console.log('🗑️ WARNING: documentsData no está definido');
    }
    
    if (!docData) {
        console.log('🗑️ ERROR: Documento no encontrado');
        showNotification('Documento no encontrado', 'error');
        return;
    }
    
    // Verificar permisos de eliminación
    console.log('🗑️ Verificando permisos');
    console.log('🗑️ currentUserRole:', typeof currentUserRole !== 'undefined' ? currentUserRole : 'NO DEFINIDO');
    console.log('🗑️ currentUserId:', typeof currentUserId !== 'undefined' ? currentUserId : 'NO DEFINIDO');
    console.log('🗑️ docData.user_id:', docData.user_id);
    
    const canDelete = (typeof currentUserRole !== 'undefined' && currentUserRole === 'admin') || 
                     (typeof currentUserId !== 'undefined' && docData.user_id == currentUserId);
    
    console.log('🗑️ Puede eliminar:', canDelete);
    
    if (!canDelete) {
        console.log('🗑️ ERROR: Sin permisos para eliminar');
        showNotification('No tienes permisos para eliminar este documento', 'error');
        return;
    }
    
    // Confirmación detallada
    console.log('🗑️ Mostrando confirmación');
    const confirmMessage = `¿Eliminar documento?

⚠️ Esta acción no se puede deshacer.`;
    
    const firstConfirm = confirm(confirmMessage);
    console.log('🗑️ Primera confirmación:', firstConfirm);
    
    if (!firstConfirm) {
        console.log('🗑️ Usuario canceló en primera confirmación');
        return;
    }
    
    // Confirmación final
    const finalConfirm = confirm('¿Está completamente seguro? Esta es la última oportunidad para cancelar.');
    console.log('🗑️ Confirmación final:', finalConfirm);
    
    if (!finalConfirm) {
        console.log('🗑️ Usuario canceló en confirmación final');
        return;
    }
    
    console.log('🗑️ EJECUTANDO ELIMINACIÓN - Documento:', docData.name);

    // Mostrar indicador visual
    showNotification('Eliminando documento...', 'warning');

    // CORREGIDO: Ahora document se refiere al DOM correctamente
    console.log('🗑️ Creando formulario de eliminación');
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'delete.php';
    form.style.display = 'none';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'document_id';
    input.value = documentId;
    
    console.log('🗑️ Formulario creado con document_id:', documentId);
    
    form.appendChild(input);
    document.body.appendChild(form);
    
    // Verificar que el formulario se creó correctamente
    console.log('🗑️ Formulario en DOM:', document.body.contains(form));
    console.log('🗑️ Acción del formulario:', form.action);
    console.log('🗑️ Método del formulario:', form.method);
    console.log('🗑️ Valor del input:', input.value);
    
    // Enviar formulario
    try {
        console.log('🗑️ Enviando formulario...');
        form.submit();
        console.log('🗑️ Formulario enviado exitosamente');
    } catch (error) {
        console.error('🗑️ ERROR al enviar formulario:', error);
        showNotification('Error al enviar formulario', 'error');
    }
    
    // Limpiar formulario después de un tiempo
    setTimeout(() => {
        if (document.body.contains(form)) {
            document.body.removeChild(form);
            console.log('🗑️ Formulario limpiado del DOM');
        }
    }, 5000);
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

// Función para cambiar vista
function changeView(view) {
    console.log('🔄 Cambiando vista a:', view);
    
    currentView = view;
    
    // Actualizar botones
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.view === view) {
            btn.classList.add('active');
        }
    });

    // Actualizar clases del contenedor
    const documentsGrid = document.getElementById('documentsGrid');
    if (documentsGrid) {
        documentsGrid.className = view === 'grid' ? 'documents-grid' : 'documents-list';
    }
}

// Función para actualizar la hora
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

// Función para mostrar notificaciones
function showNotification(message, type = 'info', duration = 4000) {
    console.log(`📢 ${type.toUpperCase()}: ${message}`);
    
    // Remover notificaciones existentes
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(notif => notif.remove());
    
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification-toast ${type}`;
    
    const colors = {
        'info': '#3b82f6',
        'success': '#10b981',
        'warning': '#f59e0b',
        'error': '#ef4444'
    };
    
    const iconMap = {
        'info': 'info',
        'success': 'check-circle',
        'warning': 'alert-triangle',
        'error': 'alert-circle'
    };
    
    const color = colors[type] || colors.info;
    const icon = iconMap[type] || 'info';
    
    notification.innerHTML = `
        <i data-feather="${icon}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">
            <i data-feather="x"></i>
        </button>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${color};
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
    
    // Reemplazar iconos de Feather
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

// Función para alternar sidebar en móvil
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

// Función para limpiar filtros
function clearAllFilters() {
    console.log('🗑️ Limpiando todos los filtros');
    window.location.href = window.location.pathname;
}

// Función para ordenar documentos
function sortDocuments() {
    const sortBy = document.getElementById('sortBy').value;
    console.log('📊 Ordenando documentos por:', sortBy);
    
    const url = new URL(window.location);
    url.searchParams.set('sort', sortBy);
    window.location.href = url.toString();
}

// Función para manejar mensajes de URL
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
                message = '❌ Error: ' + error;
        }
        
        showNotification(message, 'error', 6000);
        cleanURL();
    }
}

// Función para limpiar la URL
function cleanURL() {
    const newUrl = window.location.pathname + 
        (window.location.search
            .replace(/[?&](success|error|name)=[^&]*/g, '')
            .replace(/^&/, '?')
            .replace(/^\?$/, ''));
    window.history.replaceState({}, document.title, newUrl);
}

// Función para configurar responsive
function setupResponsive() {
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            if (overlay) {
                overlay.classList.remove('active');
            }
            document.body.style.overflow = '';
        }
    });
}

// Función para registrar actividad
function logActivity(action, documentId) {
    if (typeof fetch !== 'undefined') {
        fetch('log_activity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                document_id: documentId
            })
        }).catch(error => {
            console.log('Log de actividad falló (no crítico):', error);
        });
    }
}

// Funciones placeholder para compatibilidad
function showComingSoon(feature) {
    showNotification(`${feature} - Próximamente`, 'info');
}

function showNotifications() {
    showNotification('Sistema de notificaciones próximamente', 'info');
}

function showUserMenu() {
    showNotification('Menú de usuario próximamente', 'info');
}

// Función para búsqueda rápida (futura implementación)
function quickSearch(query) {
    if (!query || query.length < 2) {
        return [];
    }
    
    console.log('🔍 Búsqueda rápida:', query);
    
    // Simular búsqueda en los datos actuales
    if (typeof documentsData !== 'undefined') {
        return documentsData.filter(doc => 
            doc.name.toLowerCase().includes(query.toLowerCase()) ||
            (doc.description && doc.description.toLowerCase().includes(query.toLowerCase())) ||
            (doc.document_type && doc.document_type.toLowerCase().includes(query.toLowerCase()))
        );
    }
    
    return [];
}

// Función para exportar datos (futura implementación)
function exportDocuments(format = 'csv') {
    console.log('📤 Exportando documentos en formato:', format);
    
    if (typeof documentsData === 'undefined' || documentsData.length === 0) {
        showNotification('No hay documentos para exportar', 'warning');
        return;
    }
    
    showNotification('Función de exportación próximamente', 'info');
}

// Función para seleccionar múltiples documentos (futura implementación)
function toggleDocumentSelection(documentId) {
    console.log('☑️ Seleccionar documento:', documentId);
    showNotification('Selección múltiple próximamente', 'info');
}

// Función para operaciones por lotes (futura implementación)
function bulkOperation(operation) {
    console.log('📋 Operación por lotes:', operation);
    showNotification('Operaciones por lotes próximamente', 'info');
}

// Event listeners adicionales para mejoras futuras
document.addEventListener('keydown', function(e) {
    // Atajos de teclado
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'f':
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                }
                break;
            case 'n':
                e.preventDefault();
                window.location.href = 'upload.php';
                break;
        }
    }
    
    // Tecla Escape para cerrar modales o limpiar búsqueda
    if (e.key === 'Escape') {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && searchInput.value) {
            searchInput.value = '';
            searchInput.form.submit();
        }
    }
});

// Función para manejar errores de JavaScript
window.addEventListener('error', function(e) {
    console.error('❌ Error de JavaScript en inbox:', e.error);
    
    // Mostrar notificación de error solo en desarrollo
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        showNotification('Error de JavaScript - Ver consola', 'error');
    }
});

// Función para detectar si el usuario está online/offline
window.addEventListener('online', function() {
    showNotification('✅ Conexión restaurada', 'success');
});

window.addEventListener('offline', function() {
    showNotification('⚠️ Sin conexión a internet', 'warning');
});

// Función para detectar visibilidad de la página
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        console.log('📱 Página oculta');
    } else {
        console.log('📱 Página visible');
        // Actualizar datos si es necesario
        updateTime();
    }
});

// Función para manejar el beforeunload
window.addEventListener('beforeunload', function(e) {
    // Limpiar recursos si es necesario
    console.log('👋 Saliendo del inbox');
});

console.log('✅ Módulo JavaScript del inbox cargado completamente');