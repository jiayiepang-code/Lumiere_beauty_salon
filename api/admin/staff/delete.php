<?php
/**
 * Staff DELETE API Endpoint
 * Handles staff account deletion (soft delete by setting is_active = 0)
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
    if (empty($input['staff_email'])) {
        ErrorHandler::handleValidationError(['staff_email' => 'Email is required']);
    }
    
    $staff_email = trim($input['staff_email']);
    
    // Get database connection
    $conn = getDBConnection();
    
    // Check if staff exists
    $check_sql = "SELECT staff_email, staff_image FROM staff WHERE staff_email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $staff_email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $conn->close();
        ErrorHandler::handleNotFound('Staff member');
    }
    
    $staff = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Check for foreign key constraints (bookings, schedules)
    // Check if staff has any bookings
    $check_bookings_sql = "SELECT COUNT(*) as count FROM booking_service WHERE staff_email = ?";
    $check_bookings_stmt = $conn->prepare($check_bookings_sql);
    $check_bookings_stmt->bind_param("s", $staff_email);
    $check_bookings_stmt->execute();
    $bookings_result = $check_bookings_stmt->get_result();
    $bookings_count = $bookings_result->fetch_assoc()['count'];
    $check_bookings_stmt->close();
    
    // Check if staff has any schedules
    $check_schedules_sql = "SELECT COUNT(*) as count FROM staff_schedule WHERE staff_email = ?";
    $check_schedules_stmt = $conn->prepare($check_schedules_sql);
    $check_schedules_stmt->bind_param("s", $staff_email);
    $check_schedules_stmt->execute();
    $schedules_result = $check_schedules_stmt->get_result();
    $schedules_count = $schedules_result->fetch_assoc()['count'];
    $check_schedules_stmt->close();
    
    // If staff has bookings or schedules, use soft delete (set is_active = 0)
    // Otherwise, hard delete
    if ($bookings_count > 0 || $schedules_count > 0) {
        // Soft delete
        $sql = "UPDATE staff SET is_active = 0 WHERE staff_email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $staff_email);
        
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Staff account deactivated successfully (has existing bookings/schedules)'
            ]);
        } else {
            throw new Exception('Failed to deactivate staff account: ' . $stmt->error);
        }
    } else {
        // Hard delete - delete image file first
        if (!empty($staff['staff_image'])) {
            $image_path = __DIR__ . '/../../..' . $staff['staff_image'];
            if (file_exists($image_path)) {
                @unlink($image_path);
            }
        }
        
        // Delete staff account
        $sql = "DELETE FROM staff WHERE staff_email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $staff_email);
        
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Staff account deleted successfully'
            ]);
        } else {
            throw new Exception('Failed to delete staff account: ' . $stmt->error);
        }
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'staff account deletion');
}
?>
