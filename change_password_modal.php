<!-- Modal Simple de Cambio de Contraseña -->
<div id="changePasswordModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3 style="display: flex; align-items: center; gap: 10px; margin: 0;">
                <i data-feather="lock"></i> Cambio de contraseña
            </h3>
            <button class="close" onclick="closeChangePasswordModal()" style="background: none; border: none; cursor: pointer; padding: 5px;">
                <i data-feather="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="changePasswordForm" onsubmit="return handlePasswordChange(event)">
                
                <!-- Nueva Contraseña -->
                <div class="form-group">
                    <label for="new_password">Nueva contraseña</label>
                    <div style="position: relative;">
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            class="form-input"
                            placeholder="Ingrese nueva contraseña"
                            required
                        >
                        <button 
                            type="button" 
                            onclick="togglePasswordField('new_password')"
                            style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                            <i data-feather="eye" id="new_password_icon" style="width: 18px; height: 18px;"></i>
                        </button>
                    </div>
                    <span class="error-message" id="new_password_error"></span>
                </div>

                <!-- Confirmar Nueva Contraseña -->
                <div class="form-group">
                    <label for="confirm_password">Confirme la nueva contraseña</label>
                    <div style="position: relative;">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-input"
                            placeholder="Repita la nueva contraseña"
                            required
                        >
                        <button 
                            type="button" 
                            onclick="togglePasswordField('confirm_password')"
                            style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                            <i data-feather="eye" id="confirm_password_icon" style="width: 18px; height: 18px;"></i>
                        </button>
                    </div>
                    <span class="error-message" id="confirm_password_error"></span>
                </div>

                <!-- Botón Guardar -->
                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button 
                        type="button" 
                        onclick="closeChangePasswordModal()"
                        style="padding: 10px 20px; border: 2px solid #e5e7eb; background: white; border-radius: 8px; cursor: pointer; font-size: 14px;"
                    >
                        Cancelar
                    </button>
                    <button 
                        type="submit" 
                        id="changePasswordBtn"
                        style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 6px;"
                    >
                        <i data-feather="check" style="width: 16px; height: 16px;"></i> Guardar
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<style>
/* Estilos para el modal de contraseña */
#changePasswordModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

#changePasswordModal.active {
    display: flex !important;
}

#changePasswordModal .modal-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

#changePasswordModal .modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#changePasswordModal .modal-body {
    padding: 24px;
}

#changePasswordModal .form-group {
    margin-bottom: 20px;
}

#changePasswordModal label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #374151;
    font-size: 14px;
}

#changePasswordModal .form-input {
    width: 100%;
    padding: 10px 40px 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

#changePasswordModal .form-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

#changePasswordModal .error-message {
    display: block;
    color: #ef4444;
    font-size: 13px;
    margin-top: 5px;
    min-height: 18px;
}

#changePasswordModal .form-input.error {
    border-color: #ef4444;
    background-color: #fef2f2;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.spinning {
    animation: spin 1s linear infinite;
}
</style>

<script>
// Abrir modal
function showChangePasswordModal() {
    const modal = document.getElementById('changePasswordModal');
    const form = document.getElementById('changePasswordForm');
    
    // Limpiar formulario
    if (form) form.reset();
    clearAllErrors();
    
    modal.classList.add('active');
    modal.style.display = 'flex';
    
    // Enfocar primer campo
    setTimeout(() => {
        document.getElementById('new_password').focus();
    }, 100);
    
    // Actualizar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

// Cerrar modal
function closeChangePasswordModal() {
    const modal = document.getElementById('changePasswordModal');
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
    
    // Limpiar formulario
    const form = document.getElementById('changePasswordForm');
    if (form) form.reset();
    clearAllErrors();
}

// Toggle visibilidad de contraseña
function togglePasswordField(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.setAttribute('data-feather', 'eye-off');
    } else {
        field.type = 'password';
        icon.setAttribute('data-feather', 'eye');
    }
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

// Limpiar errores
function clearAllErrors() {
    document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
    document.querySelectorAll('.form-input').forEach(el => el.classList.remove('error'));
}

// Mostrar error en campo
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorEl = document.getElementById(fieldId + '_error');
    
    if (field) field.classList.add('error');
    if (errorEl) errorEl.textContent = message;
}

// Validar formulario
function validateForm() {
    clearAllErrors();
    let isValid = true;
    
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Validar nueva contraseña
    if (!newPassword) {
        showFieldError('new_password', 'La nueva contraseña es requerida');
        isValid = false;
    } else if (newPassword.length < 6) {
        showFieldError('new_password', 'Debe tener al menos 6 caracteres');
        isValid = false;
    }
    
    // Validar confirmación
    if (!confirmPassword) {
        showFieldError('confirm_password', 'Debe confirmar la nueva contraseña');
        isValid = false;
    } else if (newPassword !== confirmPassword) {
        showFieldError('confirm_password', 'Las contraseñas no coinciden');
        isValid = false;
    }
    
    return isValid;
}

// Manejar cambio de contraseña
async function handlePasswordChange(event) {
    event.preventDefault();
    
    if (!validateForm()) {
        return false;
    }
    
    const btn = document.getElementById('changePasswordBtn');
    const originalHTML = btn.innerHTML;
    
    try {
        // Deshabilitar botón
        btn.disabled = true;
        btn.innerHTML = '<i data-feather="loader" class="spinning"></i> Guardando...';
        if (typeof feather !== 'undefined') feather.replace();
        
        // Preparar datos
        const formData = new FormData();
        formData.append('new_password', document.getElementById('new_password').value);
        formData.append('confirm_password', document.getElementById('confirm_password').value);
        
        // Enviar petición
        const response = await fetch('api/change_password_simple.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ ' + (result.message || 'Contraseña actualizada correctamente'));
            closeChangePasswordModal();
        } else {
            alert('❌ ' + (result.message || 'Error al cambiar la contraseña'));
            
            // Mostrar errores específicos de campos
            if (result.errors) {
                Object.keys(result.errors).forEach(field => {
                    showFieldError(field, result.errors[field]);
                });
            }
        }
        
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error de conexión. Intente nuevamente.');
    } finally {
        // Restaurar botón
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        if (typeof feather !== 'undefined') feather.replace();
    }
    
    return false;
}

// Función para abrir ayuda
function openHelp() {
    window.open('https://drive.google.com/file/d/1LJUFtoUJZDdBy3sC-b8M92owE9-LE0c6/view?pli=1', '_blank', 'noopener,noreferrer');
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Cerrar modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('changePasswordModal');
            if (modal && modal.classList.contains('active')) {
                closeChangePasswordModal();
            }
        }
    });
    
    // Cerrar modal al hacer click fuera
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeChangePasswordModal();
            }
        });
    }
});
</script>
