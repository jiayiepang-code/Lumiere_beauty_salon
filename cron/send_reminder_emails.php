<?php
/**
 * Cron Job: Send Reminder Emails
 * 
 * Run this every hour via crontab:
 * 0 * * * * /usr/bin/php /path/to/cron/send_reminder_emails.php
 * 
 * For Windows Task Scheduler:
 * Create a scheduled task that runs: php.exe "C:\xampp\htdocs\Lumiere_beauty_salon\cron\send_reminder_emails.php"
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../includes/EmailService.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

echo "[" . date('Y-m-d H:i:s') . "] Starting reminder email job...\n";

try {
    // Find pending reminder emails that are due
    $stmt = $db->prepare("
        SELECT 
            eq.queue_id,
            eq.booking_id,
            eq.recipient_email,
            b.booking_date,
            b.start_time,
            b.total_price,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name
        FROM email_queue eq
        JOIN booking b ON eq.booking_id = b.booking_id
        JOIN customer c ON b.customer_email = c.customer_email
        WHERE eq.email_type = 'reminder'
          AND eq.status = 'pending'
          AND eq.scheduled_at <= NOW()
          AND b.status = 'confirmed'
        ORDER BY eq.scheduled_at ASC
        LIMIT 50
    ");
    
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($emails) . " reminder emails to send\n";
    
    $emailService = new EmailService();
    $sent = 0;
    $failed = 0;
    
    foreach ($emails as $email) {
        echo "Sending reminder for booking {$email['booking_id']}... ";
        
        $emailData = [
            'booking_id' => $email['booking_id'],
            'customer_email' => $email['recipient_email'],
            'customer_name' => $email['customer_name'],
            'booking_date' => $email['booking_date'],
            'start_time' => $email['start_time'],
            'total_price' => number_format($email['total_price'], 2),
            'location' => SALON_LOCATION,
            'address' => SALON_ADDRESS,
            'phone' => SALON_PHONE
        ];
        
        $result = $emailService->sendReminderEmail($emailData);
        
        if ($result['success']) {
            // Mark as sent
            $updateStmt = $db->prepare("
                UPDATE email_queue 
                SET status = 'sent', sent_at = NOW()
                WHERE queue_id = ?
            ");
            $updateStmt->execute([$email['queue_id']]);
            
            echo "✓ Sent\n";
            $sent++;
            
        } else {
            // Mark as failed and increment retry count
            $updateStmt = $db->prepare("
                UPDATE email_queue 
                SET status = 'failed', 
                    retry_count = retry_count + 1,
                    error_message = ?
                WHERE queue_id = ?
            ");
            $updateStmt->execute([$result['error'], $email['queue_id']]);
            
            echo "✗ Failed: {$result['error']}\n";
            $failed++;
        }
        
        // Small delay to avoid rate limiting
        usleep(500000); // 0.5 seconds
    }
    
    echo "\n=== Summary ===\n";
    echo "Sent: $sent\n";
    echo "Failed: $failed\n";
    echo "Completed at " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>