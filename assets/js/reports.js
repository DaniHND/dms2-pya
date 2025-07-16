// assets/js/reports.js
// JavaScript mejorado para el módulo de reportes - DMS2

// Variables globales
let currentReportType = 'activity_log';
let currentFilters = {};
let selectedRows = new Set();
let isLoading = false;

// Configuración
const CONFIG = {
    AUTO_REFRESH_INTERVAL: 300000, // 5 minutos
    NOTIFICATION_DURATION: 5000,
    DEBOUNCE_DELAY: 300,
    MAX_SELECTED_ITEMS: 1000
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando módulo de reportes mejorado');
    initializeReports();
});

// Función principal de inicialización
function initializeReports() {
    try {
        // Configuración básica
        feather.replace();
        updateTime();
        setInterval(updateTime, 1000);
        
        // Configurar eventos
        setupEventListeners();
        setupAdvancedFilters();
        setupResponsiveBehavior();
        setupKeyboardShortcuts();
        
        // Inicializar funcionalidades
        initializeTooltips();
        loadSavedFilters();
        setupAutoRefresh();
        
        // Verificar datos iniciales
        validateTableData();
        
        console.log('✅ Módulo de reportes inicializado correctamente');
        showNotification('Módulo de reportes cargado', 'success');
        
    } catch (error) {
        console.error('❌ Error inicializando reportes:', error);
        showNotification('Error al cargar el módulo de reportes', 'error');
    }
}

// Configurar event listeners
function setupEventListeners() {
    // Filtros de fecha con validación
    const dateFromInput = document.getElementById('date_from');
    const dateToInput = document.getElementById('date_to');
    
    if (dateFromInput && dateToInput) {
        dateFromInput.addEventListener('change', debounce(validateDateRange, CONFIG.DEBOUNCE_DELAY));
        dateToInput.addEventListener('change', debounce(validateDateRange, CONFIG.DEBOUNCE_DELAY));
    }
    
    // Formulario de filtros
    const filterForm = document.querySelector('.reports-filters form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            applyFilters();
        });
        
        // Auto-aplicar filtros en cambios de select
        const selects = filterForm.querySelectorAll('select');
        selects.forEach(select => {
            select.addEventListener('change', debounce(() => {
                if (document.getElementById('auto-apply-filters')?.checked) {
                    applyFilters();
                }
            }, CONFIG.DEBOUNCE_DELAY));
        });
    }
    
    // Checkboxes de selección
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            toggleAllRows(this.checked);
        });
    }
    
    // Checkboxes individuales
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('row-select')) {
            handleRowSelection(e.target);
        }
    });
    
    // Botones de exportación
    const exportButtons = document.querySelectorAll('.export-btn');
    exportButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const format = this.textContent.toLowerCase().includes('csv') ? 'csv' :
                          this.textContent.toLowerCase().includes('excel') ? 'excel' :
                          this.textContent.toLowerCase().includes('pdf') ? 'pdf' : 'csv';
            exportData(format);
        });
    });
    
    // Hover en sección de exportación
    const exportSection = document.querySelector('.export-section');
    const exportOptions = document.getElementById('exportOptions');
    
    if (exportSection && exportOptions) {
        exportSection.addEventListener('mouseenter', () => {
            exportOptions.style.display = 'block';
        });
        
        exportSection.addEventListener('mouseleave', () => {
            setTimeout(() => {
                if (!exportOptions.matches(':hover')) {
                    exportOptions.style.display = 'none';
                }
            }, 100);
        });
    }
    
    // Clics en filas de tabla
    document.addEventListener('click', function(e) {
        const row = e.target.closest('tr[data-activity-id]');
        if (row && !e.target.matches('input[type="checkbox"]')) {
            toggleRowHighlight(row);
        }
    });
}

// Validar rango de fechas
function validateDateRange() {
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    if (!dateFrom || !dateTo || !dateFrom.value || !dateTo.value) return;
    
    const fromDate = new Date(dateFrom.value);
    const toDate = new Date(dateTo.value);
    const today = new Date();
    const oneYearAgo = new Date();
    oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);
    
    // Validar que 'desde' no sea mayor que 'hasta'
    if (fromDate > toDate) {
        showNotification('La fecha "Desde" no puede ser mayor que la fecha "Hasta"', 'warning');
        dateFrom.value = dateTo.value;
        return false;
    }
    
    // Validar que no sea más de 1 año atrás
    if (fromDate < oneYearAgo) {
        showNotification('No se pueden consultar datos de más de 1 año de antigüedad', 'warning');
        dateFrom.value = oneYearAgo.toISOString().split('T')[0];
        return false;
    }
    
    // Validar fechas futuras
    if (fromDate > today || toDate > today) {
        showNotification('No se pueden seleccionar fechas futuras', 'warning');
        if (fromDate > today) dateFrom.value = today.toISOString().split('T')[0];
        if (toDate > today) dateTo.value = today.toISOString().split('T')[0];
        return false;
    }
    
    // Calcular diferencia de días
    const diffTime = Math.abs(toDate - fromDate);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays > 90) {
        showNotification(`Período seleccionado: ${diffDays} días. Considere un rango menor para mejor rendimiento.`, 'info');
    }
    
    return true;
}

