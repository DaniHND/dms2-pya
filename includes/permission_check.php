<?php
/**
 * includes/permission_check.php
 * Middleware para validar permisos de acceso a módulos
 * Sistema mejorado con prioridad de grupos - VERSIÓN CORREGIDA
 */

require_once 'group_permissions.php';

class PermissionChecker {
    
    /**
     * Verifica si el usuario puede acceder al módulo de upload
     */
    public static function checkUploadAccess($userId) {
        if (!canUserUploadFiles($userId)) {
            self::denyAccess('No tiene permisos para subir archivos');
        }
    }
    
    /**
     * Verifica si el usuario puede acceder al inbox (ver archivos)
     */
    public static function checkInboxAccess($userId) {
        if (!canUserViewFiles($userId)) {
            self::denyAccess('No tiene permisos para ver archivos');
        }
    }
    
    /**
     * Verifica si el usuario puede crear carpetas
     */
    public static function checkFolderCreationAccess($userId) {
        if (!canUserCreateFolders($userId)) {
            self::denyAccess('No tiene permisos para crear carpetas');
        }
    }
    
    /**
     * Verifica si el usuario puede descargar archivos
     */
    public static function checkDownloadAccess($userId) {
        if (!canUserDownloadFiles($userId)) {
            self::denyAccess('No tiene permisos para descargar archivos');
        }
    }
    
    /**
     * Verifica si el usuario puede eliminar archivos
     */
    public static function checkDeleteAccess($userId) {
        if (!canUserDeleteFiles($userId)) {
            self::denyAccess('No tiene permisos para eliminar archivos');
        }
    }
    
    /**
     * Verifica si el usuario puede acceder a una empresa específica
     */
    public static function checkCompanyAccess($userId, $companyId) {
        if (!canUserAccessCompany($userId, $companyId)) {
            self::denyAccess('No tiene acceso a esta empresa');
        }
    }
    
    /**
     * Verifica si el usuario puede acceder a un departamento específico
     */
    public static function checkDepartmentAccess($userId, $departmentId) {
        if (!canUserAccessDepartment($userId, $departmentId)) {
            self::denyAccess('No tiene acceso a este departamento');
        }
    }
    
    /**
     * Verifica si el usuario puede acceder a un tipo de documento específico
     */
    public static function checkDocumentTypeAccess($userId, $documentTypeId) {
        if (!canUserAccessDocumentType($userId, $documentTypeId)) {
            self::denyAccess('No tiene acceso a este tipo de documento');
        }
    }
    
    /**
     * Verifica acceso completo a un documento
     */
    public static function checkDocumentAccess($userId, $documentId) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            // Obtener información del documento
            $query = "
                SELECT d.id, d.company_id, d.department_id, d.document_type_id, d.user_id
                FROM documents d
                WHERE d.id = ? AND d.status = 'active'
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$documentId]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$document) {
                self::denyAccess('Documento no encontrado');
            }
            
            // Verificar acceso a empresa, departamento y tipo de documento
            self::checkCompanyAccess($userId, $document['company_id']);
            self::checkDepartmentAccess($userId, $document['department_id']);
            self::checkDocumentTypeAccess($userId, $document['document_type_id']);
            
        } catch (Exception $e) {
            error_log("Error checking document access: " . $e->getMessage());
            self::denyAccess('Error verificando acceso al documento');
        }
    }
    
    /**
     * Verifica que el usuario tenga al menos un grupo activo
     */
    public static function requireActiveGroups($userId) {
        $userPerms = getUserGroupPermissions($userId);
        
        if (!$userPerms['has_groups']) {
            self::denyAccess('Usuario sin grupos de acceso asignados. Contacte al administrador.');
        }
    }
    
    /**
     * Deniega el acceso y redirige o muestra error
     */
    private static function denyAccess($message) {
        // Si es una petición AJAX, devolver JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => $message,
                'error_type' => 'access_denied'
            ]);
            exit;
        }
        
        // Para peticiones normales, redirigir al dashboard con mensaje
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['error_message'] = $message;
        
        // Obtener URL base simplificada
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = $protocol . $host . '/dms2-pya'; // Ajustar según tu instalación
        
        header('Location: ' . $baseUrl . '/dashboard.php?error=access_denied');
        exit;
    }
}

/**
 * Funciones helper para uso directo en archivos
 */

function requireUploadPermission() {
    $currentUser = SessionManager::getCurrentUser();
    PermissionChecker::checkUploadAccess($currentUser['id']);
}

function requireInboxPermission() {
    $currentUser = SessionManager::getCurrentUser();
    PermissionChecker::checkInboxAccess($currentUser['id']);
}

function requireFolderPermission() {
    $currentUser = SessionManager::getCurrentUser();
    PermissionChecker::checkFolderCreationAccess($currentUser['id']);
}

function requireDownloadPermission() {
    $currentUser = SessionManager::getCurrentUser();
    PermissionChecker::checkDownloadAccess($currentUser['id']);
}

function requireDeletePermission() {
    $currentUser = SessionManager::getCurrentUser();
    PermissionChecker::checkDeleteAccess($currentUser['id']);
}

function requireCompanyAccess($companyId) {
    $currentUser = SessionManager::getCurrentUser();
    PermissionChecker::checkCompanyAccess($currentUser['id'], $companyId);
}

function requireDepartmentAccess($departmentId) {
    $currentUser = SessionManager::getCurrentUser();
    PermissionChecker::checkDepartmentAccess($currentUser['id'], $departmentId);
}

function requireDocumentTypeAccess($documentTypeId) {
    $currentUser = SessionManager::getCurrentUser();
    PermissionChecker::checkDocumentTypeAccess($currentUser['id'], $documentTypeId);
}

function requireDocumentAccess($documentId) {
    $currentUser = SessionManager::getCurrentUser();
    PermissionChecker::checkDocumentAccess($currentUser['id'], $documentId);
}

function requireActiveGroups() {
    $currentUser = SessionManager::getCurrentUser();
    PermissionChecker::requireActiveGroups($currentUser['id']);
}
?>