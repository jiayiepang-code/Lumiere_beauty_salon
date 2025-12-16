<?php
// #region agent log
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, but log them
ini_set('log_errors', 1);
ob_start(); // Start output buffering to catch any unexpected output
// #endregion

header('Content-Type: application/json');

// #region agent log
file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'api/admin/bookings/list.php:7','message'=>'API endpoint called','data'=>['method'=>$_SERVER['REQUEST_METHOD']??'unknown','uri'=>$_SERVER['REQUEST_URI']??'unknown'],'timestamp'=>time()*1000])."\n", FILE_APPEND);
// #endregion

// Include required files
// #region agent log
file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'C','location'=>'api/admin/bookings/list.php:15','message'=>'Before require_once auth_check','data'=>['headers_sent'=>headers_sent(),'output_buffer_length'=>strlen(ob_get_contents())],'timestamp'=>time()*1000])."\n", FILE_APPEND);
// #endregion
require_once '../../../admin/includes/auth_check.php'; // This handles session start
// #region agent log
file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'C','location'=>'api/admin/bookings/list.php:18','message'=>'After require_once auth_check','data'=>['headers_sent'=>headers_sent(),'output_buffer_length'=>strlen(ob_get_contents()),'auth_status'=>function_exists('isAdminAuthenticated')?isAdminAuthenticated():'function_not_found'],'timestamp'=>time()*1000])."\n", FILE_APPEND);
// #endregion
require_once '../../../config/db_connect.php'; // Use proper DB connection
// #region agent log
file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'D','location'=>'api/admin/bookings/list.php:21','message'=>'After require_once db_connect','data'=>['headers_sent'=>headers_sent(),'output_buffer_length'=>strlen(ob_get_contents()),'db_function_exists'=>function_exists('getDBConnection')],'timestamp'=>time()*1000])."\n", FILE_APPEND);
// #endregion
require_once '../../../admin/includes/error_handler.php';
// #region agent log
file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'C','location'=>'api/admin/bookings/list.php:24','message'=>'After all require_once','data'=>['headers_sent'=>headers_sent(),'output_buffer_length'=>strlen(ob_get_contents()),'buffer_content_preview'=>substr(ob_get_contents(),0,200)],'timestamp'=>time()*1000])."\n", FILE_APPEND);
// Clear any unexpected output
$unexpected_output = ob_get_clean();
if (!empty($unexpected_output)) {
    file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'api/admin/bookings/list.php:27','message'=>'UNEXPECTED OUTPUT DETECTED','data'=>['output_length'=>strlen($unexpected_output),'output_preview'=>substr($unexpected_output,0,500)],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    ob_start(); // Restart buffer
}
// #endregion

// Check authentication
// #region agent log
file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'B','location'=>'api/admin/bookings/list.php:32','message'=>'Checking authentication','data'=>['is_authenticated'=>isAdminAuthenticated()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
// #endregion
if (!isAdminAuthenticated()) {
    ErrorHandler::handleAuthError();
}

// Check session timeout
if (!checkSessionTimeout()) {
    ErrorHandler::sendError(ErrorHandler::SESSION_EXPIRED, 'Session has expired', null, 401);
}

// Handle GET request only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only GET requests are allowed', null, 405);
}

