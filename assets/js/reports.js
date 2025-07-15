// assets/js/reports.js
// JavaScript para el módulo de reportes - DMS2

// Variables globales
let currentReportType = 'activity_log';
let currentFilters = {};
let charts = {};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initializeReports();
});

// Función principal de inicialización
function initializeReports() {
    console.log('🚀 Inicializando módulo de reportes');
    
    // Configuración básica
    feather.replace();
    updateTime();
    setInterval(updateTime, 1000);
    
    // Configurar eventos
    setupEventListeners();
    
    // Configurar responsive
    setupResponsiveBehavior();
    
    // Configurar filtros avanzados
    setupAdvancedFilters();
    
    // Inicializar tooltips
    initializeTooltips();
    
    console.log('✅ Módulo de reportes inicializado');
}

// Configurar event listeners
function setupEventListeners() {
    // Filtros de fecha
    const dateFromInput = document.getElementById('date_from');
    const dateToInput = document.getElementById('date_to');
    
    if (dateFromInput && dateToInput) {
        dateFromInput.addEventListener('change', validateDateRange);
        dateToInput.addEventListener('change', validateDateRange);
    }
    
    // Botones de exportación
    const exportButtons = document.querySelectorAll('.export-btn');
    exportButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const format = this.getAttribute('onclick').match(/'([^']+)'/)[1];
            exportData(format);
        });
    });
    
    // Botones de filtro
    const filterForm = document.querySelector('.reports-filters form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            showLoadingState();
        });
    }
    
    // Botones de navegación
    const navButtons = document.querySelectorAll('.nav-btn');
    navButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            if (href && href !== '#') {
                window.location.href = href;
            }
        });
    });
}

// Validar rango de fechas
function validateDateRange() {
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    if (!dateFrom || !dateTo) return;
    
    const fromDate = new Date(dateFrom.value);
    const toDate = new Date(dateTo.value);
    
    if (fromDate > toDate) {
        showNotification('La fecha "Desde" no puede ser mayor que la fecha "Hasta"', 'error');
        dateFrom.value = dateTo.value;
    }
    
    // Validar que no sea más de 1 año de diferencia
    const oneYearAgo = new Date();
    oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);
    
    if (fromDate < oneYearAgo) {
        showNotification('El rango de fechas no puede exceder 1 año', 'warning');
        dateFrom.value = oneYearAgo.toISOString().split('T')[0];
    }
}

// Configurar filtros avanzados
function setupAdvancedFilters() {
    // Auto-completar en campos de texto
    const searchInputs = document.querySelectorAll('input[type="text"]');
    searchInputs.forEach(input => {
        input.addEventListener('input', debounce(function() {
            // Aquí se podría implementar búsqueda en tiempo real
            console.log('Buscando:', this.value);
        }, 300));
    });
    
    // Filtros dependientes (empresa -> departamento)
    const companySelect = document.getElementById('company_id');
    const departmentSelect = document.getElementById('department_id');
    
    if (companySelect && departmentSelect) {
        companySelect.addEventListener('change', function() {
            filterDepartments(this.value);
        });
    }
}

// Filtrar departamentos según empresa
function filterDepartments(companyId) {
    const departmentSelect = document.getElementById('department_id');
    if (!departmentSelect) return;
    
    const options = departmentSelect.querySelectorAll('option');
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
        } else {
            const optionCompany = option.getAttribute('data-company');
            option.style.display = (optionCompany === companyId || !companyId) ? 'block' : 'none';
        }
    });
    
    // Resetear selección si no es válida
    const selectedOption = departmentSelect.options[departmentSelect.selectedIndex];
    if (selectedOption && selectedOption.getAttribute('data-company') !== companyId && companyId) {
        departmentSelect.value = '';
    }
}

