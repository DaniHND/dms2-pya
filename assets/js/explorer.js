/*
 * assets/js/explorer.js
 * JavaScript completo para el explorador de documentos
 */

class DocumentExplorer {
    constructor() {
        this.currentFolder = null;
        this.viewMode = 'grid';
        this.sortBy = 'date';
        this.sortOrder = 'desc';
        this.searchTerm = '';
        this.typeFilter = '';
        
        this.init();
    }
    
    init() {
        console.log('📁 Inicializando Explorador de Documentos');
        
        // Configurar eventos
        this.setupEventListeners();
        
        // Restaurar preferencias
        this.restorePreferences();
        
        // Actualizar tiempo
        this.startTimeUpdater();
        
        // Inicializar Feather Icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        console.log('✅ Explorador inicializado correctamente');
    }
    
    setupEventListeners() {
        // Delegación de eventos para botones de documentos
        document.addEventListener('click', (e) => {
            this.handleDocumentActions(e);
        });
        
        // Eventos de vista
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.changeView(btn.dataset.view);
            });
        });
        
        // Eventos de ordenación
        const sortSelect = document.getElementById('sortBy');
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                this.updateSort();
            });
        }
        
        // Botón de orden
        document.querySelectorAll('[onclick*="toggleSortOrder"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleSortOrder();
            });
        });
        
        // Botones de carpeta (crear, renombrar)
        document.querySelectorAll('.folder-action-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleFolderAction(btn.dataset.action);
            });
        });
        
        // Búsqueda en tiempo real (opcional)
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (e.target.value.length >= 3 || e.target.value.length === 0) {
                        this.updateSearch(e.target.value);
                    }
                }, 500);
            });
        }
        
        // Atajos de teclado
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
        
        // Responsive: toggle sidebar en móvil
        this.setupMobileNavigation();
    }
    
    handleDocumentActions(e) {
        // Ver documento
        if (e.target.closest('.view-btn') || e.target.closest('.document-preview')) {
            e.preventDefault();
            e.stopPropagation();
            
            let docId;
            if (e.target.closest('.view-btn')) {
                docId = e.target.closest('.view-btn').dataset.docId;
            } else {
                const card = e.target.closest('.document-card');
                docId = card ? card.dataset.id : null;
            }
            
            if (docId) {
                this.viewDocument(docId);
            }
            return;
        }
        
        // Descargar documento
        if (e.target.closest('.download-btn')) {
            e.preventDefault();
            e.stopPropagation();
            
            const btn = e.target.closest('.download-btn');
            if (btn.disabled || !window.canDownload) {
                this.showNotification('No tienes permisos para descargar', 'error');
                return;
            }
            
            const docId = btn.dataset.docId;
            if (docId) {
                this.downloadDocument(docId);
            }
            return;
        }
        
        // Eliminar documento
        if (e.target.closest('.delete-btn')) {
            e.preventDefault();
            e.stopPropagation();
            
            const docId = e.target.closest('.delete-btn').dataset.docId;
            if (docId) {
                const card = e.target.closest('.document-card');
                const docName = card ? (card.querySelector('.document-name')?.textContent || 'este documento') : 'este documento';
                
                this.deleteDocument(docId, docName);
            }
            return;
        }
    }
    
    viewDocument(documentId) {
        console.log('👁️ Ver documento:', documentId);
        this.showNotification('Abriendo documento...', 'info', 1000);
        window.location.href = `view.php?id=${documentId}`;
    }
    
    downloadDocument(documentId) {
        console.log('⬇️ Descargar documento:', documentId);
        
        try {
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
            
            this.showNotification('Iniciando descarga...', 'success', 2000);
            form.submit();
            
            // Limpiar después de un momento
            setTimeout(() => {
                if (document.body.contains(form)) {
                    document.body.removeChild(form);
                }
            }, 3000);
            
        } catch (error) {
            console.error('Error al descargar:', error);
            this.showNotification('Error al iniciar descarga', 'error');
        }
    }
    
    deleteDocument(documentId, documentName) {
        console.log('🗑️ Eliminar documento:', documentId, documentName);
        
        // Confirmación con información detallada
        const confirmMessage = `¿Eliminar documento?

📄 ${documentName}

⚠️ Esta acción no se puede deshacer.
El documento será movido a la papelera.`;
        
        if (!confirm(confirmMessage)) {
            console.log('❌ Eliminación cancelada por el usuario');
            return;
        }
        
        // Segunda confirmación
        if (!confirm('¿Está completamente seguro?\n\nEsta es la última oportunidad para cancelar.')) {
            console.log('❌ Eliminación cancelada en segunda confirmación');
            return;
        }
        
        try {
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
            
            this.showNotification('Eliminando documento...', 'warning', 2000);
            form.submit();
            
        } catch (error) {
            console.error('Error al eliminar:', error);
            this.showNotification('Error al eliminar documento', 'error');
        }
    }
    
    changeView(viewType) {
        console.log('👁️ Cambiar vista a:', viewType);
        
        // Actualizar botones
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === viewType);
        });
        
        // Cambiar clase del contenedor
        const container = document.getElementById('documentsContainer');
        if (container) {
            container.className = viewType === 'grid' ? 'documents-grid' : 'documents-list';
        }
        
        // Guardar preferencia
        this.viewMode = viewType;
        this.savePreferences();
        
        this.showNotification(`Vista cambiada a ${viewType === 'grid' ? 'cuadros' : 'lista'}`, 'info', 1500);
    }
    
    updateSort() {
        const sortSelect = document.getElementById('sortBy');
        if (!sortSelect) return;
        
        const sortBy = sortSelect.value;
        const url = new URL(window.location);
        
        url.searchParams.set('sort', sortBy);
        
        // Establecer orden por defecto según tipo de ordenación
        if (!url.searchParams.has('order')) {
            const defaultOrder = sortBy === 'date' ? 'desc' : 'asc';
            url.searchParams.set('order', defaultOrder);
        }
        
        console.log('📊 Actualizando ordenación:', sortBy);
        this.showNotification(`Ordenando por ${this.getSortDisplayName(sortBy)}...`, 'info', 1500);
        
        window.location.href = url.toString();
    }
    
    toggleSortOrder() {
        const url = new URL(window.location);
        const currentOrder = url.searchParams.get('order') || 'desc';
        const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
        
        url.searchParams.set('order', newOrder);
        
        console.log('🔄 Cambiando orden a:', newOrder);
        this.showNotification(`Orden ${newOrder === 'asc' ? 'ascendente' : 'descendente'}`, 'info', 1500);
        
        window.location.href = url.toString();
    }
    
    updateSearch(searchTerm) {
        console.log('🔍 Buscar:', searchTerm);
        
        const url = new URL(window.location);
        
        if (searchTerm && searchTerm.trim().length > 0) {
            url.searchParams.set('search', searchTerm.trim());
        } else {
            url.searchParams.delete('search');
        }
        
        // Opcional: navegar automáticamente
        // window.location.href = url.toString();
    }
    
    handleFolderAction(action) {
        switch (action) {
            case 'create-company':
                this.showCreateFolderModal('company');
                break;
            case 'create-department':
                this.showCreateFolderModal('department');
                break;
            case 'rename-folder':
                this.showRenameFolderModal();
                break;
            default:
                console.log('Acción de carpeta no reconocida:', action);
        }
    }
    
    showCreateFolderModal(type) {
        const modalHtml = `
            <div class="modal-overlay" id="createFolderModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">
                            <i data-feather="${type === 'company' ? 'building' : 'folder'}"></i>
                            Crear ${type === 'company' ? 'Empresa' : 'Departamento'}
                        </h3>
                        <button class="modal-close" onclick="explorer.closeModal('createFolderModal')">
                            <i data-feather="x"></i>
                        </button>
                    </div>
                    
                    <form id="createFolderForm" onsubmit="explorer.submitCreateFolder(event, '${type}')">
                        ${type === 'department' ? `
                        <div class="form-group">
                            <label class="form-label">Empresa</label>
                            <select name="company_id" class="form-control" required id="companySelect">
                                <option value="">Cargando empresas...</option>
                            </select>
                        </div>
                        ` : ''}
                        
                        <div class="form-group">
                            <label class="form-label">Nombre ${type === 'company' ? 'de la empresa' : 'del departamento'}</label>
                            <input type="text" name="name" class="form-control" required 
                                   placeholder="Ingrese el nombre..." maxlength="255">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Descripción (opcional)</label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="Descripción detallada..."></textarea>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" 
                                    onclick="explorer.closeModal('createFolderModal')">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="plus"></i>
                                Crear ${type === 'company' ? 'Empresa' : 'Departamento'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Si es departamento, cargar empresas
        if (type === 'department') {
            this.loadCompaniesForSelect();
        }
        
        // Inicializar Feather Icons en el modal
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        // Focus en el primer input
        setTimeout(() => {
            const firstInput = document.querySelector('#createFolderModal input[name="name"]');
            if (firstInput) firstInput.focus();
        }, 100);
    }
    
    async loadCompaniesForSelect() {
        try {
            const response = await fetch('folder_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_companies'
            });
            
            const data = await response.json();
            const select = document.getElementById('companySelect');
            
            if (data.success && select) {
                select.innerHTML = '<option value="">Seleccione una empresa</option>';
                data.companies.forEach(company => {
                    select.innerHTML += `<option value="${company.id}">${company.name}</option>`;
                });
            } else {
                select.innerHTML = '<option value="">Error cargando empresas</option>';
            }
        } catch (error) {
            console.error('Error cargando empresas:', error);
            const select = document.getElementById('companySelect');
            if (select) {
                select.innerHTML = '<option value="">Error de conexión</option>';
            }
        }
    }
    
    async submitCreateFolder(event, type) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        // Agregar acción según tipo
        formData.append('action', type === 'company' ? 'create_company' : 'create_department');
        
        try {
            // Mostrar indicador de carga
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i data-feather="loader" class="animate-spin"></i> Creando...';
            submitBtn.disabled = true;
            
            const response = await fetch('folder_actions.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification(data.message, 'success');
                this.closeModal('createFolderModal');
                
                // Recargar página para mostrar la nueva carpeta
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                this.showNotification(data.message || 'Error al crear carpeta', 'error');
            }
            
        } catch (error) {
            console.error('Error creando carpeta:', error);
            this.showNotification('Error de conexión', 'error');
        } finally {
            // Restaurar botón
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }
    
    showRenameFolderModal() {
        // Esta función se implementaría para renombrar carpetas existentes
        this.showNotification('Funcionalidad de renombrar disponible próximamente', 'info');
    }
    
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.animation = 'modalSlideOut 0.3s ease forwards';
            setTimeout(() => {
                modal.remove();
            }, 300);
        }
    }
    
    handleKeyboardShortcuts(e) {
        // Ctrl+F para buscar
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Escape para cerrar modales
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal-overlay');
            modals.forEach(modal => modal.remove());
        }
        
        // F5 para actualizar
        if (e.key === 'F5') {
            e.preventDefault();
            this.showNotification('Actualizando explorador...', 'info', 1000);
            setTimeout(() => window.location.reload(), 500);
        }
        
        // Ctrl+N para nuevo documento (si tiene permisos)
        if (e.ctrlKey && e.key === 'n' && window.canCreate) {
            e.preventDefault();
            window.location.href = 'upload.php';
        }
    }
    
    setupMobileNavigation() {
        // Crear botón de hamburguesa para móvil si no existe
        if (window.innerWidth <= 768) {
            const header = document.querySelector('.header-left');
            if (header && !document.querySelector('.mobile-nav-toggle')) {
                const toggleBtn = document.createElement('button');
                toggleBtn.className = 'mobile-nav-toggle btn-icon';
                toggleBtn.innerHTML = '<i data-feather="menu"></i>';
                toggleBtn.addEventListener('click', () => {
                    const nav = document.querySelector('.explorer-nav');
                    if (nav) {
                        nav.classList.toggle('active');
                    }
                });
                
                header.insertBefore(toggleBtn, header.firstChild);
                if (typeof feather !== 'undefined') feather.replace();
            }
        }
        
        // Cerrar navegación al hacer clic fuera en móvil
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                const nav = document.querySelector('.explorer-nav');
                const toggle = document.querySelector('.mobile-nav-toggle');
                
                if (nav && nav.classList.contains('active') && 
                    !nav.contains(e.target) && !toggle.contains(e.target)) {
                    nav.classList.remove('active');
                }
            }
        });
    }
    
    startTimeUpdater() {
        const updateTime = () => {
            const now = new Date();
            const timeString = now.toLocaleString('es-ES', {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
            
            const element = document.getElementById('currentDateTime');
            if (element) {
                element.textContent = timeString;
            }
        };
        
        updateTime();
        setInterval(updateTime, 1000);
    }
    
    restorePreferences() {
        try {
            const savedView = localStorage.getItem('explorer_view_preference');
            if (savedView && ['grid', 'list'].includes(savedView)) {
                this.changeView(savedView);
            }
        } catch (e) {
            console.log('No se pudieron restaurar las preferencias');
        }
    }
    
    savePreferences() {
        try {
            localStorage.setItem('explorer_view_preference', this.viewMode);
        } catch (e) {
            console.log('No se pudieron guardar las preferencias');
        }
    }
    
    getSortDisplayName(sortBy) {
        const names = {
            'name': 'nombre',
            'date': 'fecha',
            'size': 'tamaño',
            'type': 'tipo'
        };
        return names[sortBy] || sortBy;
    }
    
    showNotification(message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        // Icono según tipo
        const icons = {
            success: 'check-circle',
            error: 'alert-circle',
            warning: 'alert-triangle',
            info: 'info'
        };
        
        notification.innerHTML = `
            <i data-feather="${icons[type] || 'info'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        // Inicializar icono
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        // Auto-remove después del duration
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.transform = 'translateX(400px)';
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, duration);
    }
    
    // Funciones de utilidad
    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // API para uso externo
    refresh() {
        window.location.reload();
    }
    
    navigateToFolder(folderPath) {
        const url = new URL(window.location);
        url.searchParams.set('folder', folderPath);
        window.location.href = url.toString();
    }
    
    clearFilters() {
        const url = new URL(window.location);
        url.searchParams.delete('search');
        url.searchParams.delete('type');
        url.searchParams.delete('folder');
        window.location.href = url.toString();
    }
    
    // Funciones para compatibilidad con código existente
    sortDocuments() {
        this.updateSort();
    }
    
    toggleSortOrder() {
        this.toggleSortOrder();
    }
}

