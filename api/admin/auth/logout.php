<?php
// Start session
session_start();

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'AUTH_REQUIRED',
            'message' => 'No active session found'
        ]
    ]);
    exit;
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Return success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully',
    'redirect' => '../../admin/login.html'
]);