try {
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'D','location'=>'api/admin/bookings/list.php:40','message'=>'Before database connection','data'=>['headers_sent'=>headers_sent()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    
    // Get database connection
    $conn = getDBConnection();
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'D','location'=>'api/admin/bookings/list.php:45','message'=>'Database connection obtained','data'=>['conn_exists'=>isset($conn),'conn_type'=>gettype($conn)],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    
    // Get filter parameters
    $date = isset($_GET['date']) ? trim($_GET['date']) : null;
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;
    $staff_email = isset($_GET['staff_email']) ? trim($_GET['staff_email']) : null;
    $service_type = isset($_GET['service_type']) ? trim($_GET['service_type']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    
    // Build bookings query with joins
    $sql = "SELECT 
                b.booking_id,
                b.customer_email,
                c.first_name as customer_first_name,
                c.last_name as customer_last_name,
                c.phone as customer_phone,
                b.booking_date,
                b.start_time,
                b.expected_finish_time,
                b.status,
                b.remarks,
                b.promo_code,
                b.discount_amount,
                b.total_price,
                b.created_at
            FROM Booking b
            INNER JOIN Customer c ON b.customer_email = c.customer_email
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Add date filters
    if ($date !== null && $date !== '') {
        $sql .= " AND b.booking_date = ?";
        $params[] = $date;
        $types .= "s";
    } elseif ($start_date !== null && $start_date !== '' && $end_date !== null && $end_date !== '') {
        $sql .= " AND b.booking_date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= "ss";
    }
    
    // Add status filter
    if ($status !== null && $status !== '') {
        $sql .= " AND b.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Add staff or service filter (requires subquery)
    if ($staff_email !== null && $staff_email !== '') {
        $sql .= " AND b.booking_id IN (
            SELECT DISTINCT booking_id 
            FROM Booking_Service 
            WHERE staff_email = ?
        )";
        $params[] = $staff_email;
        $types .= "s";
    }
    
    if ($service_type !== null && $service_type !== '') {
        $sql .= " AND b.booking_id IN (
            SELECT DISTINCT bs.booking_id 
            FROM Booking_Service bs
            INNER JOIN Service s ON bs.service_id = s.service_id
            WHERE s.service_category = ?
        )";
        $params[] = $service_type;
        $types .= "s";
    }
    
    // Order by date and time
    $sql .= " ORDER BY b.booking_date DESC, b.start_time ASC";
    
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'D','location'=>'api/admin/bookings/list.php:108','message'=>'Database connection successful','data'=>['conn_exists'=>isset($conn)],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    // Prepare and execute query
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'D','location'=>'api/admin/bookings/list.php:152','message'=>'Preparing SQL query','data'=>['sql_length'=>strlen($sql),'params_count'=>count($params)],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        // #region agent log
        file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'D','location'=>'api/admin/bookings/list.php:156','message'=>'SQL PREPARE FAILED','data'=>['error'=>$conn->error,'sql_preview'=>substr($sql,0,200)],'timestamp'=>time()*1000])."\n", FILE_APPEND);
        // #endregion
        throw new Exception("SQL prepare failed: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'D','location'=>'api/admin/bookings/list.php:164','message'=>'Executing SQL query','data'=>[],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    if (!$stmt->execute()) {
        // #region agent log
        file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'D','location'=>'api/admin/bookings/list.php:167','message'=>'SQL EXECUTE FAILED','data'=>['error'=>$stmt->error],'timestamp'=>time()*1000])."\n", FILE_APPEND);
        // #endregion
        throw new Exception("SQL execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $bookings = [];
    $booking_ids = [];
    
    while ($row = $result->fetch_assoc()) {
        $booking_ids[] = $row['booking_id'];
        $row['customer_name'] = $row['customer_first_name'] . ' ' . $row['customer_last_name'];
        $row['discount_amount'] = (float)$row['discount_amount'];
        $row['total_price'] = (float)$row['total_price'];
        $bookings[$row['booking_id']] = $row;
    }
    
    $stmt->close();
    
    // Fetch services for each booking
    if (!empty($booking_ids)) {
        $placeholders = implode(',', array_fill(0, count($booking_ids), '?'));
        $service_sql = "SELECT 
                            bs.booking_id,
                            bs.service_id,
                            s.service_name,
                            s.service_category,
                            bs.staff_email,
                            st.first_name as staff_first_name,
                            st.last_name as staff_last_name,
                            bs.quoted_price,
                            bs.quoted_duration_minutes,
                            bs.quoted_cleanup_minutes,
                            bs.quantity,
                            bs.sequence_order,
                            bs.service_status,
                            bs.special_request
                        FROM Booking_Service bs
                        INNER JOIN Service s ON bs.service_id = s.service_id
                        INNER JOIN Staff st ON bs.staff_email = st.staff_email
                        WHERE bs.booking_id IN ($placeholders)
                        ORDER BY bs.booking_id, bs.sequence_order";
        
        $service_stmt = $conn->prepare($service_sql);
        $service_types = str_repeat('s', count($booking_ids));
        $service_stmt->bind_param($service_types, ...$booking_ids);
        $service_stmt->execute();
        $service_result = $service_stmt->get_result();
        
        while ($service_row = $service_result->fetch_assoc()) {
            $booking_id = $service_row['booking_id'];
            $service_row['staff_name'] = $service_row['staff_first_name'] . ' ' . $service_row['staff_last_name'];
            $service_row['quoted_price'] = (float)$service_row['quoted_price'];
            $service_row['quoted_duration_minutes'] = (int)$service_row['quoted_duration_minutes'];
            $service_row['quoted_cleanup_minutes'] = (int)$service_row['quoted_cleanup_minutes'];
            $service_row['quantity'] = (int)$service_row['quantity'];
            $service_row['sequence_order'] = (int)$service_row['sequence_order'];
            
            unset($service_row['booking_id']);
            unset($service_row['staff_first_name']);
            unset($service_row['staff_last_name']);
            
            if (!isset($bookings[$booking_id]['services'])) {
                $bookings[$booking_id]['services'] = [];
            }
            $bookings[$booking_id]['services'][] = $service_row;
        }
        
        $service_stmt->close();
    }
    
    // Fetch staff schedules for the date range
    $schedule_sql = "SELECT 
                        ss.staff_email,
                        st.first_name as staff_first_name,
                        st.last_name as staff_last_name,
                        ss.work_date,
                        ss.start_time,
                        ss.end_time,
                        ss.status
                    FROM Staff_Schedule ss
                    INNER JOIN Staff st ON ss.staff_email = st.staff_email
                    WHERE 1=1";
    
    $schedule_params = [];
    $schedule_types = "";
    
    if ($date !== null && $date !== '') {
        $schedule_sql .= " AND ss.work_date = ?";
        $schedule_params[] = $date;
        $schedule_types .= "s";
    } elseif ($start_date !== null && $start_date !== '' && $end_date !== null && $end_date !== '') {
        $schedule_sql .= " AND ss.work_date BETWEEN ? AND ?";
        $schedule_params[] = $start_date;
        $schedule_params[] = $end_date;
        $schedule_types .= "ss";
    }
    
    if ($staff_email !== null && $staff_email !== '') {
        $schedule_sql .= " AND ss.staff_email = ?";
        $schedule_params[] = $staff_email;
        $schedule_types .= "s";
    }
    
    $schedule_sql .= " ORDER BY ss.work_date, ss.start_time";
    
    $schedule_stmt = $conn->prepare($schedule_sql);
    
    if (!empty($schedule_params)) {
        $schedule_stmt->bind_param($schedule_types, ...$schedule_params);
    }
    
    $schedule_stmt->execute();
    $schedule_result = $schedule_stmt->get_result();
    
    $staff_schedules = [];
    while ($schedule_row = $schedule_result->fetch_assoc()) {
        $schedule_row['staff_name'] = $schedule_row['staff_first_name'] . ' ' . $schedule_row['staff_last_name'];
        unset($schedule_row['staff_first_name']);
        unset($schedule_row['staff_last_name']);
        $staff_schedules[] = $schedule_row;
    }
    
    $schedule_stmt->close();
    $conn->close();
    
    // Convert bookings associative array to indexed array
    $bookings_array = array_values($bookings);
    
    // Return success response
    // #region agent log
    $json_response = json_encode([
        'success' => true,
        'bookings' => $bookings_array,
        'staff_schedules' => $staff_schedules,
        'count' => count($bookings_array)
    ]);
    file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'api/admin/bookings/list.php:241','message'=>'Before sending JSON response','data'=>['headers_sent'=>headers_sent(),'json_length'=>strlen($json_response),'output_buffer_length'=>strlen(ob_get_contents())],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    http_response_code(200);
    echo $json_response;
    
} catch (Exception $e) {
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'api/admin/bookings/list.php:260','message'=>'EXCEPTION CAUGHT','data'=>['error_message'=>$e->getMessage(),'error_file'=>$e->getFile(),'error_line'=>$e->getLine(),'trace'=>$e->getTraceAsString()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    
    // Clear any output buffer before sending error
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    ErrorHandler::handleDatabaseError($e, 'fetching bookings');
} catch (Error $e) {
    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'api/admin/bookings/list.php:270','message'=>'FATAL ERROR CAUGHT','data'=>['error_message'=>$e->getMessage(),'error_file'=>$e->getFile(),'error_line'=>$e->getLine()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    
    // Clear any output buffer before sending error
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'FATAL_ERROR',
            'message' => 'A fatal error occurred: ' . $e->getMessage()
        ]
    ]);
    exit;
}
