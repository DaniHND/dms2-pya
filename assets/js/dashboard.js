// assets/js/dashboard.js
// JavaScript para el dashboard principal - DMS2

document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
});

// Inicializar el dashboard
function initializeDashboard() {
    // Configurar responsive behavior
    setupResponsiveBehavior();
    
    // Inicializar tooltips
    initializeTooltips();
    
    // Configurar auto-refresh
    setupAutoRefresh();
    
    // Detectar inactividad
    setupInactivityDetector();
    
    // Animar elementos al cargar
    animateOnLoad();
}

// Alternar sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (window.innerWidth <= 768) {
        // Comportamiento móvil
        sidebar.classList.toggle('active');
        
        if (!overlay) {
            const newOverlay = document.createElement('div');
            newOverlay.className = 'sidebar-overlay';
            newOverlay.addEventListener('click', toggleSidebar);
            document.body.appendChild(newOverlay);
        }
        
        document.querySelector('.sidebar-overlay').classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    } else {
        // Comportamiento desktop
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('sidebar-collapsed');
    }
}

// Configurar comportamiento responsive
function setupResponsiveBehavior() {
    let resizeTimer;
    
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (window.innerWidth > 768) {
                // Desktop: remover clases móviles
                sidebar.classList.remove('active');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                // Mobile: remover clases desktop
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('sidebar-collapsed');
            }
        }, 250);
    });
}

// Actualizar reloj
function updateTime() {
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        const now = new Date();
        const timeString = now.toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        const dateString = now.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
        timeElement.textContent = `${dateString} ${timeString}`;
    }
}

// Refrescar dashboard
function refreshDashboard() {
    const refreshBtn = document.querySelector('[onclick="refreshDashboard()"]');
    const originalIcon = refreshBtn.querySelector('i');
    
    // Animación de rotación
    originalIcon.style.animation = 'spin 1s linear infinite';
    refreshBtn.disabled = true;
    
    // Simular actualización
    setTimeout(() => {
        // Aquí se haría la petición AJAX real
        location.reload();
    }, 1000);
}

// Mostrar notificaciones
function showNotifications() {
    const notifications = [
        {
            id: 1,
            type: 'info',
            title: 'Nuevo documento',
            message: 'Se ha subido un nuevo documento: Factura_2025_001.pdf',
            time: '5 min ago',
            read: false
        },
        {
            id: 2,
            type: 'warning',
            title: 'Espacio de almacenamiento',
            message: 'El almacenamiento está al 85% de su capacidad',
            time: '1 hora ago',
            read: false
        },
        {
            id: 3,
            type: 'success',
            title: 'Backup completado',
            message: 'Respaldo automático completado exitosamente',
            time: '2 horas ago',
            read: true
        }
    ];
    
    showNotificationModal(notifications);
}

// Modal de notificaciones
function showNotificationModal(notifications) {
    const modal = createModal('notifications', 'Notificaciones');
    
    const content = `
        <div class="notifications-list">
            ${notifications.map(notif => `
                <div class="notification-item ${notif.read ? 'read' : 'unread'}">
                    <div class="notification-icon ${notif.type}">
                        <i data-feather="${getNotificationIcon(notif.type)}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notif.title}</div>
                        <div class="notification-message">${notif.message}</div>
                        <div class="notification-time">${notif.time}</div>
                    </div>
                    <button class="notification-close" onclick="removeNotification(${notif.id})">
                        <i data-feather="x"></i>
                    </button>
                </div>
            `).join('')}
        </div>
        <div class="notifications-footer">
            <button class="btn btn-outline btn-sm" onclick="markAllAsRead()">
                Marcar todas como leídas
            </button>
            <button class="btn btn-primary btn-sm" onclick="closeModal('notifications')">
                Cerrar
            </button>
        </div>
    `;
    
    modal.querySelector('.modal-body').innerHTML = content;
    feather.replace();
}

// Iconos para notificaciones
function getNotificationIcon(type) {
    const icons = {
        'info': 'info',
        'success': 'check-circle',
        'warning': 'alert-triangle',
        'error': 'alert-circle'
    };
    return icons[type] || 'bell';
}

