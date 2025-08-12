// assets/js/main.js
// Funciones principales para DMS2 (VERSIÓN LIMPIA)

// Función para actualizar el tiempo
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

// Función para toggle del sidebar en móvil
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
        body.classList.toggle('sidebar-open');
    }
}

// Función para cerrar sidebar
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    sidebar.classList.remove('show');
    if (overlay) overlay.classList.remove('show');
    body.classList.remove('sidebar-open');
}

// Función para mostrar "Coming Soon"
function showComingSoon(feature) {
    alert(`${feature} - Próximamente disponible`);
}

// Función básica de notificaciones
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    
    let backgroundColor = '#3b82f6'; // azul por defecto
    if (type === 'success') backgroundColor = '#10b981';
    if (type === 'error') backgroundColor = '#ef4444';
    if (type === 'warning') backgroundColor = '#f59e0b';
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${backgroundColor};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 9999;
        font-weight: 600;
        max-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-family: Arial, sans-serif;
        font-size: 14px;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, duration);
}

// Manejo responsive del sidebar
function handleSidebarResize() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    if (window.innerWidth > 768) {
        sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
        body.classList.remove('sidebar-open');
    }
}

// Inicialización básica
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar iconos si están disponibles
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Configurar responsive
    window.addEventListener('resize', handleSidebarResize);
    
    // Inicializar tiempo si hay elemento
    if (document.getElementById('currentTime')) {
        updateTime();
        setInterval(updateTime, 1000);
    }
});

// Exportar funciones globalmente
window.updateTime = updateTime;
window.toggleSidebar = toggleSidebar;
window.closeSidebar = closeSidebar;
window.showComingSoon = showComingSoon;
window.showNotification = showNotification;