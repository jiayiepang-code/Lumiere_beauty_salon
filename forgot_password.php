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

require_once 'includes/header.php';
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

<div class="container mt-4">

<?php if ($step === 1): ?>
    <h3>Find Your Account</h3>
    <p class="text-muted">Enter your Email or Phone Number to begin.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">

        <label>Email or Phone:</label>
        <input type="text" name="login_id" class="form-control mb-3" required>

        <label>Enter CAPTCHA:</label>
        <div class="d-flex mb-2">
            <div class="p-2 bg-light border fw-bold" style="letter-spacing: 3px;">
                <?php echo $_SESSION['fp_captcha']; ?>
            </div>
        </div>

        <input type="text" name="captcha" class="form-control mb-3" required>

        <button name="find_account" class="btn btn-primary w-100">Next</button>
    </form>

<?php elseif ($step === 2): ?>
    <h3>Verify Phone Number</h3>
    <p>Enter the <b>last 4 digits</b> of your registered phone number.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="last4" maxlength="4" class="form-control mb-3" required>
        <button name="verify_phone" class="btn btn-primary w-100">Verify</button>
    </form>

<?php elseif ($step === 3): ?>
    <h3>Reset Your Password</h3>
    <p class="text-muted">Create a new password for your account.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>New Password:</label>
        <input type="password" name="new_password" class="form-control mb-3" required>

        <label>Confirm Password:</label>
        <input type="password" name="confirm_password" class="form-control mb-3" required>

        <button name="reset_password" class="btn btn-success w-100">Reset Password</button>
    </form>

<?php elseif ($step === 4): ?>
    <div class="alert alert-success">
        <?php echo $success; ?>
    </div>
    <a href="login.php" class="btn btn-primary">Back to Login</a>
<?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>
