/*
 * assets/js/groups.js
 * JavaScript para el módulo de Grupos - DMS2
 * Versión optimizada y modular
 */

// Variables globales
let currentGroupId = null;
let currentGroupData = null;
let allGroupUsers = [];

// Configuración de módulos y permisos
const MODULE_CONFIG = {
    'users': {
        name: 'Usuarios',
        icon: 'users',
        actions: ['read', 'write', 'delete']
    },
    'companies': {
        name: 'Empresas', 
        icon: 'briefcase',
        actions: ['read', 'write', 'delete']
    },
    'departments': {
        name: 'Departamentos',
        icon: 'layers', 
        actions: ['read', 'write', 'delete']
    },
    'documents': {
        name: 'Documentos',
        icon: 'file-text',
        actions: ['read', 'write', 'delete', 'download', 'upload']
    },
    'groups': {
        name: 'Grupos',
        icon: 'users',
        actions: ['read', 'write', 'delete']
    },
    'reports': {
        name: 'Reportes',
        icon: 'bar-chart-2',
        actions: ['read', 'write']
    }
};

const ACTION_LABELS = {
    'read': 'Ver/Leer',
    'write': 'Crear/Editar', 
    'delete': 'Eliminar',
    'download': 'Descargar',
    'upload': 'Subir'
};

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando módulo de grupos...');
    
    // Inicializar componentes
    initializeFeatherIcons();
    initializeEventListeners();
    initializeModals();
    
    console.log('✅ Módulo de grupos inicializado correctamente');
});

// Inicializar iconos de Feather
function initializeFeatherIcons() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

// Configurar event listeners
function initializeEventListeners() {
    // Botones principales
    const btnCreateGroup = document.getElementById('btnCreateGroup');
    const btnCreateFirstGroup = document.getElementById('btnCreateFirstGroup');
    
    if (btnCreateGroup) {
        btnCreateGroup.addEventListener('click', showCreateGroupModal);
    }
    
    if (btnCreateFirstGroup) {
        btnCreateFirstGroup.addEventListener('click', showCreateGroupModal);
    }

    // Formulario de filtros
    const searchInput = document.getElementById('search');
    const statusSelect = document.getElementById('status');
    
    if (searchInput) {
        searchInput.addEventListener('input', handleAutoSearch);
    }
    
    if (statusSelect) {
        statusSelect.addEventListener('change', handleAutoSearch);
    }

    // Event listeners de modales
    setupModalEventListeners();
}

// Configurar modales
function initializeModals() {
    setupModalCloseHandlers();
    setupFormHandlers();
}

// ============================================================================
// GESTIÓN DE MODALES
// ============================================================================

function setupModalEventListeners() {
    // Modal de grupo
    const saveGroupBtn = document.getElementById('saveGroupBtn');
    if (saveGroupBtn) {
        saveGroupBtn.addEventListener('click', saveGroup);
    }

    // Modal de permisos
    const savePermissionsBtn = document.getElementById('savePermissionsBtn');
    if (savePermissionsBtn) {
        savePermissionsBtn.addEventListener('click', saveGroupPermissions);
    }

    // Tabs del modal de permisos
    const tabButtons = document.querySelectorAll('[data-tab]');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            switchPermissionTab(this.dataset.tab);
        });
    });

    // Restricciones de empresas y departamentos
    const companyRestrictions = document.querySelectorAll('input[name="companyRestriction"]');
    const departmentRestrictions = document.querySelectorAll('input[name="departmentRestriction"]');
    
    companyRestrictions.forEach(radio => {
        radio.addEventListener('change', toggleCompanyRestriction);
    });
    
    departmentRestrictions.forEach(radio => {
        radio.addEventListener('change', toggleDepartmentRestriction);
    });

    // Filtros del modal de usuarios
    const userSearch = document.getElementById('userSearch');
    const companyFilter = document.getElementById('companyFilter');
    
    if (userSearch) {
        userSearch.addEventListener('input', filterUsers);
    }
    
    if (companyFilter) {
        companyFilter.addEventListener('change', filterUsers);
    }
}

function setupModalCloseHandlers() {
    // Cerrar modales con botones de cerrar
    document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });

    // Cerrar modales haciendo clic fuera
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    // Cerrar modales con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

function setupFormHandlers() {
    // Prevenir envío de formularios
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
        });
    });
}

