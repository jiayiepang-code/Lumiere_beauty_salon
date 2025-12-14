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
    
    // Remove favorite (British spelling to match database)
    $deleteQuery = "DELETE FROM customer_favourites WHERE customer_email = ? AND staff_email = ?";
    $deleteStmt = $db->prepare($deleteQuery);
    $result = $deleteStmt->execute([$customerEmail, $staffEmail]);
    
    if ($result && $deleteStmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Staff removed from favourites']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Favourite not found or already removed']);
    }
} catch (Exception $e) {
    error_log('Remove favorite error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

