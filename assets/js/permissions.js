/*
 * permissions.js - JavaScript para el módulo de permisos
 */

const groupId = document.querySelector('.permissions-container').dataset.groupId || 
               new URLSearchParams(window.location.search).get('group');
let selectedUsers = new Set();

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    initializePermissions();
});

function initializePermissions() {
    // Reemplazar iconos
    feather.replace();
    
    // Inicializar usuarios seleccionados
    initializeSelectedUsers();
    
    // Configurar búsqueda
    initializeSearch();
    
    // Actualizar contadores
    updateCounts();
    
    // Activar tab según URL
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab') || 'members';
    switchTab(tab);
}

function initializeSelectedUsers() {
    // Obtener usuarios ya marcados como miembros
    document.querySelectorAll('#allUsersList .item-checkbox:checked').forEach(checkbox => {
        const userId = parseInt(checkbox.closest('[data-user-id]').dataset.userId);
        selectedUsers.add(userId);
    });
}

function initializeSearch() {
    // Búsqueda en usuarios disponibles
    document.getElementById('searchAllUsers').addEventListener('input', function() {
        filterItems('allUsersList', this.value);
    });
    
    // Búsqueda en miembros
    document.getElementById('searchMembers').addEventListener('input', function() {
        filterItems('membersList', this.value);
    });
}

function switchTab(tabName) {
    // Actualizar botones de tab
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-section').forEach(section => section.classList.remove('active'));
    
    // Activar tab seleccionado
    if (tabName === 'members') {
        document.querySelector('.tab-btn:first-child').classList.add('active');
        document.getElementById('membersTab').classList.add('active');
        document.getElementById('permissionsActions').style.display = 'none';
    } else if (tabName === 'permissions') {
        document.querySelector('.tab-btn:last-child').classList.add('active');
        document.getElementById('permissionsTab').classList.add('active');
        document.getElementById('permissionsActions').style.display = 'block';
    }
    
    // Actualizar URL
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    history.replaceState(null, '', url);
    
    // Reemplazar iconos después del cambio
    feather.replace();
}

function filterItems(containerId, searchTerm) {
    const container = document.getElementById(containerId);
    const items = container.querySelectorAll('.selection-item');
    
    items.forEach(item => {
        const name = item.querySelector('.item-name')?.textContent.toLowerCase() || '';
        const meta = item.querySelector('.item-meta')?.textContent.toLowerCase() || '';
        
        const matches = name.includes(searchTerm.toLowerCase()) || 
                       meta.includes(searchTerm.toLowerCase());
        
        item.style.display = matches ? 'flex' : 'none';
    });
}

function toggleUserMembership(userId, isChecked) {
    const userItem = document.querySelector(`[data-user-id="${userId}"]`);
    
    if (isChecked) {
        selectedUsers.add(userId);
        userItem.classList.add('selected');
    } else {
        selectedUsers.delete(userId);
        userItem.classList.remove('selected');
    }
    
    updateCounts();
    updateMembersList();
}

function updateCounts() {
    document.getElementById('membersCount').textContent = selectedUsers.size;
}

function updateMembersList() {
    const membersList = document.getElementById('membersList');
    
    // Obtener información de todos los usuarios desde el DOM
    const allUserItems = document.querySelectorAll('#allUsersList .selection-item');
    const usersData = {};
    
    allUserItems.forEach(item => {
        const userId = parseInt(item.dataset.userId);
        const name = item.querySelector('.item-name').textContent;
        const meta = item.querySelector('.item-meta').textContent;
        const avatar = item.querySelector('.user-avatar').textContent;
        
        usersData[userId] = { name, meta, avatar };
    });
    
    // Limpiar lista de miembros
    membersList.innerHTML = '';
    
    if (selectedUsers.size === 0) {
        membersList.innerHTML = `
            <div class="selection-item" style="text-align: center; color: #64748b; font-style: italic;">
                No hay miembros seleccionados
            </div>
        `;
        return;
    }
    
    // Agregar usuarios seleccionados
    selectedUsers.forEach(userId => {
        const userData = usersData[userId];
        if (userData) {
            membersList.innerHTML += `
                <div class="selection-item selected" data-user-id="${userId}">
                    <div class="user-avatar">${userData.avatar}</div>
                    <div class="item-info">
                        <div class="item-name">${userData.name}</div>
                        <div class="item-meta">${userData.meta}</div>
                    </div>
                </div>
            `;
        }
    });
}

function saveMembers() {
    const memberIds = Array.from(selectedUsers);
    const btn = event.target;
    
    // Mostrar estado de carga
    showLoadingState(btn, 'Guardando...');
    
    fetch('actions/update_group_members.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            group_id: parseInt(groupId),
            member_ids: memberIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Miembros actualizados correctamente', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(data.message || 'Error al actualizar miembros', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al procesar la solicitud', 'error');
    })
    .finally(() => {
        hideLoadingState(btn, '<i data-feather="save"></i> Guardar Miembros');
    });
}