// Mostrar modal "Próximamente"
function showComingSoon(feature) {
    const modal = document.getElementById('comingSoonModal');
    const title = document.getElementById('comingSoonTitle');
    const message = document.getElementById('comingSoonMessage');
    
    title.textContent = feature;
    message.textContent = `La funcionalidad "${feature}" estará disponible próximamente.`;
    
    modal.classList.add('active');
    
    // Animar entrada
    const content = modal.querySelector('.modal-content');
    content.style.transform = 'scale(0.8)';
    content.style.opacity = '0';
    
    setTimeout(() => {
        content.style.transform = 'scale(1)';
        content.style.opacity = '1';
        content.style.transition = 'all 0.3s ease-out';
    }, 10);
}

// Ocultar modal "Próximamente"
function hideComingSoon() {
    const modal = document.getElementById('comingSoonModal');
    const content = modal.querySelector('.modal-content');
    
    content.style.transform = 'scale(0.8)';
    content.style.opacity = '0';
    
    setTimeout(() => {
        modal.classList.remove('active');
        content.style.transform = '';
        content.style.opacity = '';
        content.style.transition = '';
    }, 300);
}

// Crear modal genérico
function createModal(id, title) {
    // Remover modal existente si existe
    const existingModal = document.getElementById(id + 'Modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    const modal = document.createElement('div');
    modal.id = id + 'Modal';
    modal.className = 'modal active';
    
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="close" onclick="closeModal('${id}')">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body"></div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Cerrar al hacer clic fuera
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(id);
        }
    });
    
    feather.replace();
    return modal;
}

// Cerrar modal
function closeModal(id) {
    const modal = document.getElementById(id + 'Modal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.remove(), 300);
    }
}

// Mostrar menú de usuario
function showUserMenu() {
    const userOptions = [
        { icon: 'user', text: 'Mi Perfil', action: () => showComingSoon('Mi Perfil') },
        { icon: 'settings', text: 'Configuración', action: () => showComingSoon('Configuración') },
        { icon: 'help-circle', text: 'Ayuda', action: () => showComingSoon('Ayuda') },
        { icon: 'log-out', text: 'Cerrar Sesión', action: () => window.location.href = 'logout.php' }
    ];
    
    const modal = createModal('userMenu', 'Opciones de Usuario');
    
    const content = `
        <div class="user-menu-options">
            ${userOptions.map(option => `
                <button class="user-menu-option" onclick="${option.action.toString().replace('() => ', '')}">
                    <i data-feather="${option.icon}"></i>
                    <span>${option.text}</span>
                </button>
            `).join('')}
        </div>
    `;
    
    modal.querySelector('.modal-body').innerHTML = content;
    feather.replace();
}

// Configurar tooltips
function initializeTooltips() {
    const elementsWithTooltip = document.querySelectorAll('[title]');
    
    elementsWithTooltip.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

// Mostrar tooltip
function showTooltip(e) {
    const element = e.target;
    const text = element.getAttribute('title');
    
    if (!text) return;
    
    // Remover title para evitar tooltip nativo
    element.setAttribute('data-original-title', text);
    element.removeAttribute('title');
    
    const tooltip = document.createElement('div');
    tooltip.className = 'custom-tooltip';
    tooltip.textContent = text;
    document.body.appendChild(tooltip);
    
    // Posicionar tooltip
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
    
    // Animar entrada
    setTimeout(() => tooltip.classList.add('visible'), 10);
    
    element._tooltip = tooltip;
}

// Ocultar tooltip
function hideTooltip(e) {
    const element = e.target;
    const tooltip = element._tooltip;
    
    if (tooltip) {
        tooltip.classList.remove('visible');
        setTimeout(() => {
            if (tooltip.parentNode) {
                tooltip.parentNode.removeChild(tooltip);
            }
        }, 200);
        delete element._tooltip;
    }
    
    // Restaurar title original
    const originalTitle = element.getAttribute('data-original-title');
    if (originalTitle) {
        element.setAttribute('title', originalTitle);
        element.removeAttribute('data-original-title');
    }
}

// Configurar auto-refresh
function setupAutoRefresh() {
    // Actualizar estadísticas cada 5 minutos
    setInterval(() => {
        updateDashboardStats();
    }, 5 * 60 * 1000);
    
    // Actualizar actividad cada 2 minutos
    setInterval(() => {
        updateRecentActivity();
    }, 2 * 60 * 1000);
}

// Actualizar estadísticas
function updateDashboardStats() {
    // Aquí se haría una petición AJAX para obtener stats actualizadas
    console.log('Actualizando estadísticas...');
}

// Actualizar actividad reciente
function updateRecentActivity() {
    // Aquí se haría una petición AJAX para obtener actividad reciente
    console.log('Actualizando actividad reciente...');
}

// Detector de inactividad
function setupInactivityDetector() {
    let inactivityTimer;
    const INACTIVITY_TIME = 30 * 60 * 1000; // 30 minutos
    
    function resetTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => {
            showInactivityWarning();
        }, INACTIVITY_TIME);
    }
    
    // Eventos que indican actividad
    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, resetTimer, true);
    });
    
    resetTimer();
}

