<?php
// register.php - Enhanced Registration Handler with Advanced Validation
require_once 'config.php';

// Set JSON header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email = sanitize_input($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';
    
    // Comprehensive Validation
    
    // 1. Check for empty fields
    if (empty($email) || empty($password) || empty($confirm_password)) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required',
            'field' => 'all'
        ]);
        exit;
    }
    
    // 2. Validate email format
    if (!validate_email($email)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format. Please enter a valid email address.',
            'field' => 'email'
        ]);
        exit;
    }
    
    // 3. Check email length
    if (strlen($email) > 255) {
        echo json_encode([
            'success' => false,
            'message' => 'Email is too long (maximum 255 characters)',
            'field' => 'email'
        ]);
        exit;
    }
    
    // 4. Validate password strength
    if (strlen($password) < 8) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 8 characters long',
            'field' => 'password'
        ]);
        exit;
    }
    
    // 5. Check password complexity
    if (!preg_match('/[A-Z]/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must contain at least one uppercase letter',
            'field' => 'password'
        ]);
        exit;
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must contain at least one lowercase letter',
            'field' => 'password'
        ]);
        exit;
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must contain at least one number',
            'field' => 'password'
        ]);
        exit;
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must contain at least one special character (!@#$%^&*)',
            'field' => 'password'
        ]);
        exit;
    }
    
    // 6. Check password match
    if ($password !== $confirm_password) {
        echo json_encode([
            'success' => false,
            'message' => 'Passwords do not match. Please re-enter your password.',
            'field' => 'confirm_password'
        ]);
        exit;
    }
    
    // 7. Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'This email is already registered. Please login or use a different email.',
            'field' => 'email'
        ]);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // 8. Hash password with bcrypt
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // 9. Insert user into database
    $stmt = $conn->prepare("INSERT INTO users (email, password, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $email, $hashed_password);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Log the registration (optional)
        error_log("New user registered: ID=$user_id, Email=$email");
        
        echo json_encode([
            'success' => true,
            'message' => '🎉 Registration successful! Redirecting to login...',
            'user_id' => $user_id
        ]);
    } else {
        // Log the error
        error_log("Registration failed: " . $stmt->error);
        
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed due to a server error. Please try again later.',
            'field' => 'server'
        ]);
    }
    
    $stmt->close();
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST requests are allowed.',
        'field' => 'method'
    ]);
}

$conn->close();
?>