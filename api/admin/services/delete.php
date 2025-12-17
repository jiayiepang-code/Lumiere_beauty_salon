<?php
// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);

// Use admin-specific session name to match auth_check.php
session_name('admin_session');
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

// Handle DELETE request only
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only DELETE requests are allowed', null, 405);
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
    
    // Validate service_id
    if (!isset($input['service_id']) || empty($input['service_id'])) {
        ErrorHandler::sendError(ErrorHandler::VALIDATION_ERROR, 'Service ID is required', ['service_id' => 'Service ID is required'], 400);
    }
    
    $service_id = $input['service_id'];
    $action = isset($input['action']) ? $input['action'] : 'delete'; // 'delete' or 'deactivate'
    
    // Check if service exists (service_id is VARCHAR(4))
    $check_sql = "SELECT service_id, service_name FROM Service WHERE service_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $service_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $conn->close();
        ErrorHandler::handleNotFound('Service');
    }
    
    $service = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Check for existing future bookings
    $booking_sql = "SELECT COUNT(*) as booking_count 
                    FROM Booking_Service bs
                    INNER JOIN Booking b ON bs.booking_id = b.booking_id
                    WHERE bs.service_id = ? 
                    AND b.booking_date >= CURDATE()
                    AND b.status IN ('confirmed', 'completed')
                    AND bs.service_status IN ('confirmed', 'completed')";
    
    $booking_stmt = $conn->prepare($booking_sql);
    $booking_stmt->bind_param("s", $service_id);
    $booking_stmt->execute();
    $booking_result = $booking_stmt->get_result();
    $booking_data = $booking_result->fetch_assoc();
    $booking_count = (int)$booking_data['booking_count'];
    $booking_stmt->close();
    
    // If there are future bookings, return warning
    if ($booking_count > 0) {
        $conn->close();
        
        ErrorHandler::sendError(
            'HAS_FUTURE_BOOKINGS',
            "This service has {$booking_count} future booking(s). Please deactivate instead of deleting, or cancel the bookings first.",
            [
                'booking_count' => $booking_count,
                'service_name' => $service['service_name']
            ],
            409
        );
    }
    
    // Perform action based on request
    if ($action === 'deactivate') {
        // Deactivate service (set is_active to false)
        $sql = "UPDATE Service SET is_active = 0 WHERE service_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $service_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Service deactivated successfully',
                'service_id' => $service_id
            ]);
        } else {
            throw new Exception('Failed to deactivate service: ' . $stmt->error);
        }
    } else {
        // Delete service
        $sql = "DELETE FROM Service WHERE service_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $service_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Service deleted successfully',
                'service_id' => $service_id
            ]);
        } else {
            throw new Exception('Failed to delete service: ' . $stmt->error);
        }
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'deleting service');
}
