<?php
/**
 * Admin Login API Endpoint
 * File: api/admin/auth/login.php
 * 
 * Handles admin authentication for the Lumière Beauty Salon system
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Set JSON header
header('Content-Type: application/json');

// Include required files
require_once '../../../config/config.php';
require_once '../../../config/utils.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input exists
if (!$data || !isset($data['phone']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Phone and password are required'
    ]);
    exit;
}

// Sanitize inputs
$phone_input = trim($data['phone']);
$password = $data['password'];

// Validate phone format
if (empty($phone_input)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter your phone number'
    ]);
    exit;
}

// Sanitize phone to consistent format
$phone = sanitizePhone($phone_input);

// Validate Malaysian phone format
if (!isValidMalaysianPhone($phone)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid phone number format'
    ]);
    exit;
}

// Check if account is locked
if (isAccountLocked($phone)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Account temporarily locked due to multiple failed attempts. Please try again in 15 minutes.'
    ]);
    exit;
}

try {
    // Connect to database
    $conn = getDBConnection();
    
    // Prepare statement to find admin by phone
    $stmt = $conn->prepare("
        SELECT staff_email, phone, password, first_name, last_name, role, is_active 
        FROM Staff 
        WHERE phone = ? AND role = 'admin'
    ");
    
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if admin exists
    if ($result->num_rows === 0) {
        // Log failed attempt
        logAuthAttempt($phone, false);
        
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    // Get admin data
    $admin = $result->fetch_assoc();
    $stmt->close();
    
    // Check if account is active
    if ($admin['is_active'] != 1) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Account is deactivated. Please contact support.'
        ]);
        $conn->close();
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $admin['password'])) {
        // Log failed attempt
        logAuthAttempt($phone, false);
        
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials'
        ]);
        $conn->close();
        exit;
    }
    
    // ✅ LOGIN SUCCESSFUL
    
    // Log successful attempt
    logAuthAttempt($phone, true);
    
    // Log to Admin_Login_Log if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'Admin_Login_Log'");
    if ($tableCheck->num_rows > 0) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logStmt = $conn->prepare("INSERT INTO Admin_Login_Log (admin_email, login_time, ip_address) VALUES (?, NOW(), ?)");
        $logStmt->bind_param("ss", $admin['staff_email'], $ip_address);
        $logStmt->execute();
        $logStmt->close();
    }
    
    // Set session variables
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_email'] = $admin['staff_email'];
    $_SESSION['admin_phone'] = $admin['phone'];
    $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['login_time'] = time();
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    $conn->close();
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'name' => $admin['first_name'],
            'email' => $admin['staff_email'],
            'redirect' => '/admin/index.php'
        ]
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Admin Login Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}