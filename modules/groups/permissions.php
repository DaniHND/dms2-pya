<?php
/*
 * modules/groups/permissions.php
 * Gestión de permisos de grupos - Página principal
 */

require_once '../../config/session.php';
require_once '../../config/database.php';

// Verificar autenticación
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    header('Location: ../../dashboard.php');
    exit;
}

// Obtener parámetros
$selectedGroupId = $_GET['group'] ?? '';
$activeTab = $_GET['tab'] ?? 'members';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos de Grupos - DMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .permission-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
        }
        .permission-header {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .selected-items {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            background: #f8f9fa;
            min-height: 60px;
        }
        .selected-item {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 5px 10px;
            margin: 2px;
            border-radius: 15px;
            font-size: 0.8em;
        }
        .remove-item {
            cursor: pointer;
            margin-left: 5px;
        }
        .search-box {
            position: relative;
            margin-bottom: 15px;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
        }
        .search-result-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        .search-result-item:hover {
            background: #f8f9fa;
        }
        .permission-switch {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 5px 0;
        }
        .save-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Gestión de Permisos de Grupos</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Grupos</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Permisos</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <button class="btn btn-secondary" onclick="window.history.back()">
                        <i class="fas fa-arrow-left"></i> Regresar
                    </button>
                </div>
            </div>

            <!-- Selector de Grupo -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="groupSelect" class="form-label">Seleccionar Grupo</label>
                            <select id="groupSelect" class="form-select" onchange="loadGroupPermissions()">
                                <option value="">Seleccione un grupo...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div id="groupInfo" class="mt-2" style="display: none;">
                                <small class="text-muted">
                                    <strong>Miembros:</strong> <span id="memberCount">0</span> | 
                                    <strong>Estado:</strong> <span id="groupStatus">-</span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenido de Permisos -->
            <div id="permissionsContent" style="display: none;">
                
                <!-- Tabs de Configuración -->
                <ul class="nav nav-tabs" id="permissionTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'members' ? 'active' : '' ?>" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button" role="tab">
                            <i class="fas fa-users"></i> Miembros
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'permissions' ? 'active' : '' ?>" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab">
                            <i class="fas fa-shield-alt"></i> Permisos de Acción
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="permissionTabContent">
                    
                    <!-- Tab Miembros -->
                    <div class="tab-pane fade <?= $activeTab === 'members' ? 'show active' : '' ?>" id="members" role="tabpanel">
                        <div class="permission-card">
                            <div class="permission-header">
                                <h5><i class="fas fa-users text-primary"></i> Gestión de Miembros</h5>
                                <p class="text-muted mb-0">Agregar o remover usuarios de este grupo</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Agregar Usuarios</h6>
                                    <div class="search-box">
                                        <input type="text" id="userSearch" class="form-control" placeholder="Buscar usuarios..." onkeyup="searchUsers()">
                                        <div id="userSearchResults" class="search-results" style="display: none;"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Miembros Actuales</h6>
                                    <div id="currentMembers" class="selected-items">
                                        <div class="text-muted">Cargando miembros...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Permisos de Acción -->
                    <div class="tab-pane fade <?= $activeTab === 'permissions' ? 'show active' : '' ?>" id="permissions" role="tabpanel">
                        <div class="permission-card">
                            <div class="permission-header">
                                <h5><i class="fas fa-shield-alt text-primary"></i> Permisos de Acción</h5>
                                <p class="text-muted mb-0">Definir qué acciones pueden realizar los miembros de este grupo</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-eye"></i> Visualización</h6>
                                    <div class="permission-switch">
                                        <span>Ver documentos</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permView" checked>
                                        </div>
                                    </div>
                                    <div class="permission-switch">
                                        <span>Ver reportes</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permViewReports" checked>
                                        </div>
                                    </div>

                                    <h6 class="mt-4"><i class="fas fa-download"></i> Descarga</h6>
                                    <div class="permission-switch">
                                        <span>Descargar documentos</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permDownload">
                                        </div>
                                    </div>
                                    <div class="permission-switch">
                                        <span>Exportar reportes</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permExport">
                                        </div>
                                    </div>

                                    <h6 class="mt-4"><i class="fas fa-edit"></i> Edición</h6>
                                    <div class="permission-switch">
                                        <span>Crear documentos</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permCreate">
                                        </div>
                                    </div>
                                    <div class="permission-switch">
                                        <span>Editar documentos</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permEdit">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h6><i class="fas fa-trash"></i> Eliminación</h6>
                                    <div class="permission-switch">
                                        <span>Eliminar documentos</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permDelete">
                                        </div>
                                    </div>
                                    <div class="permission-switch">
                                        <span>Eliminar permanentemente</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permDeletePermanent">
                                        </div>
                                    </div>

                                    <h6 class="mt-4"><i class="fas fa-cog"></i> Administración</h6>
                                    <div class="permission-switch">
                                        <span>Gestionar usuarios</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permManageUsers">
                                        </div>
                                    </div>
                                    <div class="permission-switch">
                                        <span>Configurar sistema</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permSystemConfig">
                                        </div>
                                    </div>

                                    <h6 class="mt-4"><i class="fas fa-clock"></i> Límites Diarios</h6>
                                    <div class="mb-2">
                                        <label class="form-label">Descargas diarias</label>
                                        <input type="number" class="form-control" id="downloadLimit" placeholder="Sin límite" min="0">
                                    </div>
                                    <div>
                                        <label class="form-label">Subidas diarias</label>
                                        <input type="number" class="form-control" id="uploadLimit" placeholder="Sin límite" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Botón Guardar Flotante -->
<button id="saveBtn" class="btn btn-primary save-btn" onclick="saveGroupPermissions()" style="display: none;">
    <i class="fas fa-save"></i> Guardar Cambios
</button>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>

<script>
// Variables globales
let currentGroupId = <?= json_encode($selectedGroupId) ?>;
let allUsers = [];
let currentMembers = [];

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    loadGroups();
    loadAllUsers();
    
    // Si hay un grupo preseleccionado, cargarlo
    if (currentGroupId) {
        setTimeout(() => {
            document.getElementById('groupSelect').value = currentGroupId;
            loadGroupPermissions();
        }, 500);
    }
});

