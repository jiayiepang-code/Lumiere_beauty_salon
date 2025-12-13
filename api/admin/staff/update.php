<?php
/**
 * Staff UPDATE API Endpoint
 * Handles staff account updates with image upload support
 */

header('Content-Type: application/json');

// Include required files
// Note: auth_check.php handles session start with proper secure configuration
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';
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

// Handle PUT request only
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only PUT requests are allowed', null, 405);
}

try {
    // Get data from FormData (for image upload support) or JSON
    $input = [];
    
    if (!empty($_POST)) {
        // FormData submission
        $input = $_POST;
    } else {
        // JSON submission
        $json_input = json_decode(file_get_contents('php://input'), true);
        if ($json_input !== null) {
            $input = $json_input;
        }
    }
    
    if (empty($input)) {
        ErrorHandler::sendError(ErrorHandler::INVALID_JSON, 'No data provided');
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
    
    $existing_staff = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Prepare update fields
    $update_fields = [];
    $update_values = [];
    $update_types = "";
    
    // Update phone if provided
    if (isset($input['phone']) && !empty($input['phone'])) {
        $phone = preg_replace('/[\s\-]/', '', trim($input['phone']));
        $phone_validation = Validator::phoneNumber($phone);
        if ($phone_validation !== null) {
            $conn->close();
            ErrorHandler::handleValidationError(['phone' => $phone_validation]);
        }
        
        // Check for duplicate phone (excluding current staff)
        $check_phone_sql = "SELECT staff_email FROM staff WHERE phone = ? AND staff_email != ?";
        $check_phone_stmt = $conn->prepare($check_phone_sql);
        $check_phone_stmt->bind_param("ss", $phone, $staff_email);
        $check_phone_stmt->execute();
        $check_phone_result = $check_phone_stmt->get_result();
        
        if ($check_phone_result->num_rows > 0) {
            $check_phone_stmt->close();
            $conn->close();
            ErrorHandler::handleDuplicateEntry('phone', 'A staff account with this phone number already exists');
        }
        $check_phone_stmt->close();
        
        $update_fields[] = "phone = ?";
        $update_values[] = $phone;
        $update_types .= "s";
    }
    
    // Update first_name if provided
    if (isset($input['first_name']) && !empty($input['first_name'])) {
        $first_name = trim($input['first_name']);
        if (strlen($first_name) > 50) {
            $conn->close();
            ErrorHandler::handleValidationError(['first_name' => 'First name must not exceed 50 characters']);
        }
        $update_fields[] = "first_name = ?";
        $update_values[] = $first_name;
        $update_types .= "s";
    }
    
    // Update last_name if provided
    if (isset($input['last_name']) && !empty($input['last_name'])) {
        $last_name = trim($input['last_name']);
        if (strlen($last_name) > 50) {
            $conn->close();
            ErrorHandler::handleValidationError(['last_name' => 'Last name must not exceed 50 characters']);
        }
        $update_fields[] = "last_name = ?";
        $update_values[] = $last_name;
        $update_types .= "s";
    }
    
    // Update bio if provided
    if (isset($input['bio'])) {
        $bio = trim($input['bio']);
        if (strlen($bio) > 500) {
            $conn->close();
            ErrorHandler::handleValidationError(['bio' => 'Bio must not exceed 500 characters']);
        }
        $update_fields[] = "bio = ?";
        $update_values[] = $bio ?: null;
        $update_types .= "s";
    }
    
    // Update role if provided
    if (isset($input['role']) && !empty($input['role'])) {
        $role = trim($input['role']);
        if (!in_array($role, ['staff', 'admin'])) {
            $conn->close();
            ErrorHandler::handleValidationError(['role' => 'Role must be either "staff" or "admin"']);
        }
        $update_fields[] = "role = ?";
        $update_values[] = $role;
        $update_types .= "s";
    }
    
    // Update password if provided
    if (isset($input['password']) && !empty($input['password'])) {
        $password = $input['password'];
        $password_errors = Validator::passwordStrength($password);
        if (!empty($password_errors)) {
            $conn->close();
            ErrorHandler::handleValidationError(['password' => implode('. ', $password_errors)]);
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_fields[] = "password = ?";
        $update_values[] = $hashed_password;
        $update_types .= "s";
    }
    
    // Update is_active if provided
    if (isset($input['is_active'])) {
        $is_active = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($is_active !== null) {
            $update_fields[] = "is_active = ?";
            $update_values[] = $is_active ? 1 : 0;
            $update_types .= "i";
        }
    }
    
    // Handle image upload if provided
    if (isset($_FILES['staff_image']) && $_FILES['staff_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../images/staff/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size_mb = 2;
        
        $upload_result = secureFileUpload($_FILES['staff_image'], $upload_dir, $allowed_types, $max_size_mb);
        
        if (!$upload_result['success']) {
            $conn->close();
            ErrorHandler::handleFileUploadError($upload_result['error']);
        }
        
        // Delete old image if exists
        if (!empty($existing_staff['staff_image'])) {
            $old_image_path = __DIR__ . '/../../..' . $existing_staff['staff_image'];
            if (file_exists($old_image_path)) {
                @unlink($old_image_path);
            }
        }
        
        // Store relative path for database
        $staff_image = '/images/staff/' . basename($upload_result['file_path']);
        $update_fields[] = "staff_image = ?";
        $update_values[] = $staff_image;
        $update_types .= "s";
    }
    
    // If no fields to update
    if (empty($update_fields)) {
        $conn->close();
        ErrorHandler::sendError(ErrorHandler::VALIDATION_ERROR, 'No fields to update');
    }
    
    // Build and execute UPDATE query
    $update_values[] = $staff_email; // For WHERE clause
    $update_types .= "s";
    
    $sql = "UPDATE staff SET " . implode(", ", $update_fields) . " WHERE staff_email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($update_types, ...$update_values);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Staff account updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update staff account: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'staff account update');
}
?>
