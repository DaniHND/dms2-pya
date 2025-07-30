/* ============================================================================
   MAIN.JS - JAVASCRIPT PRINCIPAL BÁSICO
   Funciones comunes para todo el sistema DMS2
   ============================================================================ */

// Variables globales
window.DMS = window.DMS || {};

// Función para inicializar iconos de Feather
function initializeFeatherIcons() {
    if (typeof feather !== 'undefined') {
        feather.replace();
        console.log('✅ Iconos de Feather inicializados');
        return true;
    } else {
        console.warn('⚠️ Feather Icons no está disponible');
        return false;
    }
}

// Función para toggle del sidebar
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.toggle('collapsed');
        
        if (mainContent) {
            mainContent.classList.toggle('sidebar-collapsed');
        }
        
        if (overlay) {
            overlay.classList.toggle('active');
        }
    }
}

// Función para mostrar "próximamente"
function showComingSoon(feature) {
    alert(`${feature} estará disponible próximamente.`);
}

// Función para formatear fechas
function formatDate(dateString, format = 'dd/mm/yyyy') {
    if (!dateString) return 'N/A';
    
    const date = new Date(dateString);
    
    if (format === 'relative') {
        const now = new Date();
        const diff = now - date;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        
        if (days === 0) return 'Hoy';
        if (days === 1) return 'Ayer';
        if (days < 7) return `Hace ${days} días`;
        if (days < 30) return `Hace ${Math.floor(days / 7)} semanas`;
        if (days < 365) return `Hace ${Math.floor(days / 30)} meses`;
        return `Hace ${Math.floor(days / 365)} años`;
    }
    
    return date.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

// Función para actualizar el tiempo actual
function updateCurrentTime() {
    const timeElements = document.querySelectorAll('.current-time, #currentTime');
    const now = new Date();
    const timeString = now.toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    timeElements.forEach(element => {
        element.textContent = timeString;
    });
}

// Función para mostrar notificaciones
function showNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Mostrar notificación
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Ocultar notificación
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// Función para validar formularios
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    return isValid;
}

// Función para hacer peticiones AJAX
async function makeRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        },
    };
    
    const config = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(url, config);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error in request:', error);
        throw error;
    }
}

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar iconos
    initializeFeatherIcons();
    
    // Actualizar tiempo cada minuto
    updateCurrentTime();
    setInterval(updateCurrentTime, 60000);
    
    // Configurar formularios
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                showNotification('Por favor, complete todos los campos requeridos', 'error');
            }
        });
    });
    
    console.log('✅ Main.js inicializado correctamente');
});

// Exportar funciones globales
window.toggleSidebar = toggleSidebar;
window.showComingSoon = showComingSoon;
window.formatDate = formatDate;
window.showNotification = showNotification;
window.makeRequest = makeRequest;
window.initializeFeatherIcons = initializeFeatherIcons;