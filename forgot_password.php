<?php
session_start(); // Start session for resend cooldown
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config/database.php';
require 'mailer.php';

// Connect to DB using PDO (consistent with rest of codebase)
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// #region agent log
@file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'forgot_password.php:13', 'message' => 'Database connection initialized', 'data' => ['method' => 'PDO'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
// #endregion

$message = '';
$message_class = '';
$form_sent = false; // Added this variable to track if the form has been sent
$email_sent = isset($_SESSION['fp_email']) ? $_SESSION['fp_email'] : ''; // Get email from session for resend

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    
    // Check cooldown for resend requests (10 seconds)
    if (isset($_POST['resend_email']) && isset($_SESSION['fp_email_sent_time'])) {
        $timeSinceLastEmail = time() - $_SESSION['fp_email_sent_time'];
        if ($timeSinceLastEmail < 10) {
            $remainingSeconds = 10 - $timeSinceLastEmail;
            $message = "Please wait {$remainingSeconds} second(s) before requesting another email.";
            $message_class = 'error';
            $form_sent = true;
            $email_sent = $email;
            // Skip email sending, go to display
            } else {
                // Cooldown passed, allow resend - continue with normal flow below
                // Timer will be reset after successful email send
            }
        }
    
    // Only proceed with email sending if no error message set (cooldown not active)
    if (empty($message) || $message_class !== 'error') {
    
    // #region agent log
    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'forgot_password.php:25', 'message' => 'POST request received', 'data' => ['email_input' => $_POST['email'] ?? 'none', 'email_validated' => $email ? 'yes' : 'no'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
    
    if (!$email) {
        $message = 'Invalid email address.';
        $message_class = 'error';
    } else {
        // Check if customer exists using customer table (not users)
        // #region agent log
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'forgot_password.php:35', 'message' => 'Checking customer existence', 'data' => ['email' => $email, 'table' => 'customer', 'column' => 'customer_email'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion
        
        $stmt = $db->prepare('SELECT customer_email FROM customer WHERE customer_email = ?');
        $stmt->execute([$email]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // #region agent log
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'forgot_password.php:40', 'message' => 'Customer lookup result', 'data' => ['email' => $email, 'found' => $customer ? 'yes' : 'no'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion
        
        if ($customer) {
            $customer_email = $customer['customer_email'];
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // #region agent log
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'forgot_password.php:48', 'message' => 'Generating reset token', 'data' => ['customer_email' => $customer_email, 'expires' => $expires], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'B']) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            
            // Insert token into password_resets table using customer_email (not user_id)
            $stmt2 = $db->prepare('INSERT INTO password_resets (customer_email, token, expires_at) VALUES (?, ?, ?)');
            $stmt2->execute([$customer_email, $token, $expires]);
            
            // #region agent log
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'forgot_password.php:53', 'message' => 'Token inserted into password_resets', 'data' => ['customer_email' => $customer_email, 'success' => 'yes'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'B']) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            
            // Send email
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token";
            $subject = 'Password Reset Request';
            $body = '<div style="font-family:Roboto,Arial,sans-serif;background:#f4f8fb;padding:32px 0;">
                <div style="max-width:480px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(25,118,210,0.08);padding:32px 24px;text-align:center;">
                    <h2 style="color:#1976d2;margin-bottom:16px;">Lumière Beauty Salon Password Reset</h2>
                    <p style="color:#333;font-size:1.1rem;margin-bottom:24px;">We received a request to reset your password. Click the button below to set a new password. This link will expire in 1 hour.</p>
                    <a href="' . $reset_link . '" style="display:inline-block;padding:12px 32px;background:#1976d2;color:#fff;border-radius:8px;font-weight:700;text-decoration:none;font-size:1.1rem;margin-bottom:24px;">Reset Password</a>
                    <p style="color:#888;font-size:0.95rem;margin-top:32px;">If you did not request a password reset, you can safely ignore this email.<br><br>— Lumière Beauty Salon Team</p>
                </div>
            </div>';
            // #region agent log
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'forgot_password.php:99', 'message' => 'Sending reset email', 'data' => ['email' => $email, 'reset_link' => $reset_link, 'body_length' => strlen($body), 'disk_free_space' => @disk_free_space(__DIR__)], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            
            $result = sendMail($email, $subject, $body);
            
            // #region agent log
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'forgot_password.php:73', 'message' => 'Email send result', 'data' => ['email' => $email, 'success' => $result === true ? 'yes' : 'no', 'error' => $result !== true ? $result : 'none'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'C']) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            
            if ($result === true) {
                $message = 'A reset link has been sent to your email. Please check your mailbox.';
                $message_class = 'success';
                $form_sent = true;
                $email_sent = $email; // Store email for resend
                $_SESSION['fp_email_sent_time'] = time(); // Store timestamp for countdown
                $_SESSION['fp_email'] = $email; // Store for resend
            } else {
                $message = 'Failed to send reset email: ' . htmlspecialchars($result);
                $message_class = 'error';
                $form_sent = false;
            }
        } else {
            // Security: Don't reveal if email exists or not
            $message = 'If that email exists, a reset link has been sent.';
            $message_class = 'info';
            $form_sent = true; // If email doesn't exist, we still show the form
            if (isset($_POST['email'])) {
                $email_sent = $_POST['email'];
                $_SESSION['fp_email_sent_time'] = time();
                $_SESSION['fp_email'] = $_POST['email'];
            }
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – Lumière Beauty Salon</title>
    <link rel="stylesheet" href="css/style.css">
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
                <h2>Reset Your Password</h2>
                <div class="logo-container">
                    <img src="images/16.png" class="sidebar-logo" alt="Lumière Logo">
                </div>
                <p>Enter your email address and we'll send you a link to reset your password.</p>
            </div>
            <p>Lumière Beauty Salon</p>
        </div>

        <div class="auth-main">
            <form class="auth-form" action="forgot_password.php" method="post">
                <div class="form-header">
                    <h1>Forgot Password</h1>
                </div>

                <?php if ($message): ?>
                    <div class="message <?php echo $message_class; ?>" style="margin-bottom: 20px; padding: 12px 15px; border-radius: 8px; <?php 
                        if ($message_class === 'success') echo 'color: #388e3c; background: #e8f5e9; border-left: 3px solid #388e3c;';
                        elseif ($message_class === 'error') echo 'color: #d32f2f; background: #ffebee; border-left: 3px solid #d32f2f;';
                        else echo 'color: #1976d2; background: #e3f2fd; border-left: 3px solid #1976d2;';
                    ?>">
                        <?php echo htmlspecialchars($message); ?>
                        <?php if ($message_class === 'success' && !empty($email_sent)): ?>
                            <div id="resend-container" style="margin-top: 15px;">
                                <form method="POST" action="forgot_password.php" id="resend-form">
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email_sent); ?>">
                                    <input type="hidden" name="resend_email" value="1">
                                    <button type="submit" id="resend-btn" class="submit-btn" style="background: var(--primary-color); padding: 8px 16px; font-size: 14px; margin-top: 10px; cursor: pointer; border: none;">
                                        Resend Link
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!isset($form_sent) || !$form_sent): ?>
                    <div class="form-group">
                        <div class="input-wrapper">
                            <img src="images/75.png" class="input-icon" alt="Email">
                            <input type="email" 
                                   class="form-control indent-icon" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Enter your email address" 
                                   required>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">Send Reset Link</button>
                <?php endif; ?>

                <div class="switch-form">
                    <a href="login.php">Back to Login</a>
                </div>
            </form>
        </div>
    </div>


</body>
</html> 