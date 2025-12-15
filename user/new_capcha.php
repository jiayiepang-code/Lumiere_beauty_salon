<?php
session_start();

// Generate new CAPTCHA
function generateRegisterCaptcha($length = 5) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

$newCode = generateRegisterCaptcha();

// Update the session
$_SESSION['register_captcha'] = $newCode;

// Output only the text (NO HTML)
echo $newCode;