<?php
/**
 * Authentication System Setup Script
 * Run this once to set up the authentication tables and default admin account
 */

require_once '../../php/connection.php';

echo "<h2>Setting up Admin Authentication System...</h2>";

// Read and execute SQL file
$sql_file = __DIR__ . '/setup_auth_tables.sql';
$sql_content = file_get_contents($sql_file);

// Split SQL statements
$statements = array_filter(
    array_map('trim', explode(';', $sql_content)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', $stmt);
    }
);

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (empty(trim($statement))) continue;
    
    try {
        if ($conn->query($statement)) {
            $success_count++;
            echo "<p style='color:green;'>✓ Executed successfully</p>";
        } else {
            $error_count++;
            echo "<p style='color:red;'>✗ Error: " . $conn->error . "</p>";
        }
    } catch (Exception $e) {
        $error_count++;
        echo "<p style='color:red;'>✗ Exception: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<h3>Setup Summary:</h3>";
echo "<p>Successful: $success_count</p>";
echo "<p>Errors: $error_count</p>";

if ($error_count === 0) {
    echo "<p style='color:green;font-weight:bold;'>✓ Authentication system setup completed successfully!</p>";
    echo "<p>Default admin credentials:</p>";
    echo "<ul>";
    echo "<li>Phone: +60 12 345 6789</li>";
    echo "<li>Password: Admin@123</li>";
    echo "</ul>";
    echo "<p style='color:orange;'>⚠ Please change the default password after first login!</p>";
} else {
    echo "<p style='color:red;font-weight:bold;'>✗ Setup completed with errors. Please check the messages above.</p>";
}

$conn->close();
?>
