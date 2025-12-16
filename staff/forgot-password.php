<?php
require_once 'config.php';

$step = 1; 
$error = "";
$success = "";

// Generate CAPTCHA
function generateCaptcha($length = 5) {
    $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $out = "";
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

// Create CAPTCHA if not exist
if (empty($_SESSION['fp_captcha'])) {
    $_SESSION['fp_captcha'] = generateCaptcha();
}

// Handle form steps
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // STEP 1 — Find account using email OR phone
    if (isset($_POST['find_account'])) {

        $login_id = trim($_POST['login_id']);
        $captcha  = strtoupper(trim($_POST['captcha']));

        if ($captcha !== $_SESSION['fp_captcha']) {
            $error = "Incorrect CAPTCHA.";
        } else {
            try {
                // Search DB for staff by email or phone
                $stmt = $pdo->prepare("SELECT staff_email, phone FROM Staff WHERE staff_email = ? OR phone = ?");
                $stmt->execute([$login_id, $login_id]);

                if ($stmt->rowCount() === 1) {
                    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

                    $_SESSION['fp_staff_email'] = $staff['staff_email'];
                    $_SESSION['fp_phone'] = $staff['phone'];

                    $step = 2; // Go to phone verification
                } else {
                    $error = "Account not found.";
                }
            } catch(PDOException $e) {
                $error = "Database error. Please try again.";
            }
        }

        // Always refresh captcha after try
        $_SESSION['fp_captcha'] = generateCaptcha();
    }

    // STEP 2 — Verify last 4 digits of phone
    if (isset($_POST['verify_phone'])) {

        $last4     = trim($_POST['last4']);
        $realLast4 = substr($_SESSION['fp_phone'], -4);

        if ($last4 === $realLast4) {
            $step = 3; // Allow password reset
        } else {
            $error = "Incorrect last 4 digits.";
        }
    }

    // STEP 3 — Reset password
    if (isset($_POST['reset_password'])) {

        $new_password = $_POST['new_password'];
        $confirm      = $_POST['confirm_password'];

        if ($new_password !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters.";
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);

            try {
                // Update by staff_email
                $stmt = $pdo->prepare("UPDATE Staff SET password = ? WHERE staff_email = ?");
                $stmt->execute([$hash, $_SESSION['fp_staff_email']]);

                $success = "Password reset successful. You can now login.";

                // Clear session data
                unset($_SESSION['fp_staff_email']);
                unset($_SESSION['fp_phone']);
                unset($_SESSION['fp_captcha']);

                $step = 4; // Show success
            } catch(PDOException $e) {
                $error = "Database error. Please try again.";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Staff</title>
    <link rel="stylesheet" href="login.css">
    <style>
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        .error-message[style*="block"] {
            display: block;
        }
        .success-view {
            text-align: center;
            padding: 20px;
        }
        .success-icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .captcha-wrapper {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 12px;
        }
        .captcha-box {
            text-align: center;
            padding: 12px;
            background: #f5f5f5;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #968073;
            font-family: monospace;
        }
        .captcha-refresh {
            text-align: center;
            margin-top: 8px;
        }
        .captcha-refresh a {
            color: #968073;
            text-decoration: none;
            font-size: 14px;
        }
        .captcha-refresh a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="staff-page">
    <div class="auth-container">
        <div class="auth-sidebar">
            <div>
                <h2>Staff Portal</h2>
                <div class="logo-container">
                    <div class="logo-oval">
                        <img src="../images/16.png" class="sidebar-logo" alt="Lumière Logo">
                    </div>
                </div>
                <p>Follow the steps to recover your account.</p>
            </div>
            <div>
                <p>Lumière Beauty Salon</p>
            </div>
        </div>

        <div class="auth-main">
            <form class="auth-form staff-theme" method="POST">

                <div class="form-header">
                    <?php if ($step === 1): ?>
                        <h1>Find Your Account</h1>
                        <p>Enter your Email or Phone Number to begin.</p>
                    <?php elseif ($step === 2): ?>
                        <h1>Verify Phone Number</h1>
                        <p>Enter the last 4 digits of your registered phone number.</p>
                    <?php elseif ($step === 3): ?>
                        <h1>Reset Your Password</h1>
                        <p>Create a new password for your account.</p>
                    <?php elseif ($step === 4): ?>
                        <h1>Success!</h1>
                        <p>Your password has been reset successfully.</p>
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                    <div class="error-message" style="display: block;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                    <div class="form-group">
                        <div class="input-wrapper">
                            <img src="../images/75.png" class="input-icon">
                            <input type="text" 
                                   name="login_id" 
                                   class="form-control indent-icon" 
                                   placeholder="Email or Phone Number" 
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; color: #5c4e4b; font-weight: 600;">Enter CAPTCHA:</label>
                        <div class="captcha-wrapper">
                            <div class="captcha-box" id="captchaDisplay">
                                <?php echo $_SESSION['fp_captcha']; ?>
                            </div>
                            <div class="captcha-refresh">
                                <a href="#" onclick="refreshCaptcha(); return false;">Refresh CAPTCHA</a>
                            </div>
                            <div class="input-wrapper">
                                <input type="text" 
                                       name="captcha" 
                                       id="captchaInput"
                                       class="form-control" 
                                       placeholder="Enter CAPTCHA" 
                                       style="text-transform: uppercase; letter-spacing: 3px;"
                                       maxlength="5"
                                       required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="find_account" class="submit-btn">Next</button>

                <?php elseif ($step === 2): ?>
                    <div class="form-group">
                        <div class="input-wrapper">
                            <input type="text" 
                                   name="last4" 
                                   class="form-control" 
                                   placeholder="Last 4 digits of phone" 
                                   maxlength="4"
                                   pattern="[0-9]{4}"
                                   required>
                        </div>
                    </div>

                    <button type="submit" name="verify_phone" class="submit-btn">Verify</button>

                <?php elseif ($step === 3): ?>
                    <div class="form-group">
                        <div class="input-wrapper">
                            <img src="../images/75.png" class="input-icon">
                            <input type="password" 
                                   name="new_password" 
                                   class="form-control indent-icon" 
                                   placeholder="New Password" 
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-wrapper">
                            <img src="../images/75.png" class="input-icon">
                            <input type="password" 
                                   name="confirm_password" 
                                   class="form-control indent-icon" 
                                   placeholder="Confirm Password" 
                                   required>
                        </div>
                    </div>

                    <button type="submit" name="reset_password" class="submit-btn">Reset Password</button>

                <?php elseif ($step === 4): ?>
                    <div class="success-view">
                        <div class="success-icon">✓</div>
                        <p style="color: #28a745; font-size: 18px; margin-bottom: 30px;">
                            <?php echo htmlspecialchars($success); ?>
                        </p>
                        <a href="login.html" class="submit-btn" style="text-decoration: none; display: inline-block; width: auto; padding: 12px 40px;">Back to Login</a>
                    </div>
                <?php endif; ?>

                <div class="switch-form">
                    <a href="login.html">Back to Login</a>
                </div>

            </form>
        </div>
    </div>
    <script>
        function refreshCaptcha() {
            fetch('new_capcha.php')
                .then(response => response.text())
                .then(newCode => {
                    document.getElementById('captchaDisplay').textContent = newCode.trim();
                    document.getElementById('captchaInput').value = '';
                })
                .catch(error => {
                    console.error('Error refreshing CAPTCHA:', error);
                    alert('Failed to refresh CAPTCHA. Please reload the page.');
                });
        }
    </script>
</body>
</html>
