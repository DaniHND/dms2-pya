<?php
// includes/user-modals.php
// Modales de usuario: cambiar contraseña - DMS2
?>

<!-- Modal de Cambio de Contraseña -->
<div id="changePasswordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i data-feather="lock"></i> Cambiar Contraseña</h3>
            <button class="close" onclick="hideChangePasswordModal()">
                <i data-feather="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="changePasswordForm" onsubmit="handleChangePassword(event)">
                
                <!-- Contraseña Actual -->
                <div class="form-group">
                    <label for="current_password">
                        <i data-feather="shield"></i>
                        Contraseña Actual
                    </label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="current_password" 
                            name="current_password" 
                            class="form-control"
                            placeholder="Ingrese su contraseña actual"
                            required
                            autocomplete="current-password"
                        >
                        <button 
                            type="button" 
                            class="toggle-password" 
                            onclick="togglePasswordVisibility('current_password')"
                            tabindex="-1"
                        >
                            <i data-feather="eye"></i>
                        </button>
                    </div>
                    <span class="password-error"></span>
                </div>

                <!-- Nueva Contraseña -->
                <div class="form-group">
                    <label for="new_password">
                        <i data-feather="key"></i>
                        Nueva Contraseña
                    </label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            class="form-control"
                            placeholder="Mínimo 8 caracteres"
                            required
                            autocomplete="new-password"
                        >
                        <button 
                            type="button" 
                            class="toggle-password" 
                            onclick="togglePasswordVisibility('new_password')"
                            tabindex="-1"
                        >
                            <i data-feather="eye"></i>
                        </button>
                    </div>
                    <span class="password-error"></span>
                    <div class="password-requirements">
                        <small>
                            <i data-feather="info"></i>
                            La contraseña debe tener al menos 8 caracteres, incluir mayúsculas, minúsculas y números
                        </small>
                    </div>
                </div>

                <!-- Confirmar Nueva Contraseña -->
                <div class="form-group">
                    <label for="confirm_password">
                        <i data-feather="check-circle"></i>
                        Confirmar Nueva Contraseña
                    </label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-control"
                            placeholder="Repita la nueva contraseña"
                            required
                            autocomplete="new-password"
                        >
                        <button 
                            type="button" 
                            class="toggle-password" 
                            onclick="togglePasswordVisibility('confirm_password')"
                            tabindex="-1"
                        >
                            <i data-feather="eye"></i>
                        </button>
                    </div>
                    <span class="password-error"></span>
                </div>

                <!-- Botones -->
                <div class="modal-footer">
                    <button 
                        type="button" 
                        class="btn btn-outline" 
                        onclick="hideChangePasswordModal()"
                    >
                        <i data-feather="x"></i>
                        Cancelar
                    </button>
                    <button 
                        type="submit" 
                        class="btn btn-primary" 
                        id="changePasswordBtn"
                    >
                        <i data-feather="check"></i>
                        Cambiar Contraseña
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<style>
/* Estilos específicos para el modal de cambio de contraseña */
#changePasswordModal .form-group {
    margin-bottom: 1.5rem;
}

#changePasswordModal label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

#changePasswordModal label i {
    width: 16px;
    height: 16px;
}

.password-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.password-input-wrapper input {
    flex: 1;
    padding-right: 2.5rem;
}

.password-input-wrapper .toggle-password {
    position: absolute;
    right: 0.75rem;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-secondary);
    padding: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}

.password-input-wrapper .toggle-password:hover {
    color: var(--primary-color);
}

.password-input-wrapper .toggle-password i {
    width: 18px;
    height: 18px;
}

.password-error {
    display: block;
    color: #ef4444;
    font-size: 0.875rem;
    margin-top: 0.375rem;
    min-height: 1.25rem;
}

.password-requirements {
    margin-top: 0.5rem;
}

.password-requirements small {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.8125rem;
    line-height: 1.4;
}

.password-requirements i {
    width: 14px;
    height: 14px;
    margin-top: 0.125rem;
    flex-shrink: 0;
}

#changePasswordModal input.error {
    border-color: #ef4444;
    background-color: #fef2f2;
}

#changePasswordModal input.error:focus {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

#changePasswordModal .modal-footer {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

#changePasswordModal .btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    font-size: 0.9375rem;
}

#changePasswordModal .btn i {
    width: 16px;
    height: 16px;
}

/* Animación para el icono de carga */
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.spinning {
    animation: spin 1s linear infinite;
}

/* Responsive */
@media (max-width: 640px) {
    #changePasswordModal .modal-content {
        width: 95%;
        margin: 1rem;
    }
    
    #changePasswordModal .modal-footer {
        flex-direction: column;
    }
    
    #changePasswordModal .modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