// ============================================================================
// FUNCIONES DE MODAL
// ============================================================================

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Reinicializar iconos de Feather en el modal
        setTimeout(() => {
            initializeFeatherIcons();
        }, 100);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

function closeAllModals() {
    document.querySelectorAll('.modal.show').forEach(modal => {
        modal.classList.remove('show');
    });
    document.body.style.overflow = '';
}

// ============================================================================
// FUNCIONES DEL MODAL DE CREAR/EDITAR GRUPO
// ============================================================================

function showCreateGroupModal() {
    currentGroupId = null;
    currentGroupData = null;
    
    document.getElementById('groupModalTitle').textContent = 'Crear Nuevo Grupo';
    clearGroupForm();
    showModal('groupModal');
    
    // Enfocar primer campo
    setTimeout(() => {
        const groupNameInput = document.getElementById('groupName');
        if (groupNameInput) {
            groupNameInput.focus();
        }
    }, 200);
}

function clearGroupForm() {
    const form = document.getElementById('groupForm');
    if (form) {
        form.reset();
    }
    
    const groupId = document.getElementById('groupId');
    if (groupId) {
        groupId.value = '';
    }
    
    currentGroupId = null;
    currentGroupData = null;
}

function saveGroup() {
    const groupName = document.getElementById('groupName')?.value.trim();
    const groupDescription = document.getElementById('groupDescription')?.value.trim();
    const groupStatus = document.getElementById('groupStatus')?.value;
    const downloadLimit = document.getElementById('downloadLimit')?.value || 0;
    const uploadLimit = document.getElementById('uploadLimit')?.value || 0;
    
    if (!groupName) {
        showNotification('El nombre del grupo es obligatorio', 'error');
        document.getElementById('groupName')?.focus();
        return;
    }
    
    showLoading();
    
    const formData = new FormData();
    formData.append('group_name', groupName);
    formData.append('group_description', groupDescription);
    formData.append('group_status', groupStatus);
    formData.append('download_limit', downloadLimit);
    formData.append('upload_limit', uploadLimit);
    
    if (currentGroupId) {
        formData.append('group_id', currentGroupId);
    }
    
    const url = currentGroupId ? 'actions/update_group.php' : 'actions/create_group.php';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showNotification(
                data.message || (currentGroupId ? 'Grupo actualizado correctamente' : 'Grupo creado correctamente'), 
                'success'
            );
            closeModal('groupModal');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(data.message || 'Error al guardar el grupo', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showNotification('Error de conexión al guardar el grupo', 'error');
    });
}

// ============================================================================
// FUNCIONES DE ACCIONES DE GRUPOS
// ============================================================================

function viewGroupDetails(groupId) {
    showLoading();
    
    fetch(`actions/get_group_details.php?id=${groupId}`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                showGroupDetailsModal(data.group);
            } else {
                showNotification(data.message || 'Error al cargar detalles del grupo', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showNotification('Error de conexión al cargar detalles', 'error');
        });
}

function showGroupDetailsModal(group) {
    currentGroupData = group;
    document.getElementById('detailsModalTitle').textContent = `Detalles: ${group.name}`;
    
    const detailsHtml = generateGroupDetailsHTML(group);
    document.getElementById('groupDetails').innerHTML = detailsHtml;
    
    // Mostrar/ocultar botón de editar según si es grupo del sistema
    const editBtn = document.getElementById('editFromDetailsBtn');
    if (editBtn) {
        editBtn.style.display = group.is_system_group ? 'none' : 'inline-flex';
        editBtn.onclick = () => editGroupFromDetails();
    }
    
    showModal('detailsModal');
}

