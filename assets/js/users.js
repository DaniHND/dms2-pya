// assets/js/users.js
// JavaScript para el módulo de usuarios - DMS2 (SIN ELIMINAR USUARIOS)

// Variables globales
let currentEditingUser = null;

// Funciones de utilidad para modales
function createModal(id, content) {
    // Verificar si ya existe el modal
    const existingModal = document.getElementById(id);
    if (existingModal) {
        existingModal.remove();
    }
    
    const modal = document.createElement('div');
    modal.id = id;
    modal.className = 'modal-overlay';
    modal.innerHTML = content;
    
    document.body.appendChild(modal);
    
    // Mostrar modal con animación
    setTimeout(() => modal.classList.add('active'), 10);
    
    // Cerrar modal al hacer clic en el overlay
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(id);
        }
    });
    
    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal(id);
        }
    });
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.remove(), 300);
    }
    currentEditingUser = null;
}

// Función para mostrar loading
function showLoading(message = 'Cargando...') {
    const loading = document.createElement('div');
    loading.id = 'loadingOverlay';
    loading.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    `;
    
    loading.innerHTML = `
        <div style="background: white; padding: 30px; border-radius: 8px; text-align: center; max-width: 300px;">
            <div style="width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #6366f1; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p style="margin: 0; color: #374151; font-weight: 500;">${message}</p>
        </div>
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `;
    
    document.body.appendChild(loading);
}

function hideLoading() {
    const loading = document.getElementById('loadingOverlay');
    if (loading) {
        loading.remove();
    }
}

// Función para crear nuevo usuario
function showCreateUserModal() {
    const modalContent = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2><i data-feather="user-plus"></i> Crear Nuevo Usuario</h2>
                <button class="modal-close" onclick="closeModal('createUser')">&times;</button>
            </div>
            
            <form id="createUserForm" onsubmit="handleCreateUser(event)">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstName">Nombre *</label>
                            <input type="text" id="firstName" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="lastName">Apellido *</label>
                            <input type="text" id="lastName" name="last_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Usuario *</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Contraseña *</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Rol *</label>
                            <select id="role" name="role" required>
                                <option value="">Seleccionar rol</option>
                                <option value="admin">Administrador</option>
                                <option value="user">Usuario</option>
                                <option value="viewer">Visualizador</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="company">Empresa *</label>
                            <select id="company" name="company_id" required>
                                <option value="">Seleccionar empresa</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="download_enabled" value="1">
                                <span class="checkmark"></span>
                                Permitir descarga de documentos
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('createUser')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-feather="save"></i>
                        Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    `;
    
    createModal('createUser', modalContent);
    
    // Cargar empresas en el select
    loadCompaniesIntoSelect('company');
    
    // Reemplazar iconos
    setTimeout(() => feather.replace(), 100);
}

// Función para manejar la creación de usuario
function handleCreateUser(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    showLoading('Creando usuario...');
    
    fetch('actions/create_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showNotification('Usuario creado exitosamente', 'success');
            closeModal('createUser');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message || 'Error al crear usuario', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Error de conexión al crear usuario', 'error');
        console.error('Error:', error);
    });
}

// Función para editar usuario
function editUser(userId) {
    showLoading('Cargando datos del usuario...');
    
    fetch(`actions/get_user.php?id=${userId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        
        if (data.success) {
            currentEditingUser = data.user;
            showEditUserModal(data.user);
        } else {
            showNotification(data.message || 'Error al cargar datos del usuario', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Error de conexión al cargar datos del usuario', 'error');
        console.error('Error:', error);
    });
}

// Función para mostrar modal de edición
function showEditUserModal(user) {
    const modalContent = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2><i data-feather="edit"></i> Editar Usuario</h2>
                <button class="modal-close" onclick="closeModal('editUser')">&times;</button>
            </div>
            
            <form id="editUserForm" onsubmit="handleEditUser(event)">
                <input type="hidden" name="user_id" value="${user.id}">
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="editFirstName">Nombre *</label>
                            <input type="text" id="editFirstName" name="first_name" value="${user.first_name || ''}" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="editLastName">Apellido *</label>
                            <input type="text" id="editLastName" name="last_name" value="${user.last_name || ''}" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="editUsername">Usuario *</label>
                            <input type="text" id="editUsername" name="username" value="${user.username || ''}" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="editEmail">Email *</label>
                            <input type="email" id="editEmail" name="email" value="${user.email || ''}" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="editRole">Rol *</label>
                            <select id="editRole" name="role" required>
                                <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Administrador</option>
                                <option value="user" ${user.role === 'user' ? 'selected' : ''}>Usuario</option>
                                <option value="viewer" ${user.role === 'viewer' ? 'selected' : ''}>Visualizador</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="editCompany">Empresa *</label>
                            <select id="editCompany" name="company_id" required>
                                <option value="">Seleccionar empresa</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="download_enabled" value="1" ${user.download_enabled ? 'checked' : ''}>
                                <span class="checkmark"></span>
                                Permitir descarga de documentos
                            </label>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="checkbox-label">
                                <input type="checkbox" id="changePassword" name="change_password" onchange="togglePasswordField()">
                                <span class="checkmark"></span>
                                Cambiar contraseña
                            </label>
                        </div>
                        
                        <div class="form-group" id="passwordField" style="display: none;">
                            <label for="newPassword">Nueva Contraseña</label>
                            <input type="password" id="newPassword" name="password" placeholder="Nueva contraseña">
                        </div>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('editUser')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        <i data-feather="save"></i>
                        Actualizar Usuario
                    </button>
                </div>
            </form>
        </div>
    `;
    
    createModal('editUser', modalContent);
    
    // Cargar empresas y seleccionar la actual
    loadCompaniesIntoSelect('editCompany', user.company_id);
    
    // Reemplazar iconos
    setTimeout(() => feather.replace(), 100);
}

// Función para alternar campo de contraseña
function togglePasswordField() {
    const checkbox = document.getElementById('changePassword');
    const passwordField = document.getElementById('passwordField');
    const passwordInput = document.getElementById('newPassword');
    
    if (checkbox.checked) {
        passwordField.style.display = 'block';
        passwordInput.required = true;
    } else {
        passwordField.style.display = 'none';
        passwordInput.required = false;
        passwordInput.value = '';
    }
}

// Función para manejar la edición de usuario
function handleEditUser(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    showLoading('Actualizando usuario...');
    
    fetch('actions/update_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showNotification('Usuario actualizado exitosamente', 'success');
            closeModal('editUser');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message || 'Error al actualizar usuario', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Error de conexión al actualizar usuario', 'error');
        console.error('Error:', error);
    });
}

