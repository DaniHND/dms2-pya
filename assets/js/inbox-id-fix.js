/**
 * inbox-id-fix.js - Solución JavaScript para capturar IDs sin modificar HTML
 * Esta solución funciona con cualquier estructura HTML existente
 */

console.log("🔧 Cargando parche para captura de IDs de documentos...");

// Función mejorada para extraer ID del documento desde múltiples fuentes
function extractDocumentId(element) {
    console.log("🔍 Extrayendo ID de elemento:", element);
    
    // Método 1: Buscar data-document-id en el elemento o padres
    let current = element;
    for (let i = 0; i < 5 && current; i++) {
        const dataId = current.getAttribute("data-document-id");
        if (dataId && dataId !== "0") {
            console.log(`✅ ID encontrado via data-document-id: ${dataId}`);
            return parseInt(dataId);
        }
        current = current.parentElement;
    }
    
    // Método 2: Buscar en URLs de enlaces (view.php?id=X, download.php?id=X, etc.)
    const links = element.querySelectorAll("a[href*=\"id=\"]");
    for (const link of links) {
        const href = link.getAttribute("href");
        const match = href.match(/[?&]id=(\d+)/);
        if (match) {
            const id = parseInt(match[1]);
            console.log(`✅ ID encontrado en URL: ${id} (${href})`);
            return id;
        }
    }
    
    // Método 3: Buscar en atributos onclick
    const clickableElements = element.querySelectorAll("[onclick]");
    for (const el of clickableElements) {
        const onclick = el.getAttribute("onclick");
        const match = onclick.match(/(\d+)/);
        if (match) {
            const id = parseInt(match[1]);
            if (id > 0) {
                console.log(`✅ ID encontrado en onclick: ${id} (${onclick})`);
                return id;
            }
        }
    }
    
    // Método 4: Buscar patrones en el texto del elemento
    const text = element.textContent || "";
    const textMatch = text.match(/ID\s*:?\s*(\d+)/i);
    if (textMatch) {
        const id = parseInt(textMatch[1]);
        console.log(`✅ ID encontrado en texto: ${id}`);
        return id;
    }
    
    // Método 5: Intentar extraer de clases CSS o IDs del DOM
    const className = element.className || "";
    const idName = element.id || "";
    const classMatch = className.match(/document-(\d+)|item-(\d+)|file-(\d+)/);
    const idMatch = idName.match(/document-(\d+)|item-(\d+)|file-(\d+)/);
    
    if (classMatch) {
        const id = parseInt(classMatch[1] || classMatch[2] || classMatch[3]);
        console.log(`✅ ID encontrado en clase: ${id}`);
        return id;
    }
    
    if (idMatch) {
        const id = parseInt(idMatch[1] || idMatch[2] || idMatch[3]);
        console.log(`✅ ID encontrado en ID DOM: ${id}`);
        return id;
    }
    
    console.warn("❌ No se pudo extraer ID del elemento");
    return null;
}

// Función para extraer nombre del documento
function extractDocumentName(element) {
    // Buscar en varios selectores comunes
    const selectors = [
        ".document-name",
        ".file-name", 
        ".name",
        "h3",
        "h4",
        ".title",
        "[data-document-name]"
    ];
    
    for (const selector of selectors) {
        const nameElement = element.querySelector(selector);
        if (nameElement) {
            const name = nameElement.textContent?.trim() || nameElement.getAttribute("data-document-name");
            if (name && name.length > 0) {
                return name;
            }
        }
    }
    
    // Fallback: usar el primer texto significativo
    const allText = element.textContent || "";
    const lines = allText.split("\n").map(line => line.trim()).filter(line => line.length > 0);
    
    for (const line of lines) {
        if (line.length > 3 && line.length < 100 && !line.match(/^\d+(\.\d+)?\s*(MB|KB|GB)/i)) {
            return line;
        }
    }
    
    return "Documento sin nombre";
}

