/* ============================================================================
   USERS.JS - JAVASCRIPT CORREGIDO PARA MÓDULO DE USUARIOS
   Arregla todos los problemas de botones y funcionalidad
   ============================================================================ */

// Variables globales
let currentModal = null;
let companiesData = [];
let currentPage = 1;
let totalPages = 1;
let currentFilters = {};

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Iniciando módulo de usuarios...');
    
    // Cargar datos de empresas si están disponibles
    if (window.userData && window.userData.companies) {
        companiesData = window.userData.companies;
        console.log('✅ Empresas cargadas:', companiesData.length);
    }
    
    // Inicializar todos los componentes
    initializeUsers();
    initializeEventListeners();
    initializeCheckboxes();
    
    console.log('✅ Módulo de usuarios inicializado correctamente');
});

function initializeUsers() {
    console.log('⚙️ Inicializando componentes del módulo...');
    
    // Inicializar filtros
    initializeFilters();
    
    // Cargar usuarios si la función existe
    if (typeof loadUsers === 'function') {
        loadUsers();
    }
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
    
    // Eventos para botones de filtros
    const filterBtn = document.querySelector('.btn-filter');
    if (filterBtn) {
        filterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('📊 Aplicando filtros...');
            applyFilters();
        });
    }
    
    const clearBtn = document.querySelector('.btn-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🧹 Limpiando filtros...');
            clearFilters();
        });
    }
}

// ============================================================================
// FUNCIONES DE MODAL - ARREGLADAS
// ============================================================================

