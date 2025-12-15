<?php
require_once 'config/database.php';

// Connect to DB using PDO (consistent with rest of codebase)
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// #region agent log
@file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'reset_password.php:11', 'message' => 'Database connection initialized', 'data' => ['method' => 'PDO'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
// #endregion

$message = '';
$show_form = false;
$token = isset($_GET['token']) ? $_GET['token'] : '';

// #region agent log
@file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'reset_password.php:18', 'message' => 'Token received', 'data' => ['token' => $token ? substr($token, 0, 8) . '...' : 'none'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
// #endregion

if ($token) {
    // Check if token exists using customer_email (not user_id)
    $stmt = $db->prepare('SELECT customer_email, expires_at FROM password_resets WHERE token = ?');
    $stmt->execute([$token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // #region agent log
    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'reset_password.php:25', 'message' => 'Token lookup result', 'data' => ['token_found' => $resetData ? 'yes' : 'no', 'customer_email' => $resetData['customer_email'] ?? 'none', 'expires_at' => $resetData['expires_at'] ?? 'none'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
    
    if ($resetData) {
        $customer_email = $resetData['customer_email'];
        $expires_at = $resetData['expires_at'];
        
        if ($expires_at && strtotime($expires_at) > time()) {
            $show_form = true;
            
            // #region agent log
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'reset_password.php:34', 'message' => 'Token is valid', 'data' => ['customer_email' => $customer_email, 'expires_at' => $expires_at], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'] ?? '';
                $confirm = $_POST['confirm_password'] ?? '';
                
                // #region agent log
                @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'reset_password.php:40', 'message' => 'Password reset form submitted', 'data' => ['password_length' => strlen($password), 'passwords_match' => $password === $confirm ? 'yes' : 'no'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'B']) . "\n", FILE_APPEND | LOCK_EX);
                // #endregion
                
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
                    
                    // Update customer password using customer_email (not id)
                    $stmt2 = $db->prepare('UPDATE customer SET password = ? WHERE customer_email = ?');
                    $stmt2->execute([$hash, $customer_email]);
                    
                    // #region agent log
                    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'reset_password.php:51', 'message' => 'Password updated', 'data' => ['customer_email' => $customer_email, 'update_success' => 'yes'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'B']) . "\n", FILE_APPEND | LOCK_EX);
                    // #endregion
                    
                    // Delete token
                    $stmt3 = $db->prepare('DELETE FROM password_resets WHERE token = ?');
                    $stmt3->execute([$token]);
                    
                    // #region agent log
                    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'reset_password.php:57', 'message' => 'Token deleted, redirecting to login', 'data' => [], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'B']) . "\n", FILE_APPEND | LOCK_EX);
                    // #endregion
                    
                    header('Location: login.php?message=Password reset successfully. Please login with your new password.');
                    exit;
                }
            }
        } else {
            $message = 'Token expired. Please request a new password reset.';
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
    <title>Reset Password – Lumière Beauty Salon</title>
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
                <h2>Create New Password</h2>
                <div class="logo-container">
                    <img src="images/16.png" class="sidebar-logo" alt="Lumière Logo">
                </div>
                <p>Enter your new password below to complete the reset process.</p>
            </div>
            <p>Lumière Beauty Salon</p>
        </div>

        <div class="auth-main">
            <form class="auth-form" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
                <div class="form-header">
                    <h1>Reset Password</h1>
                </div>

                <?php if ($message): ?>
                    <div class="error-message" style="display: block; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($show_form): ?>
                    <div class="form-group">
                        <div class="input-wrapper">
                            <img src="images/75.png" class="input-icon" alt="Password">
                            <input type="password" 
                                   class="form-control indent-icon" 
                                   id="password" 
                                   name="password" 
                                   placeholder="New Password" 
                                   required
                                   oninput="checkResetPasswordRules()" 
                                   onfocus="showResetPasswordHints()">
                            <img src="images/74.png" 
                                 class="password-toggle" 
                                 id="passwordToggle" 
                                 onclick="toggleResetPassword()"
                                 alt="Toggle password visibility">
                        </div>
                        
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                        
                        <div class="hints-popup" id="passwordHints">
                            <p>Password must contain:</p>
                            <ul>
                                <li class="rule-item" id="ruleLength">✔ At least 8 characters</li>
                                <li class="rule-item" id="ruleUpper">✔ One uppercase letter (A-Z)</li>
                                <li class="rule-item" id="ruleLower">✔ One lowercase letter (a-z)</li>
                                <li class="rule-item" id="ruleNumber">✔ One number (0-9)</li>
                                <li class="rule-item" id="ruleSpecial">✔ One symbol (!@#$%…)</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <div class="input-wrapper">
                            <img src="images/75.png" class="input-icon" alt="Confirm Password">
                            <input type="password" 
                                   class="form-control indent-icon" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Confirm New Password" 
                                   required>
                            <img src="images/74.png" 
                                 class="password-toggle" 
                                 id="confirmPasswordToggle" 
                                 onclick="toggleResetConfirmPassword()"
                                 alt="Toggle password visibility">
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">Reset Password</button>
                <?php endif; ?>

                <div class="switch-form">
                    <a href="login.php">Back to Login</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Helper to switch type and icon
        function toggleInputType(input, icon) {
            if (!input) return;
            
            if (input.type === "password") {
                input.type = "text";
                if (icon) icon.src = "images/73.png"; // Open eye icon
            } else {
                input.type = "password";
                if (icon) icon.src = "images/74.png"; // Closed eye icon
            }
        }

        // Password toggle for reset page
        function toggleResetPassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('passwordToggle');
            toggleInputType(input, icon);
        }

        // Confirm password toggle for reset page
        function toggleResetConfirmPassword() {
            const input = document.getElementById('confirm_password');
            const icon = document.getElementById('confirmPasswordToggle');
            toggleInputType(input, icon);
        }

        // Show password hints popup
        function showResetPasswordHints() {
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

        // Password strength checker for reset page
        function checkResetPasswordRules() {
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