<?php
/**
 * CSRF Token Validation for Admin API Endpoints
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
    ini_set('session.use_strict_mode', 1);
    session_start();
}

/**
 * Validate CSRF token from request
 * Checks both header and POST data
 */
function validateCSRFToken() {
    // Get token from header or POST data
    $token = null;
    
    // Check X-CSRF-Token header
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    // Check POST data
    elseif (isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }
    // Check JSON input
    else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['csrf_token'])) {
            $token = $input['csrf_token'];
        }
    }
    
    // Validate token
    if (!$token || !isset($_SESSION['admin']['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['admin']['csrf_token'], $token);
}

/**
 * Require valid CSRF token
 * Returns error response if invalid
 */
function requireCSRFToken() {
    if (!validateCSRFToken()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'CSRF_VALIDATION_FAILED',
                'message' => 'Invalid or missing CSRF token'
            ]
        ]);
        exit;
    }
}

/**
 * Require admin authentication for API
 */
function requireAdminAuthAPI() {
    if (!isset($_SESSION['admin']) || !isset($_SESSION['admin']['email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'AUTH_REQUIRED',
                'message' => 'Authentication required'
            ]
        ]);
        exit;
    }
    
    // Check session timeout (30 minutes)
    $session_timeout = 1800;
    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];
        
        if ($elapsed_time > $session_timeout) {
            session_unset();
            session_destroy();
            
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'SESSION_EXPIRED',
                    'message' => 'Session has expired. Please login again.'
                ]
            ]);
            exit;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}
