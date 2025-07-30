// assets/js/departments.js - VERSIÓN CORREGIDA CON RUTAS
// JavaScript para el módulo de departamentos - DMS2 (RUTAS CORREGIDAS)

// Variables globales
let currentModal = null;
let currentDepartmentId = null;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    initializeDepartments();
});

function initializeDepartments() {
    setupEventListeners();
    setupFormValidation();
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function setupEventListeners() {
    // Formulario de departamento
    const departmentForm = document.getElementById('departmentForm');
    if (departmentForm) {
        departmentForm.addEventListener('submit', handleDepartmentSubmit);
    }

    // Select de empresa
    const companySelect = document.getElementById('departmentCompany');
    if (companySelect) {
        companySelect.addEventListener('change', handleCompanyChange);
    }

    // Filtros automáticos
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }

    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            this.form.submit();
        });
    }

    // Cerrar modales
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCurrentModal();
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeCurrentModal();
        }
    });
}

function setupFormValidation() {
    const inputs = document.querySelectorAll('#departmentForm input, #departmentForm select, #departmentForm textarea');
    
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            clearFieldError(this);
        });
    });
}

// ==========================================
// FUNCIONES PRINCIPALES DEL MÓDULO
// ==========================================

function openCreateDepartmentModal() {
    console.log('🆕 Abriendo modal crear departamento');
    
    currentDepartmentId = null;
    
    // Configurar modal
    const modalTitle = document.getElementById('departmentModalTitle');
    if (modalTitle) {
        modalTitle.textContent = 'Nuevo Departamento';
    }
    
    const saveBtn = document.getElementById('saveDepartmentBtn');
    if (saveBtn && saveBtn.querySelector('.btn-text')) {
        saveBtn.querySelector('.btn-text').textContent = 'Crear Departamento';
    }
    
    // Limpiar formulario
    const form = document.getElementById('departmentForm');
    if (form) {
        form.reset();
        clearFormErrors();
    }
    
    // Limpiar select de managers
    const managerSelect = document.getElementById('departmentManager');
    if (managerSelect) {
        managerSelect.innerHTML = '<option value="">Sin asignar</option>';
    }
    
    showModal('departmentModal');
}

function editDepartment(departmentId) {
    console.log('✏️ Editando departamento:', departmentId);
    
    if (!departmentId) {
        alert('ID de departamento inválido');
        return;
    }
    
    currentDepartmentId = departmentId;
    
    // Configurar modal
    const modalTitle = document.getElementById('departmentModalTitle');
    if (modalTitle) {
        modalTitle.textContent = 'Editar Departamento';
    }
    
    const saveBtn = document.getElementById('saveDepartmentBtn');
    if (saveBtn && saveBtn.querySelector('.btn-text')) {
        saveBtn.querySelector('.btn-text').textContent = 'Actualizar Departamento';
    }
    
    // Cargar datos y mostrar modal
    loadDepartmentData(departmentId);
    showModal('departmentModal');
}

function viewDepartmentDetails(departmentId) {
    console.log('👁️ Ver detalles departamento:', departmentId);
    
    if (!departmentId) {
        alert('ID de departamento inválido');
        return;
    }
    
    loadDepartmentDetails(departmentId);
    showModal('viewDepartmentModal');
}

function toggleDepartmentStatus(departmentId, currentStatus) {
    console.log('🔄 Toggle estado departamento:', departmentId, currentStatus);
    
    if (!departmentId || !currentStatus) {
        alert('Parámetros inválidos');
        return;
    }
    
    const action = currentStatus === 'active' ? 'desactivar' : 'activar';
    
    if (!confirm(`¿Está seguro que desea ${action} este departamento?`)) {
        return;
    }
    
    // Usar AJAX en lugar de formulario para mejor UX
    const formData = new FormData();
    formData.append('department_id', departmentId.toString());
    formData.append('current_status', currentStatus);
    
    fetch('actions/toggle_department_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('📊 Respuesta toggle:', data);
        
        if (data.success) {
            // Mostrar mensaje de éxito elegante
            if (typeof showNotification === 'function') {
                showNotification(data.message || 'Estado cambiado exitosamente', 'success');
            } else {
                alert(data.message || 'Estado cambiado exitosamente');
            }
            
            // Recargar la página para mostrar los cambios
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            alert(data.message || 'Error al cambiar el estado');
        }
    })
    .catch(error => {
        console.error('❌ Error toggle:', error);
        alert(`Error de conexión: ${error.message}`);
    });
}

// ==========================================
// CARGA DE DATOS
// ==========================================

