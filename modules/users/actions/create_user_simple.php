<?php
// modules/users/actions/create_user_simple.php
// Versión ultra simplificada para diagnosticar

header('Content-Type: application/json');

// Iniciar buffer de salida para capturar cualquier error
ob_start();

try {
    // 1. Verificar que es POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // 2. Verificar sesión
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('No hay sesión activa');
    }
    
    // 3. Obtener datos básicos
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // 4. Validaciones básicas
    if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
        throw new Exception('Faltan datos requeridos');
    }
    
    // 5. Conectar a base de datos
    $host = 'localhost';
    $dbname = 'dms2';
    $user = 'root';
    $pass = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 6. Verificar que no existe el usuario
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        throw new Exception('El usuario o email ya existe');
    }
    
    // 7. Insertar usuario
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (first_name, last_name, username, email, password, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'user', 'active', NOW())";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$firstName, $lastName, $username, $email, $hashedPassword]);
    
    if ($result) {
        $newUserId = $pdo->lastInsertId();
        
        // Limpiar cualquier salida no deseada
        ob_clean();
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'user_id' => $newUserId
        ]);
    } else {
        throw new Exception('Error al insertar en la base de datos');
    }
    
} catch (Exception $e) {
    // Limpiar cualquier salida no deseada
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
}

// Terminar el buffer
ob_end_flush();
?>