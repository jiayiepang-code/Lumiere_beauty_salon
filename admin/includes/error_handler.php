<?php
/**
 * Centralized Error Handler for Admin Module
 * Provides consistent error response format and logging
 */

class ErrorHandler {
    
    private static $log_file = '../../../logs/admin_errors.log';
    
    /**
     * Error codes
     */
    const AUTH_REQUIRED = 'AUTH_REQUIRED';
    const AUTH_FAILED = 'AUTH_FAILED';
    const SESSION_EXPIRED = 'SESSION_EXPIRED';
    const PERMISSION_DENIED = 'PERMISSION_DENIED';
    const VALIDATION_ERROR = 'VALIDATION_ERROR';
    const NOT_FOUND = 'NOT_FOUND';
    const DUPLICATE_ENTRY = 'DUPLICATE_ENTRY';
    const DATABASE_ERROR = 'DATABASE_ERROR';
    const FILE_UPLOAD_ERROR = 'FILE_UPLOAD_ERROR';
    const RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    const INVALID_JSON = 'INVALID_JSON';
    const INVALID_CSRF_TOKEN = 'INVALID_CSRF_TOKEN';
    const METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    const ACCOUNT_INACTIVE = 'ACCOUNT_INACTIVE';
    
    /**
     * Send error response with consistent format
     * 
     * @param string $code Error code
     * @param string $message Error message
     * @param array $details Optional detailed error information
     * @param int $http_code HTTP status code
     */
    public static function sendError($code, $message, $details = null, $http_code = 400) {
        // Clean any output buffer to prevent PHP warnings/errors from corrupting JSON
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        http_response_code($http_code);
        
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
        
        if ($details !== null) {
            $response['error']['details'] = $details;
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        exit;
    }
    
    /**
     * Log error to file
     * 
     * @param Exception $e Exception object
     * @param array $context Additional context information
     */
    public static function logError($e, $context = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $_SESSION['admin']['email'] ?? 'anonymous',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'context' => $context
        ];
        
        // Ensure logs directory exists
        $log_dir = dirname(self::$log_file);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        // Write to log file
        error_log(json_encode($log_entry) . PHP_EOL, 3, self::$log_file);
    }
    
    /**
     * Handle database errors
     * 
     * @param Exception $e Exception object
     * @param string $operation Operation being performed
     */
    public static function handleDatabaseError($e, $operation = 'database operation') {
        self::logError($e, ['operation' => $operation]);
        
        self::sendError(
            self::DATABASE_ERROR,
            'An error occurred while performing ' . $operation,
            null,
            500
        );
    }
    
    /**
     * Handle validation errors
     * 
     * @param array $errors Array of validation errors
     */
    public static function handleValidationError($errors) {
        self::sendError(
            self::VALIDATION_ERROR,
            'Invalid input data',
            $errors,
            400
        );
    }
    
    /**
     * Handle authentication errors
     * 
     * @param string $message Error message
     */
    public static function handleAuthError($message = 'Authentication required') {
        self::sendError(
            self::AUTH_REQUIRED,
            $message,
            null,
            401
        );
    }
    
    /**
     * Handle not found errors
     * 
     * @param string $resource Resource type
     */
    public static function handleNotFound($resource = 'Resource') {
        self::sendError(
            self::NOT_FOUND,
            $resource . ' not found',
            null,
            404
        );
    }
    
    /**
     * Handle duplicate entry errors
     * 
     * @param string $field Field name
     * @param string $message Custom message
     */
    public static function handleDuplicateEntry($field, $message = null) {
        $default_message = 'A record with this ' . $field . ' already exists';
        
        self::sendError(
            self::DUPLICATE_ENTRY,
            $message ?? $default_message,
            [$field => ($message ?? $default_message)],
            409
        );
    }
    
    /**
     * Handle file upload errors
     * 
     * @param string $message Error message
     */
    public static function handleFileUploadError($message) {
        self::sendError(
            self::FILE_UPLOAD_ERROR,
            $message,
            null,
            400
        );
    }
    
    /**
     * Set custom log file path
     * 
     * @param string $path Log file path
     */
    public static function setLogFile($path) {
        self::$log_file = $path;
    }
}
