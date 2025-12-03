<?php
session_start();

// 1. Get the email from the JavaScript request
$email = $_POST['email'];

// 2. Generate a random 4-digit code
$otp_code = rand(1000, 9999);

// 3. Save everything to SESSION (Temporary memory)
// We need this later for the final registration
$_SESSION['temp_user'] = [
    'first_name' => $_POST['first_name'],
    'last_name'  => $_POST['last_name'],
    'phone'      => $_POST['phone'],
    'email'      => $email,
    'password'   => $_POST['password'], // Note: Hash this in real projects
    'otp'        => $otp_code
];

// 4. PREPARE THE EMAIL (Using the template we made)
$subject = "Your Verification Code - Lumière Salon";
$message = '
<!DOCTYPE html>
<html>
<head>
  <style>
    .otp-box { border: 2px dashed #c29076; padding: 20px; text-align: center; background: #fcf8f6; }
    .otp-code { font-size: 38px; color: #c29076; font-weight: bold; letter-spacing: 10px;}
  </style>
</head>
<body style="font-family: Arial, sans-serif; color: #5c4e4b;">
  <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #c29076; text-align: center;">Lumière Beauty Salon</h1>
    <p>Hello Beautiful,</p>
    <p>Please use the verification code below to complete your registration:</p>
    
    <div class="otp-box">
        <div class="otp-code">' . $otp_code . '</div>
    </div>
    
    <p>This code expires in 10 minutes.</p>
  </div>
</body>
</html>
';

// 5. SEND EMAIL
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= 'From: <noreply@lumiere.com>' . "\r\n";

if(mail($email, $subject, $message, $headers)) {
    echo "success";
} else {
    echo "error";
}
?>