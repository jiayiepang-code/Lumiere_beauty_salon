<?php
// Disable error display FIRST - before any output or includes
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header immediately
header('Content-Type: application/json');

// Include database connection and auth check
// Note: auth_check.php handles session start with proper configuration
require_once '../../../config/db_connect.php';
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
    // Get database connection
    $conn = getDBConnection();
    
    // Get parameters - new flexible system
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;
    $group_by = isset($_GET['group_by']) ? trim($_GET['group_by']) : 'daily';
    $preset = isset($_GET['preset']) ? trim($_GET['preset']) : null;
    
    // Validate group_by
    $valid_groups = ['daily', 'weekly', 'monthly'];
    if (!in_array($group_by, $valid_groups)) {
        $group_by = 'daily';
    }
    
    // Handle preset date ranges if provided
    if ($preset && ($start_date === null || $end_date === null)) {
        $today = date('Y-m-d');
        switch ($preset) {
            case 'today':
                $start_date = $today;
                $end_date = $today;
                break;
            case 'yesterday':
                $start_date = date('Y-m-d', strtotime('-1 day'));
                $end_date = date('Y-m-d', strtotime('-1 day'));
                break;
            case 'last7days':
                $start_date = date('Y-m-d', strtotime('-6 days'));
                $end_date = $today;
                break;
            case 'last30days':
                $start_date = date('Y-m-d', strtotime('-29 days'));
                $end_date = $today;
                break;
            case 'thisweek':
                $start_date = date('Y-m-d', strtotime('monday this week'));
                $end_date = date('Y-m-d', strtotime('sunday this week'));
                break;
            case 'thismonth':
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-t');
                break;
            case 'lastmonth':
                $start_date = date('Y-m-01', strtotime('first day of last month'));
                $end_date = date('Y-m-t', strtotime('last day of last month'));
                break;
            case 'thisyear':
                $start_date = date('Y-01-01');
                $end_date = date('Y-12-31');
                break;
            default:
                // Default to this month if preset is invalid
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-t');
                break;
        }
    }
    
    // If dates still not provided, default to this month
    if ($start_date === null || $start_date === '') {
        $start_date = date('Y-m-01');
    }
    if ($end_date === null || $end_date === '') {
        $end_date = date('Y-m-t');
    }
    
    // Validate dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD');
    }
    
    // Ensure start_date <= end_date
    if (strtotime($start_date) > strtotime($end_date)) {
        $temp = $start_date;
        $start_date = $end_date;
        $end_date = $temp;
    }
    
    // Check cache (5-minute cache)
    $cache_key = "analytics_booking_trends_{$start_date}_{$end_date}_{$group_by}";
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
    if (!$stmt) {
        throw new Exception('Failed to prepare metrics query: ' . $conn->error);
    }
    
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute metrics query: ' . $stmt->error);
    }
    
    $metrics_result = $stmt->get_result();
    if (!$metrics_result) {
        throw new Exception('Failed to get metrics result: ' . $conn->error);
    }
    
    $metrics = $metrics_result->fetch_assoc();
    $stmt->close();
    
    // Initialize with defaults if no data
    if (!$metrics) {
        $metrics = [
            'total_bookings' => 0,
            'completed_bookings' => 0,
            'cancelled_bookings' => 0,
            'no_show_bookings' => 0,
            'total_revenue' => 0
        ];
    }
    
    // Calculate average booking value
    $average_booking_value = 0;
    if (isset($metrics['completed_bookings']) && $metrics['completed_bookings'] > 0) {
        $average_booking_value = $metrics['total_revenue'] / $metrics['completed_bookings'];
    }
    
    // Format metrics with null safety
    $metrics_formatted = [
        'total_bookings' => (int)($metrics['total_bookings'] ?? 0),
        'completed_bookings' => (int)($metrics['completed_bookings'] ?? 0),
        'cancelled_bookings' => (int)($metrics['cancelled_bookings'] ?? 0),
        'no_show_bookings' => (int)($metrics['no_show_bookings'] ?? 0),
        'total_revenue' => (float)($metrics['total_revenue'] ?? 0),
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
    
    // Calculate staff performance using Staff Leaderboard ranking system
    // Matches Staff Module rankings - ranked by revenue_generated
    $staff_sql = "SELECT 
                      s.staff_email,
                      CONCAT(s.first_name, ' ', s.last_name) AS full_name,
                      COUNT(bs.booking_service_id) AS completed_count,
                      COALESCE(SUM(bs.quoted_price), 0) AS revenue_generated,
                      COALESCE(SUM(bs.quoted_price), 0) * 0.10 AS commission_earned
                  FROM Staff s
                  LEFT JOIN Booking_Service bs ON s.staff_email = bs.staff_email 
                      AND bs.service_status = 'completed'
                  LEFT JOIN Booking b ON bs.booking_id = b.booking_id
                      AND b.booking_date BETWEEN ? AND ?
                  WHERE s.is_active = 1 AND s.role != 'admin'
                  GROUP BY s.staff_email, s.first_name, s.last_name
                  ORDER BY revenue_generated DESC";
    
    $stmt = $conn->prepare($staff_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $staff_result = $stmt->get_result();
    
    $staff_performance = [];
    while ($row = $staff_result->fetch_assoc()) {
        $staff_performance[] = [
            'staff_email' => $row['staff_email'],
            'staff_name' => $row['full_name'],
            'completed_sessions' => (int)$row['completed_count'],
            'total_revenue' => (float)$row['revenue_generated'],
            'commission_earned' => (float)$row['commission_earned']
        ];
    }
    $stmt->close();
    
    $conn->close();
    
    // Prepare response
    $response = [
        'success' => true,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'group_by' => $group_by,
        'preset' => $preset,
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
    
    // Ensure JSON response (not HTML) - set header again in case it was overwritten
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'DATABASE_ERROR',
            'message' => 'An error occurred while fetching analytics data: ' . $e->getMessage()
        ]
    ]);
    exit;
} catch (Error $e) {
    // Handle fatal errors (PHP 7+)
    error_log("Fatal error in booking_trends.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'FATAL_ERROR',
            'message' => 'A fatal error occurred. Please check server logs.'
        ]
    ]);
    exit;
}
