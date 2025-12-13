<?php
// send_reminder_script.php - To be run daily via a Cron Job

// 1. Include Configuration and PHPMailer classes
require_once 'config.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- STEP 1: Connect to Database ---
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// Get tomorrow's date in YYYY-MM-DD format
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// --- STEP 2: Find bookings for tomorrow that are 'confirmed' ---
$sql = "SELECT B.booking_id, B.customer_email, B.booking_date, B.start_time, C.first_name, B.total_price 
        FROM Booking B
        JOIN Customer C ON B.customer_email = C.customer_email
        WHERE B.booking_date = ? AND B.status = 'confirmed'"; // Check for confirmed status

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tomorrow);
$stmt->execute();
$result = $stmt->get_result();

$reminders_sent_count = 0;

if ($result->num_rows > 0) {
    while ($booking = $result->fetch_assoc()) {
        
        // --- STEP 3: Fetch service details for the reminder email (same as above) ---
        $service_details = [];
        $service_sql = "SELECT T2.service_name FROM Booking_Service T1 
                        JOIN Service T2 ON T1.service_id = T2.service_id 
                        WHERE T1.booking_id = ?";
        
        $service_stmt = $conn->prepare($service_sql);
        $service_stmt->bind_param("s", $booking['booking_id']);
        $service_stmt->execute();
        $service_result = $service_stmt->get_result();
        while ($row = $service_result->fetch_assoc()) {
            $service_details[] = $row;
        }
        $service_stmt->close();
        
        // --- STEP 4: Send Reminder Email ---
        if (sendReminderEmail(
            $booking['customer_email'],
            $booking['first_name'],
            $booking['booking_id'],
            $booking['booking_date'],
            $booking['start_time'],
            $service_details
        )) {
            $reminders_sent_count++;
        }
    }
}

$stmt->close();
$conn->close();

echo "Script finished. Sent $reminders_sent_count reminders for bookings on $tomorrow.";

// --- Reminder Email Function ---
function sendReminderEmail($to_email, $to_name, $id, $date, $time, $service_list) {
    $mail = new PHPMailer(true);
    // ... PHPMailer setup (Host, Username, Password, etc.) ...
    
    try {
        // ... Server settings and Recipients setup ... 

        // Start building the simple service list
        $service_list_text = "";
        foreach ($service_list as $service) {
            $service_list_text .= "- {$service['service_name']}\n";
        }
        
        $mail->isHTML(false); // Can be simple plain text for a reminder
        $mail->Subject = '⏰ Friendly Reminder: Your Lumiere Salon Booking Tomorrow';
        
        $mail->Body = "Hello $to_name,\n\n"
            . "This is a friendly 24-hour reminder for your reservation at Lumiere Beauty Salon.\n\n"
            . "--------------------------------\n"
            . "Booking ID: $id\n"
            . "Date: $date\n"
            . "Time: " . substr($time, 0, 5) . "\n"
            . "Services:\n$service_list_text"
            . "--------------------------------\n\n"
            . "If you need to reschedule or cancel, please log into your account immediately.\n\n"
            . "See you soon!\nThe Lumiere Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Reminder Email failed for booking $id. Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>