<?php
// Session configuration with security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);
session_start();

// Session timeout (30 minutes of inactivity)
$session_timeout = 1800; // 30 minutes in seconds

/**
 * Check if admin is authenticated
 */
function isAdminAuthenticated() {
    return isset($_SESSION['admin']) && isset($_SESSION['admin']['email']);
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    global $session_timeout;
    
    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];
        
        if ($elapsed_time > $session_timeout) {
            // Session expired
            session_unset();
            session_destroy();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['admin']['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['admin']['csrf_token'], $token);
}

/**
 * Get CSRF token
 */
function getCSRFToken() {
    if (isset($_SESSION['admin']['csrf_token'])) {
        return $_SESSION['admin']['csrf_token'];
    }
    return null;
}

/**
 * Require admin authentication
 * Redirects to login page if not authenticated
 */
function requireAdminAuth() {
    if (!isAdminAuthenticated()) {
        header('Location: login.html');
        exit;
    }
    
    if (!checkSessionTimeout()) {
        header('Location: login.html?timeout=1');
        exit;
    }
}

/**
 * Get current admin data
 */
function getCurrentAdmin() {
    if (isAdminAuthenticated()) {
        return $_SESSION['admin'];
    }
    return null;
}

// Automatically check authentication for admin pages
// This can be included at the top of any admin page
