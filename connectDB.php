<?php
$servername = "sql12.freesqldatabase.com";
$username = "sql12810487";
$password = "bMQ7LPiA6X";
$dbname = "sql12810487";
$port = 3306;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully to FreeSQLDatabase!";

// Example query: show all tables
$sql = "SHOW TABLES";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<ul>";
    while($row = $result->fetch_assoc()) {
        echo "<li>" . $row[key($row)] . "</li>";
    }
    echo "</ul>";
} else {
    echo "No tables found.";
}

// Close connection
$conn->close();
?>
