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
 * Always fetches fresh staff_image from database to ensure profile photos are up-to-date
 * 
 * @return array|null - Admin data or null if not logged in
 */
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    // Always fetch fresh staff_image from database to ensure it's up-to-date
    // This ensures profile photo changes are reflected immediately
    try {
        require_once __DIR__ . '/../../config/db_connect.php';
        $conn = getDBConnection();
        
        $email = $_SESSION['admin']['email'];
        $stmt = $conn->prepare("SELECT staff_image, first_name, last_name, role FROM Staff WHERE staff_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $fresh_data = $result->fetch_assoc();
            // Update session with fresh data (especially staff_image)
            $_SESSION['admin']['staff_image'] = $fresh_data['staff_image'] ?? null;
            $_SESSION['admin']['first_name'] = $fresh_data['first_name'] ?? $_SESSION['admin']['first_name'] ?? '';
            $_SESSION['admin']['last_name'] = $fresh_data['last_name'] ?? $_SESSION['admin']['last_name'] ?? '';
            $_SESSION['admin']['role'] = $fresh_data['role'] ?? $_SESSION['admin']['role'] ?? 'admin';
            $_SESSION['admin']['name'] = trim(($_SESSION['admin']['first_name'] ?? '') . ' ' . ($_SESSION['admin']['last_name'] ?? ''));
        }
        
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // If database fetch fails, just use session data (fallback)
        error_log("Error refreshing admin data: " . $e->getMessage());
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

/**
 * Get the base path of the application (handles subdirectory installations)
 * 
 * @return string - Base path like '/Lumiere_beauty_salon-main' or '' if at root
 */
function getBasePath() {
    static $basePath = null;
    
    if ($basePath === null) {
        // Get the script directory relative to document root
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        // Remove trailing slashes and normalize
        $scriptDir = rtrim($scriptDir, '/');
        
        // If we're in a subdirectory (not at root), extract it
        // For admin pages: /Lumiere_beauty_salon-main/admin/... -> /Lumiere_beauty_salon-main
        // For API: /Lumiere_beauty_salon-main/api/... -> /Lumiere_beauty_salon-main
        if (strpos($scriptDir, '/admin') !== false) {
            $basePath = substr($scriptDir, 0, strpos($scriptDir, '/admin'));
        } elseif (strpos($scriptDir, '/api') !== false) {
            $basePath = substr($scriptDir, 0, strpos($scriptDir, '/api'));
        } elseif (strpos($scriptDir, '/staff') !== false) {
            $basePath = substr($scriptDir, 0, strpos($scriptDir, '/staff'));
        } else {
            // Try to detect from common patterns
            $parts = explode('/', trim($scriptDir, '/'));
            if (count($parts) > 0 && !empty($parts[0])) {
                $basePath = '/' . $parts[0];
            } else {
                $basePath = '';
            }
        }
    }
    
    return $basePath;
}

/**
 * Resolve staff image path to a web-accessible URL
 * Handles various path formats stored in database
 * 
 * @param string|null $imagePath - Path from database (can be null, empty, or various formats)
 * @param string $basePath - Base path for relative URLs (default: '..')
 * @return string|null - Resolved web-accessible path or null if no image
 */
function resolveStaffImagePath($imagePath, $basePath = '..') {
    if (empty($imagePath)) {
        return null;
    }
    
    // Remove any leading/trailing whitespace
    $imagePath = trim($imagePath);
    
    // If it's already a full URL, return as-is
    if (preg_match('/^https?:\/\//', $imagePath)) {
        return $imagePath;
    }
    
    // Get the application base path (handles subdirectory installations)
    $appBasePath = getBasePath();
    
    // Extract filename from any path format
    $filename = basename($imagePath);
    
    // Handle absolute paths from site root (new format: /images/staff/filename.jpg)
    if (strpos($imagePath, '/images/staff/') === 0) {
        // Prepend application base path if in subdirectory
        return $appBasePath . '/images/staff/' . $filename;
    }
    
    // Handle old format: /images/filename.jpg (without /staff/)
    if (strpos($imagePath, '/images/') === 0 && strpos($imagePath, '/images/staff/') === false) {
        return $appBasePath . '/images/staff/' . $filename;
    }
    
    // Handle staff upload format: staff/uploads/staff/filename.jpg
    if (strpos($imagePath, 'staff/uploads/staff/') === 0) {
        return $appBasePath . '/images/staff/' . $filename;
    }
    
    // Handle just filename (legacy): filename.jpg or 42 or 70.png
    if (strpos($imagePath, '/') === false && strpos($imagePath, '\\') === false) {
        return $appBasePath . '/images/staff/' . $imagePath;
    }
    
    // For any other relative path, extract filename and use absolute path
    return $appBasePath . '/images/staff/' . $filename;
}

// Make admin data available globally for pages that include this file
if (isAdminLoggedIn()) {
    $admin = getCurrentAdmin();
}