function generateGroupDetailsHTML(group) {
    return `
        <div class="group-detail-item">
            <div class="detail-label">
                <i data-feather="tag"></i>
                Nombre del Grupo
            </div>
            <div class="detail-value highlight">
                ${group.name}
                ${group.is_system_group ? '<span class="badge badge-warning">Sistema</span>' : ''}
            </div>
        </div>
        
        <div class="group-detail-item">
            <div class="detail-label">
                <i data-feather="file-text"></i>
                Descripción
            </div>
            <div class="detail-value">
                ${group.description || 'Sin descripción'}
            </div>
        </div>
        
        <div class="group-detail-item">
            <div class="detail-label">
                <i data-feather="activity"></i>
                Estado
            </div>
            <div class="detail-value">
                <span class="badge badge-${group.status === 'active' ? 'success' : 'danger'}">
                    ${group.status.toUpperCase()}
                </span>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card members">
                <div class="stat-number">${group.member_count}</div>
                <div class="stat-label">Total Miembros</div>
            </div>
            <div class="stat-card active-members">
                <div class="stat-number">${group.active_members}</div>
                <div class="stat-label">Miembros Activos</div>
            </div>
        </div>
        
        <div class="group-detail-item">
            <div class="detail-label">
                <i data-feather="settings"></i>
                Límites Diarios
            </div>
            <div class="limits-grid">
                <div class="limit-item">
                    <span class="limit-label">Descargas:</span>
                    <span class="limit-value">${group.download_limit_daily || 'Sin límite'}</span>
                </div>
                <div class="limit-item">
                    <span class="limit-label">Subidas:</span>
                    <span class="limit-value">${group.upload_limit_daily || 'Sin límite'}</span>
                </div>
            </div>
        </div>
        
        <div class="creation-info">
            <i data-feather="info"></i>
            Creado el ${new Date(group.created_at).toLocaleDateString('es-ES')} 
            ${group.created_by_name ? `por ${group.created_by_name}` : ''}
        </div>
    `;
}

function editGroupFromDetails() {
    if (currentGroupData) {
        closeModal('detailsModal');
        editGroup(currentGroupData.id);
    }
}

function editGroup(groupId) {
    showLoading();
    
    fetch(`actions/get_group_details.php?id=${groupId}`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                const group = data.group;
                
                // Verificar si es grupo del sistema
                if (group.is_system_group) {
                    showNotification('No se puede editar un grupo del sistema', 'warning');
                    return;
                }
                
                // Llenar formulario
                currentGroupId = groupId;
                currentGroupData = group;
                document.getElementById('groupModalTitle').textContent = 'Editar Grupo';
                document.getElementById('groupId').value = groupId;
                document.getElementById('groupName').value = group.name;
                document.getElementById('groupDescription').value = group.description || '';
                document.getElementById('groupStatus').value = group.status;
                document.getElementById('downloadLimit').value = group.download_limit_daily || '';
                document.getElementById('uploadLimit').value = group.upload_limit_daily || '';
                
                showModal('groupModal');
                
                setTimeout(() => {
                    document.getElementById('groupName')?.focus();
                }, 200);
            } else {
                showNotification(data.message || 'Error al cargar datos del grupo', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showNotification('Error de conexión al cargar el grupo', 'error');
        });
}

function toggleGroupStatus(groupId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'activar' : 'desactivar';
    
    if (confirm(`¿Está seguro que desea ${action} este grupo?`)) {
        showLoading();
        
        const formData = new FormData();
        formData.append('group_id', groupId);
        formData.append('new_status', newStatus);
        
        fetch('actions/toggle_group_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                showNotification(
                    data.message || `Grupo ${action === 'activar' ? 'activado' : 'desactivado'} correctamente`, 
                    'success'
                );
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showNotification(data.message || 'Error al cambiar estado del grupo', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showNotification('Error de conexión al cambiar estado', 'error');
        });
    }
}

// ============================================================================
// FUNCIONES DE GESTIÓN DE USUARIOS
// ============================================================================

