<?php
session_start();
require_once '../config/db_connect.php';
require_once 'includes/auth_check.php';

echo "<h2>Admin API Diagnostic</h2>";

// Test 1: Database Connection
echo "<h3>1. Database Connection</h3>";
try {
    $conn = getDBConnection();
    echo "✅ Database connected<br>";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 2: Session
echo "<h3>2. Session Status</h3>";
if (isAdminAuthenticated()) {
    echo "✅ Admin authenticated<br>";
    $admin = getCurrentAdmin();
    echo "Admin: " . htmlspecialchars($admin['first_name'] ?? 'Unknown') . "<br>";
} else {
    echo "❌ Not authenticated<br>";
}

// Test 3: API Endpoints
echo "<h3>3. API Endpoints Test</h3>";
$endpoints = [
    '../api/admin/bookings/list.php',
    '../api/admin/services/list.php',
    '../api/admin/staff/list.php'
];

foreach ($endpoints as $endpoint) {
    $fullPath = __DIR__ . '/' . $endpoint;
    if (file_exists($fullPath)) {
        echo "✅ $endpoint exists<br>";
    } else {
        echo "❌ $endpoint NOT FOUND<br>";
    }
}
?>