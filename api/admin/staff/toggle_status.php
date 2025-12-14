<?php
/**
 * Staff Toggle Status API Endpoint
 * Toggles staff active/inactive status
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
    if (empty($input['staff_email'])) {
        ErrorHandler::handleValidationError(['staff_email' => 'Email is required']);
    }
    
    $staff_email = trim($input['staff_email']);
    
    // Get database connection
    $conn = getDBConnection();
    
    // Check if staff exists and get current status
    $check_sql = "SELECT is_active FROM staff WHERE staff_email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $staff_email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $conn->close();
        ErrorHandler::handleNotFound('Staff member');
    }
    
    $current_status = $check_result->fetch_assoc()['is_active'];
    $check_stmt->close();
    
    // Toggle status
    $new_status = $current_status ? 0 : 1;
    
    $sql = "UPDATE staff SET is_active = ? WHERE staff_email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $new_status, $staff_email);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'is_active' => (bool)$new_status,
            'message' => 'Staff status updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update staff status: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'staff status update');
}
?>
