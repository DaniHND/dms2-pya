// assets/js/users.js
// JavaScript para el módulo de usuarios - DMS2 (COMPLETO Y CORREGIDO)

// Función para mostrar modal de crear usuario
function showCreateUserModal() {
    showModal('createUser', 'Crear Nuevo Usuario', `
        <form id="createUserForm" onsubmit="handleCreateUser(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">Nombre</label>
                    <input type="text" id="firstName" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="lastName">Apellido</label>
                    <input type="text" id="lastName" name="last_name" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="role">Rol</label>
                    <select id="role" name="role" required>
                        <option value="">Seleccionar rol</option>
                        <option value="admin">Administrador</option>
                        <option value="user">Usuario</option>
                        <option value="viewer">Visualizador</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="company">Empresa</label>
                    <select id="company" name="company_id" required>
                        <option value="">Seleccionar empresa</option>
                        ${getCompanyOptions()}
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirmar Contraseña</label>
                    <input type="password" id="confirmPassword" name="confirm_password" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="checkbox-container">
                    <input type="checkbox" id="downloadEnabled" name="download_enabled" checked>
                    <span class="checkmark"></span>
                    Permitir descarga de documentos
                </label>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('createUser')">
                    Cancelar
                </button>
                <button type="submit" class="btn-primary">
                    <i data-feather="user-plus"></i>
                    Crear Usuario
                </button>
            </div>
        </form>
    `);
    
    // Reemplazar iconos después de crear el modal
    setTimeout(() => feather.replace(), 100);
}

// Función para manejar la creación de usuario
function handleCreateUser(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    
    if (password !== confirmPassword) {
        showNotification('Las contraseñas no coinciden', 'error');
        return;
    }
    
    if (password.length < 6) {
        showNotification('La contraseña debe tener al menos 6 caracteres', 'error');
        return;
    }
    
    // Mostrar loading
    showLoading('Creando usuario...');
    
    // Enviar datos
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
        showNotification('Error de conexión', 'error');
        console.error('Error:', error);
    });
}

// Función para editar usuario
function editUser(userId) {
    showLoading('Cargando datos del usuario...');
    
    fetch(`actions/get_user.php?id=${userId}`)
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            const user = data.user;
            showModal('editUser', 'Editar Usuario', `
                <form id="editUserForm" onsubmit="handleEditUser(event, ${userId})">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editFirstName">Nombre</label>
                            <input type="text" id="editFirstName" name="first_name" value="${escapeHtml(user.first_name)}" required>
                        </div>
                        <div class="form-group">
                            <label for="editLastName">Apellido</label>
                            <input type="text" id="editLastName" name="last_name" value="${escapeHtml(user.last_name)}" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editUsername">Usuario</label>
                            <input type="text" id="editUsername" name="username" value="${escapeHtml(user.username)}" required>
                        </div>
                        <div class="form-group">
                            <label for="editEmail">Email</label>
                            <input type="email" id="editEmail" name="email" value="${escapeHtml(user.email)}" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editRole">Rol</label>
                            <select id="editRole" name="role" required>
                                <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Administrador</option>
                                <option value="user" ${user.role === 'user' ? 'selected' : ''}>Usuario</option>
                                <option value="viewer" ${user.role === 'viewer' ? 'selected' : ''}>Visualizador</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editCompany">Empresa</label>
                            <select id="editCompany" name="company_id" required>
                                ${getCompanyOptions(user.company_id)}
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-container">
                            <input type="checkbox" id="editDownloadEnabled" name="download_enabled" ${user.download_enabled ? 'checked' : ''}>
                            <span class="checkmark"></span>
                            Permitir descarga de documentos
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-container">
                            <input type="checkbox" id="changePassword" name="change_password" onchange="togglePasswordFields()">
                            <span class="checkmark"></span>
                            Cambiar contraseña
                        </label>
                    </div>
                    
                    <div id="passwordFields" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="editPassword">Nueva Contraseña</label>
                                <input type="password" id="editPassword" name="password">
                            </div>
                            <div class="form-group">
                                <label for="editConfirmPassword">Confirmar Contraseña</label>
                                <input type="password" id="editConfirmPassword" name="confirm_password">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('editUser')">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-primary">
                            <i data-feather="save"></i>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            `);
            
            // Reemplazar iconos después de crear el modal
            setTimeout(() => feather.replace(), 100);
        } else {
            showNotification(data.message || 'Error al cargar usuario', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Error de conexión', 'error');
        console.error('Error:', error);
    });
}

// Función para manejar la edición de usuario
function handleEditUser(event, userId) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const changePassword = formData.get('change_password');
    
    if (changePassword) {
        const password = formData.get('password');
        const confirmPassword = formData.get('confirm_password');
        
        if (password !== confirmPassword) {
            showNotification('Las contraseñas no coinciden', 'error');
            return;
        }
        
        if (password.length < 6) {
            showNotification('La contraseña debe tener al menos 6 caracteres', 'error');
            return;
        }
    }
    
    // Agregar ID del usuario
    formData.append('user_id', userId);
    
    // Mostrar loading
    showLoading('Actualizando usuario...');
    
    // Enviar datos
    fetch('actions/update_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
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
        showNotification('Error de conexión', 'error');
        console.error('Error:', error);
    });
}