// Advertencia de inactividad
function showInactivityWarning() {
    const modal = createModal('inactivity', 'Sesión por Expirar');
    
    let countdown = 300; // 5 minutos
    
    const content = `
        <div class="inactivity-warning">
            <div class="warning-icon">
                <i data-feather="clock"></i>
            </div>
            <p>Su sesión expirará en <span id="countdown">${countdown}</span> segundos por inactividad.</p>
            <div class="warning-actions">
                <button class="btn btn-primary" onclick="extendSession()">
                    Continuar Sesión
                </button>
                <button class="btn btn-outline" onclick="window.location.href='logout.php'">
                    Cerrar Sesión
                </button>
            </div>
        </div>
    `;
    
    modal.querySelector('.modal-body').innerHTML = content;
    feather.replace();
    
    // Countdown
    const countdownElement = document.getElementById('countdown');
    const countdownInterval = setInterval(() => {
        countdown--;
        if (countdownElement) {
            countdownElement.textContent = countdown;
        }
        
        if (countdown <= 0) {
            clearInterval(countdownInterval);
            window.location.href = 'logout.php';
        }
    }, 1000);
    
    // Guardar referencia para poder cancelar
    modal._countdownInterval = countdownInterval;
}

// Extender sesión
function extendSession() {
    closeModal('inactivity');
    
    // Aquí se haría una petición AJAX para extender la sesión
    fetch('config/extend_session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Sesión extendida exitosamente');
        }
    })
    .catch(error => {
        console.error('Error al extender sesión:', error);
    });
}

