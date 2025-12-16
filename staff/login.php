<?php
// Prevent stray output
ob_start();
// Use a distinct session name for staff to allow parallel logins
session_name('staff_session');
session_start();
// PATH FIX: Point directly to config folder (No dots needed)
require_once '../config/database.php';

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
        $phoneLegacy = preg_replace('/\D+/', '', $phoneInput); // to allow older stored values
        $password = $_POST['password'] ?? '';

        if (empty($phoneInput) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter phone and password.']);
            exit;
        }

        // Query Staff table for staff role (not admin)
        $query = "SELECT staff_email, phone, password, first_name, last_name, role, is_active 
                  FROM Staff 
                  WHERE (phone = :p1 OR phone = :p2) AND role = 'staff' LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':p1', $phoneNormalized);
        $stmt->bindParam(':p2', $phoneLegacy);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $row['password'])) {
                // Check if account is active
                if ($row['is_active'] != 1) {
                    echo json_encode(['success' => false, 'error' => 'Account is deactivated. Please contact support.']);
                    exit;
                }

                // Build display values
                $firstNameVal = $row['first_name'] ?? '';
                $lastNameVal = $row['last_name'] ?? '';
                $fullName = trim($firstNameVal . ' ' . $lastNameVal);
                $initials = strtoupper(substr($firstNameVal, 0, 2) ?: substr($fullName, 0, 2));

                // Set session variables
                $_SESSION['staff_logged_in'] = true;
                // Set staff_id to staff_email to match API expectations
                $_SESSION['staff_id'] = $row['staff_email'];
                $_SESSION['staff_email'] = $row['staff_email'];
                $_SESSION['staff_phone'] = $phoneNormalized;
                $_SESSION['staff_name'] = $fullName;
                $_SESSION['staff_first_name'] = $firstNameVal;
                $_SESSION['staff_last_name'] = $lastNameVal;
                $_SESSION['staff_initials'] = $initials;
                $_SESSION['staff_role'] = $row['role'];
                $_SESSION['role'] = 'Staff';
                $_SESSION['login_time'] = time();

                // Regenerate session ID for security
                session_regenerate_id(true);

                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Incorrect password.']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Phone number not found or not authorized for staff access.']);
        }
    } catch (Exception $e) {
        error_log('Staff login error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'System error: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal - Lumière</title>
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
            <p>Access your schedule and client list.</p>
        </div>
        <div>
            <p>Lumière Beauty Salon</p>
        </div>
    </div>

    <div class="auth-main">
        <form class="auth-form staff-theme" id="staffForm" onsubmit="event.preventDefault();">
            <div id="error-alert" style="display:none;background:#fee;color:#c33;padding:12px;border-radius:8px;margin-bottom:15px;border:1px solid #fcc;font-size:14px;"></div>
            
            <div class="form-header">
                <h1 style="color: #968073;">Staff Login</h1>
                <p>Authorized Personnel Only</p>
            </div>

            <div class="form-group">
                <div class="phone-wrapper">
                    <span class="input-prefix">+60</span>
                    <input type="tel"
                           class="form-control phone-input"
                           id="staffId"
                           placeholder="12 345 6789"
                           maxlength="13"
                           oninput="formatPhoneNumber(this)"
                           required>
                </div>
            </div>

            <div class="form-group">
                <div class="input-wrapper">
                    <img src="../images/75.png" class="input-icon" alt="Lock">
                    <input type="password" class="form-control indent-icon" id="staffPass" placeholder="Password" required>
                    <img src="../images/74.png" class="password-toggle" onclick="togglePass('staffPass')" alt="Show">
                </div>
            </div>

            <button type="button" class="submit-btn" style="background: #968073;" onclick="validateStaffLogin()">STAFF LOGIN</button>
            
            <div class="switch-form">
                <a href="../login.php" style="color: #968073;">Back to Customer Site</a>
            </div>

        </form>
    </div>
</div>

<!-- Load staff-specific login logic (defines validateStaffLogin, helpers) -->
<script src="./login.js"></script>
</body>
</html>

