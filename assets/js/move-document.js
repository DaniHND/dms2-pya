/**
 * move-document.js - JavaScript para movimiento de documentos
 * Corrige el problema de ID de documento inválido
 */

console.log("📁 Cargando funciones de movimiento de documentos...");

// Variable global para almacenar el documento seleccionado
let selectedDocumentForMove = null;

// Función para mostrar modal de movimiento
function showMoveDocumentModal(documentId, documentName) {
    console.log(`🎯 Moviendo documento: ID=${documentId}, Nombre=${documentName}`);
    
    // Validar que el ID sea válido
    if (!documentId || documentId <= 0) {
        console.error("❌ ID de documento inválido:", documentId);
        alert("Error: ID de documento inválido");
        return;
    }
    
    // Guardar información del documento seleccionado
    selectedDocumentForMove = {
        id: documentId,
        name: documentName
    };
    
    // Crear modal dinámico si no existe
    let modal = document.getElementById("moveDocumentModal");
    if (!modal) {
        modal = createMoveDocumentModal();
    }
    
    // Actualizar título del modal
    const modalTitle = modal.querySelector(".modal-title");
    if (modalTitle) {
        modalTitle.textContent = `Mover: ${documentName}`;
    }
    
    // Cargar carpetas disponibles
    loadAvailableFolders();
    
    // Mostrar modal
    modal.style.display = "block";
    modal.classList.add("active");
}

// Función para crear el modal de movimiento dinámicamente
function createMoveDocumentModal() {
    const modalHTML = `
        <div id="moveDocumentModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Mover Documento</h3>
                    <button class="close-btn" onclick="closeMoveDocumentModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="moveDocumentForm">
                        <div class="form-group">
                            <label for="targetFolder">Carpeta de destino:</label>
                            <select id="targetFolder" class="form-input" required>
                                <option value="">Cargando carpetas...</option>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="button" onclick="closeMoveDocumentModal()" class="btn btn-secondary">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Mover Documento</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    // Agregar al body
    document.body.insertAdjacentHTML("beforeend", modalHTML);
    
    // Configurar evento del formulario
    const form = document.getElementById("moveDocumentForm");
    form.addEventListener("submit", handleMoveDocumentSubmit);
    
    return document.getElementById("moveDocumentModal");
}

// Función para cargar carpetas disponibles
function loadAvailableFolders() {
    const select = document.getElementById("targetFolder");
    
    // Opciones básicas
    select.innerHTML = `
        <option value="">Carpeta Raíz</option>
        <option value="1">Carpeta 1</option>
        <option value="2">Carpeta 2</option>
        <option value="3">Carpeta 3</option>
    `;
    
    // Intentar cargar carpetas dinámicamente (opcional)
    fetch("get_folders.php")
        .then(response => response.json())
        .then(data => {
            if (data.success && data.folders) {
                select.innerHTML = `<option value="">Carpeta Raíz</option>`;
                data.folders.forEach(folder => {
                    const option = document.createElement("option");
                    option.value = folder.id;
                    option.textContent = folder.name;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.warn("⚠️ No se pudieron cargar carpetas dinámicamente:", error);
        });
}

// Función para manejar el envío del formulario
function handleMoveDocumentSubmit(event) {
    event.preventDefault();
    
    if (!selectedDocumentForMove) {
        alert("Error: No hay documento seleccionado");
        return;
    }
    
    const folderId = document.getElementById("targetFolder").value;
    const submitBtn = event.target.querySelector('button[type="submit"]');
    
    // Mostrar estado de carga
    const originalText = submitBtn.textContent;
    submitBtn.textContent = "Moviendo...";
    submitBtn.disabled = true;
    
    console.log(`📋 Enviando solicitud de movimiento:`, {
        document_id: selectedDocumentForMove.id,
        folder_id: folderId
    });
    
    // Crear FormData con los datos correctos
    const formData = new FormData();
    formData.append("document_id", selectedDocumentForMove.id);
    formData.append("folder_id", folderId);
    
    // Enviar solicitud
    fetch("move_document.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 Respuesta del servidor:", data);
        
        // Restaurar botón
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            alert(`✅ ${data.message}`);
            closeMoveDocumentModal();
            
            // Recargar la página para mostrar cambios
            window.location.reload();
        } else {
            alert(`❌ Error: ${data.message}`);
            console.error("Error completo:", data);
        }
    })
    .catch(error => {
        console.error("❌ Error de red:", error);
        
        // Restaurar botón
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        
        alert("Error de conexión. Intenta de nuevo.");
    });
}

// Función para cerrar modal
function closeMoveDocumentModal() {
    const modal = document.getElementById("moveDocumentModal");
    if (modal) {
        modal.style.display = "none";
        modal.classList.remove("active");
    }
    selectedDocumentForMove = null;
}

// Función para agregar botones de mover a documentos existentes
function addMoveButtonsToDocuments() {
    console.log("🔧 Agregando botones de mover a documentos...");
    
    // Buscar elementos de documentos
    const documentCards = document.querySelectorAll("[data-document-id]");
    
    console.log(`📄 Documentos encontrados: ${documentCards.length}`);
    
    documentCards.forEach(card => {
        const documentId = card.getAttribute("data-document-id");
        const documentName = card.querySelector(".document-name, .file-name, h3, h4")?.textContent || "Documento";
        
        // Verificar si ya tiene botón de mover
        if (card.querySelector(".move-document-btn")) {
            return; // Ya tiene botón
        }
        
        // Crear botón de mover
        const moveBtn = document.createElement("button");
        moveBtn.className = "btn btn-sm btn-outline move-document-btn";
        moveBtn.innerHTML = "📁 Mover";
        moveBtn.onclick = () => showMoveDocumentModal(documentId, documentName);
        
        // Buscar container de acciones o agregar al final
        let actionsContainer = card.querySelector(".document-actions, .file-actions, .actions");
        if (!actionsContainer) {
            actionsContainer = document.createElement("div");
            actionsContainer.className = "document-actions";
            card.appendChild(actionsContainer);
        }
        
        actionsContainer.appendChild(moveBtn);
        
        console.log(`✅ Botón agregado a documento ID: ${documentId}`);
    });
}

// Auto-inicializar cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function() {
    console.log("🚀 DOM cargado, inicializando funciones de movimiento...");
    
    // Agregar botones después de un pequeño delay para asegurar que el contenido esté cargado
    setTimeout(addMoveButtonsToDocuments, 1000);
    
    // Agregar estilos CSS dinámicamente
    const style = document.createElement("style");
    style.textContent = `
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            display: flex;
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
            padding: 1rem;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 1rem;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        
        .move-document-btn {
            margin: 0.25rem;
            font-size: 0.875rem;
        }
    `;
    document.head.appendChild(style);
});

console.log("✅ Funciones de movimiento de documentos cargadas");