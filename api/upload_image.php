<?php
/**
 * Image Upload Handler
 * Handles image uploads for services and staff
 * Task 10: Enhanced with secure file upload utilities
 */

session_start();
header('Content-Type: application/json');

require_once '../../admin/includes/auth_check.php';
require_once '../../admin/includes/security_utils.php';
require_once '../../admin/includes/error_handler.php';

// Check authentication
if (!isAdminLoggedIn()) {
    ErrorHandler::handleAuthError();
}

// Check session timeout
if (!checkSessionTimeout()) {
    ErrorHandler::sendError(ErrorHandler::SESSION_EXPIRED, 'Session has expired', null, 401);
}

try {
    if (!isset($_FILES['image'])) {
        ErrorHandler::sendError(ErrorHandler::VALIDATION_ERROR, 'No image file uploaded');
    }
    
    $file = $_FILES['image'];
    $type = isset($_POST['type']) ? sanitizeInput($_POST['type']) : 'service'; // 'service' or 'staff'
    
    // Validate type
    if (!in_array($type, ['service', 'staff'])) {
        ErrorHandler::sendError(ErrorHandler::VALIDATION_ERROR, 'Invalid upload type. Must be "service" or "staff"');
    }
    
    // Allowed image types
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size_mb = 200; // Maximum file size: 200MB
    
    // Upload directory
    $upload_dir = __DIR__ . '/../../images/';
    
    // Use secure file upload function
    $result = secureFileUpload($file, $upload_dir, $allowed_types, $max_size_mb);
    
    if (!$result['success']) {
        ErrorHandler::handleFileUploadError($result['error']);
    }
    
    // Log successful upload
    logSecurityEvent('Image uploaded', [
        'type' => $type,
        'filename' => basename($result['file_path']),
        'size' => $file['size']
    ]);
    
    // Return relative path for database storage
    $relative_path = '../images/' . basename($result['file_path']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'image_path' => $relative_path,
        'filename' => basename($result['file_path'])
    ]);
    
} catch (Exception $e) {
    ErrorHandler::handleFileUploadError($e->getMessage());
}
?>