// Función de exportación mejorada
function exportData(format) {
    console.log(`📊 Exportando datos en formato: ${format}`);
    
    // Validar formato
    if (!['csv', 'excel', 'pdf'].includes(format)) {
        showNotification('Formato de exportación no válido', 'error');
        return;
    }
    
    // Obtener filtros actuales
    const urlParams = new URLSearchParams(window.location.search);
    const reportType = getCurrentReportType();
    
    // Mostrar loading
    showLoadingState();
    
    // Construir URL de exportación
    const exportUrl = `export.php?format=${format}&type=${reportType}&${urlParams.toString()}`;
    
    // Crear enlace temporal para descarga
    const downloadLink = document.createElement('a');
    downloadLink.href = exportUrl;
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    
    // Simular click para iniciar descarga
    downloadLink.click();
    
    // Cleanup
    setTimeout(() => {
        document.body.removeChild(downloadLink);
        hideLoadingState();
        showNotification(`Exportación ${format.toUpperCase()} iniciada`, 'success');
    }, 1000);
}

// Obtener tipo de reporte actual
function getCurrentReportType() {
    const path = window.location.pathname;
    
    if (path.includes('activity_log')) return 'activity_log';
    if (path.includes('user_reports')) return 'user_reports';
    if (path.includes('operations_report')) return 'operations_report';
    if (path.includes('documents_report')) return 'documents_report';
    
    return 'activity_log';
}

// Función para imprimir reporte
function printReport() {
    console.log('🖨️ Imprimiendo reporte');
    
    // Crear ventana de impresión
    const printWindow = window.open('', '_blank');
    
    // Obtener contenido para imprimir
    const reportContent = document.querySelector('.reports-content');
    const reportTitle = document.querySelector('h1').textContent;
    
    // Generar HTML para impresión
    const printHTML = `
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>${reportTitle} - DMS2</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #8B4513; text-align: center; margin-bottom: 30px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
                th { background-color: #8B4513; color: white; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .no-print { display: none; }
                .print-header { margin-bottom: 20px; }
                @media print { 
                    body { margin: 0; } 
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>${reportTitle}</h1>
                <p><strong>Generado:</strong> ${new Date().toLocaleString('es-ES')}</p>
                <p><strong>Usuario:</strong> ${getCurrentUserName()}</p>
            </div>
            ${getContentForPrint(reportContent)}
        </body>
        </html>
    `;
    
    printWindow.document.write(printHTML);
    printWindow.document.close();
    printWindow.print();
    
    showNotification('Reporte enviado a impresión', 'info');
}

// Obtener contenido para impresión
function getContentForPrint(content) {
    if (!content) return '';
    
    // Clonar el contenido
    const clonedContent = content.cloneNode(true);
    
    // Remover elementos no imprimibles
    const noprint = clonedContent.querySelectorAll('.no-print, .export-section, .filters-actions, .sidebar, .header-actions');
    noprint.forEach(el => el.remove());
    
    // Simplificar tablas para impresión
    const tables = clonedContent.querySelectorAll('table');
    tables.forEach(table => {
        table.style.fontSize = '11px';
        table.style.width = '100%';
    });
    
    return clonedContent.innerHTML;
}

// Obtener nombre del usuario actual
function getCurrentUserName() {
    const userNameElement = document.querySelector('.user-name-header');
    return userNameElement ? userNameElement.textContent : 'Usuario';
}

