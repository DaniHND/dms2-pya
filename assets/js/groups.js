/*
 * assets/js/groups.js
 * JavaScript para el módulo de Grupos - DMS2
 * Funcionalidades completas con AJAX y validaciones
 */

// Variables globales
let currentGroupId = null;
let groupsTable = null;

// Inicialización cuando el DOM está listo
document.addEventListener('DOMContentLoaded', function() {
    initializeGroupsModule();
});

// Función principal de inicialización
function initializeGroupsModule() {
    console.log('Inicializando módulo de Grupos...');
    
    // Configurar eventos
    setupEventListeners();
    
    // Configurar tooltips si Bootstrap está disponible
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

// Configurar event listeners
function setupEventListeners() {
    // Botones de acción en las filas
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-group-action')) {
            e.preventDefault();
        }
    });
    
    // Form submit prevention para manejo AJAX
    const groupForm = document.getElementById('groupForm');
    if (groupForm) {
        groupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveGroup();
        });
    }
}

// Mostrar modal para crear nuevo grupo
function showCreateGroupModal() {
    currentGroupId = null;
    document.getElementById('groupModalTitle').textContent = 'Crear Nuevo Grupo';
    clearGroupForm();
    
    const modal = new bootstrap.Modal(document.getElementById('groupModal'));
    modal.show();
}

// Limpiar formulario de grupo
function clearGroupForm() {
    document.getElementById('groupForm').reset();
    document.getElementById('groupId').value = '';
    
    // Limpiar checkboxes de permisos
    document.querySelectorAll('input[type="checkbox"][name^="permissions"]').forEach(cb => {
        cb.checked = false;
    });
    
    // Resetear restricciones
    document.querySelector('input[name="company_restriction"][value="all"]').checked = true;
    document.querySelector('input[name="department_restriction"][value="all"]').checked = true;
    document.querySelector('input[name="doctype_restriction"][value="all"]').checked = true;
    
    // Ocultar secciones específicas
    document.getElementById('specificCompanies').style.display = 'none';
    document.getElementById('specificDepartments').style.display = 'none';
    document.getElementById('specificDocTypes').style.display = 'none';
    
    // Limpiar checkboxes específicos
    document.querySelectorAll('input[name="allowed_companies[]"]').forEach(cb => cb.checked = false);
    document.querySelectorAll('input[name="allowed_departments[]"]').forEach(cb => cb.checked = false);
    document.querySelectorAll('input[name="allowed_document_types[]"]').forEach(cb => cb.checked = false);
}

// Editar grupo existente
function editGroup(groupId) {
    currentGroupId = groupId;
    document.getElementById('groupModalTitle').textContent = 'Editar Grupo';
    
    // Cargar datos del grupo
    showLoader('Cargando datos del grupo...');
    
    fetch(`actions/get_group_details.php?id=${groupId}`)
        .then(response => response.json())
        .then(data => {
            hideLoader();
            
            if (data.success) {
                populateGroupForm(data.group);
                const modal = new bootstrap.Modal(document.getElementById('groupModal'));
                modal.show();
            } else {
                showAlert('Error', data.message || 'No se pudieron cargar los datos del grupo', 'error');
            }
        })
        .catch(error => {
            hideLoader();
            console.error('Error cargando grupo:', error);
            showAlert('Error', 'Error de conexión al cargar el grupo', 'error');
        });
}

// Llenar formulario con datos del grupo
function populateGroupForm(group) {
    document.getElementById('groupId').value = group.id;
    document.getElementById('groupName').value = group.name;
    document.getElementById('groupDescription').value = group.description || '';
    document.getElementById('groupStatus').value = group.status;
    
    // Límites operacionales
    document.getElementById('downloadLimit').value = group.download_limit_daily || '';
    document.getElementById('uploadLimit').value = group.upload_limit_daily || '';
    
    // Cargar permisos
    if (group.permissions) {
        loadGroupPermissions(group.permissions);
    }
    
    // Cargar restricciones
    if (group.restrictions) {
        loadGroupRestrictions(group.restrictions);
    }
}

// Cargar permisos en el formulario
function loadGroupPermissions(permissions) {
    for (const [module, actions] of Object.entries(permissions)) {
        for (const [action, allowed] of Object.entries(actions)) {
            const checkbox = document.getElementById(`perm_${module}_${action}`);
            if (checkbox) {
                checkbox.checked = allowed;
            }
        }
    }
}

