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
 * Validate staff data using centralized Validator
 */
function validateStaffData($data, $is_update = false) {
    $rules = [
        'first_name' => [
            'required' => true,
            'length' => ['min' => null, 'max' => 50]
        ],
        'last_name' => [
            'required' => true,
            'length' => ['min' => null, 'max' => 50]
        ],
        'phone' => [
            'required' => true,
            'phone' => true
        ],
        'role' => [
            'required' => true,
            'enum' => ['values' => ['staff', 'admin']]
        ],
        'bio' => [
            'length' => ['min' => null, 'max' => 500]
        ]
    ];
    
    // Add email and password validation for create only
    if (!$is_update) {
        $rules['staff_email'] = [
            'required' => true,
            'email' => true,
            'length' => ['min' => null, 'max' => 100]
        ];
        $rules['password'] = [
            'required' => true,
            'password' => true
        ];
    }
    
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
    $validation_errors = validateStaffData($input);
    
    if (!empty($validation_errors)) {
        ErrorHandler::handleValidationError($validation_errors);
    }
    
    // Prepare data
    $staff_email = trim($input['staff_email']);
    $phone = preg_replace('/[\s\-]/', '', trim($input['phone']));
    $password = $input['password'];
    $first_name = trim($input['first_name']);
    $last_name = trim($input['last_name']);
    $bio = isset($input['bio']) ? trim($input['bio']) : null;
    $role = trim($input['role']);
    $staff_image = isset($input['staff_image']) ? trim($input['staff_image']) : null;
    
    // Check for duplicate email
    $check_email_sql = "SELECT staff_email FROM Staff WHERE staff_email = ?";
    $check_email_stmt = $conn->prepare($check_email_sql);
    $check_email_stmt->bind_param("s", $staff_email);
    $check_email_stmt->execute();
    $check_email_result = $check_email_stmt->get_result();
    
    if ($check_email_result->num_rows > 0) {
        $check_email_stmt->close();
        $conn->close();
        ErrorHandler::handleDuplicateEntry('staff_email', 'A staff account with this email already exists');
    }
    $check_email_stmt->close();
    
    // Check for duplicate phone
    $check_phone_sql = "SELECT staff_email FROM Staff WHERE phone = ?";
    $check_phone_stmt = $conn->prepare($check_phone_sql);
    $check_phone_stmt->bind_param("s", $phone);
    $check_phone_stmt->execute();
    $check_phone_result = $check_phone_stmt->get_result();
    
    if ($check_phone_result->num_rows > 0) {
        $check_phone_stmt->close();
        $conn->close();
        ErrorHandler::handleDuplicateEntry('phone', 'A staff account with this phone number already exists');
    }
    $check_phone_stmt->close();
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert new staff account
    $sql = "INSERT INTO Staff (staff_email, phone, password, first_name, last_name, 
                               bio, role, staff_image, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", 
        $staff_email,
        $phone,
        $hashed_password,
        $first_name,
        $last_name,
        $bio,
        $role,
        $staff_image
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'staff_email' => $staff_email,
            'message' => 'Staff account created successfully'
        ]);
    } else {
        throw new Exception('Failed to create staff account: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    ErrorHandler::handleDatabaseError($e, 'staff account creation');
}
