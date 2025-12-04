<?php
$host = "sql12.freesqldatabase.com";   // your host
$user = "your_username";               // your DB username
$pass = "your_password";               // your DB password
$dbname = "your_db_name";              // your database name
$port = 3306;                          // normally 3306

$conn = mysqli_connect($host, $user, $pass, $dbname, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
// echo "Connected successfully"; // optional
?>
