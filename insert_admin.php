<?php
/**
 * Insert Admin Record Script
 * Run this to insert/restore the default admin account
 */

require_once 'config/config.php';

$conn = getDBConnection();

// Admin details
$email = 'admin@lumiere.com';
$phone = '+60 12 3456 789'; // Format: +60 XX XXXX XXX
$password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // Password: Admin@123
$first_name = 'Admin';
$last_name = 'User';
$role = 'admin';
$is_active = 1;

// Insert or update admin record
$sql = "INSERT INTO Staff (staff_email, phone, password, first_name, last_name, role, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            password = VALUES(password),
            is_active = VALUES(is_active),
            role = VALUES(role)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssi", $email, $phone, $password_hash, $first_name, $last_name, $role, $is_active);

if ($stmt->execute()) {
    echo "<h2 style='color:green;'>✓ Admin record inserted/updated successfully!</h2>";
    echo "<p><strong>Admin Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Email:</strong> admin@lumiere.com</li>";
    echo "<li><strong>Phone:</strong> +60 12 345 6789 (or 60123456789)</li>";
    echo "<li><strong>Password:</strong> Admin@123</li>";
    echo "</ul>";
    echo "<p style='color:orange;'>⚠ Please change the default password after first login!</p>";
    echo "<p>You can now access the admin module at: <a href='admin/login.html'>admin/login.html</a></p>";
} else {
    echo "<h2 style='color:red;'>✗ Error inserting admin record</h2>";
    echo "<p>Error: " . $stmt->error . "</p>";
}

$stmt->close();
$conn->close();
?>