// Cargar restricciones en el formulario
function loadGroupRestrictions(restrictions) {
    // Restricciones de empresas
    if (restrictions.companies) {
        if (restrictions.companies === 'all') {
            document.querySelector('input[name="company_restriction"][value="all"]').checked = true;
        } else if (restrictions.companies === 'user_company') {
            document.querySelector('input[name="company_restriction"][value="user_company"]').checked = true;
        } else if (Array.isArray(restrictions.companies)) {
            document.querySelector('input[name="company_restriction"][value="specific"]').checked = true;
            document.getElementById('specificCompanies').style.display = 'block';
            restrictions.companies.forEach(companyId => {
                const checkbox = document.getElementById(`company_${companyId}`);
                if (checkbox) checkbox.checked = true;
            });
        }
    }
    
    // Restricciones de departamentos
    if (restrictions.departments) {
        if (restrictions.departments === 'all') {
            document.querySelector('input[name="department_restriction"][value="all"]').checked = true;
        } else if (restrictions.departments === 'user_department') {
            document.querySelector('input[name="department_restriction"][value="user_department"]').checked = true;
        } else if (Array.isArray(restrictions.departments)) {
            document.querySelector('input[name="department_restriction"][value="specific"]').checked = true;
            document.getElementById('specificDepartments').style.display = 'block';
            restrictions.departments.forEach(deptId => {
                const checkbox = document.getElementById(`dept_${deptId}`);
                if (checkbox) checkbox.checked = true;
            });
        }
    }
    
    // Restricciones de tipos de documentos
    if (restrictions.document_types) {
        if (restrictions.document_types === 'all') {
            document.querySelector('input[name="doctype_restriction"][value="all"]').checked = true;
        } else if (Array.isArray(restrictions.document_types)) {
            document.querySelector('input[name="doctype_restriction"][value="specific"]').checked = true;
            document.getElementById('specificDocTypes').style.display = 'block';
            restrictions.document_types.forEach(typeId => {
                const checkbox = document.getElementById(`doctype_${typeId}`);
                if (checkbox) checkbox.checked = true;
            });
        }
    }
}

// Guardar grupo (crear o actualizar)
function saveGroup() {
    const form = document.getElementById('groupForm');
    const formData = new FormData();
    
    // Datos básicos
    formData.append('group_id', document.getElementById('groupId').value);
    formData.append('name', document.getElementById('groupName').value.trim());
    formData.append('description', document.getElementById('groupDescription').value.trim());
    formData.append('status', document.getElementById('groupStatus').value);
    formData.append('download_limit_daily', document.getElementById('downloadLimit').value || null);
    formData.append('upload_limit_daily', document.getElementById('uploadLimit').value || null);
    
    // Validaciones
    if (!formData.get('name')) {
        showAlert('Error', 'El nombre del grupo es obligatorio', 'error');
        return;
    }
    
    // Recopilar permisos
    const permissions = {};
    document.querySelectorAll('input[type="checkbox"][name^="permissions"]').forEach(checkbox => {
        if (checkbox.checked) {
            const match = checkbox.name.match(/permissions\[(\w+)\]\[(\w+)\]/);
            if (match) {
                const [, module, action] = match;
                if (!permissions[module]) permissions[module] = {};
                permissions[module][action] = true;
            }
        }
    });
    formData.append('permissions', JSON.stringify(permissions));
    
    // Recopilar restricciones
    const restrictions = {};
    
    // Restricciones de empresas
    const companyRestriction = document.querySelector('input[name="company_restriction"]:checked').value;
    if (companyRestriction === 'specific') {
        const selectedCompanies = Array.from(document.querySelectorAll('input[name="allowed_companies[]"]:checked'))
            .map(cb => parseInt(cb.value));
        restrictions.companies = selectedCompanies;
    } else {
        restrictions.companies = companyRestriction;
    }
    
    // Restricciones de departamentos
    const deptRestriction = document.querySelector('input[name="department_restriction"]:checked').value;
    if (deptRestriction === 'specific') {
        const selectedDepts = Array.from(document.querySelectorAll('input[name="allowed_departments[]"]:checked'))
            .map(cb => parseInt(cb.value));
        restrictions.departments = selectedDepts;
    } else {
        restrictions.departments = deptRestriction;
    }
    
    // Restricciones de tipos de documentos
    const doctypeRestriction = document.querySelector('input[name="doctype_restriction"]:checked').value;
    if (doctypeRestriction === 'specific') {
        const selectedTypes = Array.from(document.querySelectorAll('input[name="allowed_document_types[]"]:checked'))
            .map(cb => parseInt(cb.value));
        restrictions.document_types = selectedTypes;
    } else {
        restrictions.document_types = doctypeRestriction;
    }
    
    formData.append('restrictions', JSON.stringify(restrictions));
    
    // Enviar datos
    const isEdit = currentGroupId !== null;
    const actionUrl = isEdit ? 'actions/update_group.php' : 'actions/create_group.php';
    
    showLoader(isEdit ? 'Actualizando grupo...' : 'Creando grupo...');
    
    fetch(actionUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoader();
        
        if (data.success) {
            showAlert('Éxito', data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('groupModal')).hide();
            
            // Recargar página para mostrar cambios
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('Error', data.message || 'Error al guardar el grupo', 'error');
        }
    })
    .catch(error => {
        hideLoader();
        console.error('Error guardando grupo:', error);
        showAlert('Error', 'Error de conexión al guardar el grupo', 'error');
    });
}

