<?php
require_once '../config/database.php';

// Connect to DB using PDO
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$message = '';
$show_form = false;
$token = isset($_GET['token']) ? $_GET['token'] : '';

if ($token) {
    // Check if token exists - using customer_email column to store staff_email
    $stmt = $db->prepare('SELECT customer_email, expires_at FROM password_resets WHERE token = ?');
    $stmt->execute([$token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resetData) {
        $staff_email = $resetData['customer_email']; // This contains staff_email for staff resets
        $expires_at = $resetData['expires_at'];
        
        // Verify it's actually a staff email (check if exists in Staff table)
        $stmt_check = $db->prepare('SELECT staff_email FROM Staff WHERE staff_email = ? AND role = "staff"');
        $stmt_check->execute([$staff_email]);
        $staff = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($staff && $expires_at && strtotime($expires_at) > time()) {
            $show_form = true;
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'] ?? '';
                $confirm = $_POST['confirm_password'] ?? '';
                
                // Validate password requirements (8 characters minimum, uppercase, lowercase, number, symbol)
                $hasLength = strlen($password) >= 8;
                $hasUpper = preg_match('/[A-Z]/', $password);
                $hasLower = preg_match('/[a-z]/', $password);
                $hasNumber = preg_match('/\d/', $password);
                $hasSpecial = preg_match('/[@$!%*?&_]/', $password);
                
                if (!$hasLength || !$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial) {
                    $message = 'Password must be at least 8 characters and contain uppercase, lowercase, number, and symbol.';
                } elseif ($password !== $confirm) {
                    $message = 'Passwords do not match.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Update staff password
                    $stmt2 = $db->prepare('UPDATE Staff SET password = ? WHERE staff_email = ?');
                    $stmt2->execute([$hash, $staff_email]);
                    
                    // Delete token
                    $stmt3 = $db->prepare('DELETE FROM password_resets WHERE token = ?');
                    $stmt3->execute([$token]);
                    
                    header('Location: login.php?message=Password reset successfully. Please login with your new password.');
                    exit;
                }
            }
        } else {
            if (!$staff) {
                $message = 'Invalid token for staff account.';
            } else {
                $message = 'Token expired. Please request a new password reset.';
            }
        }
    } else {
        $message = 'Invalid token.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – Staff Portal</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="staff-page">

    <div class="auth-container">
        <div class="auth-sidebar" style="background: linear-gradient(135deg, #aa9385 0%, #968073 50%, #6b5b52 100%);">
            <div>
                <h2>Staff Portal</h2>
                <div class="logo-container">
                    <img src="../images/16.png" class="sidebar-logo" alt="Lumière Logo">
                </div>
                <p>Create a new password for your staff account.</p>
            </div>
            <div>
                <p>Lumière Beauty Salon</p>
            </div>
        </div>

        <div class="auth-main">
            <form class="auth-form staff-theme" action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
                <div class="form-header">
                    <h1 style="color: #968073;">Reset Password</h1>
                    <p>Staff Account</p>
                </div>

                <?php if ($message): ?>
                    <div class="message error" style="margin-bottom: 20px; padding: 12px 15px; border-radius: 8px; color: #d32f2f; background: #ffebee; border-left: 3px solid #d32f2f;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($show_form): ?>
                    <div class="form-group">
                        <div class="input-wrapper">
                            <img src="../images/75.png" class="input-icon" alt="Lock">
                            <input type="password" 
                                   class="form-control indent-icon" 
                                   id="password" 
                                   name="password" 
                                   placeholder="New Password" 
                                   required>
                            <img src="../images/74.png" class="password-toggle" onclick="togglePass('password')" alt="Show">
                        </div>
                        <div id="passwordHints" style="display: none; margin-top: 8px; padding: 12px; background: #f8f9fa; border-radius: 8px; font-size: 13px; color: #666;">
                            <div>Password must contain:</div>
                            <div id="length-check">✓ At least 8 characters</div>
                            <div id="upper-check">✓ One uppercase letter</div>
                            <div id="lower-check">✓ One lowercase letter</div>
                            <div id="number-check">✓ One number</div>
                            <div id="special-check">✓ One symbol (@$!%*?&_)</div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 8px;">
                        <div class="input-wrapper">
                            <img src="../images/75.png" class="input-icon" alt="Lock">
                            <input type="password" 
                                   class="form-control indent-icon" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Confirm Password" 
                                   required>
                            <img src="../images/74.png" class="password-toggle" onclick="togglePass('confirm_password')" alt="Show">
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" style="background: #968073;">Reset Password</button>
                <?php else: ?>
                    <div class="message error" style="padding: 12px 15px; border-radius: 8px; color: #d32f2f; background: #ffebee; border-left: 3px solid #d32f2f;">
                        <?php echo htmlspecialchars($message ?: 'Invalid or expired reset link.'); ?>
                    </div>
                    <div class="switch-form" style="margin-top: 20px;">
                        <a href="forgot-password.php" style="color: #968073;">Request New Reset Link</a>
                    </div>
                <?php endif; ?>

                <div class="switch-form">
                    <a href="login.php" style="color: #968073;">Back to Login</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePass(id) {
            const input = document.getElementById(id);
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }

        // Password strength checker
        const passwordInput = document.getElementById('password');
        const hintsDiv = document.getElementById('passwordHints');
        
        if (passwordInput && hintsDiv) {
            passwordInput.addEventListener('focus', function() {
                hintsDiv.style.display = 'block';
            });
            
            passwordInput.addEventListener('input', function() {
                const pwd = this.value;
                document.getElementById('length-check').style.color = pwd.length >= 8 ? '#28a745' : '#666';
                document.getElementById('upper-check').style.color = /[A-Z]/.test(pwd) ? '#28a745' : '#666';
                document.getElementById('lower-check').style.color = /[a-z]/.test(pwd) ? '#28a745' : '#666';
                document.getElementById('number-check').style.color = /\d/.test(pwd) ? '#28a745' : '#666';
                document.getElementById('special-check').style.color = /[@$!%*?&_]/.test(pwd) ? '#28a745' : '#666';
            });
        }
    </script>

</body>
</html>



