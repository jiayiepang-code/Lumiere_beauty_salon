<?php
$host = "sql12.freesqldatabase.com";   // your host
$user = "sql12810487";               // your DB username
$pass = "bMQ7LPiA6X";               // your DB password
$dbname = "sql12810487";              // your database name
$port = 3306;                          // normally 3306

$conn = mysqli_connect($host, $user, $pass, $dbname, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
// echo "Connected successfully"; // optional
?>
