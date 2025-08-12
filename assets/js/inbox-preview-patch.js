// assets/js/inbox-preview-patch.js
// Parche para corregir vista previa en inbox sin modificar view.php

console.log('🔧 Aplicando parche para vista previa de documentos');

(function() {
    'use strict';
    
    // Esperar a que el DOM esté listo
    function initPatch() {
        // Interceptar la función viewDocument existente
        if (typeof window.viewDocument === 'function') {
            const originalViewDocument = window.viewDocument;
            
            window.viewDocument = function(documentId) {
                console.log('👁️ Interceptando viewDocument para ID:', documentId);
                showDocumentPreview(documentId);
            };
            
            console.log('✅ Función viewDocument interceptada correctamente');
        }
        
        // También interceptar clics directos en elementos de documentos
        document.addEventListener('click', function(e) {
            // Buscar clics en botones de vista
            const viewBtn = e.target.closest('.view-btn, .document-preview, [onclick*="viewDocument"]');
            if (viewBtn) {
                e.preventDefault();
                e.stopPropagation();
                
                // Extraer ID del documento
                let documentId = viewBtn.dataset.docId || 
                               viewBtn.dataset.documentId ||
                               viewBtn.dataset.id;
                
                // Si no hay data-attribute, extraer del onclick
                if (!documentId && viewBtn.getAttribute('onclick')) {
                    const match = viewBtn.getAttribute('onclick').match(/viewDocument\((\d+)\)/);
                    if (match) documentId = match[1];
                }
                
                if (documentId) {
                    showDocumentPreview(documentId);
                }
            }
        });
    }
    
    // Función principal para mostrar vista previa
    function showDocumentPreview(documentId) {
        const modal = document.getElementById('previewModal');
        const title = document.getElementById('previewTitle');
        const content = document.getElementById('previewContent');
        
        // Si no existe el modal, usar fallback
        if (!modal || !title || !content) {
            console.warn('⚠️ Modal no encontrado, abriendo en nueva ventana');
            window.open(`view.php?id=${documentId}`, '_blank');
            return;
        }
        
        // Mostrar modal con loading
        showLoadingState(title, content, documentId);
        
        // Mostrar modal
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Cargar contenido
        loadDocumentContent(documentId, title, content);
    }
    
    // Estado de carga
    function showLoadingState(title, content, documentId) {
        title.innerHTML = `
            <i data-feather="loader" class="spinning"></i>
            <span>Cargando vista previa...</span>
        `;
        
        content.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 2rem; color: #64748b; min-height: 300px;">
                <div class="loading-spinner"></div>
                <p style="margin-top: 1rem;">Cargando documento...</p>
            </div>
        `;
        
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }
    
    // Cargar contenido del documento
    function loadDocumentContent(documentId, title, content) {
        // Buscar datos del documento en variables globales
        let documentData = null;
        
        if (typeof documentsData !== 'undefined' && Array.isArray(documentsData)) {
            documentData = documentsData.find(doc => doc.id == documentId);
        }
        
        if (documentData) {
            renderDocumentPreview(documentData, title, content);
        } else {
            // Usar iframe como fallback
            loadDocumentInFrame(documentId, title, content);
        }
    }
    
    // Cargar en iframe
    function loadDocumentInFrame(documentId, title, content) {
        title.innerHTML = `
            <i data-feather="file-text"></i>
            <span>Vista de Documento</span>
            <div class="preview-actions">
                <button class="preview-btn" onclick="openInNewWindow(${documentId})" title="Abrir en nueva ventana">
                    <i data-feather="external-link"></i>
                </button>
                <button class="preview-btn" onclick="closePreviewModal()" title="Cerrar">
                    <i data-feather="x"></i>
                </button>
            </div>
        `;
        
        content.innerHTML = `
            <div class="preview-iframe-container">
                <iframe src="view.php?id=${documentId}" 
                        class="preview-iframe"
                        onload="this.style.opacity='1'"
                        onerror="handleFrameError(this, ${documentId})">
                </iframe>
            </div>
        `;
        
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }
    
    // Renderizar vista previa según tipo
    function renderDocumentPreview(doc, title, content) {
        title.innerHTML = `
            <i data-feather="file-text"></i>
            <span>Vista Previa - ${doc.name}</span>
            <div class="preview-actions">
                ${(typeof canDownload !== 'undefined' && canDownload) ? `
                <button class="preview-btn btn-download" onclick="downloadDocument(${doc.id})" title="Descargar">
                    <i data-feather="download"></i>
                </button>` : ''}
                <button class="preview-btn btn-external" onclick="openInNewWindow(${doc.id})" title="Abrir en nueva ventana">
                    <i data-feather="external-link"></i>
                </button>
                <button class="preview-btn btn-close" onclick="closePreviewModal()" title="Cerrar">
                    <i data-feather="x"></i>
                </button>
            </div>
        `;
        
        const fileExtension = (doc.file_path || '').split('.').pop().toLowerCase();
        const mimeType = doc.mime_type || '';
        const fileUrl = doc.file_path;
        
        let previewContent = '';
        
        // Determinar tipo de vista previa
        if (mimeType.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(fileExtension)) {
            previewContent = `
                <div class="preview-viewer">
                    <img src="${fileUrl}" 
                         alt="${doc.name}" 
                         class="preview-image"
                         onload="this.classList.add('loaded')"
                         onerror="handleImageError(this)">
                </div>
            `;
        } else if (mimeType === 'application/pdf' || fileExtension === 'pdf') {
            previewContent = `
                <div class="preview-viewer">
                    <iframe src="${fileUrl}" 
                            class="preview-pdf"
                            onload="this.classList.add('loaded')">
                    </iframe>
                </div>
            `;
        } else if (mimeType.startsWith('video/')) {
            previewContent = `
                <div class="preview-viewer">
                    <video controls class="preview-video">
                        <source src="${fileUrl}" type="${mimeType}">
                        Tu navegador no soporta la reproducción de video.
                    </video>
                </div>
            `;
        } else {
            previewContent = `
                <div class="preview-unsupported">
                    <div class="preview-icon">
                        <i data-feather="file"></i>
                    </div>
                    <h3>Vista previa no disponible</h3>
                    <p>Este tipo de archivo no puede ser visualizado en el navegador.</p>
                    <div class="file-info">
                        <div class="file-info-item">
                            <span class="label">Nombre:</span>
                            <span class="value">${doc.original_name || doc.name}</span>
                        </div>
                        <div class="file-info-item">
                            <span class="label">Tamaño:</span>
                            <span class="value">${formatBytes(doc.file_size || 0)}</span>
                        </div>
                        <div class="file-info-item">
                            <span class="label">Tipo:</span>
                            <span class="value">${mimeType || 'Desconocido'}</span>
                        </div>
                    </div>
                    <button class="btn-primary" onclick="openInNewWindow(${doc.id})">
                        <i data-feather="external-link"></i>
                        Abrir archivo
                    </button>
                </div>
            `;
        }
        
        // Agregar metadatos
        previewContent += `
            <div class="preview-metadata">
                <div class="metadata-title">
                    <i data-feather="info"></i>
                    Información del Documento
                </div>
                <div class="metadata-grid">
                    <div class="metadata-item">
                        <span class="metadata-label">Empresa</span>
                        <span class="metadata-value">${doc.company_name || 'N/A'}</span>
                    </div>
                    <div class="metadata-item">
                        <span class="metadata-label">Departamento</span>
                        <span class="metadata-value">${doc.department_name || 'N/A'}</span>
                    </div>
                    <div class="metadata-item">
                        <span class="metadata-label">Tipo</span>
                        <span class="metadata-value">${doc.document_type || 'General'}</span>
                    </div>
                    <div class="metadata-item">
                        <span class="metadata-label">Fecha</span>
                        <span class="metadata-value">${formatDate(doc.upload_date || doc.created_at)}</span>
                    </div>
                    <div class="metadata-item">
                        <span class="metadata-label">Tamaño</span>
                        <span class="metadata-value">${formatBytes(doc.file_size || 0)}</span>
                    </div>
                    <div class="metadata-item">
                        <span class="metadata-label">Subido por</span>
                        <span class="metadata-value">${doc.uploaded_by || doc.user_name || 'Desconocido'}</span>
                    </div>
                </div>
            </div>
        `;
        
        content.innerHTML = previewContent;
        
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }
    
    // Funciones globales
    window.closePreviewModal = function() {
        const modal = document.getElementById('previewModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };
    
    window.openInNewWindow = function(documentId) {
        window.open(`view.php?id=${documentId}`, '_blank');
    };
    
    window.handleImageError = function(img) {
        img.style.display = 'none';
        const errorDiv = document.createElement('div');
        errorDiv.className = 'preview-error';
        errorDiv.innerHTML = `
            <i data-feather="alert-circle"></i>
            <h3>Error al cargar imagen</h3>
            <p>No se pudo cargar la imagen.</p>
        `;
        img.parentNode.appendChild(errorDiv);
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    };
    
    window.handleFrameError = function(frame, documentId) {
        frame.style.display = 'none';
        const errorDiv = document.createElement('div');
        errorDiv.className = 'preview-error';
        errorDiv.innerHTML = `
            <i data-feather="alert-circle"></i>
            <h3>Error al cargar documento</h3>
            <p>No se pudo cargar el documento en vista previa.</p>
            <button class="btn-primary" onclick="openInNewWindow(${documentId})">
                <i data-feather="external-link"></i>
                Abrir en nueva ventana
            </button>
        `;
        frame.parentNode.appendChild(errorDiv);
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    };
    
    // Funciones auxiliares
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            return new Date(dateString).toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dateString;
        }
    }
    
    // Eventos
    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('previewModal');
        if (modal && (modal.style.display === 'flex' || modal.classList.contains('active'))) {
            if (e.key === 'Escape') {
                closePreviewModal();
            }
        }
    });
    
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('previewModal');
        if (e.target === modal) {
            closePreviewModal();
        }
    });
    
    // Agregar estilos CSS
    if (!document.getElementById('preview-patch-styles')) {
        const style = document.createElement('style');
        style.id = 'preview-patch-styles';
        style.textContent = `
            /* Estilos para el parche de vista previa */
            .loading-spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #8B4513;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .spinning {
                animation: spin 1s linear infinite;
            }
            
            .preview-actions {
                display: flex;
                gap: 0.5rem;
                align-items: center;
                margin-left: auto;
            }
            
            .preview-btn {
                background: none;
                border: none;
                padding: 0.5rem;
                border-radius: 6px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
                color: #64748b;
                min-width: 36px;
                height: 36px;
            }
            
            .preview-btn:hover {
                background: #f1f5f9;
                color: #1e293b;
            }
            
            .preview-btn.btn-download {
                color: #10b981;
            }
            
            .preview-btn.btn-external {
                color: #3b82f6;
            }
            
            .preview-btn.btn-close {
                color: #ef4444;
            }
            
            .preview-iframe-container {
                height: 70vh;
                padding: 1rem;
                background: #f8fafc;
            }
            
            .preview-iframe {
                width: 100%;
                height: 100%;
                border: none;
                border-radius: 8px;
                opacity: 0;
                transition: opacity 0.5s;
                background: white;
            }
            
            .preview-viewer {
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 2rem;
                background: #f8fafc;
                min-height: 400px;
            }
            
            .preview-image {
                max-width: 100%;
                max-height: 70vh;
                border-radius: 8px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.15);
                opacity: 0;
                transition: opacity 0.5s;
            }
            
            .preview-image.loaded {
                opacity: 1;
            }
            
            .preview-pdf {
                width: 100%;
                height: 70vh;
                border: none;
                border-radius: 8px;
                opacity: 0;
                transition: opacity 0.5s;
            }
            
            .preview-pdf.loaded {
                opacity: 1;
            }
            
            .preview-video {
                max-width: 100%;
                max-height: 70vh;
                border-radius: 8px;
            }
            
            .preview-unsupported {
                text-align: center;
                padding: 4rem 2rem;
                color: #64748b;
            }
            
            .preview-icon {
                width: 64px;
                height: 64px;
                margin: 0 auto 1.5rem;
                color: #64748b;
            }
            
            .preview-unsupported h3 {
                margin: 0 0 1rem;
                color: #1e293b;
                font-size: 1.25rem;
            }
            
            .preview-unsupported p {
                margin-bottom: 2rem;
                line-height: 1.6;
            }
            
            .file-info {
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 1.5rem;
                margin: 1.5rem auto;
                max-width: 400px;
                text-align: left;
            }
            
            .file-info-item {
                display: flex;
                justify-content: space-between;
                padding: 0.5rem 0;
                border-bottom: 1px solid #f1f5f9;
            }
            
            .file-info-item:last-child {
                border-bottom: none;
            }
            
            .file-info-item .label {
                font-weight: 500;
                color: #374151;
            }
            
            .file-info-item .value {
                color: #6b7280;
                word-break: break-all;
            }
            
            .preview-metadata {
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
                padding: 1.5rem;
            }
            
            .metadata-title {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-weight: 600;
                color: #374151;
                margin-bottom: 1rem;
                font-size: 0.875rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            
            .metadata-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 1rem;
            }
            
            .metadata-item {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .metadata-label {
                font-size: 0.75rem;
                font-weight: 500;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            
            .metadata-value {
                font-size: 0.875rem;
                color: #374151;
                font-weight: 500;
            }
            
            .btn-primary {
                background: #8B4513;
                color: white;
                border: none;
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                transition: all 0.2s;
                text-decoration: none;
            }
            
            .btn-primary:hover {
                background: #7a3c0f;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(139, 69, 19, 0.3);
            }
            
            .preview-error {
                text-align: center;
                padding: 2rem;
                color: #64748b;
            }
            
            .preview-error i {
                width: 48px;
                height: 48px;
                color: #ef4444;
                margin-bottom: 1rem;
            }
            
            .preview-error h3 {
                margin: 0 0 1rem;
                color: #374151;
            }
            
            .preview-error p {
                margin-bottom: 1.5rem;
            }
            
            @media (max-width: 768px) {
                .metadata-grid {
                    grid-template-columns: 1fr;
                }
                
                .preview-actions {
                    gap: 0.25rem;
                }
                
                .preview-btn {
                    padding: 0.375rem;
                    min-width: 32px;
                    height: 32px;
                }
                
                .preview-iframe-container {
                    height: 60vh;
                    padding: 0.5rem;
                }
                
                .preview-image,
                .preview-pdf {
                    max-height: 60vh;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Inicializar el parche
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPatch);
    } else {
        initPatch();
    }
    
    console.log('✅ Parche de vista previa cargado exitosamente');
})();