<?php
/**
 * Admin Password Re-Authentication Endpoint
 * Verifies admin password and sets a short-lived session flag to authorize deletion
 * Not MFA; acts as a step-up authentication for destructive actions
 */

header('Content-Type: application/json');

// Include required files
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';
require_once '../../../admin/includes/error_handler.php';
require_once '../includes/csrf_validation.php';

// Check authentication
if (!isAdminAuthenticated()) {
    ErrorHandler::handleAuthError();
}

// Check session timeout
if (!checkSessionTimeout()) {
    ErrorHandler::sendError(ErrorHandler::SESSION_EXPIRED, 'Session has expired', null, 401);
}

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only POST requests are allowed', null, 405);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input === null || empty($input)) {
        ErrorHandler::sendError(ErrorHandler::INVALID_JSON, 'Invalid JSON data');
    }
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        ErrorHandler::sendError(ErrorHandler::INVALID_CSRF_TOKEN, 'Invalid CSRF token', null, 403);
    }
    
    // Validate required fields
    if (empty($input['password'])) {
        ErrorHandler::handleValidationError(['password' => 'Password is required']);
    }
    
    $password = $input['password'];
    $adminEmail = $_SESSION['admin']['email'] ?? null;
    
    if (!$adminEmail) {
        ErrorHandler::sendError(ErrorHandler::AUTH_FAILED, 'Admin session invalid', null, 401);
    }
    
    // Get database connection
    $conn = getDBConnection();
    
    // Fetch admin's password hash
    $stmt = $conn->prepare("SELECT password FROM Staff WHERE staff_email = ? AND role = 'admin'");
    $stmt->bind_param("s", $adminEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        ErrorHandler::sendError(ErrorHandler::AUTH_FAILED, 'Admin account not found', null, 401);
    }
    
    $admin = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    // Verify password
    if (!password_verify($password, $admin['password'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_PASSWORD',
                'message' => 'Incorrect password'
            ]
        ]);
        exit;
    }
    
    // Set short-lived session flag (valid for 120 seconds)
    $_SESSION['reauth_ok_until'] = time() + 120;
    $_SESSION['reauth_action'] = 'delete';
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Password verified. You may now proceed with deletion.'
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'password verification');
}
?>
