<?php
/*
 * middleware/DocumentAccessControl.php
 * Middleware para controlar acceso a documentos basado en permisos de grupos
 */

class DocumentAccessControl {
    private $permissionManager;
    private $pdo;
    
    public function __construct() {
        require_once __DIR__ . '/../includes/PermissionManager.php';
        $this->permissionManager = getPermissionManager();
        
        $database = new Database();
        $this->pdo = $database->getConnection();
    }
    
    /**
     * Middleware para verificar acceso antes de mostrar documentos
     */
    public function checkDocumentAccess($documentId, $action = 'view') {
        // Verificar si el usuario está logueado
        if (!SessionManager::isLoggedIn()) {
            $this->denyAccess('Usuario no autenticado');
        }
        
        $currentUser = SessionManager::getCurrentUser();
        
        // Los admins siempre tienen acceso
        if ($currentUser['role'] === 'admin') {
            return true;
        }
        
        // Verificar permisos según la acción
        switch ($action) {
            case 'view':
                if (!$this->permissionManager->hasPermission('view')) {
                    $this->denyAccess('Sin permisos para ver documentos');
                }
                break;
                
            case 'download':
                if (!$this->permissionManager->hasPermission('download')) {
                    $this->denyAccess('Sin permisos para descargar documentos');
                }
                
                // Verificar límites de descarga
                if (!$this->permissionManager->checkDownloadLimit()) {
                    $this->denyAccess('Límite diario de descargas excedido');
                }
                break;
                
            case 'edit':
                if (!$this->permissionManager->hasPermission('edit')) {
                    $this->denyAccess('Sin permisos para editar documentos');
                }
                break;
                
            case 'delete':
                if (!$this->permissionManager->hasPermission('delete')) {
                    $this->denyAccess('Sin permisos para eliminar documentos');
                }
                break;
                
            case 'create':
                if (!$this->permissionManager->hasPermission('create')) {
                    $this->denyAccess('Sin permisos para crear documentos');
                }
                return true; // No necesita verificar documento específico
        }
        
        // Verificar acceso al documento específico
        if (!$this->permissionManager->canAccessDocument($documentId)) {
            $this->denyAccess('Sin acceso a este documento específico');
        }
        
        return true;
    }
    
    /**
     * Filtrar lista de documentos según permisos del usuario
     */
    public function filterDocumentsList($baseQuery = null, $params = []) {
        if (!$baseQuery) {
            $baseQuery = "
                SELECT 
                    d.*,
                    dt.name as document_type_name,
                    c.name as company_name,
                    dep.name as department_name,
                    CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                FROM documents d
                LEFT JOIN document_types dt ON d.document_type_id = dt.id
                LEFT JOIN companies c ON d.company_id = c.id
                LEFT JOIN departments dep ON d.department_id = dep.id
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.status = 'active'
            ";
        }
        
        // Obtener consulta filtrada con restricciones de usuario
        $queryData = $this->permissionManager->getDocumentQuery($baseQuery);
        
        $stmt = $this->pdo->prepare($queryData['query']);
        $allParams = array_merge($params, $queryData['params']);
        $stmt->execute($allParams);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener estadísticas de documentos accesibles para el usuario
     */
    public function getAccessibleDocumentStats() {
        $queryData = $this->permissionManager->getDocumentQuery("
            SELECT 
                COUNT(*) as total_documents,
                COUNT(DISTINCT company_id) as companies_count,
                COUNT(DISTINCT department_id) as departments_count,
                COUNT(DISTINCT document_type_id) as document_types_count,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_uploads
            FROM documents 
            WHERE status = 'active'
        ");
        
        $stmt = $this->pdo->prepare($queryData['query']);
        $stmt->execute($queryData['params']);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Registrar actividad del usuario
     */
    public function logDocumentActivity($documentId, $action, $details = null) {
        if (!SessionManager::isLoggedIn()) {
            return;
        }
        
        $currentUser = SessionManager::getCurrentUser();
        
        try {
            $query = "
                INSERT INTO user_activity_logs (
                    user_id, action, entity_type, entity_id, details, 
                    ip_address, user_agent, created_at
                ) VALUES (?, ?, 'document', ?, ?, ?, ?, NOW())
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                $currentUser['id'],
                $action,
                $documentId,
                $details ? json_encode($details) : null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            error_log('Error logging activity: ' . $e->getMessage());
        }
    }
    
    /**
     * Verificar si el usuario puede realizar una acción masiva
     */
    public function checkBulkAction($action, $documentIds) {
        if (empty($documentIds)) {
            return false;
        }
        
        // Verificar permisos básicos
        if (!$this->permissionManager->hasPermission($action)) {
            return false;
        }
        
        // Verificar acceso a cada documento individual
        foreach ($documentIds as $documentId) {
            if (!$this->permissionManager->canAccessDocument($documentId)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Obtener resumen de permisos para mostrar en UI
     */
    public function getPermissionsSummary() {
        return $this->permissionManager->getPermissionsSummary();
    }
    
    /**
     * Denegar acceso y mostrar error apropiado
     */
    private function denyAccess($reason) {
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            // Respuesta JSON para AJAX
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'access_denied',
                'message' => $reason
            ]);
        } else {
            // Respuesta HTML
            http_response_code(403);
            echo "
            <!DOCTYPE html>
            <html>
            <head>
                <title>Acceso Denegado</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
                    .error-container { max-width: 500px; margin: 0 auto; padding: 20px; }
                    .error-icon { font-size: 64px; color: #dc3545; margin-bottom: 20px; }
                    .error-title { color: #dc3545; margin-bottom: 10px; }
                    .error-message { color: #6c757d; margin-bottom: 30px; }
                    .back-button { 
                        background: #007bff; color: white; padding: 10px 20px; 
                        text-decoration: none; border-radius: 5px; display: inline-block;
                    }
                </style>
            </head>
            <body>
                <div class='error-container'>
                    <div class='error-icon'>🚫</div>
                    <h1 class='error-title'>Acceso Denegado</h1>
                    <p class='error-message'>$reason</p>
                    <a href='javascript:history.back()' class='back-button'>Regresar</a>
                </div>
            </body>
            </html>
            ";
        }
        exit;
    }
}

// Función helper para usar en cualquier parte
function checkDocumentAccess($documentId, $action = 'view') {
    $middleware = new DocumentAccessControl();
    return $middleware->checkDocumentAccess($documentId, $action);
}

// Función para obtener lista filtrada de documentos
function getAccessibleDocuments($baseQuery = null, $params = []) {
    $middleware = new DocumentAccessControl();
    return $middleware->filterDocumentsList($baseQuery, $params);
}
?>