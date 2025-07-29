// assets/js/users.js - Versión simplificada sin conflictos
console.log('🚀 Cargando módulo de usuarios...');

// Variables globales
let companiesData = [];

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    console.log('📋 Inicializando módulo de usuarios...');
    loadCompaniesData();
    console.log('✅ Módulo de usuarios inicializado');
});

function loadCompaniesData() {
    fetch('actions/get_companies.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            companiesData = data.companies;
            console.log('✅ Empresas cargadas:', companiesData.length);
        } else {
            console.warn('⚠️ Error cargando empresas:', data.message);
            companiesData = [];
        }
    })
    .catch(error => {
        console.warn('⚠️ Error de red cargando empresas:', error);
        companiesData = [];
    });
}

// ================================
// MODAL CREAR USUARIO
// ================================

function openCreateUserModal() {
    console.log('👤 Abriendo modal crear usuario...');
    
    // Eliminar modal existente si existe
    const existingModal = document.getElementById('createUserModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    const companyOptions = companiesData.map(company => 
        `<option value="${company.id}">${escapeHtml(company.name)}</option>`
    ).join('');

    const modal = document.createElement('div');
    modal.id = 'createUserModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    modal.innerHTML = `
        <div class="modal-content" style="
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        ">
            <div class="modal-header" style="
                padding: 24px 24px 16px;
                border-bottom: 1px solid #f1f5f9;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background-color: #f9fafb;
                border-radius: 12px 12px 0 0;
            ">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem; color: #374151; font-size: 1.125rem; font-weight: 600;">
                    <i data-feather="user-plus"></i> Crear Nuevo Usuario
                </h3>
                <button onclick="closeCreateUserModal()" style="
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    cursor: pointer;
                    color: #6b7280;
                    padding: 0.25rem;
                    width: 32px;
                    height: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 4px;
                    transition: all 0.2s;
                " onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">&times;</button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <form id="createUserForm" onsubmit="handleCreateUser(event)">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; font-size: 0.875rem;">Nombre *</label>
                            <input type="text" name="first_name" required minlength="2" placeholder="Nombre" style="
                                width: 100%;
                                padding: 0.75rem;
                                border: 1px solid #d1d5db;
                                border-radius: 0.375rem;
                                font-size: 1rem;
                                transition: border-color 0.2s;
                                box-sizing: border-box;
                            " onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; font-size: 0.875rem;">Apellido *</label>
                            <input type="text" name="last_name" required minlength="2" placeholder="Apellido" style="
                                width: 100%;
                                padding: 0.75rem;
                                border: 1px solid #d1d5db;
                                border-radius: 0.375rem;
                                font-size: 1rem;
                                transition: border-color 0.2s;
                                box-sizing: border-box;
                            " onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; font-size: 0.875rem;">Usuario *</label>
                            <input type="text" name="username" required minlength="3" placeholder="Usuario único" style="
                                width: 100%;
                                padding: 0.75rem;
                                border: 1px solid #d1d5db;
                                border-radius: 0.375rem;
                                font-size: 1rem;
                                transition: border-color 0.2s;
                                box-sizing: border-box;
                            " onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; font-size: 0.875rem;">Email *</label>
                            <input type="email" name="email" required placeholder="correo@ejemplo.com" style="
                                width: 100%;
                                padding: 0.75rem;
                                border: 1px solid #d1d5db;
                                border-radius: 0.375rem;
                                font-size: 1rem;
                                transition: border-color 0.2s;
                                box-sizing: border-box;
                            " onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; font-size: 0.875rem;">Rol *</label>
                            <select name="role" required style="
                                width: 100%;
                                padding: 0.75rem;
                                border: 1px solid #d1d5db;
                                border-radius: 0.375rem;
                                font-size: 1rem;
                                transition: border-color 0.2s;
                                box-sizing: border-box;
                            " onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                                <option value="">Seleccionar rol</option>
                                <option value="admin">Administrador</option>
                                <option value="manager">Gerente</option>
                                <option value="user">Usuario</option>
                                <option value="viewer">Visualizador</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; font-size: 0.875rem;">Empresa *</label>
                            <select name="company_id" required style="
                                width: 100%;
                                padding: 0.75rem;
                                border: 1px solid #d1d5db;
                                border-radius: 0.375rem;
                                font-size: 1rem;
                                transition: border-color 0.2s;
                                box-sizing: border-box;
                            " onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                                <option value="">Seleccionar empresa</option>
                                ${companyOptions}
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; font-size: 0.875rem;">Contraseña *</label>
                            <input type="password" name="password" required minlength="6" placeholder="Mínimo 6 caracteres" style="
                                width: 100%;
                                padding: 0.75rem;
                                border: 1px solid #d1d5db;
                                border-radius: 0.375rem;
                                font-size: 1rem;
                                transition: border-color 0.2s;
                                box-sizing: border-box;
                            " onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; font-size: 0.875rem;">Confirmar Contraseña *</label>
                            <input type="password" name="confirm_password" required minlength="6" placeholder="Repetir contraseña" style="
                                width: 100%;
                                padding: 0.75rem;
                                border: 1px solid #d1d5db;
                                border-radius: 0.375rem;
                                font-size: 1rem;
                                transition: border-color 0.2s;
                                box-sizing: border-box;
                            " onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 0.75rem; border-radius: 0.375rem; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f9fafb'" onmouseout="this.style.backgroundColor='transparent'">
                            <input type="checkbox" name="download_enabled" value="1" checked style="width: auto; margin: 0;">
                            Permitir descarga de documentos
                        </label>
                    </div>
                    
                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; margin-top: 1.5rem;">
                        <button type="button" onclick="closeCreateUserModal()" style="
                            padding: 0.75rem 1.5rem;
                            background-color: #f3f4f6;
                            color: #374151;
                            border: 1px solid #d1d5db;
                            border-radius: 0.375rem;
                            cursor: pointer;
                            font-weight: 500;
                            display: flex;
                            align-items: center;
                            gap: 0.5rem;
                            font-size: 0.875rem;
                            transition: all 0.2s;
                        " onmouseover="this.style.backgroundColor='#e5e7eb'; this.style.borderColor='#9ca3af'" onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.borderColor='#d1d5db'">
                            <i data-feather="x"></i> Cancelar
                        </button>
                        <button type="submit" style="
                            padding: 0.75rem 1.5rem;
                            background-color: #3b82f6;
                            color: white;
                            border: 1px solid #3b82f6;
                            border-radius: 0.375rem;
                            cursor: pointer;
                            font-weight: 500;
                            display: flex;
                            align-items: center;
                            gap: 0.5rem;
                            font-size: 0.875rem;
                            transition: all 0.2s;
                        " onmouseover="this.style.backgroundColor='#2563eb'; this.style.borderColor='#2563eb'" onmouseout="this.style.backgroundColor='#3b82f6'; this.style.borderColor='#3b82f6'">
                            <i data-feather="user-plus"></i> Crear Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    
    // Mostrar modal con animación
    setTimeout(() => {
        modal.style.opacity = '1';
        modal.querySelector('.modal-content').style.transform = 'scale(1)';
        feather.replace();
    }, 10);
    
    // Cerrar al hacer clic fuera
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeCreateUserModal();
        }
    });
}

