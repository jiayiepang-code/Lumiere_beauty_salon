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
 * Validate phone number format (Malaysia)
 */
function validatePhoneFormat($phone) {
    // Remove spaces and dashes
    $phone = preg_replace('/[\s\-]/', '', $phone);
    
    // Check for Malaysia format: 01X-XXXXXXX or 60XXXXXXXXX
    if (preg_match('/^(01[0-9]{8,9})$/', $phone)) {
        return true;
    }
    if (preg_match('/^(60[0-9]{9,10})$/', $phone)) {
        return true;
    }
    
    return false;
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    return $errors;
}

/**
 * Validate staff data for update
 */
function validateStaffUpdateData($data) {
    $errors = [];
    
    // staff_email is required to identify the record
    if (empty($data['staff_email'])) {
        $errors['staff_email'] = 'Email is required to identify the staff member';
    } elseif (!filter_var($data['staff_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['staff_email'] = 'Invalid email format';
    }
    
    // Validate phone if provided
    if (isset($data['phone']) && !empty($data['phone'])) {
        if (!validatePhoneFormat($data['phone'])) {
            $errors['phone'] = 'Invalid phone format. Use Malaysia format (01X-XXXXXXX or 60XXXXXXXXX)';
        }
    }
    
    // Validate password if provided (optional for update)
    if (isset($data['password']) && !empty($data['password'])) {
        $password_errors = validatePasswordStrength($data['password']);
        if (!empty($password_errors)) {
            $errors['password'] = implode('. ', $password_errors);
        }
    }
    
    // Validate first_name if provided
    if (isset($data['first_name'])) {
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'First name cannot be empty';
        } elseif (strlen($data['first_name']) > 50) {
            $errors['first_name'] = 'First name must not exceed 50 characters';
        }
    }
    
    // Validate last_name if provided
    if (isset($data['last_name'])) {
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Last name cannot be empty';
        } elseif (strlen($data['last_name']) > 50) {
            $errors['last_name'] = 'Last name must not exceed 50 characters';
        }
    }
    
    // Validate role if provided
    if (isset($data['role']) && !empty($data['role'])) {
        if (!in_array($data['role'], ['staff', 'admin'])) {
            $errors['role'] = 'Role must be either "staff" or "admin"';
        }
    }
    
    // Validate bio if provided (optional but has max length)
    if (isset($data['bio']) && strlen($data['bio']) > 500) {
        $errors['bio'] = 'Bio must not exceed 500 characters';
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
    $validation_errors = validateStaffUpdateData($input);
    
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
    
    $staff_email = trim($input['staff_email']);
    
    // Check if staff exists
    $check_sql = "SELECT staff_email FROM Staff WHERE staff_email = ?";
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
    $check_stmt->close();
    
    // Build dynamic update query
    $update_fields = [];
    $params = [];
    $types = "";
    
    // Phone
    if (isset($input['phone']) && !empty($input['phone'])) {
        $phone = preg_replace('/[\s\-]/', '', trim($input['phone']));
        
        // Check for duplicate phone (excluding current staff)
        $check_phone_sql = "SELECT staff_email FROM Staff WHERE phone = ? AND staff_email != ?";
        $check_phone_stmt = $conn->prepare($check_phone_sql);
        $check_phone_stmt->bind_param("ss", $phone, $staff_email);
        $check_phone_stmt->execute();
        $check_phone_result = $check_phone_stmt->get_result();
        
        if ($check_phone_result->num_rows > 0) {
            $check_phone_stmt->close();
            $conn->close();
            
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_ENTRY',
                    'message' => 'Phone number is already in use by another staff member',
                    'details' => [
                        'phone' => 'Phone number is already in use'
                    ]
                ]
            ]);
            exit;
        }
        $check_phone_stmt->close();
        
        $update_fields[] = "phone = ?";
        $params[] = $phone;
        $types .= "s";
    }
    
    // Password (optional)
    if (isset($input['password']) && !empty($input['password'])) {
        $hashed_password = password_hash($input['password'], PASSWORD_BCRYPT);
        $update_fields[] = "password = ?";
        $params[] = $hashed_password;
        $types .= "s";
    }
    
    // First name
    if (isset($input['first_name'])) {
        $update_fields[] = "first_name = ?";
        $params[] = trim($input['first_name']);
        $types .= "s";
    }
    
    // Last name
    if (isset($input['last_name'])) {
        $update_fields[] = "last_name = ?";
        $params[] = trim($input['last_name']);
        $types .= "s";
    }
    
    // Bio
    if (isset($input['bio'])) {
        $update_fields[] = "bio = ?";
        $params[] = trim($input['bio']);
        $types .= "s";
    }
    
    // Role
    if (isset($input['role']) && !empty($input['role'])) {
        $update_fields[] = "role = ?";
        $params[] = trim($input['role']);
        $types .= "s";
    }
    
    // Staff image
    if (isset($input['staff_image'])) {
        $update_fields[] = "staff_image = ?";
        $params[] = trim($input['staff_image']);
        $types .= "s";
    }
    
    // Check if there are fields to update
    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'NO_UPDATES',
                'message' => 'No fields to update'
            ]
        ]);
        exit;
    }
    
    // Add staff_email to params for WHERE clause
    $params[] = $staff_email;
    $types .= "s";
    
    // Build and execute update query
    $sql = "UPDATE Staff SET " . implode(", ", $update_fields) . " WHERE staff_email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'staff_email' => $staff_email,
            'message' => 'Staff account updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update staff account: ' . $stmt->error);
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
            'message' => 'An error occurred while updating the staff account'
        ]
    ]);
}
