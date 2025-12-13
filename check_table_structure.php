<?php
require_once 'config/config.php';
$conn = getDBConnection();

echo "<h2>Service Table Structure:</h2>";
$result = $conn->query('SHOW COLUMNS FROM Service');
echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Staff Table Structure:</h2>";
$result = $conn->query('SHOW COLUMNS FROM Staff');
echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Sample Service Data:</h2>";
$result = $conn->query('SELECT * FROM Service LIMIT 5');
echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Category</th><th>Sub Category</th><th>Name</th><th>Duration</th><th>Price</th><th>Image</th></tr>";
while($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['service_id']}</td>";
    echo "<td>{$row['service_category']}</td>";
    echo "<td>{$row['sub_category']}</td>";
    echo "<td>{$row['service_name']}</td>";
    echo "<td>{$row['duration_minutes']}</td>";
    echo "<td>{$row['price']}</td>";
    echo "<td>{$row['service_image']}</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>
