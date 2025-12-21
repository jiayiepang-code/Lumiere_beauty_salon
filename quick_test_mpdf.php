<?php
// Quick 30-second test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing mPDF...<br>";

$mpdf_path = __DIR__ . '/vendor/mpdf/src/Mpdf.php';
if (!file_exists($mpdf_path)) {
    die("ERROR: mPDF not found at: $mpdf_path<br>Make sure vendor/mpdf/src/Mpdf.php exists");
}

echo "✓ mPDF file found<br>";

// Try to require and initialize
try {
    require_once __DIR__ . '/api/admin/analytics/mpdf_helper.php';
    $mpdf = initMPDF();
    
    if ($mpdf) {
        echo "✓ mPDF initialized successfully!<br>";
        echo "✓ Class: " . get_class($mpdf) . "<br>";
        echo "<br><strong style='color:green;'>READY TO USE!</strong><br>";
        echo "You can now export PDFs from:<br>";
        echo "- Business Analytics page<br>";
        echo "- Sustainability Analytics page<br>";
    } else {
        die("ERROR: mPDF failed to initialize. Check error logs.");
    }
} catch (Exception $e) {
    die("ERROR: " . $e->getMessage());
}




