<?php
/**
 * Utility Script: Hash All Plain Text Passwords
 * 
 * This script will:
 * 1. Find all staff members with plain text passwords
 * 2. Hash them using password_hash()
 * 3. Update the database
 * 
 * WARNING: Run this script ONCE to upgrade all passwords.
 * After running, delete this file for security.
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

// Security: Only allow this to run if a secret key is provided
// Change this to a random string before running
$secret_key = 'hash_all_passwords_2024';
$provided_key = isset($_GET['key']) ? $_GET['key'] : '';

if ($provided_key !== $secret_key) {
    die('Access denied. Provide the correct key parameter.');
}

echo "<h2>Hashing Plain Text Passwords</h2>";
echo "<p>Starting password upgrade process...</p><hr>";

try {
    // Get all staff members
    $stmt = $pdo->query("SELECT staff_email, phone, password FROM Staff");
    $all_staff = $stmt->fetchAll();
    
    $total = count($all_staff);
    $hashed_count = 0;
    $plain_text_count = 0;
    $updated_count = 0;
    $errors = [];
    
    foreach ($all_staff as $staff) {
        $email = $staff['staff_email'];
        $phone = $staff['phone'];
        $password = $staff['password'];
        
        // Check if password is already hashed
        $is_hashed = (substr($password, 0, 4) === '$2y$' || 
                     substr($password, 0, 4) === '$2a$' || 
                     substr($password, 0, 4) === '$2b$' ||
                     substr($password, 0, 1) === '$');
        
        if ($is_hashed) {
            $hashed_count++;
            echo "<p>✓ <strong>{$email}</strong> (Phone: {$phone}) - Already hashed, skipping.</p>";
        } else {
            $plain_text_count++;
            // Hash the plain text password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update in database
            $update_stmt = $pdo->prepare("UPDATE Staff SET password = ? WHERE phone = ?");
            if ($update_stmt->execute([$hashed_password, $phone])) {
                $updated_count++;
                echo "<p>✓ <strong>{$email}</strong> (Phone: {$phone}) - Password hashed and updated successfully.</p>";
            } else {
                $errors[] = "Failed to update {$email}";
                echo "<p>✗ <strong>{$email}</strong> (Phone: {$phone}) - Failed to update.</p>";
            }
        }
    }
    
    echo "<hr>";
    echo "<h3>Summary:</h3>";
    echo "<ul>";
    echo "<li>Total staff members: <strong>{$total}</strong></li>";
    echo "<li>Already hashed: <strong>{$hashed_count}</strong></li>";
    echo "<li>Plain text found: <strong>{$plain_text_count}</strong></li>";
    echo "<li>Successfully updated: <strong>{$updated_count}</strong></li>";
    if (count($errors) > 0) {
        echo "<li>Errors: <strong>" . count($errors) . "</strong></li>";
    }
    echo "</ul>";
    
    if ($updated_count > 0) {
        echo "<p style='color: green;'><strong>✓ Password upgrade completed successfully!</strong></p>";
        echo "<p><strong>IMPORTANT:</strong> Delete this file (hash_passwords.php) for security after running.</p>";
    } else {
        echo "<p>No passwords needed updating. All passwords are already hashed.</p>";
    }
    
} catch(PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

