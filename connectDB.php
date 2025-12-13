<?php
// Local XAMPP Configuration
$servername = "localhost";    // Or "127.0.0.1" as seen in your screenshot
$username = "root";         // Default XAMPP username
$password = "";             // Default XAMPP password is empty
$dbname = "salon";          // Your database name from the image
$port = 3306;               // Default MySQL port

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully to local 'salon' database!";

// Example query: show all tables in 'salon'
$sql = "SHOW TABLES";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h3>Tables in your salon database:</h3>";
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