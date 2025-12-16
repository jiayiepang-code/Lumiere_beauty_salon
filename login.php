<?php
// Prevent stray output
ob_start();
session_start();
// PATH FIX: Point directly to config folder (No dots needed)
require_once 'config/database.php';

// Redirect target after successful login (default to customer home)
$redirect = $_GET['redirect'] ?? 'user/index.php';

// Helper: normalize phone to +60XXXXXXXXX
function normalizePhone($input) {
    $digits = preg_replace('/\D+/', '', $input ?? '');
    if (strpos($digits, '60') === 0) {
        $digits = substr($digits, 2);
    }
    $digits = ltrim($digits, '0');
    $digits = substr($digits, 0, 11);
    return '+60' . $digits;
}

// LOGIC: HANDLE LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear buffer and set JSON header to avoid invalid responses
    ob_clean();
    header('Content-Type: application/json');

    try {
        $database = new Database();
        $db = $database->getConnection();
        if (!$db) {
            throw new Exception('Database connection failed');
        }

        $phoneInput = $_POST['phone'] ?? '';
        $phoneNormalized = normalizePhone($phoneInput);
        $phoneLegacy     = preg_replace('/\D+/', '', $phoneInput); // to allow older stored values
        $password = $_POST['password'] ?? '';

        if (empty($phoneInput) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter phone and password.']);
            exit;
        }

        // No customer_id column in DB; use phone as unique key
        $query = "SELECT phone, customer_email, first_name, last_name, password 
                  FROM Customer WHERE phone IN (:p1, :p2) LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':p1', $phoneNormalized);
        $stmt->bindParam(':p2', $phoneLegacy);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['password'])) {
                // Build display values
                $firstNameVal = $row['first_name'] ?? '';
                $lastNameVal  = $row['last_name'] ?? '';
                $fullName = trim($firstNameVal . ' ' . $lastNameVal);
                // Default avatar: first two chars of first name
                $initials = strtoupper(substr($firstNameVal, 0, 2) ?: substr($fullName, 0, 2));

                // Persist session for profile/header
                $_SESSION['customer_id']    = $phoneNormalized; // use phone as ID surrogate
                $_SESSION['customer_phone'] = $phoneNormalized;
                $_SESSION['phone']          = $phoneNormalized; // header.php expects this key
                $_SESSION['customer_email'] = $row['customer_email']; // Store email for bookings
                $_SESSION['user_email']     = $row['customer_email']; // Backward compatibility
                $_SESSION['first_name']     = $firstNameVal;
                $_SESSION['last_name']      = $lastNameVal;
                $_SESSION['user_name']      = $fullName;
                $_SESSION['initials']       = $initials;
                $_SESSION['role']           = 'Customer';

                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Incorrect password.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Phone number not found.']);
        }
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        // Surface a clearer message for local debugging. Change to generic message for production.
        echo json_encode(['status' => 'error', 'message' => 'System error: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login – Lumière Beauty Salon</title>
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
            <h2>Welcome Back</h2>
            <div class="logo-container">
                <img src="images/16.png" class="sidebar-logo" alt="Lumière Logo">
            </div>
            <p>Access your account and manage your bookings.</p>
        </div>
        <p>Lumière Beauty Salon</p>
    </div>

    <div class="auth-main">

        <form class="auth-form login-form" id="loginForm">

            <div class="form-header"><h1>Login</h1></div>

            <div class="form-group">
                <div class="phone-wrapper">
                    <span class="input-prefix">+60</span>
                    <input type="tel"
                           class="form-control phone-input"
                           id="loginPhone"
                           placeholder="12 345 6789"
                           maxlength="13"
                           oninput="formatPhoneNumber(this)">
                </div>
            </div>

            <div class="form-group">
                <div class="input-wrapper">
                    <img src="images/75.png" class="input-icon">
                    <input type="password" class="form-control indent-icon"
                           id="loginPassword" placeholder="Password">
                    <img src="images/74.png" class="password-toggle"
                         id="loginPasswordToggle" onclick="toggleLoginPassword()">
                </div>
            </div>

            <div class="switch-form" style="text-align:right; margin-top: 5px;">
                <a href="forgot_password.php">Forgot password?</a>
            </div>

            <button type="button" class="submit-btn" onclick="validateCustomerLogin()">LOGIN</button>

            <div class="switch-form">
                Don’t have an account?
                <a href="register.php?redirect=<?= htmlspecialchars($redirect) ?>">Register here</a>
            </div>

            <input type="hidden" id="redirectUrl" value="<?= htmlspecialchars($redirect) ?>">
        </form>

    </div> </div> </body>
</html>