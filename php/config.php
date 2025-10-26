<?php
// config.php - Database Configuration
session_start();

// Database credentials
// OpenAI API Key (provided by user) for real AI responses
if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', 'YOUR_OPENAI_API_KEY');
}
// Default model (can be changed to 'gpt-4o-mini' or others available to your key)
if (!defined('OPENAI_MODEL')) {
    define('OPENAI_MODEL', 'gpt-4o-mini');
}

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'career_copilot');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// CORS headers for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
?>