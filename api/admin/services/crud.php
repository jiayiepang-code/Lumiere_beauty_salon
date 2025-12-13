<?php
/**
 * Service CRUD API
 * Handles Create, Update, Delete operations for services
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

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDBConnection();

try {
    switch ($method) {
        case 'POST':
            // Create new service
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $conn->prepare("INSERT INTO Service (service_category, sub_category, service_name, duration_minutes, price, description, service_image, default_cleanup_minutes, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $is_active = isset($data['is_active']) ? 1 : 0;
            $cleanup = $data['default_cleanup_minutes'] ?? 10;
            
            $stmt->bind_param("sssidssii",
                $data['service_category'],
                $data['sub_category'],
                $data['service_name'],
                $data['duration_minutes'],
                $data['price'],
                $data['description'],
                $data['service_image'],
                $cleanup,
                $is_active
            );
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Service created successfully!',
                    'service_id' => $conn->insert_id
                ]);
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();
            break;
            
        case 'PUT':
            // Update existing service
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['service_id'])) {
                throw new Exception('Service ID is required');
            }
            
            $is_active = isset($data['is_active']) ? 1 : 0;
            $cleanup = $data['default_cleanup_minutes'] ?? 10;
            
            $stmt = $conn->prepare("UPDATE Service SET service_category = ?, sub_category = ?, service_name = ?, duration_minutes = ?, price = ?, description = ?, service_image = ?, default_cleanup_minutes = ?, is_active = ? WHERE service_id = ?");
            
            $stmt->bind_param("sssidsssii",
                $data['service_category'],
                $data['sub_category'],
                $data['service_name'],
                $data['duration_minutes'],
                $data['price'],
                $data['description'],
                $data['service_image'],
                $cleanup,
                $is_active,
                $data['service_id']
            );
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Service updated successfully!'
                ]);
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();
            break;
            
        case 'DELETE':
            // Delete service
            $service_id = $_GET['id'] ?? null;
            
            if (!$service_id) {
                throw new Exception('Service ID is required');
            }
            
            // Check if service has future bookings
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Booking_Service bs 
                                   JOIN Booking b ON bs.booking_id = b.booking_id 
                                   WHERE bs.service_id = ? AND b.booking_date >= CURDATE() AND b.status = 'confirmed'");
            $stmt->bind_param("i", $service_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This service has ' . $row['count'] . ' upcoming booking(s). Please deactivate the service instead of deleting it.',
                    'has_bookings' => true
                ]);
                $stmt->close();
                break;
            }
            $stmt->close();
            
            // Delete the service
            $stmt = $conn->prepare("DELETE FROM Service WHERE service_id = ?");
            $stmt->bind_param("i", $service_id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Service deleted successfully!'
                ]);
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
