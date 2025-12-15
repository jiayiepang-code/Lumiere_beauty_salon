<?php
/**
 * Database Connection File
 * Centralized database connection for the Lumière Beauty Salon project
 * 
 * Use this file for all database connections in the project
 */

require_once __DIR__ . '/config.php';

// Ensure server-side datetime uses Malaysia timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

/**
 * Get database connection
 * 
 * @return mysqli Database connection object
 * @throws Exception If connection fails
 */
function getDBConnection() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        // Log error for debugging
        error_log("Database connection failed: " . $conn->connect_error);
        
        // Return JSON error for API requests
        if (headers_sent() === false && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'api') !== false) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'DB_CONNECTION_FAILED',
                    'message' => 'Database connection failed'
                ]
            ]);
            exit;
        }
        
        // For regular pages, show error
        die("Database connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4 for proper character handling
    $conn->set_charset("utf8mb4");
    
    return $conn;
}
?>


