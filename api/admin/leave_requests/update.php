<?php
require_once '../../../config/config.php';
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Read JSON body
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON payload'
    ]);
    exit;
}

$requestId = isset($data['request_id']) ? (int)$data['request_id'] : 0;
$action = isset($data['action']) ? strtolower(trim($data['action'])) : '';

if ($requestId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request parameters'
    ]);
    exit;
}

try {
    $conn = getDBConnection();

    // Load the leave request, ensure it is pending
    $stmt = $conn->prepare("
        SELECT id, staff_email, leave_type, start_date, end_date, half_day, status
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

    if ($request['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Leave request has already been processed'
        ]);
        exit;
    }

    $staffEmail = $request['staff_email'];
    $startDate = $request['start_date'];
    $endDate = $request['end_date'];

    // Get staff name for email notifications
    $staffStmt = $conn->prepare("SELECT first_name, last_name FROM staff WHERE staff_email = ? LIMIT 1");
    $staffStmt->bind_param('s', $staffEmail);
    $staffStmt->execute();
    $staffResult = $staffStmt->get_result();
    $staffData = $staffResult->fetch_assoc();
    $staffStmt->close();
    $staffName = $staffData ? trim($staffData['first_name'] . ' ' . $staffData['last_name']) : 'Staff Member';

    if ($action === 'reject') {
        $stmt = $conn->prepare("
            UPDATE leave_requests
            SET status = 'rejected'
            WHERE id = ? AND status = 'pending'
        ");
        if (!$stmt) {
            throw new Exception('Failed to prepare update: ' . $conn->error);
        }
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            throw new Exception('Failed to update leave request');
        }

        echo json_encode([
            'success' => true,
            'status' => 'rejected',
            'request_id' => $requestId
        ]);
        exit;
    }

    // Check for conflicting bookings before approval
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
        $conflictingBookings[] = $row;
    }
    $conflictStmt->close();

    // Approve flow: update leave_requests and staff_schedule within a transaction
    $conn->begin_transaction();

    $stmt = $conn->prepare("
        UPDATE leave_requests
        SET status = 'approved'
        WHERE id = ? AND status = 'pending'
    ");
    if (!$stmt) {
        $conn->rollback();
        throw new Exception('Failed to prepare approval update: ' . $conn->error);
    }
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        $stmt->close();
        $conn->rollback();
        throw new Exception('Failed to update leave request status to approved');
    }
    $stmt->close();

    // Loop through each date in range and upsert staff_schedule as leave for full day
    $currentDate = new DateTimeImmutable($startDate);
    $endDateObj = new DateTimeImmutable($endDate);

    $insertSql = "
        INSERT INTO staff_schedule (staff_email, work_date, start_time, end_time, status)
        VALUES (?, ?, '10:00:00', '22:00:00', 'leave')
        ON DUPLICATE KEY UPDATE status = VALUES(status), start_time = VALUES(start_time), end_time = VALUES(end_time)
    ";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        $conn->rollback();
        throw new Exception('Failed to prepare schedule upsert: ' . $conn->error);
    }

    while ($currentDate <= $endDateObj) {
        $dateStr = $currentDate->format('Y-m-d');
        $insertStmt->bind_param('ss', $staffEmail, $dateStr);
        $insertStmt->execute();
        if ($insertStmt->errno) {
            $insertStmt->close();
            $conn->rollback();
            throw new Exception('Failed to update staff schedule: ' . $insertStmt->error);
        }
        $currentDate = $currentDate->modify('+1 day');
    }

    $insertStmt->close();

    // Handle conflicting bookings: mark them and send notifications
    $emailsSent = 0;
    $emailsFailed = 0;
    
    if (!empty($conflictingBookings)) {
        // Mark bookings in remarks field
        $bookingIds = array_column($conflictingBookings, 'booking_id');
        if (!empty($bookingIds)) {
            $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
            $markBookingsSql = "
                UPDATE Booking 
                SET remarks = CONCAT(
                    COALESCE(remarks, ''), 
                    ' | ⚠️ Staff on leave - needs rescheduling/reassignment'
                )
                WHERE booking_id IN ($placeholders)
            ";
            
            $markStmt = $conn->prepare($markBookingsSql);
            if ($markStmt) {
                $types = str_repeat('s', count($bookingIds));
                $markStmt->bind_param($types, ...$bookingIds);
                $markStmt->execute();
                $markStmt->close();
            }
        }

        // Send email notifications
        require_once '../../../includes/EmailService.php';
        require_once '../../../config/email_config.php'; // For SALON constants
        
        $emailService = new EmailService();
        $leaveDateRange = date('d M Y', strtotime($startDate));
        if ($startDate !== $endDate) {
            $leaveDateRange .= ' to ' . date('d M Y', strtotime($endDate));
        }
        
        foreach ($conflictingBookings as $booking) {
            $emailData = [
                'booking_id' => $booking['booking_id'],
                'customer_email' => $booking['customer_email'],
                'customer_name' => $booking['customer_name'],
                'booking_date' => $booking['booking_date'],
                'start_time' => $booking['start_time'],
                'services' => $booking['services'],
                'staff_name' => $staffName,
                'leave_dates' => $leaveDateRange,
                'phone' => defined('SALON_PHONE') ? SALON_PHONE : 'Contact salon',
                'location' => defined('SALON_LOCATION') ? SALON_LOCATION : 'Lumière Beauty Salon',
                'address' => defined('SALON_ADDRESS') ? SALON_ADDRESS : ''
            ];
            
            $emailResult = $emailService->sendBookingConflictEmail($emailData);
            if ($emailResult['success']) {
                $emailsSent++;
            } else {
                $emailsFailed++;
                error_log("Failed to send conflict email for booking {$booking['booking_id']}: " . ($emailResult['error'] ?? 'Unknown error'));
            }
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'status' => 'approved',
        'request_id' => $requestId,
        'staff_email' => $staffEmail,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'conflict_count' => count($conflictingBookings),
        'emails_sent' => $emailsSent,
        'emails_failed' => $emailsFailed
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->errno === 0) {
        // Attempt to rollback if a transaction is open
        try {
            $conn->rollback();
        } catch (Exception $ignored) {
        }
    }

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


