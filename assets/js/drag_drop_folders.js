/*
 * drag_drop_folders.js - VERSIÓN CORREGIDA FINAL
 * JavaScript para funcionalidad de drag & drop GARANTIZADA
 */

class FolderDragDrop {
    constructor() {
        this.draggedDocument = null;
        this.init();
    }

    init() {
        console.log('🚀 Inicializando sistema de drag & drop...');
        
        // Esperar a que el DOM esté completamente cargado
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        // Intentar configurar varias veces para asegurar que funcione
        setTimeout(() => this.setupDragAndDrop(), 100);
        setTimeout(() => this.setupDragAndDrop(), 500);
        setTimeout(() => this.setupDragAndDrop(), 1000);
        
        console.log('📁 Sistema de Drag & Drop configurado');
    }

    setupDragAndDrop() {
        this.setupDocumentDraggers();
        this.setupFolderDropZones();
    }

    // ==========================================
    // CONFIGURAR ELEMENTOS ARRASTRABLES
    // ==========================================
    setupDocumentDraggers() {
        // Buscar TODOS los posibles selectores de documentos
        const documentSelectors = [
            '.document-item',
            '.list-item[data-document-id]',
            '.list-item[data-id]',
            '[data-document-id]',
            '[data-id]',
            '.document-card',
            '.file-item',
            '.doc-item'
        ];

        let documentsFound = 0;

        documentSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(item => {
                // Solo procesar si no está ya configurado
                if (item.dataset.dragConfigured) return;

                const documentId = this.getDocumentId(item);
                const documentName = this.getDocumentName(item);

                if (documentId) {
                    this.makeElementDraggable(item, documentId, documentName);
                    documentsFound++;
                }
            });
        });

        console.log(`📄 Documentos configurados para arrastrar: ${documentsFound}`);
        return documentsFound;
    }

    getDocumentId(element) {
        return element.dataset.documentId || 
               element.dataset.id || 
               element.getAttribute('data-document-id') || 
               element.getAttribute('data-id') ||
               element.querySelector('[data-document-id]')?.dataset.documentId ||
               element.querySelector('[data-id]')?.dataset.id;
    }

    getDocumentName(element) {
        return element.dataset.documentName || 
               element.querySelector('.document-name')?.textContent ||
               element.querySelector('.name-text')?.textContent ||
               element.querySelector('.item-name')?.textContent ||
               element.querySelector('.file-name')?.textContent ||
               element.textContent?.trim().split('\n')[0] ||
               'Documento';
    }

    makeElementDraggable(element, documentId, documentName) {
        // Marcar como configurado
        element.dataset.dragConfigured = 'true';
        
        // Hacer arrastrable
        element.draggable = true;
        element.style.cursor = 'move';
        
        // Agregar clase visual
        element.classList.add('draggable-document');

        // Eventos de drag
        element.addEventListener('dragstart', (e) => {
            this.draggedDocument = {
                id: documentId,
                name: documentName.substring(0, 50), // Limitar longitud
                element: element
            };

            element.style.opacity = '0.5';
            element.classList.add('dragging');

            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', documentId);

            console.log(`🎯 Arrastrando: ${this.draggedDocument.name} (ID: ${documentId})`);
        });

        element.addEventListener('dragend', (e) => {
            element.style.opacity = '1';
            element.classList.remove('dragging');
            console.log('🏁 Drag finalizado');
        });
    }

    // ==========================================
    // CONFIGURAR ZONAS DE DROP (CARPETAS)
    // ==========================================
    setupFolderDropZones() {
        // Buscar TODOS los posibles selectores de carpetas
        const folderSelectors = [
            '.folder-item',
            '.list-item[data-folder-id]',
            '[data-folder-id]',
            '.folder-card',
            '.folder'
        ];

        let foldersFound = 0;

        folderSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(folder => {
                // Solo procesar si no está ya configurado
                if (folder.dataset.dropConfigured) return;

                const folderId = this.getFolderId(folder);
                const folderName = this.getFolderName(folder);

                if (folderId) {
                    this.makeElementDroppable(folder, folderId, folderName);
                    foldersFound++;
                }
            });
        });

        console.log(`📁 Carpetas configuradas como drop zones: ${foldersFound}`);
        return foldersFound;
    }

    getFolderId(element) {
        return element.dataset.folderId || 
               element.getAttribute('data-folder-id') ||
               element.querySelector('[data-folder-id]')?.dataset.folderId;
    }

    getFolderName(element) {
        return element.dataset.folderName ||
               element.querySelector('.folder-name')?.textContent ||
               element.querySelector('.name-text')?.textContent ||
               element.querySelector('.item-name')?.textContent ||
               element.textContent?.trim().split('\n')[0] ||
               'Carpeta';
    }

    makeElementDroppable(element, folderId, folderName) {
        // Marcar como configurado
        element.dataset.dropConfigured = 'true';
        
        // Agregar clase visual
        element.classList.add('droppable-folder');

        // Eventos de drop
        element.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        element.addEventListener('dragenter', (e) => {
            e.preventDefault();
            if (this.draggedDocument) {
                element.classList.add('drag-over');
                console.log(`📂 Hover sobre: ${folderName} (ID: ${folderId})`);
            }
        });

        element.addEventListener('dragleave', (e) => {
            if (!element.contains(e.relatedTarget)) {
                element.classList.remove('drag-over');
            }
        });

        element.addEventListener('drop', (e) => {
            e.preventDefault();
            element.classList.remove('drag-over');

            if (!this.draggedDocument) {
                console.error('❌ No hay documento para mover');
                return;
            }

            console.log(`🎯 Drop: ${this.draggedDocument.name} → ${folderName}`);
            this.moveDocumentToFolder(this.draggedDocument.id, folderId, folderName);
        });
    }

    // ==========================================
    // MOVER DOCUMENTO VIA API
    // ==========================================
    async moveDocumentToFolder(documentId, folderId, folderName) {
        if (!documentId || !folderId) {
            this.showError('IDs inválidos');
            return;
        }

        try {
            console.log('📡 Enviando petición...');
            console.log('📋 Datos:', { document_id: documentId, folder_id: folderId });

            const response = await fetch('modules/documents/api/move_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    document_id: parseInt(documentId),
                    folder_id: parseInt(folderId)
                })
            });

            console.log('📡 Respuesta HTTP:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('❌ Respuesta no es JSON:', text.substring(0, 200));
                throw new Error('El servidor no devolvió JSON válido');
            }

            const result = await response.json();
            console.log('📥 Respuesta:', result);

            if (result.success) {
                this.showSuccess(`Documento movido a: ${folderName}`);
                setTimeout(() => window.location.reload(), 1500);
            } else {
                this.showError(result.message || 'Error moviendo documento');
            }

        } catch (error) {
            console.error('❌ Error completo:', error);
            this.showError('Error: ' + error.message);
        }
    }

    // ==========================================
    // NOTIFICACIONES
    // ==========================================
    showSuccess(message) {
        this.createNotification(message, 'success');
    }

    showError(message) {
        this.createNotification(message, 'error');
    }

    createNotification(message, type) {
        // Remover notificación anterior si existe
        const existing = document.querySelector('.drag-drop-notification');
        if (existing) existing.remove();

        const notification = document.createElement('div');
        notification.className = 'drag-drop-notification';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: ${type === 'success' ? 
                'linear-gradient(135deg, #10b981, #059669)' : 
                'linear-gradient(135deg, #ef4444, #dc2626)'};
        `;
        notification.textContent = (type === 'success' ? '✅ ' : '❌ ') + message;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, type === 'success' ? 4000 : 6000);
    }

    // ==========================================
    // MÉTODOS PÚBLICOS
    // ==========================================
    refresh() {
        console.log('🔄 Refrescando drag & drop...');
        this.setupDragAndDrop();
    }

    debug() {
        console.log('🔍 DEBUG - Sistema de drag & drop:');
        
        const docs = document.querySelectorAll('[data-document-id], [data-id]');
        const folders = document.querySelectorAll('[data-folder-id]');
        
        console.log(`📄 Documentos encontrados: ${docs.length}`);
        console.log(`📁 Carpetas encontradas: ${folders.length}`);
        
        docs.forEach((doc, i) => {
            const id = this.getDocumentId(doc);
            const name = this.getDocumentName(doc);
            console.log(`  Doc ${i+1}: ID=${id}, Nombre="${name}", Draggable=${doc.draggable}`);
        });
        
        folders.forEach((folder, i) => {
            const id = this.getFolderId(folder);
            const name = this.getFolderName(folder);
            console.log(`  Folder ${i+1}: ID=${id}, Nombre="${name}"`);
        });
    }
}

// ==========================================
// INICIALIZACIÓN GLOBAL
// ==========================================
let dragDropSystem = null;

// Inicializar cuando el DOM esté listo
function initDragDrop() {
    if (!dragDropSystem) {
        dragDropSystem = new FolderDragDrop();
        window.folderDragDrop = dragDropSystem; // Para acceso global
    }
}

// Auto-inicializar
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDragDrop);
} else {
    initDragDrop();
}

// Reinicializar después de cambios en el DOM
setTimeout(initDragDrop, 1000);

// Funciones globales para usar desde la consola
window.debugDragDrop = () => dragDropSystem?.debug();
window.refreshDragDrop = () => dragDropSystem?.refresh();