// Mostrar notificación toast
function showNotification(type, message, duration = 4000) {
    const notification = document.createElement('div');
    notification.className = `notification-toast ${type}`;
    
    const icon = getNotificationIcon(type);
    notification.innerHTML = `
        <i data-feather="${icon}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">
            <i data-feather="x"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    feather.replace();
    
    // Animar entrada
    setTimeout(() => notification.classList.add('visible'), 10);
    
    // Auto-remover
    setTimeout(() => {
        notification.classList.remove('visible');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// Animar elementos al cargar
function animateOnLoad() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, { threshold: 0.1 });
    
    // Observar elementos que deben animarse
    document.querySelectorAll('.stat-card, .dashboard-widget').forEach(el => {
        observer.observe(el);
    });
}

// Navegación por teclado
document.addEventListener('keydown', function(e) {
    // Cerrar modales con Escape
    if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal.active');
        if (activeModal) {
            const modalId = activeModal.id.replace('Modal', '');
            if (modalId === 'comingSoon') {
                hideComingSoon();
            } else {
                closeModal(modalId);
            }
        }
    }
    
    // Alternar sidebar con Ctrl+B
    if (e.ctrlKey && e.key === 'b') {
        e.preventDefault();
        toggleSidebar();
    }
    
    // Refrescar con F5 o Ctrl+R
    if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
        e.preventDefault();
        refreshDashboard();
    }
});

// Funciones auxiliares para notificaciones
function markAllAsRead() {
    const unreadNotifications = document.querySelectorAll('.notification-item.unread');
    unreadNotifications.forEach(notif => {
        notif.classList.remove('unread');
        notif.classList.add('read');
    });
    
    // Actualizar badge
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        badge.textContent = '0';
        badge.style.display = 'none';
    }
    
    showNotification('success', 'Todas las notificaciones marcadas como leídas');
}

function removeNotification(id) {
    const notification = document.querySelector(`[onclick="removeNotification(${id})"]`).closest('.notification-item');
    if (notification) {
        notification.style.animation = 'slideOut 0.3s ease-in-out';
        setTimeout(() => {
            notification.remove();
            
            // Verificar si quedan notificaciones
            const remainingNotifications = document.querySelectorAll('.notification-item');
            if (remainingNotifications.length === 0) {
                const notificationsList = document.querySelector('.notifications-list');
                notificationsList.innerHTML = '<div class="empty-state"><i data-feather="bell"></i><p>No hay notificaciones</p></div>';
                feather.replace();
            }
        }, 300);
    }
}

// Gestión de estado del dashboard
class DashboardState {
    constructor() {
        this.data = {
            stats: {},
            recentDocuments: [],
            recentActivity: [],
            lastUpdate: null
        };
        this.loadFromStorage();
    }
    
    update(key, value) {
        this.data[key] = value;
        this.data.lastUpdate = new Date().toISOString();
        this.saveToStorage();
    }
    
    get(key) {
        return this.data[key];
    }
    
    saveToStorage() {
        try {
            localStorage.setItem('dms2_dashboard_state', JSON.stringify(this.data));
        } catch (e) {
            console.warn('No se pudo guardar el estado del dashboard');
        }
    }
    
    loadFromStorage() {
        try {
            const stored = localStorage.getItem('dms2_dashboard_state');
            if (stored) {
                this.data = { ...this.data, ...JSON.parse(stored) };
            }
        } catch (e) {
            console.warn('No se pudo cargar el estado del dashboard');
        }
    }
    
    clear() {
        this.data = {
            stats: {},
            recentDocuments: [],
            recentActivity: [],
            lastUpdate: null
        };
        try {
            localStorage.removeItem('dms2_dashboard_state');
        } catch (e) {
            console.warn('No se pudo limpiar el estado del dashboard');
        }
    }
}

// Instancia global del estado
const dashboardState = new DashboardState();

// Función para cargar datos del dashboard
async function loadDashboardData() {
    try {
        // Mostrar indicador de carga
        showLoadingIndicator();
        
        // Simular carga de datos (en producción sería una petición AJAX)
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Actualizar estado
        dashboardState.update('stats', {
            totalDocuments: Math.floor(Math.random() * 1000) + 500,
            documentsToday: Math.floor(Math.random() * 50) + 10,
            totalUsers: Math.floor(Math.random() * 100) + 20,
            totalCompanies: Math.floor(Math.random() * 10) + 5
        });
        
        // Ocultar indicador de carga
        hideLoadingIndicator();
        
        showNotification('success', 'Dashboard actualizado');
        
    } catch (error) {
        hideLoadingIndicator();
        showNotification('error', 'Error al cargar datos del dashboard');
        console.error('Error:', error);
    }
}

// Indicadores de carga
function showLoadingIndicator() {
    const indicator = document.createElement('div');
    indicator.id = 'loadingIndicator';
    indicator.className = 'loading-indicator';
    indicator.innerHTML = `
        <div class="loading-spinner"></div>
        <span>Actualizando datos...</span>
    `;
    document.body.appendChild(indicator);
    
    setTimeout(() => indicator.classList.add('visible'), 10);
}

function hideLoadingIndicator() {
    const indicator = document.getElementById('loadingIndicator');
    if (indicator) {
        indicator.classList.remove('visible');
        setTimeout(() => {
            if (indicator.parentNode) {
                indicator.parentNode.removeChild(indicator);
            }
        }, 300);
    }
}

// Función para buscar rápidamente
function quickSearch(query) {
    if (!query || query.length < 2) {
        return [];
    }
    
    // Simular búsqueda (en producción sería una petición AJAX)
    const mockResults = [
        { type: 'document', name: 'Factura_2025_001.pdf', path: '/documentos/facturas/' },
        { type: 'document', name: 'Contrato_Servicios.docx', path: '/documentos/contratos/' },
        { type: 'user', name: 'Juan Pérez', email: 'jperez@ejemplo.com' },
        { type: 'company', name: 'Empresa Ejemplo SA', ruc: '1234567890123' }
    ];
    
    return mockResults.filter(item => 
        item.name.toLowerCase().includes(query.toLowerCase())
    );
}

// Configurar búsqueda rápida
function setupQuickSearch() {
    const searchInput = document.getElementById('quickSearch');
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        
        if (query.length < 2) {
            hideSearchResults();
            return;
        }
        
        searchTimeout = setTimeout(() => {
            const results = quickSearch(query);
            showSearchResults(results);
        }, 300);
    });
    
    searchInput.addEventListener('blur', function() {
        setTimeout(() => hideSearchResults(), 200);
    });
}

// Mostrar resultados de búsqueda
function showSearchResults(results) {
    let resultsContainer = document.getElementById('searchResults');
    
    if (!resultsContainer) {
        resultsContainer = document.createElement('div');
        resultsContainer.id = 'searchResults';
        resultsContainer.className = 'search-results';
        document.getElementById('quickSearch').parentNode.appendChild(resultsContainer);
    }
    
    if (results.length === 0) {
        resultsContainer.innerHTML = '<div class="search-no-results">No se encontraron resultados</div>';
    } else {
        resultsContainer.innerHTML = results.map(result => `
            <div class="search-result-item" onclick="selectSearchResult('${result.type}', '${result.name}')">
                <div class="search-result-icon">
                    <i data-feather="${getSearchResultIcon(result.type)}"></i>
                </div>
                <div class="search-result-content">
                    <div class="search-result-name">${result.name}</div>
                    <div class="search-result-details">${getSearchResultDetails(result)}</div>
                </div>
            </div>
        `).join('');
    }
    
    resultsContainer.classList.add('visible');
    feather.replace();
}

// Ocultar resultados de búsqueda
function hideSearchResults() {
    const resultsContainer = document.getElementById('searchResults');
    if (resultsContainer) {
        resultsContainer.classList.remove('visible');
    }
}

// Obtener icono para resultado de búsqueda
function getSearchResultIcon(type) {
    const icons = {
        'document': 'file-text',
        'user': 'user',
        'company': 'building',
        'folder': 'folder'
    };
    return icons[type] || 'search';
}

// Obtener detalles para resultado de búsqueda
function getSearchResultDetails(result) {
    switch (result.type) {
        case 'document':
            return result.path;
        case 'user':
            return result.email;
        case 'company':
            return `RUC: ${result.ruc}`;
        default:
            return '';
    }
}

// Seleccionar resultado de búsqueda
function selectSearchResult(type, name) {
    hideSearchResults();
    showComingSoon(`Ver ${type}: ${name}`);
}

// Función para exportar datos
function exportDashboardData(format = 'json') {
    const data = {
        stats: dashboardState.get('stats'),
        recentDocuments: dashboardState.get('recentDocuments'),
        recentActivity: dashboardState.get('recentActivity'),
        exportDate: new Date().toISOString(),
        user: getCurrentUserInfo()
    };
    
    let content, filename, mimeType;
    
    switch (format) {
        case 'json':
            content = JSON.stringify(data, null, 2);
            filename = `dashboard_export_${new Date().toISOString().split('T')[0]}.json`;
            mimeType = 'application/json';
            break;
        case 'csv':
            content = convertToCSV(data.stats);
            filename = `dashboard_stats_${new Date().toISOString().split('T')[0]}.csv`;
            mimeType = 'text/csv';
            break;
        default:
            showNotification('error', 'Formato de exportación no soportado');
            return;
    }
    
    downloadFile(content, filename, mimeType);
    showNotification('success', `Datos exportados como ${format.toUpperCase()}`);
}

// Convertir a CSV
function convertToCSV(stats) {
    const headers = Object.keys(stats).join(',');
    const values = Object.values(stats).join(',');
    return `${headers}\n${values}`;
}

// Descargar archivo
function downloadFile(content, filename, mimeType) {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.style.display = 'none';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    URL.revokeObjectURL(url);
}

// Obtener información del usuario actual
function getCurrentUserInfo() {
    // En producción esto vendría del servidor
    return {
        name: document.querySelector('.user-name')?.textContent || 'Usuario',
        role: document.querySelector('.user-role')?.textContent || 'user'
    };
}

// Configurar atajos de teclado
function setupKeyboardShortcuts() {
    const shortcuts = {
        'Alt+1': () => document.querySelector('[href="#dashboard"]')?.click(),
        'Alt+2': () => showComingSoon('Subir Documentos'),
        'Alt+3': () => showComingSoon('Bandeja de Entrada'),
        'Alt+4': () => showComingSoon('Búsqueda'),
        'Alt+R': () => refreshDashboard(),
        'Alt+N': () => showNotifications(),
        'Alt+S': () => document.getElementById('quickSearch')?.focus()
    };
    
    document.addEventListener('keydown', function(e) {
        const combo = [];
        if (e.altKey) combo.push('Alt');
        if (e.ctrlKey) combo.push('Ctrl');
        if (e.shiftKey) combo.push('Shift');
        combo.push(e.key.toUpperCase());
        
        const shortcut = combo.join('+');
        if (shortcuts[shortcut]) {
            e.preventDefault();
            shortcuts[shortcut]();
        }
    });
}

// Monitoreo de rendimiento
function setupPerformanceMonitoring() {
    // Medir tiempo de carga del dashboard
    const navigationStart = performance.timing.navigationStart;
    const loadComplete = performance.timing.loadEventEnd;
    const loadTime = loadComplete - navigationStart;
    
    console.log(`Dashboard cargado en ${loadTime}ms`);
    
    // Monitorear memoria (si está disponible)
    if ('memory' in performance) {
        const memory = performance.memory;
        console.log(`Memoria utilizada: ${Math.round(memory.usedJSHeapSize / 1048576)}MB`);
    }
    
    // Detectar problemas de rendimiento
    if (loadTime > 3000) {
        console.warn('El dashboard está cargando lentamente');
    }
}

// Configurar tema del sistema
function setupThemeDetection() {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    
    function handleThemeChange(e) {
        if (e.matches) {
            document.body.classList.add('dark-theme');
        } else {
            document.body.classList.remove('dark-theme');
        }
    }
    
    // Aplicar tema inicial
    handleThemeChange(mediaQuery);
    
    // Escuchar cambios
    mediaQuery.addListener(handleThemeChange);
}

// Inicializar funciones adicionales
document.addEventListener('DOMContentLoaded', function() {
    setupQuickSearch();
    setupKeyboardShortcuts();
    setupPerformanceMonitoring();
    setupThemeDetection();
});

// Cleanup al salir de la página
window.addEventListener('beforeunload', function() {
    // Limpiar timers
    clearInterval(window.timeInterval);
    clearInterval(window.refreshInterval);
    
    // Guardar estado final
    dashboardState.saveToStorage();
});

// Función para imprimir dashboard
function printDashboard() {
    const printContent = `
        <html>
        <head>
            <title>Dashboard DMS2 - ${new Date().toLocaleDateString()}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { border-bottom: 2px solid #8B4513; padding-bottom: 10px; margin-bottom: 20px; }
                .stats { display: flex; justify-content: space-around; margin: 20px 0; }
                .stat { text-align: center; padding: 10px; border: 1px solid #ddd; }
                .stat-number { font-size: 24px; font-weight: bold; color: #8B4513; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Dashboard DMS2</h1>
                <p>Generado el: ${new Date().toLocaleString()}</p>
                <p>Usuario: ${getCurrentUserInfo().name}</p>
            </div>
            ${generatePrintableStats()}
        </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.print();
}

// Generar estadísticas para impresión
function generatePrintableStats() {
    const stats = dashboardState.get('stats');
    return `
        <div class="stats">
            <div class="stat">
                <div class="stat-number">${stats.totalDocuments || 0}</div>
                <div>Total Documentos</div>
            </div>
            <div class="stat">
                <div class="stat-number">${stats.documentsToday || 0}</div>
                <div>Subidos Hoy</div>
            </div>
            <div class="stat">
                <div class="stat-number">${stats.totalUsers || 0}</div>
                <div>Usuarios Activos</div>
            </div>
            <div class="stat">
                <div class="stat-number">${stats.totalCompanies || 0}</div>
                <div>Empresas</div>
            </div>
        </div>
    `;
}