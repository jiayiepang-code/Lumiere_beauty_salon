<?php
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