<?php
$host = "localhost";   // your host
$user = "root";               // your DB username
$pass = "";               // your DB password
$dbname = "salon";              // your database name
$port = 3306;                          // normally 3306

$conn = mysqli_connect($host, $user, $pass, $dbname, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
// echo "Connected successfully"; // optional
?>
