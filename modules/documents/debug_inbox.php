<?php
// ===================================================================
// TEST_DIRECT_BUTTON.PHP - PARA VERIFICAR EL BOTÓN ESPECÍFICAMENTE
// ===================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simular datos básicos
$currentUser = ['id' => 1, 'first_name' => 'Test', 'last_name' => 'User', 'role' => 'admin'];
$canCreateFolders = true;
$currentPath = '1/2'; // Simular nivel 2
$pathParts = ['1', '2'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Directo del Botón</title>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .test-section {
            background: white;
            padding: 2rem;
            margin: 1rem 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-create {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            margin: 10px 0;
        }
        
        .btn-create:hover {
            background: #c0392b;
        }
        
        .debug-info {
            background: #333;
            color: white;
            padding: 1rem;
            border-radius: 4px;
            font-family: monospace;
            margin: 1rem 0;
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .working { background: #27ae60; }
        .broken { background: #e74c3c; }
    </style>
</head>
<body>
    <h1>🔧 Test Directo del Botón - Crear Carpeta</h1>
    
    <div class="debug-info">
🔍 INFORMACIÓN:
- canCreateFolders: <?= $canCreateFolders ? 'true' : 'false' ?>
- pathParts count: <?= count($pathParts) ?>
- Condición PHP: <?= ($canCreateFolders && count($pathParts) === 2) ? 'CUMPLE' : 'NO CUMPLE' ?>
    </div>
    
    <div class="test-section">
        <h2>🧪 Tests de Botones</h2>
        
        <h3>1. Botón Básico (siempre debería funcionar):</h3>
        <button class="btn-create working" onclick="testBasic()">
            <i data-feather="check"></i>
            <span>Test Básico</span>
        </button>
        
        <h3>2. Botón con Función Directa:</h3>
        <button class="btn-create working" onclick="openModalDirect()">
            <i data-feather="folder-plus"></i>
            <span>Abrir Modal Directo</span>
        </button>
        
        <h3>3. Botón Exacto como en inbox.php:</h3>
        <?php if ($canCreateFolders && count($pathParts) === 2): ?>
            <button class="btn-create" onclick="createDocumentFolder()">
                <i data-feather="folder-plus"></i>
                <span>Nueva Carpeta (Exacto)</span>
            </button>
        <?php else: ?>
            <div style="color: #666; padding: 1rem; background: #f8f9fa; border-radius: 4px;">
                ❌ Condición PHP no cumplida: canCreateFolders=<?= $canCreateFolders ? 'true' : 'false' ?>, pathParts=<?= count($pathParts) ?>
            </div>
        <?php endif; ?>
        
        <h3>4. Botón Forzado (sin condiciones PHP):</h3>
        <button class="btn-create working" onclick="createDocumentFolder()">
            <i data-feather="folder-plus"></i>
            <span>Nueva Carpeta (Forzado)</span>
        </button>
        
        <h3>5. Botón con addEventListener:</h3>
        <button class="btn-create working" id="eventButton">
            <i data-feather="folder-plus"></i>
            <span>Nueva Carpeta (Event Listener)</span>
        </button>
    </div>

    <!-- Modal exacto como en inbox.php -->
    <div id="createDocumentFolderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i data-feather="folder-plus"></i>
                    <span>Crear Carpeta de Documentos</span>
                </h3>
                <button class="modal-close" onclick="closeDocumentFolderModal()">
                    <i data-feather="x"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="createDocumentFolderForm" onsubmit="submitCreateDocumentFolder(event)">
                    <div class="form-group">
                        <label class="form-label">Nombre de la carpeta</label>
                        <input type="text" name="name" class="form-control" required placeholder="Ej: Contratos, Reportes, Facturas">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Descripción de la carpeta de documentos"></textarea>
                    </div>

                    <input type="hidden" name="company_id" value="<?= htmlspecialchars($pathParts[0] ?? '1') ?>">
                    <input type="hidden" name="department_id" value="<?= htmlspecialchars($pathParts[1] ?? '2') ?>">

                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeDocumentFolderModal()">
                            <span>Cancelar</span>
                        </button>
                        <button type="submit" class="btn-create">
                            <i data-feather="plus"></i>
                            <span>Crear Carpeta</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Variables globales exactas
        let canCreateFolders = <?= $canCreateFolders ? 'true' : 'false' ?>;
        let currentPath = '<?= $currentPath ?>';
        let pathParts = <?= json_encode($pathParts) ?>;
        
        console.log('🚀 TEST DIRECTO INICIADO');
        console.log('📊 Variables:', { canCreateFolders, currentPath, pathParts });
        
        // Función exacta de inbox.php
        function createDocumentFolder() {
            console.log('🔥 createDocumentFolder() EJECUTADA');
            console.log('🔍 canCreateFolders:', canCreateFolders, typeof canCreateFolders);
            
            if (!canCreateFolders) {
                console.error('❌ Sin permisos');
                alert('❌ Sin permisos para crear carpetas');
                return;
            }
            
            const modal = document.getElementById('createDocumentFolderModal');
            if (!modal) {
                console.error('❌ Modal no encontrado');
                alert('❌ Modal no encontrado');
                return;
            }
            
            console.log('✅ Abriendo modal...');
            modal.style.display = 'flex';
            
            setTimeout(() => {
                const nameInput = modal.querySelector('input[name="name"]');
                if (nameInput) nameInput.focus();
            }, 100);
            
            alert('✅ Modal abierto correctamente!');
        }
        
        // Funciones de test
        function testBasic() {
            console.log('🧪 Test básico');
            alert('✅ JavaScript funciona - Botón básico OK');
        }
        
        function openModalDirect() {
            console.log('🧪 Modal directo');
            const modal = document.getElementById('createDocumentFolderModal');
            if (modal) {
                modal.style.display = 'flex';
                alert('✅ Modal abierto directamente');
            } else {
                alert('❌ Modal no encontrado');
            }
        }
        
        function closeDocumentFolderModal() {
            const modal = document.getElementById('createDocumentFolderModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        
        function submitCreateDocumentFolder(event) {
            event.preventDefault();
            alert('✅ Formulario funcionaría correctamente');
            closeDocumentFolderModal();
        }
        
        // Test con addEventListener
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM cargado');
            feather.replace();
            
            // Agregar event listener al botón #5
            const eventButton = document.getElementById('eventButton');
            if (eventButton) {
                eventButton.addEventListener('click', function() {
                    console.log('🧪 Event listener ejecutado');
                    createDocumentFolder();
                });
                console.log('✅ Event listener agregado');
            }
            
            // Test automático después de cargar
            setTimeout(() => {
                console.log('🧪 EJECUTANDO TESTS AUTOMÁTICOS...');
                
                // Verificar que todos los botones existen
                const buttons = document.querySelectorAll('.btn-create');
                console.log(`📊 Botones encontrados: ${buttons.length}`);
                
                buttons.forEach((btn, index) => {
                    const onclick = btn.getAttribute('onclick');
                    console.log(`📋 Botón ${index + 1}: onclick="${onclick}"`);
                });
                
                // Verificar modal
                const modal = document.getElementById('createDocumentFolderModal');
                console.log('📦 Modal existe:', modal ? 'SÍ' : 'NO');
                
            }, 1000);
        });
        
        console.log('✅ Script cargado completamente');
    </script>
</body>
</html>