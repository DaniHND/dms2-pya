/* ============================================================================
   COMPANIES.JS - JAVASCRIPT PARA MÓDULO DE EMPRESAS
   Basado en la estructura exitosa del módulo de usuarios
   ============================================================================ */

// Variables globales
let currentModal = null;
let currentPage = 1;
let totalPages = 1;
let currentFilters = {};

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Iniciando módulo de empresas...');
    
    // Inicializar todos los componentes
    initializeCompanies();
    initializeEventListeners();
    
    console.log('✅ Módulo de empresas inicializado correctamente');
});

function initializeCompanies() {
    console.log('⚙️ Inicializando componentes del módulo...');
    
    // Inicializar filtros
    initializeFilters();
}

// ============================================================================
// EVENT LISTENERS PRINCIPALES
// ============================================================================

function initializeEventListeners() {
    console.log('🎯 Configurando eventos principales...');
    
    // Evento para cerrar modales con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && currentModal) {
            closeModal();
        }
    });
    
    // Eventos para filtros en tiempo real
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            console.log('🔍 Búsqueda:', this.value);
            applyFilters();
        }, 300));
    }
    
    // Eventos para selects de filtros
    const filterSelects = document.querySelectorAll('.filters-section select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            console.log('🔄 Filtro cambiado:', this.name, '=', this.value);
            applyFilters();
        });
    });
}

// ============================================================================
// FUNCIONES DE MODAL
// ============================================================================

function openCreateCompanyModal() {
    console.log('🏢 Abriendo modal de crear empresa...');
    
    const modalHTML = `
        <div class="modal active" id="createCompanyModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Crear Nueva Empresa</h3>
                    <button type="button" class="modal-close" onclick="closeCreateCompanyModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="createCompanyForm" class="modal-form" onsubmit="handleCreateCompany(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Nombre de Empresa *</label>
                                <input type="text" id="name" name="name" class="form-input" required placeholder="Ej: Empresa ABC">
                            </div>
                            <div class="form-group">
                                <label for="description">Descripción</label>
                                <input type="text" id="description" name="description" class="form-input" placeholder="Breve descripción">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email de Contacto</label>
                                <input type="email" id="email" name="email" class="form-input" placeholder="contacto@empresa.com">
                            </div>
                            <div class="form-group">
                                <label for="phone">Teléfono</label>
                                <input type="text" id="phone" name="phone" class="form-input" placeholder="(000) 000-0000">
                            </div>
                        </div>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label for="address">Dirección</label>
                                <textarea id="address" name="address" class="form-input" rows="3" placeholder="Dirección completa de la empresa"></textarea>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeCreateCompanyModal()">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="briefcase"></i>
                                Crear Empresa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    currentModal = document.getElementById('createCompanyModal');
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    console.log('✅ Modal de crear empresa abierto');
}

function closeCreateCompanyModal() {
    console.log('❌ Cerrando modal de crear empresa...');
    const modal = document.getElementById('createCompanyModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            currentModal = null;
        }, 300);
    }
}

function closeModal() {
    if (currentModal) {
        currentModal.classList.remove('active');
        setTimeout(() => {
            if (currentModal && currentModal.parentNode) {
                currentModal.remove();
            }
            currentModal = null;
        }, 300);
    }
}

// ============================================================================
// MANEJO DE FORMULARIOS
// ============================================================================

function handleCreateCompany(event) {
    event.preventDefault();
    console.log('📝 Procesando creación de empresa...');
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validar nombre requerido
    const name = formData.get('name');
    if (!name || name.trim().length < 2) {
        alert('El nombre de la empresa debe tener al menos 2 caracteres');
        return;
    }
    
    // Mostrar estado de carga
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Creando...';
    submitBtn.disabled = true;
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Enviar datos
    fetch('actions/create_company.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('📥 Respuesta del servidor:', data);
        
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        if (data.success) {
            alert('Empresa creada exitosamente');
            closeCreateCompanyModal();
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Error al crear empresa'));
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        alert('Error de conexión');
    });
}

// ============================================================================
// ACCIONES DE EMPRESA
// ============================================================================

function showCompanyDetails(companyId) {
    console.log('👁️ Mostrando detalles de la empresa:', companyId);
    
    if (!companyId) {
        console.error('❌ ID de empresa no válido');
        return;
    }
    
    const loadingModal = createLoadingModal('Cargando detalles de la empresa...');
    document.body.appendChild(loadingModal);
    
    fetch(`actions/get_company_details.php?id=${companyId}`)
        .then(response => response.json())
        .then(data => {
            loadingModal.remove();
            
            if (data.success && data.company) {
                openCompanyDetailsModal(data.company);
            } else {
                alert('Error: ' + (data.message || 'No se pudieron cargar los detalles'));
            }
        })
        .catch(error => {
            console.error('❌ Error:', error);
            loadingModal.remove();
            alert('Error de conexión');
        });
}

function editCompany(companyId) {
    console.log('✏️ Editando empresa:', companyId);
    
    if (!companyId) {
        console.error('❌ ID de empresa no válido');
        return;
    }
    
    const loadingModal = createLoadingModal('Cargando datos de la empresa...');
    document.body.appendChild(loadingModal);
    
    fetch(`actions/get_company.php?id=${companyId}`)
        .then(response => response.json())
        .then(data => {
            loadingModal.remove();
            
            if (data.success && data.company) {
                openEditCompanyModal(data.company);
            } else {
                alert('Error: ' + (data.message || 'No se pudieron cargar los datos de la empresa'));
            }
        })
        .catch(error => {
            console.error('❌ Error:', error);
            loadingModal.remove();
            alert('Error de conexión');
        });
}

function toggleCompanyStatus(companyId, currentStatus) {
    console.log('🔄 Cambiando estado de la empresa:', companyId, 'Estado actual:', currentStatus);
    
    if (!companyId) {
        console.error('❌ ID de empresa no válido');
        return;
    }
    
    const action = currentStatus === 'active' ? 'desactivar' : 'activar';
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    
    if (!confirm(`¿Está seguro que desea ${action} esta empresa?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('company_id', companyId);
    formData.append('new_status', newStatus);
    
    fetch('actions/toggle_company_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('📥 Respuesta:', data);
        
        if (data.success) {
            alert(`Empresa ${action}ada exitosamente`);
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Error al cambiar el estado de la empresa'));
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        alert('Error de conexión');
    });
}