// Estados de carga
function showLoadingState() {
    // Crear overlay de loading
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.innerHTML = `
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <p>Procesando...</p>
        </div>
    `;
    
    // Estilos inline para el overlay
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    `;
    
    const loadingContent = overlay.querySelector('.loading-content');
    loadingContent.style.cssText = `
        text-align: center;
        padding: 2rem;
        background: rgba(139, 69, 19, 0.9);
        border-radius: 8px;
    `;
    
    const spinner = overlay.querySelector('.loading-spinner');
    spinner.style.cssText = `
        width: 40px;
        height: 40px;
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-top: 4px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    `;
    
    document.body.appendChild(overlay);
    
    // Agregar animación de spinner
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
}

function hideLoadingState() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

// Sistema de notificaciones
function showNotification(message, type = 'info', duration = 4000) {
    // Remover notificaciones existentes
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(notif => notif.remove());
    
    // Crear nueva notificación
    const notification = document.createElement('div');
    notification.className = `notification-toast ${type}`;
    
    const colors = {
        'info': '#3b82f6',
        'success': '#10b981',
        'warning': '#f59e0b',
        'error': '#ef4444'
    };
    
    const icons = {
        'info': 'info',
        'success': 'check-circle',
        'warning': 'alert-triangle',
        'error': 'alert-circle'
    };
    
    notification.innerHTML = `
        <i data-feather="${icons[type]}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">
            <i data-feather="x"></i>
        </button>
    `;
    
    // Estilos
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        color: #1e293b;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-left: 4px solid ${colors[type]};
        z-index: 10000;
        font-size: 14px;
        font-weight: 500;
        max-width: 350px;
        display: flex;
        align-items: center;
        gap: 10px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
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

// Configurar tooltips
function initializeTooltips() {
    const elementsWithTooltip = document.querySelectorAll('[title]');
    
    elementsWithTooltip.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const element = e.target;
    const text = element.getAttribute('title');
    
    if (!text) return;
    
    // Crear tooltip
    const tooltip = document.createElement('div');
    tooltip.className = 'custom-tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: absolute;
        background: #1e293b;
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        z-index: 10000;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s;
    `;
    
    document.body.appendChild(tooltip);
    
    // Posicionar tooltip
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
    
    // Mostrar tooltip
    setTimeout(() => tooltip.style.opacity = '1', 10);
    
    // Ocultar title original
    element.setAttribute('data-original-title', text);
    element.removeAttribute('title');
    
    element._tooltip = tooltip;
}

function hideTooltip(e) {
    const element = e.target;
    const tooltip = element._tooltip;
    
    if (tooltip) {
        tooltip.style.opacity = '0';
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

// Configurar comportamiento responsive
function setupResponsiveBehavior() {
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (window.innerWidth > 768) {
            sidebar?.classList.remove('active');
            overlay?.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
}

// Función para alternar sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (window.innerWidth <= 768) {
        sidebar?.classList.toggle('active');
        overlay?.classList.toggle('active');
        document.body.style.overflow = sidebar?.classList.contains('active') ? 'hidden' : '';
    }
}

// Función para actualizar hora
function updateTime() {
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        const now = new Date();
        const timeString = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        const dateString = now.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
        timeElement.textContent = `${dateString} ${timeString}`;
    }
}

// Función debounce para optimizar eventos
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Funciones de utilidad
function showComingSoon(feature) {
    showNotification(`${feature} - Próximamente`, 'info');
}

// Función para limpiar filtros
function clearFilters() {
    const form = document.querySelector('.reports-filters form');
    if (form) {
        form.reset();
        form.submit();
    }
}

// Función para guardar filtros en localStorage
function saveFilters() {
    const form = document.querySelector('.reports-filters form');
    if (form) {
        const formData = new FormData(form);
        const filters = {};
        
        for (let [key, value] of formData.entries()) {
            filters[key] = value;
        }
        
        localStorage.setItem('dms2_report_filters', JSON.stringify(filters));
        showNotification('Filtros guardados', 'success');
    }
}

// Función para cargar filtros desde localStorage
function loadFilters() {
    try {
        const savedFilters = localStorage.getItem('dms2_report_filters');
        if (savedFilters) {
            const filters = JSON.parse(savedFilters);
            
            for (let [key, value] of Object.entries(filters)) {
                const input = document.getElementById(key);
                if (input) {
                    input.value = value;
                }
            }
            
            showNotification('Filtros cargados', 'success');
        }
    } catch (error) {
        console.error('Error al cargar filtros:', error);
    }
}

// Configurar atajos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl+E para exportar CSV
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportData('csv');
    }
    
    // Ctrl+P para imprimir
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printReport();
    }
    
    // Ctrl+F para enfocar en filtros
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const firstInput = document.querySelector('.reports-filters input');
        if (firstInput) {
            firstInput.focus();
        }
    }
    
    // Escape para cerrar notificaciones
    if (e.key === 'Escape') {
        const notifications = document.querySelectorAll('.notification-toast');
        notifications.forEach(notif => notif.remove());
        
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.remove();
        }
    }
});

