<?php
<<<<<<< HEAD
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Lumière</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-page">

<div class="auth-container">
    
    <div class="auth-sidebar" style="background: linear-gradient(135deg, #ac7c6e 0%, #A26E60 50%, #6d4236 100%);">
        <div>
            <h2>Admin Portal</h2>
            <div class="logo-container">
                <img src="../images/16.png" class="sidebar-logo" alt="Logo">
            </div>
            <p>System Management Access</p>
        </div>
        <p>Lumière Beauty Salon</p>
    </div>

    <div class="auth-main">
        <form class="auth-form admin-theme" id="adminForm" onsubmit="event.preventDefault();">
            
            <div class="form-header">
                <h1 style="color: #A26E60;">Administrator</h1>
                <p>Management Access Only</p>
            </div>

            <div class="form-group">
                <div class="phone-wrapper">
                    <span class="input-prefix">+60</span>
                    <input type="tel"
                           class="form-control phone-input"
                           id="adminId"
                           placeholder="12 345 6789"
                           maxlength="13"
                           oninput="formatPhoneNumber(this)">
                </div>
            </div>

            <div class="form-group">
                <div class="input-wrapper">
                    <img src="../images/75.png" class="input-icon" alt="Lock">
                    <input type="password" class="form-control indent-icon" id="adminPass" placeholder="Password" required>
                    <img src="../images/74.png" class="password-toggle" onclick="togglePass('adminPass')" alt="Show">
                </div>
            </div>

            <button type="button" class="submit-btn" style="background: #A26E60;" onclick="validateAdminLogin()">ADMIN LOGIN</button>

            <div class="switch-form">
                <a href="../login.php">Back to Customer Site</a>
            </div>

        </form>
    </div>

</div>

<script src="../js/script.js"></script>
</body>
</html>
=======
// 1. Include the connection file
include 'db.php';

// 2. Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 3. Get data from the HTML form
    $phone = $_POST['phone'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $raw_password = $_POST['password'];

    // 4. SECURITY: Hash the password
    // Never store plain text passwords!
    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

    // 5. Prepare the SQL Statement
    // We use ? placeholders to prevent SQL Injection
    $stmt = $conn->prepare("INSERT INTO customers (phone, name, email, password) VALUES (?, ?, ?, ?)");

    if ($stmt) {
        // 6. Bind parameters
        // "ssss" means: String, String, String, String
        $stmt->bind_param("ssss", $phone, $name, $email, $hashed_password);

        // 7. Execute and check for errors
        if ($stmt->execute()) {
            echo "<h3>Registration Successful!</h3>";
            echo "<p>Welcome, $name. <a href='login.html'>Click here to Login</a></p>";
        } else {
            // Check if error is due to duplicate Phone Number (Primary Key)
            if ($conn->errno == 1062) {
                echo "Error: This phone number is already registered.";
            } else {
                echo "Error: " . $stmt->error;
            }
        }
        
        $stmt->close();
    } else {
        echo "Database preparation error: " . $conn->error;
    }

    // 8. Close connection
    $conn->close();
}
?>
>>>>>>> origin/staff_update_5.2
