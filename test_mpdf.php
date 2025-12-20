<?php
/**
 * Quick test to verify mPDF installation
 * Run this file in your browser to test: http://localhost/Lumiere_beauty_salon/test_mpdf.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>mPDF Installation Test</h2>";

// Include the helper
require_once __DIR__ . '/api/admin/analytics/mpdf_helper.php';

echo "<p>Testing mPDF initialization...</p>";

$mpdf = initMPDF();

if ($mpdf) {
    echo "<p style='color: green;'><strong>✓ SUCCESS!</strong> mPDF is properly installed and initialized.</p>";
    echo "<p>mPDF class: " . get_class($mpdf) . "</p>";
    
    // Try to create a simple PDF
    try {
        $mpdf->WriteHTML('<h1>Test PDF</h1><p>If you can see this, mPDF is working correctly!</p>');
        $test_file = __DIR__ . '/test_output.pdf';
        $mpdf->Output($test_file, 'F');
        
        if (file_exists($test_file)) {
            echo "<p style='color: green;'><strong>✓ PDF Generation Test Passed!</strong></p>";
            echo "<p>Test PDF created at: <a href='test_output.pdf' target='_blank'>test_output.pdf</a></p>";
            echo "<p>You can delete this test file after verification.</p>";
        } else {
            echo "<p style='color: orange;'>⚠ PDF file was not created. Check file permissions.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>✗ Error generating PDF:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='color: red;'><strong>✗ FAILED!</strong> mPDF could not be initialized.</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>mPDF files are in <code>vendor/mpdf/</code></li>";
    echo "<li><code>vendor/mpdf/src/Mpdf.php</code> exists</li>";
    echo "<li>File permissions are correct</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<p>If the test passed, you can now use the PDF export features in:</p>";
echo "<ul>";
echo "<li>Business Analytics → Export Report</li>";
echo "<li>Sustainability Analytics → Export to PDF</li>";
echo "</ul>";



