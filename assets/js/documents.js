// assets/js/documents.js
// JavaScript para el módulo de documentos - DMS2

class DocumentUploader {
    constructor() {
        this.init();
        this.maxFileSize = 20 * 1024 * 1024; // 20MB por defecto
        this.allowedExtensions = ['pdf', 'doc', 'docx', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'];
        this.currentFile = null;
    }

    init() {
        this.setupEventListeners();
        this.setupDragAndDrop();
        this.setupFormValidation();
        this.setupCompanyDepartmentFilter();
    }

    setupEventListeners() {
        const fileInput = document.getElementById('document_file');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const uploadForm = document.querySelector('.upload-form');

        if (fileInput && fileUploadArea) {
            // Click en el área de upload - evitar doble click
            fileUploadArea.addEventListener('click', (e) => {
                // Solo abrir selector si no se está haciendo clic en elementos específicos
                if (!e.target.closest('.remove-file') && 
                    !e.target.closest('.file-preview') && 
                    !e.target.closest('button')) {
                    e.preventDefault();
                    e.stopPropagation();
                    fileInput.click();
                }
            });

            // Cambio en el input de archivo
            fileInput.addEventListener('change', (e) => {
                e.stopPropagation();
                if (e.target.files.length > 0) {
                    this.handleFileSelect(e.target.files[0]);
                }
            });

            // Prevenir el comportamiento por defecto del input file
            fileInput.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }

        // Submit del formulario
        if (uploadForm) {
            uploadForm.addEventListener('submit', (e) => {
                this.handleFormSubmit(e);
            });
        }

        // Auto-completar nombre del documento
        const docNameInput = document.getElementById('document_name');
        if (docNameInput) {
            docNameInput.addEventListener('blur', () => {
                this.validateDocumentName();
            });
        }
    }

    setupDragAndDrop() {
        const fileUploadArea = document.getElementById('fileUploadArea');
        if (!fileUploadArea) return;

        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            fileUploadArea.classList.add('drag-over');
        });

        fileUploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            // Solo remover la clase si realmente salimos del área
            if (!fileUploadArea.contains(e.relatedTarget)) {
                fileUploadArea.classList.remove('drag-over');
            }
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            fileUploadArea.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.handleFileSelect(files[0]);
                const fileInput = document.getElementById('document_file');
                if (fileInput) {
                    // Crear un nuevo FileList para asignar al input
                    const dt = new DataTransfer();
                    dt.items.add(files[0]);
                    fileInput.files = dt.files;
                }
            }
        });
    }

    setupFormValidation() {
        const form = document.querySelector('.upload-form');
        if (!form) return;

        const inputs = form.querySelectorAll('input[required], select[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });

            input.addEventListener('input', () => {
                if (input.classList.contains('is-invalid')) {
                    this.validateField(input);
                }
            });
        });
    }

    setupCompanyDepartmentFilter() {
        const companySelect = document.getElementById('company_id');
        const departmentSelect = document.getElementById('department_id');

        if (companySelect && departmentSelect) {
            companySelect.addEventListener('change', () => {
                this.filterDepartments(companySelect.value);
            });

            // Filtrar al cargar la página si hay una empresa seleccionada
            if (companySelect.value) {
                this.filterDepartments(companySelect.value);
            }
        }
    }

    handleFileSelect(file) {
        // Validar archivo
        const validation = this.validateFile(file);
        if (!validation.valid) {
            this.showError(validation.message);
            this.removeFile();
            return;
        }

        this.currentFile = file;
        this.showFilePreview(file);
        this.autoFillDocumentName(file);
        this.clearErrors();
    }

    validateFile(file) {
        // Validar tamaño
        if (file.size > this.maxFileSize) {
            return {
                valid: false,
                message: `El archivo es muy grande. Tamaño máximo: ${this.formatBytes(this.maxFileSize)}`
            };
        }

        // Validar extensión
        const extension = file.name.split('.').pop().toLowerCase();
        if (!this.allowedExtensions.includes(extension)) {
            return {
                valid: false,
                message: `Tipo de archivo no permitido. Extensiones permitidas: ${this.allowedExtensions.join(', ')}`
            };
        }

        return { valid: true };
    }

    showFilePreview(file) {
        const filePreview = document.getElementById('filePreview');
        const fileUploadContent = document.querySelector('.file-upload-content');
        
        if (filePreview && fileUploadContent) {
            const fileName = file.name;
            const fileSize = this.formatBytes(file.size);
            
            filePreview.querySelector('.file-name').textContent = fileName;
            filePreview.querySelector('.file-size').textContent = fileSize;
            
            fileUploadContent.style.display = 'none';
            filePreview.style.display = 'flex';

            // Actualizar icono según tipo de archivo
            this.updateFileIcon(fileName);
        }
    }

    updateFileIcon(fileName) {
        const extension = fileName.split('.').pop().toLowerCase();
        const iconElement = document.querySelector('.file-info i');
        
        if (iconElement) {
            // Remover clases anteriores
            iconElement.className = '';
            
            // Agregar icono según tipo
            switch (extension) {
                case 'pdf':
                    iconElement.setAttribute('data-feather', 'file-text');
                    break;
                case 'doc':
                case 'docx':
                    iconElement.setAttribute('data-feather', 'file-text');
                    break;
                case 'xlsx':
                case 'xls':
                    iconElement.setAttribute('data-feather', 'file-spreadsheet');
                    break;
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                    iconElement.setAttribute('data-feather', 'image');
                    break;
                default:
                    iconElement.setAttribute('data-feather', 'file');
            }
            
            // Reemplazar iconos de Feather
            feather.replace();
        }
    }

    autoFillDocumentName(file) {
        const docNameInput = document.getElementById('document_name');
        if (docNameInput && !docNameInput.value.trim()) {
            const nameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.'));
            docNameInput.value = nameWithoutExt;
            this.validateField(docNameInput);
        }
    }

    removeFile() {
        const fileInput = document.getElementById('document_file');
        const filePreview = document.getElementById('filePreview');
        const fileUploadContent = document.querySelector('.file-upload-content');
        
        if (fileInput) fileInput.value = '';
        if (filePreview) filePreview.style.display = 'none';
        if (fileUploadContent) fileUploadContent.style.display = 'block';
        
        this.currentFile = null;
        this.clearErrors();
    }

    filterDepartments(companyId) {
        const departmentSelect = document.getElementById('department_id');
        if (!departmentSelect) return;

        const options = departmentSelect.querySelectorAll('option');
        
        options.forEach(option => {
            if (option.value === '') {
                option.style.display = 'block';
            } else {
                const optionCompany = option.getAttribute('data-company');
                option.style.display = (optionCompany === companyId) ? 'block' : 'none';
            }
        });
        
        // Resetear selección si la opción actual no es válida para la nueva empresa
        const currentOption = departmentSelect.options[departmentSelect.selectedIndex];
        if (currentOption && currentOption.getAttribute('data-company') !== companyId) {
            departmentSelect.value = '';
        }
    }

    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';

        // Validaciones específicas por campo
        switch (field.id) {
            case 'document_name':
                if (!value) {
                    isValid = false;
                    message = 'El nombre del documento es requerido';
                } else if (value.length < 3) {
                    isValid = false;
                    message = 'El nombre debe tener al menos 3 caracteres';
                }
                break;
                
            case 'document_type_id':
                if (!value) {
                    isValid = false;
                    message = 'Debe seleccionar un tipo de documento';
                }
                break;
                
            case 'company_id':
                if (!value) {
                    isValid = false;
                    message = 'Debe seleccionar una empresa';
                }
                break;
                
            case 'document_file':
                if (!this.currentFile) {
                    isValid = false;
                    message = 'Debe seleccionar un archivo';
                }
                break;
        }

        // Aplicar clases de validación
        field.classList.remove('is-valid', 'is-invalid');
        field.classList.add(isValid ? 'is-valid' : 'is-invalid');

        // Mostrar/ocultar mensaje de error
        this.toggleFieldMessage(field, message, isValid);

        return isValid;
    }

    validateDocumentName() {
        const docNameInput = document.getElementById('document_name');
        if (docNameInput) {
            return this.validateField(docNameInput);
        }
        return true;
    }

    toggleFieldMessage(field, message, isValid) {
        let messageElement = field.parentNode.querySelector('.invalid-feedback, .valid-feedback');
        
        // Remover mensaje anterior
        if (messageElement) {
            messageElement.remove();
        }

        // Agregar nuevo mensaje si es necesario
        if (!isValid && message) {
            messageElement = document.createElement('div');
            messageElement.className = 'invalid-feedback';
            messageElement.textContent = message;
            field.parentNode.appendChild(messageElement);
        }
    }

    handleFormSubmit(e) {
        // Validar todos los campos requeridos
        const requiredFields = document.querySelectorAll('input[required], select[required]');
        let isFormValid = true;

        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isFormValid = false;
            }
        });

        // Validar archivo especialmente
        if (!this.currentFile) {
            this.showError('Debe seleccionar un archivo');
            isFormValid = false;
        }

        if (!isFormValid) {
            e.preventDefault();
            this.showError('Por favor corrija los errores en el formulario');
            return;
        }

        // Mostrar loading en el botón
        this.showLoading(true);
    }

    showLoading(show) {
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            if (show) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            } else {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        }
    }

    showError(message) {
        // Crear o actualizar alerta de error
        let alertElement = document.querySelector('.alert-error');
        
        if (!alertElement) {
            alertElement = document.createElement('div');
            alertElement.className = 'alert alert-error';
            alertElement.innerHTML = '<i data-feather="alert-circle"></i><span></span>';
            
            const form = document.querySelector('.upload-form');
            if (form) {
                form.insertBefore(alertElement, form.firstChild);
                feather.replace();
            }
        }
        
        alertElement.querySelector('span').textContent = message;
        alertElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    clearErrors() {
        const alertElement = document.querySelector('.alert-error');
        if (alertElement) {
            alertElement.remove();
        }
    }

    formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
}

// Funciones globales para compatibilidad
function removeFile() {
    if (window.documentUploader) {
        window.documentUploader.removeFile();
    }
}

function showNotifications() {
    alert('Sistema de notificaciones - Próximamente');
}

function showUserMenu() {
    alert('Menú de usuario - Próximamente');
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }
}

function updateTime() {
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        const now = new Date();
        const timeString = now.toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });
        const dateString = now.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
        timeElement.textContent = `${dateString} ${timeString}`;
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar uploader de documentos
    window.documentUploader = new DocumentUploader();
    
    // Inicializar reloj
    updateTime();
    setInterval(updateTime, 1000);
    
    // Responsive behavior
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
    
    // Inicializar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});