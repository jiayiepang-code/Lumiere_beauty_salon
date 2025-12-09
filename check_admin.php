<?php
require_once 'config/config.php';
$conn = getDBConnection();

$result = $conn->query("SELECT staff_email, phone, first_name, last_name, role FROM Staff WHERE role='admin'");

echo "<h2>Admin Records in Database:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Email</th><th>Phone</th><th>First Name</th><th>Last Name</th><th>Role</th></tr>";

while($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['staff_email']}</td>";
    echo "<td>{$row['phone']}</td>";
    echo "<td>{$row['first_name']}</td>";
    echo "<td>{$row['last_name']}</td>";
    echo "<td>{$row['role']}</td>";
    echo "</tr>";
}

echo "</table>";
$conn->close();
?>
