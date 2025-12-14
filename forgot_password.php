<?php
session_start();
require_once 'config/database.php';

// Determine current page/step
$currentPage = $_GET['page'] ?? 'find-account'; // find-account, email-sent, reset-password, success
$error = "";
$success = "";
$email = $_SESSION['fp_email'] ?? '';

// Generate CAPTCHA
function generateCaptcha($length = 5) {
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle resend email (from email-sent page)
    if (isset($_POST['resend_email'])) {
        // Check if email was already sent and enforce 30-second cooldown
        $lastEmailTime = $_SESSION['fp_email_sent_time'] ?? 0;
        $currentTime = time();
        $timeSinceLastEmail = $currentTime - $lastEmailTime;
        
        if ($timeSinceLastEmail < 30) {
            $remainingSeconds = 30 - $timeSinceLastEmail;
            $error = "Please wait {$remainingSeconds} second(s) before requesting another email.";
            $currentPage = 'email-sent';
        } else {
            // Resend email - regenerate token and send
            if (isset($_SESSION['fp_email']) && isset($_SESSION['fp_user_id'])) {
                $_SESSION['fp_reset_token'] = bin2hex(random_bytes(32)); // Generate new reset token
                $_SESSION['fp_token_expiry'] = time() + 3600; // 1 hour expiry
                $_SESSION['fp_email_sent_time'] = time(); // Update timestamp
                
                // TODO: Send email with reset link
                // sendPasswordResetEmail($_SESSION['fp_email'], $_SESSION['fp_reset_token']);
                
                $success = "Password reset email has been resent to your email address.";
                $currentPage = 'email-sent';
            } else {
                $error = "Session expired. Please start over.";
                $currentPage = 'find-account';
            }
        }
    }

    // STEP 1 — Find account using email
    if (isset($_POST['find_account'])) {
        $email_input = trim($_POST['email']);
        $captcha = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';

        // Case-sensitive CAPTCHA validation
        if (empty($captcha) || $captcha !== $_SESSION['fp_captcha']) {
            $error = "Incorrect CAPTCHA. Please check the case of letters.";
            $currentPage = 'find-account';
        } else {
            // Search DB for email
            $db = (new Database())->getConnection();
            if (!$db) {
                $error = "Database connection failed. Please try again.";
                $currentPage = 'find-account';
            } else {
                $stmt = $db->prepare("SELECT customer_email, phone FROM customer WHERE customer_email = ?");
                $stmt->execute([$email_input]);

                if ($stmt->rowCount() === 1) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $_SESSION['fp_email'] = $user['customer_email'];
                    $_SESSION['fp_user_id'] = $user['phone']; // use phone as ID
                    $_SESSION['fp_reset_token'] = bin2hex(random_bytes(32)); // Generate reset token
                    $_SESSION['fp_token_expiry'] = time() + 3600; // 1 hour expiry
                    $_SESSION['fp_email_sent_time'] = time(); // Track when email was sent
                    
                    // TODO: Send email with reset link
                    // sendPasswordResetEmail($user['customer_email'], $_SESSION['fp_reset_token']);
                    
                    $currentPage = 'email-sent';
                } else {
                    $error = "Account not found.";
                    $currentPage = 'find-account';
                }
            }
        }

        // Always refresh captcha after try
        $_SESSION['fp_captcha'] = generateCaptcha();
    }

    // STEP 2 — User confirms they received email (or skip for demo)
    if (isset($_POST['email_received'])) {
        $currentPage = 'reset-password';
    }

    // STEP 3 — Reset password
    if (isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if ($new_password !== $confirm) {
            $error = "Passwords do not match.";
            $currentPage = 'reset-password';
            } else {
                // Validate password strength - must meet all 5 rules
                $hasLength = strlen($new_password) >= 8;
                $hasUpper = preg_match('/[A-Z]/', $new_password);
                $hasLower = preg_match('/[a-z]/', $new_password);
                $hasNumber = preg_match('/\d/', $new_password);
                $hasSpecial = preg_match('/[@$!%*?&_]/', $new_password);
                
                if (!$hasLength || !$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial) {
                    $error = "Password must be STRONG. Please ensure all requirements are met: at least 8 characters, one uppercase letter, one lowercase letter, one number, and one symbol (@$!%*?&_).";
                    $currentPage = 'reset-password';
                } else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);

                $db = (new Database())->getConnection();
                if (!$db) {
                    $error = "Database connection failed. Please try again.";
                    $currentPage = 'reset-password';
                } else {
                    // Update password by phone
                    $stmt = $db->prepare("UPDATE customer SET password = ? WHERE phone = ?");
                    $result = $stmt->execute([$hash, $_SESSION['fp_user_id']]);

                    if ($result) {
                        // Clear session data
                        unset($_SESSION['fp_email']);
                        unset($_SESSION['fp_user_id']);
                        unset($_SESSION['fp_reset_token']);
                        unset($_SESSION['fp_token_expiry']);
                        
                        $success = "Password reset successful. You can now login.";
                        $currentPage = 'success';
                    } else {
                        $error = "Failed to update password. Please try again.";
                        $currentPage = 'reset-password';
                    }
                }
            }
        }
    }
}