function openCreateUserModal() {
    console.log('👤 Abriendo modal de crear usuario...');
    
    // Crear el HTML del modal
    const modalHTML = `
        <div class="modal active" id="createUserModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Crear Nuevo Usuario</h3>
                    <button type="button" class="modal-close" onclick="closeCreateUserModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="createUserForm" class="modal-form" onsubmit="handleCreateUser(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">Nombre *</label>
                                <input type="text" id="first_name" name="first_name" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Apellido *</label>
                                <input type="text" id="last_name" name="last_name" class="form-input" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Usuario *</label>
                                <input type="text" id="username" name="username" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" class="form-input" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="role">Rol *</label>
                                <select id="role" name="role" class="form-input" required>
                                    <option value="">Seleccionar rol</option>
                                    <option value="admin">Administrador</option>
                                    <option value="user">Usuario</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="company_id">Empresa</label>
                                <select id="company_id" name="company_id" class="form-input">
                                    <option value="">Sin empresa</option>
                                    ${getCompanyOptions()}
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Contraseña *</label>
                                <input type="password" id="password" name="password" class="form-input" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirmar Contraseña *</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input" required minlength="6">
                            </div>
                        </div>
                        

                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeCreateUserModal()">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="user-plus"></i>
                                Crear Usuario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    // Insertar modal en el DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    currentModal = document.getElementById('createUserModal');
    
    // Inicializar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    console.log('✅ Modal de crear usuario abierto');
}

function closeCreateUserModal() {
    console.log('❌ Cerrando modal de crear usuario...');
    const modal = document.getElementById('createUserModal');
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

function handleCreateUser(event) {
    event.preventDefault();
    console.log('📝 Procesando creación de usuario...');
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validar contraseñas
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    
    if (password !== confirmPassword) {
        alert('Las contraseñas no coinciden');
        return;
    }
    
    if (password.length < 6) {
        alert('La contraseña debe tener al menos 6 caracteres');
        return;
    }
    
    // Mostrar estado de carga
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Creando...';
    submitBtn.disabled = true;
    
    // Reemplazar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Enviar datos
    fetch('actions/create_user.php', {
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
            alert('Usuario creado exitosamente');
            closeCreateUserModal();
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Error al crear usuario'));
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
// ACCIONES DE USUARIO - ARREGLADAS
// ============================================================================

function showUserDetails(userId) {
    console.log('👁️ Mostrando detalles del usuario:', userId);
    
    if (!userId) {
        console.error('❌ ID de usuario no válido');
        return;
    }
    
    // Mostrar loading
    const loadingModal = createLoadingModal('Cargando detalles del usuario...');
    document.body.appendChild(loadingModal);
    
    // Obtener detalles del usuario
    fetch(`actions/get_user_details.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            loadingModal.remove();
            
            if (data.success && data.user) {
                openUserDetailsModal(data.user);
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

function editUser(userId) {
    console.log('✏️ Editando usuario:', userId);
    
    if (!userId) {
        console.error('❌ ID de usuario no válido');
        return;
    }
    
    // Mostrar loading
    const loadingModal = createLoadingModal('Cargando datos del usuario...');
    document.body.appendChild(loadingModal);
    
    // Obtener datos del usuario
    fetch(`actions/get_user.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            loadingModal.remove();
            
            if (data.success && data.user) {
                openEditUserModal(data.user);
            } else {
                alert('Error: ' + (data.message || 'No se pudieron cargar los datos del usuario'));
            }
        })
        .catch(error => {
            console.error('❌ Error:', error);
            loadingModal.remove();
            alert('Error de conexión');
        });
}

function toggleUserStatus(userId, currentStatus) {
    console.log('🔄 Cambiando estado del usuario:', userId, 'Estado actual:', currentStatus);
    
    if (!userId) {
        console.error('❌ ID de usuario no válido');
        return;
    }
    
    const action = currentStatus === 'active' ? 'desactivar' : 'activar';
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    
    if (!confirm(`¿Está seguro que desea ${action} este usuario?`)) {
        return;
    }
    
    // Enviar petición
    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('new_status', newStatus);
    
    fetch('actions/toggle_user_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('📥 Respuesta:', data);
        
        if (data.success) {
            alert(`Usuario ${action}ado exitosamente`);
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Error al cambiar el estado del usuario'));
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        alert('Error de conexión');
    });
}

function deleteUser(userId) {
    console.log('🗑️ Eliminando usuario:', userId);
    
    if (!userId) {
        console.error('❌ ID de usuario no válido');
        return;
    }
    
    if (!confirm('¿Está seguro que desea eliminar este usuario? Esta acción no se puede deshacer.')) {
        return;
    }
    
    // Confirmación adicional
    if (!confirm('Esta acción eliminará permanentemente el usuario y todos sus datos asociados. ¿Confirma que desea continuar?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('user_id', userId);
    
    fetch('actions/delete_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('📥 Respuesta:', data);
        
        if (data.success) {
            alert('Usuario eliminado exitosamente');
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Error al eliminar el usuario'));
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        alert('Error de conexión');
    });
}

// ============================================================================
// MODALES ADICIONALES
// ============================================================================

function openUserDetailsModal(user) {
    const modalHTML = `
        <div class="modal active" id="userDetailsModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Detalles del Usuario</h3>
                    <button type="button" class="modal-close" onclick="closeUserDetailsModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="user-details-grid">
                        <div class="detail-item">
                            <label>Nombre Completo:</label>
                            <span>${user.first_name} ${user.last_name}</span>
                        </div>
                        <div class="detail-item">
                            <label>Usuario:</label>
                            <span>${user.username}</span>
                        </div>
                        <div class="detail-item">
                            <label>Email:</label>
                            <span>${user.email}</span>
                        </div>
                        <div class="detail-item">
                            <label>Rol:</label>
                            <span class="role-badge role-${user.role}">${user.role}</span>
                        </div>
                        <div class="detail-item">
                            <label>Estado:</label>
                            <span class="status-badge status-${user.status}">${user.status}</span>
                        </div>
                        <div class="detail-item">
                            <label>Empresa:</label>
                            <span>${user.company_name || 'Sin empresa'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Descarga Habilitada:</label>
                            <span>${user.download_enabled ? 'Sí' : 'No'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Creado:</label>
                            <span>${formatDate(user.created_at)}</span>
                        </div>
                        ${user.last_login ? `
                        <div class="detail-item">
                            <label>Último Acceso:</label>
                            <span>${formatDate(user.last_login)}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeUserDetailsModal()">
                        Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="editUser(${user.id})">
                        <i data-feather="edit"></i>
                        Editar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    currentModal = document.getElementById('userDetailsModal');
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function closeUserDetailsModal() {
    const modal = document.getElementById('userDetailsModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            currentModal = null;
        }, 300);
    }
}

function openEditUserModal(user) {
    const modalHTML = `
        <div class="modal active" id="editUserModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Editar Usuario</h3>
                    <button type="button" class="modal-close" onclick="closeEditUserModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm" class="modal-form" onsubmit="handleEditUser(event)">
                        <input type="hidden" name="user_id" value="${user.id}">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_first_name">Nombre *</label>
                                <input type="text" id="edit_first_name" name="first_name" class="form-input" value="${user.first_name}" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_last_name">Apellido *</label>
                                <input type="text" id="edit_last_name" name="last_name" class="form-input" value="${user.last_name}" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_username">Usuario *</label>
                                <input type="text" id="edit_username" name="username" class="form-input" value="${user.username}" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_email">Email *</label>
                                <input type="email" id="edit_email" name="email" class="form-input" value="${user.email}" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_role">Rol *</label>
                                <select id="edit_role" name="role" class="form-input" required>
                                    <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Administrador</option>
                                    <option value="user" ${user.role === 'user' ? 'selected' : ''}>Usuario</option>
                                    <option value="viewer" ${user.role === 'viewer' ? 'selected' : ''}>Visualizador</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit_status">Estado *</label>
                                <select id="edit_status" name="status" class="form-input" required>
                                    <option value="active" ${user.status === 'active' ? 'selected' : ''}>Activo</option>
                                    <option value="inactive" ${user.status === 'inactive' ? 'selected' : ''}>Inactivo</option>
                                    <option value="suspended" ${user.status === 'suspended' ? 'selected' : ''}>Suspendido</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_company_id">Empresa</label>
                                <select id="edit_company_id" name="company_id" class="form-input">
                                    <option value="">Sin empresa</option>
                                    ${getCompanyOptions(user.company_id)}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="download_enabled" value="1" ${user.download_enabled ? 'checked' : ''}>
                                    Permitir descarga de documentos
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="changePassword" name="change_password" value="1">
                                    Cambiar contraseña
                                </label>
                            </div>
                        </div>
                        
                        <div id="passwordFields" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_password">Nueva Contraseña</label>
                                    <input type="password" id="edit_password" name="password" class="form-input" minlength="6">
                                </div>
                                <div class="form-group">
                                    <label for="edit_confirm_password">Confirmar Contraseña</label>
                                    <input type="password" id="edit_confirm_password" name="confirm_password" class="form-input" minlength="6">
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">
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
    currentModal = document.getElementById('editUserModal');
    
    // Configurar checkbox de cambiar contraseña
    const changePasswordCheckbox = document.getElementById('changePassword');
    const passwordFields = document.getElementById('passwordFields');
    
    changePasswordCheckbox.addEventListener('change', function() {
        if (this.checked) {
            passwordFields.style.display = 'block';
            document.getElementById('edit_password').required = true;
            document.getElementById('edit_confirm_password').required = true;
        } else {
            passwordFields.style.display = 'none';
            document.getElementById('edit_password').required = false;
            document.getElementById('edit_confirm_password').required = false;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_confirm_password').value = '';
        }
    });
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function closeEditUserModal() {
    const modal = document.getElementById('editUserModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            currentModal = null;
        }, 300);
    }
}

function handleEditUser(event) {
    event.preventDefault();
    console.log('📝 Procesando edición de usuario...');
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validar contraseñas si se van a cambiar
    if (formData.get('change_password')) {
        const password = formData.get('password');
        const confirmPassword = formData.get('confirm_password');
        
        if (password !== confirmPassword) {
            alert('Las contraseñas no coinciden');
            return;
        }
        
        if (password.length < 6) {
            alert('La contraseña debe tener al menos 6 caracteres');
            return;
        }
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
    fetch('actions/update_user.php', {
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
            alert('Usuario actualizado exitosamente');
            closeEditUserModal();
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Error al actualizar usuario'));
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
// FILTROS
// ============================================================================

function initializeFilters() {
    console.log('🔧 Inicializando filtros...');
    
    const filtersForm = document.querySelector('.filters-form');
    if (!filtersForm) return;
    
    // Obtener filtros actuales de la URL
    const urlParams = new URLSearchParams(window.location.search);
    currentFilters = {
        search: urlParams.get('search') || '',
        role: urlParams.get('role') || '',
        status: urlParams.get('status') || '',
        company_id: urlParams.get('company_id') || '',
        date_from: urlParams.get('date_from') || '',
        date_to: urlParams.get('date_to') || ''
    };
    
    // Aplicar filtros a los campos
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
    
    // Construir parámetros de filtros
    for (let [key, value] of formData.entries()) {
        if (value.trim()) {
            params.append(key, value.trim());
        }
    }
    
    // Mantener la página actual si existe
    if (currentPage > 1) {
        params.append('page', currentPage);
    }
    
    // Redirigir con filtros
    const newUrl = window.location.pathname + '?' + params.toString();
    window.location.href = newUrl;
}

function clearFilters() {
    console.log('🧹 Limpiando filtros...');
    
    const filtersForm = document.querySelector('.filters-form');
    if (filtersForm) {
        filtersForm.reset();
    }
    
    // Redirigir sin parámetros
    window.location.href = window.location.pathname;
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

function getCompanyOptions(selectedId = null) {
    if (!companiesData || companiesData.length === 0) {
        return '<option value="">No hay empresas disponibles</option>';
    }
    
    return companiesData.map(company => 
        `<option value="${company.id}" ${selectedId == company.id ? 'selected' : ''}>
            ${company.name}
        </option>`
    ).join('');
}

function createLoadingModal(message = 'Cargando...') {
    const modalHTML = `
        <div class="modal active" id="loadingModal">
            <div class="modal-content" style="max-width: 300px; text-align: center;">
                <div class="modal-body">
                    <div class="loading-spinner" style="margin: 20px auto;"></div>
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
// FUNCIONES DE CHECKBOXES - ARREGLADAS
// ============================================================================

function initializeCheckboxes() {
    console.log('🔧 Inicializando checkboxes...');
    
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    
    checkboxes.forEach((checkbox, index) => {
        console.log(`📋 Checkbox ${index + 1}: ${checkbox.id || checkbox.name || 'sin ID'}`);
        
        // Remover event listeners previos
        checkbox.removeEventListener('change', handleCheckboxChange);
        
        // Agregar nuevo event listener
        checkbox.addEventListener('change', handleCheckboxChange);
        
        // Aplicar estilos visuales iniciales
        updateCheckboxVisual(checkbox);
    });
    
    console.log(`✅ ${checkboxes.length} checkboxes inicializados`);
}

function handleCheckboxChange(event) {
    const checkbox = event.target;
    const checkboxName = checkbox.id || checkbox.name || 'desconocido';
    const isChecked = checkbox.checked;
    
    console.log(`🔄 Checkbox cambiado: ${checkboxName} = ${isChecked ? 'marcado' : 'desmarcado'}`);
    
    // Manejar checkbox específicos
    if (checkbox.id === 'changePassword') {
        togglePasswordFields();
    }
    
    if (checkbox.name === 'download_enabled') {
        console.log(`📥 Descarga ${isChecked ? 'habilitada' : 'deshabilitada'}`);
    }
    
    // Actualizar visual
    updateCheckboxVisual(checkbox);
}

function updateCheckboxVisual(checkbox) {
    const container = checkbox.closest('.checkbox-container') || checkbox.parentElement;
    
    if (container) {
        if (checkbox.checked) {
            container.classList.add('checked');
        } else {
            container.classList.remove('checked');
        }
    }
}

function togglePasswordFields() {
    const checkbox = document.getElementById('changePassword');
    const passwordFields = document.getElementById('passwordFields');
    
    if (!checkbox || !passwordFields) return;
    
    console.log('🔑 Ejecutando togglePasswordFields...');
    
    if (checkbox.checked) {
        passwordFields.style.display = 'block';
        const passwordInput = document.getElementById('edit_password');
        const confirmInput = document.getElementById('edit_confirm_password');
        
        if (passwordInput) passwordInput.required = true;
        if (confirmInput) confirmInput.required = true;
        
        console.log('✅ Campos de contraseña mostrados');
    } else {
        passwordFields.style.display = 'none';
        const passwordInput = document.getElementById('edit_password');
        const confirmInput = document.getElementById('edit_confirm_password');
        
        if (passwordInput) {
            passwordInput.required = false;
            passwordInput.value = '';
        }
        if (confirmInput) {
            confirmInput.required = false;
            confirmInput.value = '';
        }
        
        console.log('✅ Campos de contraseña ocultados');
    }
}

// ============================================================================
// EXPORTAR FUNCIONES GLOBALES
// ============================================================================

// Hacer funciones disponibles globalmente
window.openCreateUserModal = openCreateUserModal;
window.closeCreateUserModal = closeCreateUserModal;
window.showUserDetails = showUserDetails;
window.editUser = editUser;
window.toggleUserStatus = toggleUserStatus;
window.deleteUser = deleteUser;
window.handleCreateUser = handleCreateUser;
window.handleEditUser = handleEditUser;
window.applyFilters = applyFilters;
window.clearFilters = clearFilters;
window.togglePasswordFields = togglePasswordFields;

console.log('✅ Todas las funciones exportadas correctamente');