function openEditCompanyModal(company) {
    const modalHTML = `
        <div class="modal active" id="editCompanyModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Editar Empresa</h3>
                    <button type="button" class="modal-close" onclick="closeEditCompanyModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editCompanyForm" class="modal-form" onsubmit="handleEditCompany(event)">
                        <input type="hidden" name="company_id" value="${company.id}">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_name">Nombre de Empresa *</label>
                                <input type="text" id="edit_name" name="name" class="form-input" required 
                                       value="${company.name}" placeholder="Ej: Empresa ABC">
                            </div>
                            <div class="form-group">
                                <label for="edit_description">Descripción</label>
                                <input type="text" id="edit_description" name="description" class="form-input" 
                                       value="${company.description || ''}" placeholder="Breve descripción">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_email">Email de Contacto</label>
                                <input type="email" id="edit_email" name="email" class="form-input" 
                                       value="${company.email || ''}" placeholder="contacto@empresa.com">
                            </div>
                            <div class="form-group">
                                <label for="edit_phone">Teléfono</label>
                                <input type="text" id="edit_phone" name="phone" class="form-input" 
                                       value="${company.phone || ''}" placeholder="(000) 000-0000">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_status">Estado *</label>
                                <select id="edit_status" name="status" class="form-input" required>
                                    <option value="active" ${company.status === 'active' ? 'selected' : ''}>Activo</option>
                                    <option value="inactive" ${company.status === 'inactive' ? 'selected' : ''}>Inactivo</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label for="edit_address">Dirección</label>
                                <textarea id="edit_address" name="address" class="form-input" rows="3" 
                                          placeholder="Dirección completa de la empresa">${company.address || ''}</textarea>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeEditCompanyModal()">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="save"></i>
                                Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    currentModal = document.getElementById('editCompanyModal');
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function closeEditCompanyModal() {
    const modal = document.getElementById('editCompanyModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            currentModal = null;
        }, 300);
    }
}

function handleEditCompany(event) {
    event.preventDefault();
    console.log('📝 Procesando edición de empresa...');
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validar nombre requerido
    const name = formData.get('name');
    if (!name || name.trim().length < 2) {
        alert('El nombre de la empresa debe tener al menos 2 caracteres');
        return;
    }
    
    // Mostrar estado de carga
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Guardando...';
    submitBtn.disabled = true;
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Enviar datos
    fetch('actions/update_company.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('📥 Respuesta del servidor:', data);
        
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        if (data.success) {
            alert('Empresa actualizada exitosamente');
            closeEditCompanyModal();
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Error al actualizar empresa'));
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        alert('Error de conexión');
    });
}

function openCompanyDetailsModal(company) {
    const modalHTML = `
        <div class="modal active" id="companyDetailsModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Detalles de la Empresa</h3>
                    <button type="button" class="modal-close" onclick="closeCompanyDetailsModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="company-details-grid">
                        <div class="detail-item">
                            <label>Nombre:</label>
                            <span>${company.name}</span>
                        </div>
                        <div class="detail-item">
                            <label>Estado:</label>
                            <span class="status-badge status-${company.status}">${company.status}</span>
                        </div>
                        <div class="detail-item">
                            <label>Descripción:</label>
                            <span>${company.description || 'Sin descripción'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Email:</label>
                            <span>${company.email || 'No especificado'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Teléfono:</label>
                            <span>${company.phone || 'No especificado'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Usuarios:</label>
                            <span>${company.user_count || 0}</span>
                        </div>
                        <div class="detail-item">
                            <label>Documentos:</label>
                            <span>${company.document_count || 0}</span>
                        </div>
                        <div class="detail-item">
                            <label>Creado:</label>
                            <span>${formatDate(company.created_at)}</span>
                        </div>
                        ${company.address ? `
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <label>Dirección:</label>
                            <span>${company.address}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCompanyDetailsModal()">
                        Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="editCompany(${company.id})">
                        <i data-feather="edit-2"></i>
                        Editar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    currentModal = document.getElementById('companyDetailsModal');
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function closeCompanyDetailsModal() {
    const modal = document.getElementById('companyDetailsModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            currentModal = null;
        }, 300);
    }
}

// ============================================================================
// FILTROS
// ============================================================================

function initializeFilters() {
    console.log('🔧 Inicializando filtros...');
    
    const filtersForm = document.querySelector('.filters-form');
    if (!filtersForm) return;
    
    const urlParams = new URLSearchParams(window.location.search);
    currentFilters = {
        search: urlParams.get('search') || '',
        status: urlParams.get('status') || ''
    };
    
    Object.keys(currentFilters).forEach(key => {
        const field = filtersForm.querySelector(`[name="${key}"]`);
        if (field && currentFilters[key]) {
            field.value = currentFilters[key];
        }
    });
}

function applyFilters() {
    console.log('📊 Aplicando filtros...');
    
    const filtersForm = document.querySelector('.filters-form');
    if (!filtersForm) return;
    
    const formData = new FormData(filtersForm);
    const params = new URLSearchParams();
    
    for (let [key, value] of formData.entries()) {
        if (value.trim()) {
            params.append(key, value.trim());
        }
    }
    
    if (currentPage > 1) {
        params.append('page', currentPage);
    }
    
    const newUrl = window.location.pathname + '?' + params.toString();
    window.location.href = newUrl;
}

function clearFilters() {
    console.log('🧹 Limpiando filtros...');
    
    const filtersForm = document.querySelector('.filters-form');
    if (filtersForm) {
        filtersForm.reset();
    }
    
    window.location.href = window.location.pathname;
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

function createLoadingModal(message = 'Cargando...') {
    const modalHTML = `
        <div class="modal active" id="loadingModal">
            <div class="modal-content" style="max-width: 300px; text-align: center;">
                <div class="modal-body">
                    <div class="loading-spinner" style="margin: 20px auto; width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top: 3px solid #8B4513; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <p>${message}</p>
                </div>
            </div>
        </div>
    `;
    
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = modalHTML;
    return tempDiv.firstElementChild;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

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

// ============================================================================
// EXPORTAR FUNCIONES GLOBALES
// ============================================================================

window.openCreateCompanyModal = openCreateCompanyModal;
window.closeCreateCompanyModal = closeCreateCompanyModal;
window.showCompanyDetails = showCompanyDetails;
window.editCompany = editCompany;
window.toggleCompanyStatus = toggleCompanyStatus;
window.handleCreateCompany = handleCreateCompany;
window.handleEditCompany = handleEditCompany;
window.openEditCompanyModal = openEditCompanyModal;
window.closeEditCompanyModal = closeEditCompanyModal;
window.applyFilters = applyFilters;
window.clearFilters = clearFilters;

console.log('✅ Todas las funciones de empresas exportadas correctamente');