<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_phone'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $phone = $_SESSION['customer_phone'];
    $firstName = $input['firstName'] ?? '';
    $lastName = $input['lastName'] ?? '';
    $email = $input['email'] ?? '';
    
    // Validate inputs
    if (empty($firstName) || empty($lastName)) {
        echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
        exit();
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }
    
    // Update customer profile
    $query = "UPDATE customer SET first_name = ?, last_name = ?, customer_email = ? WHERE phone = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$firstName, $lastName, $email, $phone]);
    
    if ($result) {
        // Update session
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $_SESSION['email'] = $email;
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

