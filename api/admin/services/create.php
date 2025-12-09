<?php
// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);
session_start();

header('Content-Type: application/json');

// Include required files
require_once '../../../php/connection.php';
require_once '../../../admin/includes/auth_check.php';
require_once '../../../admin/includes/error_handler.php';
require_once '../../../admin/includes/validator.php';

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

/**
 * Validate service data using centralized Validator
 */
function validateServiceData($data) {
    $rules = [
        'service_category' => [
            'required' => true,
            'length' => ['min' => null, 'max' => 50]
        ],
        'service_name' => [
            'required' => true,
            'length' => ['min' => null, 'max' => 100]
        ],
        'current_duration_minutes' => [
            'required' => true,
            'range' => ['min' => 15, 'max' => 480]
        ],
        'current_price' => [
            'required' => true,
            'range' => ['min' => 0.01, 'max' => 99999999.99]
        ],
        'default_cleanup_minutes' => [
            'required' => true,
            'range' => ['min' => 0, 'max' => 60]
        ],
        'sub_category' => [
            'length' => ['min' => null, 'max' => 50]
        ]
    ];
    
    $validation = Validator::validate($data, $rules);
    return $validation['errors'];
}

try {
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
    $service_category = trim($input['service_category']);
    $sub_category = isset($input['sub_category']) ? trim($input['sub_category']) : null;
    $service_name = trim($input['service_name']);
    $current_duration_minutes = (int)$input['current_duration_minutes'];
    $current_price = (float)$input['current_price'];
    $description = isset($input['description']) ? trim($input['description']) : null;
    $service_image = isset($input['service_image']) ? trim($input['service_image']) : null;
    $default_cleanup_minutes = (int)$input['default_cleanup_minutes'];
    
    // Check for duplicate service name within the same category
    $check_sql = "SELECT service_id FROM Service 
                  WHERE service_category = ? AND service_name = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $service_category, $service_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $check_stmt->close();
        $conn->close();
        ErrorHandler::handleDuplicateEntry('service_name', 'A service with this name already exists in this category');
    }
    $check_stmt->close();
    
    // Insert new service
    $sql = "INSERT INTO Service (service_category, sub_category, service_name, 
                                 current_duration_minutes, current_price, description, 
                                 service_image, default_cleanup_minutes, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssidssi", 
        $service_category,
        $sub_category,
        $service_name,
        $current_duration_minutes,
        $current_price,
        $description,
        $service_image,
        $default_cleanup_minutes
    );
    
    if ($stmt->execute()) {
        $service_id = $conn->insert_id;
        $stmt->close();
        $conn->close();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'service_id' => $service_id,
            'message' => 'Service created successfully'
        ]);
    } else {
        throw new Exception('Failed to create service: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    ErrorHandler::handleDatabaseError($e, 'service creation');
}
