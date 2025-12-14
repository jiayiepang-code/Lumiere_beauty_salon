<?php
/**
 * Toggle Service Status API
 */

// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);
session_start();

header('Content-Type: application/json');

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

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only POST requests are allowed', null, 405);
}

try {
    // Get database connection
    $conn = getDBConnection();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input === null) {
        ErrorHandler::sendError(ErrorHandler::INVALID_JSON, 'Invalid JSON data');
    }
    
    // Validate CSRF token
    if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
        ErrorHandler::sendError(ErrorHandler::INVALID_CSRF_TOKEN, 'Invalid CSRF token', null, 403);
    }
    
    $service_id = $input['service_id'] ?? null;
    
    if (!$service_id) {
        ErrorHandler::sendError(ErrorHandler::VALIDATION_ERROR, 'Service ID is required', ['service_id' => 'Service ID is required'], 400);
    }
    
    // Check if service exists (service_id is VARCHAR(4))
    $check_sql = "SELECT service_id FROM Service WHERE service_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $service_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $conn->close();
        ErrorHandler::handleNotFound('Service');
    }
    $check_stmt->close();
    
    // Toggle the status (service_id is VARCHAR(4))
    $stmt = $conn->prepare("UPDATE Service SET is_active = NOT is_active WHERE service_id = ?");
    $stmt->bind_param("s", $service_id);
    
    if ($stmt->execute()) {
        // Get the new status
        $status_stmt = $conn->prepare("SELECT is_active FROM Service WHERE service_id = ?");
        $status_stmt->bind_param("s", $service_id);
        $status_stmt->execute();
        $status_result = $status_stmt->get_result();
        $row = $status_result->fetch_assoc();
        $status_stmt->close();
        
        $new_status = $row['is_active'] ? 'Active' : 'Inactive';
        
        $stmt->close();
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Service status updated to ' . $new_status,
            'is_active' => (bool)$row['is_active']
        ]);
    } else {
        throw new Exception('Failed to toggle service status: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'toggling service status');
}
?>