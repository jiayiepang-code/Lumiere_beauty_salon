<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Use manually installed PHPMailer instead of Composer autoloader
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

require 'config.php';

function sendMail($to, $subject, $body) {
    global $smtp_host, $smtp_port, $smtp_user, $smtp_pass;
    
    // #region agent log
    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:13', 'message' => 'sendMail function called', 'data' => ['to' => $to, 'subject' => $subject, 'smtp_host' => $smtp_host, 'smtp_port' => $smtp_port], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
    
    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtp_port;
        
        // Enable verbose debug output to diagnose authentication issues
        $mail->SMTPDebug = 2;
        $debugOutput = '';
        $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
            $debugOutput .= $str . "\n";
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:32', 'message' => 'SMTP Debug Output', 'data' => ['debug_line' => trim($str), 'level' => $level], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
        };
        
        // Fix SSL certificate verification issue for local development
        // This disables SSL verification - use only for local development
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set timeout to prevent connection drops during large email transmission
        $mail->Timeout = 60; // 60 seconds timeout
        
        // #region agent log
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:47', 'message' => 'SMTP configured', 'data' => ['host' => $smtp_host, 'port' => $smtp_port, 'encryption' => 'STARTTLS', 'ssl_options_set' => true, 'username' => $smtp_user, 'password_length' => strlen($smtp_pass), 'password_first_chars' => substr($smtp_pass, 0, 4) . '...', 'timeout' => 60], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E']) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion

        //Recipients
        $mail->setFrom($smtp_user, 'Lumière Beauty Salon');
        $mail->addAddress($to);

        //Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8'; // Fix encoding issue - use UTF-8 instead of iso-8859-1
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        // #region agent log
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:59', 'message' => 'Attempting to send email', 'data' => ['to' => $to, 'from' => $smtp_user, 'body_length' => strlen($body), 'charset' => 'UTF-8'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'D']) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion

        // #region agent log
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:69', 'message' => 'Before send() call', 'data' => ['body_length' => strlen($body), 'subject_length' => strlen($subject), 'charset' => $mail->CharSet], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'D']) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion
        
        $mail->send();
        
        // #region agent log
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:72', 'message' => 'Email sent successfully', 'data' => ['to' => $to], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'B']) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion
        
        return true;
    } catch (Exception $e) {
        // #region agent log
        $smtpInstance = null;
        try {
            $smtpInstance = $mail->getSMTPInstance();
        } catch (Exception $ex) {
            // SMTP instance not available
        }
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:80', 'message' => 'Email send failed', 'data' => ['to' => $to, 'error' => $mail->ErrorInfo, 'exception' => $e->getMessage(), 'smtp_debug_output_length' => isset($debugOutput) ? strlen($debugOutput) : 0, 'smtp_instance_available' => $smtpInstance !== null ? 'yes' : 'no'], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'D']) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion
        
        // Return detailed error for debugging (in production, return generic message)
        return $mail->ErrorInfo . (isset($debugOutput) && !empty($debugOutput) ? "\n\nSMTP Debug:\n" . $debugOutput : '');
    }
} 