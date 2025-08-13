<?php

/**
 * delete.php - Versión corregida sin doble codificación
 * REEMPLAZAR delete.php existente con este código
 */

require_once '../../bootstrap.php';

SessionManager::requireLogin();
$currentUser = SessionManager::getCurrentUser();

// Log inicial
error_log("DELETE.PHP - Iniciando eliminación para usuario: " . $currentUser['username']);
error_log("DELETE.PHP - POST data: " . print_r($_POST, true));
error_log("DELETE.PHP - REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("DELETE.PHP - ERROR: Método no es POST");
    header('Location: inbox.php?error=invalid_request');
    exit;
}

// Verificar document_id
if (!isset($_POST['document_id']) || !is_numeric($_POST['document_id'])) {
    error_log("DELETE.PHP - ERROR: document_id inválido");
    header('Location: inbox.php?error=invalid_document_id');
    exit;
}

$documentId = intval($_POST['document_id']);
$returnPath = $_POST['return_path'] ?? '';

error_log("DELETE.PHP - Document ID: $documentId");
error_log("DELETE.PHP - Return Path recibido: '$returnPath'");

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // ===== VERIFICACIÓN DE PERMISOS =====
    $hasDeletePermission = false;

    if ($currentUser['role'] === 'admin') {
        $hasDeletePermission = true;
        error_log("DELETE.PHP - Usuario es admin");
    } else {
        // Verificar sistema unificado
        if (class_exists('UnifiedPermissionSystem')) {
            try {
                $permissionSystem = UnifiedPermissionSystem::getInstance();
                $userPerms = $permissionSystem->getUserEffectivePermissions($currentUser['id']);
                $hasDeletePermission = isset($userPerms['permissions']['delete_files']) &&
                    $userPerms['permissions']['delete_files'] === true;
                error_log("DELETE.PHP - Permisos por sistema unificado: " . ($hasDeletePermission ? 'SÍ' : 'NO'));
            } catch (Exception $e) {
                error_log('DELETE.PHP - ERROR en verificación de permisos: ' . $e->getMessage());
                $hasDeletePermission = false;
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
                if (isset($permissions['delete_files']) && $permissions['delete_files'] === true) {
                    $hasDeletePermission = true;
                    break;
                }
            }
            error_log("DELETE.PHP - Permisos por sistema legacy: " . ($hasDeletePermission ? 'SÍ' : 'NO'));
        }
    }

    if (!$hasDeletePermission) {
        error_log("DELETE.PHP - ERROR: Usuario sin permisos de eliminación");
        header('Location: inbox.php?error=delete_disabled');
        exit;
    }

    // ===== OBTENER DOCUMENTO =====
    $query = "SELECT d.*, c.name as company_name, u.first_name, u.last_name
              FROM documents d
              LEFT JOIN companies c ON d.company_id = c.id
              LEFT JOIN users u ON d.user_id = u.id
              WHERE d.id = ? AND d.status = 'active'";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        error_log("DELETE.PHP - ERROR: Documento no encontrado o no activo");
        header('Location: inbox.php?error=document_not_found');
        exit;
    }

    error_log("DELETE.PHP - Documento encontrado: " . $document['name']);

    // ===== VERIFICAR SI PUEDE ELIMINAR ESTE DOCUMENTO =====
    if ($currentUser['role'] !== 'admin' && $document['user_id'] != $currentUser['id']) {
        error_log("DELETE.PHP - ERROR: Usuario no es admin ni dueño");
        header('Location: inbox.php?error=not_owner');
        exit;
    }

    // ===== INICIAR TRANSACCIÓN =====
    $pdo->beginTransaction();

    try {
        // Marcar documento como eliminado (soft delete)
        $updateQuery = "UPDATE documents SET 
                        status = 'deleted', 
                        updated_at = NOW()";

        // Verificar si existen campos adicionales para eliminación
        $checkColumns = $pdo->query("SHOW COLUMNS FROM documents LIKE 'deleted_at'");
        if ($checkColumns->rowCount() > 0) {
            $updateQuery .= ", deleted_at = NOW(), deleted_by = ?";
            $updateParams = [$currentUser['id'], $documentId];
        } else {
            $updateParams = [$documentId];
        }

        $updateQuery .= " WHERE id = ?";

        $stmt = $pdo->prepare($updateQuery);
        $result = $stmt->execute($updateParams);

        if (!$result) {
            throw new Exception('Error al marcar documento como eliminado');
        }

        error_log("DELETE.PHP - Documento marcado como eliminado exitosamente");

        // ===== MANEJAR ARCHIVO FÍSICO =====
        $filePath = '../../' . $document['file_path'];

        if (file_exists($filePath)) {
            // EN LUGAR DE MOVER A CARPETA DELETED, ELIMINAR DIRECTAMENTE
            if (unlink($filePath)) {
                error_log("DELETE.PHP - Archivo eliminado completamente");
            } else {
                error_log("DELETE.PHP - Error al eliminar archivo físico");
            }
            // NO crear ni mover a carpeta deleted


            // Mover archivo a carpeta de eliminados
            $timestamp = time();
            $originalBasename = basename($document['file_path']);
            $deletedFileName = $documentId . '_' . $timestamp . '_' . $originalBasename;
            $deletedFilePath = $deletedDir . $deletedFileName;

            if (rename($filePath, $deletedFilePath)) {
                error_log("DELETE.PHP - Archivo movido a carpeta de eliminados");
                // Actualizar ruta en BD si es posible
                if ($checkColumns->rowCount() > 0) {
                    $updatePathQuery = "UPDATE documents SET file_path = ? WHERE id = ?";
                    $stmt = $pdo->prepare($updatePathQuery);
                    $stmt->execute(['uploads/deleted/' . $deletedFileName, $documentId]);
                }
            } else {
                // Si no se puede mover, eliminar directamente
                unlink($filePath);
                error_log("DELETE.PHP - Archivo eliminado directamente");
            }
        }

        // ===== REGISTRAR ACTIVIDAD =====
        if (function_exists('logActivity')) {
            logActivity(
                $currentUser['id'],
                'delete',
                'documents',
                $documentId,
                "Eliminó documento: {$document['name']} ({$document['company_name']})"
            );
        }

        // Confirmar transacción
        $pdo->commit();
        error_log("DELETE.PHP - Transacción completada exitosamente");

        // ===== CONSTRUIR URL DE REDIRECCIÓN (SIN DOBLE CODIFICACIÓN) =====
        $redirectUrl = 'inbox.php';

        if (!empty($returnPath)) {
            // IMPORTANTE: NO volver a codificar si ya está codificado
            $decodedPath = urldecode($returnPath);
            error_log("DELETE.PHP - Return path decodificado: '$decodedPath'");

            $redirectUrl .= '?path=' . urlencode($decodedPath);
            $redirectUrl .= '&success=document_deleted';
        } else {
            $redirectUrl .= '?success=document_deleted';
        }

        $redirectUrl .= '&name=' . urlencode($document['name']);

        error_log("DELETE.PHP - URL de redirección final: " . $redirectUrl);

        header('Location: ' . $redirectUrl);
        exit;
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("DELETE.PHP - Error en transacción: " . $e->getMessage());
        header('Location: inbox.php?error=delete_failed');
        exit;
    }
} catch (Exception $e) {
    error_log("DELETE.PHP - Error general: " . $e->getMessage());
    header('Location: inbox.php?error=delete_failed');
    exit;
}
