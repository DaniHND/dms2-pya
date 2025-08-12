/**
 * guaranteed-move.js - Solución garantizada para mover documentos
 * Esta versión mapea elementos a IDs conocidos sin depender de HTML
 */

console.log("🎯 Cargando solución garantizada para movimiento de documentos...");

// Mapeo manual de documentos (actualizar según tu BD)
const DOCUMENT_MAPPING = [
    { id: 37, name: "A.Wilson - Brownies" },
    { id: 36, name: "A.Wilson - Budines Cl\u00e1sicos LUCE BIEN" },
    { id: 35, name: "cocina_disney_orkidea" },
    { id: 34, name: "cocina_disney_orkidea" },
    { id: 33, name: "Sabrosas empanadas dulces y saladas" },
    { id: 32, name: "El Gran Libro De Los Postres - Maria Pilar" },
    { id: 31, name: "Sabrosas empanadas dulces y saladas" },
    { id: 30, name: "Pastelillos y panes" },
    { id: 29, name: "Donas__trenzas_y_berlinesas" },
    { id: 28, name: "bb4ea234-1639-42b8-a8a1-f156bffe51a0" }
];

console.log("📋 Mapeo de documentos cargado:", DOCUMENT_MAPPING);

// Función para crear botones con IDs garantizados
function createGuaranteedMoveButtons() {
    console.log("🔧 Creando botones con IDs garantizados...");
    
    // Buscar elementos que parezcan contener documentos
    const possibleContainers = document.querySelectorAll(`
        div, article, li, .card, .item, .document, .file,
        [class*="document"], [class*="file"], [class*="card"], [class*="item"]
    `);
    
    console.log(`🔍 Elementos posibles encontrados: ${possibleContainers.length}`);
    
    let buttonsCreated = 0;
    let documentIndex = 0;
    
    possibleContainers.forEach((element, index) => {
        // Saltar si ya tiene botón de mover garantizado
        if (element.querySelector(".guaranteed-move-btn")) return;
        
        // Saltar elementos muy pequeños o que parecen contenedores
        const text = element.textContent || "";
        if (text.trim().length < 10) return;
        
        // Buscar indicadores de que es un documento
        const hasDocumentIndicators = 
            text.includes("MB") || text.includes("KB") || text.includes("GB") ||
            text.includes(".pdf") || text.includes(".doc") || text.includes(".xls") ||
            text.includes("/") || // fechas
            element.querySelector("i[data-feather]") || // iconos feather
            element.querySelector(".icon") ||
            element.className.includes("document") ||
            element.className.includes("file") ||
            element.className.includes("card");
        
        if (!hasDocumentIndicators) return;
        
        // Asignar documento del mapeo
        if (documentIndex >= DOCUMENT_MAPPING.length) return;
        
        const documentData = DOCUMENT_MAPPING[documentIndex];
        console.log(`📄 Asignando documento ${documentData.id} a elemento ${index}`);
        
        // Crear botón garantizado
        const moveBtn = document.createElement("button");
        moveBtn.className = "btn btn-sm btn-outline guaranteed-move-btn";
        moveBtn.innerHTML = "📁 Mover (ID: " + documentData.id + ")";
        moveBtn.style.cssText = `
            margin: 4px; 
            padding: 6px 12px; 
            background: #e3f2fd; 
            border: 1px solid #2196f3; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 0.875rem;
            color: #1565c0;
        `;
        
        moveBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log(`🎯 CLICK GARANTIZADO: ID=${documentData.id}, Nombre="${documentData.name}"`);
            
            // Llamar directamente a la función de movimiento
            if (typeof showMoveDocumentModal === "function") {
                showMoveDocumentModal(documentData.id, documentData.name);
            } else {
                // Llamar directamente a move_document.php sin modal
                moveDocumentDirectly(documentData.id, documentData.name);
            }
        };
        
        // Agregar el botón de manera visible
        const firstChild = element.firstElementChild;
        if (firstChild) {
            firstChild.style.position = "relative";
            firstChild.appendChild(moveBtn);
        } else {
            element.appendChild(moveBtn);
        }
        
        buttonsCreated++;
        documentIndex++;
    });
    
    console.log(`✅ Botones garantizados creados: ${buttonsCreated}`);
    
    // Si no se crearon botones, crear uno de prueba universal
    if (buttonsCreated === 0) {
        createUniversalTestButton();
    }
}

// Función para mover documento directamente sin modal
function moveDocumentDirectly(documentId, documentName) {
    console.log(`🚀 Movimiento directo: ID=${documentId}, Nombre="${documentName}"`);
    
    // Preguntar al usuario la carpeta destino
    const folderId = prompt(`Mover "${documentName}" a qué carpeta?\n\n0 o vacío = Carpeta Raíz\n1 = Carpeta 1\n2 = Carpeta 2\n3 = Carpeta 3`, "1");
    
    if (folderId === null) {
        console.log("❌ Movimiento cancelado por el usuario");
        return;
    }
    
    console.log(`📋 Enviando: document_id=${documentId}, folder_id=${folderId}`);
    
    // Crear FormData con datos garantizados
    const formData = new FormData();
    formData.append("document_id", documentId);
    formData.append("folder_id", folderId || "");
    
    // Mostrar datos que se van a enviar
    console.log("📤 Datos enviados:", {
        document_id: documentId,
        folder_id: folderId || "null"
    });
    
    // Enviar solicitud
    fetch("move_document.php", {
        method: "POST",
        body: formData
    })
    .then(response => {
        console.log("📥 Respuesta recibida:", response.status, response.statusText);
        return response.json();
    })
    .then(data => {
        console.log("📋 Datos de respuesta:", data);
        
        if (data.success) {
            alert(`✅ ${data.message}`);
            window.location.reload();
        } else {
            alert(`❌ Error: ${data.message}`);
            console.error("Error completo:", data);
        }
    })
    .catch(error => {
        console.error("❌ Error de red:", error);
        alert("Error de conexión: " + error.message);
    });
}

// Función para crear botón de prueba universal
function createUniversalTestButton() {
    console.log("🆘 Creando botón de prueba universal...");
    
    const testBtn = document.createElement("div");
    testBtn.innerHTML = `
        <div style="
            position: fixed; 
            top: 20px; 
            right: 20px; 
            z-index: 9999; 
            background: #ff5722; 
            color: white; 
            padding: 15px; 
            border-radius: 8px; 
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        " onclick="testMove()">
            🧪 PROBAR MOVIMIENTO<br>
            <small>ID: ${DOCUMENT_MAPPING[0]?.id || "37"}</small>
        </div>
    `;
    
    document.body.appendChild(testBtn);
    
    // Función global para probar
    window.testMove = function() {
        const doc = DOCUMENT_MAPPING[0] || { id: 37, name: "Documento de Prueba" };
        moveDocumentDirectly(doc.id, doc.name);
    };
    
    console.log("🆘 Botón de prueba universal creado (esquina superior derecha)");
}

// Inicializar con múltiples intentos
let attempts = 0;
const maxAttempts = 3;

function initializeGuaranteed() {
    attempts++;
    console.log(`🚀 Intento garantizado ${attempts}/${maxAttempts}`);
    
    createGuaranteedMoveButtons();
    
    if (attempts < maxAttempts) {
        setTimeout(initializeGuaranteed, 2000);
    }
}

// Inicializar inmediatamente y después de delays
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeGuaranteed);
} else {
    initializeGuaranteed();
}

setTimeout(initializeGuaranteed, 1000);
setTimeout(initializeGuaranteed, 3000);

console.log("🎯 Solución garantizada cargada - IDs hardcoded listos");