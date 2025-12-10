<?php
/**
 * Admin Authentication Check
 * File: admin/includes/auth_check.php
 * 
 * This file is included at the top of all admin pages to verify login status
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        
        // Redirect to login page
        header('Location: /Lumiere-beauty-salon/admin/login.html');
        exit;
    }
    
    // Check session timeout (optional: 30 minutes)
    if (isset($_SESSION['last_activity'])) {
        $timeout = 30 * 60; // 30 minutes in seconds
        if (time() - $_SESSION['last_activity'] > $timeout) {
            // Session expired
            session_destroy();
            header('Location: /Lumiere-beauty-salon/admin/login.html?timeout=1');
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

// Make admin data available globally for pages that include this file
if (isAdminLoggedIn()) {
    $admin = getCurrentAdmin();
}