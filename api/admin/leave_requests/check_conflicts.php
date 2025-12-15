<?php
require_once '../../../config/config.php';
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

if (!isAdminAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required'
    ]);
    exit;
}

$requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

if ($requestId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request ID'
    ]);
    exit;
}

try {
    $conn = getDBConnection();

    // Load the leave request
    $stmt = $conn->prepare("
        SELECT id, staff_email, start_date, end_date
        FROM leave_requests
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare select: ' . $conn->error);
    }
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();
    $stmt->close();

    if (!$request) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Leave request not found'
        ]);
        exit;
    }

    $staffEmail = $request['staff_email'];
    $startDate = $request['start_date'];
    $endDate = $request['end_date'];

    // Check for conflicting bookings
    $conflictSql = "
        SELECT 
            b.booking_id,
            b.booking_date,
            b.start_time,
            b.customer_email,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.phone as customer_phone,
            GROUP_CONCAT(DISTINCT s.service_name ORDER BY bs.sequence_order SEPARATOR ', ') as services
        FROM Booking_Service bs
        JOIN Booking b ON bs.booking_id = b.booking_id
        JOIN Customer c ON b.customer_email = c.customer_email
        JOIN Service s ON bs.service_id = s.service_id
        WHERE bs.staff_email = ?
          AND b.booking_date BETWEEN ? AND ?
          AND b.status IN ('confirmed', 'completed')
        GROUP BY b.booking_id, b.booking_date, b.start_time, b.customer_email, c.first_name, c.last_name, c.phone
        ORDER BY b.booking_date, b.start_time
    ";

    $conflictStmt = $conn->prepare($conflictSql);
    if (!$conflictStmt) {
        throw new Exception('Failed to prepare conflict check: ' . $conn->error);
    }
    $conflictStmt->bind_param('sss', $staffEmail, $startDate, $endDate);
    $conflictStmt->execute();
    $conflictResult = $conflictStmt->get_result();
    
    $conflictingBookings = [];
    while ($row = $conflictResult->fetch_assoc()) {
        $conflictingBookings[] = [
            'booking_id' => $row['booking_id'],
            'booking_date' => $row['booking_date'],
            'start_time' => $row['start_time'],
            'customer_email' => $row['customer_email'],
            'customer_name' => $row['customer_name'],
            'customer_phone' => $row['customer_phone'],
            'services' => $row['services']
        ];
    }
    $conflictStmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'has_conflicts' => !empty($conflictingBookings),
        'conflict_count' => count($conflictingBookings),
        'conflicting_bookings' => $conflictingBookings,
        'staff_email' => $staffEmail,
        'leave_dates' => [
            'start' => $startDate,
            'end' => $endDate
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

