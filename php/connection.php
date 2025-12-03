<?php
$servername = "localhost";
$username = "root";
$password = ""; // XAMPP default is empty
$dbname = "lumiere_salon_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>