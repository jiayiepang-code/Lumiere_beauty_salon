<?php
/**
 * Admin Login API Endpoint - UPDATED VERSION
 * File: api/admin/auth/login.php
 * 
 * Matches your original session structure with $_SESSION['admin']
 */

// Disable error display (errors will be logged, not printed)
error_reporting(0);
ini_set('display_errors', 0);

// Configure secure session BEFORE starting
require_once __DIR__ . '/../../../admin/includes/security_utils.php';
configureSecureSession();

// Start session
session_start();

// Set JSON header
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/utils.php';

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
        SELECT staff_email, phone, password, first_name, last_name, role, is_active, staff_image 
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
    $admin_data = $result->fetch_assoc();
    $stmt->close();
    
    // Check if account is active
    if ($admin_data['is_active'] != 1) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Account is deactivated. Please contact support.'
        ]);
        $conn->close();
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $admin_data['password'])) {
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
        $logStmt->bind_param("ss", $admin_data['staff_email'], $ip_address);
        $logStmt->execute();
        $logStmt->close();
    }
    
    // Generate CSRF token
    $csrf_token = bin2hex(random_bytes(32));
    
    // Set session variables in YOUR ORIGINAL FORMAT
    $_SESSION['admin'] = [
        'email' => $admin_data['staff_email'],
        'phone' => $admin_data['phone'],
        'first_name' => $admin_data['first_name'],
        'last_name' => $admin_data['last_name'],
        'name' => $admin_data['first_name'] . ' ' . $admin_data['last_name'],
        'role' => $admin_data['role'],
        'staff_image' => $admin_data['staff_image'] ?? null,
        'csrf_token' => $csrf_token
    ];
    
    // Set additional session security variables
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Ensure session is written (but don't close it - let it persist)
    // The session will automatically be saved when the script ends
    
    $conn->close();
    
    // Determine redirect URL (use relative path for better compatibility)
    $base_path = '/Lumiere-beauty-salon';
    $redirect_url = $base_path . '/admin/index.php';
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'name' => $admin_data['first_name'],
            'email' => $admin_data['staff_email'],
            'redirect' => $redirect_url
        ],
        'redirect' => $redirect_url, // Also include at top level for compatibility
        'csrf_token' => $csrf_token
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Admin Login Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.',
        'debug' => $e->getMessage() // Remove this in production!
    ]);
}