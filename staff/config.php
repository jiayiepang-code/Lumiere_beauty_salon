<?php
// Database configuration for Local XAMPP
define('DB_HOST', 'localhost');    // Standard for XAMPP
define('DB_NAME', 'salon');        // Your local database name
define('DB_USER', 'root');         // Default XAMPP username
define('DB_PASS', '');             // Default XAMPP password is empty
define('DB_PORT', 3306);           // Default MySQL port

// Create database connection using PDO
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session with proper settings (use staff-specific session name)
if (session_status() === PHP_SESSION_NONE) {
    // Ensure APIs read the same session cookie set by staff/login.php
    if (session_name() !== 'staff_session') {
        session_name('staff_session');
    }
    session_set_cookie_params([
        'lifetime' => 0, // Until browser closes
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to false for local development (no HTTPS)
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Helper function to sanitize user input
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Helper function to send JSON responses
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Security helper to check if staff is logged in
 */
function checkAuth() {
    if (!isset($_SESSION['staff_id'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
}
?>