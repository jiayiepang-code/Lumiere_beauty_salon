<?php
// File: auth/refresh_captcha.php
session_start();

// Generate random code
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$code  = '';
for ($i = 0; $i < 5; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}

// Update Session
$_SESSION['register_captcha'] = $code;

// Send code back to JavaScript
echo $code;
?>