<?php
session_start();
require_once '../config/database.php';
require_once '../includes/EmailService.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['customer_email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['booking_date', 'start_time', 'service_ids', 'total_price'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $db->beginTransaction();
    
    // 1. Generate unique booking ID
    $booking_id = generateBookingId($db);
    
    // 2. Calculate expected finish time (sum of all service durations + cleanup)
    $total_minutes = calculateTotalDuration($db, $data['service_ids']);
    $expected_finish = date('H:i:s', strtotime($data['start_time'] . " +{$total_minutes} minutes"));
    
    // 3. Insert booking
    $stmt = $db->prepare("
        INSERT INTO booking (
            booking_id, customer_email, booking_date, start_time, 
            expected_finish_time, status, total_price, remarks
        ) VALUES (?, ?, ?, ?, ?, 'confirmed', ?, ?)
    ");
    
    $stmt->execute([
        $booking_id,
        $_SESSION['customer_email'],
        $data['booking_date'],
        $data['start_time'],
        $expected_finish,
        $data['total_price'],
        $data['remarks'] ?? null
    ]);
    
    // 4. Insert booking_service records (services + staff assignments)
    insertBookingServices($db, $booking_id, $data['services']);
    
    // 5. Get customer details for email
    $customer = getCustomerDetails($db, $_SESSION['customer_email']);
    
    // 6. Send confirmation email IMMEDIATELY
    $emailService = new EmailService();
    $emailData = [
        'booking_id' => $booking_id,
        'customer_email' => $customer['customer_email'],
        'customer_name' => $customer['first_name'] . ' ' . $customer['last_name'],
        'booking_date' => $data['booking_date'],
        'start_time' => $data['start_time'],
        'total_price' => number_format($data['total_price'], 2),
        'location' => SALON_LOCATION,
        'address' => SALON_ADDRESS,
        'phone' => SALON_PHONE
    ];
    
    $emailResult = $emailService->sendConfirmationEmail($emailData);
    
    if (!$emailResult['success']) {
        // Log error but don't fail booking
        error_log("Confirmation email failed for booking $booking_id: " . $emailResult['error']);
    }
    
    // 7. Schedule reminder email (24 hours before booking)
    $reminderTime = calculateReminderTime($data['booking_date'], $data['start_time']);
    
    $stmt = $db->prepare("
        INSERT INTO email_queue (
            booking_id, email_type, recipient_email, scheduled_at
        ) VALUES (?, 'reminder', ?, ?)
    ");
    
    $stmt->execute([
        $booking_id,
        $customer['customer_email'],
        $reminderTime
    ]);
    
    $db->commit();
    
    // Return success
    echo json_encode([
        'success' => true,
        'booking_id' => $booking_id,
        'confirmation_sent' => $emailResult['success'],
        'reminder_scheduled' => $reminderTime
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// Helper functions

function generateBookingId($db) {
    // Format: BK + YYMMDD + 001
    $prefix = 'BK' . date('ymd');
    
    $stmt = $db->prepare("
        SELECT booking_id FROM booking 
        WHERE booking_id LIKE ? 
        ORDER BY booking_id DESC LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    
    if ($last) {
        $num = intval(substr($last, -3)) + 1;
    } else {
        $num = 1;
    }
    
    return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
}

function calculateTotalDuration($db, $serviceIds) {
    $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
    $stmt = $db->prepare("
        SELECT SUM(current_duration_minutes + default_cleanup_minutes) as total
        FROM service
        WHERE service_id IN ($placeholders)
    ");
    $stmt->execute($serviceIds);
    return $stmt->fetchColumn() ?: 0;
}

function insertBookingServices($db, $bookingId, $services) {
    $stmt = $db->prepare("
        INSERT INTO booking_service (
            booking_id, service_id, staff_email, quoted_price, 
            quoted_duration_minutes, quoted_cleanup_minutes, 
            quantity, sequence_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($services as $index => $service) {
        $stmt->execute([
            $bookingId,
            $service['service_id'],
            $service['staff_email'],
            $service['price'],
            $service['duration'],
            $service['cleanup'],
            $service['quantity'] ?? 1,
            $index + 1
        ]);
    }
}

function getCustomerDetails($db, $email) {
    $stmt = $db->prepare("
        SELECT customer_email, first_name, last_name, phone
        FROM customer WHERE customer_email = ?
    ");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function calculateReminderTime($bookingDate, $startTime) {
    // Booking datetime - 24 hours
    $bookingDateTime = new DateTime("$bookingDate $startTime");
    $reminderDateTime = clone $bookingDateTime;
    $reminderDateTime->modify('-24 hours');
    return $reminderDateTime->format('Y-m-d H:i:s');
}
?>