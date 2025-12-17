<?php
require_once '../config.php';

// Use staff session name (should match config.php)
if (session_status() === PHP_SESSION_NONE) {
    if (session_name() !== 'staff_session') {
        session_name('staff_session');
    }
    session_start();
}

// Destroy all session data
$_SESSION = array();

// Delete the session cookie
$sessionName = session_name();
if (isset($_COOKIE[$sessionName])) {
    setcookie($sessionName, '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Handle direct GET request (from profile.html or similar)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: ../user/index.php');
    exit;
}

// Return success response for AJAX requests
jsonResponse(['success' => true, 'message' => 'Logged out successfully', 'redirect' => '../user/index.php']);
?>

