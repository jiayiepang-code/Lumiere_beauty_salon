<?php
/**
 * Quick Integration Test Script
 * Run: http://localhost/Lumiere-beauty-salon/test_integration.php
 */

$baseUrl = 'http://localhost/Lumiere-beauty-salon';
$issues = [];

// Test 1: User Homepage
$userHome = @file_get_contents("$baseUrl/user/index.php");
if (!$userHome || strpos($userHome, 'home.php') !== false) {
    $issues[] = "❌ User module: index.php references non-existent home.php";
} else {
    $issues[] = "✅ User module: index.php exists";
}

// Test 2: Admin Dashboard
$adminDashboard = @file_get_contents("$baseUrl/admin/index.php");
if (!$adminDashboard) {
    $issues[] = "❌ Admin module: index.php not accessible";
} else {
    $issues[] = "✅ Admin module: index.php exists";
}

// Test 3: Check API files exist
$apiFiles = [
    'api/admin/bookings/list.php',
    'api/admin/services/list.php',
    'api/admin/staff/list.php',
    'api/admin/customers/list.php'
];

foreach ($apiFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $issues[] = "✅ API: $file exists";
    } else {
        $issues[] = "❌ API: $file MISSING";
    }
}

// Test 4: Check database config
if (file_exists(__DIR__ . '/config/db_connect.php')) {
    $issues[] = "✅ Database config exists";
} else {
    $issues[] = "❌ Database config MISSING";
}

// Output results
echo "<h1>Integration Test Results</h1>";
echo "<pre>";
foreach ($issues as $issue) {
    echo $issue . "\n";
}
echo "</pre>";
?>