// Función para refrescar datos
function refreshData() {
    showLoadingState();
    
    // Simular actualización de datos
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Función para configurar auto-refresh
function setupAutoRefresh(interval = 300000) { // 5 minutos por defecto
    setInterval(refreshData, interval);
}

// Función para manejar errores de red
function handleNetworkError(error) {
    console.error('Error de red:', error);
    showNotification('Error de conexión. Verifique su conexión a internet.', 'error');
}

// Función para validar formularios
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Función para formatear números
function formatNumber(number, decimals = 0) {
    return new Intl.NumberFormat('es-ES', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(number);
}

// Función para formatear fechas
function formatDate(date, format = 'short') {
    const options = {
        short: { day: '2-digit', month: '2-digit', year: 'numeric' },
        long: { day: '2-digit', month: 'long', year: 'numeric' },
        time: { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    };
    
    return new Intl.DateTimeFormat('es-ES', options[format]).format(new Date(date));
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

// Función para crear elementos DOM de forma más fácil
function createElement(tag, className, innerHTML) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (innerHTML) element.innerHTML = innerHTML;
    return element;
}

// Función para obtener parámetros de URL
function getURLParams() {
    const params = {};
    const urlParams = new URLSearchParams(window.location.search);
    for (let [key, value] of urlParams.entries()) {
        params[key] = value;
    }
    return params;
}

// Función para actualizar URL sin recargar la página
function updateURL(params) {
    const url = new URL(window.location);
    
    for (let [key, value] of Object.entries(params)) {
        if (value) {
            url.searchParams.set(key, value);
        } else {
            url.searchParams.delete(key);
        }
    }
    
    window.history.replaceState({}, '', url);
}

// Función para copiar texto al portapapeles
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Texto copiado al portapapeles', 'success');
        });
    } else {
        // Fallback para navegadores más antiguos
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Texto copiado al portapapeles', 'success');
    }
}

// Función para descargar datos como archivo
function downloadAsFile(data, filename, type = 'text/plain') {
    const blob = new Blob([data], { type });
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

// Función para manejar la búsqueda en tiempo real
function setupLiveSearch() {
    const searchInput = document.getElementById('liveSearch');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', debounce(function() {
        const query = this.value.toLowerCase();
        const rows = document.querySelectorAll('.data-table tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
        
        // Actualizar contador de resultados
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        updateSearchResults(visibleRows.length, rows.length);
    }, 300));
}

// Actualizar contador de resultados de búsqueda
function updateSearchResults(visible, total) {
    let counter = document.getElementById('searchCounter');
    if (!counter) {
        counter = createElement('div', 'search-counter');
        const searchInput = document.getElementById('liveSearch');
        if (searchInput) {
            searchInput.parentNode.appendChild(counter);
        }
    }
    
    counter.textContent = `Mostrando ${visible} de ${total} registros`;
}

// Función para destacar texto en búsquedas
function highlightText(text, query) {
    if (!query) return text;
    
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

// Función para ordenar tablas
function setupTableSorting() {
    const headers = document.querySelectorAll('.data-table th[data-sort]');
    
    headers.forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            const column = this.dataset.sort;
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Determinar dirección de ordenamiento
            const isAsc = !this.classList.contains('sort-asc');
            
            // Remover clases de ordenamiento anteriores
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            
            // Agregar clase de ordenamiento actual
            this.classList.add(isAsc ? 'sort-asc' : 'sort-desc');
            
            // Ordenar filas
            rows.sort((a, b) => {
                const aValue = a.querySelector(`td:nth-child(${this.cellIndex + 1})`).textContent;
                const bValue = b.querySelector(`td:nth-child(${this.cellIndex + 1})`).textContent;
                
                if (isAsc) {
                    return aValue.localeCompare(bValue);
                } else {
                    return bValue.localeCompare(aValue);
                }
            });
            
            // Reordenar en el DOM
            rows.forEach(row => tbody.appendChild(row));
        });
    });
}

// Función para configurar lazy loading de imágenes
function setupLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Función para manejar formularios con AJAX
function setupAjaxForms() {
    const forms = document.querySelectorAll('form[data-ajax]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const url = this.action || window.location.href;
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Operación exitosa', 'success');
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    showNotification(data.message || 'Error en la operación', 'error');
                }
            })
            .catch(error => {
                handleNetworkError(error);
            });
        });
    });
}

