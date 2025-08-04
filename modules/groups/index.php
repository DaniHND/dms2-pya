<?php
/*
 * modules/groups/index.php
 * Dashboard de grupos con sidebar integrado
 */

require_once '../../config/session.php';
require_once '../../config/database.php';

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    header('Location: ../../dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Grupos - DMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/app.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Sidebar Styles */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand {
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .nav-item {
            margin-bottom: 5px;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            border-left: 4px solid #ffd700;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            padding: 0;
        }
        
        .content-header {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            margin-bottom: 30px;
        }
        
        .content-body {
            padding: 0 30px 30px;
        }
        
        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 20px;
            transition: transform 0.2s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .stats-label {
            color: #666;
            font-weight: 500;
        }
        
        /* Group Cards */
        .group-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .group-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .group-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .group-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .group-description {
            color: #666;
            margin-bottom: 0;
        }
        
        .group-meta {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .group-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-weight: bold;
            color: #667eea;
            font-size: 1.1rem;
        }
        
        .stat-label {
            font-size: 0.85em;
            color: #666;
        }
        
        .group-actions {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            background: white;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .system-badge {
            background: #e7f3ff;
            color: #0c5aa6;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-left: 10px;
        }
        
        /* Filters */
        .filters-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        
        .btn-group-sm .btn {
            padding: 6px 12px;
            font-size: 0.875rem;
        }
        
        /* User Info */
        .user-info {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        
        .user-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-role {
            font-size: 0.9rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <a href="../../dashboard.php" class="sidebar-brand">
            <i class="fas fa-file-alt me-2"></i>
            DMS Sistema
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="../../dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="../documents/index.php" class="nav-link">
                    <i class="fas fa-folder"></i>
                    Subir Documentos
                </a>
            </li>
            <li class="nav-item">
                <a href="../../modules/archives/index.php" class="nav-link">
                    <i class="fas fa-archive"></i>
                    Archivos
                </a>
            </li>
            <li class="nav-item">
                <a href="../../modules/reports/index.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Reportes
                </a>
            </li>
            
            <?php if ($currentUser['role'] === 'admin'): ?>
            <li class="nav-item">
                <h6 class="nav-header px-3 py-2 text-white-50">ADMINISTRACIÓN</h6>
            </li>
            <li class="nav-item">
                <a href="../../modules/users/index.php" class="nav-link">
                    <i class="fas fa-user"></i>
                    Usuarios
                </a>
            </li>
            <li class="nav-item">
                <a href="../../modules/companies/index.php" class="nav-link">
                    <i class="fas fa-building"></i>
                    Empresas
                </a>
            </li>
            <li class="nav-item">
                <a href="../../modules/departments/index.php" class="nav-link">
                    <i class="fas fa-sitemap"></i>
                    Departamentos
                </a>
            </li>
            <li class="nav-item">
                <a href="../../modules/document-types/index.php" class="nav-link">
                    <i class="fas fa-tags"></i>
                    Tipos de Documentos
                </a>
            </li>
            <li class="nav-item">
                <a href="index.php" class="nav-link active">
                    <i class="fas fa-users"></i>
                    Grupos
                </a>
            </li>
            <li class="nav-item">
                <a href="../../modules/configuration/index.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    Configuración
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <!-- User Info -->
    <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></div>
        <div class="user-role"><?= ucfirst($currentUser['role']) ?></div>
        <a href="../../logout.php" class="btn btn-sm btn-outline-light mt-2 w-100">
            <i class="fas fa-sign-out-alt me-1"></i>Cerrar Sesión
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Content Header -->
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">
                    <i class="fas fa-users me-2 text-primary"></i>
                    Gestión de Grupos
                </h2>
                <p class="text-muted mb-0">Administrar grupos de usuarios y permisos del sistema</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="showCreateModal()">
                    <i class="fas fa-plus me-2"></i>Nuevo Grupo
                </button>
            </div>
        </div>
    </div>

    <!-- Content Body -->
    <div class="content-body">
        <!-- Estadísticas -->
        <div class="row mb-4" id="statsRow">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number" id="totalGroups">0</div>
                    <div class="stats-label">Total de Grupos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number" id="activeGroups">0</div>
                    <div class="stats-label">Grupos Activos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number" id="totalMembers">0</div>
                    <div class="stats-label">Total Miembros</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number" id="systemGroups">0</div>
                    <div class="stats-label">Grupos Sistema</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-container">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar grupos</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Nombre o descripción..." onkeyup="filterGroups()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" id="statusFilter" onchange="filterGroups()">
                        <option value="">Todos</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" id="typeFilter" onchange="filterGroups()">
                        <option value="">Todos</option>
                        <option value="system">Sistema</option>
                        <option value="custom">Personalizados</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>

        <!-- Lista de Grupos -->
        <div id="groupsList">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-3">Cargando grupos...</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear/Editar Grupo -->
<div class="modal fade" id="groupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Crear Nuevo Grupo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="groupForm">
                <div class="modal-body">
                    <input type="hidden" id="groupId" name="group_id">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="groupName" class="form-label">Nombre del Grupo *</label>
                                <input type="text" class="form-control" id="groupName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="groupStatus" class="form-label">Estado</label>
                                <select class="form-select" id="groupStatus" name="status">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="groupDescription" class="form-label">Descripción</label>
                        <textarea class="form-control" id="groupDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permisos Básicos</label>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="permView" checked disabled>
                                    <label class="form-check-label" for="permView">Ver documentos</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="permDownload">
                                    <label class="form-check-label" for="permDownload">Descargar</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="permCreate">
                                    <label class="form-check-label" for="permCreate">Crear</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="permEdit">
                                    <label class="form-check-label" for="permEdit">Editar</label>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">Los permisos detallados se configuran después de crear el grupo.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar Grupo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>

<script>
let allGroups = [];
let filteredGroups = [];

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    loadGroups();
    setupEventListeners();
});

function setupEventListeners() {
    document.getElementById('groupForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveGroup();
    });
}

// Cargar grupos
async function loadGroups() {
    try {
        const response = await fetch('actions/get_groups.php');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.groups) {
            allGroups = data.groups;
            filteredGroups = [...allGroups];
            updateStats();
            renderGroups();
        } else {
            showError('Error al cargar grupos: ' + (data.message || 'Respuesta inválida'));
        }
        
    } catch (error) {
        console.error('Error cargando grupos:', error);
        showError('Error de conexión: ' + error.message);
    }
}

