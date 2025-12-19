<?php
// API endpoint to check staff availability for all time slots on a specific date
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
    $duration = $input['duration'] ?? 0; // Duration in minutes
    $startHour = $input['start_hour'] ?? 10; // Shop open hour
    $endHour = $input['end_hour'] ?? 22; // Shop close hour
    
    if (empty($date) || empty($staffEmails) || !is_array($staffEmails) || $duration <= 0) {
        ob_end_clean();
        echo json_encode(['unavailable_slots' => [], 'error' => 'Missing required parameters']);
        exit;
    }
    
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
    
    // Check all time slots
    $unavailableSlots = [];
    
    for ($hour = $startHour; $hour < $endHour; $hour++) {
        for ($minute = 0; $minute < 60; $minute += 30) {
            $startTime = sprintf("%02d:%02d", $hour, $minute);
            $startMinutes = timeToMinutes($startTime);
            $endMinutes = $startMinutes + $duration;
            
            // Check if any of the selected staff are busy during this time slot
            $isUnavailable = false;
            
            foreach ($existingBookings as $booking) {
                $bookingStart = timeToMinutes($booking['start_time']);
                $bookingEnd = timeToMinutes($booking['expected_finish_time']);
                
                // Check for overlap: (StartA < EndB) and (EndA > StartB)
                if ($startMinutes < $bookingEnd && $endMinutes > $bookingStart) {
                    $isUnavailable = true;
                    break;
                }
            }
            
            if ($isUnavailable) {
                $unavailableSlots[] = $startTime;
            }
        }
    }
    
    ob_end_clean();
    echo json_encode([
        'unavailable_slots' => $unavailableSlots
    ]);
    exit;
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['unavailable_slots' => [], 'error' => 'Error checking availability: ' . $e->getMessage()]);
    exit;
}

function timeToMinutes($timeStr) {
    list($h, $m) = explode(':', $timeStr);
    return ($h * 60) + (int)$m;
}
?>










