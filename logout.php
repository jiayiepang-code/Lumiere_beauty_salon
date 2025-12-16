<?php
// logout.php
session_start();
session_destroy();
header("Location: user/index.php"); // Go back to Home (guest view)
exit;
?>