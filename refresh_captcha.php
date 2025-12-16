<?php
// File: refresh_captcha.php
session_start();

// Determine which captcha to refresh
$type = $_GET['type'] ?? 'register';

// Generate random code
$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
$code  = '';
for ($i = 0; $i < 5; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}

// Update Session based on type
if ($type === 'fp' || $type === 'forgot') {
    $_SESSION['fp_captcha'] = $code;
} else {
    $_SESSION['register_captcha'] = $code;
}

// Send code back to JavaScript
echo $code;
?>