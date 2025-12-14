<?php
session_start();
require_once 'config/database.php';

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
            // Search DB
            $db = (new Database())->getConnection();
            if (!$db) {
                $error = "Database connection failed. Please try again.";
            } else {
                // No customer_id column; use phone as key
                $stmt = $db->prepare("SELECT phone FROM customer WHERE customer_email = ? OR phone = ?");
                $stmt->execute([$login_id, $login_id]);

                if ($stmt->rowCount() === 1) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    $_SESSION['fp_user_id'] = $user['phone']; // use phone as ID surrogate
                    $_SESSION['fp_phone']   = $user['phone'];

                    $step = 2; // Go to phone verification
                } else {
                    $error = "Account not found.";
                }
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
        } else {

            $hash = password_hash($new_password, PASSWORD_DEFAULT);

            $db = (new Database())->getConnection();
            if (!$db) {
                $error = "Database connection failed. Please try again.";
            } else {
                // Update by phone (no customer_id column)
                $stmt = $db->prepare("UPDATE customer SET password = ? WHERE phone = ?");
                $stmt->execute([$hash, $_SESSION['fp_user_id']]);

                $success = "Password reset successful. You can now login.";

                $step = 4; // Show success
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password – Lumière Beauty Salon</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/script.js" defer></script>
</head>
<body>

<div class="floating-btn-group">
    <a href="staff/login.php" class="float-wrapper staff-btn-container" style="text-decoration:none;">
        <div class="icon-circle">
            <img src="images/79.png" alt="Staff">
        </div>
        <span class="float-text">Staff Login</span>
    </a>

    <a href="admin/login.php" class="float-wrapper admin-btn-container" style="text-decoration:none;">
        <div class="icon-circle">
            <img src="images/80.png" alt="Admin">
        </div>
        <span class="float-text">Admin Login</span>
    </a>
</div>

<div class="auth-container">

    <div class="auth-sidebar">
        <div>
            <h2>Reset Password</h2>
            <div class="logo-container">
                <img src="images/16.png" class="sidebar-logo" alt="Lumière Logo">
            </div>
            <p>Follow the steps to recover your account.</p>
        </div>
        <p>Lumière Beauty Salon</p>
    </div>

    <div class="auth-main">

        <form class="auth-form" method="POST">

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
                        <img src="images/75.png" class="input-icon">
                        <input type="text" 
                               name="login_id" 
                               class="form-control indent-icon" 
                               placeholder="Email or Phone Number" 
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; color: var(--text-color); font-weight: 600;">Enter CAPTCHA:</label>
                    <div class="captcha-wrapper" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 12px;">
                        <div class="captcha-box" style="text-align: center; margin: 0 auto;">
                            <?php echo $_SESSION['fp_captcha']; ?>
                        </div>
                        <div class="input-wrapper">
                            <input type="text" 
                                   name="captcha" 
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
                        <img src="images/74.png" class="input-icon">
                        <input type="password" 
                               name="new_password" 
                               class="form-control indent-icon" 
                               placeholder="New Password" 
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <img src="images/74.png" class="input-icon">
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
                    <p style="color: var(--success-color); font-size: 18px; margin-bottom: 30px;">
                        <?php echo htmlspecialchars($success); ?>
                    </p>
                    <a href="login.php" class="submit-btn" style="text-decoration: none; display: inline-block; width: auto; padding: 12px 40px;">Back to Login</a>
                </div>
            <?php endif; ?>

            <div class="switch-form">
                <a href="login.php">Back to Login</a>
            </div>

        </form>

    </div>
</div>
</body>
</html>
