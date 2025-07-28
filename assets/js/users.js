// assets/js/users.js - CÓDIGO COMPLETO CON FIX DE CHECKBOXES
// REEMPLAZA TODO EL CONTENIDO DE users.js CON ESTE CÓDIGO

// ================================
// VARIABLES GLOBALES
// ================================
let currentPage = 1;
let totalPages = 1;
let currentFilters = {};

// ================================
// INICIALIZACIÓN
// ================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Módulo de usuarios cargado');
    initializeUsers();
    initializeCheckboxes();
});

function initializeUsers() {
    // Inicializar filtros si existen
    initializeFilters();
    
    // Cargar usuarios si la función existe
    if (typeof loadUsers === 'function') {
        loadUsers();
    }
    
    console.log('✅ Módulo de usuarios inicializado');
}

// ================================
// FIX ESPECÍFICO PARA CHECKBOXES
// ================================

function initializeCheckboxes() {
    console.log('🔧 Inicializando checkboxes...');
    
    const checkboxes = document.querySelectorAll('.checkbox-container input[type="checkbox"]');
    
    checkboxes.forEach((checkbox, index) => {
        console.log(`📋 Checkbox ${index + 1}: ${checkbox.id || checkbox.name || 'sin ID'}`);
        
        // Remover event listeners previos para evitar duplicados
        checkbox.removeEventListener('change', handleCheckboxChange);
        
        // Agregar nuevo event listener
        checkbox.addEventListener('change', handleCheckboxChange);
        
        // Aplicar estilos visuales iniciales
        updateCheckboxVisual(checkbox);
        
        // Manejar específicamente el checkbox de cambiar contraseña
        if (checkbox.id === 'changePassword') {
            togglePasswordFields();
        }
    });
    
    console.log(`✅ ${checkboxes.length} checkboxes inicializados correctamente`);
}

function handleCheckboxChange(event) {
    const checkbox = event.target;
    const checkboxName = checkbox.id || checkbox.name || 'desconocido';
    const isChecked = checkbox.checked;
    
    console.log(`🔄 Checkbox cambiado: ${checkboxName} = ${isChecked ? 'marcado' : 'desmarcado'}`);
    
    // Manejar específicamente el checkbox de cambiar contraseña
    if (checkbox.id === 'changePassword') {
        console.log('🔑 Ejecutando togglePasswordFields...');
        togglePasswordFields();
    }
    
    // Manejar checkbox de descarga
    if (checkbox.id === 'downloadEnabled' || checkbox.id === 'editDownloadEnabled') {
        console.log(`📥 Descarga ${isChecked ? 'habilitada' : 'deshabilitada'}`);
    }
    
    // Actualizar apariencia visual
    updateCheckboxVisual(checkbox);
}

function updateCheckboxVisual(checkbox) {
    const checkmark = checkbox.nextElementSibling;
    if (checkmark && checkmark.classList.contains('checkmark')) {
        if (checkbox.checked) {
            checkmark.style.background = '#6366f1';
            checkmark.style.borderColor = '#6366f1';
        } else {
            checkmark.style.background = '#ffffff';
            checkmark.style.borderColor = '#d1d5db';
        }
    }
}

// ================================
// FUNCIÓN PARA MOSTRAR/OCULTAR CONTRASEÑAS
// ================================

function togglePasswordFields() {
    console.log('🔑 Ejecutando togglePasswordFields...');
    
    const checkbox = document.getElementById('changePassword');
    const passwordFields = document.getElementById('passwordFields');
    
    if (!checkbox) {
        console.error('❌ No se encontró el checkbox changePassword');
        return;
    }
    
    if (!passwordFields) {
        console.error('❌ No se encontró el contenedor passwordFields');
        return;
    }
    
    console.log(`🔍 Checkbox estado: ${checkbox.checked ? 'marcado' : 'desmarcado'}`);
    
    if (checkbox.checked) {
        // Mostrar campos de contraseña
        passwordFields.style.display = 'block';
        passwordFields.style.opacity = '1';
        passwordFields.classList.add('show');
        
        // Hacer requeridos los campos
        const passwordInput = document.getElementById('editPassword');
        const confirmPasswordInput = document.getElementById('editConfirmPassword');
        
        if (passwordInput) {
            passwordInput.required = true;
            console.log('✅ Campo password marcado como requerido');
        }
        if (confirmPasswordInput) {
            confirmPasswordInput.required = true;
            console.log('✅ Campo confirm password marcado como requerido');
        }
        
        console.log('✅ Campos de contraseña mostrados');
    } else {
        // Ocultar campos de contraseña
        passwordFields.style.display = 'none';
        passwordFields.style.opacity = '0';
        passwordFields.classList.remove('show');
        
        // Quitar requerimiento y limpiar campos
        const passwordInput = document.getElementById('editPassword');
        const confirmPasswordInput = document.getElementById('editConfirmPassword');
        
        if (passwordInput) {
            passwordInput.required = false;
            passwordInput.value = '';
            console.log('✅ Campo password limpiado');
        }
        if (confirmPasswordInput) {
            confirmPasswordInput.required = false;
            confirmPasswordInput.value = '';
            console.log('✅ Campo confirm password limpiado');
        }
        
        console.log('✅ Campos de contraseña ocultados');
    }
}

