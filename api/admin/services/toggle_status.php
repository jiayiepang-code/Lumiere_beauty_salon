<?php
/**
 * Toggle Service Status API
 */

session_start();
header('Content-Type: application/json');

require_once '../../../admin/includes/auth_check.php';
require_once '../../../config/config.php';
require_once '../../../config/db_connect.php';

// Check authentication
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$service_id = $data['service_id'] ?? null;

if (!$service_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Service ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Toggle the status
    $stmt = $conn->prepare("UPDATE Service SET is_active = NOT is_active WHERE service_id = ?");
    $stmt->bind_param("i", $service_id);
    
    if ($stmt->execute()) {
        // Get the new status
        $stmt = $conn->prepare("SELECT is_active FROM Service WHERE service_id = ?");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $new_status = $row['is_active'] ? 'Active' : 'Inactive';
        
        echo json_encode([
            'success' => true,
            'message' => 'Service status updated to ' . $new_status,
            'is_active' => (bool)$row['is_active']
        ]);
    } else {
        throw new Exception($stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error updating status: ' . $e->getMessage()
    ]);
}
?>