function closeCreateUserModal() {
    const modal = document.getElementById('createUserModal');
    if (modal) {
        modal.style.opacity = '0';
        modal.querySelector('.modal-content').style.transform = 'scale(0.9)';
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

function handleCreateUser(event) {
    event.preventDefault();
    console.log('📝 Procesando creación de usuario...');
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validaciones
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    
    if (password !== confirmPassword) {
        showSimpleNotification('Las contraseñas no coinciden', 'error');
        return;
    }
    
    if (password.length < 6) {
        showSimpleNotification('La contraseña debe tener al menos 6 caracteres', 'error');
        return;
    }
    
    // Mostrar loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Creando...';
    submitBtn.disabled = true;
    
    // Enviar datos
    fetch('actions/create_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.log('📥 Response raw:', text);
        
        try {
            const data = JSON.parse(text);
            
            if (data.success) {
                showSimpleNotification('Usuario creado exitosamente', 'success');
                closeCreateUserModal();
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showSimpleNotification(data.message || 'Error al crear usuario', 'error');
            }
        } catch (error) {
            console.error('❌ JSON Parse Error:', error);
            showSimpleNotification('Error del servidor: ' + text.substring(0, 100), 'error');
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        showSimpleNotification('Error de conexión', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        feather.replace();
    });
}

// ================================
// VER DETALLES DE USUARIO
// ================================

function viewUser(userId) {
    console.log(`👁️ Ver usuario ${userId}`);
    
    showSimpleNotification('Cargando detalles del usuario...', 'info', 2000);
    
    fetch(`actions/get_user_details.php?id=${userId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showUserDetailsModal(data.user, data.stats || {});
        } else {
            showSimpleNotification(data.message || 'Error al cargar detalles', 'error');
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        showSimpleNotification('Error de conexión', 'error');
    });
}

function showUserDetailsModal(user, stats) {
    // Crear modal de detalles
    const modal = document.createElement('div');
    modal.id = 'userDetailsModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    modal.innerHTML = `
        <div class="modal-content" style="
            background: white;
            border-radius: 12px;
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        ">
            <div class="modal-header" style="
                padding: 24px 24px 16px;
                border-bottom: 1px solid #f1f5f9;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background-color: #f9fafb;
                border-radius: 12px 12px 0 0;
            ">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem; color: #374151; font-size: 1.125rem; font-weight: 600;">
                    <i data-feather="user"></i> Detalles del Usuario
                </h3>
                <button onclick="closeUserDetailsModal()" style="
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    cursor: pointer;
                    color: #6b7280;
                    padding: 0.25rem;
                    width: 32px;
                    height: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 4px;
                    transition: all 0.2s;
                " onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">&times;</button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="margin-bottom: 1rem; color: #374151;">Información Personal</h4>
                        <div style="margin-bottom: 0.75rem;"><strong>Nombre:</strong> ${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</div>
                        <div style="margin-bottom: 0.75rem;"><strong>Usuario:</strong> @${escapeHtml(user.username)}</div>
                        <div style="margin-bottom: 0.75rem;"><strong>Email:</strong> ${escapeHtml(user.email)}</div>
                        <div style="margin-bottom: 0.75rem;"><strong>Rol:</strong> ${getRoleLabel(user.role)}</div>
                        <div style="margin-bottom: 0.75rem;"><strong>Empresa:</strong> ${escapeHtml(user.company_name || 'Sin empresa')}</div>
                        <div style="margin-bottom: 0.75rem;"><strong>Estado:</strong> <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; ${user.status === 'active' ? 'background-color: #d1fae5; color: #065f46;' : 'background-color: #fee2e2; color: #991b1b;'}">${user.status === 'active' ? 'Activo' : 'Inactivo'}</span></div>
                        <div style="margin-bottom: 0.75rem;"><strong>Descarga:</strong> ${user.download_enabled ? '✅ Habilitada' : '❌ Deshabilitada'}</div>
                    </div>
                    <div>
                        <h4 style="margin-bottom: 1rem; color: #374151;">Estadísticas</h4>
                        <div style="margin-bottom: 0.75rem;"><strong>Documentos subidos:</strong> ${stats.total_documents || 0}</div>
                        <div style="margin-bottom: 0.75rem;"><strong>Actividades registradas:</strong> ${stats.total_activities || 0}</div>
                        <div style="margin-bottom: 0.75rem;"><strong>Descargas realizadas:</strong> ${stats.total_downloads || 0}</div>
                        <div style="margin-bottom: 0.75rem;"><strong>Último acceso:</strong> ${user.last_login || 'Nunca'}</div>
                        <div style="margin-bottom: 0.75rem;"><strong>Creado:</strong> ${formatDate(user.created_at)}</div>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; margin-top: 1.5rem;">
                    <button onclick="closeUserDetailsModal()" style="
                        padding: 0.75rem 1.5rem;
                        background-color: #f3f4f6;
                        color: #374151;
                        border: 1px solid #d1d5db;
                        border-radius: 0.375rem;
                        cursor: pointer;
                        font-weight: 500;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                        font-size: 0.875rem;
                        transition: all 0.2s;
                    " onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
                        <i data-feather="x"></i> Cerrar
                    </button>
                    <button onclick="editUser(${user.id})" style="
                        padding: 0.75rem 1.5rem;
                        background-color: #3b82f6;
                        color: white;
                        border: 1px solid #3b82f6;
                        border-radius: 0.375rem;
                        cursor: pointer;
                        font-weight: 500;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                        font-size: 0.875rem;
                        transition: all 0.2s;
                    " onmouseover="this.style.backgroundColor='#2563eb'" onmouseout="this.style.backgroundColor='#3b82f6'">
                        <i data-feather="edit"></i> Editar Usuario
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    setTimeout(() => {
        modal.style.opacity = '1';
        modal.querySelector('.modal-content').style.transform = 'scale(1)';
        feather.replace();
    }, 10);
    
    // Cerrar al hacer clic fuera
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeUserDetailsModal();
        }
    });
}

function closeUserDetailsModal() {
    const modal = document.getElementById('userDetailsModal');
    if (modal) {
        modal.style.opacity = '0';
        modal.querySelector('.modal-content').style.transform = 'scale(0.9)';
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// ================================
// EDITAR USUARIO
// ================================

function editUser(userId) {
    console.log(`✏️ Editar usuario ${userId}`);
    showSimpleNotification('Esta funcionalidad estará disponible próximamente', 'info');
}

// ================================
// CAMBIAR ESTADO USUARIO
// ================================

function toggleUserStatus(userId, currentStatus) {
    const action = currentStatus === 'active' ? 'desactivar' : 'activar';
    const confirmMessage = `¿Está seguro que desea ${action} este usuario?`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    console.log(`🔄 Cambiando estado del usuario ${userId}`);
    
    fetch('actions/toggle_user_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${encodeURIComponent(userId)}&current_status=${encodeURIComponent(currentStatus)}`
    })
    .then(response => response.text())
    .then(text => {
        console.log('📥 Toggle response:', text);
        
        try {
            const data = JSON.parse(text);
            
            if (data.success) {
                showSimpleNotification(`Usuario ${action}ado exitosamente`, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showSimpleNotification(data.message || `Error al ${action} usuario`, 'error');
            }
        } catch (error) {
            console.error('❌ JSON Parse Error:', error);
            showSimpleNotification('Error del servidor', 'error');
        }
    })
    .catch(error => {
        console.error('❌ Network Error:', error);
        showSimpleNotification('Error de conexión', 'error');
    });
}

// ================================
// FUNCIONES DE UTILIDAD
// ================================

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getRoleLabel(role) {
    const roles = {
        'admin': 'Administrador',
        'manager': 'Gerente', 
        'user': 'Usuario',
        'viewer': 'Visualizador'
    };
    return roles[role] || role;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// ================================
// SISTEMA DE NOTIFICACIONES SIMPLE
// ================================

function showSimpleNotification(message, type = 'info', duration = 5000) {
    console.log(`📢 Notificación ${type}: ${message}`);
    
    // Eliminar notificaciones existentes
    const existingNotifications = document.querySelectorAll('.simple-notification');
    existingNotifications.forEach(notif => notif.remove());
    
    const notification = document.createElement('div');
    notification.className = 'simple-notification';
    notification.style.cssText = `
        position: fixed;
        top: 1rem;
        right: 1rem;
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 0.5rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        border-left: 4px solid ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        z-index: 1001;
        min-width: 300px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        cursor: pointer;
    `;
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span>${message}</span>
        </div>
    `;
    
    notification.addEventListener('click', () => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    });
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    if (duration > 0) {
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }
        }, duration);
    }
}

console.log('✅ Módulo de usuarios cargado completamente');