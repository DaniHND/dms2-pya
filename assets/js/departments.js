// assets/js/departments.js
// JavaScript para el módulo de departamentos - DMS2 - VERSIÓN CORREGIDA

// Variables globales
let currentModal = null;
let currentDepartmentId = null;

// ==========================================
// INICIALIZACIÓN
// ==========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🏢 Módulo de departamentos cargado');
    initializeDepartmentsModule();
});

function initializeDepartmentsModule() {
    setupEventListeners();
    loadManagers(); // Cargar managers para los selects
}

function setupEventListeners() {
    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && currentModal) {
            closeCurrentModal();
        }
    });

    // Cerrar modal al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeCurrentModal();
        }
    });
}

// ==========================================
// FUNCIONES DE MODAL
// ==========================================

function openCreateDepartmentModal() {
    console.log('🆕 Abriendo modal crear departamento');
    
    currentDepartmentId = null;
    
    const modalHTML = `
        <div class="modal active" id="createDepartmentModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Nuevo Departamento</h3>
                    <button type="button" class="modal-close" onclick="closeCreateDepartmentModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="createDepartmentForm" class="modal-form" onsubmit="handleCreateDepartment(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Nombre del Departamento *</label>
                                <input type="text" id="name" name="name" class="form-input" required 
                                       placeholder="Ej: Recursos Humanos">
                            </div>
                            <div class="form-group">
                                <label for="company_id">Empresa *</label>
                                <select id="company_id" name="company_id" class="form-input" required>
                                    <option value="">Seleccionar empresa</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label for="description">Descripción</label>
                                <textarea id="description" name="description" class="form-input" rows="3"
                                          placeholder="Describe las funciones y responsabilidades del departamento..."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="manager_id">Manager/Jefe</label>
                                <select id="manager_id" name="manager_id" class="form-input">
                                    <option value="">Sin asignar</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Estado</label>
                                <select id="status" name="status" class="form-input">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeCreateDepartmentModal()">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="plus"></i>
                                Crear Departamento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    // Insertar modal en el DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    currentModal = document.getElementById('createDepartmentModal');
    
    // Cargar datos para los selects
    loadCompaniesForModal();
    loadManagersForModal();
    
    // Inicializar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    console.log('✅ Modal de crear departamento abierto');
}

function closeCreateDepartmentModal() {
    console.log('❌ Cerrando modal de crear departamento...');
    const modal = document.getElementById('createDepartmentModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            currentModal = null;
        }, 300);
    }
}

function editDepartment(departmentId) {
    console.log('✏️ Editando departamento:', departmentId);
    
    currentDepartmentId = departmentId;
    
    // Crear modal de edición
    const modalHTML = `
        <div class="modal active" id="editDepartmentModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Editar Departamento</h3>
                    <button type="button" class="modal-close" onclick="closeEditDepartmentModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="loading-state">
                        <i data-feather="loader"></i>
                        <p>Cargando datos del departamento...</p>
                    </div>
                    <form id="editDepartmentForm" class="modal-form" onsubmit="handleUpdateDepartment(event)" style="display: none;">
                        <input type="hidden" id="edit_department_id" name="department_id" value="${departmentId}">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_name">Nombre del Departamento *</label>
                                <input type="text" id="edit_name" name="name" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_company_id">Empresa *</label>
                                <select id="edit_company_id" name="company_id" class="form-input" required>
                                    <option value="">Seleccionar empresa</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label for="edit_description">Descripción</label>
                                <textarea id="edit_description" name="description" class="form-input" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_manager_id">Manager/Jefe</label>
                                <select id="edit_manager_id" name="manager_id" class="form-input">
                                    <option value="">Sin asignar</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_status">Estado</label>
                                <select id="edit_status" name="status" class="form-input">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeEditDepartmentModal()">
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
    
    // Insertar modal en el DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    currentModal = document.getElementById('editDepartmentModal');
    
    // Cargar datos del departamento
    loadDepartmentData(departmentId);
    
    // Inicializar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function closeEditDepartmentModal() {
    console.log('❌ Cerrando modal de editar departamento...');
    const modal = document.getElementById('editDepartmentModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            currentModal = null;
            currentDepartmentId = null;
        }, 300);
    }
}

