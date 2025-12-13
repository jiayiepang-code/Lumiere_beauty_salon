<?php
/**
 * Toggle Staff Status API
 */

session_start();
header('Content-Type: application/json');

require_once '../../../admin/includes/auth_check.php';
require_once '../../../config/config.php';

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
$staff_email = $data['staff_email'] ?? null;

if (!$staff_email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Staff email is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Toggle the status
    $stmt = $conn->prepare("UPDATE Staff SET status = IF(status = 'active', 'inactive', 'active') WHERE staff_email = ?");
    $stmt->bind_param("s", $staff_email);
    
    if ($stmt->execute()) {
        // Get the new status
        $stmt = $conn->prepare("SELECT status FROM Staff WHERE staff_email = ?");
        $stmt->bind_param("s", $staff_email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'message' => 'Staff status updated to ' . ucfirst($row['status']),
            'status' => $row['status']
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