// assets/js/user-actions.js
// Funcionalidades de usuario: cambiar contraseña y ayuda - DMS2

/**
 * Mostrar modal de cambio de contraseña
 */
function showChangePasswordModal() {
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.style.display = 'flex';
        
        // Limpiar formulario
        document.getElementById('changePasswordForm').reset();
        clearPasswordErrors();
        
        // Enfocar primer campo
        setTimeout(() => {
            document.getElementById('current_password').focus();
        }, 100);
    }
}

/**
 * Cerrar modal de cambio de contraseña
 */
function hideChangePasswordModal() {
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('changePasswordForm').reset();
        clearPasswordErrors();
    }
}

/**
 * Limpiar errores del formulario de contraseña
 */
function clearPasswordErrors() {
    const errorElements = document.querySelectorAll('.password-error');
    errorElements.forEach(el => el.textContent = '');
    
    const inputs = document.querySelectorAll('#changePasswordForm input');
    inputs.forEach(input => input.classList.remove('error'));
}

/**
 * Validar formulario de cambio de contraseña
 */
function validatePasswordForm() {
    clearPasswordErrors();
    
    const currentPassword = document.getElementById('current_password').value.trim();
    const newPassword = document.getElementById('new_password').value.trim();
    const confirmPassword = document.getElementById('confirm_password').value.trim();
    
    let isValid = true;
    
    // Validar contraseña actual
    if (!currentPassword) {
        showPasswordError('current_password', 'La contraseña actual es requerida');
        isValid = false;
    }
    
    // Validar nueva contraseña
    if (!newPassword) {
        showPasswordError('new_password', 'La nueva contraseña es requerida');
        isValid = false;
    } else if (newPassword.length < 8) {
        showPasswordError('new_password', 'La contraseña debe tener al menos 8 caracteres');
        isValid = false;
    } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(newPassword)) {
        showPasswordError('new_password', 'Debe incluir mayúsculas, minúsculas y números');
        isValid = false;
    }
    
    // Validar confirmación
    if (!confirmPassword) {
        showPasswordError('confirm_password', 'Debe confirmar la nueva contraseña');
        isValid = false;
    } else if (newPassword !== confirmPassword) {
        showPasswordError('confirm_password', 'Las contraseñas no coinciden');
        isValid = false;
    }
    
    // Validar que la nueva contraseña sea diferente
    if (currentPassword && newPassword && currentPassword === newPassword) {
        showPasswordError('new_password', 'La nueva contraseña debe ser diferente a la actual');
        isValid = false;
    }
    
    return isValid;
}

/**
 * Mostrar error en campo de contraseña
 */
function showPasswordError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorElement = field.parentElement.querySelector('.password-error');
    
    if (field) {
        field.classList.add('error');
    }
    
    if (errorElement) {
        errorElement.textContent = message;
    }
}

/**
 * Toggle visibilidad de contraseña
 */
function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.setAttribute('data-feather', 'eye-off');
    } else {
        field.type = 'password';
        icon.setAttribute('data-feather', 'eye');
    }
    
    feather.replace();
}

/**
 * Manejar envío del formulario de cambio de contraseña
 */
async function handleChangePassword(event) {
    event.preventDefault();
    
    if (!validatePasswordForm()) {
        return;
    }
    
    const submitBtn = document.getElementById('changePasswordBtn');
    const originalText = submitBtn.innerHTML;
    
    try {
        // Deshabilitar botón y mostrar estado de carga
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i data-feather="loader" class="spinning"></i> Cambiando contraseña...';
        feather.replace();
        
        const formData = new FormData(event.target);
        
        const response = await fetch('api/change_password.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Mostrar mensaje de éxito
            showToast('success', result.message || 'Contraseña actualizada correctamente');
            
            // Cerrar modal después de un breve delay
            setTimeout(() => {
                hideChangePasswordModal();
            }, 1500);
            
        } else {
            // Mostrar error
            showToast('error', result.message || 'Error al cambiar la contraseña');
            
            // Si hay errores específicos de campos
            if (result.errors) {
                Object.keys(result.errors).forEach(field => {
                    showPasswordError(field, result.errors[field]);
                });
            }
        }
        
    } catch (error) {
        console.error('Error:', error);
        showToast('error', 'Error de conexión. Por favor intente nuevamente.');
    } finally {
        // Restaurar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        feather.replace();
    }
}

/**
 * Abrir ayuda en nueva pestaña
 */
function openHelp() {
    const helpUrl = 'https://drive.google.com/file/d/1LJUFtoUJZDdBy3sC-b8M92owE9-LE0c6/view?pli=1';
    window.open(helpUrl, '_blank', 'noopener,noreferrer');
    
    // Opcional: mostrar toast informativo
    showToast('info', 'Abriendo manual de ayuda...');
}

/**
 * Inicializar eventos cuando el DOM esté listo
 */
document.addEventListener('DOMContentLoaded', function() {
    // Agregar evento al formulario de cambio de contraseña
    const changePasswordForm = document.getElementById('changePasswordForm');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', handleChangePassword);
    }
    
    // Cerrar modal al hacer clic fuera
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideChangePasswordModal();
            }
        });
    }
    
    // Cerrar modal con tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('changePasswordModal');
            if (modal && modal.style.display === 'flex') {
                hideChangePasswordModal();
            }
        }
    });
});

/**
 * Función auxiliar para mostrar notificaciones toast
 * (usa el sistema existente de modals.php)
 */
function showToast(type, message, duration = 4000) {
    // Si ya existe la función showToast global, usarla
    if (typeof window.showToast === 'function') {
        window.showToast(type, message, duration);
        return;
    }
    
    // Fallback: crear toast simple
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
    `;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}
