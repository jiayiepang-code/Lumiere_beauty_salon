<?php
// Start output buffering to catch any accidental output
ob_start();

// Disable error display (errors will be logged, not printed)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);

// Use admin-specific session name to match auth_check.php
session_name('admin_session');
session_start();

// Set JSON header early to prevent any HTML output
header('Content-Type: application/json');

// Clear any output that might have been generated
ob_clean();

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
    // #region agent log
    file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:99','message'=>'Entering main try block','data'=>['method'=>$method],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    
    // Get database connection
    $conn = getDBConnection();
    
    // #region agent log
    file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'C','location'=>'crud.php:102','message'=>'DB connection obtained','data'=>['conn_exists'=>isset($conn)],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    
    // Get JSON input for POST, PUT, DELETE
    $input = null;
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $raw_input = file_get_contents('php://input');
        $input = json_decode($raw_input, true);
        
        // #region agent log
        file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:108','message'=>'JSON input parsed','data'=>['method'=>$method,'input_keys'=>array_keys($input ?? []),'json_error'=>json_last_error_msg()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
        // #endregion
        
        if ($input === null && $method !== 'DELETE') {
            ErrorHandler::sendError(ErrorHandler::INVALID_JSON, 'Invalid JSON data');
        }
    }
    
    // Validate CSRF token for POST, PUT, DELETE
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        // #region agent log
        file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'D','location'=>'crud.php:118','message'=>'Before CSRF validation','data'=>['has_csrf'=>isset($input['csrf_token']),'session_has_token'=>isset($_SESSION['admin']['csrf_token'])],'timestamp'=>time()*1000])."\n", FILE_APPEND);
        // #endregion
        
        if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
            ErrorHandler::sendError(ErrorHandler::INVALID_CSRF_TOKEN, 'Invalid CSRF token', null, 403);
        }
        
        // #region agent log
        file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'D','location'=>'crud.php:121','message'=>'CSRF validation passed','data'=>[],'timestamp'=>time()*1000])."\n", FILE_APPEND);
        // #endregion
    }
    
    switch ($method) {
        case 'POST':
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'E','location'=>'crud.php:125','message'=>'Entering POST case','data'=>['input_keys'=>array_keys($input ?? [])],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
            // Create new service
            // Validate input data
            $validation_errors = validateServiceData($input, false);
            
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'E','location'=>'crud.php:129','message'=>'Validation completed','data'=>['has_errors'=>!empty($validation_errors),'error_count'=>count($validation_errors ?? [])],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
            if (!empty($validation_errors)) {
                ErrorHandler::handleValidationError($validation_errors);
            }
            
            // Prepare data
            $service_category = trim($input['service_category'] ?? '');
            $sub_category = isset($input['sub_category']) && is_string($input['sub_category']) && trim($input['sub_category']) !== '' ? trim($input['sub_category']) : null;
            $service_name = trim($input['service_name'] ?? '');
            $current_duration_minutes = (int)($input['current_duration_minutes'] ?? 0);
            $current_price = (float)($input['current_price'] ?? 0);
            $description = isset($input['description']) && is_string($input['description']) && trim($input['description']) !== '' ? trim($input['description']) : null;
            // Handle service_image - it might be an array from FormData, so check type first
            $service_image = null;
            if (isset($input['service_image'])) {
                if (is_string($input['service_image']) && trim($input['service_image']) !== '') {
                    $service_image = trim($input['service_image']);
                } elseif (is_array($input['service_image'])) {
                    // If it's an array (from file input), ignore it for now (file uploads handled separately)
                    $service_image = null;
                }
            }
            $default_cleanup_minutes = (int)($input['default_cleanup_minutes'] ?? 10);
            $is_active = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;
            
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'B','location'=>'crud.php:141','message'=>'Data prepared for insert','data'=>['service_category'=>$service_category,'service_name'=>$service_name,'sub_category'=>$sub_category,'has_description'=>!is_null($description),'has_image'=>!is_null($service_image)],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
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
            
            // Generate service_id (VARCHAR(4) format: S001, S002, etc.)
            // Get the highest numeric service_id by extracting the number part
            $id_stmt = $conn->prepare("SELECT service_id FROM Service WHERE service_id LIKE 'S%' ORDER BY CAST(SUBSTRING(service_id, 2) AS UNSIGNED) DESC, service_id DESC LIMIT 1");
            $id_stmt->execute();
            $id_result = $id_stmt->get_result();
            $last_id = 'S000';
            if ($id_row = $id_result->fetch_assoc()) {
                $last_id = $id_row['service_id'];
            }
            $id_stmt->close();
            
            // Extract number and increment
            $num = intval(substr($last_id, 1)) + 1;
            $service_id = 'S' . str_pad($num, 3, '0', STR_PAD_LEFT);
            
            // Ensure it doesn't exceed VARCHAR(4) - max is S999
            if (strlen($service_id) > 4 || $num > 999) {
                // If we exceed S999, we need a different strategy
                // For now, throw an error as this is unlikely
                throw new Exception('Maximum service ID limit reached (S999). Please contact administrator.');
            }
            
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'B','location'=>'crud.php:158','message'=>'Generated service_id','data'=>['service_id'=>$service_id,'last_id'=>$last_id],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
            // Insert new service with generated service_id
            $sql = "INSERT INTO Service (service_id, service_category, sub_category, service_name, 
                                     current_duration_minutes, current_price, description, 
                                     service_image, default_cleanup_minutes, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'C','location'=>'crud.php:163','message'=>'Before bind_param','data'=>['stmt_exists'=>isset($stmt),'stmt_error'=>$stmt ? $stmt->error : 'no_stmt'],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
            $stmt->bind_param("ssssidssii", 
                $service_id,
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
            
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'C','location'=>'crud.php:225','message'=>'Before execute','data'=>['service_id'=>$service_id],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
            if ($stmt->execute()) {
                // #region agent log
                file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'B','location'=>'crud.php:228','message'=>'Execute successful','data'=>['service_id'=>$service_id],'timestamp'=>time()*1000])."\n", FILE_APPEND);
                // #endregion
                
                // #region agent log
                file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:232','message'=>'Before ob_end_clean','data'=>['service_id'=>$service_id,'ob_level'=>ob_get_level()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
                // #endregion
                
                $stmt->close();
                $conn->close();
                
                // Clear output buffer and send JSON response
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // #region agent log
                file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:278','message'=>'Before sending JSON response','data'=>['service_id'=>$service_id],'timestamp'=>time()*1000])."\n", FILE_APPEND);
                // #endregion
                
                http_response_code(201);
                $response = json_encode([
                    'success' => true,
                    'service_id' => $service_id,
                    'message' => 'Service created successfully'
                ]);
                
                // #region agent log
                file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:287','message'=>'JSON encoded, about to echo','data'=>['response_length'=>strlen($response),'json_error'=>json_last_error_msg()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
                // #endregion
                
                echo $response;
                
                // #region agent log
                file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:292','message'=>'Response sent, about to exit','data'=>[],'timestamp'=>time()*1000])."\n", FILE_APPEND);
                // #endregion
                
                exit;
            } else {
                // #region agent log
                file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'C','location'=>'crud.php:214','message'=>'Execute failed','data'=>['error'=>$stmt->error],'timestamp'=>time()*1000])."\n", FILE_APPEND);
                // #endregion
                
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
            $service_category = trim($input['service_category'] ?? '');
            $sub_category = isset($input['sub_category']) && is_string($input['sub_category']) && trim($input['sub_category']) !== '' ? trim($input['sub_category']) : null;
            $service_name = trim($input['service_name'] ?? '');
            $current_duration_minutes = (int)($input['current_duration_minutes'] ?? 0);
            $current_price = (float)($input['current_price'] ?? 0);
            $description = isset($input['description']) && is_string($input['description']) && trim($input['description']) !== '' ? trim($input['description']) : null;
            // Handle service_image - it might be an array from FormData, so check type first
            $service_image = null;
            if (isset($input['service_image'])) {
                if (is_string($input['service_image']) && trim($input['service_image']) !== '') {
                    $service_image = trim($input['service_image']);
                } elseif (is_array($input['service_image'])) {
                    // If it's an array (from file input), ignore it for now (file uploads handled separately)
                    $service_image = null;
                }
            }
            $default_cleanup_minutes = (int)($input['default_cleanup_minutes'] ?? 10);
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
                
                // Clear output buffer and send JSON response
                ob_end_clean();
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'service_id' => $service_id,
                    'message' => 'Service updated successfully'
                ]);
                exit;
            } else {
                throw new Exception('Failed to update service: ' . $stmt->error);
            }
            break;
            
        case 'DELETE':
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:399','message'=>'Entering DELETE case','data'=>['has_service_id'=>isset($input['service_id']),'service_id'=>$input['service_id'] ?? null,'service_id_type'=>gettype($input['service_id'] ?? null),'input_keys'=>array_keys($input ?? [])],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
            // Delete service
            // Validate service_id - check for both missing and empty string
            if (!isset($input['service_id']) || $input['service_id'] === '' || $input['service_id'] === null) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                ErrorHandler::sendError(ErrorHandler::VALIDATION_ERROR, 'Service ID is required and cannot be empty', ['service_id' => 'Service ID is required'], 400);
            }
            
            // Check if password re-authentication is valid (required for delete)
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:408','message'=>'Checking reauth','data'=>['has_reauth'=>isset($_SESSION['reauth_ok_until']),'reauth_until'=>$_SESSION['reauth_ok_until'] ?? null,'current_time'=>time()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
            if (!isset($_SESSION['reauth_ok_until']) || time() > $_SESSION['reauth_ok_until']) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
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
            
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:425','message'=>'Service ID validated','data'=>['service_id'=>$service_id],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
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
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:445','message'=>'Checking for future bookings','data'=>['service_id'=>$service_id],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
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
            
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:460','message'=>'Booking check completed','data'=>['booking_count'=>$booking_count],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
            // If there are future bookings, return error
            if ($booking_count > 0) {
                $conn->close();
                
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
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
            // #region agent log
            file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:477','message'=>'About to delete service','data'=>['service_id'=>$service_id],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            
            $sql = "DELETE FROM Service WHERE service_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $service_id);
            
            if ($stmt->execute()) {
                // #region agent log
                file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:485','message'=>'Delete execute successful','data'=>['service_id'=>$service_id],'timestamp'=>time()*1000])."\n", FILE_APPEND);
                // #endregion
                
                $stmt->close();
                $conn->close();
                
                // Clear reauth flag after successful delete
                unset($_SESSION['reauth_ok_until']);
                unset($_SESSION['reauth_action']);
                
                // Clear output buffer and send JSON response
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // #region agent log
                file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:497','message'=>'Before sending delete response','data'=>['service_id'=>$service_id],'timestamp'=>time()*1000])."\n", FILE_APPEND);
                // #endregion
                
                http_response_code(200);
                $response = json_encode([
                    'success' => true,
                    'service_id' => $service_id,
                    'message' => 'Service deleted successfully'
                ]);
                
                // #region agent log
                file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:507','message'=>'JSON encoded, about to echo','data'=>['response_length'=>strlen($response),'json_error'=>json_last_error_msg()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
                // #endregion
                
                echo $response;
                exit;
            } else {
                // #region agent log
                file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'crud.php:514','message'=>'Delete execute failed','data'=>['error'=>$stmt->error],'timestamp'=>time()*1000])."\n", FILE_APPEND);
                // #endregion
                
                throw new Exception('Failed to delete service: ' . $stmt->error);
            }
            break;
            
        default:
            ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Method not allowed', null, 405);
    }
    
} catch (Exception $e) {
    // #region agent log
    file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'ALL','location'=>'crud.php:368','message'=>'Exception caught','data'=>['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'trace'=>explode("\n",$e->getTraceAsString())],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    
    if (isset($conn)) {
        $conn->close();
    }
    // Clear output buffer before sending error
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    ErrorHandler::handleDatabaseError($e, 'service operation');
} catch (Error $e) {
    // #region agent log
    file_put_contents('../../../.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'ALL','location'=>'crud.php:378','message'=>'Fatal Error caught','data'=>['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    
    if (isset($conn)) {
        $conn->close();
    }
    // Clear output buffer before sending error
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'FATAL_ERROR',
            'message' => 'A fatal error occurred: ' . $e->getMessage()
        ]
    ]);
    exit;
}
