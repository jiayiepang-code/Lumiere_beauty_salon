<?php
session_start();

// 1. Connect to Database
$conn = new mysqli('localhost', 'root', '', 'lumiere_salon_db');

// 2. Get the OTP user typed in Step 3
$user_otp_input = $_POST['otp_code']; // "1234"

// 3. Get the Real OTP from Session
$real_otp = $_SESSION['temp_user']['otp'];

// 4. CHECK: Do they match?
if ($user_otp_input == $real_otp) {
    
    // MATCH! Now we save the user to the database for real
    $u = $_SESSION['temp_user']; // Get data back from temp storage
    
    $fname = $u['first_name'];
    $lname = $u['last_name'];
    $email = $u['email'];
    $phone = $u['phone'];
    $pass  = $u['password'];
    $role  = 'customer'; // Default role

    // Insert SQL
    $sql = "INSERT INTO users (username, password, email, phone_number, role) 
            VALUES ('$fname $lname', '$pass', '$email', '$phone', '$role')";

    if ($conn->query($sql) === TRUE) {
        // Clear the session so they can't register twice
        unset($_SESSION['temp_user']);
        echo "success";
    } else {
        echo "db_error: " . $conn->error;
    }

} else {
    echo "wrong_otp";
}

$conn->close();
?>