// Aplicar filtros
function applyFilters() {
    if (!validateDateRange()) return;
    
    showLoadingState('Aplicando filtros...');
    
    // Recopilar filtros
    const form = document.querySelector('.reports-filters form');
    const formData = new FormData(form);
    const filters = {};
    
    for (let [key, value] of formData.entries()) {
        if (value.trim()) {
            filters[key] = value.trim();
        }
    }
    
    // Guardar filtros actuales
    currentFilters = filters;
    saveFilters();
    
    // Construir URL con filtros
    const url = new URL(window.location.href);
    url.search = new URLSearchParams(filters).toString();
    
    // Navegar con filtros
    window.location.href = url.toString();
}

// Manejar selección de filas
function handleRowSelection(checkbox) {
    const activityId = checkbox.value;
    const row = checkbox.closest('tr');
    
    if (checkbox.checked) {
        selectedRows.add(activityId);
        row.classList.add('selected');
    } else {
        selectedRows.delete(activityId);
        row.classList.remove('selected');
    }
    
    updateSelectionUI();
    
    // Verificar límite de selección
    if (selectedRows.size > CONFIG.MAX_SELECTED_ITEMS) {
        showNotification(`Máximo ${CONFIG.MAX_SELECTED_ITEMS} elementos seleccionados`, 'warning');
        checkbox.checked = false;
        selectedRows.delete(activityId);
        row.classList.remove('selected');
    }
}

// Alternar todas las filas
function toggleAllRows(checked) {
    const rowCheckboxes = document.querySelectorAll('.row-select');
    const rows = document.querySelectorAll('tr[data-activity-id]');
    
    rowCheckboxes.forEach((cb, index) => {
        cb.checked = checked;
        const row = rows[index];
        
        if (checked) {
            selectedRows.add(cb.value);
            row?.classList.add('selected');
        } else {
            selectedRows.delete(cb.value);
            row?.classList.remove('selected');
        }
    });
    
    updateSelectionUI();
}

// Actualizar UI de selección
function updateSelectionUI() {
    const count = selectedRows.size;
    const selectAllCheckbox = document.getElementById('selectAll');
    const totalRows = document.querySelectorAll('.row-select').length;
    
    // Actualizar estado del checkbox principal
    if (selectAllCheckbox) {
        selectAllCheckbox.indeterminate = count > 0 && count < totalRows;
        selectAllCheckbox.checked = count === totalRows && totalRows > 0;
    }
    
    // Mostrar contador de selección
    updateSelectionCounter(count);
    
    // Habilitar/deshabilitar botones de exportación
    const exportButtons = document.querySelectorAll('.export-btn');
    const selectedOnlyCheckbox = document.getElementById('selectedOnly');
    
    if (selectedOnlyCheckbox?.checked) {
        exportButtons.forEach(btn => {
            btn.disabled = count === 0;
            btn.style.opacity = count === 0 ? '0.5' : '1';
        });
    }
}

// Mostrar contador de selección
function updateSelectionCounter(count) {
    let counter = document.getElementById('selection-counter');
    
    if (count > 0) {
        if (!counter) {
            counter = document.createElement('div');
            counter.id = 'selection-counter';
            counter.className = 'selection-counter';
            document.querySelector('.table-header').appendChild(counter);
        }
        counter.textContent = `${count} elemento${count > 1 ? 's' : ''} seleccionado${count > 1 ? 's' : ''}`;
    } else if (counter) {
        counter.remove();
    }
}

