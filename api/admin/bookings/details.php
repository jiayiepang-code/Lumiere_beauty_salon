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
    // Get booking_id parameter
    $booking_id = isset($_GET['booking_id']) ? trim($_GET['booking_id']) : null;
    
    if ($booking_id === null || $booking_id === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'booking_id parameter is required'
            ]
        ]);
        exit;
    }
    
    // Fetch booking details with customer information
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
                b.created_at,
                b.updated_at
            FROM Booking b
            INNER JOIN Customer c ON b.customer_email = c.customer_email
            WHERE b.booking_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Booking not found'
            ]
        ]);
        exit;
    }
    
    $booking = $result->fetch_assoc();
    $booking['customer_name'] = $booking['customer_first_name'] . ' ' . $booking['customer_last_name'];
    $booking['discount_amount'] = (float)$booking['discount_amount'];
    $booking['total_price'] = (float)$booking['total_price'];
    
    $stmt->close();
    
    // Fetch services for this booking
    $service_sql = "SELECT 
                        bs.booking_service_id,
                        bs.service_id,
                        s.service_name,
                        s.service_category,
                        s.sub_category,
                        s.description as service_description,
                        bs.staff_email,
                        st.first_name as staff_first_name,
                        st.last_name as staff_last_name,
                        st.phone as staff_phone,
                        st.role as staff_role,
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
                    WHERE bs.booking_id = ?
                    ORDER BY bs.sequence_order";
    
    $service_stmt = $conn->prepare($service_sql);
    $service_stmt->bind_param("s", $booking_id);
    $service_stmt->execute();
    $service_result = $service_stmt->get_result();
    
    $services = [];
    while ($service_row = $service_result->fetch_assoc()) {
        $service_row['staff_name'] = $service_row['staff_first_name'] . ' ' . $service_row['staff_last_name'];
        $service_row['quoted_price'] = (float)$service_row['quoted_price'];
        $service_row['quoted_duration_minutes'] = (int)$service_row['quoted_duration_minutes'];
        $service_row['quoted_cleanup_minutes'] = (int)$service_row['quoted_cleanup_minutes'];
        $service_row['quantity'] = (int)$service_row['quantity'];
        $service_row['sequence_order'] = (int)$service_row['sequence_order'];
        $service_row['booking_service_id'] = (int)$service_row['booking_service_id'];
        
        unset($service_row['staff_first_name']);
        unset($service_row['staff_last_name']);
        
        $services[] = $service_row;
    }
    
    $service_stmt->close();
    
    $booking['services'] = $services;
    
    // Fetch customer's booking history (excluding current booking)
    $history_sql = "SELECT 
                        b.booking_id,
                        b.booking_date,
                        b.start_time,
                        b.status,
                        b.total_price,
                        COUNT(bs.booking_service_id) as service_count
                    FROM Booking b
                    LEFT JOIN Booking_Service bs ON b.booking_id = bs.booking_id
                    WHERE b.customer_email = ? AND b.booking_id != ?
                    GROUP BY b.booking_id
                    ORDER BY b.booking_date DESC, b.start_time DESC
                    LIMIT 10";
    
    $history_stmt = $conn->prepare($history_sql);
    $history_stmt->bind_param("ss", $booking['customer_email'], $booking_id);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    
    $booking_history = [];
    while ($history_row = $history_result->fetch_assoc()) {
        $history_row['total_price'] = (float)$history_row['total_price'];
        $history_row['service_count'] = (int)$history_row['service_count'];
        $booking_history[] = $history_row;
    }
    
    $history_stmt->close();
    
    $booking['booking_history'] = $booking_history;
    
    // Calculate total duration
    $total_duration = 0;
    foreach ($services as $service) {
        $total_duration += ($service['quoted_duration_minutes'] + $service['quoted_cleanup_minutes']) * $service['quantity'];
    }
    $booking['total_duration_minutes'] = $total_duration;
    
    $conn->close();
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'booking' => $booking
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
            'code' => 'DATABASE_ERROR',
            'message' => 'An error occurred while fetching booking details'
        ]
    ]);
}
