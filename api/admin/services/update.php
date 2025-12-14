<?php
// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
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

// Handle PUT request only
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only PUT requests are allowed', null, 405);
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
    } elseif (!is_numeric($data['current_duration_minutes']) || $data['current_duration_minutes'] < 5 || $data['current_duration_minutes'] > 480) {
        $errors['current_duration_minutes'] = 'Duration must be between 5 and 480 minutes';
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
    
    // Validate input data
    $validation_errors = validateServiceData($input);
    
    if (!empty($validation_errors)) {
        ErrorHandler::handleValidationError($validation_errors);
    }
    
    // Prepare data
    $service_id = $input['service_id'];
    $service_category = trim($input['service_category']);
    $sub_category = isset($input['sub_category']) && trim($input['sub_category']) !== '' ? trim($input['sub_category']) : null;
    $service_name = trim($input['service_name']);
    $current_duration_minutes = (int)$input['current_duration_minutes'];
    $current_price = (float)$input['current_price'];
    $description = isset($input['description']) && trim($input['description']) !== '' ? trim($input['description']) : null;
    $service_image = isset($input['service_image']) && trim($input['service_image']) !== '' ? trim($input['service_image']) : null;
    $default_cleanup_minutes = (int)$input['default_cleanup_minutes'];
    $is_active = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;
    
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
    
    // Check for duplicate service name within the same category (excluding current service, case-insensitive)
    $dup_sql = "SELECT service_id FROM Service 
                WHERE LOWER(service_category) = LOWER(?) AND LOWER(service_name) = LOWER(?) AND service_id != ?";
    $dup_stmt = $conn->prepare($dup_sql);
    $dup_stmt->bind_param("sss", $service_category, $service_name, $service_id);
    $dup_stmt->execute();
    $dup_result = $dup_stmt->get_result();
    
    if ($dup_result->num_rows > 0) {
        $dup_stmt->close();
        $conn->close();
        ErrorHandler::handleDuplicateEntry('service_name', 'A service with this name already exists in this category');
    }
    $dup_stmt->close();
    
    // Update service
    $sql = "UPDATE Service 
            SET service_category = ?, sub_category = ?, service_name = ?, 
                current_duration_minutes = ?, current_price = ?, description = ?, 
                service_image = ?, default_cleanup_minutes = ?, is_active = ?
            WHERE service_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssidssiis", 
        $service_category,
        $sub_category,
        $service_name,
        $current_duration_minutes,
        $current_price,
        $description,
        $service_image,
        $default_cleanup_minutes,
        $is_active,
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
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'updating service');
}
