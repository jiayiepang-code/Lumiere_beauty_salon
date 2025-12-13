<?php
/**
 * Admin Logout
 * File: admin/logout.php
 */

// Start session
session_start();

// Destroy all session data
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: /Lumiere-beauty-salon/admin/login.html');
exit;
?>