// Función para configurar drag and drop
function setupDragAndDrop() {
    const dropZones = document.querySelectorAll('[data-drop-zone]');
    
    dropZones.forEach(zone => {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        
        zone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
        });
        
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileUpload(files[0]);
            }
        });
    });
}

// Función para manejar subida de archivos
function handleFileUpload(file) {
    const allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    
    if (!allowedTypes.includes(file.type)) {
        showNotification('Tipo de archivo no permitido', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    
    showLoadingState();
    
    fetch('upload_report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingState();
        if (data.success) {
            showNotification('Archivo procesado exitosamente', 'success');
            // Recargar datos o actualizar interfaz
            location.reload();
        } else {
            showNotification(data.message || 'Error al procesar archivo', 'error');
        }
    })
    .catch(error => {
        hideLoadingState();
        handleNetworkError(error);
    });
}

// Función para configurar gráficos con Chart.js
function setupCharts() {
    // Configuración global para Chart.js
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        Chart.defaults.color = '#64748b';
        Chart.defaults.plugins.tooltip.backgroundColor = '#1e293b';
        Chart.defaults.plugins.tooltip.titleColor = '#f1f5f9';
        Chart.defaults.plugins.tooltip.bodyColor = '#e2e8f0';
    }
}

// Función para configurar modo oscuro
function setupDarkMode() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    if (!darkModeToggle) return;
    
    const isDark = localStorage.getItem('darkMode') === 'true';
    
    if (isDark) {
        document.body.classList.add('dark-mode');
        darkModeToggle.checked = true;
    }
    
    darkModeToggle.addEventListener('change', function() {
        if (this.checked) {
            document.body.classList.add('dark-mode');
            localStorage.setItem('darkMode', 'true');
        } else {
            document.body.classList.remove('dark-mode');
            localStorage.setItem('darkMode', 'false');
        }
    });
}

// Función para configurar notificaciones push
function setupPushNotifications() {
    if ('Notification' in window && navigator.serviceWorker) {
        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }
}

// Función para mostrar notificación push
function showPushNotification(title, options = {}) {
    if (Notification.permission === 'granted') {
        new Notification(title, {
            icon: '/assets/images/icon-192x192.png',
            badge: '/assets/images/badge-72x72.png',
            ...options
        });
    }
}

// Función para configurar PWA
function setupPWA() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('Service Worker registrado:', registration);
            })
            .catch(error => {
                console.log('Error al registrar Service Worker:', error);
            });
    }
}

// Función para manejar instalación de PWA
function setupPWAInstall() {
    let deferredPrompt;
    
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        showInstallBanner();
    });
    
    function showInstallBanner() {
        const banner = createElement('div', 'install-banner', `
            <div class="install-content">
                <p>¿Instalar DMS2 en tu dispositivo?</p>
                <div class="install-actions">
                    <button id="installBtn" class="btn btn-primary">Instalar</button>
                    <button id="dismissBtn" class="btn btn-secondary">Ahora no</button>
                </div>
            </div>
        `);
        
        document.body.appendChild(banner);
        
        document.getElementById('installBtn').addEventListener('click', () => {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('Usuario aceptó la instalación');
                }
                deferredPrompt = null;
                banner.remove();
            });
        });
        
        document.getElementById('dismissBtn').addEventListener('click', () => {
            banner.remove();
        });
    }
}

// Inicializar funciones adicionales cuando sea necesario
document.addEventListener('DOMContentLoaded', function() {
    setupLiveSearch();
    setupTableSorting();
    setupLazyLoading();
    setupAjaxForms();
    setupDragAndDrop();
    setupCharts();
    setupDarkMode();
    setupPushNotifications();
    setupPWA();
    setupPWAInstall();
});

// Exportar funciones para uso global
window.ReportsModule = {
    exportData,
    printReport,
    showNotification,
    toggleSidebar,
    updateTime,
    formatNumber,
    formatDate,
    formatBytes,
    copyToClipboard,
    downloadAsFile,
    refreshData,
    showComingSoon
};