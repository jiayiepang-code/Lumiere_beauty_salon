<?php
/**
 * Send booking reminders 24 hours before appointment
 * This script should be run periodically (e.g., via cron job every hour)
 */

require_once 'config/database.php';
require 'mailer.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    error_log('Reminder script running - Looking for bookings for tomorrow');
    
    // Find confirmed bookings for tomorrow (24 hours ahead)
    // Since this script should run hourly, it will catch all bookings for tomorrow
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    $query = "SELECT DISTINCT b.booking_id, 
                     b.customer_email,
                     b.booking_date,
                     b.start_time,
                     b.expected_finish_time,
                     b.total_price,
                     c.first_name, 
                     c.last_name
              FROM booking b
              JOIN customer c ON b.customer_email = c.customer_email
              WHERE b.status = 'confirmed'
              AND b.booking_date = ?
              ORDER BY b.start_time";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$tomorrow]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log('Found ' . count($bookings) . ' bookings to send reminders for');
    
    $sentCount = 0;
    $failedCount = 0;
    
    foreach ($bookings as $booking) {
        try {
            $customerName = trim(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? ''));
            $bookingDate = new DateTime($booking['booking_date'] . ' ' . $booking['start_time']);
            $startTime = new DateTime($booking['booking_date'] . ' ' . $booking['start_time']);
            $endTime = new DateTime($booking['booking_date'] . ' ' . $booking['expected_finish_time']);
            
            // Get detailed services list
            $servicesQuery = "SELECT s.service_name, 
                                     COALESCE(CONCAT(st.first_name, ' ', st.last_name), 'No Preference') as staff_name,
                                     bs.quoted_price
                              FROM booking_service bs
                              JOIN service s ON bs.service_id = s.service_id
                              LEFT JOIN staff st ON bs.staff_email = st.staff_email
                              WHERE bs.booking_id = ?";
            $servicesStmt = $db->prepare($servicesQuery);
            $servicesStmt->execute([$booking['booking_id']]);
            $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $servicesList = '';
            foreach ($services as $service) {
                $servicesList .= '<li>' . htmlspecialchars($service['service_name']) . ' - ' . htmlspecialchars($service['staff_name']) . ' (RM ' . number_format($service['quoted_price'], 2) . ')</li>';
            }
            
            $subject = 'Reminder: Your Appointment Tomorrow - ' . $booking['booking_id'];
            $emailBody = '<div style="font-family: Roboto, Arial, sans-serif; background: #f4f8fb; padding: 32px 0;">
                <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 32px 24px;">
                    <h2 style="color: #1976d2; margin-bottom: 16px; text-align: center;">Lumière Beauty Salon</h2>
                    <h3 style="color: #333; margin-bottom: 24px;">Appointment Reminder</h3>
                    <p style="color: #333; font-size: 1rem; margin-bottom: 16px;">Dear ' . htmlspecialchars($customerName) . ',</p>
                    <p style="color: #333; font-size: 1rem; margin-bottom: 24px;">This is a friendly reminder that you have an appointment with us tomorrow:</p>
                    
                    <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin-bottom: 24px;">
                        <p style="margin: 0 0 12px 0;"><strong>Booking ID:</strong> ' . htmlspecialchars($booking['booking_id']) . '</p>
                        <p style="margin: 0 0 12px 0;"><strong>Date:</strong> ' . $bookingDate->format('l, d M Y') . '</p>
                        <p style="margin: 0 0 12px 0;"><strong>Time:</strong> ' . $startTime->format('h:i A') . ' - ' . $endTime->format('h:i A') . '</p>
                        <p style="margin: 0 0 12px 0;"><strong>Services:</strong></p>
                        <ul style="margin: 0 0 12px 0; padding-left: 20px;">' . $servicesList . '</ul>
                        <p style="margin: 0;"><strong>Total:</strong> RM ' . number_format($booking['total_price'], 2) . '</p>
                    </div>
                    
                    <p style="color: #888; font-size: 0.95rem; margin-top: 24px;">We look forward to seeing you!</p>
                    <p style="color: #888; font-size: 0.95rem; margin-top: 16px;">If you need to cancel or reschedule, please contact us at least 24 hours in advance.</p>
                    <p style="color: #888; font-size: 0.95rem; margin-top: 32px;">Thank you for choosing Lumière Beauty Salon!<br><br>— Lumière Beauty Salon Team</p>
                </div>
            </div>';
            
            $emailResult = sendMail($booking['customer_email'], $subject, $emailBody);
            
            if ($emailResult === true) {
                $sentCount++;
                error_log('Reminder sent successfully for booking ' . $booking['booking_id']);
            } else {
                $failedCount++;
                error_log('Failed to send reminder for booking ' . $booking['booking_id'] . ': ' . $emailResult);
            }
        } catch (Exception $e) {
            $failedCount++;
            error_log('Exception sending reminder for booking ' . $booking['booking_id'] . ': ' . $e->getMessage());
        }
    }
    
    echo "Reminder script completed. Sent: $sentCount, Failed: $failedCount\n";
    error_log("Reminder script completed. Sent: $sentCount, Failed: $failedCount");
    
} catch (Exception $e) {
    error_log('Reminder script error: ' . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

?>



