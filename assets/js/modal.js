// assets/js/modal.js - Sistema de modales global
// Funciones para el manejo de modales en todo el sistema

console.log('🎭 Iniciando sistema de modales...');

// ================================
// SISTEMA DE MODALES GLOBAL
// ================================

// Variable para tracking de modales activos
let activeModals = new Set();

// Función principal para mostrar modales
window.showModal = function(id, title, content) {
    console.log(`📋 Mostrando modal: ${id} - ${title}`);
    
    // Remover modal existente si existe
    const existingModal = document.getElementById(id + 'Modal');
    if (existingModal) {
        existingModal.remove();
        activeModals.delete(id);
    }
    
    // Crear nuevo modal
    const modal = document.createElement('div');
    modal.id = id + 'Modal';
    modal.className = 'modal';
    modal.setAttribute('data-modal-id', id);
    
    // Estructura del modal
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="close" onclick="closeModal('${id}')" type="button">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body">
                ${content}
            </div>
        </div>
    `;
    
    // Agregar al DOM
    document.body.appendChild(modal);
    activeModals.add(id);
    
    // Activar modal con animación
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
    
    // Eventos del modal
    setupModalEvents(modal, id);
    
    // Reemplazar iconos si feather está disponible
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Evitar scroll del body
    document.body.style.overflow = 'hidden';
    
    console.log(`✅ Modal ${id} creado y mostrado`);
};

// Función para cerrar modales
window.closeModal = function(id) {
    console.log(`❌ Cerrando modal: ${id}`);
    
    const modal = document.getElementById(id + 'Modal');
    if (!modal) {
        console.warn(`⚠️ Modal ${id} no encontrado`);
        return;
    }
    
    // Animación de cierre
    modal.classList.remove('active');
    activeModals.delete(id);
    
    // Remover del DOM después de la animación
    setTimeout(() => {
        if (modal.parentNode) {
            modal.parentNode.removeChild(modal);
        }
        
        // Restaurar scroll si no hay más modales
        if (activeModals.size === 0) {
            document.body.style.overflow = '';
        }
    }, 300);
    
    console.log(`✅ Modal ${id} cerrado`);
};

// Configurar eventos de un modal
function setupModalEvents(modal, id) {
    // Cerrar al hacer clic fuera del contenido
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(id);
        }
    });
    
    // Cerrar con tecla Escape
    const escapeHandler = function(e) {
        if (e.key === 'Escape') {
            closeModal(id);
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
    
    // Manejar botones de cerrar
    const closeButtons = modal.querySelectorAll('.close, [data-dismiss="modal"]');
    closeButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            closeModal(id);
        });
    });
}

// ================================
// SISTEMA DE LOADING GLOBAL
// ================================

let loadingElement = null;

window.showLoading = function(message = 'Cargando...') {
    console.log(`⏳ Mostrando loading: ${message}`);
    
    // Remover loading existente
    hideLoading();
    
    // Crear elemento de loading
    loadingElement = document.createElement('div');
    loadingElement.id = 'globalLoading';
    loadingElement.className = 'loading-overlay';
    
    loadingElement.innerHTML = `
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-message">${message}</div>
        </div>
    `;
    
    // Agregar estilos inline si no existen
    if (!document.getElementById('loadingStyles')) {
        const styles = document.createElement('style');
        styles.id = 'loadingStyles';
        styles.textContent = `
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                backdrop-filter: blur(2px);
            }
            
            .loading-content {
                background: white;
                padding: 32px;
                border-radius: 12px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
                max-width: 300px;
            }
            
            .loading-spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #8B4513;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 16px;
            }
            
            .loading-message {
                color: #333;
                font-weight: 600;
                font-size: 16px;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(styles);
    }
    
    document.body.appendChild(loadingElement);
    
    // Evitar scroll
    document.body.style.overflow = 'hidden';
    
    console.log('✅ Loading mostrado');
};

window.hideLoading = function() {
    console.log('✅ Ocultando loading');
    
    if (loadingElement && loadingElement.parentNode) {
        loadingElement.parentNode.removeChild(loadingElement);
        loadingElement = null;
        
        // Restaurar scroll si no hay modales activos
        if (activeModals.size === 0) {
            document.body.style.overflow = '';
        }
    }
};

// ================================
// SISTEMA DE NOTIFICACIONES GLOBAL
// ================================

let notificationContainer = null;

