<?php
/**
 * Security Utilities for Admin Module
 * Task 10: Optimize performance and add security measures
 * 
 * Provides security helper functions for XSS prevention, input sanitization,
 * file upload validation, and secure session management
 */

/**
 * Sanitize output to prevent XSS attacks
 * 
 * @param string $data - Data to sanitize
 * @param bool $allow_html - Allow HTML tags (default: false)
 * @return string - Sanitized data
 */
function sanitizeOutput($data, $allow_html = false) {
    if (is_null($data)) {
        return '';
    }
    
    if (is_array($data)) {
        return array_map(function($item) use ($allow_html) {
            return sanitizeOutput($item, $allow_html);
        }, $data);
    }
    
    if ($allow_html) {
        // Allow HTML but sanitize dangerous attributes
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Strip all HTML tags
    return htmlspecialchars(strip_tags($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize input data
 * 
 * @param mixed $data - Input data
 * @return mixed - Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    if (is_string($data)) {
        // Remove null bytes
        $data = str_replace("\0", '', $data);
        // Trim whitespace
        $data = trim($data);
        // Remove HTML tags
        $data = strip_tags($data);
        // Escape special characters
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    return $data;
}

/**
 * Validate and sanitize file upload
 * 
 * @param array $file - $_FILES array element
 * @param array $allowed_types - Allowed MIME types (default: image types)
 * @param int $max_size_mb - Maximum file size in MB (default: 2)
 * @return array - ['valid' => bool, 'error' => string|null, 'sanitized_name' => string|null]
 */
function validateFileUpload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], $max_size_mb = 2) {
    $max_size_bytes = $max_size_mb * 1024 * 1024;
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return [
            'valid' => false,
            'error' => 'File upload failed or no file provided',
            'sanitized_name' => null
        ];
    }
    
    // Check file size
    if ($file['size'] > $max_size_bytes) {
        return [
            'valid' => false,
            'error' => "File size exceeds maximum allowed size of {$max_size_mb}MB",
            'sanitized_name' => null
        ];
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return [
            'valid' => false,
            'error' => 'Invalid file type. Only ' . implode(', ', $allowed_types) . ' are allowed',
            'sanitized_name' => null
        ];
    }
    
    // Sanitize filename
    $original_name = $file['name'];
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $basename = pathinfo($original_name, PATHINFO_FILENAME);
    
    // Remove dangerous characters
    $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
    $sanitized_name = $basename . '.' . strtolower($extension);
    
    // Generate unique filename to prevent overwrites
    $unique_name = uniqid() . '_' . $sanitized_name;
    
    return [
        'valid' => true,
        'error' => null,
        'sanitized_name' => $unique_name,
        'mime_type' => $mime_type,
        'size' => $file['size']
    ];
}

/**
 * Secure file upload handler
 * 
 * @param array $file - $_FILES array element
 * @param string $upload_dir - Upload directory path
 * @param array $allowed_types - Allowed MIME types
 * @param int $max_size_mb - Maximum file size in MB
 * @return array - ['success' => bool, 'file_path' => string|null, 'error' => string|null]
 */
function secureFileUpload($file, $upload_dir, $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], $max_size_mb = 2) {
    // Validate upload
    $validation = validateFileUpload($file, $allowed_types, $max_size_mb);
    
    if (!$validation['valid']) {
        return [
            'success' => false,
            'file_path' => null,
            'error' => $validation['error']
        ];
    }
    
    // Ensure upload directory exists and is writable
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return [
                'success' => false,
                'file_path' => null,
                'error' => 'Upload directory does not exist and could not be created'
            ];
        }
    }
    
    if (!is_writable($upload_dir)) {
        return [
            'success' => false,
            'file_path' => null,
            'error' => 'Upload directory is not writable'
        ];
    }
    
    // Move uploaded file
    $target_path = rtrim($upload_dir, '/') . '/' . $validation['sanitized_name'];
    
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        return [
            'success' => false,
            'file_path' => null,
            'error' => 'Failed to move uploaded file'
        ];
    }
    
    // Set proper permissions
    chmod($target_path, 0644);
    
    return [
        'success' => true,
        'file_path' => $target_path,
        'error' => null
    ];
}

/**
 * Configure secure session settings
 * Should be called before session_start()
 */
function configureSecureSession() {
    // Only configure if session hasn't started yet
    if (session_status() === PHP_SESSION_NONE) {
        // Prevent session fixation
        ini_set('session.use_strict_mode', 1);
        
        // Use cookies only (not URL parameters)
        ini_set('session.use_only_cookies', 1);
        
        // HttpOnly flag prevents JavaScript access
        ini_set('session.cookie_httponly', 1);
        
        // Secure flag (set to 1 in production with HTTPS)
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
        ini_set('session.cookie_secure', $is_https ? 1 : 0);
        
        // Set cookie path to root so it's accessible across the entire site
        ini_set('session.cookie_path', '/');
        
        // SameSite attribute (PHP 7.3+)
        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            ini_set('session.cookie_samesite', 'Lax'); // Changed from Strict to Lax for better compatibility
        }
        
        // Session name
        session_name('LUMIERE_ADMIN_SESSION');
    }
    
    // Regenerate session ID periodically (only if session is active)
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Regenerate every 5 minutes
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * Generate secure random token
 * 
 * @param int $length - Token length in bytes (default: 32)
 * @return string - Hexadecimal token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Verify password strength
 * 
 * @param string $password - Password to verify
 * @return array - ['valid' => bool, 'errors' => array]
 */
function verifyPasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Escape SQL string (use prepared statements instead, but this is a fallback)
 * 
 * @param mysqli $conn - Database connection
 * @param string $string - String to escape
 * @return string - Escaped string
 */
function escapeSQL($conn, $string) {
    return $conn->real_escape_string($string);
}

/**
 * Set secure HTTP headers
 */
function setSecureHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Content Security Policy (adjust as needed)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;");
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Log security event
 * 
 * @param string $event - Event description
 * @param array $context - Additional context
 */
function logSecurityEvent($event, $context = []) {
    $log_file = __DIR__ . '/../../logs/admin_security.log';
    $log_dir = dirname($log_file);
    
    // Create log directory if it doesn't exist
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'context' => $context
    ];
    
    error_log(json_encode($log_entry) . "\n", 3, $log_file);
}