// Get email for display
$email = $_SESSION['fp_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – Lumière Beauty Salon</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Import theme variables */
        :root {
            --primary-color: #c29076;
            --primary-hover: #a87b65;
            --text-light: #8a8a95;
            --border-color: #e6d9d2;
            --bg-gradient: linear-gradient(180deg, #f5e9e4, #faf5f2, #ffffff);
        }
        /* Forgot Password Flow Styles */
        .forgot-password-container {
            min-height: 100vh;
            background: var(--bg-gradient, linear-gradient(180deg, #f5e9e4, #faf5f2, #ffffff));
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .forgot-password-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            overflow: hidden;
            max-width: 1200px;
            width: 100%;
            display: flex;
            min-height: 600px;
        }

        /* Left Panel */
        .forgot-left-panel {
            width: 40%;
            background: linear-gradient(135deg, #d8a88e 0%, #c29076 50%, #8d5a48 100%);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: white;
        }

        .forgot-left-panel h1 {
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 0;
            color: rgb(255, 255, 255);
            text-shadow: 0px 2px 6px rgba(0,0,0,0.18);
        }

        .forgot-logo-container {
            background-color: rgba(255, 253, 250, 0.85);
            border-radius: 50%;
            padding: 20px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            margin: 20px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 2px solid rgba(255,255,255,0.3);
            width: auto;
            height: auto;
        }

        .forgot-logo-container .sidebar-logo {
            width: 150px;
            height: auto;
            margin: 0;
            display: block;
        }

        .forgot-left-panel p {
            color: rgb(255, 255, 255);
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 0;
            text-shadow: 0px 2px 6px rgba(0,0,0,0.18);
        }

        .forgot-left-panel .footer-text {
            color: rgb(255, 255, 255);
            font-size: 18px;
            text-shadow: 0px 2px 6px rgba(0,0,0,0.18);
        }

        /* Right Panel */
        .forgot-right-panel {
            width: 60%;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #fff;
        }

        .forgot-right-panel .form-header {
            text-align: center;
            margin-bottom: 25px;
            margin-top: 0;
        }

        .forgot-right-panel .form-header h2 {
            font-size: 32px;
            font-weight: bold;
            color: var(--primary-color, #c29076);
            margin-bottom: 5px;
        }

        .forgot-right-panel .subtitle {
            color: var(--text-light, #8a8a95);
            font-size: 14px;
            margin-bottom: 0;
        }

        /* Form Elements */
        .forgot-form-group {
            margin-bottom: 24px;
        }

        .forgot-form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color, #5c4e4b);
            margin-bottom: 8px;
        }

        .forgot-input-wrapper {
            position: relative;
        }

        .forgot-input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            opacity: 0.6;
            z-index: 5;
            pointer-events: none;
        }

        .forgot-password-toggle img {
            width: 20px;
            height: 20px;
            opacity: 0.6;
            transition: opacity 0.2s;
        }

        .forgot-password-toggle:hover img {
            opacity: 1;
        }

        .forgot-input {
            width: 100%;
            height: 50px;
            padding: 10px 15px 10px 45px;
            border: 1px solid var(--border-color, #e6d9d2);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            color: #333;
        }

        .forgot-input:focus {
            outline: none;
            border-color: var(--primary-color, #c29076);
            box-shadow: 0 0 0 2px rgba(194, 144, 118, 0.2);
        }

        .forgot-password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            z-index: 5;
        }

        /* CAPTCHA */
        .forgot-captcha-container {
            margin-bottom: 24px;
        }

        .forgot-captcha-instruction {
            color: var(--text-color, #5c4e4b);
            font-size: 14px;
            margin-bottom: 12px;
        }

        .forgot-captcha-wrapper {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
            margin-top: 12px;
        }

        .forgot-captcha-display {
            background: #eeeeee;
            border-radius: 8px;
            padding: 12px 20px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
        }

        .forgot-captcha-text {
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 6px;
            color: #333;
            user-select: none;
            display: inline-block;
        }

        .forgot-captcha-input {
            width: 100%;
            height: 50px;
            padding: 10px 15px;
            border: 1px solid #e6d6c2;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            color: #333;
            background: white;
        }

        .forgot-captcha-input:focus {
            outline: none;
            border-color: var(--primary-color, #c29076);
            box-shadow: 0 0 0 2px rgba(194, 144, 118, 0.2);
        }

        .forgot-captcha-refresh {
            border: none;
            background: none;
            padding: 0;
            margin-top: 5px;
            font-size: 14px;
            color: var(--primary-color, #c29076);
            cursor: pointer;
            text-decoration: underline;
        }

        .forgot-captcha-refresh:hover {
            color: var(--primary-hover, #a87b65);
        }

        /* Buttons */
        .forgot-btn-primary {
            width: 100%;
            background: var(--primary-color, #c29076);
            color: white;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
            border: none;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 15px;
            margin-bottom: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .forgot-btn-primary:hover {
            background: var(--primary-hover, #a87b65);
        }

        .forgot-btn-link {
            width: 100%;
            color: var(--primary-color, #c29076);
            font-weight: 600;
            background: none;
            border: none;
            cursor: pointer;
            text-align: center;
            padding: 8px;
            text-decoration: none;
            display: block;
        }

        .forgot-btn-link:hover {
            color: var(--primary-hover, #a87b65);
        }

        .forgot-btn-secondary {
            width: 100%;
            color: #6b7280;
            font-weight: 500;
            background: none;
            border: none;
            cursor: pointer;
            text-align: center;
            padding: 8px;
        }

        .forgot-btn-secondary:hover {
            color: #374151;
        }

        /* Password Strength Styles - Matching Register Form */
        .password-strength-wrapper {
            width: 100%;
        }

        .password-strength {
            height: 4px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
            display: none;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .strength-weak { background: #d9534f; }
        .strength-fair { background: #f0ad4e; }
        .strength-good { background: #5bc0de; }
        .strength-strong { background: #5cb85c; }

        .strength-text {
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .hints-popup {
            display: none;
            position: absolute;
            top: 100px;
            left: 0;
            width: 100%;
            background: #ffffff;
            border: 1px solid var(--border-color, #e6d9d2);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
            z-index: 100;
            font-size: 13px;
        }

        .hints-popup p {
            font-weight: bold;
            color: var(--primary-color, #c29076);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .hints-popup ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .rule-item {
            font-size: 13px;
            color: #a88f87;
            padding: 3px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease;
        }

        .rule-item.valid {
            color: var(--success-color, #5cb85c);
            font-weight: 600;
        }

        .forgot-btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #ccc;
        }

        .forgot-btn-primary:disabled:hover {
            background: #ccc;
        }

        /* Email Sent Page */
        .forgot-email-sent-icon {
            width: 80px;
            height: 80px;
            background: #d1fae5;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 40px;
        }

        .forgot-info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .forgot-info-box p {
            font-size: 14px;
            color: #0369a1;
            margin: 0;
        }

        /* Success Page */
        .forgot-success-icon {
            width: 80px;
            height: 80px;
            background: #d1fae5;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 40px;
            color: #059669;
        }

        .forgot-success-text {
            text-align: center;
            margin-bottom: 32px;
        }

        /* Error Message */
        .forgot-error {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-left: 3px solid var(--error-color, #d9534f);
            color: var(--error-color, #d9534f);
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        /* Password hint */
        .forgot-password-hint {
            font-size: 12px;
            color: var(--text-light, #8a8a95);
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .forgot-password-card {
                flex-direction: column;
            }

            .forgot-left-panel,
            .forgot-right-panel {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="forgot-password-card">
            <!-- Left Panel -->
            <div class="forgot-left-panel">
                <div>
                    <h1>
                        <?php 
                        if ($currentPage === 'find-account') echo 'Reset Password';
                        elseif ($currentPage === 'email-sent') echo 'Check Your Email';
                        elseif ($currentPage === 'reset-password') echo 'New Password';
                        elseif ($currentPage === 'success') echo 'All Set!';
                        ?>
                    </h1>
                    
                    <div class="forgot-logo-container">
                        <img src="images/16.png" class="sidebar-logo" alt="Lumière Logo">
                    </div>

                    <p>
                        <?php 
                        if ($currentPage === 'find-account') echo 'Follow the steps to recover your account.';
                        elseif ($currentPage === 'email-sent') echo 'We\'ve sent you a password reset link.';
                        elseif ($currentPage === 'reset-password') echo 'Create a strong password for your account.';
                        elseif ($currentPage === 'success') echo 'Your password has been successfully reset!';
                        ?>
                    </p>
                </div>

                <p class="footer-text">Lumière Beauty Salon</p>
            </div>

            <!-- Right Panel -->
            <div class="forgot-right-panel">
                <?php if ($error): ?>
                    <div class="forgot-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Find Account Page -->
                <?php if ($currentPage === 'find-account'): ?>
                    <div class="form-header">
                        <h2>Find Your Account</h2>
                        <p class="subtitle">Enter your email address to receive a password reset link.</p>
                    </div>

                    <form method="POST">
                        <div class="forgot-form-group">
                            <label>Email Address</label>
                            <div class="forgot-input-wrapper">
                                <img src="images/72.png" class="forgot-input-icon" alt="Email">
                                <input type="email" 
                                       name="email" 
                                       class="forgot-input" 
                                       placeholder="your.email@example.com" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="forgot-form-group">
                            <p class="forgot-captcha-instruction">Please enter the characters shown below to verify you're not a robot.</p>
                            <div class="forgot-captcha-wrapper">
                                <div class="forgot-captcha-display">
                                    <span class="forgot-captcha-text" id="captchaDisplay"><?php echo $_SESSION['fp_captcha']; ?></span>
                                </div>
                                <input type="text" 
                                       name="captcha" 
                                       class="forgot-captcha-input" 
                                       placeholder="Enter CAPTCHA here" 
                                       maxlength="10"
                                       required>
                                <button type="button" 
                                        class="forgot-captcha-refresh" 
                                        onclick="refreshCaptcha()">Refresh CAPTCHA</button>
                            </div>
                        </div>

                        <button type="submit" name="find_account" class="forgot-btn-primary">Next</button>
                    </form>

                    <a href="login.php" 
                       class="forgot-btn-link" 
                       style="text-decoration: none; display: block; text-align: center;">Back to Login</a>

                <!-- Email Sent Page -->
                <?php elseif ($currentPage === 'email-sent'): ?>
                    <?php if ($error): ?>
                        <div class="forgot-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="forgot-success" style="background: #d1fae5; border: 1px solid #86efac; border-left: 3px solid #22c55e; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div style="text-align: center; margin-bottom: 32px;">
                        <div class="forgot-email-sent-icon">✉️</div>
                        <div class="form-header">
                            <h2>Check Your Email</h2>
                            <p class="subtitle">
                                We've sent a password reset link to<br>
                                <strong><?php echo htmlspecialchars($email); ?></strong>
                            </p>
                        </div>
                    </div>

                    <div class="forgot-info-box">
                        <p>
                            <strong>Note:</strong> The reset link will expire in 1 hour. If you don't see the email, 
                            check your spam folder.
                        </p>
                    </div>

                    <form method="POST">
                        <button type="submit" name="email_received" class="forgot-btn-primary">I've received the email</button>
                    </form>

                    <?php
                    // Calculate remaining cooldown time
                    $lastEmailTime = $_SESSION['fp_email_sent_time'] ?? 0;
                    $currentTime = time();
                    $timeSinceLastEmail = $currentTime - $lastEmailTime;
                    $remainingSeconds = max(0, 30 - $timeSinceLastEmail);
                    $canResend = $remainingSeconds <= 0;
                    ?>
                    
                    <form method="POST" id="resendEmailForm" style="margin-bottom: 8px;">
                        <input type="hidden" name="resend_email" value="1">
                        <button type="submit" 
                                class="forgot-btn-link" 
                                id="resendEmailBtn"
                                <?php echo $canResend ? '' : 'disabled style="opacity: 0.5; cursor: not-allowed;"'; ?>>
                            <?php if ($canResend): ?>
                                Resend Email
                            <?php else: ?>
                                Resend Email (Wait <?php echo $remainingSeconds; ?>s)
                            <?php endif; ?>
                        </button>
                    </form>
                    
                    <?php if (!$canResend): ?>
                        <p style="text-align: center; font-size: 12px; color: #666; margin-top: 4px;">
                            Please wait <?php echo $remainingSeconds; ?> second(s) before requesting another email.
                        </p>
                    <?php endif; ?>

                    <button type="button" 
                            onclick="window.location.href='forgot_password.php?page=find-account'" 
                            class="forgot-btn-secondary">Back to Find Account</button>

                <!-- Reset Password Page -->
                <?php elseif ($currentPage === 'reset-password'): ?>
                    <div class="form-header">
                        <h2>Create New Password</h2>
                        <p class="subtitle">Your new password must be different from previously used passwords.</p>
                    </div>

                    <form method="POST" id="resetPasswordForm">
                        <div class="forgot-form-group" style="position: relative;">
                            <label>New Password</label>
                            <div class="forgot-input-wrapper">
                                <img src="images/75.png" class="forgot-input-icon" alt="Password">
                                <input type="password" 
                                       name="new_password" 
                                       id="newPassword"
                                       class="forgot-input" 
                                       placeholder="Enter new password" 
                                       required
                                       oninput="checkPasswordRules()">
                                <button type="button" 
                                        class="forgot-password-toggle" 
                                        onclick="togglePassword('newPassword', this)">
                                    <img src="images/74.png" alt="Toggle" style="width: 20px; height: 20px;">
                                </button>
                            </div>
                            
                            <!-- Password Rules (matches register form position) -->
                            <div class="hints-popup" id="forgotPasswordHints" style="display: none;">
                                <p>Password must contain:</p>
                                <ul>
                                    <li class="rule-item" id="forgotRuleLength">✔ At least 8 characters</li>
                                    <li class="rule-item" id="forgotRuleUpper">✔ One uppercase letter (A-Z)</li>
                                    <li class="rule-item" id="forgotRuleLower">✔ One lowercase letter (a-z)</li>
                                    <li class="rule-item" id="forgotRuleNumber">✔ One number (0-9)</li>
                                    <li class="rule-item" id="forgotRuleSpecial">✔ One symbol (@$!%*?&_)</li>
                                </ul>
                            </div>
                        </div>

                        <div class="forgot-form-group">
                            <label>Confirm Password</label>
                            <div class="forgot-input-wrapper">
                                <img src="images/75.png" class="forgot-input-icon" alt="Password">
                                <input type="password" 
                                       name="confirm_password" 
                                       id="confirmPassword"
                                       class="forgot-input" 
                                       placeholder="Confirm new password" 
                                       required>
                                <button type="button" 
                                        class="forgot-password-toggle" 
                                        onclick="togglePassword('confirmPassword', this)">
                                    <img src="images/74.png" alt="Toggle" style="width: 20px; height: 20px;">
                                </button>
                            </div>
                        </div>

                        <button type="submit" name="reset_password" id="resetPasswordBtn" class="forgot-btn-primary" disabled>Reset Password</button>
                    </form>

                    <button type="button" 
                            onclick="window.location.href='forgot_password.php?page=find-account'" 
                            class="forgot-btn-secondary">Cancel</button>

                <!-- Success Page -->
                <?php elseif ($currentPage === 'success'): ?>
                    <div class="forgot-success-text">
                        <div class="forgot-success-icon">✓</div>
                        <div class="form-header">
                            <h2>Password Reset Complete!</h2>
                            <p class="subtitle">
                                Your password has been successfully changed.<br>
                                You can now log in with your new password.
                            </p>
                        </div>

                        <a href="login.php" class="forgot-btn-primary" style="text-decoration: none; display: inline-block; width: auto; padding: 12px 40px;">Go to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function refreshCaptcha() {
            fetch('refresh_captcha.php?type=fp')
                .then(response => response.text())
                .then(newCaptcha => {
                    document.getElementById('captchaDisplay').textContent = newCaptcha.trim();
                })
                .catch(error => {
                    console.error('Error refreshing CAPTCHA:', error);
                    // Fallback: reload page
                    location.reload();
                });
        }

        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('img');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.src = 'images/73.png'; // Eye open icon
            } else {
                input.type = 'password';
                if (icon) icon.src = 'images/74.png'; // Eye closed icon
            }
        }

        // Password strength variables
        let forgotPasswordStrength = 'weak';
        let isForgotPasswordStrong = false;

        // Password Strength Checker for Forgot Password
        function checkPasswordRules() {
            const password = document.getElementById('newPassword').value;
            const resetBtn = document.getElementById('resetPasswordBtn');
            const hintsContainer = document.getElementById('forgotPasswordHints');

            // Show password rules when user starts typing
            if (password.length > 0) {
                if (hintsContainer) hintsContainer.style.display = "block";
            } else {
                // Hide when password is empty
                if (hintsContainer) hintsContainer.style.display = "none";
            }

            // Get rule elements
            const ruleLength = document.getElementById("forgotRuleLength");
            const ruleUpper = document.getElementById("forgotRuleUpper");
            const ruleLower = document.getElementById("forgotRuleLower");
            const ruleNumber = document.getElementById("forgotRuleNumber");
            const ruleSpecial = document.getElementById("forgotRuleSpecial");

            // Check each rule
            let hasLength = password.length >= 8;
            let hasUpper = /[A-Z]/.test(password);
            let hasLower = /[a-z]/.test(password);
            let hasNumber = /\d/.test(password);
            let hasSpecial = /[@$!%*?&_]/.test(password);

            // Toggle checkmark/color classes
            if(ruleLength) ruleLength.classList.toggle("valid", hasLength);
            if(ruleUpper) ruleUpper.classList.toggle("valid", hasUpper);
            if(ruleLower) ruleLower.classList.toggle("valid", hasLower);
            if(ruleNumber) ruleNumber.classList.toggle("valid", hasNumber);
            if(ruleSpecial) ruleSpecial.classList.toggle("valid", hasSpecial);

            let validCount = hasLength + hasUpper + hasLower + hasNumber + hasSpecial;

            // Check if ALL 5 rules are met (strong password)
            isForgotPasswordStrong = validCount === 5;
            forgotPasswordStrength = validCount === 5 ? 'strong' : (validCount >= 3 ? 'good' : (validCount >= 2 ? 'fair' : 'weak'));

            // Enable/disable Reset Password button based on password strength
            if (resetBtn) {
                if (isForgotPasswordStrong) {
                    resetBtn.disabled = false;
                    resetBtn.style.opacity = '1';
                    resetBtn.style.cursor = 'pointer';
                    resetBtn.style.background = 'var(--primary-color, #c29076)';
                } else {
                    resetBtn.disabled = true;
                    resetBtn.style.opacity = '0.5';
                    resetBtn.style.cursor = 'not-allowed';
                    resetBtn.style.background = '#ccc';
                }
            }
        }

        // Hide password hints when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('newPassword');
            const hintsContainer = document.getElementById('forgotPasswordHints');
            
            // Hide hints when clicking outside the password input or hints area
            document.addEventListener('click', function(e) {
                if (passwordInput && hintsContainer) {
                    const isClickInsideInput = passwordInput.contains(e.target);
                    const isClickInsideHints = hintsContainer.contains(e.target);
                    
                    // If click is outside and password field is empty or blurred, hide hints
                    if (!isClickInsideInput && !isClickInsideHints) {
                        if (passwordInput.value.length === 0 || document.activeElement !== passwordInput) {
                            hintsContainer.style.display = 'none';
                        }
                    }
                }
            });
            
            // Show hints when focusing on password input
            if (passwordInput) {
                passwordInput.addEventListener('focus', function() {
                    if (hintsContainer) hintsContainer.style.display = 'block';
                });
                
                passwordInput.addEventListener('blur', function() {
                    // Only hide if password is empty
                    if (passwordInput.value.length === 0) {
                        if (hintsContainer) hintsContainer.style.display = 'none';
                    }
                });
            }
        });

        // Validate password match and strength on form submit
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetPasswordForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const newPassword = document.getElementById('newPassword').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;
                    
                    // Check if password is strong
                    if (!isForgotPasswordStrong || forgotPasswordStrength !== 'strong') {
                        e.preventDefault();
                        alert('Password must be STRONG. Please ensure all password requirements are met: at least 8 characters, one uppercase letter, one lowercase letter, one number, and one symbol.');
                        return false;
                    }
                    
                    // Check if passwords match
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match. Please try again.');
                        return false;
                    }
                });
            }
        });

        // Resend email countdown timer
        document.addEventListener('DOMContentLoaded', function() {
            const resendBtn = document.getElementById('resendEmailBtn');
            const resendForm = document.getElementById('resendEmailForm');
            
            <?php if (isset($_SESSION['fp_email_sent_time']) && $currentPage === 'email-sent'): ?>
            const lastEmailTime = <?php echo $_SESSION['fp_email_sent_time']; ?>;
            const currentTime = <?php echo time(); ?>;
            let remainingSeconds = Math.max(0, 30 - (currentTime - lastEmailTime));
            
            if (resendBtn && remainingSeconds > 0) {
                resendBtn.disabled = true;
                resendBtn.style.opacity = '0.5';
                resendBtn.style.cursor = 'not-allowed';
                
                const updateCountdown = setInterval(function() {
                    remainingSeconds--;
                    
                    if (remainingSeconds > 0) {
                        resendBtn.innerHTML = 'Resend Email (Wait ' + remainingSeconds + 's)';
                        
                        // Update the message below button if it exists
                        const countdownMsg = resendForm.nextElementSibling;
                        if (countdownMsg && countdownMsg.tagName === 'P') {
                            countdownMsg.innerHTML = 'Please wait ' + remainingSeconds + ' second(s) before requesting another email.';
                        }
                    } else {
                        clearInterval(updateCountdown);
                        resendBtn.disabled = false;
                        resendBtn.style.opacity = '1';
                        resendBtn.style.cursor = 'pointer';
                        resendBtn.innerHTML = 'Resend Email';
                        
                        // Remove or update the message below button
                        const countdownMsg = resendForm.nextElementSibling;
                        if (countdownMsg && countdownMsg.tagName === 'P') {
                            countdownMsg.style.display = 'none';
                        }
                    }
                }, 1000);
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>