async function loadDepartmentData(departmentId) {
    console.log('📡 Cargando datos departamento:', departmentId);
    
    try {
        showButtonLoading('saveDepartmentBtn');
        
        // Usar ruta relativa correcta
        const url = `actions/get_department_details.php?id=${departmentId}`;
        console.log('🌐 URL:', url);
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('❌ Respuesta no es JSON:', text);
            throw new Error('Respuesta del servidor no es JSON válido');
        }
        
        const data = await response.json();
        console.log('📊 Datos recibidos:', data);
        
        if (data.success) {
            const dept = data.department;
            
            // Llenar formulario con validación
            const fields = [
                { id: 'departmentId', value: dept.id },
                { id: 'departmentName', value: dept.name },
                { id: 'departmentDescription', value: dept.description || '' },
                { id: 'departmentCompany', value: dept.company_id },
                { id: 'departmentStatus', value: dept.status }
            ];
            
            fields.forEach(field => {
                const element = document.getElementById(field.id);
                if (element) {
                    element.value = field.value;
                } else {
                    console.warn(`⚠️ Campo ${field.id} no encontrado`);
                }
            });
            
            // Cargar managers para la empresa seleccionada
            if (dept.company_id) {
                await loadManagersForCompany(dept.company_id);
                
                // Seleccionar manager actual
                if (dept.manager_id) {
                    const managerSelect = document.getElementById('departmentManager');
                    if (managerSelect) {
                        managerSelect.value = dept.manager_id;
                    }
                }
            }
            
            console.log('✅ Datos cargados correctamente');
        } else {
            throw new Error(data.message || 'Error al cargar datos del departamento');
        }
    } catch (error) {
        console.error('❌ Error cargando datos:', error);
        alert(`Error al cargar datos: ${error.message}`);
    } finally {
        hideButtonLoading('saveDepartmentBtn');
    }
}

async function loadDepartmentDetails(departmentId) {
    console.log('📋 Cargando detalles departamento:', departmentId);
    
    try {
        const url = `actions/get_department_details.php?id=${departmentId}`;
        console.log('🌐 URL detalles:', url);
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('📊 Detalles recibidos:', data);
        
        if (data.success) {
            renderDepartmentDetails(data.department);
            currentDepartmentId = departmentId;
        } else {
            throw new Error(data.message || 'Error al cargar detalles');
        }
    } catch (error) {
        console.error('❌ Error cargando detalles:', error);
        alert(`Error al cargar detalles: ${error.message}`);
    }
}

async function handleCompanyChange(e) {
    const companyId = e.target.value;
    console.log('🏢 Cambio de empresa:', companyId);
    await loadManagersForCompany(companyId);
}

