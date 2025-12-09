<?php
$servername = "sql12.freesqldatabase.com";
$username = "sql12810487";
$password = "bMQ7LPiA6X"; // XAMPP default is empty
$dbname = "sql12810487";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>