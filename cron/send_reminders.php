<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/send_email.php';

$tomorrow = date('Y-m-d', strtotime('+1 day'));

// 1. Get bookings that need reminders
$sql = "SELECT b.*, u.email, u.first_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.booking_date = ?
        AND b.reminder_sent = 0";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tomorrow);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $email = $row['email'];
    $name  = $row['first_name'];
    $service = $row['service'];
    $date = $row['booking_date'];
    $time = $row['booking_time'];

    // Email content
    $subject = "Reminder: Your Appointment at Lumière Beauty Salon";
    $body = "
        <h2>Hello {$name},</h2>
        <p>This is a friendly reminder of your appointment tomorrow:</p>
        <p><b>Service:</b> {$service}<br>
           <b>Date:</b> {$date}<br>
           <b>Time:</b> {$time}</p>
        <p>We look forward to seeing you 💕</p>
        <p><i>Lumière Beauty Salon</i></p>
    ";

    // Send email
    if (sendEmail($email, $subject, $body)) {
        // Mark as sent
        $update = $conn->prepare("UPDATE bookings SET reminder_sent = 1 WHERE id = ?");
        $update->bind_param("i", $row['id']);
        $update->execute();
    }
}

echo "Reminder check completed.";
