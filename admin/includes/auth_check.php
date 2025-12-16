<?php
/**
 * Admin Authentication Check
 * File: admin/includes/auth_check.php
 * 
 * This file is included at the top of all admin pages to verify login status
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Load security utilities
    require_once __DIR__ . '/security_utils.php';
    
    // Configure secure session
    configureSecureSession();
    
    // Use admin-specific session name for parallel logins
session_name('admin_session');
session_start();
    
    // Set secure headers
    setSecureHeaders();
}

/**
 * Check if admin is authenticated
 * 
 * @return bool - True if authenticated
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin']) 
           && is_array($_SESSION['admin'])
           && isset($_SESSION['admin']['email']);
}

/**
 * Require admin authentication
 * Redirects to login page if not authenticated
 */
function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        // Store the attempted URL for redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page (use login.php for backend auth)
        header('Location: /Lumiere-beauty-salon/admin/login.php');
        exit;
    }
    
    // Check session timeout (optional: 30 minutes)
    if (isset($_SESSION['last_activity'])) {
        $timeout = 30 * 60; // 30 minutes in seconds
        if (time() - $_SESSION['last_activity'] > $timeout) {
            // Session expired
            session_destroy();
            header('Location: /Lumiere-beauty-salon/admin/login.php?timeout=1');
            exit;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Get current admin data
 * 
 * @return array|null - Admin data or null if not logged in
 */
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    return $_SESSION['admin'];
}

/**
 * Legacy function for compatibility
 */
function getAdminData() {
    return getCurrentAdmin();
}

/**
 * Check if admin is authenticated (alias)
 */
function isAdminAuthenticated() {
    return isAdminLoggedIn();
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    if (!isAdminLoggedIn()) {
        return false;
    }
    
    if (isset($_SESSION['last_activity'])) {
        $timeout = 30 * 60; // 30 minutes
        if (time() - $_SESSION['last_activity'] > $timeout) {
            return false;
        }
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Get CSRF token
 */
function getCSRFToken() {
    if (!isAdminLoggedIn()) {
        return '';
    }
    return $_SESSION['admin']['csrf_token'] ?? '';
}

// Make admin data available globally for pages that include this file
if (isAdminLoggedIn()) {
    $admin = getCurrentAdmin();
}