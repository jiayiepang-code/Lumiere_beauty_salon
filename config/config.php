//configuration file to store sensitive connection details
//replace placeholder values with actual credentials

<?php
// config.php - Configuration Details

// --- MySQL Database Configuration ---
define('DB_SERVER', 'sql12.freesqldatabase.com');
define('DB_USERNAME', 'sql12810487'); // e.g., 'root'
define('DB_PASSWORD', 'bMQ7LPiA6X'); // e.g., 'mysql'
define('DB_NAME', 'sql12810487');

// --- PHPMailer SMTP Configuration (Using a free service like Gmail for this example) ---
// Note: Using a standard Gmail account requires setting up an "App Password" for security.
// Search for "Gmail App Password" to set this up. Do NOT use your regular password.
/*define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USERNAME', 'lumierebeautysalon2022@gmail.com'); // The sending email address
define('MAIL_PASSWORD', 'sdog turz dhwy ymqf'); // The App Password you generate
define('MAIL_PORT', 587); // Use 587 for TLS, or 465 for SMTPS
define('MAIL_ENCRYPTION', 'tls'); // Use 'tls' or 'ssl'
define('MAIL_FROM_NAME', 'Lumiere Beauty Salon');*/

// ---------------------------
// 2. PHPMailer (SMTP) CONFIGURATION
// ---------------------------
// Use your Gmail settings for free testing
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'lumierebeautysalon2022@gmail.com');  // Your full Gmail address
define('SMTP_PASSWORD', 'sdogturzdhwyymqf');       // **CRITICAL: Your 16-character Gmail App Password**
define('SMTP_PORT', 587); // TSL port
define('SMTP_SENDER_EMAIL', 'lumierebeautysalon2022@gmail.com');
define('SMTP_SENDER_NAME', 'Lumiere Beauty Salon');

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

// Helper function to connect to DB
function getDBConnection() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        // Log error instead of dying in production, but for demo:
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
?>