// Actualizar estadísticas
function updateStats() {
    const stats = {
        total: allGroups.length,
        active: allGroups.filter(g => g.status === 'active').length,
        members: allGroups.reduce((sum, g) => sum + (parseInt(g.total_members) || 0), 0),
        system: allGroups.filter(g => g.is_system_group == 1).length
    };
    
    document.getElementById('totalGroups').textContent = stats.total;
    document.getElementById('activeGroups').textContent = stats.active;
    document.getElementById('totalMembers').textContent = stats.members;
    document.getElementById('systemGroups').textContent = stats.system;
}

// Renderizar grupos
function renderGroups() {
    const container = document.getElementById('groupsList');
    
    if (filteredGroups.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5>No hay grupos</h5>
                <p class="text-muted">No se encontraron grupos con los criterios de búsqueda.</p>
                <button class="btn btn-primary" onclick="showCreateModal()">
                    <i class="fas fa-plus me-2"></i>Crear Primer Grupo
                </button>
            </div>
        `;
        return;
    }
    
    const groupsHTML = filteredGroups.map(group => `
        <div class="group-card">
            <div class="group-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="group-title">${escapeHtml(group.name)}</h5>
                        <p class="group-description">${escapeHtml(group.description || 'Sin descripción')}</p>
                    </div>
                    <div class="text-end">
                        <span class="status-badge status-${group.status}">
                            ${group.status === 'active' ? 'Activo' : 'Inactivo'}
                        </span>
                        ${group.is_system_group == 1 ? '<span class="system-badge">Sistema</span>' : ''}
                    </div>
                </div>
            </div>
            
            <div class="group-meta">
                <div class="group-stats">
                    <div class="stat-item">
                        <div class="stat-value">${group.total_members || 0}</div>
                        <div class="stat-label">Miembros</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${group.active_members || 0}</div>
                        <div class="stat-label">Activos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${group.companies_represented || 0}</div>
                        <div class="stat-label">Empresas</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${group.departments_represented || 0}</div>
                        <div class="stat-label">Departamentos</div>
                    </div>
                </div>
                <div>
                    <small class="text-muted">Creado: ${formatDate(group.created_at)}</small>
                </div>
            </div>
            
            <div class="group-actions">
                <div class="btn-group btn-group-sm me-2" role="group">
                    <button class="btn btn-outline-primary" onclick="viewGroup(${group.id})" title="Ver detalles">
                        <i class="fas fa-eye"></i> Ver
                    </button>
                    <button class="btn btn-outline-success" onclick="manageMembers(${group.id})" title="Gestionar miembros">
                        <i class="fas fa-users"></i> Miembros
                    </button>
                    <button class="btn btn-outline-info" onclick="managePermissions(${group.id})" title="Configurar permisos">
                        <i class="fas fa-shield-alt"></i> Permisos
                    </button>
                </div>
                
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-secondary" onclick="editGroup(${group.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-outline-warning" onclick="toggleStatus(${group.id}, '${group.status}')" title="${group.status === 'active' ? 'Desactivar' : 'Activar'}">
                        <i class="fas fa-power-off"></i>
                    </button>
                    ${group.is_system_group != 1 ? `
                    <button class="btn btn-outline-danger" onclick="deleteGroup(${group.id})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = groupsHTML;
}

// Funciones de filtro
function filterGroups() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const type = document.getElementById('typeFilter').value;
    
    filteredGroups = allGroups.filter(group => {
        const matchesSearch = !search || 
            group.name.toLowerCase().includes(search) || 
            (group.description && group.description.toLowerCase().includes(search));
            
        const matchesStatus = !status || group.status === status;
        
        const matchesType = !type || 
            (type === 'system' && group.is_system_group == 1) ||
            (type === 'custom' && group.is_system_group != 1);
        
        return matchesSearch && matchesStatus && matchesType;
    });
    
    renderGroups();
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('typeFilter').value = '';
    filterGroups();
}

// Funciones de gestión
function showCreateModal() {
    document.getElementById('modalTitle').textContent = 'Crear Nuevo Grupo';
    document.getElementById('groupForm').reset();
    document.getElementById('groupId').value = '';
    document.getElementById('permView').checked = true;
    
    const modal = new bootstrap.Modal(document.getElementById('groupModal'));
    modal.show();
}

function editGroup(groupId) {
    const group = allGroups.find(g => g.id == groupId);
    if (!group) return;
    
    document.getElementById('modalTitle').textContent = 'Editar Grupo';
    document.getElementById('groupId').value = group.id;
    document.getElementById('groupName').value = group.name;
    document.getElementById('groupDescription').value = group.description || '';
    document.getElementById('groupStatus').value = group.status;
    
    try {
        const permissions = group.module_permissions ? JSON.parse(group.module_permissions) : {};
        document.getElementById('permDownload').checked = permissions.download || false;
        document.getElementById('permCreate').checked = permissions.create || false;
        document.getElementById('permEdit').checked = permissions.edit || false;
    } catch (e) {
        console.warn('Error cargando permisos:', e);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('groupModal'));
    modal.show();
}

async function saveGroup() {
    const formData = new FormData();
    const groupId = document.getElementById('groupId').value;
    
    formData.append('name', document.getElementById('groupName').value);
    formData.append('description', document.getElementById('groupDescription').value);
    formData.append('status', document.getElementById('groupStatus').value);
    
    const permissions = {
        view: true,
        download: document.getElementById('permDownload').checked,
        create: document.getElementById('permCreate').checked,
        edit: document.getElementById('permEdit').checked
    };
    formData.append('basic_permissions', JSON.stringify(permissions));
    
    if (groupId) {
        formData.append('group_id', groupId);
    }
    
    try {
        const url = groupId ? 'actions/update_group.php' : 'actions/create_group.php';
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('groupModal')).hide();
            await loadGroups();
        } else {
            showNotification(data.message || 'Error al guardar grupo', 'error');
        }
        
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    }
}

// Funciones de navegación
function viewGroup(groupId) {
    window.location.href = `permissions.php?group=${groupId}`;
}

function manageMembers(groupId) {
    window.location.href = `permissions.php?group=${groupId}&tab=members`;
}

function managePermissions(groupId) {
    window.location.href = `permissions.php?group=${groupId}&tab=permissions`;
}

async function toggleStatus(groupId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'activar' : 'desactivar';
    
    if (!confirm(`¿Confirma ${action} este grupo?`)) return;
    
    try {
        const formData = new FormData();
        formData.append('group_id', groupId);
        formData.append('status', newStatus);
        
        const response = await fetch('actions/toggle_group_status.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            loadGroups();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

async function deleteGroup(groupId) {
    const group = allGroups.find(g => g.id == groupId);
    if (!group) return;
    
    if (!confirm(`¿Confirma eliminar el grupo "${group.name}"?\n\nEsta acción no se puede deshacer.`)) return;
    
    try {
        const formData = new FormData();
        formData.append('group_id', groupId);
        
        const response = await fetch('actions/delete_group.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            loadGroups();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
    }
}

// Utilidades
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                     type === 'error' ? 'alert-danger' : 'alert-info';
    
    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    }, 5000);
}

function showError(message) {
    document.getElementById('groupsList').innerHTML = `
        <div class="text-center py-5">
            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
            <h5 class="text-danger">Error al cargar grupos</h5>
            <p class="text-muted">${message}</p>
            <button class="btn btn-primary" onclick="loadGroups()">
                <i class="fas fa-redo me-2"></i>Reintentar
            </button>
        </div>
    `;
}

</script>

</body>
</html>