<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_phone'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['staff_email'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $phone = $_SESSION['customer_phone'];
    $staffEmail = $input['staff_email'];
    
    // Get customer_email from phone
    $query = "SELECT customer_email FROM customer WHERE phone = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$phone]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer || empty($customer['customer_email'])) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit();
    }
    
    $customerEmail = $customer['customer_email'];
    
    // Create favourites table if it doesn't exist (British spelling to match database)
    $createTableQuery = "CREATE TABLE IF NOT EXISTS customer_favourites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_email VARCHAR(100) NOT NULL,
        staff_email VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_favourite (customer_email, staff_email),
        FOREIGN KEY (customer_email) REFERENCES customer(customer_email) ON DELETE CASCADE,
        FOREIGN KEY (staff_email) REFERENCES staff(staff_email) ON DELETE CASCADE
    )";
    $db->exec($createTableQuery);
    
    // Check if favorite already exists
    $checkQuery = "SELECT * FROM customer_favourites WHERE customer_email = ? AND staff_email = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$customerEmail, $staffEmail]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Staff already in favourites']);
        exit();
    }
    
    // Add favorite
    $insertQuery = "INSERT INTO customer_favourites (customer_email, staff_email) VALUES (?, ?)";
    $insertStmt = $db->prepare($insertQuery);
    $result = $insertStmt->execute([$customerEmail, $staffEmail]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Staff added to favourites']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add favourite']);
    }
} catch (Exception $e) {
    error_log('Add favorite error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

