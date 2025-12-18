<?php
/**
 * Staff CREATE API Endpoint
 * Handles staff account creation with image upload support
 */

// Suppress PHP warnings/errors from being displayed in JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// Start output buffering to prevent any stray output from corrupting JSON
ob_start();

header('Content-Type: application/json');

// Helper function to safely write debug logs
function safeLogDebug($data) {
    $log_dir = __DIR__ . '/../../.cursor';
    $log_file = $log_dir . '/debug.log';
    
    // Create directory if it doesn't exist
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    
    // Use @ to suppress any warnings if write fails
    @file_put_contents($log_file, json_encode($data) . "\n", FILE_APPEND);
}

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

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only POST requests are allowed', null, 405);
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
    $required_fields = ['staff_email', 'phone', 'first_name', 'last_name', 'password', 'role'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || trim($input[$field]) === '') {
            ErrorHandler::handleValidationError([$field => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        }
    }
    
    // Prepare data
    $staff_email = trim($input['staff_email']);
    $phone = preg_replace('/[\s\-\+]/', '', trim($input['phone']));
    $password = trim($input['password']);
    $first_name = trim($input['first_name']);
    $last_name = trim($input['last_name']);
    $bio = isset($input['bio']) ? trim($input['bio']) : null;
    $role = trim($input['role']);
    $staff_image = null;
    
    // Validate email format
    if (!filter_var($staff_email, FILTER_VALIDATE_EMAIL)) {
        ErrorHandler::handleValidationError(['staff_email' => 'Invalid email format']);
    }
    
    // Validate phone format
    $phone_validation = Validator::phoneNumber($phone);
    if ($phone_validation !== null) {
        ErrorHandler::handleValidationError(['phone' => $phone_validation]);
    }
    
    // Validate password strength
    $password_errors = Validator::passwordStrength($password);
    if (!empty($password_errors)) {
        ErrorHandler::handleValidationError(['password' => implode('. ', $password_errors)]);
    }
    
    // Validate role
    if (!in_array($role, ['staff', 'admin'])) {
        ErrorHandler::handleValidationError(['role' => 'Role must be either "staff" or "admin"']);
    }
    
    // Validate name lengths
    if (strlen($first_name) > 50 || strlen($last_name) > 50) {
        ErrorHandler::handleValidationError(['name' => 'First name and last name must not exceed 50 characters']);
    }
    
    // Validate bio length
    if ($bio && strlen($bio) > 500) {
        ErrorHandler::handleValidationError(['bio' => 'Bio must not exceed 500 characters']);
    }
    
    // Get database connection
    $conn = getDBConnection();
    
    // Check for duplicate email
    $check_email_sql = "SELECT staff_email FROM staff WHERE staff_email = ?";
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
    $check_phone_sql = "SELECT staff_email FROM staff WHERE phone = ?";
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
    
    // Handle image upload if provided
    if (isset($_FILES['staff_image']) && $_FILES['staff_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../images/staff/';
        
        // Ensure upload directory exists and is writable
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        if (!is_writable($upload_dir)) {
            $conn->close();
            ErrorHandler::handleFileUploadError('Upload directory is not writable');
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size_mb = 2;
        
        $upload_result = secureFileUpload($_FILES['staff_image'], $upload_dir, $allowed_types, $max_size_mb);
        
        if (!$upload_result['success']) {
            $conn->close();
            ErrorHandler::handleFileUploadError($upload_result['error']);
        }
        
        // Store relative path for database
        $staff_image = '/images/staff/' . basename($upload_result['file_path']);
    }
    
    // Get is_active status (default to 1 if not provided)
    $is_active = 1; // Default to active
    if (isset($input['is_active'])) {
        // Handle string "1"/"0" from FormData or boolean values
        $is_active_value = $input['is_active'];
        if ($is_active_value === '1' || $is_active_value === 1 || $is_active_value === true || $is_active_value === 'true') {
            $is_active = 1;
        } elseif ($is_active_value === '0' || $is_active_value === 0 || $is_active_value === false || $is_active_value === 'false') {
            $is_active = 0;
        }
        // Otherwise keep default (1)
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new staff account (removed bio column as it doesn't exist in database)
    $sql = "INSERT INTO staff (staff_email, phone, password, first_name, last_name, 
                               role, staff_image, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    // #region agent log
    safeLogDebug([
        'sessionId' => 'debug-session',
        'runId' => 'run1',
        'hypothesisId' => 'C,D',
        'location' => 'api/admin/staff/create.php:' . __LINE__,
        'message' => 'Preparing database insert',
        'data' => ['sql' => $sql, 'staff_email' => $staff_email, 'first_name' => $first_name, 'last_name' => $last_name],
        'timestamp' => time() * 1000
    ]);
    // #endregion
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // #region agent log
        safeLogDebug([
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'C,D',
            'location' => 'api/admin/staff/create.php:' . __LINE__,
            'message' => 'Failed to prepare statement',
            'data' => ['error' => $conn->error],
            'timestamp' => time() * 1000
        ]);
        // #endregion
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    // Handle NULL values properly
    $staff_image_value = ($staff_image === null || $staff_image === '') ? null : $staff_image;
    
    $stmt->bind_param("sssssssi", 
        $staff_email,
        $phone,
        $hashed_password,
        $first_name,
        $last_name,
        $role,
        $staff_image_value,
        $is_active
    );
    
    if ($stmt->execute()) {
        // #region agent log
        safeLogDebug([
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'C,D',
            'location' => 'api/admin/staff/create.php:' . __LINE__,
            'message' => 'Database insert successful',
            'data' => ['staff_email' => $staff_email],
            'timestamp' => time() * 1000
        ]);
        // #endregion
        
        $stmt->close();
        $conn->close();
        
        // Clean any output buffer before sending JSON
        ob_clean();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'staff_email' => $staff_email,
            'message' => 'Staff account created successfully'
        ]);
        ob_end_flush();
    } else {
        // #region agent log
        safeLogDebug([
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'C,D',
            'location' => 'api/admin/staff/create.php:' . __LINE__,
            'message' => 'Database insert failed',
            'data' => ['error' => $stmt->error, 'errno' => $stmt->errno],
            'timestamp' => time() * 1000
        ]);
        // #endregion
        throw new Exception('Failed to create staff account: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'staff account creation');
}
?>
