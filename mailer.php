<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Use manually installed PHPMailer instead of Composer autoloader
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

require 'config.php';

function sendMail($to, $subject, $body, $maxRetries = 3) {
    global $smtp_host, $smtp_port, $smtp_user, $smtp_pass;
    
    // #region agent log
    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:13', 'message' => 'sendMail function called', 'data' => ['to' => $to, 'subject' => $subject, 'smtp_host' => $smtp_host, 'smtp_port' => $smtp_port, 'maxRetries' => $maxRetries], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
    
    $lastError = null;
    $debugOutput = '';
    
    // Retry loop with exponential backoff
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        // #region agent log
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:attempt', 'message' => 'Email send attempt', 'data' => ['attempt' => $attempt, 'maxRetries' => $maxRetries, 'to' => $to], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'H']) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion
        
        // Wait before retry (exponential backoff: 1s, 2s, 4s)
        if ($attempt > 1) {
            $waitTime = pow(2, $attempt - 2); // 1, 2, 4 seconds
            sleep($waitTime);
        }
        
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
                @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:32', 'message' => 'SMTP Debug Output', 'data' => ['debug_line' => trim($str), 'level' => $level, 'attempt' => $attempt], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
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
            
            // Set timeout - increase for retries
            $mail->Timeout = 60 + ($attempt * 10); // 60s, 70s, 80s
            
            // #region agent log
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:47', 'message' => 'SMTP configured', 'data' => ['host' => $smtp_host, 'port' => $smtp_port, 'encryption' => 'STARTTLS', 'ssl_options_set' => true, 'username' => $smtp_user, 'password_length' => strlen($smtp_pass), 'password_first_chars' => substr($smtp_pass, 0, 4) . '...', 'timeout' => $mail->Timeout, 'attempt' => $attempt], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E']) . "\n", FILE_APPEND | LOCK_EX);
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
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:59', 'message' => 'Attempting to send email', 'data' => ['to' => $to, 'from' => $smtp_user, 'body_length' => strlen($body), 'charset' => 'UTF-8', 'attempt' => $attempt], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'D']) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion

            // #region agent log
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:69', 'message' => 'Before send() call', 'data' => ['body_length' => strlen($body), 'subject_length' => strlen($subject), 'charset' => $mail->CharSet, 'attempt' => $attempt], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'D']) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            
            $mail->send();
            
            // #region agent log
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:72', 'message' => 'Email sent successfully', 'data' => ['to' => $to, 'attempt' => $attempt], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'B']) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            
            return true; // Success!
            
        } catch (Exception $e) {
            $lastError = $mail->ErrorInfo;
            
            // #region agent log
            $smtpInstance = null;
            try {
                $smtpInstance = $mail->getSMTPInstance();
            } catch (Exception $ex) {
                // SMTP instance not available
            }
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:attempt-failed', 'message' => 'Email send attempt failed', 'data' => ['to' => $to, 'error' => $mail->ErrorInfo, 'exception' => $e->getMessage(), 'attempt' => $attempt, 'maxRetries' => $maxRetries, 'willRetry' => $attempt < $maxRetries], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'I']) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            
            // Check if this is a connection error that we should retry
            $isConnectionError = (
                strpos($mail->ErrorInfo, 'Could not connect') !== false ||
                strpos($mail->ErrorInfo, 'connect error') !== false ||
                strpos($mail->ErrorInfo, 'Connection timed out') !== false ||
                strpos($mail->ErrorInfo, '10060') !== false
            );
            
            // If last attempt or not a connection error, don't retry
            if ($attempt >= $maxRetries || !$isConnectionError) {
                break;
            }
            
            // Close SMTP connection before retry
            try {
                $smtp = $mail->getSMTPInstance();
                if ($smtp && $smtp->connected()) {
                    $smtp->quit();
                    $smtp->close();
                }
            } catch (Exception $ex) {
                // Ignore cleanup errors
            }
        }
    }
    
    // All retries failed
    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:all-retries-failed', 'message' => 'All email send attempts failed', 'data' => ['to' => $to, 'error' => $lastError, 'attempts' => $maxRetries], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'J']) . "\n", FILE_APPEND | LOCK_EX);
    
    // Return detailed error for debugging (in production, return generic message)
    return $lastError . (isset($debugOutput) && !empty($debugOutput) ? "\n\nSMTP Debug:\n" . $debugOutput : '');
} 