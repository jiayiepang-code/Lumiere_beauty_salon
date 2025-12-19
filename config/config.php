<?php
// Configuration file to store sensitive connection details
// Replace placeholder values with actual credentials

// config.php - Configuration Details

// --- MySQL Database Configuration ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // e.g., 'root'
define('DB_PASSWORD', ''); // e.g., 'mysql'
define('DB_NAME', 'salon');

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

// Note: Database connection function is now in config/db_connect.php
// Use require_once '../../config/db_connect.php' to get getDBConnection() function