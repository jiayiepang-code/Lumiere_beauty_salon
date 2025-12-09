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

// Handle PUT request only
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Only PUT requests are allowed'
        ]
    ]);
    exit;
}

/**
 * Validate service data
 */
function validateServiceData($data) {
    $errors = [];
    
    // Validate service_id
    if (empty($data['service_id'])) {
        $errors['service_id'] = 'Service ID is required';
    }
    
    // Validate service_category
    if (empty($data['service_category'])) {
        $errors['service_category'] = 'Service category is required';
    } elseif (strlen($data['service_category']) > 50) {
        $errors['service_category'] = 'Service category must not exceed 50 characters';
    }
    
    // Validate service_name
    if (empty($data['service_name'])) {
        $errors['service_name'] = 'Service name is required';
    } elseif (strlen($data['service_name']) > 100) {
        $errors['service_name'] = 'Service name must not exceed 100 characters';
    }
    
    // Validate current_duration_minutes
    if (!isset($data['current_duration_minutes']) || $data['current_duration_minutes'] === '') {
        $errors['current_duration_minutes'] = 'Duration is required';
    } elseif (!is_numeric($data['current_duration_minutes']) || $data['current_duration_minutes'] < 15 || $data['current_duration_minutes'] > 480) {
        $errors['current_duration_minutes'] = 'Duration must be between 15 and 480 minutes';
    }
    
    // Validate current_price
    if (!isset($data['current_price']) || $data['current_price'] === '') {
        $errors['current_price'] = 'Price is required';
    } elseif (!is_numeric($data['current_price']) || $data['current_price'] <= 0) {
        $errors['current_price'] = 'Price must be greater than 0';
    } elseif ($data['current_price'] > 99999999.99) {
        $errors['current_price'] = 'Price exceeds maximum allowed value';
    }
    
    // Validate default_cleanup_minutes
    if (!isset($data['default_cleanup_minutes']) || $data['default_cleanup_minutes'] === '') {
        $errors['default_cleanup_minutes'] = 'Cleanup time is required';
    } elseif (!is_numeric($data['default_cleanup_minutes']) || $data['default_cleanup_minutes'] < 0 || $data['default_cleanup_minutes'] > 60) {
        $errors['default_cleanup_minutes'] = 'Cleanup time must be between 0 and 60 minutes';
    }
    
    // Validate sub_category (optional but has max length)
    if (isset($data['sub_category']) && strlen($data['sub_category']) > 50) {
        $errors['sub_category'] = 'Sub-category must not exceed 50 characters';
    }
    
    return $errors;
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
    
    // Validate input data
    $validation_errors = validateServiceData($input);
    
    if (!empty($validation_errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Invalid input data',
                'details' => $validation_errors
            ]
        ]);
        exit;
    }
    
    // Prepare data
    $service_id = $input['service_id'];
    $service_category = trim($input['service_category']);
    $sub_category = isset($input['sub_category']) ? trim($input['sub_category']) : null;
    $service_name = trim($input['service_name']);
    $current_duration_minutes = (int)$input['current_duration_minutes'];
    $current_price = (float)$input['current_price'];
    $description = isset($input['description']) ? trim($input['description']) : null;
    $service_image = isset($input['service_image']) ? trim($input['service_image']) : null;
    $default_cleanup_minutes = (int)$input['default_cleanup_minutes'];
    
    // Check if service exists
    $check_sql = "SELECT service_id FROM Service WHERE service_id = ?";
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
    $check_stmt->close();
    
    // Check for duplicate service name within the same category (excluding current service)
    $dup_sql = "SELECT service_id FROM Service 
                WHERE service_category = ? AND service_name = ? AND service_id != ?";
    $dup_stmt = $conn->prepare($dup_sql);
    $dup_stmt->bind_param("ssi", $service_category, $service_name, $service_id);
    $dup_stmt->execute();
    $dup_result = $dup_stmt->get_result();
    
    if ($dup_result->num_rows > 0) {
        $dup_stmt->close();
        $conn->close();
        
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'DUPLICATE_ENTRY',
                'message' => 'A service with this name already exists in this category',
                'details' => [
                    'service_name' => 'Service name must be unique within the category'
                ]
            ]
        ]);
        exit;
    }
    $dup_stmt->close();
    
    // Update service
    $sql = "UPDATE Service 
            SET service_category = ?, sub_category = ?, service_name = ?, 
                current_duration_minutes = ?, current_price = ?, description = ?, 
                service_image = ?, default_cleanup_minutes = ?
            WHERE service_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssidssii", 
        $service_category,
        $sub_category,
        $service_name,
        $current_duration_minutes,
        $current_price,
        $description,
        $service_image,
        $default_cleanup_minutes,
        $service_id
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'service_id' => $service_id,
            'message' => 'Service updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update service: ' . $stmt->error);
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
            'message' => 'An error occurred while updating the service'
        ]
    ]);
}
