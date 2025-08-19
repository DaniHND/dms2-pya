<?php
/**
 * delete.php - VERSIÓN SIMPLIFICADA Y FUNCIONAL
 * Ubicación: modules/documents/delete.php
 * Esta versión está simplificada para funcionar de manera confiable
 */

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/group_permissions.php';

// Habilitar logging detallado
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log de inicio
error_log("=== DELETE.PHP INICIADO ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . json_encode($_POST));

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERROR: Método no es POST");
    header('Location: inbox.php?error=invalid_request');
    exit;
}

// Verificar document_id
if (!isset($_POST['document_id']) || !is_numeric($_POST['document_id'])) {
    error_log("ERROR: document_id inválido - " . ($_POST['document_id'] ?? 'null'));
    header('Location: inbox.php?error=invalid_document_id');
    exit;
}

$documentId = intval($_POST['document_id']);
$returnPath = $_POST['return_path'] ?? '';

error_log("Procesando eliminación:");
error_log("- Documento ID: $documentId");
error_log("- Usuario: " . $currentUser['username'] . " (ID: " . $currentUser['id'] . ")");
error_log("- Return Path: $returnPath");

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // ===== VERIFICACIÓN SIMPLE DE PERMISOS =====
    $hasDeletePermission = false;
    $isAdmin = ($currentUser['role'] === 'admin' || $currentUser['role'] === 'super_admin');

    if ($isAdmin) {
        $hasDeletePermission = true;
        error_log("PERMISO: Usuario es administrador");
    } else {
        error_log("Verificando permisos de grupo...");
        
        try {
            $groupPermissions = getUserGroupPermissions($currentUser['id']);
            error_log("Permisos de grupo obtenidos: " . json_encode($groupPermissions));
            
            if ($groupPermissions['has_groups']) {
                $deletePermission = $groupPermissions['permissions']['delete_files'] ?? false;
                $hasDeletePermission = ($deletePermission === true);
                
                error_log("delete_files = " . ($deletePermission ? 'true' : 'false'));
                error_log("hasDeletePermission = " . ($hasDeletePermission ? 'true' : 'false'));
            } else {
                error_log("Usuario no tiene grupos activos");
            }
        } catch (Exception $e) {
            error_log("Error al obtener permisos de grupo: " . $e->getMessage());
            $hasDeletePermission = false;
        }
    }

    if (!$hasDeletePermission) {
        error_log("ERROR: Usuario sin permisos de eliminación");
        header('Location: inbox.php?error=no_delete_permission&path=' . urlencode($returnPath));
        exit;
    }

    error_log("✅ Usuario tiene permisos de eliminación");

    // ===== OBTENER DOCUMENTO =====
    $query = "SELECT d.*, c.name as company_name FROM documents d LEFT JOIN companies c ON d.company_id = c.id WHERE d.id = ? AND d.status = 'active'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        error_log("ERROR: Documento no encontrado - ID: $documentId");
        header('Location: inbox.php?error=document_not_found&path=' . urlencode($returnPath));
        exit;
    }

    error_log("✅ Documento encontrado: " . $document['name']);

    // ===== VERIFICACIÓN DE ACCESO SIMPLIFICADA =====
    $canDeleteDocument = false;

    if ($isAdmin) {
        $canDeleteDocument = true;
        error_log("ACCESO: Admin puede eliminar cualquier documento");
    } else {
        // Para usuarios no admin, verificar si es su documento O si tiene acceso por grupo
        if ($document['user_id'] == $currentUser['id']) {
            $canDeleteDocument = true;
            error_log("ACCESO: Es propietario del documento");
        } else {
            // Verificar acceso por grupo (sin restricciones por ahora)
            if ($hasDeletePermission) {
                $canDeleteDocument = true;
                error_log("ACCESO: Permitido por permisos de grupo");
            }
        }
    }

    if (!$canDeleteDocument) {
        error_log("ERROR: Usuario no tiene acceso a este documento");
        header('Location: inbox.php?error=access_denied&path=' . urlencode($returnPath));
        exit;
    }

    // ===== EJECUTAR ELIMINACIÓN =====
    error_log("Iniciando eliminación...");
    
    $pdo->beginTransaction();

    try {
        // SOFT DELETE
        $updateQuery = "UPDATE documents SET status = 'deleted', deleted_at = NOW(), deleted_by = ? WHERE id = ?";
        $stmt = $pdo->prepare($updateQuery);
        $result = $stmt->execute([$currentUser['id'], $documentId]);

        if (!$result) {
            throw new Exception('Error al ejecutar UPDATE');
        }

        $rowsAffected = $stmt->rowCount();
        error_log("Filas afectadas: $rowsAffected");

        if ($rowsAffected === 0) {
            throw new Exception('Ninguna fila fue actualizada');
        }

        // Registrar actividad
        try {
            $logQuery = "INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $logStmt = $pdo->prepare($logQuery);
            $logStmt->execute([
                $currentUser['id'],
                'document_deleted',
                'documents',
                $documentId,
                "Eliminó documento: {$document['name']} ({$document['company_name']})"
            ]);
            error_log("✅ Actividad registrada");
        } catch (Exception $e) {
            error_log("Warning: Error registrando actividad: " . $e->getMessage());
            // No fallar por esto
        }

        $pdo->commit();
        error_log("✅ ELIMINACIÓN EXITOSA - Transacción confirmada");

        // ===== REDIRECCIÓN =====
        $redirectUrl = 'inbox.php?success=document_deleted&deleted_name=' . urlencode($document['name']);
        
        if (!empty($returnPath)) {
            $redirectUrl .= '&path=' . urlencode($returnPath);
        }

        error_log("Redirigiendo a: $redirectUrl");
        
        // Forzar redirección inmediata
        header('Location: ' . $redirectUrl);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("ERROR en eliminación: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        header('Location: inbox.php?error=delete_failed&message=' . urlencode($e->getMessage()) . '&path=' . urlencode($returnPath));
        exit;
    }

} catch (Exception $e) {
    error_log("ERROR CRÍTICO: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Location: inbox.php?error=system_error&message=' . urlencode($e->getMessage()) . '&path=' . urlencode($returnPath));
    exit;
}

// Esta línea nunca debería ejecutarse
error_log("WARNING: Llegó al final del script sin redirección");
?>