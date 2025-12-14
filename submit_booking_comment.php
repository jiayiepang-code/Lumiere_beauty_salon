<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['customer_phone']) && !isset($_SESSION['customer_email'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in. Please log in again.']);
    exit();
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if(!$input || empty($input['booking_id']) || empty($input['comment'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
    exit();
}

$bookingId = $input['booking_id'];
$comment = trim($input['comment']);

if(empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get customer email from session
    $customerEmail = $_SESSION['customer_email'] ?? $_SESSION['user_email'] ?? null;
    if (!$customerEmail && isset($_SESSION['customer_phone'])) {
        $emailQuery = "SELECT customer_email FROM customer WHERE phone = ? LIMIT 1";
        $emailStmt = $db->prepare($emailQuery);
        $emailStmt->execute([$_SESSION['customer_phone']]);
        $emailRow = $emailStmt->fetch(PDO::FETCH_ASSOC);
        $customerEmail = $emailRow['customer_email'] ?? null;
    }

    if (!$customerEmail) {
        echo json_encode(['success' => false, 'message' => 'Customer information not found.']);
        exit();
    }
    
    // Verify the booking belongs to the customer and is completed
    $checkQuery = "SELECT status FROM booking WHERE booking_id = ? AND customer_email = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$bookingId, $customerEmail]);
    $booking = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit();
    }
    
    if(strtolower($booking['status']) !== 'completed') {
        echo json_encode(['success' => false, 'message' => 'Comments can only be added for completed bookings.']);
        exit();
    }
    
    // Update booking with comment (using remarks field)
    // Append to existing remarks if there are any, or replace if empty
    $existingRemarks = '';
    $getRemarksQuery = "SELECT remarks FROM booking WHERE booking_id = ? AND customer_email = ?";
    $getRemarksStmt = $db->prepare($getRemarksQuery);
    $getRemarksStmt->execute([$bookingId, $customerEmail]);
    $existingRow = $getRemarksStmt->fetch(PDO::FETCH_ASSOC);
    if ($existingRow && !empty($existingRow['remarks'])) {
        $existingRemarks = $existingRow['remarks'] . "\n\n--- Customer Comment ---\n";
    }
    
    $finalComment = $existingRemarks . $comment . "\n\nSubmitted: " . date('d M Y h:i A');
    
    $updateQuery = "UPDATE booking SET remarks = ?, updated_at = NOW() WHERE booking_id = ? AND customer_email = ?";
    $updateStmt = $db->prepare($updateQuery);
    $success = $updateStmt->execute([$finalComment, $bookingId, $customerEmail]);
    
    if($success) {
        echo json_encode(['success' => true, 'message' => 'Comment submitted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save comment.']);
    }
    
} catch(Exception $e) {
    error_log('Comment submission error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>