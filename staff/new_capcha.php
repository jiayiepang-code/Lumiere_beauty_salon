<?php
session_start();

// Generate new CAPTCHA
function generateCaptcha($length = 5) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

$newCode = generateCaptcha();

// Update the session
$_SESSION['fp_captcha'] = $newCode;

// Output only the text (NO HTML)
echo $newCode;
