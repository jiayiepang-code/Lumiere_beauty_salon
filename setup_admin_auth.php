<?php
/**
 * Standalone Admin Authentication Setup Script
 * Place this in your root directory and access via: http://localhost/Lumiere-beauty-salon/setup_admin_auth.php
 */

// Database configuration
$servername = "sql12.freesqldatabase.com";
$username = "sql12810487";
$password = "bMQ7LPiA6X"; // XAMPP default is empty
$dbname = "sql12810487";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("<h2 style='color:red;'>Connection failed: " . $conn->connect_error . "</h2>");
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Admin Authentication Setup</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #A26E60; }
        .success { color: green; padding: 10px; background: #e8f5e9; border-left: 4px solid green; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #ffebee; border-left: 4px solid red; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #e3f2fd; border-left: 4px solid blue; margin: 10px 0; }
        .step { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .credentials { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
<div class='container'>
<h1>🔐 Admin Authentication Setup</h1>";

$errors = 0;
$success = 0;

// Step 1: Create Login_Attempts table
echo "<div class='step'><h3>Step 1: Creating Login_Attempts Table</h3>";
$sql1 = "CREATE TABLE IF NOT EXISTS Login_Attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(18) NOT NULL,
    attempt_time DATETIME NOT NULL,
    INDEX idx_phone_time (phone, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql1)) {
    echo "<div class='success'>✓ Login_Attempts table created successfully</div>";
    $success++;
} else {
    echo "<div class='error'>✗ Error creating Login_Attempts table: " . $conn->error . "</div>";
    $errors++;
}
echo "</div>";

// Step 2: Create Admin_Login_Log table
echo "<div class='step'><h3>Step 2: Creating Admin_Login_Log Table</h3>";
$sql2 = "CREATE TABLE IF NOT EXISTS Admin_Login_Log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_email VARCHAR(100) NOT NULL,
    login_time DATETIME NOT NULL,
    ip_address VARCHAR(45),
    INDEX idx_admin_email (admin_email),
    INDEX idx_login_time (login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql2)) {
    echo "<div class='success'>✓ Admin_Login_Log table created successfully</div>";
    $success++;
} else {
    echo "<div class='error'>✗ Error creating Admin_Login_Log table: " . $conn->error . "</div>";
    $errors++;
}
echo "</div>";

// Step 3: Check if Staff table exists
echo "<div class='step'><h3>Step 3: Checking Staff Table</h3>";
$result = $conn->query("SHOW TABLES LIKE 'Staff'");
if ($result->num_rows > 0) {
    echo "<div class='success'>✓ Staff table exists</div>";
    
    // Check if password column can store hashes
    $result = $conn->query("SHOW COLUMNS FROM Staff LIKE 'password'");
    if ($result->num_rows > 0) {
        $column = $result->fetch_assoc();
        echo "<div class='info'>Current password column type: " . $column['Type'] . "</div>";
        
        // Update password column if needed
        if (strpos($column['Type'], 'varchar') === false || intval(preg_replace('/[^0-9]/', '', $column['Type'])) < 255) {
            $sql3 = "ALTER TABLE Staff MODIFY COLUMN password VARCHAR(255) NOT NULL";
            if ($conn->query($sql3)) {
                echo "<div class='success'>✓ Password column updated to VARCHAR(255)</div>";
                $success++;
            } else {
                echo "<div class='error'>✗ Error updating password column: " . $conn->error . "</div>";
                $errors++;
            }
        } else {
            echo "<div class='success'>✓ Password column is already correct size</div>";
            $success++;
        }
    }
} else {
    echo "<div class='error'>✗ Staff table does not exist. Please create it first.</div>";
    $errors++;
}
echo "</div>";

// Step 4: Create or update default admin account
echo "<div class='step'><h3>Step 4: Creating Default Admin Account</h3>";

// Check if admin exists
$check_admin = $conn->query("SELECT staff_email FROM Staff WHERE phone = '60123456789' OR staff_email = 'admin@lumiere.com'");

$admin_password = 'Admin@123';
$admin_hash = password_hash($admin_password, PASSWORD_BCRYPT);

if ($check_admin && $check_admin->num_rows > 0) {
    // Update existing admin
    $sql4 = "UPDATE Staff SET 
             password = '$admin_hash',
             role = 'admin',
             is_active = TRUE
             WHERE phone = '60123456789' OR staff_email = 'admin@lumiere.com'";
    
    if ($conn->query($sql4)) {
        echo "<div class='success'>✓ Existing admin account updated with new password</div>";
        $success++;
    } else {
        echo "<div class='error'>✗ Error updating admin account: " . $conn->error . "</div>";
        $errors++;
    }
} else {
    // Create new admin
    $sql4 = "INSERT INTO Staff (staff_email, phone, password, first_name, last_name, role, is_active)
             VALUES (
                 'admin@lumiere.com',
                 '60123456789',
                 '$admin_hash',
                 'Admin',
                 'User',
                 'admin',
                 TRUE
             )";
    
    if ($conn->query($sql4)) {
        echo "<div class='success'>✓ New admin account created successfully</div>";
        $success++;
    } else {
        echo "<div class='error'>✗ Error creating admin account: " . $conn->error . "</div>";
        $errors++;
    }
}
echo "</div>";

// Summary
echo "<div class='step' style='background: #f9f9f9;'>";
echo "<h2>Setup Summary</h2>";
echo "<p><strong>Successful operations:</strong> $success</p>";
echo "<p><strong>Errors:</strong> $errors</p>";

if ($errors === 0) {
    echo "<div class='success'><h3>✓ Setup Completed Successfully!</h3></div>";
    echo "<div class='credentials'>";
    echo "<h3>🔑 Default Admin Credentials:</h3>";
    echo "<p><strong>Phone:</strong> <code>12 345 6789</code> (or <code>60123456789</code>)</p>";
    echo "<p><strong>Password:</strong> <code>Admin@123</code></p>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Go to: <a href='admin/login.html' target='_blank'>admin/login.html</a></li>";
    echo "<li>Enter the credentials above</li>";
    echo "<li>Click 'ADMIN LOGIN'</li>";
    echo "<li><strong>Important:</strong> Change the default password after first login!</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h3>⚠️ Security Note:</h3>";
    echo "<p>Delete this setup file (<code>setup_admin_auth.php</code>) after setup is complete for security reasons.</p>";
    echo "</div>";
} else {
    echo "<div class='error'><h3>✗ Setup completed with errors</h3>";
    echo "<p>Please review the error messages above and fix any issues.</p></div>";
}
echo "</div>";

echo "</div></body></html>";

$conn->close();
?>
