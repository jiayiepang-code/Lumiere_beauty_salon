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
    
    // Validate service_id
    if (!isset($input['service_id']) || empty($input['service_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Service ID is required',
                'details' => [
                    'service_id' => 'Service ID is required'
                ]
            ]
        ]);
        exit;
    }
    
    $service_id = $input['service_id'];
    
    // Get current status
    $check_sql = "SELECT service_id, is_active FROM Service WHERE service_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $service_id);
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
                'message' => 'Service not found'
            ]
        ]);
        exit;
    }
    
    $service = $check_result->fetch_assoc();
    $current_status = (bool)$service['is_active'];
    $new_status = !$current_status;
    $check_stmt->close();
    
    // Toggle active status
    $sql = "UPDATE Service SET is_active = ? WHERE service_id = ?";
    $stmt = $conn->prepare($sql);
    $new_status_int = $new_status ? 1 : 0;
    $stmt->bind_param("ii", $new_status_int, $service_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $new_status ? 'Service activated successfully' : 'Service deactivated successfully',
            'service_id' => $service_id,
            'is_active' => $new_status
        ]);
    } else {
        throw new Exception('Failed to toggle service status: ' . $stmt->error);
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
            'message' => 'An error occurred while toggling service status'
        ]
    ]);
}
