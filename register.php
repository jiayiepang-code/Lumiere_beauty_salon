<?php
// Start output buffering to prevent any accidental output
ob_start();
session_start();
// PATH FIX: Point directly to config folder
require_once 'config/database.php';

// CAPTCHA GENERATOR
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!isset($_SESSION['register_captcha'])) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $_SESSION['register_captcha'] = substr(str_shuffle($chars), 0, 5);
    }
}

// REGISTER LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear any output buffer and set JSON header
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            throw new Exception("Database connection failed");
        }
    } catch(Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed. Please try again.']);
        exit;
    }

    // Normalize phone to +60 format and clean inputs
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName  = trim($_POST['lastName'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $captcha   = $_POST['captcha'] ?? '';

    // Convert any user-entered value to a consistent +60XXXXXXXXX format
    $normalizePhone = function($input) {
        $digits = preg_replace('/\D+/', '', $input ?? '');
        // Drop leading country code if provided
        if (strpos($digits, '60') === 0) {
            $digits = substr($digits, 2);
        }
        // Drop leading zeroes then cap length
        $digits = ltrim($digits, '0');
        $digits = substr($digits, 0, 11); // Malaysia max 11 digits after country code
        return '+60' . $digits;
    };
    $phone = $normalizePhone($_POST['phone'] ?? '');

    if (empty($firstName) || empty($phone) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all fields.']);
        exit;
    }

    // Case-sensitive CAPTCHA validation - trim whitespace only
    $captcha = trim($captcha);
    $sessionCaptcha = isset($_SESSION['register_captcha']) ? trim($_SESSION['register_captcha']) : '';

    if (empty($sessionCaptcha) || $captcha !== $sessionCaptcha) {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect CAPTCHA. Please check the case of letters.']);
        exit;
    }

    // #region agent log
    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'register.php:70','message'=>'Checking for duplicates','data'=>['email'=>$email,'phone'=>$phone,'normalized_phone'=>$phone],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion

    // Check for duplicates - improved to show which field is duplicate
    $check = $db->prepare("SELECT customer_email, phone FROM Customer WHERE customer_email = ? OR phone = ?");
    $check->execute([$email, $phone]);
    $existing = $check->fetchAll(PDO::FETCH_ASSOC);
    
    // #region agent log
    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'register.php:75','message'=>'Duplicate check results','data'=>['row_count'=>count($existing),'existing_records'=>$existing],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion
    
    if (count($existing) > 0) {
        // Determine which field is duplicate
        $duplicateEmail = false;
        $duplicatePhone = false;
        foreach ($existing as $row) {
            if (strtolower($row['customer_email']) === strtolower($email)) {
                $duplicateEmail = true;
            }
            if ($row['phone'] === $phone) {
                $duplicatePhone = true;
            }
        }
        
        $message = 'Email or Phone already registered.';
        if ($duplicateEmail && $duplicatePhone) {
            $message = 'Both email and phone are already registered. Please login instead.';
        } elseif ($duplicateEmail) {
            $message = 'This email is already registered. Please login or use a different email.';
        } elseif ($duplicatePhone) {
            $message = 'This phone number is already registered. Please login or use a different phone number.';
        }
        
        // #region agent log
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'register.php:95','message'=>'DUPLICATE FOUND - Registration blocked','data'=>['duplicate_email'=>$duplicateEmail,'duplicate_phone'=>$duplicatePhone,'message'=>$message],'timestamp'=>time()*1000])."\n", FILE_APPEND);
        // #endregion
        
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }

    try {
        // #region agent log
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'B','location'=>'register.php:100','message'=>'Attempting to insert new customer','data'=>['first_name'=>$firstName,'last_name'=>$lastName,'phone'=>$phone,'email'=>$email],'timestamp'=>time()*1000])."\n", FILE_APPEND);
        // #endregion
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO Customer (first_name, last_name, phone, customer_email, password, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $db->prepare($sql);
        
        // Execute the insert with normalized phone
        $result = $stmt->execute([$firstName, $lastName, $phone, $email, $hash]);
        
        // #region agent log
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'B','location'=>'register.php:108','message'=>'INSERT executed','data'=>['result'=>$result,'row_count'=>$stmt->rowCount()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
        // #endregion
        
        if ($result) {
            // Clear the CAPTCHA session after successful registration
            unset($_SESSION['register_captcha']);
            // #region agent log
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'B','location'=>'register.php:113','message'=>'Registration SUCCESS','data'=>['email'=>$email,'phone'=>$phone],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            echo json_encode(['status' => 'success', 'message' => 'Registration successful!']);
        } else {
            $errorInfo = $stmt->errorInfo();
            // #region agent log
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'B','location'=>'register.php:118','message'=>'INSERT FAILED - result is false','data'=>['error_info'=>$errorInfo],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            error_log("Registration error: " . print_r($errorInfo, true));
            echo json_encode(['status' => 'error', 'message' => 'Failed to save registration data. Please try again.']);
        }
    } catch(PDOException $e) {
        // #region agent log
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'B','location'=>'register.php:125','message'=>'INSERT EXCEPTION','data'=>['error_message'=>$e->getMessage(),'error_code'=>$e->getCode()],'timestamp'=>time()*1000])."\n", FILE_APPEND);
        // #endregion
        error_log("Registration PDO error: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Data: firstName=$firstName, lastName=$lastName, phone=$phone, email=$email");
        // Don't expose database errors to users, log them instead
        echo json_encode(['status' => 'error', 'message' => 'Failed to save registration. Please try again.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/script.js" defer></script>
</head>
<body>
    <div class="floating-btn-group">
    <a href="staff/login.php" class="float-wrapper staff-btn-container" style="text-decoration:none;">
        <div class="icon-circle"><img src="images/79.png" alt="Staff"></div>
        <span class="float-text">Staff Login</span>
    </a>
    <a href="admin/login.php" class="float-wrapper admin-btn-container" style="text-decoration:none;">
        <div class="icon-circle"><img src="images/80.png" alt="Admin"></div>
        <span class="float-text">Admin Login</span>
    </a>
</div>

<div class="auth-container">

    <div class="auth-sidebar">
        <div>
            <h2>Join Us</h2>
            <div class="logo-container">
                <img src="images/16.png" class="sidebar-logo" alt="Lumière Logo">
            </div>
            <p>Create an account to book your favorite treatments.</p>
        </div>
        <p>Lumière Beauty Salon</p>
    </div>

    <div class="auth-main">

        <form class="auth-form" id="authForm" onsubmit="event.preventDefault();">

            <div class="form-header">
                <h1 id="formTitle">Register</h1>
                <p>Step <span id="stepCount">1</span> of 4</p>
            </div>

            <!-- STEPPER -->
            <div class="register-stepper">
                <div class="step step-active" id="circle-1"><span class="step-number">1</span></div>
                <div class="step" id="circle-2"><span class="step-number">2</span></div>
                <div class="step" id="circle-3"><span class="step-number">3</span></div>
                <div class="step" id="circle-4"><span class="step-number">4</span></div>
            </div>

            <div id="step-group-1" class="form-step active-step">
                <div class="name-row">
                    <div class="form-group half-width">
                        <input type="text" class="form-control" id="firstName" placeholder="First name" required>
                    </div>
                    <div class="form-group half-width">
                        <input type="text" class="form-control" id="lastName" placeholder="Last name" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="phone-wrapper">
                        <span class="input-prefix">+60</span>
                        <input type="tel" class="form-control phone-input" id="phone"
                               placeholder="12 345 6789" maxlength="13"
                               oninput="formatPhoneNumber(this)" required>
                    </div>
                </div>

                <div class="error-message" id="step1Error"></div>
                <button type="button" class="submit-btn" onclick="validateStep1()">Next Step</button>

                <div class="switch-form">
                    Already have an account?
                    <a href="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">Login</a>
                </div>
            </div>

            <div id="step-group-2" class="form-step">
                <div class="form-group">
                    <div class="input-wrapper">
                        <img src="images/72.png" class="input-icon">
                        <input type="email" class="form-control indent-icon" id="email" placeholder="Email address" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <img src="images/75.png" class="input-icon">
                        <input type="password" class="form-control indent-icon" id="password"
                               placeholder="Password" required
                               oninput="checkPasswordRules()" onfocus="showPasswordHints()">
                        <img src="images/74.png" class="password-toggle" id="passwordToggle" onclick="togglePassword()">
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

                <div class="form-group confirm-password-group">
                    <div class="input-wrapper">
                        <img src="images/75.png" class="input-icon">
                        <input type="password" class="form-control indent-icon" id="confirmPassword"
                               placeholder="Confirm password" required>
                        <img src="images/74.png" class="password-toggle" id="confirmPasswordToggle" onclick="toggleConfirmPassword()">
                    </div>
                </div>

                <div class="checkbox-container">
                    <div class="custom-checkbox" id="rememberMe" onclick="toggleCheckbox(this)"></div>
                    <label>Remember me</label>
                </div>

                <div class="error-message" id="step2Error"></div>
                <button type="button" class="submit-btn" onclick="validateStep2()">Next Step</button>

                <div class="switch-form">
                    <a href="#" onclick="goToStep(1)">Back</a>
                </div>
            </div>

            <div id="step-group-3" class="form-step">
                <div class="otp-container">
                    <p>Please enter the characters shown below to verify you're not a robot.</p>
                    <div class="otp-inputs captcha-wrapper">
                        <div id="registerCaptchaCode" class="captcha-box"
                             data-code="<?= htmlspecialchars($_SESSION['register_captcha']); ?>">
                            <?= htmlspecialchars($_SESSION['register_captcha']); ?>
                        </div>
                        <input type="text" maxlength="10" class="form-control"
                               id="registerCaptchaInput" placeholder="Enter CAPTCHA here">
                        <button type="button" class="captcha-refresh" onclick="refreshRegisterCaptcha(event)">
                            Refresh CAPTCHA
                        </button>
                    </div>
                </div>

                <div class="error-message" id="step3Error"></div>
                <button type="button" class="submit-btn" onclick="validateStep3()">Verify &amp; Register</button>

                <div class="switch-form"><a href="#" onclick="goToStep(2)">Back</a></div>
            </div>

            <div id="step-group-4" class="form-step success-view">
                <i class="fas fa-check-circle success-icon"></i>
                <h2>Registration Successful!</h2>
                <p>Your account has been created.</p>
                <button type="button" class="submit-btn" onclick="window.location.href='login.php'">Go to Login</button>
            </div>
        </form>
    <script>

    function refreshRegisterCaptcha(event) {
        if(event) event.preventDefault();
        // #region agent log
        fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'register.php:refreshRegisterCaptcha',message:'CAPTCHA refresh initiated',data:{currentUrl:window.location.href},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'A'})}).catch(()=>{});
        // #endregion agent log
        fetch('refresh_captcha.php')
            .then(res => {
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'register.php:refreshRegisterCaptcha',message:'CAPTCHA fetch response',data:{ok:res.ok,status:res.status,statusText:res.statusText},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'B'})}).catch(()=>{});
                // #endregion agent log
                if (!res.ok) {
                    throw new Error('Failed to refresh CAPTCHA: ' + res.statusText);
                }
                return res.text();
            })
            .then(code => {
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'register.php:refreshRegisterCaptcha',message:'CAPTCHA code received',data:{codeLength:code.length,codePreview:code.substring(0,5)},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'C'})}).catch(()=>{});
                // #endregion agent log
                const box = document.getElementById("registerCaptchaCode");
                if(box) {
                    box.textContent = code;
                    box.dataset.code = code;
                }
            })
            .catch(error => {
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/03464b7d-2340-40f5-be08-e3068c396ba3',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'register.php:refreshRegisterCaptcha',message:'CAPTCHA refresh error',data:{errorMessage:error.message,errorStack:error.stack},timestamp:Date.now(),sessionId:'debug-session',runId:'run1',hypothesisId:'D'})}).catch(()=>{});
                // #endregion agent log
                console.error('Error refreshing CAPTCHA:', error);
                alert('Failed to refresh CAPTCHA. Please try again.');
            });
    }
    </script>
</body>
</html>