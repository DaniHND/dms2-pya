// assets/js/inbox.js
// JavaScript para la Bandeja de Entrada - DMS2 (Versión Simple y Funcional)

// Variables globales
let currentView = 'grid';

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    feather.replace();
    updateTime();
    setInterval(updateTime, 1000);
    
    // Manejar mensajes de URL
    handleURLMessages();
    
    // Configurar responsive
    setupResponsive();
    
    console.log('✅ Inbox simple inicializado correctamente');
});

// Función para cambiar vista
function changeView(view) {
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

    console.log('Vista cambiada a:', view);
}

// Función para ordenar documentos
function sortDocuments() {
    const sortBy = document.getElementById('sortBy').value;
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('sort', sortBy);
    window.location.search = urlParams.toString();
}

// Función para ver documento
function viewDocument(documentId) {
    console.log('Viendo documento ID:', documentId);
    
    // Verificar que existe el documento
    if (typeof documentsData !== 'undefined') {
        const document = documentsData.find(doc => doc.id == documentId);
        if (!document) {
            showNotification('Documento no encontrado', 'error');
            return;
        }
        console.log('Abriendo documento:', document.name);
    }
    
    // Abrir en nueva ventana/pestaña
    window.open('view.php?id=' + documentId, '_blank');
    
    // Registrar actividad
    logActivity('view', documentId);
}

// Función para descargar documento
function downloadDocument(documentId) {
    console.log('Descargando documento ID:', documentId);
    
    // Verificar permisos
    if (typeof canDownload !== 'undefined' && !canDownload) {
        showNotification('No tienes permisos para descargar documentos', 'error');
        return;
    }

    // Mostrar indicador en el botón
    const downloadBtn = event.target.closest('.action-btn');
    const originalContent = downloadBtn ? downloadBtn.innerHTML : null;
    
    if (downloadBtn) {
        downloadBtn.innerHTML = '<i data-feather="loader"></i>';
        downloadBtn.disabled = true;
        feather.replace();
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
    
    // Limpiar y restaurar botón
    setTimeout(() => {
        if (document.body.contains(form)) {
            document.body.removeChild(form);
        }
        if (downloadBtn && originalContent) {
            downloadBtn.innerHTML = originalContent;
            downloadBtn.disabled = false;
            feather.replace();
        }
    }, 2000);

    // Registrar actividad
    logActivity('download', documentId);
}

// Función para eliminar documento
function deleteDocument(documentId) {
    console.log('Intentando eliminar documento ID:', documentId);
    
    // Buscar el documento en los datos
    let document = null;
    if (typeof documentsData !== 'undefined') {
        document = documentsData.find(doc => doc.id == documentId);
    }
    
    if (!document) {
        showNotification('Documento no encontrado', 'error');
        return;
    }
    
    // Verificar permisos de eliminación
    const canDelete = (typeof currentUserRole !== 'undefined' && currentUserRole === 'admin') || 
                     (typeof currentUserId !== 'undefined' && document.user_id == currentUserId);
    
    if (!canDelete) {
        showNotification('No tienes permisos para eliminar este documento', 'error');
        return;
    }
    
    // Mostrar confirmación con detalles del documento
    const confirmMessage = `¿Está seguro de que desea eliminar el documento?

📄 Nombre: ${document.name}
🏢 Empresa: ${document.company_name || 'Sin empresa'}
📁 Departamento: ${document.department_name || 'Sin departamento'}
📏 Tamaño: ${formatBytes(document.file_size)}

⚠️ ADVERTENCIA: Esta acción no se puede deshacer.`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // Confirmación adicional para mayor seguridad
    const finalConfirm = confirm('¿Realmente desea proceder con la eliminación?\n\nEsta es su última oportunidad para cancelar.');
    if (!finalConfirm) {
        return;
    }
    
    console.log('Eliminando documento:', document.name);

    // Mostrar indicador de eliminación en el botón
    const deleteBtn = event.target.closest('.action-btn');
    const originalContent = deleteBtn ? deleteBtn.innerHTML : null;
    
    if (deleteBtn) {
        deleteBtn.innerHTML = '<i data-feather="loader"></i>';
        deleteBtn.disabled = true;
        deleteBtn.style.background = '#fca5a5';
        feather.replace();
    }

    // Crear formulario para eliminación
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
    
    // Enviar formulario
    form.submit();
    
    showNotification('Eliminando documento...', 'warning');
    
    // Registrar actividad
    logActivity('delete', documentId);
    
    // Limpiar formulario
    setTimeout(() => {
        if (document.body.contains(form)) {
            document.body.removeChild(form);
        }
    }, 1000);
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

// Función para mostrar notificaciones
function showNotification(message, type = 'info', duration = 4000) {
    // Remover notificaciones existentes
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(notif => notif.remove());
    
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification-toast ${type}`;
    
    const colors = {
        'info': '#8B4513',
        'success': '#059669',
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
        <i data-feather="${icon}" style="width: 16px; height: 16px; color: ${color}; flex-shrink: 0;"></i>
        <span style="flex: 1;">${message}</span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: #64748b;">
            <i data-feather="x" style="width: 14px; height: 14px;"></i>
        </button>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        color: #1e293b;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-left: 4px solid ${color};
        z-index: 9999;
        font-size: 14px;
        font-weight: 500;
        max-width: 350px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    
    document.body.appendChild(notification);
    feather.replace();
    
    // Animar entrada
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Auto-remover
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// Función para limpiar filtros
function clearAllFilters() {
    window.location.href = window.location.pathname;
}

// Función para alternar sidebar en móvil
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
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

// Función para manejar mensajes de URL
function handleURLMessages() {
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const error = urlParams.get('error');
    
    if (success === 'document_deleted') {
        const documentName = urlParams.get('name') || 'el documento';
        showNotification(`✅ ${documentName} ha sido eliminado exitosamente`, 'success', 5000);
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
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
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