function viewDepartmentDetails(departmentId) {
    console.log('👁️ Viendo detalles del departamento:', departmentId);
    
    const modalHTML = `
        <div class="modal active" id="viewDepartmentModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Detalles del Departamento</h3>
                    <button type="button" class="modal-close" onclick="closeViewDepartmentModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="loading-state">
                        <i data-feather="loader"></i>
                        <p>Cargando detalles del departamento...</p>
                    </div>
                    <div id="departmentDetailsContent" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeViewDepartmentModal()">
                        Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="editDepartmentFromView(${departmentId})">
                        <i data-feather="edit"></i>
                        Editar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Insertar modal en el DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    currentModal = document.getElementById('viewDepartmentModal');
    
    // Cargar detalles del departamento
    loadDepartmentDetails(departmentId);
    
    // Inicializar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function closeViewDepartmentModal() {
    console.log('❌ Cerrando modal de ver departamento...');
    const modal = document.getElementById('viewDepartmentModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            currentModal = null;
        }, 300);
    }
}

function editDepartmentFromView(departmentId) {
    closeViewDepartmentModal();
    setTimeout(() => {
        editDepartment(departmentId);
    }, 300);
}

function closeCurrentModal() {
    if (currentModal) {
        currentModal.classList.remove('active');
        setTimeout(() => {
            if (currentModal && currentModal.parentNode) {
                currentModal.remove();
                currentModal = null;
                currentDepartmentId = null;
            }
        }, 300);
    }
}

// ==========================================
// FUNCIONES DE DATOS
// ==========================================

function loadCompaniesForModal() {
    console.log('📋 Cargando empresas para modal...');
    
    fetch('actions/get_companies.php')
        .then(response => {
            console.log('🔍 Status de respuesta (empresas):', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Primero obtener como texto para debug
        })
        .then(text => {
            console.log('📄 Respuesta completa del servidor (empresas):');
            console.log(text);
            console.log('📄 Fin de respuesta');
            
            // Intentar extraer solo la parte JSON si hay HTML mezclado
            let jsonText = text;
            
            // Si hay HTML antes del JSON, intentar extraer solo el JSON
            const jsonStart = text.indexOf('{');
            const jsonEnd = text.lastIndexOf('}');
            
            if (jsonStart !== -1 && jsonEnd !== -1 && jsonStart < jsonEnd) {
                jsonText = text.substring(jsonStart, jsonEnd + 1);
                console.log('🔧 JSON extraído:', jsonText);
            }
            
            try {
                const data = JSON.parse(jsonText);
                if (data.success) {
                    const companySelects = document.querySelectorAll('#company_id, #edit_company_id');
                    companySelects.forEach(select => {
                        // Limpiar opciones existentes excepto la primera
                        const firstOption = select.firstElementChild;
                        select.innerHTML = '';
                        select.appendChild(firstOption);
                        
                        // Agregar empresas
                        data.companies.forEach(company => {
                            const option = document.createElement('option');
                            option.value = company.id;
                            option.textContent = company.name;
                            select.appendChild(option);
                        });
                    });
                    console.log('✅ Empresas cargadas correctamente:', data.companies.length);
                } else {
                    showNotification(data.message || 'Error al cargar empresas', 'error');
                }
            } catch (jsonError) {
                console.error('❌ Error parsing JSON:', jsonError);
                console.error('📄 Texto que intentamos parsear:', jsonText);
                showNotification('Error: Respuesta inválida del servidor (empresas)', 'error');
            }
        })
        .catch(error => {
            console.error('❌ Error cargando empresas:', error);
            showNotification('Error de conexión al cargar empresas', 'error');
        });
}

function loadManagersForModal() {
    console.log('👥 Cargando managers para modal...');
    
    fetch('actions/get_managers.php')
        .then(response => {
            console.log('🔍 Status de respuesta (managers):', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Primero obtener como texto para debug
        })
        .then(text => {
            console.log('📄 Respuesta completa del servidor (managers):');
            console.log(text);
            console.log('📄 Fin de respuesta');
            
            // Intentar extraer solo la parte JSON si hay HTML mezclado
            let jsonText = text;
            
            // Si hay HTML antes del JSON, intentar extraer solo el JSON
            const jsonStart = text.indexOf('{');
            const jsonEnd = text.lastIndexOf('}');
            
            if (jsonStart !== -1 && jsonEnd !== -1 && jsonStart < jsonEnd) {
                jsonText = text.substring(jsonStart, jsonEnd + 1);
                console.log('🔧 JSON extraído:', jsonText);
            }
            
            try {
                const data = JSON.parse(jsonText);
                if (data.success) {
                    const managerSelects = document.querySelectorAll('#manager_id, #edit_manager_id');
                    managerSelects.forEach(select => {
                        // Limpiar opciones existentes excepto la primera
                        const firstOption = select.firstElementChild;
                        select.innerHTML = '';
                        select.appendChild(firstOption);
                        
                        // Agregar managers
                        data.managers.forEach(manager => {
                            const option = document.createElement('option');
                            option.value = manager.id;
                            option.textContent = manager.name;
                            select.appendChild(option);
                        });
                    });
                    console.log('✅ Managers cargados correctamente:', data.managers.length);
                } else {
                    showNotification(data.message || 'Error al cargar managers', 'error');
                }
            } catch (jsonError) {
                console.error('❌ Error parsing JSON:', jsonError);
                console.error('📄 Texto que intentamos parsear:', jsonText);
                showNotification('Error: Respuesta inválida del servidor (managers)', 'error');
            }
        })
        .catch(error => {
            console.error('❌ Error cargando managers:', error);
            showNotification('Error de conexión al cargar managers', 'error');
        });
}

function loadDepartmentData(departmentId) {
    console.log('📝 Cargando datos del departamento:', departmentId);
    
    fetch(`actions/get_department_details.php?id=${departmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Cargar datos en el formulario
                const form = document.getElementById('editDepartmentForm');
                const loadingState = document.querySelector('#editDepartmentModal .loading-state');
                
                // Llenar formulario
                document.getElementById('edit_name').value = data.department.name || '';
                document.getElementById('edit_description').value = data.department.description || '';
                document.getElementById('edit_status').value = data.department.status || 'active';
                
                // Cargar selects primero, luego establecer valores
                loadCompaniesForModal();
                loadManagersForModal();
                
                // Establecer valores después de un pequeño delay para que se carguen los selects
                setTimeout(() => {
                    if (data.department.company_id) {
                        document.getElementById('edit_company_id').value = data.department.company_id;
                    }
                    if (data.department.manager_id) {
                        document.getElementById('edit_manager_id').value = data.department.manager_id;
                    }
                }, 500);
                
                // Mostrar formulario y ocultar loading
                loadingState.style.display = 'none';
                form.style.display = 'block';
                
                // Reinicializar iconos
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
                
            } else {
                showNotification(data.message || 'Error al cargar datos del departamento', 'error');
                closeEditDepartmentModal();
            }
        })
        .catch(error => {
            console.error('Error cargando datos del departamento:', error);
            showNotification('Error de conexión', 'error');
            closeEditDepartmentModal();
        });
}

