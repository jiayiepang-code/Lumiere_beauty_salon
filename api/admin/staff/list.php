<?php
header('Content-Type: application/json');

// Include required files
require_once '../../../admin/includes/auth_check.php'; // This handles session start
require_once '../../../php/connection.php';

// Check authentication
if (!isAdminAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'AUTH_REQUIRED',
            'message' => 'Authentication required'
        ]
    ]);
    exit;
}

// Check session timeout
if (!checkSessionTimeout()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'SESSION_EXPIRED',
            'message' => 'Session has expired'
        ]
    ]);
    exit;
}

// Handle GET request only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Only GET requests are allowed'
        ]
    ]);
    exit;
}

try {
    // Get filter parameters
    $role = isset($_GET['role']) ? trim($_GET['role']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $active_only = isset($_GET['active_only']) ? filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN) : false;
    
    // Build query with filters
    $sql = "SELECT staff_email, phone, first_name, last_name, bio, role, 
                   staff_image, is_active, created_at
            FROM Staff
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Add role filter
    if ($role !== null && $role !== '') {
        $sql .= " AND role = ?";
        $params[] = $role;
        $types .= "s";
    }
    
    // Add search filter (searches in first_name, last_name, email)
    if ($search !== null && $search !== '') {
        $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR staff_email LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }
    
    // Add active filter
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    
    // Order by name
    $sql .= " ORDER BY first_name, last_name";
    
    // Prepare and execute query
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        // Convert is_active to boolean
        $row['is_active'] = (bool)$row['is_active'];
        // Remove password from response (security)
        unset($row['password']);
        
        $staff[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'staff' => $staff,
        'count' => count($staff)
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log(json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $_SESSION['admin']['email'] ?? 'unknown',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]), 3, '../../../logs/admin_errors.log');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'DATABASE_ERROR',
            'message' => 'An error occurred while fetching staff'
        ]
    ]);
}
