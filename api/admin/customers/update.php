<?php
/**
 * Customer UPDATE API Endpoint
 * Handles customer account updates (name, phone)
 */

// Include required files first
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';

header('Content-Type: application/json');
require_once '../../../admin/includes/error_handler.php';
require_once '../../../admin/includes/validator.php';
require_once '../../../admin/includes/security_utils.php';
require_once '../includes/csrf_validation.php';

// Check authentication
if (!isAdminAuthenticated()) {
    ErrorHandler::handleAuthError();
}

// Check session timeout
if (!checkSessionTimeout()) {
    ErrorHandler::sendError(ErrorHandler::SESSION_EXPIRED, 'Session has expired', null, 401);
}

// Handle PUT or POST request
if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only PUT or POST requests are allowed', null, 405);
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
    $check_sql = "SELECT customer_email FROM customer WHERE customer_email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $customer_email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        $conn->close();
        ErrorHandler::handleNotFound('Customer');
    }
    
    $check_stmt->close();
    
    // Prepare update fields
    $update_fields = [];
    $update_values = [];
    $update_types = "";
    
    // Update first_name if provided
    if (isset($input['first_name']) && !empty($input['first_name'])) {
        $first_name = trim($input['first_name']);
        
        // Validate name: not empty, reasonable length (2-100 chars), alphanumeric + spaces/hyphens/apostrophes
        $name_error = null;
        if (strlen($first_name) < 2) {
            $name_error = 'First name must be at least 2 characters';
        } elseif (strlen($first_name) > 100) {
            $name_error = 'First name must not exceed 100 characters';
        } elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $first_name)) {
            $name_error = 'First name can only contain letters, spaces, hyphens, and apostrophes';
        }
        
        if ($name_error !== null) {
            $conn->close();
            ErrorHandler::handleValidationError(['first_name' => $name_error]);
        }
        $update_fields[] = "first_name = ?";
        $update_values[] = $first_name;
        $update_types .= "s";
    }
    
    // Update last_name if provided
    if (isset($input['last_name']) && !empty($input['last_name'])) {
        $last_name = trim($input['last_name']);
        
        // Validate name: not empty, reasonable length (2-100 chars), alphanumeric + spaces/hyphens/apostrophes
        $name_error = null;
        if (strlen($last_name) < 2) {
            $name_error = 'Last name must be at least 2 characters';
        } elseif (strlen($last_name) > 100) {
            $name_error = 'Last name must not exceed 100 characters';
        } elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $last_name)) {
            $name_error = 'Last name can only contain letters, spaces, hyphens, and apostrophes';
        }
        
        if ($name_error !== null) {
            $conn->close();
            ErrorHandler::handleValidationError(['last_name' => $name_error]);
        }
        $update_fields[] = "last_name = ?";
        $update_values[] = $last_name;
        $update_types .= "s";
    }
    
    // Update phone if provided
    if (isset($input['phone']) && !empty($input['phone'])) {
        // Use sanitizePhone utility function to normalize to +60 format
        require_once '../../../config/utils.php';
        $phone = sanitizePhone($input['phone']);
        
        // Validate phone format
        if (!isValidMalaysianPhone($phone)) {
            $conn->close();
            ErrorHandler::handleValidationError(['phone' => 'Invalid Malaysian phone number format']);
        }
        
        // Check for duplicate phone (excluding current customer)
        $check_phone_sql = "SELECT customer_email FROM customer WHERE phone = ? AND customer_email != ?";
        $check_phone_stmt = $conn->prepare($check_phone_sql);
        $check_phone_stmt->bind_param("ss", $phone, $customer_email);
        $check_phone_stmt->execute();
        $phone_result = $check_phone_stmt->get_result();
        
        if ($phone_result->num_rows > 0) {
            $check_phone_stmt->close();
            $conn->close();
            ErrorHandler::handleValidationError(['phone' => 'This phone number is already registered']);
        }
        $check_phone_stmt->close();
        
        $update_fields[] = "phone = ?";
        $update_values[] = $phone;
        $update_types .= "s";
    }
    
    // Check if there are any fields to update
    if (empty($update_fields)) {
        $conn->close();
        ErrorHandler::sendError(ErrorHandler::VALIDATION_ERROR, 'No fields to update');
    }
    
    // Build and execute UPDATE query
    $sql = "UPDATE customer SET " . implode(", ", $update_fields) . " WHERE customer_email = ?";
    $update_values[] = $customer_email;
    $update_types .= "s";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        $conn->close();
        throw new Exception("Failed to prepare update statement: " . $conn->error);
    }
    
    // Bind parameters dynamically
    $stmt->bind_param($update_types, ...$update_values);
    
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        throw new Exception("Failed to update customer: " . $stmt->error);
    }
    
    $stmt->close();
    
    // Fetch updated customer data
    $fetch_sql = "SELECT customer_email, first_name, last_name, phone, created_at FROM customer WHERE customer_email = ?";
    $fetch_stmt = $conn->prepare($fetch_sql);
    $fetch_stmt->bind_param("s", $customer_email);
    $fetch_stmt->execute();
    $result = $fetch_stmt->get_result();
    $updated_customer = $result->fetch_assoc();
    $fetch_stmt->close();
    $conn->close();
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Customer updated successfully',
        'customer' => [
            'customer_email' => $updated_customer['customer_email'],
            'first_name' => $updated_customer['first_name'],
            'last_name' => $updated_customer['last_name'],
            'phone' => $updated_customer['phone'],
            'created_at' => $updated_customer['created_at']
        ]
    ]);

} catch (Exception $e) {
    error_log("Customer update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'UPDATE_FAILED',
            'message' => 'Failed to update customer: ' . $e->getMessage()
        ]
    ]);
}
?>