// Función mejorada para agregar botones de mover
function addMoveButtonsToDocuments() {
    console.log("🔧 Agregando botones de mover con detección inteligente...");
    
    // Selectores amplios para encontrar elementos de documentos
    const selectors = [
        "[data-document-id]",
        ".document-card",
        ".file-card", 
        ".document-item",
        ".file-item",
        ".document",
        ".file",
        "article",
        ".grid-item",
        ".list-item",
        "[href*=\"view.php\"]",
        "[href*=\"download.php\"]"
    ];
    
    const processedElements = new Set();
    let buttonsAdded = 0;
    
    for (const selector of selectors) {
        const elements = document.querySelectorAll(selector);
        
        elements.forEach(element => {
            // Evitar procesar el mismo elemento múltiples veces
            if (processedElements.has(element)) return;
            processedElements.add(element);
            
            // Verificar si ya tiene botón de mover
            if (element.querySelector(".move-document-btn")) return;
            
            // Intentar extraer ID y nombre
            const documentId = extractDocumentId(element);
            const documentName = extractDocumentName(element);
            
            if (!documentId || documentId <= 0) {
                console.warn("⚠️ Elemento sin ID válido:", element);
                return;
            }
            
            console.log(`📄 Procesando documento: ID=${documentId}, Nombre="${documentName}"`);
            
            // Crear botón de mover
            const moveBtn = document.createElement("button");
            moveBtn.className = "btn btn-sm btn-outline move-document-btn";
            moveBtn.innerHTML = "📁 Mover";
            moveBtn.style.cssText = "margin: 4px; padding: 6px 12px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; font-size: 0.875rem;";
            
            moveBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log(`🎯 Click en mover: ID=${documentId}, Nombre="${documentName}"`);
                
                if (typeof showMoveDocumentModal === "function") {
                    showMoveDocumentModal(documentId, documentName);
                } else {
                    alert(`Mover documento ID: ${documentId}\nNombre: ${documentName}\n\n(Función showMoveDocumentModal no encontrada)`);
                }
            };
            
            // Buscar container de acciones o crear uno
            let actionsContainer = element.querySelector(".document-actions, .file-actions, .actions, .buttons");
            
            if (!actionsContainer) {
                // Crear container de acciones
                actionsContainer = document.createElement("div");
                actionsContainer.className = "document-actions";
                actionsContainer.style.cssText = "margin-top: 8px; display: flex; gap: 4px; flex-wrap: wrap;";
                
                // Intentar agregarlo en un lugar lógico
                const goodPlaces = element.querySelectorAll(".content, .body, .info, .details");
                if (goodPlaces.length > 0) {
                    goodPlaces[goodPlaces.length - 1].appendChild(actionsContainer);
                } else {
                    element.appendChild(actionsContainer);
                }
            }
            
            actionsContainer.appendChild(moveBtn);
            buttonsAdded++;
            
            console.log(`✅ Botón agregado a documento ID: ${documentId}`);
        });
    }
    
    console.log(`🎉 Total de botones agregados: ${buttonsAdded}`);
    
    if (buttonsAdded === 0) {
        console.warn("⚠️ No se pudieron agregar botones. Estructura HTML no reconocida.");
        // Mostrar elementos encontrados para debugging
        console.log("🔍 Elementos encontrados:");
        selectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            if (elements.length > 0) {
                console.log(`   ${selector}: ${elements.length} elementos`);
            }
        });
    }
}

// Auto-inicializar con múltiples intentos
let initAttempts = 0;
const maxAttempts = 5;

function tryInitialize() {
    initAttempts++;
    console.log(`🚀 Intento de inicialización ${initAttempts}/${maxAttempts}`);
    
    addMoveButtonsToDocuments();
    
    // Si no se agregaron botones y aún hay intentos, probar de nuevo
    const existingButtons = document.querySelectorAll(".move-document-btn");
    if (existingButtons.length === 0 && initAttempts < maxAttempts) {
        console.log("⏳ Reintentando en 2 segundos...");
        setTimeout(tryInitialize, 2000);
    } else if (existingButtons.length > 0) {
        console.log(`✅ Inicialización exitosa: ${existingButtons.length} botones agregados`);
    } else {
        console.warn("❌ No se pudieron agregar botones después de todos los intentos");
    }
}

// Inicializar cuando el DOM esté listo
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", tryInitialize);
} else {
    tryInitialize();
}

// También intentar después de un delay para contenido dinámico
setTimeout(tryInitialize, 1000);

console.log("✅ Parche de captura de IDs cargado");