// Exportar datos
function exportData(format) {
    if (isLoading) {
        showNotification('Ya hay una exportación en proceso', 'warning');
        return;
    }
    
    const selectedOnly = document.getElementById('selectedOnly')?.checked;
    const includeFilters = document.getElementById('includeFilters')?.checked !== false;
    
    // Validar selección si es necesario
    if (selectedOnly && selectedRows.size === 0) {
        showNotification('No hay registros seleccionados para exportar', 'warning');
        return;
    }
    
    // Mostrar estado de carga
    showLoadingState(`Generando archivo ${format.toUpperCase()}...`);
    
    // Construir URL de exportación
    let url = `export.php?format=${format}&type=${currentReportType}`;
    
    if (includeFilters) {
        const params = new URLSearchParams(currentFilters);
        url += '&' + params.toString();
    }
    
    if (selectedOnly) {
        url += '&selected_ids=' + Array.from(selectedRows).join(',');
    }
    
    // Registrar actividad
    logExportActivity(format, selectedOnly ? selectedRows.size : 'all');
    
    // Abrir descarga
    const downloadLink = document.createElement('a');
    downloadLink.href = url;
    downloadLink.target = '_blank';
    downloadLink.download = `reporte_${format}_${new Date().toISOString().split('T')[0]}`;
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
    
    // Ocultar estado de carga después de un tiempo
    setTimeout(() => {
        hideLoadingState();
        showNotification(`Archivo ${format.toUpperCase()} generado correctamente`, 'success');
    }, 2000);
}