// Ver detalles del grupo
function viewGroupDetails(groupId) {
    showLoader('Cargando detalles del grupo...');
    
    fetch(`actions/get_group_details.php?id=${groupId}&full=1`)
        .then(response => response.json())
        .then(data => {
            hideLoader();
            
            if (data.success) {
                displayGroupDetails(data.group, data.members || []);
                const modal = new bootstrap.Modal(document.getElementById('viewGroupModal'));
                modal.show();
            } else {
                showAlert('Error', data.message || 'No se pudieron cargar los detalles del grupo', 'error');
            }
        })
        .catch(error => {
            hideLoader();
            console.error('Error cargando detalles:', error);
            showAlert('Error', 'Error de conexión al cargar detalles', 'error');
        });
}

// Mostrar detalles del grupo en el modal
function displayGroupDetails(group, members) {
    const content = document.getElementById('viewGroupContent');
    
    let membersHtml = '';
    if (members.length > 0) {
        membersHtml = members.map(member => `
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div>
                    <strong>${member.first_name} ${member.last_name}</strong>
                    <br><small class="text-muted">@${member.username} - ${member.company_name || 'Sin empresa'}</small>
                </div>
                <span class="badge bg-${member.status === 'active' ? 'success' : 'warning'}">${member.status}</span>
            </div>
        `).join('');
    } else {
        membersHtml = '<p class="text-muted text-center py-3">No hay usuarios asignados a este grupo</p>';
    }
    
    content.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-info-circle me-2"></i>Información General</h6>
                <table class="table table-sm">
                    <tr><td><strong>Nombre:</strong></td><td>${group.name}</td></tr>
                    <tr><td><strong>Descripción:</strong></td><td>${group.description || 'Sin descripción'}</td></tr>
                    <tr><td><strong>Estado:</strong></td><td><span class="badge bg-${group.status === 'active' ? 'success' : 'warning'}">${group.status}</span></td></tr>
                    <tr><td><strong>Tipo:</strong></td><td>${group.is_system_group ? 'Grupo del Sistema' : 'Grupo Personalizado'}</td></tr>
                    <tr><td><strong>Creado:</strong></td><td>${new Date(group.created_at).toLocaleDateString()}</td></tr>
                    <tr><td><strong>Límite Descargas:</strong></td><td>${group.download_limit_daily || 'Sin límite'}</td></tr>
                    <tr><td><strong>Límite Subidas:</strong></td><td>${group.upload_limit_daily || 'Sin límite'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-users me-2"></i>Miembros (${members.length})</h6>
                <div style="max-height: 300px; overflow-y: auto;">
                    ${membersHtml}
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <h6><i class="fas fa-key me-2"></i>Permisos por Módulo</h6>
            <div id="permissionsDetails"></div>
        </div>
        
        <div class="mt-4">
            <h6><i class="fas fa-filter me-2"></i>Restricciones de Acceso</h6>
            <div id="restrictionsDetails"></div>
        </div>
    `;
    
    // Mostrar permisos formateados
    displayFormattedPermissions(group.permissions, 'permissionsDetails');
    
    // Mostrar restricciones formateadas
    displayFormattedRestrictions(group.restrictions, 'restrictionsDetails');
}

// Mostrar permisos formateados
function displayFormattedPermissions(permissions, containerId) {
    const container = document.getElementById(containerId);
    if (!permissions || Object.keys(permissions).length === 0) {
        container.innerHTML = '<p class="text-muted">Sin permisos específicos asignados</p>';
        return;
    }
    
    let html = '<div class="row">';
    for (const [module, actions] of Object.entries(permissions)) {
        const moduleActions = Object.entries(actions)
            .filter(([action, allowed]) => allowed)
            .map(([action]) => action);
            
        if (moduleActions.length > 0) {
            html += `
                <div class="col-md-4 mb-2">
                    <div class="card card-body py-2">
                        <h6 class="mb-1">${module.charAt(0).toUpperCase() + module.slice(1)}</h6>
                        <small class="text-success">${moduleActions.join(', ')}</small>
                    </div>
                </div>
            `;
        }
    }
    html += '</div>';
    
    container.innerHTML = html;
}

// Mostrar restricciones formateadas
function displayFormattedRestrictions(restrictions, containerId) {
    const container = document.getElementById(containerId);
    if (!restrictions) {
        container.innerHTML = '<p class="text-muted">Sin restricciones específicas</p>';
        return;
    }
    
    let html = '<div class="row">';
    
    // Restricciones de empresas
    html += '<div class="col-md-4"><strong>Empresas:</strong><br>';
    if (restrictions.companies === 'all') {
        html += '<span class="text-success">Todas las empresas</span>';
    } else if (restrictions.companies === 'user_company') {
        html += '<span class="text-info">Solo su empresa</span>';
    } else if (Array.isArray(restrictions.companies)) {
        html += `<span class="text-warning">${restrictions.companies.length} empresas específicas</span>`;
    }
    html += '</div>';
    
    // Restricciones de departamentos
    html += '<div class="col-md-4"><strong>Departamentos:</strong><br>';
    if (restrictions.departments === 'all') {
        html += '<span class="text-success">Todos los departamentos</span>';
    } else if (restrictions.departments === 'user_department') {
        html += '<span class="text-info">Solo su departamento</span>';
    } else if (Array.isArray(restrictions.departments)) {
        html += `<span class="text-warning">${restrictions.departments.length} departamentos específicos</span>`;
    }
    html += '</div>';
    
    // Restricciones de tipos de documentos
    html += '<div class="col-md-4"><strong>Tipos de Documentos:</strong><br>';
    if (restrictions.document_types === 'all') {
        html += '<span class="text-success">Todos los tipos</span>';
    } else if (Array.isArray(restrictions.document_types)) {
        html += `<span class="text-warning">${restrictions.document_types.length} tipos específicos</span>`;
    }
    html += '</div>';
    
    html += '</div>';
    container.innerHTML = html;
}

// Gestionar usuarios del grupo
function manageGroupUsers(groupId) {
    showLoader('Cargando gestión de usuarios...');
    
    fetch(`actions/get_group_users.php?group_id=${groupId}`)
        .then(response => response.json())
        .then(data => {
            hideLoader();
            
            if (data.success) {
                displayUserManagement(groupId, data.current_users || [], data.available_users || []);
                const modal = new bootstrap.Modal(document.getElementById('manageUsersModal'));
                modal.show();
            } else {
                showAlert('Error', data.message || 'No se pudo cargar la gestión de usuarios', 'error');
            }
        })
        .catch(error => {
            hideLoader();
            console.error('Error cargando usuarios:', error);
            showAlert('Error', 'Error de conexión al cargar usuarios', 'error');
        });
}

// Mostrar interfaz de gestión de usuarios
function displayUserManagement(groupId, currentUsers, availableUsers) {
    const content = document.getElementById('manageUsersContent');
    
    content.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-users me-2"></i>Usuarios Actuales (${currentUsers.length})</h6>
                <div id="currentUsers" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 5px; padding: 10px;">
                    ${currentUsers.length > 0 ? 
                        currentUsers.map(user => `
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom" data-user-id="${user.id}">
                                <div>
                                    <strong>${user.first_name} ${user.last_name}</strong>
                                    <br><small class="text-muted">@${user.username} - ${user.company_name || 'Sin empresa'}</small>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" onclick="removeUserFromGroup(${groupId}, ${user.id})">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `).join('') : 
                        '<p class="text-muted text-center">No hay usuarios asignados</p>'
                    }
                </div>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-user-plus me-2"></i>Usuarios Disponibles</h6>
                <div class="mb-3">
                    <input type="text" class="form-control" id="userSearch" placeholder="Buscar usuarios..." onkeyup="filterAvailableUsers()">
                </div>
                <div id="availableUsers" style="max-height: 350px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 5px; padding: 10px;">
                    ${availableUsers.map(user => `
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom user-item" data-user-id="${user.id}" data-user-name="${user.first_name} ${user.last_name} ${user.username}">
                            <div>
                                <strong>${user.first_name} ${user.last_name}</strong>
                                <br><small class="text-muted">@${user.username} - ${user.company_name || 'Sin empresa'}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-success" onclick="addUserToGroup(${groupId}, ${user.id})">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
}

// Filtrar usuarios disponibles
function filterAvailableUsers() {
    const searchTerm = document.getElementById('userSearch').value.toLowerCase();
    const userItems = document.querySelectorAll('.user-item');
    
    userItems.forEach(item => {
        const userName = item.getAttribute('data-user-name').toLowerCase();
        if (userName.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// Agregar usuario al grupo
function addUserToGroup(groupId, userId) {
    const formData = new FormData();
    formData.append('group_id', groupId);
    formData.append('user_id', userId);
    formData.append('action', 'add');
    
    fetch('actions/manage_group_users.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Éxito', 'Usuario agregado al grupo correctamente', 'success');
            // Recargar gestión de usuarios
            manageGroupUsers(groupId);
        } else {
            showAlert('Error', data.message || 'Error al agregar usuario al grupo', 'error');
        }
    })
    .catch(error => {
        console.error('Error agregando usuario:', error);
        showAlert('Error', 'Error de conexión al agregar usuario', 'error');
    });
}

// Remover usuario del grupo
function removeUserFromGroup(groupId, userId) {
    if (!confirm('¿Está seguro de que desea remover este usuario del grupo?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('group_id', groupId);
    formData.append('user_id', userId);
    formData.append('action', 'remove');
    
    fetch('actions/manage_group_users.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Éxito', 'Usuario removido del grupo correctamente', 'success');
            // Recargar gestión de usuarios
            manageGroupUsers(groupId);
        } else {
            showAlert('Error', data.message || 'Error al remover usuario del grupo', 'error');
        }
    })
    .catch(error => {
        console.error('Error removiendo usuario:', error);
        showAlert('Error', 'Error de conexión al remover usuario', 'error');
    });
}

// Cambiar estado del grupo (activar/desactivar)
function toggleGroupStatus(groupId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'activar' : 'desactivar';
    
    if (!confirm(`¿Está seguro de que desea ${action} este grupo?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('group_id', groupId);
    formData.append('status', newStatus);
    
    showLoader(`${action.charAt(0).toUpperCase() + action.slice(1)}ando grupo...`);
    
    fetch('actions/toggle_group_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoader();
        
        if (data.success) {
            showAlert('Éxito', data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('Error', data.message || `Error al ${action} el grupo`, 'error');
        }
    })
    .catch(error => {
        hideLoader();
        console.error('Error cambiando estado:', error);
        showAlert('Error', 'Error de conexión al cambiar estado', 'error');
    });
}

// Exportar grupos
function exportGroups() {
    window.location.href = 'actions/export_groups.php';
}

// Funciones de utilidad
function showAlert(title, message, type = 'info') {
    // Crear alert dinámico si no existe un sistema de alertas
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;" role="alert">
            <strong>${title}:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert:last-of-type');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

function showLoader(message = 'Cargando...') {
    // Crear o mostrar loader
    let loader = document.getElementById('globalLoader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'globalLoader';
        loader.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
        loader.style.cssText = 'background: rgba(0,0,0,0.7); z-index: 9999;';
        loader.innerHTML = `
            <div class="card text-center p-4">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <div id="loaderMessage">${message}</div>
            </div>
        `;
        document.body.appendChild(loader);
    } else {
        document.getElementById('loaderMessage').textContent = message;
        loader.style.display = 'flex';
    }
}

function hideLoader() {
    const loader = document.getElementById('globalLoader');
    if (loader) {
        loader.style.display = 'none';
    }
}

// Exportar funciones globales para uso en HTML
window.showCreateGroupModal = showCreateGroupModal;
window.editGroup = editGroup;
window.viewGroupDetails = viewGroupDetails;
window.manageGroupUsers = manageGroupUsers;
window.toggleGroupStatus = toggleGroupStatus;
window.exportGroups = exportGroups;
window.saveGroup = saveGroup;
window.addUserToGroup = addUserToGroup;
window.removeUserFromGroup = removeUserFromGroup;
window.filterAvailableUsers = filterAvailableUsers;