// ================================
// OBSERVADOR PARA DETECTAR NUEVOS CHECKBOXES
// ================================

const checkboxObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.addedNodes.length > 0) {
            const hasCheckboxes = Array.from(mutation.addedNodes).some(node => 
                node.nodeType === 1 && (
                    (node.querySelector && node.querySelector('.checkbox-container')) ||
                    (node.classList && node.classList.contains('checkbox-container'))
                )
            );
            
            if (hasCheckboxes) {
                console.log('🔄 Detectados nuevos checkboxes, reinicializando...');
                setTimeout(initializeCheckboxes, 100);
            }
        }
    });
});

// Observar cambios en el documento
checkboxObserver.observe(document.body, {
    childList: true,
    subtree: true
});

// ================================
// FUNCIONES DE MODALES DE USUARIOS
// ================================

function createUser() {
    console.log('👤 Abriendo modal crear usuario...');
    
    showModal('createUser', 'Crear Usuario', `
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
    
    // Inicializar elementos después de crear el modal
    setTimeout(() => {
        console.log('🔄 Inicializando elementos del modal...');
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        initializeCheckboxes();
    }, 100);
}

function editUser(userId) {
    if (!userId) {
        console.error('❌ ID de usuario no válido');
        return;
    }
    
    console.log(`✏️ Editando usuario ID: ${userId}`);
    showLoading('Cargando datos del usuario...');
    
    // Si no tienes la función fetch real, usa datos de ejemplo
    if (typeof fetch === 'undefined' || !document.querySelector('base')) {
        // Datos de ejemplo para testing
        const userData = {
            id: userId,
            first_name: 'Juan',
            last_name: 'Pérez',
            username: 'jperez',
            email: 'juan@ejemplo.com',
            role: 'user',
            company_id: 1,
            download_enabled: true
        };
        
        setTimeout(() => {
            hideLoading();
            showEditUserModal(userData);
        }, 500);
        return;
    }
    
    // Código real para cargar datos del servidor
    fetch(`actions/get_user.php?id=${userId}`)
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showEditUserModal(data.user);
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

function showEditUserModal(user) {
    showModal('editUser', 'Editar Usuario', `
        <form id="editUserForm" onsubmit="handleEditUser(event, ${user.id})">
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
                    <input type="checkbox" id="changePassword" name="change_password">
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
    
    // Inicializar elementos después de crear el modal
    setTimeout(() => {
        console.log('🔄 Inicializando elementos del modal de edición...');
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        initializeCheckboxes();
    }, 100);
}

// ================================
// FUNCIONES DE MANEJO DE FORMULARIOS
// ================================

function handleCreateUser(event) {
    event.preventDefault();
    console.log('📝 Procesando creación de usuario...');
    
    const formData = new FormData(event.target);
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    
    // Validaciones
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
    
    // Enviar datos al servidor (si existe)
    if (typeof fetch !== 'undefined' && document.querySelector('base')) {
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
                setTimeout(() => {
                    if (typeof loadUsers === 'function') {
                        loadUsers(currentPage, currentFilters);
                    } else {
                        window.location.reload();
                    }
                }, 1000);
            } else {
                showNotification(data.message || 'Error al crear usuario', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showNotification('Error de conexión', 'error');
            console.error('Error:', error);
        });
    } else {
        // Para testing sin backend
        setTimeout(() => {
            hideLoading();
            showNotification('Usuario creado exitosamente (modo demo)', 'success');
            closeModal('createUser');
        }, 1000);
    }
}

