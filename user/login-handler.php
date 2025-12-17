<?php
// User Login Handler
// File: user/login-handler.php

// Prevent stray output
ob_start();
session_start();

// PATH FIX: Point directly to config folder
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

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Clear buffer and set JSON header
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

    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'user/login-handler.php:45','message'=>'Login attempt','data'=>['phone_input'=>$phoneInput,'phone_normalized'=>$phoneNormalized,'phone_legacy'=>$phoneLegacy,'has_password'=>!empty($password)],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion

    if (empty($phoneInput) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please enter phone and password.']);
        exit;
    }

    // Query Customer table
    $query = "SELECT phone, customer_email, first_name, last_name, password 
              FROM Customer WHERE phone IN (:p1, :p2) LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':p1', $phoneNormalized);
    $stmt->bindParam(':p2', $phoneLegacy);
    $stmt->execute();

    // #region agent log
    file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'user/login-handler.php:60','message'=>'Customer query executed','data'=>['row_count'=>$stmt->rowCount(),'phone_normalized'=>$phoneNormalized,'phone_legacy'=>$phoneLegacy],'timestamp'=>time()*1000])."\n", FILE_APPEND);
    // #endregion

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // #region agent log
        file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'user/login-handler.php:65','message'=>'Customer found, verifying password','data'=>['customer_email'=>$row['customer_email']??'','stored_phone'=>$row['phone']??''],'timestamp'=>time()*1000])."\n", FILE_APPEND);
        // #endregion
        
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

            // #region agent log
            file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'user/login-handler.php:85','message'=>'Login SUCCESS','data'=>['customer_email'=>$row['customer_email'],'phone'=>$phoneNormalized],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion

            echo json_encode(['success' => true, 'message' => 'Login successful']);
        } else {
            // #region agent log
            file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'user/login-handler.php:90','message'=>'Password verification FAILED','data'=>[],'timestamp'=>time()*1000])."\n", FILE_APPEND);
            // #endregion
            echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
        }
    } else {
        // #region agent log
        file_put_contents('c:\xampp\htdocs\Lumiere-beauty-salon\.cursor\debug.log', json_encode(['sessionId'=>'debug-session','runId'=>'pre-fix','hypothesisId'=>'A','location'=>'user/login-handler.php:96','message'=>'Customer NOT FOUND','data'=>['phone_normalized'=>$phoneNormalized,'phone_legacy'=>$phoneLegacy],'timestamp'=>time()*1000])."\n", FILE_APPEND);
        // #endregion
        echo json_encode(['success' => false, 'message' => 'Phone number not found.']);
    }
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error. Please try again.']);
}
exit;
?>

