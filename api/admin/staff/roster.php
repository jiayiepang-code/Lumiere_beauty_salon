<?php
// Suppress error display but log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

header('Content-Type: application/json');

// Include required files
require_once '../../../admin/includes/auth_check.php';
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/error_handler.php';

// Clear any unexpected output from included files
$unexpected_output = ob_get_clean();
if (!empty($unexpected_output)) {
    ob_start();
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
    
    // Get date parameter (default to today)
    $date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD');
    }
    
    // Get current time for status determination
    $current_time = date('H:i:s');
    $current_datetime = new DateTime($date . ' ' . $current_time);
    
    // Get all active staff
    $staff_sql = "SELECT 
                    st.staff_email,
                    st.first_name,
                    st.last_name,
                    st.role,
                    st.staff_image
                  FROM Staff st
                  WHERE st.is_active = 1
                  ORDER BY st.first_name, st.last_name";
    
    $staff_stmt = $conn->prepare($staff_sql);
    $staff_stmt->execute();
    $staff_result = $staff_stmt->get_result();
    
    $roster = [];
    
    while ($staff = $staff_result->fetch_assoc()) {
        $staff_email = $staff['staff_email'];
        $staff_name = $staff['first_name'] . ' ' . $staff['last_name'];
        
        // Get staff schedule for today
        $schedule_sql = "SELECT 
                            work_date,
                            start_time,
                            end_time,
                            status
                         FROM Staff_Schedule
                         WHERE staff_email = ? AND work_date = ?";
        
        $schedule_stmt = $conn->prepare($schedule_sql);
        $schedule_stmt->bind_param("ss", $staff_email, $date);
        $schedule_stmt->execute();
        $schedule_result = $schedule_stmt->get_result();
        $schedule = $schedule_result->fetch_assoc();
        $schedule_stmt->close();
        
        // Determine status and schedule
        $status = 'off-duty'; // Default
        $schedule_display = 'Off Today';
        $hours_today = 0;
        $current_client = null;
        $break_info = null;
        
        if ($schedule) {
            $schedule_status = strtolower($schedule['status']);
            
            // Check if staff is on leave
            if ($schedule_status === 'leave') {
                $status = 'off-duty';
                $schedule_display = 'Off Today';
            } else {
                // Staff is scheduled to work
                $start_time = $schedule['start_time'];
                $end_time = $schedule['end_time'];
                $schedule_display = date('H:i', strtotime($start_time)) . ' - ' . date('H:i', strtotime($end_time));
                
                // Check if currently with a client
                $client_sql = "SELECT 
                                b.booking_id,
                                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                                b.start_time,
                                b.expected_finish_time
                              FROM Booking b
                              INNER JOIN Booking_Service bs ON b.booking_id = bs.booking_id
                              INNER JOIN Customer c ON b.customer_email = c.customer_email
                              WHERE bs.staff_email = ?
                                AND b.booking_date = ?
                                AND b.status IN ('confirmed', 'in-progress')
                                AND ? BETWEEN b.start_time AND b.expected_finish_time
                              LIMIT 1";
                
                $client_stmt = $conn->prepare($client_sql);
                $client_stmt->bind_param("sss", $staff_email, $date, $current_time);
                $client_stmt->execute();
                $client_result = $client_stmt->get_result();
                $current_booking = $client_result->fetch_assoc();
                $client_stmt->close();
                
                if ($current_booking) {
                    $status = 'with-client';
                    $current_client = $current_booking['customer_name'];
                } else {
                    // Check if within working hours
                    $schedule_start = new DateTime($date . ' ' . $start_time);
                    $schedule_end = new DateTime($date . ' ' . $end_time);
                    
                    if ($current_datetime >= $schedule_start && $current_datetime <= $schedule_end) {
                        // Check if on break (simplified: assume break if no booking in last 15 min and next 15 min)
                        $break_check_start = (clone $current_datetime)->modify('-15 minutes');
                        $break_check_end = (clone $current_datetime)->modify('+15 minutes');
                        
                        $break_sql = "SELECT COUNT(*) as booking_count
                                      FROM Booking b
                                      INNER JOIN Booking_Service bs ON b.booking_id = bs.booking_id
                                      WHERE bs.staff_email = ?
                                        AND b.booking_date = ?
                                        AND b.status IN ('confirmed', 'in-progress')
                                        AND (
                                          (b.start_time <= ? AND b.expected_finish_time >= ?)
                                          OR (b.start_time <= ? AND b.expected_finish_time >= ?)
                                        )";
                        
                        $break_start_str = $break_check_start->format('H:i:s');
                        $break_end_str = $break_check_end->format('H:i:s');
                        
                        $break_stmt = $conn->prepare($break_sql);
                        $break_stmt->bind_param("ssssss", $staff_email, $date, $break_start_str, $break_start_str, $break_end_str, $break_end_str);
                        $break_stmt->execute();
                        $break_result = $break_stmt->get_result();
                        $break_data = $break_result->fetch_assoc();
                        $break_stmt->close();
                        
                        if ($break_data['booking_count'] == 0) {
                            $status = 'on-break';
                            $break_info = "Back in 15 minutes";
                        } else {
                            $status = 'available';
                        }
                    } else {
                        // Outside working hours
                        $status = 'off-duty';
                    }
                }
            }
        }
        
        // Calculate hours worked today (from completed bookings)
        $hours_sql = "SELECT 
                        SUM(bs.quoted_duration_minutes + bs.quoted_cleanup_minutes) as total_minutes
                      FROM Booking b
                      INNER JOIN Booking_Service bs ON b.booking_id = bs.booking_id
                      WHERE bs.staff_email = ?
                        AND b.booking_date = ?
                        AND b.status = 'completed'";
        
        $hours_stmt = $conn->prepare($hours_sql);
        $hours_stmt->bind_param("ss", $staff_email, $date);
        $hours_stmt->execute();
        $hours_result = $hours_stmt->get_result();
        $hours_data = $hours_result->fetch_assoc();
        $hours_stmt->close();
        
        if ($hours_data && $hours_data['total_minutes']) {
            $total_minutes = (int)$hours_data['total_minutes'];
            $hours = floor($total_minutes / 60);
            $minutes = $total_minutes % 60;
            $hours_today = $hours . 'h ' . $minutes . 'm';
        } else {
            $hours_today = '0h';
        }
        
        // Build roster entry
        $roster[] = [
            'staff_email' => $staff_email,
            'staff_name' => $staff_name,
            'role' => $staff['role'],
            'staff_image' => $staff['staff_image'],
            'status' => $status,
            'schedule' => $schedule_display,
            'hours_today' => $hours_today,
            'current_client' => $current_client,
            'break_info' => $break_info
        ];
    }
    
    $staff_stmt->close();
    $conn->close();
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'date' => $date,
        'roster' => $roster,
        'count' => count($roster)
    ]);
    
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
            'code' => 'SERVER_ERROR',
            'message' => 'An error occurred while fetching staff roster'
        ]
    ]);
}

