<?php
// simple_test.php - Test súper básico
error_reporting(E_ALL);
ini_set("display_errors", 0);

try {
    // Solo las inclusiones básicas
    if (file_exists("../../config/database.php")) {
        require_once "../../config/database.php";
    } else {
        throw new Exception("No se encuentra config/database.php");
    }
    
    if (file_exists("../../config/session.php")) {
        require_once "../../config/session.php";
    } else {
        throw new Exception("No se encuentra config/session.php");
    }
    
    header("Content-Type: application/json");
    
    // Test de conexión básica
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    // Test de documentos
    $query = "SELECT id, name FROM documents LIMIT 3";
    $result = $pdo->query($query);
    $docs = $result->fetchAll(PDO::FETCH_ASSOC);
    
    // Respuesta básica
    echo json_encode([
        "success" => true,
        "message" => "Test básico exitoso",
        "documents_found" => count($docs),
        "sample_documents" => $docs,
        "post_data" => $_POST,
        "get_data" => $_GET
    ]);
    
} catch (Exception $e) {
    header("Content-Type: application/json");
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
}
?>