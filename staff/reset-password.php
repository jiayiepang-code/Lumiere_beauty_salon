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
                                   required
                                   oninput="checkPasswordStrength()"
                                   onfocus="showPasswordHints()">
                            <img src="../images/74.png" class="password-toggle" onclick="togglePass('password')" alt="Show">
                        </div>
                        
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                        
                        <div class="hints-popup" id="passwordHints" style="display: none;">
                            <p>Password must contain:</p>
                            <ul>
                                <li class="rule-item" id="ruleLength">✔ At least 8 characters</li>
                                <li class="rule-item" id="ruleUpper">✔ One uppercase letter (A-Z)</li>
                                <li class="rule-item" id="ruleLower">✔ One lowercase letter (a-z)</li>
                                <li class="rule-item" id="ruleNumber">✔ One number (0-9)</li>
                                <li class="rule-item" id="ruleSpecial">✔ One symbol (@$!%*?&_)</li>
                            </ul>
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

        // Show password hints popup
        function showPasswordHints() {
            const hints = document.getElementById('passwordHints');
            if (hints) hints.style.display = 'block';
        }

        // Hide hints when clicking outside
        document.addEventListener('click', function(e) {
            const passwordInput = document.getElementById('password');
            const hints = document.getElementById('passwordHints');
            if (passwordInput && hints && !passwordInput.contains(e.target) && !hints.contains(e.target)) {
                hints.style.display = 'none';
            }
        });

        // Password strength checker with progress bar
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');
            const strengthContainer = document.querySelector('.password-strength');
            const submitBtn = document.querySelector('.submit-btn');

            if (strengthContainer) strengthContainer.style.display = "block";
            if (text) text.style.display = "block";

            const ruleLength = document.getElementById("ruleLength");
            const ruleUpper = document.getElementById("ruleUpper");
            const ruleLower = document.getElementById("ruleLower");
            const ruleNumber = document.getElementById("ruleNumber");
            const ruleSpecial = document.getElementById("ruleSpecial");

            let hasLength = password.length >= 8;
            let hasUpper = /[A-Z]/.test(password);
            let hasLower = /[a-z]/.test(password);
            let hasNumber = /\d/.test(password);
            let hasSpecial = /[@$!%*?&_]/.test(password);

            if(ruleLength) ruleLength.classList.toggle("valid", hasLength);
            if(ruleUpper) ruleUpper.classList.toggle("valid", hasUpper);
            if(ruleLower) ruleLower.classList.toggle("valid", hasLower);
            if(ruleNumber) ruleNumber.classList.toggle("valid", hasNumber);
            if(ruleSpecial) ruleSpecial.classList.toggle("valid", hasSpecial);

            let validCount = hasLength + hasUpper + hasLower + hasNumber + hasSpecial;
            let isPasswordStrong = validCount === 5;

            bar.className = 'password-strength-bar';

            if (validCount <= 1) {
                bar.style.width = '25%';
                bar.classList.add('strength-weak');
                text.innerText = "Weak";
                text.style.color = "#d9534f";
            } else if (validCount === 2) {
                bar.style.width = '50%';
                bar.classList.add('strength-fair');
                text.innerText = "Fair";
                text.style.color = "#f0ad4e";
            } else if (validCount === 3) {
                bar.style.width = '75%';
                bar.classList.add('strength-good');
                text.innerText = "Good";
                text.style.color = "#5bc0de";
            } else if (validCount === 4) {
                bar.style.width = '90%';
                bar.classList.add('strength-good');
                text.innerText = "Good";
                text.style.color = "#5bc0de";
            } else if (validCount === 5) {
                bar.style.width = '100%';
                bar.classList.add('strength-strong');
                text.innerText = "Strong";
                text.style.color = "#5cb85c";
            }

            // Enable/disable submit button based on password strength
            if (submitBtn) {
                if (isPasswordStrong) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                } else {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                }
            }
        }
    </script>

</body>
</html>













