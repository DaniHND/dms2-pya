// assets/js/login.js
// JavaScript para login y recuperación de contraseña - DMS2

// Toggle password visibility
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleBtn = document.querySelector('.toggle-password');
    const icon = toggleBtn.querySelector('i');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        icon.setAttribute('data-feather', 'eye-off');
    } else {
        passwordField.type = 'password';
        icon.setAttribute('data-feather', 'eye');
    }
    
    // Actualizar iconos
    feather.replace();
}

// Mostrar modal de recuperación
function showRecoveryModal() {
    const modal = document.getElementById('recoveryModal');
    if (modal) {
        modal.style.display = 'flex';
        document.getElementById('recovery_email').focus();
    }
}

// Ocultar modal de recuperación
function hideRecoveryModal() {
    const modal = document.getElementById('recoveryModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('recoveryForm').reset();
        clearRecoveryMessages();
    }
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('recoveryModal');
    if (event.target === modal) {
        hideRecoveryModal();
    }
}

// Cerrar modal con tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideRecoveryModal();
    }
});

// Limpiar mensajes del modal
function clearRecoveryMessages() {
    const container = document.querySelector('#recoveryModal .alert');
    if (container) {
        container.remove();
    }
}

// Mostrar mensaje en el modal
function showRecoveryMessage(message, type = 'info') {
    clearRecoveryMessages();
    
    const modalBody = document.querySelector('#recoveryModal .modal-body');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    
    const icon = type === 'success' ? 'check-circle' : 
                 type === 'error' ? 'alert-circle' : 
                 'info';
    
    alertDiv.innerHTML = `
        <i data-feather="${icon}"></i>
        ${message}
    `;
    
    modalBody.insertBefore(alertDiv, modalBody.firstChild);
    feather.replace();
}

// Manejar envío del formulario de recuperación
document.getElementById('recoveryForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    const email = document.getElementById('recovery_email').value.trim();
    
    // Validar email
    if (!email) {
        showRecoveryMessage('Por favor ingrese su email', 'error');
        return;
    }
    
    if (!isValidEmail(email)) {
        showRecoveryMessage('Por favor ingrese un email válido', 'error');
        return;
    }
    
    // Deshabilitar botón y mostrar loader
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Enviando...';
    feather.replace();
    
    try {
        const response = await fetch('api/request_password_reset.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email: email })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showRecoveryMessage(result.message, 'success');
            document.getElementById('recoveryForm').reset();
            
            // Cerrar modal después de 5 segundos
            setTimeout(() => {
                hideRecoveryModal();
            }, 5000);
        } else {
            showRecoveryMessage(result.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showRecoveryMessage('Error al procesar la solicitud. Por favor intente nuevamente.', 'error');
    } finally {
        // Restaurar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        feather.replace();
    }
});

// Validar formato de email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Validación en tiempo real del email
document.getElementById('recovery_email')?.addEventListener('input', function() {
    clearRecoveryMessages();
});

// Prevenir envío múltiple del formulario de login
document.getElementById('loginForm')?.addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn.disabled) {
        e.preventDefault();
        return false;
    }
    submitBtn.disabled = true;
});

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    // Enfocar el campo de usuario al cargar
    const usernameField = document.getElementById('username');
    if (usernameField && !usernameField.value) {
        usernameField.focus();
    }
    
    console.log('✅ Login y recuperación de contraseña inicializados');
});
