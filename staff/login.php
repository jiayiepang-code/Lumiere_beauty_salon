<?php
<<<<<<< HEAD
// Prevent stray output
ob_start();
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
                    echo json_encode(['status' => 'error', 'message' => 'Account is deactivated. Please contact support.']);
                    exit;
                }

                // Build display values
                $firstNameVal = $row['first_name'] ?? '';
                $lastNameVal = $row['last_name'] ?? '';
                $fullName = trim($firstNameVal . ' ' . $lastNameVal);
                $initials = strtoupper(substr($firstNameVal, 0, 2) ?: substr($fullName, 0, 2));

                // Set session variables
                $_SESSION['staff_logged_in'] = true;
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

                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Incorrect password.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Phone number not found or not authorized for staff access.']);
        }
    } catch (Exception $e) {
        error_log('Staff login error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'System error: ' . $e->getMessage()]);
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

<script src="../js/script.js"></script>
</body>
</html>
=======
// --- BACKEND LOGIC ---
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($phone) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Please enter both phone and password']);
        exit;
    }

    // Normalize phone number: Remove spaces and ensure consistent format
    $phone = preg_replace('/\s+/', '', $phone); // Remove all spaces
    
    // If phone doesn't start with +60, try to add it
    if (!preg_match('/^\+60/', $phone)) {
        // Remove leading 0 if present
        $phone = ltrim($phone, '0');
        // Add +60 prefix
        $phone = '+60' . $phone;
    }

    try {
        // 1. SELECT USER
        // Try exact match first
        $stmt = $pdo->prepare("SELECT * FROM Staff WHERE phone = ?");
        $stmt->execute([$phone]);
        $staff = $stmt->fetch();
        
        // If not found, try alternative formats (without +60, or with different spacing)
        if (!$staff) {
            // Try without +60 prefix
            if (strpos($phone, '+60') === 0) {
                $phone_alt = substr($phone, 3); // Remove +60
                $stmt = $pdo->prepare("SELECT * FROM Staff WHERE phone = ?");
                $stmt->execute([$phone_alt]);
                $staff = $stmt->fetch();
            }
            
            // If still not found, try with +60 prefix added
            if (!$staff && strpos($phone, '+60') !== 0) {
                $phone_with_prefix = '+60' . $phone;
                $stmt = $pdo->prepare("SELECT * FROM Staff WHERE phone = ?");
                $stmt->execute([$phone_with_prefix]);
                $staff = $stmt->fetch();
                if ($staff) {
                    $phone = $phone_with_prefix; // Update phone for later use
                }
            }
        }

        if ($staff) {
            $db_password = $staff['password'];
            $login_valid = false;
            $needs_hashing = false;

            // 2. CHECK PASSWORD
            // Detect if password is hashed (starts with $2y$, $2a$, $2b$, etc.)
            $is_hashed = (substr($db_password, 0, 4) === '$2y$' || 
                         substr($db_password, 0, 4) === '$2a$' || 
                         substr($db_password, 0, 4) === '$2b$' ||
                         substr($db_password, 0, 1) === '$');
            
            if ($is_hashed) {
                // Check A: Password is HASHED (Like Admin user)
                if (password_verify($password, $db_password)) {
                    $login_valid = true;
                }
            } else {
                // Check B: Password is PLAIN TEXT (Like Chloe user)
                if ($db_password === $password) {
                    $login_valid = true;
                    $needs_hashing = true; // Mark this account for upgrade
                }
            }

            if ($login_valid) {
                // Check if account is active
                if ($staff['is_active'] == 0) {
                    echo json_encode(['success' => false, 'error' => 'Account is deactivated']);
                    exit;
                }

                // 3. AUTO-UPGRADE SECURITY
                // If the user had a plain text password, encrypt it now!
                if ($needs_hashing) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Update the database immediately
                    $update = $pdo->prepare("UPDATE Staff SET password = ? WHERE phone = ?");
                    $update->execute([$newHash, $phone]);
                }

                // 4. LOGIN SUCCESS
                $_SESSION['staff_id'] = $staff['staff_email']; // Using email as unique ID
                $_SESSION['staff_name'] = $staff['first_name'] . ' ' . $staff['last_name'];
                
                // Debug: Log session info (remove in production)
                error_log("Login Success - Setting session staff_id: " . $_SESSION['staff_id']);
                error_log("Login Success - Setting session staff_name: " . $_SESSION['staff_name']);
                error_log("Login Success - Staff email from DB: " . $staff['staff_email']);
                error_log("Login Success - Session ID: " . session_id());
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid password']);
            }
        } else {
            // Debug: Log what phone number was searched (remove this in production)
            error_log("Login attempt failed - Phone searched: " . $phone);
            echo json_encode(['success' => false, 'error' => 'Phone number not found. Please check your phone number format.']);
        }

    } catch(PDOException $e) {
        // Return actual DB error for debugging
        echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid Request Method']);
}
?>
>>>>>>> origin/staff_update_5.2
