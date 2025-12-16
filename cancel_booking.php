<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['customer_phone'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if(!$input || !isset($input['booking_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$bookingId = $input['booking_id'];
$phone = $_SESSION['customer_phone'];

$database = new Database();
$db = $database->getConnection();

try {
    // Get customer email from phone
    $emailQuery = "SELECT customer_email FROM customer WHERE phone = ? LIMIT 1";
    $emailStmt = $db->prepare($emailQuery);
    $emailStmt->execute([$phone]);
    $emailRow = $emailStmt->fetch(PDO::FETCH_ASSOC);
    $customerEmail = $emailRow['customer_email'] ?? null;
    
    if (!$customerEmail) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit();
    }
    
    // Verify the booking belongs to the customer and can be cancelled
    $query = "SELECT b.booking_id, b.booking_date, b.status 
              FROM booking b
              WHERE b.booking_id = ? AND b.customer_email = ? AND b.status = 'confirmed'";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$bookingId, $customerEmail]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found or cannot be cancelled']);
        exit();
    }
    
    // Check if booking date is in the past
    if($booking['booking_date'] < date('Y-m-d')) {
        echo json_encode(['success' => false, 'message' => 'Cannot cancel past bookings']);
        exit();
    }
    
    // Update booking status to cancelled
    $query = "UPDATE booking SET status = 'cancelled', updated_at = NOW() WHERE booking_id = ?";
    $stmt = $db->prepare($query);
    
    if($stmt->execute([$bookingId])) {
        echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel booking']);
    }
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>