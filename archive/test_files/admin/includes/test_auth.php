<?php
/**
 * Authentication System Test Script
 * Tests various authentication functions
 */

require_once '../../php/connection.php';

echo "<h2>Authentication System Test</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
</style>";

// Test 1: Check if tables exist
echo "<div class='test-section'>";
echo "<h3>Test 1: Database Tables</h3>";

$tables = ['Login_Attempts', 'Admin_Login_Log', 'Staff'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p class='success'>✓ Table '$table' exists</p>";
    } else {
        echo "<p class='error'>✗ Table '$table' does not exist</p>";
    }
}
echo "</div>";

// Test 2: Check if default admin exists
echo "<div class='test-section'>";
echo "<h3>Test 2: Default Admin Account</h3>";

$result = $conn->query("SELECT staff_email, phone, first_name, last_name, role, is_active 
                        FROM Staff 
                        WHERE role = 'admin' 
                        LIMIT 1");

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "<p class='success'>✓ Admin account found</p>";
    echo "<ul>";
    echo "<li>Email: " . htmlspecialchars($admin['staff_email']) . "</li>";
    echo "<li>Phone: " . htmlspecialchars($admin['phone']) . "</li>";
    echo "<li>Name: " . htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) . "</li>";
    echo "<li>Role: " . htmlspecialchars($admin['role']) . "</li>";
    echo "<li>Active: " . ($admin['is_active'] ? 'Yes' : 'No') . "</li>";
    echo "</ul>";
} else {
    echo "<p class='error'>✗ No admin account found</p>";
    echo "<p class='info'>Run setup_auth.php to create default admin account</p>";
}
echo "</div>";

// Test 3: Test password verification
echo "<div class='test-section'>";
echo "<h3>Test 3: Password Hashing</h3>";

$test_password = "Admin@123";
$test_hash = password_hash($test_password, PASSWORD_BCRYPT);

echo "<p class='info'>Test password: $test_password</p>";
echo "<p class='info'>Generated hash: $test_hash</p>";

if (password_verify($test_password, $test_hash)) {
    echo "<p class='success'>✓ Password verification works correctly</p>";
} else {
    echo "<p class='error'>✗ Password verification failed</p>";
}
echo "</div>";

// Test 4: Test phone number validation
echo "<div class='test-section'>";
echo "<h3>Test 4: Phone Number Validation</h3>";

function testPhoneValidation($phone) {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    $pattern = '/^(01[0-9]{7,8}|011[0-9]{8}|60[0-9]{8,9})$/';
    return preg_match($pattern, $cleaned);
}

$test_phones = [
    '0123456789' => true,
    '60123456789' => true,
    '0111234567' => false,
    '01112345678' => true,
    '12345' => false,
    '60987654321' => true
];

foreach ($test_phones as $phone => $expected) {
    $result = testPhoneValidation($phone);
    $status = ($result === $expected) ? 'success' : 'error';
    $icon = ($result === $expected) ? '✓' : '✗';
    echo "<p class='$status'>$icon Phone '$phone': " . ($result ? 'Valid' : 'Invalid') . 
         " (Expected: " . ($expected ? 'Valid' : 'Invalid') . ")</p>";
}
echo "</div>";

// Test 5: Check file permissions
echo "<div class='test-section'>";
echo "<h3>Test 5: File Structure</h3>";

$required_files = [
    '../../api/admin/auth/login.php',
    '../../api/admin/auth/logout.php',
    '../includes/auth_check.php',
    '../../api/admin/includes/csrf_validation.php',
    '../login.html',
    '../login.js',
    '../index.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p class='success'>✓ File exists: " . basename($file) . "</p>";
    } else {
        echo "<p class='error'>✗ File missing: $file</p>";
    }
}
echo "</div>";

// Test 6: Session configuration
echo "<div class='test-section'>";
echo "<h3>Test 6: PHP Session Configuration</h3>";

echo "<ul>";
echo "<li>Session save path: " . session_save_path() . "</li>";
echo "<li>Session name: " . session_name() . "</li>";
echo "<li>Session cookie lifetime: " . ini_get('session.cookie_lifetime') . "</li>";
echo "<li>Session gc maxlifetime: " . ini_get('session.gc_maxlifetime') . "</li>";
echo "</ul>";

if (is_writable(session_save_path())) {
    echo "<p class='success'>✓ Session save path is writable</p>";
} else {
    echo "<p class='error'>✗ Session save path is not writable</p>";
}
echo "</div>";

// Summary
echo "<div class='test-section' style='background:#f0f0f0;'>";
echo "<h3>Test Summary</h3>";
echo "<p>All tests completed. Review the results above.</p>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>If tables don't exist, run <a href='setup_auth.php'>setup_auth.php</a></li>";
echo "<li>Test login at <a href='../login.html'>admin/login.html</a></li>";
echo "<li>Use credentials: Phone: 12 345 6789, Password: Admin@123</li>";
echo "</ol>";
echo "</div>";

$conn->close();
?>
