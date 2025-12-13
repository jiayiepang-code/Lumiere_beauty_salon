<?php
/**
 * Staff Details API Endpoint
 * Returns details of a single staff member
 */

// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();

header('Content-Type: application/json');

// Include required files
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';
require_once '../../../admin/includes/error_handler.php';

// Check authentication
if (!isAdminAuthenticated()) {
    ErrorHandler::handleAuthError();
}

// Check session timeout
if (!checkSessionTimeout()) {
    ErrorHandler::sendError(ErrorHandler::SESSION_EXPIRED, 'Session has expired', null, 401);
}

// Handle GET request only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only GET requests are allowed', null, 405);
}

try {
    // Get email parameter
    if (!isset($_GET['email']) || empty($_GET['email'])) {
        ErrorHandler::sendError(ErrorHandler::VALIDATION_ERROR, 'Email parameter is required');
    }
    
    $staff_email = trim($_GET['email']);
    
    // Get database connection
    $conn = getDBConnection();
    
    // Fetch staff details
    $sql = "SELECT staff_email, phone, first_name, last_name, bio, role, 
                   staff_image, is_active, created_at
            FROM staff
            WHERE staff_email = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $staff_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        ErrorHandler::handleNotFound('Staff member');
    }
    
    $staff = $result->fetch_assoc();
    $staff['is_active'] = (bool)$staff['is_active'];
    
    $stmt->close();
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'staff' => $staff
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'fetching staff details');
}
?>