function savePermissions() {
    const form = document.getElementById('permissionsForm');
    const formData = new FormData(form);
    const btn = event.target;
    
    // Mostrar estado de carga
    showLoadingState(btn, 'Guardando...');
    
    // Procesar datos del formulario
    const permissions = {};
    const restrictions = {
        companies: [],
        departments: [],
        document_types: []
    };
    
    let downloadLimit = null;
    let uploadLimit = null;
    
    for (let [key, value] of formData.entries()) {
        if (key.startsWith('permissions[')) {
            const permissionKey = key.replace('permissions[', '').replace(']', '');
            permissions[permissionKey] = true;
        } else if (key.startsWith('restrictions[')) {
            const restrictionKey = key.replace('restrictions[', '').replace('][]', '');
            if (!restrictions[restrictionKey]) {
                restrictions[restrictionKey] = [];
            }
            restrictions[restrictionKey].push(parseInt(value));
        } else if (key === 'download_limit_daily') {
            downloadLimit = value ? parseInt(value) : null;
        } else if (key === 'upload_limit_daily') {
            uploadLimit = value ? parseInt(value) : null;
        }
    }
    
    // Enviar datos
    fetch('actions/update_group_permissions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            group_id: parseInt(groupId),
            permissions: permissions,
            restrictions: restrictions,
            download_limit_daily: downloadLimit,
            upload_limit_daily: uploadLimit
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Permisos actualizados correctamente', 'success');
        } else {
            showNotification(data.message || 'Error al actualizar permisos', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al procesar la solicitud', 'error');
    })
    .finally(() => {
        hideLoadingState(btn, '<i data-feather="save"></i> Guardar Permisos');
    });
}

function showLoadingState(button, text) {
    button.disabled = true;
    button.innerHTML = `<i data-feather="loader" class="loading"></i> ${text}`;
    feather.replace();
}

function hideLoadingState(button, originalContent) {
    button.disabled = false;
    button.innerHTML = originalContent;
    feather.replace();
}

function showNotification(message, type = 'info') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    // Definir icono según el tipo
    const icons = {
        success: 'check-circle',
        error: 'x-circle',
        info: 'info',
        warning: 'alert-triangle'
    };
    
    notification.innerHTML = `
        <i data-feather="${icons[type] || icons.info}"></i>
        <span>${message}</span>
    `;
    
    // Agregar al DOM
    document.body.appendChild(notification);
    feather.replace();
    
    // Mostrar con animación
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Ocultar después de 4 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Funciones auxiliares para mejorar la experiencia
function selectAllUsers() {
    document.querySelectorAll('#allUsersList .item-checkbox').forEach(checkbox => {
        if (!checkbox.checked) {
            checkbox.checked = true;
            const userId = parseInt(checkbox.closest('[data-user-id]').dataset.userId);
            toggleUserMembership(userId, true);
        }
    });
}

function deselectAllUsers() {
    document.querySelectorAll('#allUsersList .item-checkbox').forEach(checkbox => {
        if (checkbox.checked) {
            checkbox.checked = false;
            const userId = parseInt(checkbox.closest('[data-user-id]').dataset.userId);
            toggleUserMembership(userId, false);
        }
    });
}

// Mejorar accesibilidad con teclado
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + S para guardar
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        
        const activeTab = document.querySelector('.tab-section.active');
        if (activeTab && activeTab.id === 'membersTab') {
            const saveBtn = document.querySelector('button[onclick="saveMembers()"]');
            if (saveBtn && !saveBtn.disabled) {
                saveMembers();
            }
        } else if (activeTab && activeTab.id === 'permissionsTab') {
            const saveBtn = document.querySelector('button[onclick="savePermissions()"]');
            if (saveBtn && !saveBtn.disabled) {
                savePermissions();
            }
        }
    }
    
    // Escape para cerrar notificaciones
    if (e.key === 'Escape') {
        document.querySelectorAll('.notification').forEach(notification => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        });
    }
});

// Validación en tiempo real
document.addEventListener('input', function(e) {
    if (e.target.type === 'number' && e.target.name && e.target.name.includes('limit')) {
        const value = parseInt(e.target.value);
        if (value < 0) {
            e.target.value = 0;
        }
    }
});

// Auto-guardar draft (opcional)
let autoSaveTimeout;
function scheduleAutoSave() {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(() => {
        // Aquí podrías implementar auto-guardado como draft
        console.log('Auto-save triggered (draft)');
    }, 30000); // 30 segundos
}

// Escuchar cambios en el formulario para auto-save
document.getElementById('permissionsForm')?.addEventListener('change', scheduleAutoSave);