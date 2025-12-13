<?php
require_once '../config.php';

checkAuth();

$staff_email = $_SESSION['staff_id']; // staff_id is actually staff_email from login

// Debug: Log session info (remove in production)
error_log("API Request - Session staff_id: " . (isset($_SESSION['staff_id']) ? $_SESSION['staff_id'] : 'NOT SET'));
error_log("API Request - Session staff_name: " . (isset($_SESSION['staff_name']) ? $_SESSION['staff_name'] : 'NOT SET'));
error_log("API Request - Using staff_email: " . $staff_email);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get staff information
    try {
        // Verify staff_email is set
        if (empty($staff_email)) {
            jsonResponse(['error' => 'Session expired. Please login again.'], 401);
        }
        
        $stmt = $pdo->prepare("SELECT staff_email, first_name, last_name, phone, staff_image, role, is_active 
                              FROM Staff WHERE staff_email = ?");
        $stmt->execute([$staff_email]);
        $staff = $stmt->fetch();
        
        if ($staff) {
            // Debug: Log what was found
            error_log("API Response - Found staff: " . $staff['first_name'] . " " . $staff['last_name'] . " (" . $staff['staff_email'] . ")");
            jsonResponse(['success' => true, 'staff' => $staff]);
        } else {
            error_log("API Response - Staff not found for email: " . $staff_email);
            jsonResponse(['error' => 'Staff not found'], 404);
        }
        
    } catch(PDOException $e) {
        error_log("API Error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch staff information'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update staff information
    $data = json_decode(file_get_contents('php://input'), true);
    
    $first_name = isset($data['first_name']) ? sanitizeInput($data['first_name']) : '';
    $last_name = isset($data['last_name']) ? sanitizeInput($data['last_name']) : '';
    $phone = isset($data['phone']) ? sanitizeInput($data['phone']) : '';
    
    if (empty($first_name) || empty($last_name) || empty($phone)) {
        jsonResponse(['error' => 'Missing required fields'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE Staff SET first_name = ?, last_name = ?, phone = ? 
                              WHERE staff_email = ?");
        $stmt->execute([$first_name, $last_name, $phone, $staff_email]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            jsonResponse(['success' => true, 'message' => 'No changes were made']);
        }
        
    } catch(PDOException $e) {
        jsonResponse(['error' => 'Failed to update profile'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'upload_image') {
    // Upload profile image
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'No image file uploaded'], 400);
    }
    
    $file = $_FILES['image'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        jsonResponse(['error' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'], 400);
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(['error' => 'File size too large. Maximum size is 5MB.'], 400);
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/staff/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'staff_' . time() . '_' . uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Save path to database (relative path from root: staff/uploads/staff/filename.jpg)
        $db_path = 'staff/uploads/staff/' . $filename;
        
        try {
            $stmt = $pdo->prepare("UPDATE Staff SET staff_image = ? WHERE staff_email = ?");
            $stmt->execute([$db_path, $staff_email]);
            
            jsonResponse(['success' => true, 'message' => 'Image uploaded successfully', 'image_path' => $db_path]);
        } catch(PDOException $e) {
            // Delete uploaded file if database update fails
            unlink($filepath);
            jsonResponse(['error' => 'Failed to save image to database'], 500);
        }
    } else {
        jsonResponse(['error' => 'Failed to upload image'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'change_password') {
    // Change password
    $data = json_decode(file_get_contents('php://input'), true);
    
    $current_password = isset($data['current_password']) ? $data['current_password'] : '';
    $new_password = isset($data['new_password']) ? $data['new_password'] : '';
    
    if (empty($current_password) || empty($new_password)) {
        jsonResponse(['error' => 'Missing required fields'], 400);
    }
    
    // Validate password strength
    if (strlen($new_password) < 8) {
        jsonResponse(['error' => 'Password must be at least 8 characters long'], 400);
    }
    
    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM Staff WHERE staff_email = ?");
        $stmt->execute([$staff_email]);
        $staff = $stmt->fetch();
        
        if (!$staff) {
            jsonResponse(['error' => 'Staff not found'], 404);
        }
        
        $db_password = $staff['password'];
        $password_valid = false;
        
        // Check if password is hashed
        $is_hashed = (substr($db_password, 0, 4) === '$2y$' || 
                     substr($db_password, 0, 4) === '$2a$' || 
                     substr($db_password, 0, 4) === '$2b$' ||
                     substr($db_password, 0, 1) === '$');
        
        if ($is_hashed) {
            $password_valid = password_verify($current_password, $db_password);
        } else {
            $password_valid = ($db_password === $current_password);
        }
        
        if (!$password_valid) {
            jsonResponse(['error' => 'Current password is incorrect'], 400);
        }
        
        // Update password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE Staff SET password = ? WHERE staff_email = ?");
        $stmt->execute([$new_password_hash, $staff_email]);
        
        jsonResponse(['success' => true, 'message' => 'Password changed successfully']);
        
    } catch(PDOException $e) {
        jsonResponse(['error' => 'Failed to change password'], 500);
    }
}
?>