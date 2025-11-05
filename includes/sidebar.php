<?php
// includes/sidebar.php
// Componente Sidebar reutilizable - DMS2 (Actualizado con tipos de documentos)

// Asegurar que el usuario esté definido
if (!isset($currentUser)) {
    require_once dirname(__FILE__) . '/../config/session.php';
    $currentUser = SessionManager::getCurrentUser();
}

// Determinar la página actual para marcar como activa
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Función para determinar rutas relativas mejorada
function getRelativePath($targetPath)
{
    $currentPath = $_SERVER['PHP_SELF'];
    $currentDir = dirname($currentPath);

    // Detectar si estamos en root o en subdirectorio
    if (strpos($currentPath, '/modules/') !== false) {
        // Estamos en un módulo, ir dos niveles arriba
        return '../../' . $targetPath;
    } else {
        // Estamos en root
        return $targetPath;
    }
}

// Función mejorada para el logo
function getLogoPath()
{
    $currentPath = $_SERVER['PHP_SELF'];

    if (strpos($currentPath, '/modules/') !== false) {
        // Estamos en un módulo
        return 'https://perdomoyasociados.com/wp-content/uploads/2023/09/logo_perdomo_2023_dorado-768x150.png';
    } else {
        // Estamos en root
        return 'https://perdomoyasociados.com/wp-content/uploads/2023/09/logo_perdomo_2023_dorado-768x150.png';
    }
}

// Función para verificar si un enlace está activo
function isActive($page, $module = null)
{
    global $currentPage, $currentDir;

    if ($module) {
        return $currentDir === $module;
    }

    return $currentPage === $page;
}
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="<?php echo getLogoPath(); ?>" alt="Perdomo y Asociados" class="logo-image">
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item <?php echo isActive('dashboard.php') ? 'active' : ''; ?>">
                <a href="<?php echo getRelativePath('dashboard.php'); ?>" class="nav-link">
                    <i data-feather="home"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item <?php echo isActive('upload.php', '') ? 'active' : ''; ?>">
                <a href="<?php echo getRelativePath('modules/documents/upload.php'); ?>" class="nav-link">
                    <i data-feather="upload"></i>
                    <span>Subir Documentos</span>
                </a>
            </li>

            <li class="nav-item <?php echo isActive('inbox.php', '') ? 'active' : ''; ?>">
                <a href="<?php echo getRelativePath('modules/documents/inbox.php'); ?>" class="nav-link">
                    <i data-feather="inbox"></i>
                    <span>Archivos</span>
                </a>
            </li>

            <li class="nav-divider"></li>

            <li class="nav-item <?php echo isActive('index.php', 'reports') ? 'active' : ''; ?>">
                <a href="<?php echo getRelativePath('modules/reports/index.php'); ?>" class="nav-link">
                    <i data-feather="bar-chart-2"></i>
                    <span>Reportes</span>
                </a>
            </li>

            <?php if ($currentUser['role'] === 'admin'): ?>
                <li class="nav-section">
                    <span>ADMINISTRACIÓN</span>
                </li>

                <li class="nav-item <?php echo isActive('index.php', 'users') ? 'active' : ''; ?>">
                    <a href="<?php echo getRelativePath('modules/users/index.php'); ?>" class="nav-link">
                        <i data-feather="users"></i>
                        <span>Usuarios</span>
                    </a>
                </li>

                <li class="nav-item <?php echo isActive('index.php', 'companies') ? 'active' : ''; ?>">
                    <a href="<?php echo getRelativePath('modules/companies/index.php'); ?>" class="nav-link">
                        <i data-feather="briefcase"></i>
                        <span>Empresas</span>
                    </a>
                </li>

                <!-- DEPARTAMENTOS -->
                <li class="nav-item <?php echo isActive('index.php', 'departments') ? 'active' : ''; ?>">
                    <a href="<?php echo getRelativePath('modules/departments/index.php'); ?>" class="nav-link">
                        <i data-feather="layers"></i>
                        <span>Departamentos</span>
                    </a>
                </li>

                <!-- TIPOS DE DOCUMENTOS - NUEVO MÓDULO AGREGADO -->
                <li class="nav-item <?php echo isActive('index.php', 'document-types') ? 'active' : ''; ?>">
                    <a href="<?php echo getRelativePath('modules/document-types/index.php'); ?>" class="nav-link">
                        <i data-feather="file-text"></i>
                        <span>Tipos de Documentos</span>
                    </a>
                </li>

                <li class="nav-item <?php echo isActive('index.php', 'groups') ? 'active' : ''; ?>">
                    <a href="<?php echo getRelativePath('modules/groups/index.php'); ?>" class="nav-link">
                        <i data-feather="users"></i>
                        <span>Grupos</span>
                    </a>
                </li>

            <?php endif; ?>
            <li class="nav-divider"></li>
            
            <!-- CONFIGURACIÓN CON MENÚ DESPLEGABLE -->
            <li class="nav-item dropdown-container-sidebar">
                <button class="nav-link config-btn-sidebar" onclick="toggleSidebarConfigMenu(event)" id="sidebarConfigBtn">
                    <i data-feather="settings"></i>
                    <span>Configuración</span>
                    <i data-feather="chevron-down" class="chevron-icon-sidebar"></i>
                </button>
                
                <!-- Menú desplegable del sidebar -->
                <div class="sidebar-dropdown-menu" id="sidebarConfigDropdown">
                    <button class="sidebar-dropdown-item" onclick="closeSidebarConfigMenu(); showChangePasswordModal();">
                        <i data-feather="lock"></i>
                        <span>Cambiar contraseña</span>
                    </button>
                    <button class="sidebar-dropdown-item" onclick="closeSidebarConfigMenu(); openHelp();">
                        <i data-feather="help-circle"></i>
                        <span>Ayuda</span>
                    </button>
                </div>
            </li>
        </ul>
    </nav>
