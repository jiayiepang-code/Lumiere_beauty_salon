<?php
// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);
session_start();

header('Content-Type: application/json');

// Include database connection
require_once '../../../php/connection.php';
require_once '../../../admin/includes/auth_check.php';

// Check authentication
if (!isAdminAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'AUTH_REQUIRED',
            'message' => 'Authentication required'
        ]
    ]);
    exit;
}

// Check session timeout
if (!checkSessionTimeout()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'SESSION_EXPIRED',
            'message' => 'Session has expired'
        ]
    ]);
    exit;
}

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Only POST requests are allowed'
        ]
    ]);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_JSON',
                'message' => 'Invalid JSON data'
            ]
        ]);
        exit;
    }
    
    // Validate CSRF token
    if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_CSRF_TOKEN',
                'message' => 'Invalid CSRF token'
            ]
        ]);
        exit;
    }
    
    // Validate required fields
    if (empty($input['staff_email'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Staff email is required',
                'details' => [
                    'staff_email' => 'Staff email is required'
                ]
            ]
        ]);
        exit;
    }
    
    if (!isset($input['is_active'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Active status is required',
                'details' => [
                    'is_active' => 'Active status is required'
                ]
            ]
        ]);
        exit;
    }
    
    $staff_email = trim($input['staff_email']);
    $is_active = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN);
    
    // Check if staff exists
    $check_sql = "SELECT staff_email, is_active FROM Staff WHERE staff_email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $staff_email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $conn->close();
        
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Staff member not found'
            ]
        ]);
        exit;
    }
    
    $staff_data = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // If deactivating, check for future bookings
    if (!$is_active && $staff_data['is_active']) {
        $future_bookings_sql = "SELECT COUNT(*) as booking_count 
                                FROM Booking_Service bs
                                INNER JOIN Booking b ON bs.booking_id = b.booking_id
                                WHERE bs.staff_email = ? 
                                AND b.booking_date >= CURDATE()
                                AND bs.service_status IN ('confirmed')";
        
        $future_stmt = $conn->prepare($future_bookings_sql);
        $future_stmt->bind_param("s", $staff_email);
        $future_stmt->execute();
        $future_result = $future_stmt->get_result();
        $future_data = $future_result->fetch_assoc();
        $future_stmt->close();
        
        if ($future_data['booking_count'] > 0) {
            $conn->close();
            
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'HAS_FUTURE_BOOKINGS',
                    'message' => 'Cannot deactivate staff member with future bookings',
                    'details' => [
                        'future_bookings' => $future_data['booking_count']
                    ]
                ],
                'warning' => true
            ]);
            exit;
        }
    }
    
    // Update is_active status
    $update_sql = "UPDATE Staff SET is_active = ? WHERE staff_email = ?";
    $update_stmt = $conn->prepare($update_sql);
    $active_value = $is_active ? 1 : 0;
    $update_stmt->bind_param("is", $active_value, $staff_email);
    
    if ($update_stmt->execute()) {
        $update_stmt->close();
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'staff_email' => $staff_email,
            'is_active' => $is_active,
            'message' => $is_active ? 'Staff account activated successfully' : 'Staff account deactivated successfully'
        ]);
    } else {
        throw new Exception('Failed to toggle staff active status: ' . $update_stmt->error);
    }
    
} catch (Exception $e) {
    // Log error
    error_log(json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $_SESSION['admin']['email'] ?? 'unknown',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]), 3, '../../../logs/admin_errors.log');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'DATABASE_ERROR',
            'message' => 'An error occurred while toggling staff active status'
        ]
    ]);
}