function handleEditUser(event, userId) {
    event.preventDefault();
    console.log(`📝 Procesando edición de usuario ID: ${userId}`);
    
    const formData = new FormData(event.target);
    const changePassword = formData.get('change_password');
    
    // Si se va a cambiar la contraseña, validar
    if (changePassword) {
        const password = formData.get('password');
        const confirmPassword = formData.get('confirm_password');
        
        if (!password || !confirmPassword) {
            showNotification('Debe completar ambos campos de contraseña', 'error');
            return;
        }
        
        if (password !== confirmPassword) {
            showNotification('Las contraseñas no coinciden', 'error');
            return;
        }
        
        if (password.length < 6) {
            showNotification('La contraseña debe tener al menos 6 caracteres', 'error');
            return;
        }
    }
    
    // Agregar ID de usuario
    formData.append('user_id', userId);
    
    // Mostrar loading
    showLoading('Actualizando usuario...');
    
    // Enviar datos al servidor (si existe)
    if (typeof fetch !== 'undefined' && document.querySelector('base')) {
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
                setTimeout(() => {
                    if (typeof loadUsers === 'function') {
                        loadUsers(currentPage, currentFilters);
                    } else {
                        window.location.reload();
                    }
                }, 1000);
            } else {
                showNotification(data.message || 'Error al actualizar usuario', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showNotification('Error de conexión', 'error');
            console.error('Error:', error);
        });
    } else {
        // Para testing sin backend
        setTimeout(() => {
            hideLoading();
            showNotification('Usuario actualizado exitosamente (modo demo)', 'success');
            closeModal('editUser');
        }, 1000);
    }
}

// ================================
// FUNCIONES AUXILIARES
// ================================

function getCompanyOptions(selectedId = null) {
    // Esta función debería cargar las empresas desde el servidor
    // Por ahora devolvemos opciones estáticas
    const companies = [
        { id: 1, name: 'Perdomo y Asociados' },
        { id: 2, name: 'Empresa Test' },
        { id: 3, name: 'Otra Empresa' }
    ];
    
    return companies.map(company => 
        `<option value="${company.id}" ${selectedId == company.id ? 'selected' : ''}>
            ${escapeHtml(company.name)}
        </option>`
    ).join('');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function initializeFilters() {
    const searchInput = document.getElementById('searchUser');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const companyFilter = document.getElementById('companyFilter');
    
    // Agregar event listeners para filtros si existen
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (typeof applyFilters === 'function') {
                    applyFilters();
                }
            }, 300);
        });
    }
    
    [roleFilter, statusFilter, companyFilter].forEach(filter => {
        if (filter && typeof applyFilters === 'function') {
            filter.addEventListener('change', applyFilters);
        }
    });
}

// ================================
// FUNCIONES DE UI (PLACEHOLDER)
// ================================

function showModal(id, title, content) {
    console.log(`📋 Mostrando modal: ${title}`);
    // Esta función debe existir en tu sistema
    // Si no existe, crea un modal básico
    if (typeof window.showModal === 'function') {
        window.showModal(id, title, content);
    } else {
        console.warn('Función showModal no encontrada');
    }
}

function closeModal(id) {
    console.log(`❌ Cerrando modal: ${id}`);
    // Esta función debe existir en tu sistema
    if (typeof window.closeModal === 'function') {
        window.closeModal(id);
    } else {
        console.warn('Función closeModal no encontrada');
    }
}

function showLoading(message = 'Cargando...') {
    console.log(`⏳ ${message}`);
    // Esta función debe existir en tu sistema
    if (typeof window.showLoading === 'function') {
        window.showLoading(message);
    } else {
        console.warn('Función showLoading no encontrada');
    }
}

function hideLoading() {
    console.log('✅ Ocultando loading');
    // Esta función debe existir en tu sistema
    if (typeof window.hideLoading === 'function') {
        window.hideLoading();
    } else {
        console.warn('Función hideLoading no encontrada');
    }
}

function showNotification(message, type = 'info') {
    console.log(`📢 ${type.toUpperCase()}: ${message}`);
    // Esta función debe existir en tu sistema
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
    } else {
        // Fallback simple
        alert(`${type.toUpperCase()}: ${message}`);
    }
}

// ================================
// EXPORTAR FUNCIONES GLOBALES
// ================================

// Hacer funciones disponibles globalmente
window.initializeCheckboxes = initializeCheckboxes;
window.togglePasswordFields = togglePasswordFields;
window.createUser = createUser;
window.editUser = editUser;
window.handleCreateUser = handleCreateUser;
window.handleEditUser = handleEditUser;

console.log('✅ JavaScript de usuarios cargado con fix de checkboxes');