<?php
/**
 * Admin Login Testing Script
 * File: admin/test_admin_login.php
 * 
 * Use this to verify your admin login setup
 * Access: http://localhost/lumiere/admin/test_admin_login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/db_connect.php';
require_once '../config/utils.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #a26e60;
            padding-bottom: 10px;
        }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #a26e60;
            color: white;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #a26e60;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        .btn:hover {
            background: #8a5d52;
        }
    </style>
</head>
<body>
    <h1>🔐 Admin Login System Test</h1>
    
    <?php
    try {
        $conn = getDBConnection();
        
        // TEST 1: Database Connection
        echo '<div class="test-section">';
        echo '<h2>Test 1: Database Connection</h2>';
        if ($conn) {
            echo '<p class="success">✅ Database connected successfully!</p>';
            echo '<p>Server: <code>' . DB_SERVER . '</code></p>';
            echo '<p>Database: <code>' . DB_NAME . '</code></p>';
        } else {
            echo '<p class="error">❌ Database connection failed!</p>';
        }
        echo '</div>';
        
        // TEST 2: Admin Accounts
        echo '<div class="test-section">';
        echo '<h2>Test 2: Admin Accounts in Database</h2>';
        $result = $conn->query("SELECT staff_email, phone, first_name, last_name, role, is_active FROM Staff WHERE role = 'admin'");
        
        if ($result->num_rows > 0) {
            echo '<p class="success">✅ Found ' . $result->num_rows . ' admin account(s)</p>';
            echo '<table>';
            echo '<tr><th>Email</th><th>Phone</th><th>Name</th><th>Active</th></tr>';
            
            while ($row = $result->fetch_assoc()) {
                $active_status = $row['is_active'] == 1 ? '<span class="success">✓ Yes</span>' : '<span class="error">✗ No</span>';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['staff_email']) . '</td>';
                echo '<td><code>' . htmlspecialchars($row['phone']) . '</code></td>';
                echo '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
                echo '<td>' . $active_status . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p class="error">❌ No admin accounts found!</p>';
            echo '<p>You need to create an admin account first.</p>';
        }
        echo '</div>';
        
        // TEST 3: Phone Format Check
        echo '<div class="test-section">';
        echo '<h2>Test 3: Phone Number Format Check</h2>';
        $result = $conn->query("SELECT staff_email, phone FROM Staff WHERE role = 'admin'");
        
        $format_ok = true;
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $phone = $row['phone'];
                $clean = sanitizePhone($phone);
                
                if ($phone === $clean) {
                    echo '<p class="success">✅ Correct format: <code>' . $phone . '</code> (' . $row['staff_email'] . ')</p>';
                } else {
                    echo '<p class="error">❌ Wrong format: <code>' . $phone . '</code> (' . $row['staff_email'] . ')</p>';
                    echo '<p>&nbsp;&nbsp;&nbsp;Should be: <code>' . $clean . '</code></p>';
                    $format_ok = false;
                }
            }
            
            if (!$format_ok) {
                echo '<p class="warning">⚠️ Phone numbers need to be fixed!</p>';
                echo '<a href="/lumiere/fix_phone_numbers.php" class="btn">Run Phone Fixer Script</a>';
            }
        }
        echo '</div>';
        
        // TEST 4: Password Hash Check
        echo '<div class="test-section">';
        echo '<h2>Test 4: Password Hash Check</h2>';
        $result = $conn->query("SELECT staff_email, password FROM Staff WHERE role = 'admin' LIMIT 1");
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $password_hash = $row['password'];
            
            // Check if password starts with $2y$ (bcrypt hash)
            if (substr($password_hash, 0, 4) === '$2y$') {
                echo '<p class="success">✅ Password is properly hashed (bcrypt)</p>';
                echo '<p>Hash sample: <code>' . substr($password_hash, 0, 30) . '...</code></p>';
            } else {
                echo '<p class="error">❌ Password is NOT hashed!</p>';
                echo '<p>Current value: <code>' . htmlspecialchars($password_hash) . '</code></p>';
                echo '<p class="warning">⚠️ This is a security risk! Passwords must be hashed.</p>';
            }
        }
        echo '</div>';
        
        // TEST 5: API Endpoint Check
        echo '<div class="test-section">';
        echo '<h2>Test 5: API Endpoint Check</h2>';
        
        $api_path = '../api/admin/auth/login.php';
        if (file_exists($api_path)) {
            echo '<p class="success">✅ Login API file exists</p>';
            echo '<p>Path: <code>/api/admin/auth/login.php</code></p>';
        } else {
            echo '<p class="error">❌ Login API file not found!</p>';
            echo '<p>Expected path: <code>' . $api_path . '</code></p>';
        }
        echo '</div>';
        
        // TEST 6: Utility Functions
        echo '<div class="test-section">';
        echo '<h2>Test 6: Utility Functions Test</h2>';
        
        // Test phone sanitization
        $test_phones = [
            '12 345 6789' => '+60123456789',
            '012-345-6789' => '+60123456789',
            '+60 12 3456 789' => '+60123456789',
            '0123456789' => '+60123456789'
        ];
        
        echo '<h3>Phone Sanitization:</h3>';
        echo '<table>';
        echo '<tr><th>Input</th><th>Output</th><th>Status</th></tr>';
        
        foreach ($test_phones as $input => $expected) {
            $output = sanitizePhone($input);
            $status = ($output === $expected) ? '<span class="success">✅ Pass</span>' : '<span class="error">❌ Fail</span>';
            echo '<tr>';
            echo '<td><code>' . $input . '</code></td>';
            echo '<td><code>' . $output . '</code></td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // Test password hashing
        echo '<h3>Password Hashing:</h3>';
        $test_password = 'TestPass123';
        $hash = hashPassword($test_password);
        $verify = verifyPassword($test_password, $hash);
        
        if ($verify) {
            echo '<p class="success">✅ Password hashing works correctly</p>';
        } else {
            echo '<p class="error">❌ Password hashing failed</p>';
        }
        echo '</div>';
        
        // TEST 7: File Structure
        echo '<div class="test-section">';
        echo '<h2>Test 7: File Structure Check</h2>';
        
        $required_files = [
            '../config/config.php' => 'Database config',
            '../config/utils.php' => 'Utility functions',
            'login.html' => 'Login page',
            'login.js' => 'Login JavaScript',
            '../api/admin/auth/login.php' => 'Login API',
            'includes/auth_check.php' => 'Auth checker',
            'index.php' => 'Admin dashboard'
        ];
        
        echo '<table>';
        echo '<tr><th>File</th><th>Description</th><th>Status</th></tr>';
        
        foreach ($required_files as $file => $desc) {
            $exists = file_exists($file);
            $status = $exists ? '<span class="success">✅ Exists</span>' : '<span class="error">❌ Missing</span>';
            echo '<tr>';
            echo '<td><code>' . $file . '</code></td>';
            echo '<td>' . $desc . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        $conn->close();
        
    } catch (Exception $e) {
        echo '<div class="test-section">';
        echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
        echo '</div>';
    }
    ?>
    
    <div class="test-section">
        <h2>📝 Next Steps</h2>
        <ol>
            <li>If phone numbers are wrong format, run: <a href="/lumiere/fix_phone_numbers.php" class="btn">Fix Phone Numbers</a></li>
            <li>Make sure all files exist (check Test 7)</li>
            <li>Try logging in: <a href="login.html" class="btn">Go to Login Page</a></li>
            <li>Check browser console (F12) for JavaScript errors</li>
        </ol>
    </div>
    
    <p style="text-align: center; color: #999; margin-top: 40px;">
        <small>Delete this test file after verification for security</small>
    </p>
</body>
</html>