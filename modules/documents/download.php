<?php
/**
 * download.php - SISTEMA UNIFICADO DE PERMISOS
 * Solo grupos controlan el acceso - Sin restricciones de empresa
 */

require_once '../../bootstrap.php';

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inbox.php?error=invalid_request');
    exit;
}

if (!isset($_POST['document_id']) || !is_numeric($_POST['document_id'])) {
    header('Location: inbox.php?error=invalid_document');
    exit;
}

$documentId = intval($_POST['document_id']);

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // ===== VERIFICACIÓN DE PERMISOS - SOLO GRUPOS =====
    $hasDownloadPermission = false;

    if ($currentUser['role'] === 'admin') {
        $hasDownloadPermission = true;
    } else {
        // Verificar sistema unificado
        if (class_exists('UnifiedPermissionSystem')) {
            try {
                $permissionSystem = UnifiedPermissionSystem::getInstance();
                $userPerms = $permissionSystem->getUserEffectivePermissions($currentUser['id']);
                $hasDownloadPermission = isset($userPerms['permissions']['download_files']) && 
                                         $userPerms['permissions']['download_files'] === true;
            } catch (Exception $e) {
                error_log('ERROR en verificación de permisos download: ' . $e->getMessage());
                $hasDownloadPermission = false;
            }
        } else {
            // Sistema legacy
            $stmt = $pdo->prepare("SELECT ug.module_permissions FROM user_groups ug
                                   INNER JOIN user_group_members ugm ON ug.id = ugm.group_id
                                   WHERE ugm.user_id = ? AND ug.status = 'active'");
            $stmt->execute([$currentUser['id']]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($groups as $group) {
                $permissions = json_decode($group['module_permissions'] ?: '{}', true);
                if (isset($permissions['download_files']) && $permissions['download_files'] === true) {
                    $hasDownloadPermission = true;
                    break;
                }
            }
        }
    }

    if (!$hasDownloadPermission) {
        header('Location: inbox.php?error=download_disabled');
        exit;
    }

    // ===== OBTENER DOCUMENTO - SIN RESTRICCIONES DE EMPRESA =====
    // Los grupos ya controlan a qué puede acceder el usuario
    $query = "SELECT d.*, c.name as company_name, dept.name as department_name
              FROM documents d
              LEFT JOIN companies c ON d.company_id = c.id
              LEFT JOIN departments dept ON d.department_id = dept.id
              WHERE d.id = ? AND d.status = 'active'";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        header('Location: inbox.php?error=document_not_found');
        exit;
    }

    // ===== VERIFICAR ARCHIVO FÍSICO =====
    $filePath = '../../' . $document['file_path'];
    
    if (!file_exists($filePath)) {
        error_log("Archivo no encontrado: $filePath para documento ID: $documentId");
        header('Location: inbox.php?error=file_not_found');
        exit;
    }

    // ===== REGISTRAR ACTIVIDAD =====
    if (function_exists('logActivity')) {
        logActivity(
            $currentUser['id'], 
            'download', 
            'documents', 
            $documentId, 
            "Descarga: {$document['name']} ({$document['company_name']})"
        );
    }

    // ===== PREPARAR Y ENVIAR DESCARGA =====
    $fileSize = filesize($filePath);
    $originalName = $document['original_name'] ?: $document['name'];
    $mimeType = $document['mime_type'] ?: 'application/octet-stream';

    // Limpiar buffer de salida
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Headers para descarga
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . addslashes($originalName) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $fileSize);

    // Enviar archivo
    if ($fileSize > 8 * 1024 * 1024) { // Mayor a 8MB
        $handle = fopen($filePath, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        } else {
            readfile($filePath);
        }
    } else {
        readfile($filePath);
    }

    exit;

} catch (Exception $e) {
    error_log("Error en descarga: " . $e->getMessage());
    header('Location: inbox.php?error=download_failed');
    exit;
}
?>