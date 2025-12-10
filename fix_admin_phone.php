<?php
require_once 'config/config.php';

$conn = getDBConnection();

// Update admin phone to correct format: +60123456789 (no spaces)
$sql = "UPDATE Staff SET phone = '+60123456789' WHERE role = 'admin' AND staff_email = 'admin@lumiere.com'";

if ($conn->query($sql)) {
    echo "<h2 style='color:green;'>✓ Admin phone number updated!</h2>";
    echo "<p>New format: <strong>+60123456789</strong></p>";
    
    // Verify the update
    $result = $conn->query("SELECT staff_email, phone, first_name, last_name FROM Staff WHERE role='admin'");
    if ($row = $result->fetch_assoc()) {
        echo "<h3>Current Admin Record:</h3>";
        echo "<pre>";
        print_r($row);
        echo "</pre>";
    }
} else {
    echo "<h2 style='color:red;'>✗ Error updating admin phone</h2>";
    echo "<p>Error: " . $conn->error . "</p>";
}

$conn->close();
?>
