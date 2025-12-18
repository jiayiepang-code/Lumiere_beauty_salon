<?php
/**
 * Admin Logout API Endpoint
 * Returns JSON response with redirect URL
 */

header('Content-Type: application/json');

// Start session
session_start();

// #region agent log
$logData = [
    'location' => 'api/admin/auth/logout.php:15',
    'message' => 'Logout API called',
    'data' => [
        'hasSession' => isset($_SESSION['admin']),
        'sessionId' => session_id()
    ],
    'timestamp' => round(microtime(true) * 1000),
    'sessionId' => 'debug-session',
    'runId' => 'run1',
    'hypothesisId' => 'H1,H2'
];
file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode($logData) . "\n", FILE_APPEND);
// #endregion

try {
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }

    // Destroy the session
    session_destroy();

    // Calculate correct redirect path to user/index.php
    // Return just the path segment, JavaScript will construct full path using basePath
    $redirectPath = 'user/index.php';
    
    // #region agent log
    $logData = [
        'location' => 'api/admin/auth/logout.php:35',
        'message' => 'Session destroyed successfully',
        'data' => [
            'redirect' => $redirectPath,
            'calculatedPath' => realpath(__DIR__ . '/../../../user/index.php'),
            'fileExists' => file_exists(__DIR__ . '/../../../user/index.php'),
            'docRoot' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown'
        ],
        'timestamp' => round(microtime(true) * 1000),
        'sessionId' => 'debug-session',
        'runId' => 'run1',
        'hypothesisId' => 'H1'
    ];
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode($logData) . "\n", FILE_APPEND);
    // #endregion

    // Return success response with redirect to user index.php
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully',
        'redirect' => $redirectPath  // Redirect to user homepage
    ]);
    exit;
} catch (Exception $e) {
    // #region agent log
    $logData = [
        'location' => 'api/admin/auth/logout.php:52',
        'message' => 'Logout error occurred',
        'data' => [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ],
        'timestamp' => round(microtime(true) * 1000),
        'sessionId' => 'debug-session',
        'runId' => 'run1',
        'hypothesisId' => 'H1'
    ];
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode($logData) . "\n", FILE_APPEND);
    // #endregion

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during logout',
        'redirect' => 'user/index.php'  // Still redirect even on error
    ]);
    exit;
}