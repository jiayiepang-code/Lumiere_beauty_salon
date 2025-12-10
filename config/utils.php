<?php
/**
 * Utility Functions for Admin Authentication
 */

/**
 * Sanitize phone number to database format
 * Converts various inputs to +60XXXXXXXXXX format
 */
function sanitizePhone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Handle different input formats:
    // 123456789 -> 60123456789
    // 0123456789 -> 60123456789
    // 60123456789 -> 60123456789
    
    if (substr($phone, 0, 1) === '0') {
        // Remove leading 0 and add 60
        $phone = '60' . substr($phone, 1);
    } else if (substr($phone, 0, 2) !== '60') {
        // Add 60 prefix if not present
        $phone = '60' . $phone;
    }
    
    // Return in format: +60XXXXXXXXXX (no spaces)
    return '+' . $phone;
}

/**
 * Validate Malaysian phone number
 */
function isValidMalaysianPhone($phone) {
    // Remove all non-numeric characters
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    
    // Must be 11-12 digits (60 + 9-10 digit number)
    if (strlen($cleaned) < 11 || strlen($cleaned) > 12) {
        return false;
    }
    
    // Must start with 60
    return substr($cleaned, 0, 2) === '60';
}

/**
 * Check if account is locked due to failed attempts
 */
function isAccountLocked($phone) {
    $conn = getDBConnection();
    $lockout_time = 900; // 15 minutes
    $max_attempts = 5;
    
    // Check if Login_Attempts table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'Login_Attempts'");
    if ($tableCheck->num_rows === 0) {
        $conn->close();
        return false;
    }
    
    $cutoff = date('Y-m-d H:i:s', time() - $lockout_time);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as attempt_count FROM Login_Attempts WHERE phone = ? AND attempt_time > ?");
    $stmt->bind_param("ss", $phone, $cutoff);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $row['attempt_count'] >= $max_attempts;
}

/**
 * Log authentication attempt
 */
function logAuthAttempt($phone, $success) {
    $conn = getDBConnection();
    
    // Check if Login_Attempts table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'Login_Attempts'");
    if ($tableCheck->num_rows === 0) {
        $conn->close();
        return;
    }
    
    if (!$success) {
        // Log failed attempt
        $stmt = $conn->prepare("INSERT INTO Login_Attempts (phone, attempt_time) VALUES (?, NOW())");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->close();
    } else {
        // Clear attempts on success
        $stmt = $conn->prepare("DELETE FROM Login_Attempts WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
}
?>
