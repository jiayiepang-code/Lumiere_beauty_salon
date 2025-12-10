<?php
require_once 'config/config.php';

$conn = getDBConnection();

echo "<h2>Current Admin Record:</h2>";
$result = $conn->query("SELECT staff_email, phone, password, first_name, last_name, role, is_active FROM Staff WHERE role='admin'");

if ($row = $result->fetch_assoc()) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Email</td><td>{$row['staff_email']}</td></tr>";
    echo "<tr><td>Phone</td><td>{$row['phone']}</td></tr>";
    echo "<tr><td>Password Hash</td><td>{$row['password']}</td></tr>";
    echo "<tr><td>First Name</td><td>{$row['first_name']}</td></tr>";
    echo "<tr><td>Last Name</td><td>{$row['last_name']}</td></tr>";
    echo "<tr><td>Role</td><td>{$row['role']}</td></tr>";
    echo "<tr><td>Active</td><td>{$row['is_active']}</td></tr>";
    echo "</table>";
    
    echo "<hr><h2>Password Verification Test:</h2>";
    
    $test_password = 'Admin@123';
    $stored_hash = $row['password'];
    
    echo "<p>Testing password: <strong>$test_password</strong></p>";
    echo "<p>Stored hash: <strong>$stored_hash</strong></p>";
    
    if (password_verify($test_password, $stored_hash)) {
        echo "<p style='color:green;font-weight:bold;'>✓ Password MATCHES!</p>";
    } else {
        echo "<p style='color:red;font-weight:bold;'>✗ Password DOES NOT MATCH!</p>";
        
        // Generate new hash
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "<p>New hash for 'Admin@123': <code>$new_hash</code></p>";
        
        // Update with new hash
        $update = $conn->prepare("UPDATE Staff SET password = ? WHERE role = 'admin' AND staff_email = 'admin@lumiere.com'");
        $update->bind_param("s", $new_hash);
        if ($update->execute()) {
            echo "<p style='color:green;'>✓ Password hash updated in database!</p>";
        }
        $update->close();
    }
    
    echo "<hr><h2>Phone Normalization Test:</h2>";
    require_once 'config/utils.php';
    
    $test_inputs = ['123456789', '0123456789', '60123456789', '12 345 6789'];
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Input</th><th>Normalized</th><th>Matches DB?</th></tr>";
    
    foreach ($test_inputs as $input) {
        $normalized = sanitizePhone($input);
        $matches = ($normalized === $row['phone']) ? '✓ YES' : '✗ NO';
        $color = ($normalized === $row['phone']) ? 'green' : 'red';
        echo "<tr><td>$input</td><td>$normalized</td><td style='color:$color;font-weight:bold;'>$matches</td></tr>";
    }
    echo "</table>";
    
} else {
    echo "<p style='color:red;'>No admin record found!</p>";
}

$conn->close();
?>
