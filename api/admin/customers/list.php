<?php
// Include required files first
require_once '../../../config/config.php';
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';

// Enable error logging
error_log("=== Customer List API Called ===");
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));

header('Content-Type: application/json');

// Check authentication
$isAuth = isAdminAuthenticated();
error_log("isAdminAuthenticated: " . ($isAuth ? 'true' : 'false'));

if (!$isAuth) {
    error_log("Authentication failed - returning 401");
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'AUTH_REQUIRED',
            'message' => 'Authentication required',
            'debug' => [
                'session_id' => session_id(),
                'has_admin_session' => isset($_SESSION['admin']),
                'session_keys' => array_keys($_SESSION)
            ]
        ]
    ]);
    exit;
}

error_log("Authentication successful - proceeding with query");

error_log("Authentication successful - proceeding with query");

try {
    $conn = getDBConnection();
    error_log("Database connection established");
    
    // Query to get customers with stats
    // We join with booking table to calculate totals
    // Use explicit GROUP BY listing all non-aggregated columns to satisfy ONLY_FULL_GROUP_BY
    $sql = "
        SELECT 
            c.customer_email AS email,
            c.first_name,
            c.last_name,
            c.phone,
            c.created_at,
            COUNT(b.booking_id) AS total_bookings,
            COALESCE(SUM(CASE WHEN b.status IN ('completed', 'confirmed') THEN b.total_price ELSE 0 END), 0) AS total_spent,
            MAX(b.booking_date) AS last_visit
        FROM customer c
        LEFT JOIN booking b ON c.customer_email = b.customer_email
        GROUP BY c.customer_email, c.first_name, c.last_name, c.phone, c.created_at
        ORDER BY c.last_name ASC, c.first_name ASC
    ";
    
    error_log("Executing query: " . $sql);
    $result = $conn->query($sql);
    
    $customers = [];
    if ($result) {
        error_log("Query successful, row count: " . $result->num_rows);
        while ($row = $result->fetch_assoc()) {
            $customers[] = [
                'email' => $row['email'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'created_at' => $row['created_at'],
                'total_bookings' => isset($row['total_bookings']) ? (int)$row['total_bookings'] : 0,
                'total_spent' => isset($row['total_spent']) ? (float)$row['total_spent'] : 0.0,
                'last_visit' => $row['last_visit']
            ];
        }
    } else {
        error_log("Query failed: " . $conn->error);
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    error_log("Returning " . count($customers) . " customers");
    echo json_encode([
        'success' => true,
        'customers' => $customers,
        'count' => count($customers)
    ]);

} catch (Exception $e) {
    error_log("Exception caught: " . $e->getMessage());
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
