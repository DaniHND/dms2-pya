// assets/js/document-types.js
// JavaScript para el módulo de tipos de documentos - DMS2

// Variables globales
let currentModal = null;
let currentDocumentTypeId = null;

// ==========================================
// INICIALIZACIÓN
// ==========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 Módulo de tipos de documentos cargado');
    initializeDocumentTypesModule();
});

function initializeDocumentTypesModule() {
    setupEventListeners();
}

function setupEventListeners() {
    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && currentModal) {
            closeCurrentModal();
        }
    });

    // Cerrar modal al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeCurrentModal();
        }
    });
}

// ==========================================
// FUNCIONES DE MODAL
// ==========================================

function openCreateDocumentTypeModal() {
    console.log('🆕 Abriendo modal crear tipo de documento');
    
    currentDocumentTypeId = null;
    
    const modalHTML = `
        <div class="modal active" id="createDocumentTypeModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Nuevo Tipo de Documento</h3>
                    <button type="button" class="modal-close" onclick="closeCreateDocumentTypeModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="createDocumentTypeForm" class="modal-form" onsubmit="handleCreateDocumentType(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Nombre del Tipo *</label>
                                <input type="text" id="name" name="name" class="form-input" required 
                                       placeholder="Ej: Contrato, Factura, Reporte">
                            </div>
                            <div class="form-group">
                                <label for="status">Estado</label>
                                <select id="status" name="status" class="form-input">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label for="description">Descripción</label>
                                <textarea id="description" name="description" class="form-input" rows="3"
                                          placeholder="Describe el tipo de documento y su uso..."></textarea>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeCreateDocumentTypeModal()">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="plus"></i>
                                Crear Tipo de Documento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    // Insertar modal en el DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    currentModal = document.getElementById('createDocumentTypeModal');
    
    // Inicializar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    console.log('✅ Modal de crear tipo de documento abierto');
}

function closeCreateDocumentTypeModal() {
    console.log('❌ Cerrando modal de crear tipo de documento...');
    const modal = document.getElementById('createDocumentTypeModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            currentModal = null;
        }, 300);
    }
}

function editDocumentType(documentTypeId) {
    console.log('✏️ Editando tipo de documento:', documentTypeId);
    
    currentDocumentTypeId = documentTypeId;
    
    const modalHTML = `
        <div class="modal active" id="editDocumentTypeModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Editar Tipo de Documento</h3>
                    <button type="button" class="modal-close" onclick="closeEditDocumentTypeModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="loading-state">
                        <i data-feather="loader"></i>
                        <p>Cargando datos del tipo de documento...</p>
                    </div>
                    <form id="editDocumentTypeForm" class="modal-form" onsubmit="handleUpdateDocumentType(event)" style="display: none;">
                        <input type="hidden" id="edit_document_type_id" name="document_type_id" value="${documentTypeId}">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_name">Nombre del Tipo *</label>
                                <input type="text" id="edit_name" name="name" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_status">Estado</label>
                                <select id="edit_status" name="status" class="form-input">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label for="edit_description">Descripción</label>
                                <textarea id="edit_description" name="description" class="form-input" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeEditDocumentTypeModal()">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="save"></i>
                                Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    // Insertar modal en el DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    currentModal = document.getElementById('editDocumentTypeModal');
    
    // Cargar datos del tipo de documento
    loadDocumentTypeData(documentTypeId);
    
    // Inicializar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function closeEditDocumentTypeModal() {
    console.log('❌ Cerrando modal de editar tipo de documento...');
    const modal = document.getElementById('editDocumentTypeModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            currentModal = null;
            currentDocumentTypeId = null;
        }, 300);
    }
}

function viewDocumentTypeDetails(documentTypeId) {
    console.log('👁️ Viendo detalles del tipo de documento:', documentTypeId);
    
    const modalHTML = `
        <div class="modal active" id="viewDocumentTypeModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Detalles del Tipo de Documento</h3>
                    <button type="button" class="modal-close" onclick="closeViewDocumentTypeModal()">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="loading-state">
                        <i data-feather="loader"></i>
                        <p>Cargando detalles del tipo de documento...</p>
                    </div>
                    <div id="documentTypeDetailsContent" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeViewDocumentTypeModal()">
                        Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="editDocumentTypeFromView(${documentTypeId})">
                        <i data-feather="edit"></i>
                        Editar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Insertar modal en el DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    currentModal = document.getElementById('viewDocumentTypeModal');
    
    // Cargar detalles del tipo de documento
    loadDocumentTypeDetails(documentTypeId);
    
    // Inicializar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function closeViewDocumentTypeModal() {
    console.log('❌ Cerrando modal de ver tipo de documento...');
    const modal = document.getElementById('viewDocumentTypeModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            currentModal = null;
        }, 300);
    }
}

function editDocumentTypeFromView(documentTypeId) {
    closeViewDocumentTypeModal();
    setTimeout(() => {
        editDocumentType(documentTypeId);
    }, 300);
}

function closeCurrentModal() {
    if (currentModal) {
        currentModal.classList.remove('active');
        setTimeout(() => {
            if (currentModal && currentModal.parentNode) {
                currentModal.remove();
                currentModal = null;
                currentDocumentTypeId = null;
            }
        }, 300);
    }
}

// ==========================================
// FUNCIONES DE DATOS
// ==========================================

function loadDocumentTypeData(documentTypeId) {
    console.log('📝 Cargando datos del tipo de documento:', documentTypeId);
    
    fetch(`actions/get_document_type_details.php?id=${documentTypeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const form = document.getElementById('editDocumentTypeForm');
                const loadingState = document.querySelector('#editDocumentTypeModal .loading-state');
                
                // Llenar formulario
                document.getElementById('edit_name').value = data.document_type.name || '';
                document.getElementById('edit_description').value = data.document_type.description || '';
                document.getElementById('edit_status').value = data.document_type.status || 'active';
                
                // Mostrar formulario y ocultar loading
                loadingState.style.display = 'none';
                form.style.display = 'block';
                
                // Reinicializar iconos
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
                
            } else {
                showNotification(data.message || 'Error al cargar datos del tipo de documento', 'error');
                closeEditDocumentTypeModal();
            }
        })
        .catch(error => {
            console.error('Error cargando datos del tipo de documento:', error);
            showNotification('Error de conexión', 'error');
            closeEditDocumentTypeModal();
        });
}

