<?php
/**
 * Staff CRUD API
 * Handles Create, Update, Delete operations for staff
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
            // Create new staff
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if (empty($data['staff_email']) || empty($data['password'])) {
                throw new Exception('Email and password are required');
            }
            
            // Check if email already exists
            $checkStmt = $conn->prepare("SELECT staff_email FROM Staff WHERE staff_email = ?");
            $checkStmt->bind_param("s", $data['staff_email']);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $checkStmt->close();
                throw new Exception('This email is already registered');
            }
            $checkStmt->close();
            
            // Hash password
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $status = isset($data['status']) ? $data['status'] : 'active';
            
            $stmt = $conn->prepare("INSERT INTO Staff (staff_email, phone, password, first_name, last_name, bio, role, staff_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("sssssssss",
                $data['staff_email'],
                $data['phone'],
                $hashed_password,
                $data['first_name'],
                $data['last_name'],
                $data['bio'],
                $data['role'],
                $data['staff_image'],
                $status
            );
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Staff member created successfully!',
                    'staff_email' => $data['staff_email']
                ]);
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();
            break;
            
        case 'PUT':
            // Update existing staff
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['staff_email'])) {
                throw new Exception('Staff email is required');
            }
            
            $status = isset($data['status']) ? $data['status'] : 'active';
            
            // Build update query dynamically (exclude password if not provided)
            if (!empty($data['password'])) {
                $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE Staff SET phone = ?, password = ?, first_name = ?, last_name = ?, bio = ?, role = ?, staff_image = ?, status = ? WHERE staff_email = ?");
                $stmt->bind_param("sssssssss",
                    $data['phone'],
                    $hashed_password,
                    $data['first_name'],
                    $data['last_name'],
                    $data['bio'],
                    $data['role'],
                    $data['staff_image'],
                    $status,
                    $data['staff_email']
                );
            } else {
                $stmt = $conn->prepare("UPDATE Staff SET phone = ?, first_name = ?, last_name = ?, bio = ?, role = ?, staff_image = ?, status = ? WHERE staff_email = ?");
                $stmt->bind_param("ssssssss",
                    $data['phone'],
                    $data['first_name'],
                    $data['last_name'],
                    $data['bio'],
                    $data['role'],
                    $data['staff_image'],
                    $status,
                    $data['staff_email']
                );
            }
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Staff member updated successfully!'
                ]);
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();
            break;
            
        case 'DELETE':
            // Delete staff
            $staff_email = $_GET['email'] ?? null;
            
            if (!$staff_email) {
                throw new Exception('Staff email is required');
            }
            
            // Prevent deleting admin account
            $checkStmt = $conn->prepare("SELECT role FROM Staff WHERE staff_email = ?");
            $checkStmt->bind_param("s", $staff_email);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $staff = $result->fetch_assoc();
            
            if ($staff && $staff['role'] === 'admin') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Cannot delete admin account. Please change the role first.'
                ]);
                $checkStmt->close();
                break;
            }
            $checkStmt->close();
            
            // Check if staff has future bookings
            $checkBookings = $conn->prepare("SELECT COUNT(*) as count FROM Booking WHERE staff_email = ? AND booking_date >= CURDATE() AND status = 'confirmed'");
            $checkBookings->bind_param("s", $staff_email);
            $checkBookings->execute();
            $bookingResult = $checkBookings->get_result();
            $bookingRow = $bookingResult->fetch_assoc();
            
            if ($bookingRow['count'] > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This staff member has ' . $bookingRow['count'] . ' upcoming booking(s). Please deactivate the account instead of deleting it.',
                    'has_bookings' => true
                ]);
                $checkBookings->close();
                break;
            }
            $checkBookings->close();
            
            // Delete the staff
            $stmt = $conn->prepare("DELETE FROM Staff WHERE staff_email = ?");
            $stmt->bind_param("s", $staff_email);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Staff member deleted successfully!'
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
                    $data['staff_email']
                );
            } else {
                $stmt = $conn->prepare("UPDATE Staff SET phone = ?, first_name = ?, last_name = ?, bio = ?, role = ?, staff_image = ?, is_active = ? WHERE staff_email = ?");
                $stmt->bind_param("ssssssss",
                    $data['phone'],
                    $data['first_name'],
                    $data['last_name'],
                    $data['bio'] ?? '',
                    $data['role'] ?? 'staff',
                    $data['staff_image'] ?? null,
                    $data['is_active'] ?? 1,
                    $data['staff_email']
                );
            }
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Staff updated successfully'
                ]);
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();
            break;
            
        case 'DELETE':
            // Delete/deactivate staff
            $staff_email = $_GET['email'] ?? null;
            
            if (!$staff_email) {
                throw new Exception('Staff email is required');
            }
            
            // Check if staff has future bookings
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Booking WHERE staff_email = ? AND booking_date >= CURDATE() AND status = 'confirmed'");
            $stmt->bind_param("s", $staff_email);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Cannot delete staff with future bookings. Please deactivate instead.',
                    'has_bookings' => true
                ]);
                $stmt->close();
                break;
            }
            $stmt->close();
            
            // Delete the staff
            $stmt = $conn->prepare("DELETE FROM Staff WHERE staff_email = ?");
            $stmt->bind_param("s", $staff_email);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Staff deleted successfully'
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