// Mostrar estado de carga
function showLoadingState(message = 'Cargando...') {
    isLoading = true;
    
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div style="text-align: center; color: white;">
                <div class="loading-spinner"></div>
                <div class="loading-text">${message}</div>
            </div>
        `;
        document.body.appendChild(overlay);
    } else {
        overlay.querySelector('.loading-text').textContent = message;
        overlay.style.display = 'flex';
    }
}

// Ocultar estado de carga
function hideLoadingState() {
    isLoading = false;
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

// Mostrar notificación
function showNotification(message, type = 'info', duration = CONFIG.NOTIFICATION_DURATION) {
    const notification = document.createElement('div');
    notification.className = `notification-toast ${type}`;
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i data-feather="${getNotificationIcon(type)}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; margin-left: auto;">
                <i data-feather="x"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    feather.replace();
    
    // Auto-remove
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, duration);
}

// Obtener icono de notificación
function getNotificationIcon(type) {
    const icons = {
        success: 'check-circle',
        error: 'alert-circle',
        warning: 'alert-triangle',
        info: 'info'
    };
    return icons[type] || 'info';
}

// Configurar filtros avanzados
function setupAdvancedFilters() {
    // Añadir auto-aplicación de filtros
    const filtersContainer = document.querySelector('.reports-filters');
    if (filtersContainer && !document.getElementById('auto-apply-filters')) {
        const autoApplyContainer = document.createElement('div');
        autoApplyContainer.style.marginTop = '1rem';
        autoApplyContainer.innerHTML = `
            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                <input type="checkbox" id="auto-apply-filters">
                Aplicar filtros automáticamente
            </label>
        `;
        filtersContainer.appendChild(autoApplyContainer);
    }
}

// Alternar filtros avanzados
function toggleAdvancedFilters() {
    const advancedFilters = document.getElementById('advancedFilters');
    const button = event.target.closest('button');
    
    if (advancedFilters) {
        const isVisible = advancedFilters.style.display !== 'none';
        advancedFilters.style.display = isVisible ? 'none' : 'block';
        
        // Actualizar texto del botón
        const icon = button.querySelector('i');
        const text = button.childNodes[button.childNodes.length - 1];
        
        if (isVisible) {
            text.textContent = ' Avanzado';
            icon.setAttribute('data-feather', 'sliders');
        } else {
            text.textContent = ' Ocultar';
            icon.setAttribute('data-feather', 'x');
        }
        
        feather.replace();
    }
}

// Configurar comportamiento responsive
function setupResponsiveBehavior() {
    let resizeTimer;
    
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            handleResponsiveChanges();
        }, 250);
    });
    
    // Ejecutar una vez al inicio
    handleResponsiveChanges();
}

function handleResponsiveChanges() {
    const isMobile = window.innerWidth <= 768;
    const sidebar = document.getElementById('sidebar');
    
    // Cerrar sidebar en móvil
    if (isMobile && sidebar) {
        sidebar.classList.remove('active');
    }
    
    // Ajustar tabla en móvil
    const tableContainer = document.querySelector('.table-container');
    if (tableContainer) {
        if (isMobile) {
            tableContainer.style.maxHeight = '50vh';
        } else {
            tableContainer.style.maxHeight = '70vh';
        }
    }
}

// Configurar atajos de teclado
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl+F - Enfocar filtros
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const firstInput = document.querySelector('.reports-filters input[type="date"]');
            if (firstInput) {
                firstInput.focus();
                firstInput.select();
            }
        }
        
        // Ctrl+E - Exportar CSV
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            exportData('csv');
        }
        
        // Ctrl+P - Imprimir
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printReport();
        }
        
        // Ctrl+R - Refrescar
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            refreshData();
        }
        
        // Ctrl+A - Seleccionar todo
        if (e.ctrlKey && e.key === 'a' && e.target.closest('.reports-table')) {
            e.preventDefault();
            selectAllRows();
        }
        
        // Escape - Limpiar selección y cerrar modales
        if (e.key === 'Escape') {
            clearSelection();
            hideLoadingState();
            
            // Cerrar notificaciones
            document.querySelectorAll('.notification-toast').forEach(notif => {
                notif.remove();
            });
        }
        
        // Enter en filtros - Aplicar filtros
        if (e.key === 'Enter' && e.target.closest('.reports-filters')) {
            e.preventDefault();
            applyFilters();
        }
    });
}

// Inicializar tooltips
function initializeTooltips() {
    const elementsWithTooltips = document.querySelectorAll('[title]');
    
    elementsWithTooltips.forEach(element => {
        const originalTitle = element.title;
        element.removeAttribute('title');
        element.setAttribute('data-tooltip', originalTitle);
        element.classList.add('tooltip');
    });
}

// Guardar filtros
function saveFilters() {
    try {
        localStorage.setItem('dms2_report_filters', JSON.stringify(currentFilters));
        localStorage.setItem('dms2_report_filters_timestamp', Date.now().toString());
    } catch (error) {
        console.warn('No se pudieron guardar los filtros:', error);
    }
}

// Cargar filtros guardados
function loadSavedFilters() {
    try {
        const savedFilters = localStorage.getItem('dms2_report_filters');
        const timestamp = localStorage.getItem('dms2_report_filters_timestamp');
        
        // Solo cargar si no son muy antiguos (24 horas)
        if (savedFilters && timestamp) {
            const age = Date.now() - parseInt(timestamp);
            const maxAge = 24 * 60 * 60 * 1000; // 24 horas
            
            if (age < maxAge) {
                const filters = JSON.parse(savedFilters);
                
                for (let [key, value] of Object.entries(filters)) {
                    const input = document.getElementById(key);
                    if (input && !input.value) { // Solo aplicar si el campo está vacío
                        input.value = value;
                    }
                }
                
                console.log('Filtros cargados desde localStorage');
            }
        }
    } catch (error) {
        console.warn('Error al cargar filtros guardados:', error);
    }
}

// Configurar auto-refresh
function setupAutoRefresh() {
    const autoRefreshCheckbox = document.getElementById('auto-refresh');
    
    if (!autoRefreshCheckbox) {
        // Crear checkbox de auto-refresh
        const headerActions = document.querySelector('.header-actions');
        if (headerActions) {
            const autoRefreshContainer = document.createElement('div');
            autoRefreshContainer.style.marginRight = '1rem';
            autoRefreshContainer.innerHTML = `
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: #6c757d;">
                    <input type="checkbox" id="auto-refresh" style="transform: scale(0.8);">
                    Auto-actualizar
                </label>
            `;
            headerActions.insertBefore(autoRefreshContainer, headerActions.firstChild);
        }
    }
    
    // Configurar intervalos
    let refreshInterval;
    
    document.addEventListener('change', function(e) {
        if (e.target.id === 'auto-refresh') {
            if (e.target.checked) {
                refreshInterval = setInterval(refreshData, CONFIG.AUTO_REFRESH_INTERVAL);
                showNotification('Auto-actualización activada (cada 5 minutos)', 'info');
            } else {
                clearInterval(refreshInterval);
                showNotification('Auto-actualización desactivada', 'info');
            }
        }
    });
}

// Validar datos de tabla
function validateTableData() {
    const rows = document.querySelectorAll('.data-table tbody tr[data-activity-id]');
    const emptyState = document.querySelector('.empty-state');
    
    if (rows.length === 0 && !emptyState) {
        console.warn('No se encontraron datos en la tabla');
    }
    
    // Validar integridad de datos
    rows.forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        if (cells.length < 6) {
            console.warn(`Fila ${index + 1} tiene datos incompletos`);
        }
    });
}

// Funciones de utilidad
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

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar && window.innerWidth <= 768) {
        sidebar.classList.toggle('active');
    }
}

function selectAllRows() {
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
        toggleAllRows(true);
    }
}

function clearSelection() {
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        toggleAllRows(false);
    }
}

function clearFilters() {
    if (confirm('¿Está seguro que desea limpiar todos los filtros?')) {
        localStorage.removeItem('dms2_report_filters');
        localStorage.removeItem('dms2_report_filters_timestamp');
        window.location.href = window.location.pathname;
    }
}

function refreshData() {
    showLoadingState('Actualizando datos...');
    
    // Agregar timestamp para evitar cache
    const url = new URL(window.location.href);
    url.searchParams.set('_refresh', Date.now().toString());
    
    // Guardar estado actual
    const currentScroll = window.pageYOffset;
    
    // Recargar página
    window.location.href = url.toString();
}

function printReport() {
    // Preparar para impresión
    document.body.classList.add('printing');
    
    // Ocultar elementos no imprimibles
    const nonPrintElements = document.querySelectorAll('.no-print, .export-section, .pagination');
    nonPrintElements.forEach(el => el.style.display = 'none');
    
    // Imprimir
    window.print();
    
    // Restaurar después de imprimir
    setTimeout(() => {
        document.body.classList.remove('printing');
        nonPrintElements.forEach(el => el.style.display = '');
    }, 1000);
}

function toggleRowHighlight(row) {
    row.classList.toggle('highlighted');
    setTimeout(() => row.classList.remove('highlighted'), 2000);
}

function logExportActivity(format, recordCount) {
    // Log de actividad de exportación (si existe la función)
    if (typeof logActivity === 'function') {
        logActivity('export_report', `Exportó reporte en formato ${format} (${recordCount} registros)`);
    }
}

// Funciones expuestas globalmente para compatibilidad
window.exportData = exportData;
window.toggleSidebar = toggleSidebar;
window.toggleAdvancedFilters = toggleAdvancedFilters;
window.selectAllRows = selectAllRows;
window.clearSelection = clearSelection;
window.clearFilters = clearFilters;
window.refreshData = refreshData;
window.printReport = printReport;
window.showNotification = showNotification;

// Event listeners adicionales para compatibilidad
document.addEventListener('click', function(e) {
    // Manejar clics en botones de acción
    if (e.target.matches('[onclick*="exportData"]')) {
        e.preventDefault();
        const onclick = e.target.getAttribute('onclick');
        const format = onclick.match(/'([^']+)'/)?.[1] || 'csv';
        exportData(format);
    }
    
    if (e.target.matches('[onclick*="toggleAdvancedFilters"]')) {
        e.preventDefault();
        toggleAdvancedFilters();
    }
    
    if (e.target.matches('[onclick*="selectAllRows"]')) {
        e.preventDefault();
        selectAllRows();
    }
    
    if (e.target.matches('[onclick*="clearSelection"]')) {
        e.preventDefault();
        clearSelection();
    }
    
    if (e.target.matches('[onclick*="refreshData"]')) {
        e.preventDefault();
        refreshData();
    }
    
    if (e.target.matches('[onclick*="printReport"]')) {
        e.preventDefault();
        printReport();
    }
});

// Manejo de errores globales
window.addEventListener('error', function(e) {
    console.error('Error en módulo de reportes:', e.error);
    showNotification('Se produjo un error. Por favor, recargue la página.', 'error');
});

// Manejo de errores de red
window.addEventListener('online', function() {
    showNotification('Conexión restablecida', 'success');
});

window.addEventListener('offline', function() {
    showNotification('Sin conexión a internet', 'warning');
});

// Cleanup al salir de la página
window.addEventListener('beforeunload', function() {
    // Limpiar intervalos y timeouts
    clearInterval(window.refreshInterval);
    
    // Guardar estado si es necesario
    if (selectedRows.size > 0) {
        sessionStorage.setItem('dms2_selected_rows', JSON.stringify(Array.from(selectedRows)));
    }
});

// Restaurar selección al cargar
window.addEventListener('load', function() {
    try {
        const savedSelection = sessionStorage.getItem('dms2_selected_rows');
        if (savedSelection) {
            const ids = JSON.parse(savedSelection);
            ids.forEach(id => {
                const checkbox = document.querySelector(`input[value="${id}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    handleRowSelection(checkbox);
                }
            });
            sessionStorage.removeItem('dms2_selected_rows');
        }
    } catch (error) {
        console.warn('Error al restaurar selección:', error);
    }
});

console.log('📊 Módulo de reportes JavaScript cargado correctamente');