// Función para cargar empresas en un select
function loadCompaniesIntoSelect(selectId, selectedValue = null) {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    // Limpiar opciones existentes (excepto la primera)
    while (select.children.length > 1) {
        select.removeChild(select.lastChild);
    }
    
    // Cargar empresas desde la variable global
    if (window.companiesData) {
        window.companiesData.forEach(company => {
            const option = document.createElement('option');
            option.value = company.id;
            option.textContent = company.name;
            if (selectedValue && company.id == selectedValue) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }
}

// Función para mostrar detalles del usuario
function showUserDetails(userId) {
    showLoading('Cargando detalles...');
    
    fetch(`actions/get_user.php?id=${userId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        
        if (data.success) {
            const user = data.user;
            const modalContent = `
                <div class="modal-content" style="max-width: 700px;">
                    <div class="modal-header">
                        <h2><i data-feather="user"></i> Detalles del Usuario</h2>
                        <button class="modal-close" onclick="closeModal('userDetails')">&times;</button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="user-details-container">
                            <div class="user-info-header">
                                <div class="user-avatar-large">
                                    ${(user.first_name || 'U').charAt(0).toUpperCase()}
                                </div>
                                <div class="user-info-text">
                                    <h3>${user.first_name} ${user.last_name}</h3>
                                    <p>@${user.username}</p>
                                    <span class="badge badge-${user.status}">${user.status === 'active' ? 'Activo' : 'Inactivo'}</span>
                                </div>
                            </div>
                            
                            <div class="user-details-grid">
                                <div class="detail-item">
                                    <label>Email</label>
                                    <value>${user.email}</value>
                                </div>
                                
                                <div class="detail-item">
                                    <label>Rol</label>
                                    <value><span class="badge badge-${user.role}">${user.role}</span></value>
                                </div>
                                
                                <div class="detail-item">
                                    <label>Empresa</label>
                                    <value>${user.company_name || 'Sin empresa'}</value>
                                </div>
                                
                                <div class="detail-item">
                                    <label>Fecha de Creación</label>
                                    <value>${new Date(user.created_at).toLocaleDateString('es-ES')}</value>
                                </div>
                                
                                <div class="detail-item">
                                    <label>Último Acceso</label>
                                    <value>${user.last_login ? new Date(user.last_login).toLocaleDateString('es-ES') : 'Nunca'}</value>
                                </div>
                            </div>
                            
                            <div class="permissions-section">
                                <h4>Permisos</h4>
                                <div class="permission-item ${user.download_enabled ? 'enabled' : 'disabled'}">
                                    <i data-feather="download"></i>
                                    <span>Descarga de documentos</span>
                                    <div class="permission-status">
                                        ${user.download_enabled ? 'Habilitado' : 'Deshabilitado'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('userDetails')">
                            Cerrar
                        </button>
                        <button type="button" class="btn-primary" onclick="closeModal('userDetails'); editUser(${userId})">
                            <i data-feather="edit"></i>
                            Editar Usuario
                        </button>
                    </div>
                </div>
            `;
            
            createModal('userDetails', modalContent);
            
            // Reemplazar iconos después de crear el modal
            setTimeout(() => feather.replace(), 100);
        } else {
            showNotification(data.message || 'Error al cargar detalles', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Error de conexión al cargar detalles', 'error');
        console.error('Error:', error);
    });
}

// Función para cambiar el estado del usuario
function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'activar' : 'desactivar';
    
    if (confirm(`¿Está seguro que desea ${action} este usuario?`)) {
        showLoading(`${action === 'activar' ? 'Activando' : 'Desactivando'} usuario...`);
        
        fetch('actions/toggle_user_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId,
                status: newStatus
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            hideLoading();
            
            if (data.success) {
                showNotification(`Usuario ${action === 'activar' ? 'activado' : 'desactivado'} exitosamente`, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showNotification(data.message || 'Error al cambiar estado', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showNotification('Error de conexión al cambiar estado', 'error');
            console.error('Error:', error);
        });
    }
}