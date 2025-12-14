<?php
header('Content-Type: application/json');

// Include required files - auth_check.php will handle session start with proper configuration
require_once '../../../config/db_connect.php';
require_once '../../../admin/includes/auth_check.php';
require_once '../../../admin/includes/error_handler.php';

// Check authentication
if (!isAdminAuthenticated()) {
    ErrorHandler::handleAuthError();
}

// Check session timeout
if (!checkSessionTimeout()) {
    ErrorHandler::sendError(ErrorHandler::SESSION_EXPIRED, 'Session has expired', null, 401);
}

// Handle GET request only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ErrorHandler::sendError(ErrorHandler::METHOD_NOT_ALLOWED, 'Only GET requests are allowed', null, 405);
}

try {
    // Get database connection
    $conn = getDBConnection();
    
    // Get filter parameters
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $active_only = isset($_GET['active_only']) ? filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN) : false;
    
    // Build query with filters
    $sql = "SELECT service_id, service_category, sub_category, service_name, 
                   current_duration_minutes, current_price, description, 
                   service_image, default_cleanup_minutes, is_active, created_at
            FROM Service
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Add category filter
    if ($category !== null && $category !== '') {
        $sql .= " AND service_category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    // Add search filter (searches in service_name and description)
    if ($search !== null && $search !== '') {
        $sql .= " AND (service_name LIKE ? OR description LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }
    
    // Add active filter
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    
    // Order by category and name
    $sql .= " ORDER BY service_category, service_name";
    
    // Prepare and execute query
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $services = [];
    while ($row = $result->fetch_assoc()) {
        // Convert is_active to boolean
        $row['is_active'] = (bool)$row['is_active'];
        // Convert numeric values
        $row['current_duration_minutes'] = (int)$row['current_duration_minutes'];
        $row['current_price'] = (float)$row['current_price'];
        $row['default_cleanup_minutes'] = (int)$row['default_cleanup_minutes'];
        
        $services[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'services' => $services,
        'count' => count($services)
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    ErrorHandler::handleDatabaseError($e, 'fetching services');
}
