<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Test Upload Departamentos - DMS2</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
        .btn { background: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .log { background: #f8f9fa; padding: 10px; border-left: 4px solid #007bff; margin: 10px 0; max-height: 300px; overflow-y: auto; }
        iframe { width: 100%; height: 500px; border: 1px solid #ddd; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔧 Test en Vivo: Upload Departamentos</h1>
    
    <div class="section">
        <h2>1. 🎯 Abrir Upload.php en iframe</h2>
        <button class="btn" onclick="loadUploadPage()">📄 Cargar modules/documents/upload.php</button>
        <button class="btn" onclick="toggleConsole()">📊 Mostrar/Ocultar Consola</button>
        <div id="iframe-container"></div>
    </div>

    <div class="section" id="console-section" style="display: none;">
        <h2>2. 📊 Consola de Debug</h2>
        <div id="debug-log" class="log">Esperando carga de página...</div>
        <button class="btn" onclick="clearLog()">🗑️ Limpiar</button>
        <button class="btn" onclick="runTests()">🧪 Ejecutar Tests</button>
    </div>

    <div class="section">
        <h2>3. 🔍 Tests Automáticos</h2>
        <div id="test-results"></div>
    </div>

    <div class="section">
        <h2>4. 📝 Instrucciones Manuales</h2>
        <ol>
            <li><strong>Carga la página:</strong> Haz clic en "Cargar modules/documents/upload.php"</li>
            <li><strong>Inspecciona el select:</strong> Haz clic derecho en el select de departamentos → Inspeccionar elemento</li>
            <li><strong>Verifica las opciones:</strong> Busca elementos como <code>&lt;option data-company="1"&gt;</code></li>
            <li><strong>Prueba el filtro:</strong> Selecciona una empresa y mira si se filtran los departamentos</li>
            <li><strong>Revisa la consola:</strong> Presiona F12 → Console para ver mensajes de JavaScript</li>
        </ol>
    </div>

    <script>
        let debugLog = null;
        let testIframe = null;

        function log(message, type = 'info') {
            if (!debugLog) debugLog = document.getElementById('debug-log');
            
            const timestamp = new Date().toLocaleTimeString();
            const className = type === 'error' ? 'error' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info';
            
            debugLog.innerHTML += `<div class="${className}">[${timestamp}] ${message}</div>`;
            debugLog.scrollTop = debugLog.scrollHeight;
        }

        function clearLog() {
            if (debugLog) debugLog.innerHTML = 'Log limpiado...';
        }

        function toggleConsole() {
            const consoleSection = document.getElementById('console-section');
            consoleSection.style.display = consoleSection.style.display === 'none' ? 'block' : 'none';
        }

        function loadUploadPage() {
            log('🚀 Cargando modules/documents/upload.php...', 'info');
            
            const container = document.getElementById('iframe-container');
            container.innerHTML = '<iframe id="upload-iframe" src="modules/documents/upload.php"></iframe>';
            
            testIframe = document.getElementById('upload-iframe');
            
            testIframe.onload = function() {
                log('✅ Página cargada exitosamente', 'success');
                setTimeout(() => {
                    analyzeUploadPage();
                }, 1000);
            };

            testIframe.onerror = function() {
                log('❌ Error cargando la página', 'error');
            };
        }

        function analyzeUploadPage() {
            try {
                log('🔍 Analizando contenido de upload.php...', 'info');
                
                const iframeDoc = testIframe.contentDocument || testIframe.contentWindow.document;
                
                // Verificar elementos clave
                const companySelect = iframeDoc.getElementById('company_id');
                const departmentSelect = iframeDoc.getElementById('department_id');
                
                if (!companySelect) {
                    log('❌ No se encontró el select de empresas (#company_id)', 'error');
                } else {
                    // Corregir selector CSS
                    const companyOptions = Array.from(companySelect.options).filter(opt => opt.value !== '');
                    log(`✅ Select de empresas encontrado: ${companyOptions.length} opciones`, 'success');
                }
                
                if (!departmentSelect) {
                    log('❌ No se encontró el select de departamentos (#department_id)', 'error');
                } else {
                    // Corregir selector CSS y obtener más información
                    const allOptions = Array.from(departmentSelect.options);
                    const deptOptions = allOptions.filter(opt => opt.value !== '');
                    
                    log(`✅ Select de departamentos encontrado: ${deptOptions.length} opciones`, 'success');
                    log(`📊 Total opciones (incluyendo vacía): ${allOptions.length}`, 'info');
                    
                    // Verificar si hay departamentos en el HTML
                    if (deptOptions.length === 0) {
                        log('❌ PROBLEMA: No hay departamentos en el select', 'error');
                        log('🔍 Verificando HTML del select...', 'info');
                        log(`📄 HTML del select: ${departmentSelect.innerHTML.substring(0, 500)}...`, 'info');
                    } else {
                        // Verificar atributos data-company
                        let optionsWithData = 0;
                        deptOptions.forEach(option => {
                            if (option.getAttribute('data-company')) {
                                optionsWithData++;
                            }
                        });
                        
                        if (optionsWithData === deptOptions.length) {
                            log(`✅ Todos los departamentos tienen atributo data-company`, 'success');
                        } else {
                            log(`⚠️ Solo ${optionsWithData}/${deptOptions.length} departamentos tienen data-company`, 'warning');
                        }
                        
                        // Mostrar departamentos encontrados
                        log('📋 Departamentos encontrados:', 'info');
                        deptOptions.forEach(option => {
                            const name = option.textContent.trim();
                            const company = option.getAttribute('data-company');
                            const visible = option.style.display !== 'none';
                            log(`  • ${name} (empresa: ${company}, visible: ${visible})`, 'info');
                        });
                    }
                }
                
                // Verificar JavaScript
                const scripts = iframeDoc.querySelectorAll('script');
                log(`📜 Scripts encontrados: ${scripts.length}`, 'info');
                
                // Verificar si assets/js/documents.js se carga
                const docScript = iframeDoc.querySelector('script[src*="documents.js"]');
                if (docScript) {
                    log('✅ Script documents.js encontrado', 'success');
                } else {
                    log('⚠️ Script documents.js no encontrado', 'warning');
                }
                
            } catch (error) {
                log(`❌ Error analizando página: ${error.message}`, 'error');
            }
        }

        function runTests() {
            if (!testIframe) {
                log('❌ Primero carga la página de upload', 'error');
                return;
            }

            try {
                log('🧪 Ejecutando tests automáticos...', 'info');
                
                const iframeDoc = testIframe.contentDocument || testIframe.contentWindow.document;
                const iframeWindow = testIframe.contentWindow;
                
                // Test 1: Simular selección de empresa
                const companySelect = iframeDoc.getElementById('company_id');
                const departmentSelect = iframeDoc.getElementById('department_id');
                
                if (companySelect && departmentSelect) {
                    log('🧪 Test 1: Simulando selección de empresa...', 'info');
                    
                    // Seleccionar primera empresa (corregir selector)
                    const companyOptions = Array.from(companySelect.options).filter(opt => opt.value !== '');
                    const firstCompany = companyOptions[0];
                    
                    if (firstCompany) {
                        companySelect.value = firstCompany.value;
                        
                        // Disparar evento change
                        const changeEvent = new iframeWindow.Event('change', { bubbles: true });
                        companySelect.dispatchEvent(changeEvent);
                        
                        log(`  • Empresa seleccionada: ${firstCompany.textContent} (ID: ${firstCompany.value})`, 'info');
                        
                        // Verificar filtrado después de un momento
                        setTimeout(() => {
                            const allDeptOptions = Array.from(departmentSelect.options).filter(opt => opt.value !== '');
                            const visibleDepts = allDeptOptions.filter(opt => opt.style.display !== 'none');
                            
                            log(`  • Total departamentos: ${allDeptOptions.length}`, 'info');
                            log(`  • Departamentos visibles después del filtro: ${visibleDepts.length}`, 'info');
                            
                            if (visibleDepts.length > 0) {
                                log('✅ Test 1 PASÓ: El filtro funciona', 'success');
                                visibleDepts.forEach(opt => {
                                    log(`    ▸ ${opt.textContent.trim()}`, 'info');
                                });
                            } else if (allDeptOptions.length === 0) {
                                log('❌ Test 1 FALLÓ: No hay departamentos en el HTML', 'error');
                            } else {
                                log('❌ Test 1 FALLÓ: Hay departamentos pero el filtro los oculta todos', 'error');
                            }
                        }, 500);
                    } else {
                        log('❌ No se encontró ninguna empresa para seleccionar', 'error');
                    }
                }
                
                // Test 2: Verificar función de filtrado
                setTimeout(() => {
                    log('🧪 Test 2: Verificando función de filtrado...', 'info');
                    
                    if (typeof iframeWindow.filterDepartmentsByCompany === 'function') {
                        log('✅ Test 2 PASÓ: Función filterDepartmentsByCompany existe', 'success');
                    } else {
                        log('❌ Test 2 FALLÓ: Función filterDepartmentsByCompany no existe', 'error');
                    }
                }, 1000);
                
                // Test 3: Verificar DocumentUploader
                setTimeout(() => {
                    log('🧪 Test 3: Verificando DocumentUploader...', 'info');
                    
                    if (typeof iframeWindow.DocumentUploader !== 'undefined') {
                        log('✅ Test 3 PASÓ: Clase DocumentUploader disponible', 'success');
                    } else {
                        log('⚠️ Test 3: Clase DocumentUploader no disponible (puede ser normal)', 'warning');
                    }
                }, 1500);
                
            } catch (error) {
                log(`❌ Error ejecutando tests: ${error.message}`, 'error');
            }
        }

        // Funciones auxiliares para verificar desde consola del navegador
        function checkUploadPage() {
            console.log('🔍 Verificación manual de upload.php:');
            
            const companySelect = document.getElementById('company_id');
            const departmentSelect = document.getElementById('department_id');
            
            if (companySelect) {
                console.log('✅ Select de empresas encontrado');
                const options = companySelect.querySelectorAll('option[value!=""]');
                console.log(`📊 Empresas disponibles: ${options.length}`);
            } else {
                console.log('❌ Select de empresas NO encontrado');
            }
            
            if (departmentSelect) {
                console.log('✅ Select de departamentos encontrado');
                const options = departmentSelect.querySelectorAll('option[value!=""]');
                console.log(`📊 Departamentos disponibles: ${options.length}`);
                
                options.forEach(option => {
                    console.log(`  • ${option.textContent} (data-company: ${option.getAttribute('data-company')})`);
                });
            } else {
                console.log('❌ Select de departamentos NO encontrado');
            }
        }

        // Hacer funciones disponibles globalmente para debug
        window.checkUploadPage = checkUploadPage;
        window.filterDepartmentsByCompany = function(companyId) {
            if (testIframe) {
                const iframeWindow = testIframe.contentWindow;
                if (typeof iframeWindow.filterDepartmentsByCompany === 'function') {
                    iframeWindow.filterDepartmentsByCompany(companyId);
                    log(`🔧 Filtro aplicado para empresa: ${companyId}`, 'info');
                } else {
                    log('❌ Función de filtrado no disponible en iframe', 'error');
                }
            } else {
                log('❌ Iframe no cargado', 'error');
            }
        };

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            log('🚀 Sistema de testing inicializado', 'info');
            log('📋 Usa los botones para cargar y probar upload.php', 'info');
        });
    </script>
</body>
</html>