function loadDocumentTypeDetails(documentTypeId) {
    console.log('📋 Cargando detalles del tipo de documento:', documentTypeId);
    
    fetch(`actions/get_document_type_details.php?id=${documentTypeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const loadingState = document.querySelector('#viewDocumentTypeModal .loading-state');
                const contentDiv = document.getElementById('documentTypeDetailsContent');
                
                // Crear HTML con los detalles
                const detailsHTML = `
                    <div class="document-type-details">
                        <div class="detail-section">
                            <h4>Información General</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Nombre:</label>
                                    <span>${data.document_type.name || 'N/A'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Estado:</label>
                                    <span class="status-badge ${data.document_type.status === 'active' ? 'status-active' : 'status-inactive'}">
                                        ${data.document_type.status === 'active' ? 'Activo' : 'Inactivo'}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        ${data.document_type.description ? `
                        <div class="detail-section">
                            <h4>Descripción</h4>
                            <p>${data.document_type.description}</p>
                        </div>
                        ` : ''}
                        
                        <div class="detail-section">
                            <h4>Estadísticas</h4>
                            <div class="stats-mini-grid">
                                <div class="stat-mini">
                                    <div class="stat-number">${data.statistics.total_documents}</div>
                                    <div class="stat-label">Total Documentos</div>
                                </div>
                                <div class="stat-mini">
                                    <div class="stat-number">${data.statistics.active_documents}</div>
                                    <div class="stat-label">Documentos Activos</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Fechas</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Creado:</label>
                                    <span>${data.document_type.formatted_created_date || 'N/A'}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Actualizado:</label>
                                    <span>${data.document_type.formatted_updated_date || 'N/A'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                contentDiv.innerHTML = detailsHTML;
                
                // Mostrar contenido y ocultar loading
                loadingState.style.display = 'none';
                contentDiv.style.display = 'block';
                
            } else {
                showNotification(data.message || 'Error al cargar detalles del tipo de documento', 'error');
                closeViewDocumentTypeModal();
            }
        })
        .catch(error => {
            console.error('Error cargando detalles del tipo de documento:', error);
            showNotification('Error de conexión', 'error');
            closeViewDocumentTypeModal();
        });
}

// ==========================================
// FUNCIONES DE ACCIONES
// ==========================================

function handleCreateDocumentType(event) {
    event.preventDefault();
    console.log('💾 Creando nuevo tipo de documento...');
    
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Deshabilitar botón
    submitBtn.disabled = true;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Creando...';
    
    fetch('actions/create_document_type.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Tipo de documento creado correctamente', 'success');
            closeCreateDocumentTypeModal();
            // Recargar página para mostrar el nuevo tipo
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Error al crear tipo de documento', 'error');
        }
    })
    .catch(error => {
        console.error('Error creando tipo de documento:', error);
        showNotification('Error de conexión', 'error');
    })
    .finally(() => {
        // Rehabilitar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
}

function handleUpdateDocumentType(event) {
    event.preventDefault();
    console.log('💾 Actualizando tipo de documento...');
    
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Deshabilitar botón
    submitBtn.disabled = true;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i data-feather="loader"></i> Guardando...';
    
    fetch('actions/update_document_type.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Tipo de documento actualizado correctamente', 'success');
            closeEditDocumentTypeModal();
            // Recargar página para mostrar los cambios
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Error al actualizar tipo de documento', 'error');
        }
    })
    .catch(error => {
        console.error('Error actualizando tipo de documento:', error);
        showNotification('Error de conexión', 'error');
    })
    .finally(() => {
        // Rehabilitar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
}

function toggleDocumentTypeStatus(documentTypeId, currentStatus) {
    const action = currentStatus === 'active' ? 'desactivar' : 'activar';
    const confirmMessage = `¿Está seguro que desea ${action} este tipo de documento?`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    console.log(`🔄 Cambiando estado del tipo de documento ${documentTypeId} de ${currentStatus}`);
    
    const formData = new FormData();
    formData.append('document_type_id', documentTypeId);
    formData.append('current_status', currentStatus);
    
    fetch('actions/toggle_document_type_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`Tipo de documento ${action}do correctamente`, 'success');
            // Recargar página para mostrar los cambios
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message || `Error al ${action} tipo de documento`, 'error');
        }
    })
    .catch(error => {
        console.error('Error cambiando estado del tipo de documento:', error);
        showNotification('Error de conexión', 'error');
    });
}

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

function showNotification(message, type = 'info') {
    console.log(`📢 Notificación [${type}]: ${message}`);
    
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i data-feather="${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Agregar al DOM
    document.body.appendChild(notification);
    
    // Inicializar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Mostrar notificación
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Ocultar después de 5 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 5000);
}

console.log('✅ Módulo de tipos de documentos JavaScript cargado correctamente');