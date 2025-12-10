<?php
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