// Cargar grupos disponibles
async function loadGroups() {
    try {
        const response = await fetch('actions/get_groups.php');
        const data = await response.json();
        
        const select = document.getElementById('groupSelect');
        select.innerHTML = '<option value="">Seleccione un grupo...</option>';
        
        if (data.success && data.groups) {
            data.groups.forEach(group => {
                const option = document.createElement('option');
                option.value = group.id;
                option.textContent = `${group.name} (${group.total_members || 0} miembros)`;
                if (group.id == currentGroupId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error cargando grupos:', error);
        showAlert('Error al cargar grupos', 'error');
    }
}

// Cargar todos los usuarios
async function loadAllUsers() {
    try {
        const response = await fetch('../../api/get_users.php');
        const data = await response.json();
        
        if (data.success && data.users) {
            allUsers = data.users;
        }
    } catch (error) {
        console.error('Error cargando usuarios:', error);
    }
}

// Cargar permisos del grupo seleccionado
async function loadGroupPermissions() {
    const groupId = document.getElementById('groupSelect').value;
    
    if (!groupId) {
        document.getElementById('permissionsContent').style.display = 'none';
        document.getElementById('saveBtn').style.display = 'none';
        return;
    }

    currentGroupId = groupId;

    try {
        const response = await fetch(`actions/get_group_details.php?id=${groupId}`);
        const data = await response.json();
        
        if (data.success && data.group) {
            // Mostrar información del grupo
            document.getElementById('memberCount').textContent = data.group.total_members || 0;
            document.getElementById('groupStatus').textContent = data.group.status;
            document.getElementById('groupInfo').style.display = 'block';
            
            // Cargar miembros actuales
            currentMembers = data.members || [];
            displayCurrentMembers();
            
            // Cargar permisos de módulo
            const modulePermissions = data.module_permissions || {};
            loadModulePermissions(modulePermissions);
            
            // Mostrar contenido
            document.getElementById('permissionsContent').style.display = 'block';
            document.getElementById('saveBtn').style.display = 'block';
            
        } else {
            showAlert('Error al cargar permisos del grupo', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error de conexión', 'error');
    }
}

// Mostrar miembros actuales
function displayCurrentMembers() {
    const container = document.getElementById('currentMembers');
    
    if (currentMembers.length === 0) {
        container.innerHTML = '<div class="text-muted">No hay miembros en este grupo</div>';
        return;
    }
    
    container.innerHTML = currentMembers.map(member => `
        <span class="selected-item">
            ${member.full_name || (member.first_name + ' ' + member.last_name)}
            <span class="remove-item" onclick="removeMember(${member.id})">&times;</span>
        </span>
    `).join('');
}

// Buscar usuarios
function searchUsers() {
    const query = document.getElementById('userSearch').value.toLowerCase();
    const resultsContainer = document.getElementById('userSearchResults');
    
    if (query.length < 2) {
        resultsContainer.style.display = 'none';
        return;
    }
    
    const memberIds = currentMembers.map(m => m.id);
    const filteredUsers = allUsers.filter(user => 
        !memberIds.includes(user.id) &&
        (user.first_name.toLowerCase().includes(query) || 
         user.last_name.toLowerCase().includes(query) ||
         user.email.toLowerCase().includes(query))
    );
    
    resultsContainer.innerHTML = filteredUsers.map(user => `
        <div class="search-result-item" onclick="addMember(${user.id}, '${user.first_name} ${user.last_name}')">
            <strong>${user.first_name} ${user.last_name}</strong><br>
            <small class="text-muted">${user.email}</small>
        </div>
    `).join('');
    
    resultsContainer.style.display = filteredUsers.length > 0 ? 'block' : 'none';
}

// Agregar miembro
async function addMember(userId, userName) {
    try {
        const formData = new FormData();
        formData.append('group_id', currentGroupId);
        formData.append('user_id', userId);
        formData.append('action', 'add');
        
        const response = await fetch('actions/manage_group_members.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Agregar a la lista local
            const user = allUsers.find(u => u.id == userId);
            if (user) {
                currentMembers.push({
                    id: user.id,
                    first_name: user.first_name,
                    last_name: user.last_name,
                    full_name: user.first_name + ' ' + user.last_name
                });
                displayCurrentMembers();
            }
            
            // Limpiar búsqueda
            document.getElementById('userSearch').value = '';
            document.getElementById('userSearchResults').style.display = 'none';
            
            showAlert('Usuario agregado al grupo', 'success');
        } else {
            showAlert(data.message || 'Error al agregar usuario', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error de conexión', 'error');
    }
}

// Remover miembro
async function removeMember(userId) {
    if (!confirm('¿Está seguro de que desea remover este usuario del grupo?')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('group_id', currentGroupId);
        formData.append('user_id', userId);
        formData.append('action', 'remove');
        
        const response = await fetch('actions/manage_group_members.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentMembers = currentMembers.filter(m => m.id != userId);
            displayCurrentMembers();
            showAlert('Usuario removido del grupo', 'success');
        } else {
            showAlert(data.message || 'Error al remover usuario', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error de conexión', 'error');
    }
}

// Cargar permisos de módulo existentes
function loadModulePermissions(permissions) {
    document.getElementById('permView').checked = permissions.view !== false;
    document.getElementById('permViewReports').checked = permissions.view_reports !== false;
    document.getElementById('permDownload').checked = permissions.download === true;
    document.getElementById('permExport').checked = permissions.export === true;
    document.getElementById('permCreate').checked = permissions.create === true;
    document.getElementById('permEdit').checked = permissions.edit === true;
    document.getElementById('permDelete').checked = permissions.delete === true;
    document.getElementById('permDeletePermanent').checked = permissions.delete_permanent === true;
    document.getElementById('permManageUsers').checked = permissions.manage_users === true;
    document.getElementById('permSystemConfig').checked = permissions.system_config === true;
    document.getElementById('downloadLimit').value = permissions.download_limit || '';
    document.getElementById('uploadLimit').value = permissions.upload_limit || '';
}

// Guardar permisos del grupo
async function saveGroupPermissions() {
    if (!currentGroupId) {
        showAlert('Seleccione un grupo primero', 'warning');
        return;
    }

    try {
        const permissionsData = {
            group_id: currentGroupId,
            access_restrictions: {
                companies: [],
                departments: [],
                document_types: []
            },
            module_permissions: {
                view: document.getElementById('permView').checked,
                view_reports: document.getElementById('permViewReports').checked,
                download: document.getElementById('permDownload').checked,
                export: document.getElementById('permExport').checked,
                create: document.getElementById('permCreate').checked,
                edit: document.getElementById('permEdit').checked,
                delete: document.getElementById('permDelete').checked,
                delete_permanent: document.getElementById('permDeletePermanent').checked,
                manage_users: document.getElementById('permManageUsers').checked,
                system_config: document.getElementById('permSystemConfig').checked,
                download_limit: parseInt(document.getElementById('downloadLimit').value) || null,
                upload_limit: parseInt(document.getElementById('uploadLimit').value) || null
            }
        };
        
        const formData = new FormData();
        formData.append('permissions_data', JSON.stringify(permissionsData));
        
        const response = await fetch('actions/update_group_permissions.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Permisos actualizados exitosamente', 'success');
        } else {
            showAlert(data.message || 'Error al actualizar permisos', 'error');
        }
        
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error de conexión al guardar', 'error');
    }
}

// Función para mostrar alertas
function showAlert(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                     type === 'error' ? 'alert-danger' : 
                     type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    setTimeout(() => {
        const alert = document.querySelector('.alert:last-of-type');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Cerrar resultados de búsqueda al hacer click fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-box')) {
        document.querySelectorAll('.search-results').forEach(el => {
            el.style.display = 'none';
        });
    }
});

</script>

</body>
</html>