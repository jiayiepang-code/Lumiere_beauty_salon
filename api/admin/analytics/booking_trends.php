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
    $period = isset($_GET['period']) ? trim($_GET['period']) : 'weekly';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;
    
    // Validate period
    $valid_periods = ['daily', 'weekly', 'monthly'];
    if (!in_array($period, $valid_periods)) {
        $period = 'weekly';
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
    
    // Check cache (5-minute cache)
    $cache_key = "analytics_booking_trends_{$period}_{$start_date}_{$end_date}";
    $cache_file = "../../../cache/{$cache_key}.json";
    $cache_duration = 300; // 5 minutes
    
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        http_response_code(200);
        echo json_encode($cached_data);
        exit;
    }
    
    // Calculate metrics
    $metrics_sql = "SELECT 
                        COUNT(*) as total_bookings,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                        SUM(CASE WHEN status = 'no-show' THEN 1 ELSE 0 END) as no_show_bookings,
                        SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END) as total_revenue
                    FROM Booking
                    WHERE booking_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($metrics_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $metrics_result = $stmt->get_result();
    $metrics = $metrics_result->fetch_assoc();
    $stmt->close();
    
    // Calculate average booking value
    $average_booking_value = 0;
    if ($metrics['completed_bookings'] > 0) {
        $average_booking_value = $metrics['total_revenue'] / $metrics['completed_bookings'];
    }
    
    // Format metrics
    $metrics_formatted = [
        'total_bookings' => (int)$metrics['total_bookings'],
        'completed_bookings' => (int)$metrics['completed_bookings'],
        'cancelled_bookings' => (int)$metrics['cancelled_bookings'],
        'no_show_bookings' => (int)$metrics['no_show_bookings'],
        'total_revenue' => (float)$metrics['total_revenue'],
        'average_booking_value' => round($average_booking_value, 2)
    ];
    
    // Generate daily breakdown
    $daily_sql = "SELECT 
                      booking_date as date,
                      COUNT(*) as bookings,
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                      SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                      SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END) as revenue
                  FROM Booking
                  WHERE booking_date BETWEEN ? AND ?
                  GROUP BY booking_date
                  ORDER BY booking_date ASC";
    
    $stmt = $conn->prepare($daily_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $daily_result = $stmt->get_result();
    
    $daily_breakdown = [];
    while ($row = $daily_result->fetch_assoc()) {
        $daily_breakdown[] = [
            'date' => $row['date'],
            'bookings' => (int)$row['bookings'],
            'completed' => (int)$row['completed'],
            'cancelled' => (int)$row['cancelled'],
            'revenue' => (float)$row['revenue']
        ];
    }
    $stmt->close();
    
    // Aggregate popular services
    $services_sql = "SELECT 
                         s.service_name,
                         COUNT(bs.booking_service_id) as booking_count,
                         SUM(CASE WHEN b.status = 'completed' THEN bs.quoted_price * bs.quantity ELSE 0 END) as revenue
                     FROM Booking_Service bs
                     INNER JOIN Service s ON bs.service_id = s.service_id
                     INNER JOIN Booking b ON bs.booking_id = b.booking_id
                     WHERE b.booking_date BETWEEN ? AND ?
                     GROUP BY s.service_id, s.service_name
                     ORDER BY booking_count DESC
                     LIMIT 10";
    
    $stmt = $conn->prepare($services_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $services_result = $stmt->get_result();
    
    $popular_services = [];
    while ($row = $services_result->fetch_assoc()) {
        $popular_services[] = [
            'service_name' => $row['service_name'],
            'booking_count' => (int)$row['booking_count'],
            'revenue' => (float)$row['revenue']
        ];
    }
    $stmt->close();
    
    // Calculate staff performance
    $staff_sql = "SELECT 
                      st.first_name,
                      st.last_name,
                      st.staff_email,
                      COUNT(CASE WHEN b.status = 'completed' THEN 1 END) as completed_sessions,
                      SUM(CASE WHEN b.status = 'completed' THEN bs.quoted_price * bs.quantity ELSE 0 END) as total_revenue
                  FROM Staff st
                  LEFT JOIN Booking_Service bs ON st.staff_email = bs.staff_email
                  LEFT JOIN Booking b ON bs.booking_id = b.booking_id AND b.booking_date BETWEEN ? AND ?
                  WHERE st.is_active = 1
                  GROUP BY st.staff_email, st.first_name, st.last_name
                  ORDER BY completed_sessions DESC";
    
    $stmt = $conn->prepare($staff_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $staff_result = $stmt->get_result();
    
    $staff_performance = [];
    while ($row = $staff_result->fetch_assoc()) {
        $staff_performance[] = [
            'staff_name' => $row['first_name'] . ' ' . $row['last_name'],
            'staff_email' => $row['staff_email'],
            'completed_sessions' => (int)$row['completed_sessions'],
            'total_revenue' => (float)($row['total_revenue'] ?? 0)
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
        'metrics' => $metrics_formatted,
        'daily_breakdown' => $daily_breakdown,
        'popular_services' => $popular_services,
        'staff_performance' => $staff_performance
    ];
    
    // Cache the response
    if (!is_dir('../../../cache')) {
        mkdir('../../../cache', 0755, true);
    }
    file_put_contents($cache_file, json_encode($response));
    
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
            'message' => 'An error occurred while fetching analytics data'
        ]
    ]);
}