function manageGroupUsers(groupId) {
    currentGroupId = groupId;
    showLoading();
    
    // Cargar datos del grupo y usuarios
    Promise.all([
        fetch(`actions/get_group_details.php?id=${groupId}`).then(r => r.json()),
        fetch(`actions/get_group_users.php?group_id=${groupId}`).then(r => r.json())
    ])
    .then(([groupData, usersData]) => {
        hideLoading();
        
        if (groupData.success && usersData.success) {
            document.getElementById('usersModalTitle').textContent = `Gestionar Usuarios: ${groupData.group.name}`;
            document.getElementById('currentGroupId').value = groupId;
            
            allGroupUsers = usersData.users || [];
            renderUsersList();
            showModal('usersModal');
        } else {
            showNotification('Error al cargar datos del grupo', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showNotification('Error de conexión al cargar usuarios', 'error');
    });
}

function renderUsersList() {
    const searchTerm = document.getElementById('userSearch')?.value.toLowerCase() || '';
    const companyFilter = document.getElementById('companyFilter')?.value || '';
    
    let filteredUsers = availableUsers.filter(user => {
        const matchesSearch = !searchTerm || 
            user.first_name.toLowerCase().includes(searchTerm) ||
            user.last_name.toLowerCase().includes(searchTerm) ||
            user.email.toLowerCase().includes(searchTerm) ||
            user.username.toLowerCase().includes(searchTerm);
        
        const matchesCompany = !companyFilter || user.company_id == companyFilter;
        
        return matchesSearch && matchesCompany;
    });

    const usersList = document.getElementById('usersList');
    const userStats = document.getElementById('userStats');
    
    if (!usersList) return;
    
    if (filteredUsers.length === 0) {
        usersList.innerHTML = `
            <div class="text-center py-4">
                <div class="empty-icon mx-auto mb-3">
                    <i data-feather="users"></i>
                </div>
                <p class="text-muted">No se encontraron usuarios</p>
            </div>
        `;
        initializeFeatherIcons();
        return;
    }

    const usersHtml = filteredUsers.map(user => {
        const isInGroup = allGroupUsers.some(gu => gu.user_id == user.id);
        const company = availableCompanies.find(c => c.id == user.company_id);
        
        return `
            <div class="user-item">
                <div class="user-info">
                    <div class="user-name">${user.first_name} ${user.last_name}</div>
                    <div class="user-details">${user.email} • ${user.username}</div>
                    ${company ? `<div class="user-company">${company.name}</div>` : ''}
                </div>
                <div class="user-actions">
                    ${isInGroup ? 
                        `<button class="btn-user-action btn-remove-user" onclick="removeUserFromGroup(${user.id})">
                            <i data-feather="user-minus"></i>
                            Remover
                        </button>` :
                        `<button class="btn-user-action btn-add-user" onclick="addUserToGroup(${user.id})">
                            <i data-feather="user-plus"></i>
                            Agregar
                        </button>`
                    }
                </div>
            </div>
        `;
    }).join('');

    usersList.innerHTML = usersHtml;
    
    // Actualizar estadísticas
    if (userStats) {
        const totalUsers = filteredUsers.length;
        const usersInGroup = filteredUsers.filter(user => 
            allGroupUsers.some(gu => gu.user_id == user.id)
        ).length;
        
        userStats.innerHTML = `
            <span class="text-muted">
                ${totalUsers} usuarios • 
                <span class="badge badge-info">${usersInGroup}</span> en el grupo
            </span>
        `;
    }
    
    initializeFeatherIcons();
}

function filterUsers() {
    renderUsersList();
}

function addUserToGroup(userId) {
    if (!currentGroupId) return;
    
    const formData = new FormData();
    formData.append('group_id', currentGroupId);
    formData.append('user_id', userId);
    formData.append('action', 'add');
    
    fetch('actions/manage_group_users.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Usuario agregado al grupo', 'success');
            
            // Actualizar lista local
            allGroupUsers.push({
                user_id: userId,
                group_id: currentGroupId,
                assigned_at: new Date().toISOString()
            });
            
            renderUsersList();
        } else {
            showNotification(data.message || 'Error al agregar usuario', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión al agregar usuario', 'error');
    });
}

function removeUserFromGroup(userId) {
    if (!currentGroupId) return;
    
    const user = availableUsers.find(u => u.id == userId);
    if (user && confirm(`¿Remover a ${user.first_name} ${user.last_name} del grupo?`)) {
        const formData = new FormData();
        formData.append('group_id', currentGroupId);
        formData.append('user_id', userId);
        formData.append('action', 'remove');
        
        fetch('actions/manage_group_users.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'Usuario removido del grupo', 'success');
                
                // Actualizar lista local
                allGroupUsers = allGroupUsers.filter(gu => gu.user_id != userId);
                
                renderUsersList();
            } else {
                showNotification(data.message || 'Error al remover usuario', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión al remover usuario', 'error');
        });
    }
}

// ============================================================================
// FUNCIONES DE GESTIÓN DE PERMISOS
// ============================================================================

function manageGroupPermissions(groupId) {
    showLoading();
    
    fetch(`actions/get_group_details.php?id=${groupId}`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                const group = data.group;
                
                // Verificar si es grupo del sistema
                if (group.is_system_group) {
                    showNotification('No se pueden editar permisos de un grupo del sistema', 'warning');
                    return;
                }
                
                document.getElementById('permissionsModalTitle').textContent = `Permisos: ${group.name}`;
                document.getElementById('currentPermissionGroupId').value = groupId;
                
                // Cargar permisos actuales
                loadCurrentPermissions(data.module_permissions || {});
                loadCurrentRestrictions(data.access_restrictions || {});
                
                showModal('permissionsModal');
            } else {
                showNotification(data.message || 'Error al cargar permisos del grupo', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showNotification('Error de conexión al cargar permisos', 'error');
        });
}

function switchPermissionTab(tabName) {
    // Desactivar todas las pestañas
    document.querySelectorAll('.nav-link').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(content => content.classList.remove('active'));
    
    // Activar pestaña seleccionada
    document.getElementById(tabName + 'Tab').classList.add('active');
    document.getElementById(tabName + 'Content').classList.add('active');
    
    // Re-renderizar iconos
    setTimeout(() => {
        initializeFeatherIcons();
    }, 50);
}

function loadCurrentPermissions(permissions) {
    let html = '';
    
    for (const [moduleKey, moduleData] of Object.entries(MODULE_CONFIG)) {
        const modulePermissions = permissions[moduleKey] || {};
        
        html += `
            <div class="permission-module">
                <h8>
                    <i data-feather="${moduleData.icon}"></i>
                    ${moduleData.name}
                </h8>
                <div class="permission-actions">
        `;
        
        moduleData.actions.forEach(action => {
            const isChecked = modulePermissions[action] === true;
            html += `
                <div class="permission-checkbox">
                    <input type="checkbox" id="perm_${moduleKey}_${action}" 
                           name="permissions[${moduleKey}][${action}]" 
                           value="1" ${isChecked ? 'checked' : ''}>
                    <label for="perm_${moduleKey}_${action}">${ACTION_LABELS[action] || action}</label>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    document.getElementById('modulePermissionsList').innerHTML = html;
    initializeFeatherIcons();
}

function loadCurrentRestrictions(restrictions) {
    // Restricciones de empresas
    const companyRestriction = restrictions.companies || 'all';
    
    if (companyRestriction === 'all') {
        document.querySelector('input[name="companyRestriction"][value="all"]').checked = true;
    } else if (companyRestriction === 'user_company') {
        document.querySelector('input[name="companyRestriction"][value="user_company"]').checked = true;
    } else if (Array.isArray(companyRestriction)) {
        document.querySelector('input[name="companyRestriction"][value="specific"]').checked = true;
        document.getElementById('specificCompanies').style.display = 'block';
        
        companyRestriction.forEach(companyId => {
            const checkbox = document.querySelector(`input[name="allowedCompanies[]"][value="${companyId}"]`);
            if (checkbox) checkbox.checked = true;
        });
    }
    
    // Restricciones de departamentos
    const departmentRestriction = restrictions.departments || 'all';
    
    if (departmentRestriction === 'all') {
        document.querySelector('input[name="departmentRestriction"][value="all"]').checked = true;
    } else if (departmentRestriction === 'user_department') {
        document.querySelector('input[name="departmentRestriction"][value="user_department"]').checked = true;
    } else if (Array.isArray(departmentRestriction)) {
        document.querySelector('input[name="departmentRestriction"][value="specific"]').checked = true;
        document.getElementById('specificDepartments').style.display = 'block';
        
        departmentRestriction.forEach(deptId => {
            const checkbox = document.querySelector(`input[name="allowedDepartments[]"][value="${deptId}"]`);
            if (checkbox) checkbox.checked = true;
        });
    }
}

function toggleCompanyRestriction() {
    const specificDiv = document.getElementById('specificCompanies');
    const specificRadio = document.querySelector('input[name="companyRestriction"][value="specific"]');
    
    if (specificRadio.checked) {
        specificDiv.style.display = 'block';
    } else {
        specificDiv.style.display = 'none';
        // Desmarcar todos los checkboxes
        document.querySelectorAll('input[name="allowedCompanies[]"]').forEach(cb => cb.checked = false);
    }
}

function toggleDepartmentRestriction() {
    const specificDiv = document.getElementById('specificDepartments');
    const specificRadio = document.querySelector('input[name="departmentRestriction"][value="specific"]');
    
    if (specificRadio.checked) {
        specificDiv.style.display = 'block';
    } else {
        specificDiv.style.display = 'none';
        // Desmarcar todos los checkboxes
        document.querySelectorAll('input[name="allowedDepartments[]"]').forEach(cb => cb.checked = false);
    }
}

function saveGroupPermissions() {
    const groupId = document.getElementById('currentPermissionGroupId').value;
    if (!groupId) return;
    
    showLoading();
    
    // Recopilar permisos
    const permissions = {};
    document.querySelectorAll('input[name^="permissions["]').forEach(checkbox => {
        if (checkbox.checked) {
            const match = checkbox.name.match(/permissions\[([^\]]+)\]\[([^\]]+)\]/);
            if (match) {
                const [, module, action] = match;
                if (!permissions[module]) permissions[module] = {};
                permissions[module][action] = true;
            }
        }
    });
    
    // Recopilar restricciones
    const restrictions = {};
    
    // Restricciones de empresas
    const companyRestrictionType = document.querySelector('input[name="companyRestriction"]:checked')?.value;
    if (companyRestrictionType === 'specific') {
        const selectedCompanies = Array.from(document.querySelectorAll('input[name="allowedCompanies[]"]:checked'))
            .map(cb => parseInt(cb.value));
        restrictions.companies = selectedCompanies;
    } else {
        restrictions.companies = companyRestrictionType;
    }
    
    // Restricciones de departamentos
    const departmentRestrictionType = document.querySelector('input[name="departmentRestriction"]:checked')?.value;
    if (departmentRestrictionType === 'specific') {
        const selectedDepartments = Array.from(document.querySelectorAll('input[name="allowedDepartments[]"]:checked'))
            .map(cb => parseInt(cb.value));
        restrictions.departments = selectedDepartments;
    } else {
        restrictions.departments = departmentRestrictionType;
    }
    
    // Enviar datos
    const formData = new FormData();
    formData.append('group_id', groupId);
    formData.append('permissions', JSON.stringify(permissions));
    formData.append('restrictions', JSON.stringify(restrictions));
    
    fetch('actions/update_group_permissions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showNotification(data.message || 'Permisos actualizados correctamente', 'success');
            closeModal('permissionsModal');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(data.message || 'Error al actualizar permisos', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showNotification('Error de conexión al guardar permisos', 'error');
    });
}

// ============================================================================
// FUNCIONES DE BÚSQUEDA Y FILTROS
// ============================================================================

let searchTimeout;
function handleAutoSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        document.getElementById('filtersForm')?.submit();
    }, 500);
}

function clearFilters() {
    const searchInput = document.getElementById('search');
    const statusSelect = document.getElementById('status');
    
    if (searchInput) searchInput.value = '';
    if (statusSelect) statusSelect.selectedIndex = 0;
    
    window.location.href = window.location.pathname;
}

// ============================================================================
// FUNCIONES DE UTILIDAD
// ============================================================================

function showNotification(message, type = 'success') {
    // Remover notificaciones existentes
    document.querySelectorAll('.notification').forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideInRight 0.3s ease-out reverse';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }
    }, 4000);
}

function showLoading() {
    // Remover loading existente
    hideLoading();
    
    const loading = document.createElement('div');
    loading.className = 'loading-overlay';
    loading.id = 'loadingOverlay';
    
    loading.innerHTML = `
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Cargando...</div>
        </div>
    `;
    
    document.body.appendChild(loading);
}

function hideLoading() {
    const loading = document.getElementById('loadingOverlay');
    if (loading && loading.parentNode) {
        loading.parentNode.removeChild(loading);
    }
}

// ============================================================================
// FUNCIONES GLOBALES REQUERIDAS
// ============================================================================

// Estas funciones son llamadas desde el HTML, por lo que deben estar en el scope global
window.showCreateGroupModal = showCreateGroupModal;
window.viewGroupDetails = viewGroupDetails;
window.editGroup = editGroup;
window.manageGroupUsers = manageGroupUsers;
window.manageGroupPermissions = manageGroupPermissions;
window.toggleGroupStatus = toggleGroupStatus;
window.addUserToGroup = addUserToGroup;
window.removeUserFromGroup = removeUserFromGroup;
window.clearFilters = clearFilters;

// ============================================================================
// DEBUG Y LOGGING
// ============================================================================

console.log('📋 Funciones del módulo de grupos disponibles:');
console.log('- showCreateGroupModal:', typeof showCreateGroupModal);
console.log('- viewGroupDetails:', typeof viewGroupDetails);
console.log('- editGroup:', typeof editGroup);
console.log('- toggleGroupStatus:', typeof toggleGroupStatus);
console.log('- manageGroupUsers:', typeof manageGroupUsers);
console.log('- manageGroupPermissions:', typeof manageGroupPermissions);