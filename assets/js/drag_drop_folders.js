/*
 * drag_drop_folders.js
 * JavaScript para funcionalidad de drag & drop de documentos a carpetas
 */

class FolderDragDrop {
    constructor() {
        this.init();
        this.draggedDocument = null;
        this.dropZoneIndicator = document.getElementById('dropZoneIndicator');
        this.successNotification = document.getElementById('successNotification');
        this.errorNotification = document.getElementById('errorNotification');
    }

    init() {
        this.setupDocumentDraggers();
        this.setupFolderDropZones();
        console.log('📁 Sistema de Drag & Drop inicializado');
    }

    // ==========================================
    // CONFIGURAR ELEMENTOS ARRASTRABLES
    // ==========================================
    setupDocumentDraggers() {
        const documentItems = document.querySelectorAll('.document-item');
        
        documentItems.forEach(item => {
            // Hacer el elemento arrastrable
            item.draggable = true;
            item.style.cursor = 'move';
            
            // Eventos de drag para documentos
            item.addEventListener('dragstart', (e) => {
                const documentId = item.dataset.documentId || item.getAttribute('data-id');
                const documentName = item.dataset.documentName || 
                    item.querySelector('.document-name')?.textContent || 'Documento';
                
                if (!documentId) {
                    console.error('❌ Documento sin ID válido');
                    e.preventDefault();
                    return;
                }
                
                this.draggedDocument = {
                    id: documentId,
                    name: documentName,
                    element: item
                };
                
                // Efectos visuales
                item.classList.add('dragging');
                item.style.opacity = '0.5';
                
                console.log('🎯 Arrastrando:', this.draggedDocument.name);
                
                // Configurar datos de transferencia
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', documentId);
            });
            
            item.addEventListener('dragend', (e) => {
                // Limpiar efectos visuales
                item.classList.remove('dragging');
                item.style.opacity = '1';
                
                if (this.dropZoneIndicator) {
                    this.dropZoneIndicator.style.display = 'none';
                }
                
                console.log('🏁 Drag finalizado');
            });
        });
    }

    // ==========================================
    // CONFIGURAR ZONAS DE DROP (CARPETAS)
    // ==========================================
    setupFolderDropZones() {
        const folderItems = document.querySelectorAll('.folder-item');
        
        folderItems.forEach(folder => {
            // Eventos de drop para carpetas
            folder.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });
            
            folder.addEventListener('dragenter', (e) => {
                e.preventDefault();
                
                if (this.draggedDocument) {
                    // Efectos visuales de hover
                    folder.classList.add('drag-over');
                    
                    // Mostrar indicador de drop
                    if (this.dropZoneIndicator) {
                        const folderName = folder.querySelector('.folder-name')?.textContent || 'Carpeta';
                        this.dropZoneIndicator.innerHTML = `📁 Moviendo a: <strong>${folderName}</strong>`;
                        this.dropZoneIndicator.style.display = 'block';
                    }
                    
                    console.log('📂 Hover sobre carpeta:', folder.dataset.folderId);
                }
            });
            
            folder.addEventListener('dragleave', (e) => {
                // Solo remover efectos si realmente salimos del elemento
                if (!folder.contains(e.relatedTarget)) {
                    folder.classList.remove('drag-over');
                    
                    if (this.dropZoneIndicator) {
                        this.dropZoneIndicator.style.display = 'none';
                    }
                }
            });
            
            folder.addEventListener('drop', (e) => {
                e.preventDefault();
                
                // Limpiar efectos visuales
                folder.classList.remove('drag-over');
                
                if (this.dropZoneIndicator) {
                    this.dropZoneIndicator.style.display = 'none';
                }
                
                if (!this.draggedDocument) {
                    console.error('❌ No hay documento para mover');
                    return;
                }
                
                // Obtener ID de carpeta (puede ser null para "sin carpeta")
                const folderId = folder.dataset.folderId || null;
                const folderName = folder.querySelector('.folder-name')?.textContent || 'Carpeta';
                
                console.log('🎯 Drop en carpeta:', folderName, 'ID:', folderId);
                
                // Mover documento
                this.moveDocumentToFolder(this.draggedDocument.id, folderId, folderName);
            });
        });
    }

    // ==========================================
    // MOVER DOCUMENTO VIA API
    // ==========================================
    async moveDocumentToFolder(documentId, folderId, folderName) {
        if (!documentId) {
            this.showError('ID de documento inválido');
            return;
        }
        
        try {
            console.log('📡 Enviando petición de movimiento...');
            
            const response = await fetch('modules/folders/api/move_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    document_id: parseInt(documentId),
                    folder_id: folderId ? parseInt(folderId) : null
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Documento movido exitosamente');
                
                // Mostrar notificación de éxito
                const message = folderId ? 
                    `Documento movido a: ${folderName}` : 
                    'Documento movido fuera de carpetas';
                this.showSuccess(message);
                
                // Recargar página después de un momento para ver cambios
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
                
            } else {
                console.error('❌ Error del servidor:', result.message);
                this.showError(result.message || 'Error moviendo documento');
            }
            
        } catch (error) {
            console.error('❌ Error de red:', error);
            this.showError('Error de conexión. Verifica tu conexión a internet.');
        }
    }

    // ==========================================
    // NOTIFICACIONES
    // ==========================================
    showSuccess(message) {
        if (this.successNotification) {
            this.successNotification.textContent = '✅ ' + message;
            this.successNotification.style.display = 'block';
            
            setTimeout(() => {
                this.successNotification.style.display = 'none';
            }, 4000);
        } else {
            alert('Éxito: ' + message);
        }
    }
    
    showError(message) {
        if (this.errorNotification) {
            this.errorNotification.textContent = '❌ ' + message;
            this.errorNotification.style.display = 'block';
            
            setTimeout(() => {
                this.errorNotification.style.display = 'none';
            }, 5000);
        } else {
            alert('Error: ' + message);
        }
    }

    // ==========================================
    // MÉTODOS PÚBLICOS
    // ==========================================
    refresh() {
        // Reinicializar después de cambios en el DOM
        this.setupDocumentDraggers();
        this.setupFolderDropZones();
        console.log('🔄 Sistema de drag & drop refrescado');
    }
    
    destroy() {
        // Limpiar event listeners si es necesario
        const documentItems = document.querySelectorAll('.document-item');
        documentItems.forEach(item => {
            item.draggable = false;
            item.style.cursor = 'default';
        });
        
        console.log('🗑️ Sistema de drag & drop destruido');
    }
}

// ==========================================
// INICIALIZACIÓN AUTOMÁTICA
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    // Esperar un poco para que el DOM esté completamente cargado
    setTimeout(() => {
        if (typeof window.folderDragDrop === 'undefined') {
            window.folderDragDrop = new FolderDragDrop();
            console.log('🚀 Sistema de carpetas drag & drop listo');
        }
    }, 500);
});

// Función global para refrescar el sistema después de cambios
function refreshDragDrop() {
    if (window.folderDragDrop) {
        window.folderDragDrop.refresh();
    }
}

// Función global para mostrar notificaciones desde otras partes
function showFolderNotification(message, isError = false) {
    if (window.folderDragDrop) {
        if (isError) {
            window.folderDragDrop.showError(message);
        } else {
            window.folderDragDrop.showSuccess(message);
        }
    }
}