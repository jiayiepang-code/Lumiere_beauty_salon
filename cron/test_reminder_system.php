<?php
/**
 * Test Script: Verify Reminder Email System
 * 
 * This script helps verify that:
 * 1. The email_queue table exists
 * 2. Reminders are being scheduled correctly
 * 3. The cron job can process pending reminders
 * 
 * Run this manually: php cron/test_reminder_system.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

echo "=== Reminder Email System Test ===\n\n";

// 1. Check if email_queue table exists
echo "1. Checking if email_queue table exists...\n";
try {
    $checkTableQuery = "SHOW TABLES LIKE 'email_queue'";
    $checkStmt = $db->prepare($checkTableQuery);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        echo "   ✓ email_queue table exists\n";
    } else {
        echo "   ✗ email_queue table does NOT exist\n";
        echo "   → Please run: database/email_queue.sql to create the table\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ✗ Error checking table: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Check pending reminders
echo "\n2. Checking pending reminders...\n";
try {
    $pendingQuery = "SELECT COUNT(*) as count FROM email_queue WHERE status = 'pending' AND email_type = 'reminder'";
    $pendingStmt = $db->prepare($pendingQuery);
    $pendingStmt->execute();
    $pendingResult = $pendingStmt->fetch(PDO::FETCH_ASSOC);
    $pendingCount = $pendingResult['count'];
    
    echo "   Found {$pendingCount} pending reminder(s)\n";
    
    if ($pendingCount > 0) {
        // Show next 5 pending reminders
        $listQuery = "SELECT booking_id, recipient_email, scheduled_at 
                      FROM email_queue 
                      WHERE status = 'pending' AND email_type = 'reminder'
                      ORDER BY scheduled_at ASC 
                      LIMIT 5";
        $listStmt = $db->prepare($listQuery);
        $listStmt->execute();
        $reminders = $listStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Next reminders to send:\n";
        foreach ($reminders as $reminder) {
            $scheduled = new DateTime($reminder['scheduled_at']);
            $now = new DateTime();
            $diff = $now->diff($scheduled);
            
            if ($scheduled <= $now) {
                $status = "READY NOW";
            } else {
                $status = "in " . $diff->format('%h hours, %i minutes');
            }
            
            echo "   - Booking {$reminder['booking_id']}: {$reminder['recipient_email']} ({$status})\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error checking pending reminders: " . $e->getMessage() . "\n";
}

// 3. Check sent reminders
echo "\n3. Checking sent reminders...\n";
try {
    $sentQuery = "SELECT COUNT(*) as count FROM email_queue WHERE status = 'sent' AND email_type = 'reminder'";
    $sentStmt = $db->prepare($sentQuery);
    $sentStmt->execute();
    $sentResult = $sentStmt->fetch(PDO::FETCH_ASSOC);
    $sentCount = $sentResult['count'];
    
    echo "   Total sent reminders: {$sentCount}\n";
} catch (Exception $e) {
    echo "   ✗ Error checking sent reminders: " . $e->getMessage() . "\n";
}

// 4. Check failed reminders
echo "\n4. Checking failed reminders...\n";
try {
    $failedQuery = "SELECT COUNT(*) as count FROM email_queue WHERE status = 'failed' AND email_type = 'reminder'";
    $failedStmt = $db->prepare($failedQuery);
    $failedStmt->execute();
    $failedResult = $failedStmt->fetch(PDO::FETCH_ASSOC);
    $failedCount = $failedResult['count'];
    
    echo "   Total failed reminders: {$failedCount}\n";
    
    if ($failedCount > 0) {
        // Show recent failures
        $failuresQuery = "SELECT booking_id, recipient_email, error_message, retry_count 
                          FROM email_queue 
                          WHERE status = 'failed' AND email_type = 'reminder'
                          ORDER BY created_at DESC 
                          LIMIT 3";
        $failuresStmt = $db->prepare($failuresQuery);
        $failuresStmt->execute();
        $failures = $failuresStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Recent failures:\n";
        foreach ($failures as $failure) {
            $error = substr($failure['error_message'] ?? 'Unknown error', 0, 50);
            echo "   - Booking {$failure['booking_id']}: {$error} (retries: {$failure['retry_count']})\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error checking failed reminders: " . $e->getMessage() . "\n";
}

// 5. Check if cron job file exists
echo "\n5. Checking cron job setup...\n";
$cronFile = __DIR__ . '/send_reminder_emails.php';
if (file_exists($cronFile)) {
    echo "   ✓ Cron job file exists: cron/send_reminder_emails.php\n";
    echo "   → To set up Windows Task Scheduler:\n";
    echo "     1. Open Task Scheduler\n";
    echo "     2. Create Basic Task\n";
    echo "     3. Set trigger: Daily or Hourly\n";
    echo "     4. Action: Start a program\n";
    echo "     5. Program: C:\\xampp\\php\\php.exe\n";
    echo "     6. Arguments: \"C:\\xampp\\htdocs\\Lumiere_beauty_salon\\cron\\send_reminder_emails.php\"\n";
} else {
    echo "   ✗ Cron job file not found\n";
}

// 6. Check EmailService
echo "\n6. Checking EmailService...\n";
$emailServiceFile = __DIR__ . '/../includes/EmailService.php';
if (file_exists($emailServiceFile)) {
    echo "   ✓ EmailService.php exists\n";
    
    require_once $emailServiceFile;
    if (class_exists('EmailService')) {
        echo "   ✓ EmailService class is available\n";
        
        // Check if sendReminderEmail method exists
        $reflection = new ReflectionClass('EmailService');
        if ($reflection->hasMethod('sendReminderEmail')) {
            echo "   ✓ sendReminderEmail method exists\n";
        } else {
            echo "   ✗ sendReminderEmail method NOT found\n";
        }
    } else {
        echo "   ✗ EmailService class not found\n";
    }
} else {
    echo "   ✗ EmailService.php not found\n";
}

echo "\n=== Test Complete ===\n";
echo "\nNext steps:\n";
echo "1. If email_queue table doesn't exist, run: database/email_queue.sql\n";
echo "2. Create a test booking to verify reminder scheduling\n";
echo "3. Set up the cron job (Windows Task Scheduler) to run hourly\n";
echo "4. Monitor the cron job output to ensure reminders are being sent\n";

?>

