<?php
require_once '../../config/session.php';
require_once '../../config/database.php';
// require_once '../../includes/init.php'; // Reemplazado por bootstrap
/*
 * modules/groups/index.php
 * Módulo de Gestión de Grupos con diseño consistente
 */

$projectRoot = dirname(dirname(__DIR__));
require_once $projectRoot . '/config/session.php';
require_once $projectRoot . '/config/database.php';

// Verificar sesión y permisos
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$currentUser = SessionManager::getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    header('Location: ../../dashboard.php');
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Configuración de paginación y filtros
    $itemsPerPage = 10;
    $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Costruir consulta con filtros
    $whereConditions = ["ug.deleted_at IS NULL"];
    $params = [];
    
    if (!empty($searchTerm)) {
        $whereConditions[] = "(ug.name LIKE ? OR ug.description LIKE ?)";
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%';
    }
    
    if (!empty($statusFilter)) {
        $whereConditions[] = "ug.status = ?";
        $params[] = $statusFilter;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Obtener grupos con información adicional
    $groupsQuery = "
        SELECT 
            ug.*,
            COUNT(DISTINCT ugm.user_id) as total_members,
            COALESCE(CONCAT(creator.first_name, ' ', creator.last_name), 'Sistema') as created_by_name
        FROM user_groups ug
        LEFT JOIN user_group_members ugm ON ug.id = ugm.group_id
        LEFT JOIN users creator ON ug.created_by = creator.id
        $whereClause
        GROUP BY ug.id
        ORDER BY ug.is_system_group ASC, ug.created_at DESC
        LIMIT $itemsPerPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($groupsQuery);
    $stmt->execute($params);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total para paginación
    $countQuery = "
        SELECT COUNT(DISTINCT ug.id) as total
        FROM user_groups ug
        LEFT JOIN user_group_members ugm ON ug.id = ugm.group_id
        LEFT JOIN users creator ON ug.created_by = creator.id
        $whereClause
    ";
    
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalItems / $itemsPerPage);
    
} catch (Exception $e) {
    error_log('Error en grupos: ' . $e->getMessage());
    $groups = [];
    $totalItems = 0;
    $totalPages = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Grupos - DMS2</title>
    
    <!-- CSS Principal del sistema -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/modal.css">
    
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
        /* Estilos adicionales para mantener consistencia con tipos de documentos */
        .btn-action {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: none;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            margin: 0 2px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .btn-action:hover {
            background: rgba(59, 130, 246, 0.2);
        }
        .btn-action.edit {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        .btn-action.edit:hover {
            background: rgba(245, 158, 11, 0.2);
        }
        .btn-action.delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        .btn-action.delete:hover {
            background: rgba(239, 68, 68, 0.2);
        }
        .btn-action.toggle {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        .btn-action.toggle:hover {
            background: rgba(16, 185, 129, 0.2);
        }
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .cell-content {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .primary-text {
            font-weight: 600;
            color: #1e293b;
        }
        .secondary-text {
            font-size: 0.8rem;
            color: #64748b;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
        .no-data i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .table-container {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        .data-table tr:hover {
            background: #f8fafc;
        }
        .actions-header {
            text-align: center;
        }
        .table-header {
            padding: 16px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .table-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #374151;
        }
        .table-section {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .filters-section {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .filters-section h3 {
            margin: 0 0 16px 0;
            font-size: 1.1rem;
            color: #374151;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: 1fr 200px;
            gap: 16px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-group label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }
        .form-input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            background: white;
        }
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Estilo del botón igual a otros módulos */
        .btn.btn-primary {
            background: #8B4513;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn.btn-primary:hover {
            background: #654321;
        }
        
        /* Estilos para header igual a departamentos */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 24px;
            height: 80px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .header-left h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .header-info {
            text-align: right;
        }
        
        .user-name-header {
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .current-time {
            font-size: 12px;
            color: #64748b;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-icon {
            background: transparent;
            border: none;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-icon:hover {
            background: #f1f5f9;
            color: #374151;
        }
        
        .logout-btn {
            color: #ef4444;
        }
        
        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        /* Container igual a departamentos */
        .container {
            padding: 0 24px;
        }
        
        /* Botón crear igual a departamentos */
        .create-button-section {
            margin-bottom: 24px;
        }
        
        .btn-create-group {
            background: linear-gradient(135deg, var(--dms-primary) 0%, var(--dms-primary-hover) 100%);
            border: none;
            box-shadow: 0 4px 12px rgba(139, 69, 19, 0.3);
            padding: 14px 28px;
            font-weight: 600;
            font-size: 15px;
            text-transform: none;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border-radius: 8px;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-create-group:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 69, 19, 0.4);
            background: linear-gradient(135deg, var(--dms-primary-hover) 0%, #4a2c0a 100%);
        }
        
        .btn-create-group span {
            margin-left: 2px;
        }
        
        /* Actualizar estilos de filtros */
        .filters-section {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="dashboard-layout">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header igual a departamentos -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Gestión de Grupos</h1>
            </div>

            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                <div class="header-actions">
                    <button class="btn-icon" onclick="showComingSoon('Configuración')">
                        <i data-feather="settings"></i>
                    </button>
                    <a href="../../logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido del módulo -->
        <div class="container">
            <!-- Botón Crear Grupo -->
            <div class="create-button-section">
                <button class="btn btn-primary btn-create-group" onclick="openCreateGroupModal()">
                    <i data-feather="layers"></i>
                    <span>Crear Grupo</span>
                </button>
            </div>
            <!-- Filtros de búsqueda -->
            <div class="filters-section">
                <h3>Filtros de Búsqueda</h3>
                
                <form method="GET" class="filters-form" id="filtersForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Buscar Grupo</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   class="form-input"
                                   placeholder="Nombre, descripción..."
                                   value="<?php echo htmlspecialchars($searchTerm); ?>"
                                   oninput="autoSubmitFilters()">
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Estado</label>
                            <select id="status" name="status" class="form-input" onchange="autoSubmitFilters()">
                                <option value="">Todos los estados</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>
                                    Activo
                                </option>
                                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>
                                    Inactivo
                                </option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabla de grupos -->
            <div class="table-section">
                <div class="table-header">
                    <h3>Grupos de Usuarios (<?php echo $totalItems; ?> registros)</h3>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Grupo</th>
                                <th>Miembros</th>
                                <th>Estado</th>
                                <th>Fecha Creación</th>
                                <th class="actions-header">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($groups)): ?>
                                <tr>
                                    <td colspan="5" class="no-data">
                                        <i data-feather="users"></i>
                                        <p>No se encontraron grupos</p>
                                        <button class="btn btn-primary" onclick="openCreateGroupModal()">
                                            <i data-feather="plus"></i>
                                            Crear primer grupo
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($groups as $group): ?>
                                    <tr>
                                        <td>
                                            <div class="cell-content">
                                                <i data-feather="<?php echo $group['is_system_group'] ? 'shield' : 'users'; ?>"></i>
                                                <div>
                                                    <div class="primary-text"><?php echo htmlspecialchars($group['name']); ?></div>
                                                    <?php if (!empty($group['description'])): ?>
                                                        <div class="secondary-text"><?php echo htmlspecialchars($group['description']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cell-content">
                                                <i data-feather="users"></i>
                                                <span><?php echo $group['total_members']; ?> miembros</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $group['status']; ?>">
                                                <?php echo $group['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($group['created_at'])); ?></td>
                                        <td style="text-align: center;">
                                          
                                            <a href="permissions.php?group=<?php echo $group['id']; ?>&tab=permissions" 
                                               class="btn-action edit" 
                                               title="Configurar permisos">
                                                <i data-feather="shield"></i>
                                            </a>
                                            
                                            <button class="btn-action toggle" 
                                                    onclick="toggleGroupStatus(<?php echo $group['id']; ?>, '<?php echo $group['status']; ?>')"
                                                    title="<?php echo $group['status'] === 'active' ? 'Desactivar' : 'Activar'; ?>">
                                                <i data-feather="<?php echo $group['status'] === 'active' ? 'toggle-right' : 'toggle-left'; ?>"></i>
                                            </button>
                                            
                                            <?php if (!$group['is_system_group']): ?>
                                                <button class="btn-action delete" 
                                                        onclick="deleteGroup(<?php echo $group['id']; ?>)"
                                                        title="Eliminar grupo">
                                                    <i data-feather="trash-2"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal para crear grupo -->
    <div class="modal" id="createGroupModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Crear Nuevo Grupo</h3>
                <button type="button" class="modal-close" onclick="closeCreateGroupModal()">
                    <i data-feather="x"></i>
                </button>
            </div>
            <form id="createGroupForm" onsubmit="submitCreateGroup(event)">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nombre del Grupo *</label>
                            <input type="text" id="name" name="name" class="form-input" 
                                   placeholder="Nombre del grupo..." required maxlength="150">
                        </div>
                        <div class="form-group">
                            <label for="status">Estado</label>
                            <select id="status" name="status" class="form-input">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row single">
                        <div class="form-group">
                            <label for="description">Descripción</label>
                            <textarea id="description" name="description" class="form-input" rows="3"
                                      placeholder="Describe el grupo y su propósito..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateGroupModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="plus"></i>
                        Crear Grupo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    // Variables globales
    let currentModal = null;
    let searchTimeout = null;

    // Inicializar cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Inicializando módulo de Grupos...');
        
        // Inicializar iconos de Feather
        if (typeof feather !== 'undefined') {
            feather.replace();
            console.log('✅ Iconos de Feather inicializados');
        } else {
            console.error('❌ Feather Icons no está disponible');
        }
    });

    // Función para enviar filtros automáticamente
    function autoSubmitFilters() {
        // Limpiar timeout anterior si existe
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Esperar 500ms después de que el usuario deje de escribir
        searchTimeout = setTimeout(function() {
            document.getElementById('filtersForm').submit();
        }, 500);
    }

    // Función para mostrar coming soon igual a departamentos
    function showComingSoon(feature) {
        alert(`Función "${feature}" próximamente disponible`);
    }

    // Función para toggle del sidebar (mobile)
    function toggleSidebar() {
        // Funcionalidad para móviles si es necesaria
    }
    
    // Actualizar reloj cada minuto
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: '2-digit', 
            year: 'numeric'
        }) + ' ' + now.toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
    }
    
    // Actualizar tiempo cada minuto
    setInterval(updateTime, 60000);
    updateTime(); // Llamada inicial

    function openCreateGroupModal() {
        console.log('🆕 Abriendo modal para crear grupo...');
        
        const modal = document.getElementById('createGroupModal');
        if (modal) {
            modal.classList.add('active');
            currentModal = modal;
            
            // Reinicializar iconos después de mostrar el modal
            setTimeout(() => {
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            }, 100);
            
            console.log('✅ Modal de crear grupo abierto');
        }
    }

    function closeCreateGroupModal() {
        console.log('❌ Cerrando modal de crear grupo...');
        const modal = document.getElementById('createGroupModal');
        if (modal) {
            modal.classList.remove('active');
            currentModal = null;
            
            // Limpiar formulario
            const form = document.getElementById('createGroupForm');
            if (form) {
                form.reset();
            }
        }
    }

    function submitCreateGroup(event) {
        event.preventDefault();
        console.log('📝 Enviando formulario de crear grupo...');
        
        const formData = new FormData(event.target);
        const submitBtn = event.target.querySelector('button[type="submit"]');
        
        // Deshabilitar botón
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i data-feather="loader"></i> Creando...';
        
        // Reinicializar iconos
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        fetch('actions/create_group.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Grupo creado exitosamente');
                showNotification('Grupo creado exitosamente', 'success');
                closeCreateGroupModal();
                
                // Recargar página después de un breve delay
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                console.error('❌ Error al crear grupo:', data.message);
                showNotification(data.message || 'Error al crear el grupo', 'error');
            }
        })
        .catch(error => {
            console.error('❌ Error de conexión:', error);
            showNotification('Error de conexión al crear el grupo', 'error');
        })
        .finally(() => {
            // Restaurar botón
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i data-feather="plus"></i> Crear Grupo';
            
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });
    }

    function viewGroupDetails(groupId) {
        console.log('👁️ Viendo detalles del grupo:', groupId);
        // Redirigir a la página de permisos con tab de miembros
        window.location.href = `permissions.php?group=${groupId}&tab=members`;
    }

    function toggleGroupStatus(groupId, currentStatus) {
        const action = currentStatus === 'active' ? 'desactivar' : 'activar';
        const confirmMessage = `¿Está seguro que desea ${action} este grupo?`;
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        console.log(`🔄 Cambiando estado del grupo ${groupId} de ${currentStatus}`);
        
        const formData = new FormData();
        formData.append('group_id', groupId);
        
        fetch('actions/toggle_group_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Estado cambiado exitosamente');
                showNotification(`Grupo ${action}do correctamente`, 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                console.error('❌ Error al cambiar estado:', data.message);
                showNotification(data.message || `Error al ${action} grupo`, 'error');
            }
        })
        .catch(error => {
            console.error('❌ Error de conexión:', error);
            showNotification('Error de conexión', 'error');
        });
    }

    function deleteGroup(groupId) {
        if (!confirm('¿Está seguro de eliminar este grupo? Esta acción no se puede deshacer.')) {
            return;
        }
        
        console.log('🗑️ Eliminando grupo:', groupId);
        
        const formData = new FormData();
        formData.append('group_id', groupId);
        
        fetch('actions/delete_group.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Grupo eliminado exitosamente');
                showNotification('Grupo eliminado correctamente', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                console.error('❌ Error al eliminar grupo:', data.message);
                showNotification(data.message || 'Error al eliminar grupo', 'error');
            }
        })
        .catch(error => {
            console.error('❌ Error de conexión:', error);
            showNotification('Error de conexión', 'error');
        });
    }

    function showNotification(message, type = 'info') {
        console.log(`📢 Notificación [${type}]: ${message}`);
        
        // Crear elemento de notificación
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i data-feather="${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        // Agregar al DOM
        document.body.appendChild(notification);
        
        // Inicializar iconos
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        // Mostrar notificación
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        // Ocultar después de 5 segundos
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, 5000);
    }

    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && currentModal) {
            currentModal.classList.remove('active');
            currentModal = null;
        }
    });

    // Cerrar modal al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal') && e.target.classList.contains('active')) {
            e.target.classList.remove('active');
            currentModal = null;
        }
    });

    console.log('✅ Módulo de grupos JavaScript cargado correctamente');
    </script>
</body>
</html>