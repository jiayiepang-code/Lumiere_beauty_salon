<?php
/**
 * Database Connection File
 * This file provides backward compatibility for APIs using the old connection path
 * It simply includes the new centralized db_connect.php
 */

require_once __DIR__ . '/../config/db_connect.php';

// Create a global $conn variable for backward compatibility with APIs
// that expect a MySQLi connection object
$conn = getDBConnection();
?>
