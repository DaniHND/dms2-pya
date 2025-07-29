// assets/js/users_new.js
// JavaScript para el nuevo módulo de usuarios - DMS

console.log('🚀 Cargando módulo de usuarios nuevo...');

// Variables globales
let currentModal = null;
let companiesData = [];

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('📋 DOM cargado, inicializando módulo usuarios nuevo...');
    
    // Obtener datos globales
    if (window.userData) {
        companiesData = window.userData.companies || [];
        console.log('✅ Datos de empresas cargados:', companiesData.length);
    }
    
    // Inicializar componentes
    initializeModule();
});

function initializeModule() {
    console.log('⚙️ Inicializando componentes del módulo...');
    
    // Configurar eventos
    setupEventListeners();
    
    // Inicializar checkboxes si existen
    initializeCheckboxes();
    
    console.log('✅ Módulo de usuarios nuevo inicializado correctamente');
}

// ================================
// CONFIGURACIÓN DE EVENTOS
// ================================

function setupEventListeners() {
    console.log('🎯 Configurando eventos...');
    
    // Evento para cerrar modales con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && currentModal) {
            closeModal();
        }
    });
    
    // Eventos para filtros en tiempo real (opcional)
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            console.log('🔍 Búsqueda en tiempo real:', this.value);
            // Aquí podrías implementar búsqueda en tiempo real
        }, 300));
    }
}

// ================================
// FUNCIONES DE MODALES
// ================================

