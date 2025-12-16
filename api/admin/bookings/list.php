<?php
// Suppress error display but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

header('Content-Type: application/json');

// Include required files
require_once '../../../admin/includes/auth_check.php'; // This handles session start
require_once '../../../config/db_connect.php'; // Use proper DB connection
require_once '../../../admin/includes/error_handler.php';

// Clear any unexpected output from included files
$unexpected_output = ob_get_clean();
if (!empty($unexpected_output)) {
    ob_start(); // Restart buffer
}

// Check authentication
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
    // Get database connection
    $conn = getDBConnection();
    
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
    
    // Prepare and execute query
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        throw new Exception("SQL prepare failed: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
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
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'bookings' => $bookings_array,
        'staff_schedules' => $staff_schedules,
        'count' => count($bookings_array)
    ]);
    
} catch (Exception $e) {
    // Clear any output buffer before sending error
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    ErrorHandler::handleDatabaseError($e, 'fetching bookings');
} catch (Error $e) {
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