window.showNotification = function(message, type = 'info', duration = 5000) {
    console.log(`📢 Notificación ${type}: ${message}`);
    
    // Crear contenedor si no existe
    if (!notificationContainer) {
        notificationContainer = document.createElement('div');
        notificationContainer.id = 'notificationContainer';
        notificationContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 400px;
        `;
        document.body.appendChild(notificationContainer);
    }
    
    // Crear notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    // Estilos según el tipo
    const colors = {
        success: { bg: '#10b981', icon: 'check-circle' },
        error: { bg: '#ef4444', icon: 'alert-circle' },
        warning: { bg: '#f59e0b', icon: 'alert-triangle' },
        info: { bg: '#3b82f6', icon: 'info' }
    };
    
    const color = colors[type] || colors.info;
    
    notification.style.cssText = `
        background: ${color.bg};
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        font-weight: 600;
        font-size: 14px;
        line-height: 1.4;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInNotification 0.3s ease;
        cursor: pointer;
        transition: transform 0.2s ease;
    `;
    
    notification.innerHTML = `
        <i data-feather="${color.icon}" style="width: 20px; height: 20px; flex-shrink: 0;"></i>
        <span>${message}</span>
        <i data-feather="x" style="width: 16px; height: 16px; flex-shrink: 0; opacity: 0.7; margin-left: auto;"></i>
    `;
    
    // Agregar estilos de animación si no existen
    if (!document.getElementById('notificationStyles')) {
        const styles = document.createElement('style');
        styles.id = 'notificationStyles';
        styles.textContent = `
            @keyframes slideInNotification {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            .notification:hover {
                transform: translateX(-4px);
            }
        `;
        document.head.appendChild(styles);
    }
    
    // Evento para cerrar al hacer clic
    notification.addEventListener('click', () => {
        removeNotification(notification);
    });
    
    // Agregar al contenedor
    notificationContainer.appendChild(notification);
    
    // Reemplazar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Auto-remover después del tiempo especificado
    if (duration > 0) {
        setTimeout(() => {
            removeNotification(notification);
        }, duration);
    }
    
    console.log(`✅ Notificación ${type} creada`);
};

// Función para remover notificación
function removeNotification(notification) {
    if (notification && notification.parentNode) {
        notification.style.animation = 'slideInNotification 0.3s ease reverse';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
}

// ================================
// SISTEMA DE CONFIRMACIÓN
// ================================

window.showConfirm = function(message, title = 'Confirmar', onConfirm = null, onCancel = null) {
    console.log(`❓ Mostrando confirmación: ${title}`);
    
    const content = `
        <div class="confirm-dialog">
            <div class="confirm-message">
                <p>${message}</p>
            </div>
            <div class="confirm-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('confirm'); ${onCancel ? onCancel.toString() + '();' : ''}">
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="closeModal('confirm'); ${onConfirm ? onConfirm.toString() + '();' : ''}">
                    Confirmar
                </button>
            </div>
        </div>
    `;
    
    showModal('confirm', title, content);
};

// ================================
// INICIALIZACIÓN Y UTILIDADES
// ================================

// Función para limpiar todos los modales al cargar la página
function cleanupModals() {
    const existingModals = document.querySelectorAll('[id$="Modal"]');
    existingModals.forEach(modal => {
        if (modal.parentNode) {
            modal.parentNode.removeChild(modal);
        }
    });
    activeModals.clear();
    document.body.style.overflow = '';
}

// Función para verificar si un modal está activo
window.isModalActive = function(id) {
    return activeModals.has(id);
};

// Función para obtener lista de modales activos
window.getActiveModals = function() {
    return Array.from(activeModals);
};

// Limpiar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    console.log('🧹 Limpiando modales existentes...');
    cleanupModals();
});

// Limpiar al salir de la página
window.addEventListener('beforeunload', function() {
    cleanupModals();
});

// ================================
// FUNCIONES DE COMPATIBILIDAD
// ================================

// Función de compatibilidad para sistemas que usen nombres diferentes
window.openModal = window.showModal;
window.hideModal = window.closeModal;
window.displayModal = window.showModal;

console.log('✅ Sistema de modales inicializado correctamente');

// ================================
// EXPORTAR PARA DEBUGGING
// ================================

window.modalSystem = {
    showModal: window.showModal,
    closeModal: window.closeModal,
    showLoading: window.showLoading,
    hideLoading: window.hideLoading,
    showNotification: window.showNotification,
    showConfirm: window.showConfirm,
    isModalActive: window.isModalActive,
    getActiveModals: window.getActiveModals,
    activeModals: activeModals
};