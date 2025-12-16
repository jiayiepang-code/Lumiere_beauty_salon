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
require_once '../../../admin/includes/validator.php';

// Check authentication
if (!isAdminAuthenticated()) {
    ErrorHandler::handleAuthError();
}

// Check session timeout
if (!checkSessionTimeout()) {
    ErrorHandler::sendError(ErrorHandler::SESSION_EXPIRED, 'Session has expired', null, 401);
}

/**
 * Validate CSRF token
 * 
 * @param string $token Token to validate
 * @return bool True if valid
 */
function validateCSRFToken($token) {
    if (!isAdminAuthenticated()) {
        return false;
    }
    $session_token = $_SESSION['admin']['csrf_token'] ?? '';
    return !empty($token) && hash_equals($session_token, $token);
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Validate service data using centralized Validator
 */
function validateServiceData($data, $isUpdate = false) {
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
            'range' => ['min' => 5, 'max' => 480]
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
    
    // For update, require service_id
    if ($isUpdate) {
        $rules['service_id'] = [
            'required' => true
        ];
    }
    
    $validation = Validator::validate($data, $rules);
    return $validation['errors'];
}

try {
    // Get database connection
    $conn = getDBConnection();
    
    // Get JSON input for POST, PUT, DELETE
    $input = null;
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($input === null && $method !== 'DELETE') {
            ErrorHandler::sendError(ErrorHandler::INVALID_JSON, 'Invalid JSON data');
        }
    }
    
    // Validate CSRF token for POST, PUT, DELETE
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
            ErrorHandler::sendError(ErrorHandler::INVALID_CSRF_TOKEN, 'Invalid CSRF token', null, 403);
        }
    }
    
    switch ($method) {
        case 'POST':
            // Create new service
            // Validate input data
            $validation_errors = validateServiceData($input, false);
            
            if (!empty($validation_errors)) {
                ErrorHandler::handleValidationError($validation_errors);
            }
            
            // Prepare data
            $service_category = trim($input['service_category']);
            $sub_category = isset($input['sub_category']) && trim($input['sub_category']) !== '' ? trim($input['sub_category']) : null;
            $service_name = trim($input['service_name']);
            $current_duration_minutes = (int)$input['current_duration_minutes'];
            $current_price = (float)$input['current_price'];
            $description = isset($input['description']) && trim($input['description']) !== '' ? trim($input['description']) : null;
            $service_image = isset($input['service_image']) && trim($input['service_image']) !== '' ? trim($input['service_image']) : null;
            $default_cleanup_minutes = (int)$input['default_cleanup_minutes'];
            $is_active = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;
            
            // Check for duplicate service name within the same category (case-insensitive)
            $check_sql = "SELECT service_id FROM Service 
                          WHERE LOWER(service_category) = LOWER(?) AND LOWER(service_name) = LOWER(?)";
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
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
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
                $is_active
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
            break;
            
        case 'PUT':
            // Update existing service
            // Validate input data
            $validation_errors = validateServiceData($input, true);
            
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
            break;
            
        case 'DELETE':
            // Delete service
            // Validate service_id
            if (!isset($input['service_id']) || empty($input['service_id'])) {
                ErrorHandler::sendError(ErrorHandler::VALIDATION_ERROR, 'Service ID is required', ['service_id' => 'Service ID is required'], 400);
            }
            
            // Check if password re-authentication is valid (required for delete)
            if (!isset($_SESSION['reauth_ok_until']) || time() > $_SESSION['reauth_ok_until']) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'REAUTH_REQUIRED',
                        'message' => 'Please confirm your password to proceed with deletion.'
                    ]
                ]);
                $conn->close();
                exit;
            }
            
            $service_id = $input['service_id'];
            
            // Check if service exists (service_id is VARCHAR(4))
            $check_sql = "SELECT service_id, service_name FROM Service WHERE service_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $service_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                $check_stmt->close();
                $conn->close();
                ErrorHandler::handleNotFound('Service');
            }
            
            $service = $check_result->fetch_assoc();
            $check_stmt->close();
            
            // Check for existing future bookings
            $booking_sql = "SELECT COUNT(*) as booking_count 
                            FROM Booking_Service bs
                            INNER JOIN Booking b ON bs.booking_id = b.booking_id
                            WHERE bs.service_id = ? 
                            AND b.booking_date >= CURDATE()
                            AND b.status IN ('confirmed', 'completed')
                            AND bs.service_status IN ('confirmed', 'completed')";
            
            $booking_stmt = $conn->prepare($booking_sql);
            $booking_stmt->bind_param("s", $service_id);
            $booking_stmt->execute();
            $booking_result = $booking_stmt->get_result();
            $booking_data = $booking_result->fetch_assoc();
            $booking_count = (int)$booking_data['booking_count'];
            $booking_stmt->close();
            
            // If there are future bookings, return error
            if ($booking_count > 0) {
                $conn->close();
                
                ErrorHandler::sendError(
                    'HAS_FUTURE_BOOKINGS',
                    "This service has {$booking_count} future booking(s). Please deactivate instead of deleting, or cancel the bookings first.",
                    [
                        'booking_count' => $booking_count,
                        'service_name' => $service['service_name']
                    ],
                    409
                );
            }
            
            // Delete service
            $sql = "DELETE FROM Service WHERE service_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $service_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                
                // Clear reauth flag after successful delete
                unset($_SESSION['reauth_ok_until']);
                unset($_SESSION['reauth_action']);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'service_id' => $service_id,
                    'message' => 'Service deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete service: ' . $stmt->error);
            }
            break;
            
        default:
            ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Method not allowed', null, 405);
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'service operation');
}
