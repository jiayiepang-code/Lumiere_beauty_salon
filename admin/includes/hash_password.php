<?php
/**
 * Password Hashing Utility
 * Use this to generate secure password hashes for admin accounts
 */

// Check if password is provided
if (php_sapi_name() === 'cli') {
    // Command line usage
    if ($argc < 2) {
        echo "Usage: php hash_password.php <password>\n";
        echo "Example: php hash_password.php Admin@123\n";
        exit(1);
    }
    $password = $argv[1];
} else {
    // Web usage
    if (!isset($_GET['password'])) {
        echo "<h2>Password Hashing Utility</h2>";
        echo "<form method='get'>";
        echo "<label>Enter password to hash:</label><br>";
        echo "<input type='text' name='password' required><br><br>";
        echo "<button type='submit'>Generate Hash</button>";
        echo "</form>";
        exit;
    }
    $password = $_GET['password'];
}

// Validate password strength
$errors = [];

if (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters long";
}
if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = "Password must contain at least one uppercase letter";
}
if (!preg_match('/[0-9]/', $password)) {
    $errors[] = "Password must contain at least one number";
}
if (!preg_match('/[._\-?@#$%^]/', $password)) {
    $errors[] = "Password must contain at least one symbol (._-?@#$%^)";
}

if (!empty($errors)) {
    if (php_sapi_name() === 'cli') {
        echo "Password does not meet requirements:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    } else {
        echo "<h3 style='color:red;'>Password does not meet requirements:</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
        echo "<a href='?'>Try again</a>";
    }
    exit(1);
}

// Generate hash
$hash = password_hash($password, PASSWORD_BCRYPT);

if (php_sapi_name() === 'cli') {
    echo "Password: $password\n";
    echo "Hash: $hash\n";
    echo "\nUse this hash in your SQL INSERT statement:\n";
    echo "UPDATE Staff SET password = '$hash' WHERE staff_email = 'your_email@example.com';\n";
} else {
    echo "<h2>Password Hash Generated</h2>";
    echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
    echo "<p><strong>Hash:</strong></p>";
    echo "<textarea style='width:100%;height:100px;font-family:monospace;'>" . htmlspecialchars($hash) . "</textarea>";
    echo "<h3>SQL Update Statement:</h3>";
    echo "<textarea style='width:100%;height:80px;font-family:monospace;'>";
    echo "UPDATE Staff SET password = '" . $hash . "' WHERE staff_email = 'your_email@example.com';";
    echo "</textarea>";
    echo "<br><br><a href='?'>Hash another password</a>";
}
?>
