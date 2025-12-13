<?php
// Start session with secure configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);
session_start();

header('Content-Type: application/json');

// Include database connection
require_once '../../../config/config.php';
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

try {
    $conn = getDBConnection();
    
    // Query to get customers with stats
    // We join with Booking table to calculate totals
    $sql = "
        SELECT 
            c.customer_email as email,
            c.first_name,
            c.last_name,
            c.phone,
            c.created_at,
            COUNT(b.booking_id) as total_bookings,
            SUM(CASE WHEN b.status IN ('completed', 'confirmed') THEN b.total_price ELSE 0 END) as total_spent,
            MAX(b.booking_date) as last_visit
        FROM Customer c
        LEFT JOIN Booking b ON c.customer_email = b.customer_email
        GROUP BY c.customer_email
        ORDER BY c.created_at DESC
    ";
    
    $result = $conn->query($sql);
    
    $customers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $customers[] = [
                'email' => $row['email'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'created_at' => $row['created_at'],
                'total_bookings' => (int)$row['total_bookings'],
                'total_spent' => (float)$row['total_spent'],
                'last_visit' => $row['last_visit']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'customers' => $customers,
        'count' => count($customers)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
