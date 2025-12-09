<?php
/**
 * Utility Functions for Lumière Beauty Salon
 * File: config/utils.php
 * 
 * This file contains helper functions used across the application
 */

/**
 * Sanitize phone number to consistent format
 * Converts any phone input to: +60123456789 (no spaces)
 * 
 * @param string $phone - Input phone number in any format
 * @return string - Sanitized phone number
 * 
 * Examples:
 * sanitizePhone("12 345 6789") → "+60123456789"
 * sanitizePhone("012-345-6789") → "+60123456789"
 * sanitizePhone("+60 12 3456 6789") → "+60123456789"
 * sanitizePhone("0123456789") → "+60123456789"
 */
function sanitizePhone($phone) {
    // Remove all non-digit characters except +
    $clean = preg_replace('/[^\d+]/', '', $phone);
    
    // If it starts with 0, remove it (Malaysian format: 012xxx → 12xxx)
    if (substr($clean, 0, 1) === '0') {
        $clean = substr($clean, 1);
    }
    
    // If doesn't start with +60, add it
    if (substr($clean, 0, 3) !== '+60') {
        $clean = '+60' . $clean;
    }
    
    return $clean;
}

/**
 * Format phone number for display
 * Converts: +60123456789 → +60 12 3456 789 (visual only)
 * 
 * @param string $phone - Sanitized phone number
 * @return string - Formatted for display
 */
function formatPhoneDisplay($phone) {
    // Remove +60 prefix
    $number = str_replace('+60', '', $phone);
    
    // Format: XX XXXX XXXX or XX XXXX XXX
    if (strlen($number) === 10) {
        return '+60 ' . substr($number, 0, 2) . ' ' . substr($number, 2, 4) . ' ' . substr($number, 6);
    } elseif (strlen($number) === 9) {
        return '+60 ' . substr($number, 0, 2) . ' ' . substr($number, 2, 4) . ' ' . substr($number, 6);
    }
    
    // Fallback: return as-is with +60
    return '+60 ' . $number;
}

/**
 * Validate Malaysian phone number
 * 
 * @param string $phone - Phone number to validate
 * @return bool - True if valid Malaysian mobile number
 */
function isValidMalaysianPhone($phone) {
    $clean = sanitizePhone($phone);
    
    // Malaysian mobile: +60 + (10-11 digits starting with 1)
    // Pattern: +60 1X XXXX XXXX (10 digits after country code)
    $pattern = '/^\+601[0-9]{8,9}$/';
    
    return preg_match($pattern, $clean) === 1;
}

/**
 * Secure password hashing
 * 
 * @param string $password - Plain text password
 * @return string - Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password against hash
 * 
 * @param string $password - Plain text password
 * @param string $hash - Stored password hash
 * @return bool - True if password matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate CSRF token for form security
 * 
 * @return string - CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * 
 * @param string $token - Token to validate
 * @return bool - True if valid
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 * 
 * @param string $data - Input data
 * @return string - Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Log authentication attempts (for security tracking)
 * 
 * @param string $email - Email/phone attempted
 * @param bool $success - Whether login succeeded
 * @param string $ip - IP address
 */
function logAuthAttempt($email, $success, $ip = null) {
    if ($ip === null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    $conn = getDBConnection();
    
    // Check if Login_Attempts table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'Login_Attempts'");
    if ($tableCheck->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO Login_Attempts (phone, attempt_time, ip_address, success) VALUES (?, NOW(), ?, ?)");
        $success_int = $success ? 1 : 0;
        $stmt->bind_param("ssi", $email, $ip, $success_int);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
}

/**
 * Check if account is locked due to too many failed attempts
 * 
 * @param string $phone - Phone number to check
 * @return bool - True if account is locked
 */
function isAccountLocked($phone) {
    $conn = getDBConnection();
    
    // Check if Login_Attempts table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'Login_Attempts'");
    if ($tableCheck->num_rows === 0) {
        return false; // Table doesn't exist, no lockout
    }
    
    // Check for 5 failed attempts in last 15 minutes
    $stmt = $conn->prepare("
        SELECT COUNT(*) as attempts 
        FROM Login_Attempts 
        WHERE phone = ? 
        AND success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $row['attempts'] >= 5;
}