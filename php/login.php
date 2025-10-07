<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$host = 'localhost';
$dbname = 'career_copilot';
$username = 'root'; // Change according to your MySQL username
$password = ''; // Change according to your MySQL password

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validate input
    if (empty($email) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email and password are required',
            'field' => empty($email) ? 'email' : 'password'
        ]);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid email address',
            'field' => 'email'
        ]);
        exit;
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password',
            'field' => 'email'
        ]);
        exit;
    }
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Password is correct, create session data
        session_start();
        
        // Generate a simple token (you can use JWT for more security)
        $token = bin2hex(random_bytes(32));
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['token'] = $token;
        $_SESSION['login_time'] = time();
        
        // Update last login time
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $updateStmt->bindParam(':id', $user['id']);
        $updateStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful! Redirecting to dashboard...',
            'user_id' => $user['id'],
            'email' => $user['email'],
            'token' => $token
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password',
            'field' => 'password'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>