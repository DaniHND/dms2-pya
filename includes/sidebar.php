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
            <li>
                <a href="#">
                    <span>Configuración</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<!-- Overlay para móvil -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<script>
    function showComingSoon(feature) {
        alert(feature + ' - Próximamente disponible');
    }
</script>