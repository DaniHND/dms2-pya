<?php
/**
 * get_navigation.php - API para obtener navegación con permisos corregidos
 * Versión reparada que respeta las restricciones de usuarios
 */

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/permission_functions.php';

try {
    SessionManager::requireLogin();
    $currentUser = SessionManager::getCurrentUser();
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    $result = [
        'success' => true,
        'companies' => [],
        'user_info' => [
            'is_admin' => SessionManager::isAdmin(),
            'role' => $currentUser['role'],
            'company_id' => $currentUser['company_id'],
            'full_name' => $currentUser['full_name']
        ]
    ];
    
    // ========================================
    // OBTENER EMPRESAS SEGÚN PERMISOS
    // ========================================
    
    if (SessionManager::isAdmin()) {
        // ADMIN: Ve todas las empresas
        $companiesQuery = "
            SELECT id, name, description, address, phone, email 
            FROM companies 
            WHERE status = 'active' 
            ORDER BY name
        ";
        $companiesStmt = $pdo->prepare($companiesQuery);
        $companiesStmt->execute();
        $companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // USUARIO NORMAL: Solo empresas permitidas
        $companies = getAccessibleCompanies($currentUser['id']);
    }
    
    // ========================================
    // PROCESAR CADA EMPRESA CON SUS DEPARTAMENTOS
    // ========================================
    
    foreach ($companies as $company) {
        $companyData = [
            'id' => (int)$company['id'],
            'name' => $company['name'],
            'description' => $company['description'] ?? '',
            'departments' => []
        ];
        
        // Obtener departamentos de la empresa
        if (SessionManager::isAdmin()) {
            // ADMIN: Ve todos los departamentos de la empresa
            $deptQuery = "
                SELECT id, name, description, manager_id, parent_id 
                FROM departments 
                WHERE company_id = ? AND status = 'active' 
                ORDER BY name
            ";
            $deptStmt = $pdo->prepare($deptQuery);
            $deptStmt->execute([$company['id']]);
            $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
            
        } else {
            // USUARIO NORMAL: Solo departamentos permitidos
            $departments = getAccessibleDepartments($company['id'], $currentUser['id']);
        }
        
        // ========================================
        // PROCESAR DEPARTAMENTOS CON CARPETAS
        // ========================================
        
        foreach ($departments as $department) {
            $deptData = [
                'id' => (int)$department['id'],
                'name' => $department['name'],
                'description' => $department['description'] ?? '',
                'folders' => [],
                'document_count' => 0
            ];
            
            // Verificar acceso al departamento
            if (!SessionManager::isAdmin() && !canAccessDepartment($department['id'], $currentUser['id'])) {
                continue; // Saltar departamentos sin acceso
            }
            
            // Obtener carpetas del departamento
            $foldersQuery = "
                SELECT id, name, description, folder_color, folder_icon,
                       (SELECT COUNT(*) FROM documents d 
                        WHERE d.folder_id = df.id AND d.status = 'active') as document_count
                FROM document_folders df 
                WHERE company_id = ? AND department_id = ? AND is_active = 1 
                ORDER BY name
            ";
            $foldersStmt = $pdo->prepare($foldersQuery);
            $foldersStmt->execute([$company['id'], $department['id']]);
            $folders = $foldersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($folders as $folder) {
                $deptData['folders'][] = [
                    'id' => (int)$folder['id'],
                    'name' => $folder['name'],
                    'description' => $folder['description'] ?? '',
                    'color' => $folder['folder_color'] ?? '#3498db',
                    'icon' => $folder['folder_icon'] ?? 'folder',
                    'document_count' => (int)$folder['document_count']
                ];
                
                $deptData['document_count'] += (int)$folder['document_count'];
            }
            
            // Contar documentos sin carpeta en el departamento
            $looseDocs = "
                SELECT COUNT(*) as count 
                FROM documents 
                WHERE company_id = ? AND department_id = ? 
                AND folder_id IS NULL AND status = 'active'
            ";
            $looseStmt = $pdo->prepare($looseDocs);
            $looseStmt->execute([$company['id'], $department['id']]);
            $looseCount = $looseStmt->fetch(PDO::FETCH_ASSOC);
            
            $deptData['document_count'] += (int)$looseCount['count'];
            
            $companyData['departments'][] = $deptData;
        }
        
        // Solo agregar la empresa si tiene departamentos accesibles
        if (!empty($companyData['departments']) || SessionManager::isAdmin()) {
            $result['companies'][] = $companyData;
        }
    }
    
    // ========================================
    // INFORMACIÓN ADICIONAL PARA DEBUG
    // ========================================
    
    if (SessionManager::isAdmin()) {
        $result['debug'] = [
            'permissions' => 'ADMIN - ACCESO TOTAL',
            'restrictions' => 'NINGUNA'
        ];
    } else {
        $perms = getUserPermissions($currentUser['id']);
        $result['debug'] = [
            'permissions' => array_keys(array_filter($perms['permissions'])),
            'restrictions' => $perms['restrictions'],
            'restrictions_message' => getRestrictionsMessage($currentUser['id'])
        ];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'debug' => $e->getMessage()
    ]);
}

?>