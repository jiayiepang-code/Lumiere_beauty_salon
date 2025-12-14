<?php
/**
 * Customer DELETE API Endpoint
 * Handles customer account deletion (permanent delete)
 */

// Include required files
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';

header('Content-Type: application/json');
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

// Handle DELETE request only
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only DELETE requests are allowed', null, 405);
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
    if (empty($input['customer_email'])) {
        ErrorHandler::handleValidationError(['customer_email' => 'Email is required']);
    }
    
    $customer_email = trim($input['customer_email']);
    
    // Get database connection
    $conn = getDBConnection();
    
    // Check if customer exists
    $check_sql = "SELECT customer_email, first_name, last_name FROM customer WHERE customer_email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $customer_email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $conn->close();
        ErrorHandler::handleNotFound('Customer');
    }
    
    $customer = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Check for foreign key constraints (bookings)
    // Check if customer has any bookings
    $check_bookings_sql = "SELECT COUNT(*) as count FROM booking WHERE customer_email = ?";
    $check_bookings_stmt = $conn->prepare($check_bookings_sql);
    $check_bookings_stmt->bind_param("s", $customer_email);
    $check_bookings_stmt->execute();
    $bookings_result = $check_bookings_stmt->get_result();
    $bookings_count = $bookings_result->fetch_assoc()['count'];
    $check_bookings_stmt->close();
    
    // If customer has bookings, prevent deletion
    if ($bookings_count > 0) {
        $conn->close();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'HAS_BOOKINGS',
                'message' => 'Cannot delete customer with existing bookings. Customer has ' . $bookings_count . ' booking(s).',
                'bookings_count' => $bookings_count
            ]
        ]);
        exit;
    }
    
    // Proceed with deletion
    $sql = "DELETE FROM customer WHERE customer_email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $customer_email);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Customer deleted successfully',
            'customer' => [
                'customer_email' => $customer['customer_email'],
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name']
            ]
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        
        throw new Exception("Failed to delete customer: " . $error);
    }

} catch (Exception $e) {
    error_log("Customer deletion error: " . $e->getMessage());
    
    if (isset($conn)) {
        $conn->close();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'DELETE_FAILED',
            'message' => $e->getMessage()
        ]
    ]);
}
?>
