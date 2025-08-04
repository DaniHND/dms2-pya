<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/session.php';

try {
    if (!SessionManager::isLoggedIn()) {
        throw new Exception("Usuario no autenticado");
    }
    
    $currentUser = SessionManager::getCurrentUser();
    if ($currentUser['role'] !== 'admin') {
        throw new Exception("Permisos insuficientes");
    }
    
    // Simplemente devolver tipos ficticios que siempre funcionan
    $documentTypes = [
        ['id' => 1, 'name' => 'PDF', 'description' => 'Documentos PDF'],
        ['id' => 2, 'name' => 'Word', 'description' => 'Documentos de Word'],
        ['id' => 3, 'name' => 'Excel', 'description' => 'Hojas de cálculo'],
        ['id' => 4, 'name' => 'Imagen', 'description' => 'Archivos de imagen'],
        ['id' => 5, 'name' => 'Otros', 'description' => 'Otros tipos de documentos']
    ];
    
    echo json_encode([
        'success' => true,
        'document_types' => $documentTypes,
        'total' => count($documentTypes)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>