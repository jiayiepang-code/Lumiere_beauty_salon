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

// Handle GET request only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Only GET requests are allowed'
        ]
    ]);
    exit;
}

try {
    // Get parameters
    $period = isset($_GET['period']) ? trim($_GET['period']) : 'monthly';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;
    
    // Validate period
    $valid_periods = ['daily', 'weekly', 'monthly'];
    if (!in_array($period, $valid_periods)) {
        $period = 'monthly';
    }
    
    // Calculate date range based on period if not provided
    if ($start_date === null || $start_date === '') {
        switch ($period) {
            case 'daily':
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d');
                break;
            case 'weekly':
                $start_date = date('Y-m-d', strtotime('monday this week'));
                $end_date = date('Y-m-d', strtotime('sunday this week'));
                break;
            case 'monthly':
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-t');
                break;
        }
    }
    
    // If end_date not provided, set it to start_date
    if ($end_date === null || $end_date === '') {
        $end_date = $start_date;
    }
    
    // Calculate total scheduled hours from Staff_Schedule table
    $scheduled_sql = "SELECT 
                          SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time) / 60) as total_scheduled_hours
                      FROM Staff_Schedule
                      WHERE work_date BETWEEN ? AND ?
                      AND status = 'working'";
    
    $stmt = $conn->prepare($scheduled_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $scheduled_result = $stmt->get_result();
    $scheduled_data = $scheduled_result->fetch_assoc();
    $total_scheduled_hours = (float)($scheduled_data['total_scheduled_hours'] ?? 0);
    $stmt->close();
    
    // Calculate total booked hours from Booking_Service table
    $booked_sql = "SELECT 
                       SUM((bs.quoted_duration_minutes + bs.quoted_cleanup_minutes) / 60) as total_booked_hours
                   FROM Booking_Service bs
                   INNER JOIN Booking b ON bs.booking_id = b.booking_id
                   WHERE b.booking_date BETWEEN ? AND ?
                   AND bs.service_status IN ('confirmed', 'completed')";
    
    $stmt = $conn->prepare($booked_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $booked_result = $stmt->get_result();
    $booked_data = $booked_result->fetch_assoc();
    $total_booked_hours = (float)($booked_data['total_booked_hours'] ?? 0);
    $stmt->close();
    
    // Calculate idle hours and utilization rate
    $total_idle_hours = $total_scheduled_hours - $total_booked_hours;
    $utilization_rate = $total_scheduled_hours > 0 
        ? ($total_booked_hours / $total_scheduled_hours) * 100 
        : 0;
    
    // Format salon metrics
    $salon_metrics = [
        'total_scheduled_hours' => round($total_scheduled_hours, 2),
        'total_booked_hours' => round($total_booked_hours, 2),
        'total_idle_hours' => round($total_idle_hours, 2),
        'utilization_rate' => round($utilization_rate, 1)
    ];
    
    // Calculate staff breakdown
    $staff_breakdown_sql = "SELECT 
                                st.staff_email,
                                st.first_name,
                                st.last_name,
                                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, ss.start_time, ss.end_time) / 60), 0) as scheduled_hours,
                                COALESCE(SUM((bs.quoted_duration_minutes + bs.quoted_cleanup_minutes) / 60), 0) as booked_hours
                            FROM Staff st
                            LEFT JOIN Staff_Schedule ss ON st.staff_email = ss.staff_email 
                                AND ss.work_date BETWEEN ? AND ?
                                AND ss.status = 'working'
                            LEFT JOIN Booking_Service bs ON st.staff_email = bs.staff_email
                            LEFT JOIN Booking b ON bs.booking_id = b.booking_id 
                                AND b.booking_date BETWEEN ? AND ?
                                AND bs.service_status IN ('confirmed', 'completed')
                            WHERE st.is_active = 1
                            GROUP BY st.staff_email, st.first_name, st.last_name
                            ORDER BY st.first_name, st.last_name";
    
    $stmt = $conn->prepare($staff_breakdown_sql);
    $stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
    $stmt->execute();
    $staff_result = $stmt->get_result();
    
    $staff_breakdown = [];
    while ($row = $staff_result->fetch_assoc()) {
        $scheduled = (float)$row['scheduled_hours'];
        $booked = (float)$row['booked_hours'];
        $idle = $scheduled - $booked;
        $util_rate = $scheduled > 0 ? ($booked / $scheduled) * 100 : 0;
        
        $staff_breakdown[] = [
            'staff_name' => $row['first_name'] . ' ' . $row['last_name'],
            'staff_email' => $row['staff_email'],
            'scheduled_hours' => round($scheduled, 2),
            'booked_hours' => round($booked, 2),
            'idle_hours' => round($idle, 2),
            'utilization_rate' => round($util_rate, 1)
        ];
    }
    $stmt->close();
    
    // Generate daily idle hour patterns
    $daily_pattern_sql = "SELECT 
                              dates.work_date as date,
                              COALESCE(SUM(TIMESTAMPDIFF(MINUTE, ss.start_time, ss.end_time) / 60), 0) as scheduled_hours,
                              COALESCE(SUM((bs.quoted_duration_minutes + bs.quoted_cleanup_minutes) / 60), 0) as booked_hours
                          FROM (
                              SELECT DISTINCT work_date 
                              FROM Staff_Schedule 
                              WHERE work_date BETWEEN ? AND ?
                          ) dates
                          LEFT JOIN Staff_Schedule ss ON dates.work_date = ss.work_date 
                              AND ss.status = 'working'
                          LEFT JOIN Booking b ON dates.work_date = b.booking_date
                          LEFT JOIN Booking_Service bs ON b.booking_id = bs.booking_id 
                              AND bs.service_status IN ('confirmed', 'completed')
                          GROUP BY dates.work_date
                          ORDER BY dates.work_date ASC";
    
    $stmt = $conn->prepare($daily_pattern_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $daily_result = $stmt->get_result();
    
    $daily_idle_pattern = [];
    while ($row = $daily_result->fetch_assoc()) {
        $scheduled = (float)$row['scheduled_hours'];
        $booked = (float)$row['booked_hours'];
        $idle = $scheduled - $booked;
        $util_rate = $scheduled > 0 ? ($booked / $scheduled) * 100 : 0;
        
        $daily_idle_pattern[] = [
            'date' => $row['date'],
            'scheduled_hours' => round($scheduled, 2),
            'booked_hours' => round($booked, 2),
            'idle_hours' => round($idle, 2),
            'utilization_rate' => round($util_rate, 1)
        ];
    }
    $stmt->close();
    
    $conn->close();
    
    // Prepare response
    $response = [
        'success' => true,
        'period' => $period,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'salon_metrics' => $salon_metrics,
        'staff_breakdown' => $staff_breakdown,
        'daily_idle_pattern' => $daily_idle_pattern
    ];
    
    // Return success response
    http_response_code(200);
    echo json_encode($response);
    
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
            'message' => 'An error occurred while fetching idle hours data'
        ]
    ]);
}