// CSS adicional para animaciones
const additionalCSS = `
<style>
@keyframes modalSlideOut {
    from {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
    to {
        transform: scale(0.9) translateY(-20px);
        opacity: 0;
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-primary, #1e293b);
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid var(--folder-border, #e2e8f0);
    border-radius: 6px;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: var(--explorer-primary, #8B4513);
    box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid var(--folder-border, #e2e8f0);
}

.btn {
    padding: 0.75rem 1.5rem;
    border: 1px solid transparent;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary {
    background: var(--explorer-primary, #8B4513);
    color: white;
    border-color: var(--explorer-primary, #8B4513);
}

.btn-primary:hover {
    background: var(--explorer-secondary, #D2B48C);
    border-color: var(--explorer-secondary, #D2B48C);
}

.btn-secondary {
    background: white;
    color: var(--text-secondary, #64748b);
    border-color: var(--folder-border, #e2e8f0);
}

.btn-secondary:hover {
    background: var(--folder-hover, #f1f5f9);
    color: var(--text-primary, #1e293b);
}

.mobile-nav-toggle {
    display: none;
    margin-right: 1rem;
}

@media (max-width: 768px) {
    .mobile-nav-toggle {
        display: flex;
    }
}
</style>
`;

// Inyectar CSS adicional
document.head.insertAdjacentHTML('beforeend', additionalCSS);

// Inicializar explorador cuando el DOM esté listo
let explorer;

document.addEventListener('DOMContentLoaded', () => {
    explorer = new DocumentExplorer();
    
    // Hacer disponible globalmente para compatibilidad
    window.explorer = explorer;
    
    // Funciones globales para compatibilidad con el HTML existente
    window.changeView = (viewType) => explorer.changeView(viewType);
    window.sortDocuments = () => explorer.updateSort();
    window.toggleSortOrder = () => explorer.toggleSortOrder();
});

// Exportar para uso como módulo si es necesario
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DocumentExplorer;
}