async function loadManagersForCompany(companyId) {
    console.log('👥 Cargando managers para empresa:', companyId);
    
    const managerSelect = document.getElementById('departmentManager');
    if (!managerSelect) {
        console.warn('⚠️ Select de manager no encontrado');
        return;
    }
    
    // Limpiar opciones
    managerSelect.innerHTML = '<option value="">Sin asignar</option>';
    
    if (!companyId) {
        console.log('ℹ️ No hay empresa seleccionada');
        return;
    }
    
    try {
        // Ruta correcta a users
        const url = `../users/actions/get_users.php?company_id=${companyId}&status=active`;
        console.log('🌐 URL managers:', url);
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('👥 Managers recibidos:', data);
        
        if (data.success && data.users && Array.isArray(data.users)) {
            data.users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.first_name} ${user.last_name} (${user.email})`;
                managerSelect.appendChild(option);
            });
            console.log(`✅ ${data.users.length} managers cargados`);
        } else {
            console.log('ℹ️ No hay managers disponibles');
        }
    } catch (error) {
        console.error('❌ Error cargando managers:', error);
        // No mostrar alert aquí, solo log del error
    }
}

// ==========================================
// MANEJO DE FORMULARIOS
// ==========================================

async function handleDepartmentSubmit(e) {
    e.preventDefault();
    console.log('📤 Enviando formulario departamento...');
    
    if (!validateForm()) {
        alert('Por favor corrige los errores en el formulario');
        return;
    }
    
    const formData = new FormData(e.target);
    const isEdit = currentDepartmentId !== null;
    
    if (isEdit) {
        formData.append('department_id', currentDepartmentId);
    }
    
    // Log de datos del formulario
    console.log('📋 Datos del formulario:');
    for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
    }
    
    try {
        showButtonLoading('saveDepartmentBtn');
        
        const endpoint = isEdit ? 'actions/update_department.php' : 'actions/create_department.php';
        console.log('🌐 Endpoint:', endpoint);
        
        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('📊 Respuesta servidor:', data);
        
        if (data.success) {
            const action = isEdit ? 'actualizado' : 'creado';
            
            // Mostrar notificación elegante si está disponible
            if (typeof showNotification === 'function') {
                showNotification(`Departamento ${action} exitosamente`, 'success');
            } else {
                alert(`Departamento ${action} exitosamente`);
            }
            
            closeDepartmentModal();
            
            // Recargar página
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            if (data.errors && Array.isArray(data.errors)) {
                data.errors.forEach(error => alert(error));
            } else {
                alert(data.message || 'Error al procesar la solicitud');
            }
        }
    } catch (error) {
        console.error('❌ Error enviando formulario:', error);
        alert(`Error de conexión: ${error.message}`);
    } finally {
        hideButtonLoading('saveDepartmentBtn');
    }
}

// ==========================================
// VALIDACIÓN
// ==========================================

function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    let message = '';

    clearFieldError(field);

    switch (field.name) {
        case 'name':
            if (!value) {
                isValid = false;
                message = 'El nombre es requerido';
            } else if (value.length < 2) {
                isValid = false;
                message = 'El nombre debe tener al menos 2 caracteres';
            } else if (value.length > 100) {
                isValid = false;
                message = 'El nombre no puede exceder 100 caracteres';
            }
            break;

        case 'company_id':
            if (!value) {
                isValid = false;
                message = 'Debe seleccionar una empresa';
            }
            break;

        case 'description':
            if (value && value.length > 500) {
                isValid = false;
                message = 'La descripción no puede exceder 500 caracteres';
            }
            break;
    }

    if (!isValid) {
        showFieldError(field, message);
    }

    return isValid;
}

function validateForm() {
    const form = document.getElementById('departmentForm');
    if (!form) {
        console.error('❌ Formulario no encontrado');
        return false;
    }
    
    const inputs = form.querySelectorAll('input[required], select[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    field.classList.add('is-invalid');
    
    const feedback = field.parentNode.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.textContent = message;
        feedback.style.display = 'block';
    }
}

function clearFieldError(field) {
    field.classList.remove('is-invalid');
    
    const feedback = field.parentNode.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.textContent = '';
        feedback.style.display = 'none';
    }
}

function clearFormErrors() {
    const form = document.getElementById('departmentForm');
    if (!form) return;
    
    const invalidFields = form.querySelectorAll('.is-invalid');
    invalidFields.forEach(field => {
        clearFieldError(field);
    });
}

// ==========================================
// MODALES
// ==========================================

function showModal(modalId) {
    console.log('📱 Abriendo modal:', modalId);
    
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        modal.classList.add('show');
        currentModal = modalId;
        
        const firstInput = modal.querySelector('input, select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    } else {
        console.error('❌ Modal no encontrado:', modalId);
    }
}

function hideModal(modalId) {
    console.log('❌ Cerrando modal:', modalId);
    
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        
        if (currentModal === modalId) {
            currentModal = null;
        }
    }
}

function closeCurrentModal() {
    if (currentModal) {
        hideModal(currentModal);
    }
}

function closeDepartmentModal() {
    hideModal('departmentModal');
    clearFormErrors();
}

function closeViewDepartmentModal() {
    hideModal('viewDepartmentModal');
    currentDepartmentId = null;
}

function editDepartmentFromView() {
    closeViewDepartmentModal();
    editDepartment(currentDepartmentId);
}

// ==========================================
// FUNCIONES DE APOYO
// ==========================================

function showButtonLoading(buttonId) {
    const button = document.getElementById(buttonId);
    if (button) {
        button.disabled = true;
        const textSpan = button.querySelector('.btn-text');
        const spinnerSpan = button.querySelector('.btn-spinner');
        if (textSpan) textSpan.style.display = 'none';
        if (spinnerSpan) spinnerSpan.style.display = 'inline-block';
    }
}

function hideButtonLoading(buttonId) {
    const button = document.getElementById(buttonId);
    if (button) {
        button.disabled = false;
        const textSpan = button.querySelector('.btn-text');
        const spinnerSpan = button.querySelector('.btn-spinner');
        if (textSpan) textSpan.style.display = 'inline-block';
        if (spinnerSpan) spinnerSpan.style.display = 'none';
    }
}

function renderDepartmentDetails(dept) {
    const detailsContainer = document.getElementById('departmentDetails');
    if (!detailsContainer) {
        console.error('❌ Contenedor de detalles no encontrado');
        return;
    }
    
    const html = `
        <div class="department-details">
            <div class="detail-header">
                <h3>${escapeHtml(dept.name)}</h3>
                <span class="status-badge ${dept.status === 'active' ? 'active' : 'inactive'}">
                    ${dept.status === 'active' ? 'ACTIVO' : 'INACTIVO'}
                </span>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Empresa:</label>
                    <span>${escapeHtml(dept.company_name || 'Sin empresa')}</span>
                </div>
                
                <div class="detail-item">
                    <label>Manager:</label>
                    <span>${escapeHtml(dept.manager_name || 'Sin asignar')}</span>
                </div>
                
                <div class="detail-item">
                    <label>Descripción:</label>
                    <span>${escapeHtml(dept.description || 'Sin descripción')}</span>
                </div>
                
                <div class="detail-item">
                    <label>Usuarios:</label>
                    <span>${dept.stats ? dept.stats.total_users : 0}</span>
                </div>
                
                <div class="detail-item">
                    <label>Fecha Creación:</label>
                    <span>${dept.formatted_created || 'No disponible'}</span>
                </div>
            </div>
        </div>
    `;
    
    detailsContainer.innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ==========================================
// EXPORTAR FUNCIONES GLOBALES
// ==========================================

window.openCreateDepartmentModal = openCreateDepartmentModal;
window.editDepartment = editDepartment;
window.viewDepartmentDetails = viewDepartmentDetails;
window.editDepartmentFromView = editDepartmentFromView;
window.toggleDepartmentStatus = toggleDepartmentStatus;
window.closeDepartmentModal = closeDepartmentModal;
window.closeViewDepartmentModal = closeViewDepartmentModal;

console.log('✅ Módulo de departamentos cargado correctamente');