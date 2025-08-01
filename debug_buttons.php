<?php
/*
 * debug_buttons.php
 * Script para diagnosticar problemas de CSS en los botones del módulo de Grupos
 */

require_once 'config/session.php';
require_once 'config/database.php';

// Verificar sesión
SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico CSS Botones - DMS2</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/groups.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f8fafc;
        }
        .debug-section { 
            background: white;
            margin: 20px 0; 
            padding: 20px; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .debug-title { 
            color: #1e293b; 
            font-weight: 600; 
            margin-bottom: 15px;
            border-bottom: 2px solid #8B4513;
            padding-bottom: 8px;
        }
        .test-buttons { 
            display: flex; 
            gap: 10px; 
            margin: 15px 0; 
            flex-wrap: wrap;
        }
        .btn-info { 
            background: #e0f2fe; 
            border: 1px solid #0284c7; 
            color: #0c4a6e; 
            padding: 8px 12px; 
            border-radius: 4px; 
            font-size: 12px;
            font-weight: 500;
        }
        .css-rules { 
            background: #f1f5f9; 
            padding: 15px; 
            border-radius: 6px; 
            font-family: monospace; 
            font-size: 12px;
            border-left: 4px solid #8B4513;
            margin: 10px 0;
        }
        .original-system {
            background: #f0fff4;
            border: 1px solid #22c55e;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .comparison-table th,
        .comparison-table td {
            border: 1px solid #e2e8f0;
            padding: 12px;
            text-align: left;
        }
        .comparison-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        /* COPIAR ESTILOS EXACTOS DE OTROS MÓDULOS */
        .system-btn {
            width: 36px;
            height: 36px;
            padding: 0;
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            background: #f8fafc;
            color: #64748b;
        }
        
        .system-btn:hover {
            background: #8B4513;
            color: white;
            border-color: #8B4513;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(139, 69, 19, 0.3);
        }
    </style>
</head>

<body>
    <h1>🔍 Diagnóstico CSS - Botones del Módulo de Grupos</h1>
    
    <div class="debug-section">
        <h2 class="debug-title">1. Información del Sistema</h2>
        <p><strong>Usuario:</strong> <?= htmlspecialchars($currentUser['username']) ?></p>
        <p><strong>Rol:</strong> <?= htmlspecialchars($currentUser['role']) ?></p>
        <p><strong>Navegador:</strong> <span id="browserInfo"></span></p>
        <p><strong>Fecha:</strong> <?= date('Y-m-d H:i:s') ?></p>
    </div>

    <div class="debug-section">
        <h2 class="debug-title">2. Archivos CSS Cargados</h2>
        <div id="cssFiles"></div>
    </div>

    <div class="debug-section">
        <h2 class="debug-title">3. Comparación de Botones</h2>
        
        <div class="original-system">
            <h3>✅ Botón Sistema Original (Debería funcionar correctamente)</h3>
            <div class="test-buttons">
                <button class="system-btn" onmouseover="showStyles(this, 'system')">
                    <i data-feather="eye"></i>
                </button>
                <button class="system-btn" onmouseover="showStyles(this, 'system')">
                    <i data-feather="edit"></i>
                </button>
                <button class="system-btn" onmouseover="showStyles(this, 'system')">
                    <i data-feather="user-plus"></i>
                </button>
            </div>
            <div class="btn-info">Estos botones usan el CSS copiado exactamente de otros módulos</div>
        </div>

        <h3>❓ Botones Módulo Grupos (Los problemáticos)</h3>
        <div class="test-buttons">
            <button class="btn-action btn-view" onmouseover="showStyles(this, 'groups')">
                <i data-feather="eye"></i>
            </button>
            <button class="btn-action btn-edit" onmouseover="showStyles(this, 'groups')">
                <i data-feather="edit"></i>
            </button>
            <button class="btn-action btn-users" onmouseover="showStyles(this, 'groups')">
                <i data-feather="user-plus"></i>
            </button>
            <button class="btn-action btn-toggle" onmouseover="showStyles(this, 'groups')">
                <i data-feather="pause"></i>
            </button>
        </div>
        <div class="btn-info">Estos son los botones que no cambian de color correctamente</div>
    </div>

    <div class="debug-section">
        <h2 class="debug-title">4. Estilos Aplicados (Hover sobre botones para ver)</h2>
        <div id="appliedStyles"></div>
    </div>

    <div class="debug-section">
        <h2 class="debug-title">5. CSS Esperado vs Actual</h2>
        <table class="comparison-table">
            <thead>
                <tr>
                    <th>Propiedad</th>
                    <th>Valor Esperado</th>
                    <th>Valor Actual</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody id="cssComparison">
                <!-- Se llenará con JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="debug-section">
        <h2 class="debug-title">6. Reglas CSS Detectadas</h2>
        <div class="css-rules" id="detectedRules">
            <!-- Se llenará con JavaScript -->
        </div>
    </div>

    <div class="debug-section">
        <h2 class="debug-title">7. Soluciones Posibles</h2>
        <div id="solutions">
            <h4>💡 Opciones para solucionar:</h4>
            <ol>
                <li><strong>Especificidad CSS:</strong> Usar selectores más específicos</li>
                <li><strong>!important:</strong> Forzar estilos con mayor prioridad</li>
                <li><strong>Inline CSS:</strong> Aplicar estilos directamente en HTML</li>
                <li><strong>Orden de carga:</strong> Cambiar orden de archivos CSS</li>
                <li><strong>Cache:</strong> Limpiar caché del navegador</li>
            </ol>
        </div>
    </div>

    <div class="debug-section">
        <h2 class="debug-title">8. Test en Vivo</h2>
        <button onclick="applyInlineStyles()" style="background: #8B4513; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">
            🔧 Aplicar Estilos Inline (Test)
        </button>
        <button onclick="resetStyles()" style="background: #ef4444; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; margin-left: 10px;">
            🔄 Resetear Estilos
        </button>
        <div class="btn-info" style="margin-top: 10px;">
            Usa estos botones para probar soluciones en tiempo real
        </div>
    </div>

    <script>
        // Inicializar feather icons
        feather.replace();

        // Mostrar información del navegador
        document.getElementById('browserInfo').textContent = navigator.userAgent;

        // Detectar archivos CSS cargados
        function detectCSSFiles() {
            const cssFiles = Array.from(document.styleSheets).map(sheet => {
                try {
                    return sheet.href || 'inline CSS';
                } catch (e) {
                    return 'CSS protegido';
                }
            });
            
            document.getElementById('cssFiles').innerHTML = cssFiles.map(file => 
                `<div style="padding: 5px; background: #f0f9ff; margin: 2px 0; border-radius: 4px; font-family: monospace; font-size: 12px;">
                    📄 ${file.split('/').pop()}
                </div>`
            ).join('');
        }

        // Mostrar estilos aplicados al hacer hover
        function showStyles(element, type) {
            const computedStyles = window.getComputedStyle(element);
            const appliedDiv = document.getElementById('appliedStyles');
            
            const relevantProps = [
                'width', 'height', 'background-color', 'color', 'border', 
                'border-radius', 'box-shadow', 'transform'
            ];
            
            let styleInfo = `<h4>🎨 Estilos de Botón ${type.toUpperCase()}:</h4><div class="css-rules">`;
            
            relevantProps.forEach(prop => {
                const value = computedStyles.getPropertyValue(prop);
                styleInfo += `${prop}: ${value}<br>`;
            });
            
            styleInfo += '</div>';
            appliedDiv.innerHTML = styleInfo;
            
            // Actualizar comparación
            updateComparison(computedStyles);
        }

        // Actualizar tabla de comparación
        function updateComparison(computedStyles) {
            const expected = {
                'width': '36px',
                'height': '36px',
                'background-color': 'rgb(248, 250, 252)', // #f8fafc
                'color': 'rgb(100, 116, 139)' // #64748b
            };
            
            const tbody = document.getElementById('cssComparison');
            tbody.innerHTML = '';
            
            Object.entries(expected).forEach(([prop, expectedValue]) => {
                const actualValue = computedStyles.getPropertyValue(prop);
                const matches = actualValue === expectedValue;
                
                tbody.innerHTML += `
                    <tr>
                        <td><code>${prop}</code></td>
                        <td><code>${expectedValue}</code></td>
                        <td><code>${actualValue}</code></td>
                        <td>${matches ? '✅' : '❌'}</td>
                    </tr>
                `;
            });
        }

        // Aplicar estilos inline para test
        function applyInlineStyles() {
            const buttons = document.querySelectorAll('.btn-action');
            buttons.forEach(btn => {
                btn.style.cssText = `
                    width: 36px !important;
                    height: 36px !important;
                    background: #f8fafc !important;
                    color: #64748b !important;
                    border: 2px solid transparent !important;
                    border-radius: 8px !important;
                    transition: all 0.2s ease !important;
                `;
                
                btn.addEventListener('mouseenter', function() {
                    this.style.background = '#8B4513 !important';
                    this.style.color = 'white !important';
                    this.style.borderColor = '#8B4513 !important';
                    this.style.transform = 'translateY(-1px)';
                    this.style.boxShadow = '0 3px 8px rgba(139, 69, 19, 0.3)';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.background = '#f8fafc !important';
                    this.style.color = '#64748b !important';
                    this.style.borderColor = 'transparent !important';
                    this.style.transform = 'none';
                    this.style.boxShadow = 'none';
                });
            });
            
            alert('✅ Estilos inline aplicados. ¿Ahora funcionan los colores?');
        }

        // Resetear estilos
        function resetStyles() {
            const buttons = document.querySelectorAll('.btn-action');
            buttons.forEach(btn => {
                btn.style.cssText = '';
                // Remover event listeners clonando el elemento
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
            });
            alert('🔄 Estilos reseteados');
        }

        // Detectar reglas CSS conflictivas
        function detectConflictingRules() {
            const rules = [];
            try {
                Array.from(document.styleSheets).forEach(sheet => {
                    if (sheet.cssRules) {
                        Array.from(sheet.cssRules).forEach(rule => {
                            if (rule.selectorText && rule.selectorText.includes('btn-action')) {
                                rules.push(`${rule.selectorText} { ${rule.style.cssText} }`);
                            }
                        });
                    }
                });
            } catch (e) {
                rules.push('⚠️ No se pueden acceder a algunas reglas CSS por CORS');
            }
            
            document.getElementById('detectedRules').innerHTML = rules.length > 0 
                ? rules.join('<br><br>') 
                : '❌ No se encontraron reglas CSS para .btn-action';
        }

        // Ejecutar diagnósticos al cargar
        window.addEventListener('load', function() {
            detectCSSFiles();
            detectConflictingRules();
        });
    </script>
</body>
</html>