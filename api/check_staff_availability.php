<?php
// API endpoint to check staff availability for specific date/time
// Suppress all output and errors to ensure clean JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once '../config/database.php';

ob_clean();
header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $date = $input['date'] ?? '';
    $staffEmails = $input['staff_emails'] ?? []; // Array of staff emails to check
    $startTime = $input['start_time'] ?? ''; // "10:00"
    $duration = $input['duration'] ?? 0; // Duration in minutes
    
    if (empty($date) || empty($staffEmails) || !is_array($staffEmails) || empty($startTime) || $duration <= 0) {
        ob_end_clean();
        echo json_encode(['available' => false, 'error' => 'Missing required parameters']);
        exit;
    }
    
    // Calculate end time
    $startMinutes = timeToMinutes($startTime);
    $endMinutes = $startMinutes + $duration;
    
    // Get all bookings for the selected staff on this date
    $placeholders = implode(',', array_fill(0, count($staffEmails), '?'));
    $query = "SELECT DISTINCT bs.staff_email, b.start_time, b.expected_finish_time
              FROM booking b
              JOIN booking_service bs ON b.booking_id = bs.booking_id
              WHERE b.booking_date = ?
              AND bs.staff_email IN ($placeholders)
              AND b.status != 'cancelled'
              AND b.status != 'completed'";
    
    $params = array_merge([$date], $staffEmails);
    $stmt = $db->prepare($query);
    
    if ($stmt === false) {
        throw new Exception('Failed to prepare query');
    }
    
    $stmt->execute($params);
    $existingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if any of the selected staff are busy during this time slot
    $isAvailable = true;
    $conflicts = [];
    
    foreach ($existingBookings as $booking) {
        $bookingStart = timeToMinutes($booking['start_time']);
        $bookingEnd = timeToMinutes($booking['expected_finish_time']);
        
        // Check for overlap: (StartA < EndB) and (EndA > StartB)
        if ($startMinutes < $bookingEnd && $endMinutes > $bookingStart) {
            $isAvailable = false;
            $conflicts[] = [
                'staff_email' => $booking['staff_email'],
                'start_time' => $booking['start_time'],
                'end_time' => $booking['expected_finish_time']
            ];
        }
    }
    
    ob_end_clean();
    echo json_encode([
        'available' => $isAvailable,
        'conflicts' => $conflicts
    ]);
    exit;
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['available' => false, 'error' => 'Error checking availability: ' . $e->getMessage()]);
    exit;
}

function timeToMinutes($timeStr) {
    list($h, $m) = explode(':', $timeStr);
    return ($h * 60) + (int)$m;
}
?>




