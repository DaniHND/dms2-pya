// assets/js/login.js
// JavaScript para la página de login - DMS2

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const submitButton = loginForm.querySelector('button[type="submit"]');
    const recoveryModal = document.getElementById('recoveryModal');
    const recoveryForm = document.getElementById('recoveryForm');
    
    // Enfocar el campo de usuario al cargar
    if (usernameInput && !usernameInput.value) {
        usernameInput.focus();
    } else if (passwordInput) {
        passwordInput.focus();
    }
    
    // Validación en tiempo real
    if (usernameInput) {
        usernameInput.addEventListener('input', validateUsername);
        usernameInput.addEventListener('blur', validateUsername);
    }
    
    if (passwordInput) {
        passwordInput.addEventListener('input', validatePassword);
        passwordInput.addEventListener('blur', validatePassword);
    }
    
    // Manejo del formulario de login
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    // Manejo del formulario de recuperación
    if (recoveryForm) {
        recoveryForm.addEventListener('submit', handleRecovery);
    }
    
    // Tecla Enter para navegar entre campos
    if (usernameInput) {
        usernameInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                passwordInput.focus();
            }
        });
    }
    
    if (passwordInput) {
        passwordInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && isFormValid()) {
                e.preventDefault();
                loginForm.dispatchEvent(new Event('submit'));
            }
        });
    }
});

// Función para alternar visibilidad de contraseña
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.querySelector('.toggle-password i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleButton.setAttribute('data-feather', 'eye-off');
    } else {
        passwordInput.type = 'password';
        toggleButton.setAttribute('data-feather', 'eye');
    }
    
    feather.replace();
}

// Validación del campo usuario
function validateUsername() {
    const input = document.getElementById('username');
    const value = input.value.trim();
    const group = input.closest('.form-group');
    
    clearValidationState(group);
    
    if (value.length === 0) {
        return false;
    }
    
    if (value.length < 3) {
        showFieldError(group, 'El usuario debe tener al menos 3 caracteres');
        return false;
    }
    
    if (!/^[a-zA-Z0-9_]+$/.test(value)) {
        showFieldError(group, 'Solo se permiten letras, números y guiones bajos');
        return false;
    }
    
    showFieldSuccess(group);
    return true;
}

// Validación del campo contraseña
function validatePassword() {
    const input = document.getElementById('password');
    const value = input.value;
    const group = input.closest('.form-group');
    
    clearValidationState(group);
    
    if (value.length === 0) {
        return false;
    }
    
    if (value.length < 6) {
        showFieldError(group, 'La contraseña debe tener al menos 6 caracteres');
        return false;
    }
    
    showFieldSuccess(group);
    return true;
}

// Verificar si el formulario es válido
function isFormValid() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    return username.length >= 3 && password.length >= 6;
}

// Mostrar error en campo
function showFieldError(group, message) {
    group.classList.add('has-error');
    group.classList.remove('has-success');
    
    let errorElement = group.querySelector('.error-message');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        group.appendChild(errorElement);
    }
    
    errorElement.innerHTML = `<i data-feather="alert-circle"></i> ${message}`;
    feather.replace();
}

// Mostrar éxito en campo
function showFieldSuccess(group) {
    group.classList.add('has-success');
    group.classList.remove('has-error');
    
    const errorElement = group.querySelector('.error-message');
    if (errorElement) {
        errorElement.remove();
    }
}

// Limpiar estado de validación
function clearValidationState(group) {
    group.classList.remove('has-error', 'has-success');
    
    const errorElement = group.querySelector('.error-message');
    if (errorElement) {
        errorElement.remove();
    }
}

// Manejo del envío del formulario
function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const submitButton = e.target.querySelector('button[type="submit"]');
    
    // Validar campos
    const isUsernameValid = validateUsername();
    const isPasswordValid = validatePassword();
    
    if (!isUsernameValid || !isPasswordValid) {
        showAlert('error', 'Por favor corrija los errores en el formulario');
        return;
    }
    
    // Mostrar estado de carga
    setLoadingState(submitButton, true);
    
    // Enviar formulario (el PHP maneja la validación real)
    setTimeout(() => {
        e.target.submit();
    }, 500);
}

