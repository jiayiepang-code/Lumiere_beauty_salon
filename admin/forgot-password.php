<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../config/database.php';
require '../mailer.php';

// Connect to DB using PDO
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$message = '';
$message_class = '';
$form_sent = false;
$email_sent = isset($_SESSION['fp_email']) ? $_SESSION['fp_email'] : '';

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
        }
    }
    
    // Only proceed with email sending if no error message set (cooldown not active)
    if (empty($message) || $message_class !== 'error') {
        if (!$email) {
            $message = 'Invalid email address.';
            $message_class = 'error';
        } else {
            // Check if admin exists with this email
            $stmt = $db->prepare('SELECT staff_email FROM Staff WHERE staff_email = ? AND role = "admin"');
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin) {
                $admin_email = $admin['staff_email'];
                // Generate token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Insert token into password_resets table using admin_email
                $stmt2 = $db->prepare('INSERT INTO password_resets (customer_email, token, expires_at) VALUES (?, ?, ?)');
                $stmt2->execute([$admin_email, $token, $expires]);
                
                // Send email
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . "/admin/reset-password.php?token=$token";
                $subject = 'Admin Password Reset Request - Lumière';
                $body = '<div style="font-family:\'Playfair Display\',\'Georgia\',serif,Arial,sans-serif;background:#f5e9e4;padding:40px 20px;">
                    <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:16px;box-shadow:0 4px 20px rgba(162,110,96,0.15);overflow:hidden;">
                        <!-- Header -->
                        <div style="background:linear-gradient(135deg, #A26E60 0%, #8a5a4f 100%);padding:30px 24px;text-align:center;border-bottom:3px solid #7a4d42;">
                            <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:700;font-family:\'Playfair Display\',serif;letter-spacing:0.5px;">Lumière Beauty Salon</h1>
                        </div>
                        
                        <!-- Content -->
                        <div style="padding:40px 32px;text-align:center;">
                            <h2 style="color:#A26E60;margin:0 0 20px 0;font-size:22px;font-weight:600;font-family:\'Playfair Display\',serif;">Admin Password Reset Request</h2>
                            <p style="color:#5c4e4b;font-size:16px;line-height:1.6;margin-bottom:32px;text-align:left;">We received a request to reset your admin account password. Click the button below to set a new password. This link will expire in 1 hour.</p>
                            
                            <a href="' . htmlspecialchars($reset_link) . '" style="display:inline-block;padding:14px 40px;background:linear-gradient(135deg, #A26E60 0%, #8a5a4f 100%);color:#ffffff;border-radius:30px;font-weight:600;text-decoration:none;font-size:16px;margin:0 auto 32px;box-shadow:0 4px 12px rgba(162,110,96,0.3);transition:all 0.3s ease;">Reset Password</a>
                            
                            <div style="border-top:1px solid #e6d9d2;padding-top:24px;margin-top:32px;">
                                <p style="color:#8a766e;font-size:14px;line-height:1.5;margin:0;text-align:left;">If you did not request a password reset, you can safely ignore this email.</p>
                                <p style="color:#8a766e;font-size:14px;margin-top:16px;margin-bottom:0;font-style:italic;">— Lumière Beauty Salon Team</p>
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div style="background:#faf5f2;padding:20px 32px;text-align:center;border-top:1px solid #e6d9d2;">
                            <p style="color:#8a766e;font-size:12px;margin:0;">© ' . date('Y') . ' Lumière Beauty Salon. All rights reserved.</p>
                        </div>
                    </div>
                </div>';
                
                $result = sendMail($email, $subject, $body);
                
                if ($result === true) {
                    $message = 'A reset link has been sent to your email. Please check your mailbox.';
                    $message_class = 'success';
                    $form_sent = true;
                    $email_sent = $email;
                    $_SESSION['fp_email_sent_time'] = time();
                    $_SESSION['fp_email'] = $email;
                } else {
                    $message = 'Failed to send reset email: ' . htmlspecialchars($result);
                    $message_class = 'error';
                    $form_sent = false;
                }
            } else {
                // Security: Don't reveal if email exists or not
                $message = 'If that email exists, a reset link has been sent.';
                $message_class = 'info';
                $form_sent = true;
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
    <title>Forgot Password – Admin Portal</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-page">

    <div class="auth-container">
        <div class="auth-sidebar" style="background: linear-gradient(135deg, #ac7c6e 0%, #A26E60 50%, #6d4236 100%);">
            <div>
                <h2>Admin Portal</h2>
                <div class="logo-container">
                    <img src="../images/16.png" class="sidebar-logo" alt="Lumière Logo">
                </div>
                <p>Enter your email address and we'll send you a link to reset your password.</p>
            </div>
            <div>
                <p>Lumière Beauty Salon</p>
            </div>
        </div>

        <div class="auth-main">
            <form class="auth-form admin-theme" action="forgot-password.php" method="post">
                <div class="form-header">
                    <h1 style="color: #A26E60;">Forgot Password</h1>
                    <p>Admin Account Recovery</p>
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
                                <form method="POST" action="forgot-password.php" id="resend-form">
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email_sent); ?>">
                                    <input type="hidden" name="resend_email" value="1">
                                    <button type="submit" id="resend-btn" class="submit-btn" style="background: #A26E60; padding: 8px 16px; font-size: 14px; margin-top: 10px; cursor: pointer; border: none;">
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
                            <img src="../images/75.png" class="input-icon" alt="Email">
                            <input type="email" 
                                   class="form-control indent-icon" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Enter your admin email address" 
                                   required>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" style="background: #A26E60;">Send Reset Link</button>
                <?php endif; ?>

                <div class="switch-form">
                    <a href="login.php" style="color: #A26E60;">Back to Login</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>