function openCreateUserModal() {
    console.log('👤 Abriendo modal de crear usuario...');
    
    const modalHTML = `
        <div class="modal active" id="createUserModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Crear Usuario</h3>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="createUserForm" onsubmit="handleCreateUser(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nombre *</label>
                                <input type="text" name="first_name" required minlength="2" placeholder="Ingrese el nombre">
                            </div>
                            <div class="form-group">
                                <label>Apellido *</label>
                                <input type="text" name="last_name" required minlength="2" placeholder="Ingrese el apellido">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Usuario *</label>
                                <input type="text" name="username" required minlength="3" placeholder="Nombre de usuario">
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" required placeholder="correo@ejemplo.com">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Rol *</label>
                                <select name="role" required>
                                    <option value="">Seleccionar rol</option>
                                    <option value="admin">Administrador</option>
                                    <option value="user">Usuario</option>
                                    <option value="viewer">Visualizador</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Empresa *</label>
                                <select name="company_id" required>
                                    <option value="">Seleccionar empresa</option>
                                    ${getCompanyOptions()}
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Contraseña *</label>
                                <input type="password" name="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                            </div>
                            <div class="form-group">
                                <label>Confirmar Contraseña *</label>
                                <input type="password" name="confirm_password" required minlength="6" placeholder="Repetir contraseña">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-container">
                                <input type="checkbox" name="download_enabled" checked>
                                <span class="checkmark"></span>
                                Permitir descarga de documentos
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeModal()">
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
    
    // Configurar eventos del modal
    setupModalEvents();
    
    // Inicializar checkboxes del modal
    setTimeout(() => {
        initializeCheckboxes();
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }, 100);
    
    console.log('✅ Modal de crear usuario abierto');
}

function closeModal() {
    console.log('❌ Cerrando modal...');
    
    if (currentModal) {
        currentModal.classList.remove('active');
        
        setTimeout(() => {
            if (currentModal && currentModal.parentNode) {
                currentModal.parentNode.removeChild(currentModal);
            }
            currentModal = null;
        }, 300);
    }
}

function setupModalEvents() {
    if (!currentModal) return;
    
    // Cerrar al hacer clic fuera del contenido
    currentModal.addEventListener('click', function(e) {
        if (e.target === currentModal) {
            closeModal();
        }
    });
}

// ================================
// FUNCIONES DE USUARIOS
// ================================

function handleCreateUser(event) {
    event.preventDefault();
    console.log('📝 Procesando creación de usuario...');
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validaciones del lado del cliente
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
    
    // Mostrar loading en el botón
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Creando...';
    submitBtn.disabled = true;
    
    // Reemplazar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Enviar datos al servidor (VERSIÓN SIMPLE)
    fetch('modules/users/actions/create_user_simple.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('📥 Response status:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('📥 Response raw:', text);
        
        try {
            const data = JSON.parse(text);
            console.log('📥 Parsed data:', data);
            
            if (data.success) {
                showNotification('Usuario creado exitosamente', 'success');
                closeModal();
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showNotification(data.message || 'Error al crear usuario', 'error');
            }
        } catch (parseError) {
            console.error('❌ JSON Parse Error:', parseError);
            console.error('❌ Raw response:', text);
            showNotification('Error del servidor: ' + text.substring(0, 100), 'error');
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        showNotification('Error de conexión', 'error');
    })
    .finally(() => {
        // Restaurar botón
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
}

function viewUser(userId) {
    console.log(`👁️ Ver detalles del usuario ${userId}`);
    showNotification(`Mostrando detalles del usuario ${userId}`, 'info');
    // Aquí implementarías la función real
}

function editUser(userId) {
    console.log(`✏️ Editar usuario ${userId}`);
    showNotification(`Editando usuario ${userId}`, 'info');
    // Aquí implementarías la función real
}

function toggleUserStatus(userId, currentStatus) {
    const action = currentStatus === 'active' ? 'desactivar' : 'activar';
    const confirmMessage = `¿Está seguro que desea ${action} este usuario?`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    console.log(`🔄 Cambiando estado del usuario ${userId}`);
    
    // Aquí enviarías la petición al servidor
    fetch('modules/users/actions/toggle_user_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&current_status=${currentStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`Usuario ${action}ado exitosamente`, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message || `Error al ${action} usuario`, 'error');
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

// ================================
// FUNCIONES DE CHECKBOXES
// ================================

function initializeCheckboxes() {
    console.log('☑️ Inicializando checkboxes...');
    
    const checkboxes = document.querySelectorAll('.checkbox-container input[type="checkbox"]');
    
    checkboxes.forEach(checkbox => {
        // Remover listeners anteriores
        checkbox.removeEventListener('change', handleCheckboxChange);
        
        // Agregar nuevo listener
        checkbox.addEventListener('change', handleCheckboxChange);
        
        // Aplicar estado visual inicial
        updateCheckboxVisual(checkbox);
    });
    
    console.log(`✅ ${checkboxes.length} checkboxes inicializados`);
}

function handleCheckboxChange(event) {
    const checkbox = event.target;
    console.log(`☑️ Checkbox ${checkbox.name} cambió a:`, checkbox.checked);
    
    updateCheckboxVisual(checkbox);
}

function updateCheckboxVisual(checkbox) {
    const container = checkbox.closest('.checkbox-container');
    if (container) {
        if (checkbox.checked) {
            container.classList.add('checked');
        } else {
            container.classList.remove('checked');
        }
    }
}

// ================================
// FUNCIONES DE UTILIDAD
// ================================

function getCompanyOptions() {
    if (companiesData.length === 0) {
        return '<option value="1">Empresa Principal</option>';
    }
    
    return companiesData.map(company => 
        `<option value="${company.id}">${escapeHtml(company.name)}</option>`
    ).join('');
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
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

// ================================
// SISTEMA DE NOTIFICACIONES
// ================================

function showNotification(message, type = 'info', duration = 5000) {
    console.log(`📢 Notificación ${type}: ${message}`);
    
    // Remover notificación existente
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Crear notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    // Colores según el tipo
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    
    const icons = {
        success: 'check-circle',
        error: 'alert-circle',
        warning: 'alert-triangle',
        info: 'info'
    };
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        font-weight: 600;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        cursor: pointer;
        max-width: 400px;
    `;
    
    notification.innerHTML = `
        <i data-feather="${icons[type] || icons.info}" style="width: 20px; height: 20px; flex-shrink: 0;"></i>
        <span>${message}</span>
        <i data-feather="x" style="width: 16px; height: 16px; flex-shrink: 0; opacity: 0.7; margin-left: auto;"></i>
    `;
    
    // Agregar CSS de animación si no existe
    if (!document.getElementById('notificationStyles')) {
        const styles = document.createElement('style');
        styles.id = 'notificationStyles';
        styles.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            .notification:hover {
                transform: translateX(-4px);
                transition: transform 0.2s ease;
            }
        `;
        document.head.appendChild(styles);
    }
    
    // Evento para cerrar al hacer clic
    notification.addEventListener('click', () => {
        removeNotification(notification);
    });
    
    // Agregar al DOM
    document.body.appendChild(notification);
    
    // Reemplazar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Auto-remover después del tiempo especificado
    if (duration > 0) {
        setTimeout(() => {
            removeNotification(notification);
        }, duration);
    }
}

function removeNotification(notification) {
    if (notification && notification.parentNode) {
        notification.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
}

// ================================
// EXPORTAR FUNCIONES GLOBALES
// ================================

// Hacer funciones disponibles globalmente
window.openCreateUserModal = openCreateUserModal;
window.closeModal = closeModal;
window.handleCreateUser = handleCreateUser;
window.viewUser = viewUser;
window.editUser = editUser;
window.toggleUserStatus = toggleUserStatus;
window.showNotification = showNotification;

console.log('✅ Módulo de usuarios nuevo cargado completamente');