function loadDepartmentDetails(departmentId) {
    console.log('📋 Cargando detalles del departamento:', departmentId);
    
    fetch(`actions/get_department_details.php?id=${departmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const loadingState = document.querySelector('#viewDepartmentModal .loading-state');
                const contentDiv = document.getElementById('departmentDetailsContent');
                
                // Crear HTML con los detalles
                const detailsHTML = `
                    <div class="department-details">
                        <div class="detail-section">
                            <h4>Información General</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Nombre:</label>
                                    <span>${data.department.name || 'N/A'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Empresa:</label>
                                    <span>${data.department.company_name || 'N/A'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Manager:</label>
                                    <span>${data.department.manager_name || 'Sin asignar'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Estado:</label>
                                    <span class="status-badge ${data.department.status === 'active' ? 'status-active' : 'status-inactive'}">
                                        ${data.department.status === 'active' ? 'Activo' : 'Inactivo'}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        ${data.department.description ? `
                        <div class="detail-section">
                            <h4>Descripción</h4>
                            <p>${data.department.description}</p>
                        </div>
                        ` : ''}
                        
                        <div class="detail-section">
                            <h4>Estadísticas</h4>
                            <div class="stats-mini-grid">
                                <div class="stat-mini">
                                    <div class="stat-number">${data.statistics.total_users}</div>
                                    <div class="stat-label">Total Usuarios</div>
                                </div>
                                <div class="stat-mini">
                                    <div class="stat-number">${data.statistics.active_users}</div>
                                    <div class="stat-label">Activos</div>
                                </div>
                                <div class="stat-mini">
                                    <div class="stat-number">${data.statistics.inactive_users}</div>
                                    <div class="stat-label">Inactivos</div>
                                </div>
                            </div>
                        </div>
                        
                        ${data.users && data.users.length > 0 ? `
                        <div class="detail-section">
                            <h4>Usuarios Asignados</h4>
                            <div class="users-list">
                                ${data.users.map(user => `
                                    <div class="user-item">
                                        <div class="user-info">
                                            <span class="user-name">${user.name}</span>
                                            <span class="user-email">${user.email}</span>
                                        </div>
                                        <span class="user-role">${user.role}</span>
                                        <span class="status-badge ${user.status === 'active' ? 'status-active' : 'status-inactive'}">
                                            ${user.status}
                                        </span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="detail-section">
                            <h4>Fechas</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Creado:</label>
                                    <span>${data.department.formatted_created_date || 'N/A'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Actualizado:</label>
                                    <span>${data.department.formatted_updated_date || 'N/A'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                contentDiv.innerHTML = detailsHTML;
                
                // Mostrar contenido y ocultar loading
                loadingState.style.display = 'none';
                contentDiv.style.display = 'block';
                
            } else {
                showNotification(data.message || 'Error al cargar detalles del departamento', 'error');
                closeViewDepartmentModal();
            }
        })
        .catch(error => {
            console.error('Error cargando detalles del departamento:', error);
            showNotification('Error de conexión', 'error');
            closeViewDepartmentModal();
        });
}

// ==========================================
// FUNCIONES DE ACCIONES
// ==========================================

function handleCreateDepartment(event) {
    event.preventDefault();
    console.log('💾 Creando nuevo departamento...');
    
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Deshabilitar botón
    submitBtn.disabled = true;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Creando...';
    
    fetch('actions/create_department.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Departamento creado correctamente', 'success');
            closeCreateDepartmentModal();
            // Recargar página para mostrar el nuevo departamento
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Error al crear departamento', 'error');
        }
    })
    .catch(error => {
        console.error('Error creando departamento:', error);
        showNotification('Error de conexión', 'error');
    })
    .finally(() => {
        // Rehabilitar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
}

function handleUpdateDepartment(event) {
    event.preventDefault();
    console.log('💾 Actualizando departamento...');
    
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Deshabilitar botón
    submitBtn.disabled = true;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Guardando...';
    
    fetch('actions/update_department.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Departamento actualizado correctamente', 'success');
            closeEditDepartmentModal();
            // Recargar página para mostrar los cambios
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Error al actualizar departamento', 'error');
        }
    })
    .catch(error => {
        console.error('Error actualizando departamento:', error);
        showNotification('Error de conexión', 'error');
    })
    .finally(() => {
        // Rehabilitar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
}

function toggleDepartmentStatus(departmentId, currentStatus) {
    const action = currentStatus === 'active' ? 'desactivar' : 'activar';
    const confirmMessage = `¿Está seguro que desea ${action} este departamento?`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    console.log(`🔄 Cambiando estado del departamento ${departmentId} de ${currentStatus}`);
    
    const formData = new FormData();
    formData.append('department_id', departmentId);
    formData.append('current_status', currentStatus);
    
    fetch('actions/toggle_department_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`Departamento ${action}do correctamente`, 'success');
            // Recargar página para mostrar los cambios
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message || `Error al ${action} departamento`, 'error');
        }
    })
    .catch(error => {
        console.error('Error cambiando estado del departamento:', error);
        showNotification('Error de conexión', 'error');
    });
}

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

function showNotification(message, type = 'info') {
    console.log(`📢 Notificación [${type}]: ${message}`);
    
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i data-feather="${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Agregar al DOM
    document.body.appendChild(notification);
    
    // Inicializar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Mostrar notificación
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Ocultar después de 5 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 5000);
}

// Función de compatibilidad para funciones externas
function loadManagers() {
    // Esta función puede ser llamada desde el exterior si es necesario
    loadManagersForModal();
}

console.log('✅ Módulo de departamentos JavaScript cargado correctamente');