// Mostrar/ocultar modal de recuperación
function showRecoveryModal() {
    const modal = document.getElementById('recoveryModal');
    modal.classList.add('active');
    
    // Enfocar el campo de email
    setTimeout(() => {
        const emailInput = document.getElementById('recovery_email');
        if (emailInput) {
            emailInput.focus();
        }
    }, 100);
}

function hideRecoveryModal() {
    const modal = document.getElementById('recoveryModal');
    modal.classList.remove('active');
    
    // Limpiar formulario
    const form = document.getElementById('recoveryForm');
    if (form) {
        form.reset();
        clearValidationState(form.querySelector('.form-group'));
    }
}

// Manejo de recuperación de contraseña
function handleRecovery(e) {
    e.preventDefault();
    
    const email = document.getElementById('recovery_email').value.trim();
    const submitButton = e.target.querySelector('button[type="submit"]');
    
    if (!email) {
        showAlert('error', 'Por favor ingrese su email');
        return;
    }
    
    if (!isValidEmail(email)) {
        showAlert('error', 'Por favor ingrese un email válido');
        return;
    }
    
    setLoadingState(submitButton, true);
    
    // Simular envío de email de recuperación
    setTimeout(() => {
        setLoadingState(submitButton, false);
        showAlert('success', 'Se han enviado las instrucciones a su email');
        hideRecoveryModal();
    }, 2000);
}

// Validar formato de email
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

// Mostrar alerta
function showAlert(type, message) {
    // Remover alertas existentes
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    
    const icon = type === 'error' ? 'alert-circle' : 'check-circle';
    alertDiv.innerHTML = `<i data-feather="${icon}"></i> ${message}`;
    
    const loginForm = document.querySelector('.login-form');
    loginForm.insertBefore(alertDiv, loginForm.firstChild);
    
    feather.replace();
    
    // Auto-ocultar después de 5 segundos
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Estado de carga para botones
function setLoadingState(button, loading) {
    if (loading) {
        button.classList.add('loading');
        button.disabled = true;
        
        const originalText = button.innerHTML;
        button.setAttribute('data-original-text', originalText);
        button.innerHTML = '<i data-feather="loader"></i> Ingresando...';
    } else {
        button.classList.remove('loading');
        button.disabled = false;
        
        const originalText = button.getAttribute('data-original-text');
        if (originalText) {
            button.innerHTML = originalText;
        }
    }
    
    feather.replace();
}

// Cerrar modal al hacer clic fuera
document.addEventListener('click', function(e) {
    const modal = document.getElementById('recoveryModal');
    if (e.target === modal) {
        hideRecoveryModal();
    }
});

// Cerrar modal con tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('recoveryModal');
        if (modal.classList.contains('active')) {
            hideRecoveryModal();
        }
    }
});

// Funciones auxiliares para las credenciales de demo
function fillDemoCredentials(username, password) {
    document.getElementById('username').value = username;
    document.getElementById('password').value = password;
    
    // Validar campos automáticamente
    validateUsername();
    validatePassword();
    
    // Enfocar el botón de submit
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.focus();
    }
}

// Efecto de escritura para credenciales demo
function typeCredentials(username, password) {
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    
    // Limpiar campos
    usernameInput.value = '';
    passwordInput.value = '';
    
    // Escribir usuario
    let i = 0;
    const typeUsername = setInterval(() => {
        if (i < username.length) {
            usernameInput.value += username[i];
            i++;
        } else {
            clearInterval(typeUsername);
            validateUsername();
            
            // Escribir contraseña después de una pausa
            setTimeout(() => {
                let j = 0;
                const typePassword = setInterval(() => {
                    if (j < password.length) {
                        passwordInput.value += password[j];
                        j++;
                    } else {
                        clearInterval(typePassword);
                        validatePassword();
                        passwordInput.focus();
                    }
                }, 100);
            }, 300);
        }
    }, 150);
}

// Detectar si el usuario está usando un dispositivo móvil
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Ajustar comportamiento en dispositivos móviles
if (isMobileDevice()) {
    // Prevenir zoom en inputs en iOS
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            if (window.innerWidth < 768) {
                const viewport = document.querySelector('meta[name="viewport"]');
                viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
            }
        });
        
        input.addEventListener('blur', function() {
            if (window.innerWidth < 768) {
                const viewport = document.querySelector('meta[name="viewport"]');
                viewport.setAttribute('content', 'width=device-width, initial-scale=1.0');
            }
        });
    });
}