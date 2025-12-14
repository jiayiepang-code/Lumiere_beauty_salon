<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
require_once 'config/db_connect.php';

$conn = getDBConnection();

// Check what's in the database
echo "<h2>Current Admin Record:</h2>";
$result = $conn->query("SELECT staff_email, phone, first_name, last_name, role, LENGTH(phone) as phone_length FROM Staff WHERE role='admin'");

if ($row = $result->fetch_assoc()) {
    echo "<pre>";
    print_r($row);
    echo "</pre>";
    
    $stored_phone = $row['phone'];
    echo "<p>Stored phone: <strong>$stored_phone</strong></p>";
    echo "<p>Phone length: <strong>" . $row['phone_length'] . "</strong></p>";
} else {
    echo "<p style='color:red;'>No admin record found!</p>";
}

echo "<hr><h2>Testing Phone Normalization:</h2>";

// Test what the login script will do
$test_input = "123456789"; // What user enters
echo "<p>User input: <strong>$test_input</strong></p>";

$phone = preg_replace('/[^0-9]/', '', $test_input);
echo "<p>After removing non-numeric: <strong>$phone</strong></p>";

if (substr($phone, 0, 1) === '0') {
    $phone = '60' . substr($phone, 1);
} else if (substr($phone, 0, 2) !== '60') {
    $phone = '60' . $phone;
}
echo "<p>After normalization: <strong>$phone</strong></p>";

$formatted_phone = '+' . substr($phone, 0, 2) . ' ' . substr($phone, 2, 2) . ' ' . substr($phone, 4, 4) . ' ' . substr($phone, 8);
echo "<p>After formatting: <strong>$formatted_phone</strong></p>";

echo "<hr><h2>Database Query Test:</h2>";

$stmt = $conn->prepare("SELECT staff_email, phone FROM Staff WHERE (phone = ? OR phone = ?) AND role = 'admin'");
$stmt->bind_param("ss", $formatted_phone, $phone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<p style='color:green;'>✓ Found admin with this phone!</p>";
    while($row = $result->fetch_assoc()) {
        echo "<pre>";
        print_r($row);
        echo "</pre>";
    }
} else {
    echo "<p style='color:red;'>✗ No admin found with phone: $formatted_phone or $phone</p>";
}

$conn->close();
?>