// Función para mostrar detalles del usuario
function showUserDetails(userId) {
    showLoading('Cargando detalles del usuario...');
    
    fetch(`actions/get_user_details.php?id=${userId}`)
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            const user = data.user;
            const stats = data.stats;
            
            showModal('userDetails', 'Detalles del Usuario', `
                <div class="user-details-content">
                    <div class="user-profile">
                        <div class="user-avatar-large">
                            <i data-feather="user"></i>
                        </div>
                        <div class="user-info-detailed">
                            <h3>${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</h3>
                            <p>@${escapeHtml(user.username)}</p>
                            <p>${escapeHtml(user.email)}</p>
                        </div>
                    </div>
                    
                    <div class="user-stats-grid">
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i data-feather="activity"></i>
                            </div>
                            <div class="stat-data">
                                <span class="stat-number">${stats.total_activities}</span>
                                <span class="stat-label">Actividades</span>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i data-feather="file"></i>
                            </div>
                            <div class="stat-data">
                                <span class="stat-number">${stats.total_documents}</span>
                                <span class="stat-label">Documentos</span>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i data-feather="download"></i>
                            </div>
                            <div class="stat-data">
                                <span class="stat-number">${stats.total_downloads}</span>
                                <span class="stat-label">Descargas</span>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i data-feather="clock"></i>
                            </div>
                            <div class="stat-data">
                                <span class="stat-number">${stats.days_since_last_login}</span>
                                <span class="stat-label">Días desde último acceso</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-details-info">
                        <div class="info-section">
                            <h4>Información General</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Empresa:</span>
                                    <span class="info-value">${escapeHtml(user.company_name || 'Sin empresa')}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Rol:</span>
                                    <span class="info-value">${escapeHtml(user.role)}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Estado:</span>
                                    <span class="info-value status-${user.status}">${user.status === 'active' ? 'Activo' : 'Inactivo'}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Fecha de registro:</span>
                                    <span class="info-value">${formatDate(user.created_at)}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <h4>Permisos</h4>
                            <div class="permissions-grid">
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
            `);
            
            // Reemplazar iconos después de crear el modal
            setTimeout(() => feather.replace(), 100);
        } else {
            showNotification(data.message || 'Error al cargar detalles', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Error de conexión', 'error');
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
        .then(response => response.json())
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
            showNotification('Error de conexión', 'error');
            console.error('Error:', error);
        });
    }
}

// Función para eliminar usuario
function deleteUser(userId) {
    if (confirm('¿Está seguro que desea eliminar este usuario? Esta acción no se puede deshacer.')) {
        showLoading('Eliminando usuario...');
        
        fetch('actions/delete_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                showNotification('Usuario eliminado exitosamente', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showNotification(data.message || 'Error al eliminar usuario', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showNotification('Error de conexión', 'error');
            console.error('Error:', error);
        });
    }
}

// Función para obtener opciones de empresas (CORREGIDA)
function getCompanyOptions(selectedId = null) {
    let options = '<option value="">Seleccionar empresa</option>';
    
    if (window.companiesData && Array.isArray(window.companiesData)) {
        window.companiesData.forEach(company => {
            const selected = selectedId == company.id ? 'selected' : '';
            options += `<option value="${company.id}" ${selected}>${escapeHtml(company.name)}</option>`;
        });
    }
    
    return options;
}

// Función para alternar campos de contraseña
function togglePasswordFields() {
    const passwordFields = document.getElementById('passwordFields');
    const changePassword = document.getElementById('changePassword');
    
    if (changePassword && passwordFields) {
        if (changePassword.checked) {
            passwordFields.style.display = 'block';
            const editPassword = document.getElementById('editPassword');
            const editConfirmPassword = document.getElementById('editConfirmPassword');
            if (editPassword) editPassword.required = true;
            if (editConfirmPassword) editConfirmPassword.required = true;
        } else {
            passwordFields.style.display = 'none';
            const editPassword = document.getElementById('editPassword');
            const editConfirmPassword = document.getElementById('editConfirmPassword');
            if (editPassword) editPassword.required = false;
            if (editConfirmPassword) editConfirmPassword.required = false;
        }
    }
}

// Función para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Función para mostrar modal genérico
function showModal(id, title, content) {
    // Remover modal existente
    const existingModal = document.getElementById(id + 'Modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    const modal = document.createElement('div');
    modal.id = id + 'Modal';
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>${escapeHtml(title)}</h3>
                <button class="modal-close" onclick="closeModal('${id}')">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body">
                ${content}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'flex';
    
    // Cerrar al hacer clic fuera
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(id);
        }
    });
    
    // Reemplazar iconos
    setTimeout(() => feather.replace(), 100);
}

// Función para cerrar modal
function closeModal(id) {
    const modal = document.getElementById(id + 'Modal');
    if (modal) {
        modal.remove();
    }
}

// Función para mostrar loading
function showLoading(message = 'Cargando...') {
    // Remover loading existente
    hideLoading();
    
    const loading = document.createElement('div');
    loading.id = 'loadingModal';
    loading.className = 'modal loading-modal';
    loading.innerHTML = `
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <p>${escapeHtml(message)}</p>
        </div>
    `;
    
    document.body.appendChild(loading);
    loading.style.display = 'flex';
}

// Función para ocultar loading
function hideLoading() {
    const loading = document.getElementById('loadingModal');
    if (loading) {
        loading.remove();
    }
}

// Función para formatear fecha
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return 'Fecha inválida';
    }
}