</aside>

<!-- Overlay para móvil -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Estilos para el menú desplegable del sidebar -->
<style>
.nav-item.dropdown-container-sidebar {
    position: relative;
}

.config-btn-sidebar {
    width: 100%;
    background: transparent;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
    color: #cbd5e1;
    font-weight: 500;
    font-size: 0.875rem;
    border-radius: 8px;
}

.config-btn-sidebar:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.1);
}

.config-btn-sidebar.active {
    color: #D4AF37;
    background: rgba(212, 175, 55, 0.1);
}

.chevron-icon-sidebar {
    width: 16px;
    height: 16px;
    transition: transform 0.3s ease;
    margin-left: auto;
}

.config-btn-sidebar.active .chevron-icon-sidebar {
    transform: rotate(180deg);
}

/* Menú desplegable del sidebar */
.sidebar-dropdown-menu {
    display: none;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    margin: 0.5rem 0.75rem 0.5rem 0.75rem;
    padding: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    overflow: hidden;
}

.sidebar-dropdown-menu.active {
    display: block;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        max-height: 300px;
        transform: translateY(0);
    }
}

.sidebar-dropdown-item {
    width: 100%;
    padding: 0.75rem 1rem;
    background: transparent;
    border: none;
    border-radius: 6px;
    text-align: left;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #cbd5e1;
    font-size: 14px;
    transition: all 0.2s ease;
    margin-bottom: 0.25rem;
}

.sidebar-dropdown-item:last-child {
    margin-bottom: 0;
}

.sidebar-dropdown-item:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    transform: translateX(4px);
}

.sidebar-dropdown-item i {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.sidebar-dropdown-item span {
    flex: 1;
}

/* Ajustes para el sidebar colapsado */
.sidebar.collapsed .sidebar-dropdown-menu {
    position: absolute;
    left: 100%;
    top: 0;
    margin-left: 0.5rem;
    min-width: 220px;
    z-index: 1000;
    background: #4e342e;
    border: 1px solid #334155;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

.sidebar.collapsed .config-btn-sidebar span,
.sidebar.collapsed .chevron-icon-sidebar {
    display: none;
}

.sidebar.collapsed .nav-item.dropdown-container-sidebar:hover .sidebar-dropdown-menu.active {
    display: block;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar-dropdown-menu {
        margin: 0.5rem 0.5rem;
    }
    
    .sidebar-dropdown-item {
        padding: 0.65rem 0.75rem;
        font-size: 13px;
    }
}
</style>

<script>
// Toggle menú de configuración del sidebar
function toggleSidebarConfigMenu(event) {
    event.preventDefault();
    event.stopPropagation();
    
    const dropdown = document.getElementById('sidebarConfigDropdown');
    const button = document.getElementById('sidebarConfigBtn');
    
    if (dropdown.classList.contains('active')) {
        closeSidebarConfigMenu();
    } else {
        dropdown.classList.add('active');
        button.classList.add('active');
        
        // Actualizar iconos de Feather
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }
}

// Cerrar menú de configuración del sidebar
function closeSidebarConfigMenu() {
    const dropdown = document.getElementById('sidebarConfigDropdown');
    const button = document.getElementById('sidebarConfigBtn');
    
    if (dropdown) {
        dropdown.classList.remove('active');
    }
    
    if (button) {
        button.classList.remove('active');
    }
}

// Cerrar menú al hacer clic fuera
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('sidebarConfigDropdown');
    const button = document.getElementById('sidebarConfigBtn');
    
    if (dropdown && button && 
        !dropdown.contains(e.target) && 
        !button.contains(e.target)) {
        closeSidebarConfigMenu();
    }
});

// Cerrar menú al presionar ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSidebarConfigMenu();
    }
});

// Función para ayuda
function openHelp() {
    alert('Sistema de Ayuda - Próximamente disponible\n\nPara soporte inmediato, contacte al administrador.');
}

// Inicializar iconos cuando el documento esté listo
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});

function showComingSoon(feature) {
    alert(feature + ' - Próximamente disponible');
}
</script>