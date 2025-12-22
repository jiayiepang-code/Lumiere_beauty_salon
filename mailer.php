<?php
// #region agent log
@file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:1', 'message' => 'mailer.php file loaded', 'data' => ['timestamp' => time() * 1000], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'F']) . "\n", FILE_APPEND | LOCK_EX);
// #endregion

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Use manually installed PHPMailer instead of Composer autoloader
$phpmailerPath = __DIR__ . '/PHPMailer/src/PHPMailer.php';
$smtpPath = __DIR__ . '/PHPMailer/src/SMTP.php';
$exceptionPath = __DIR__ . '/PHPMailer/src/Exception.php';

// #region agent log
@file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:phpmailer-check', 'message' => 'Checking PHPMailer files', 'data' => ['phpmailer_exists' => file_exists($phpmailerPath), 'smtp_exists' => file_exists($smtpPath), 'exception_exists' => file_exists($exceptionPath)], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'F']) . "\n", FILE_APPEND | LOCK_EX);
// #endregion

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

// #region agent log
@file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:phpmailer-loaded', 'message' => 'PHPMailer files loaded', 'data' => ['phpmailer_class_exists' => class_exists('PHPMailer\PHPMailer\PHPMailer')], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'F']) . "\n", FILE_APPEND | LOCK_EX);
// #endregion

require 'config.php';

// #region agent log
@file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:config-loaded', 'message' => 'config.php loaded', 'data' => ['config_exists' => file_exists(__DIR__ . '/config.php'), 'smtp_host_defined' => isset($smtp_host), 'smtp_user_defined' => isset($smtp_user)], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'C']) . "\n", FILE_APPEND | LOCK_EX);
// #endregion

function sendMail($to, $subject, $body, $maxRetries = 1) {
    global $smtp_host, $smtp_port, $smtp_user, $smtp_pass;
    
    // #region agent log
    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:13', 'message' => 'sendMail function called', 'data' => ['to' => $to, 'subject' => $subject, 'smtp_host' => isset($smtp_host) ? $smtp_host : 'NOT_SET', 'smtp_port' => isset($smtp_port) ? $smtp_port : 'NOT_SET', 'smtp_user' => isset($smtp_user) ? $smtp_user : 'NOT_SET', 'smtp_pass_set' => isset($smtp_pass), 'maxRetries' => $maxRetries, 'config_file_exists' => file_exists(__DIR__ . '/config.php')], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
    
    // #region agent log
    if (!isset($smtp_host) || !isset($smtp_port) || !isset($smtp_user) || !isset($smtp_pass)) {
        @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:config-check', 'message' => 'SMTP config variables missing', 'data' => ['smtp_host_set' => isset($smtp_host), 'smtp_port_set' => isset($smtp_port), 'smtp_user_set' => isset($smtp_user), 'smtp_pass_set' => isset($smtp_pass)], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'C']) . "\n", FILE_APPEND | LOCK_EX);
        return 'SMTP configuration not loaded. Please check config.php file.';
    }
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
            // Use SSL for port 465, STARTTLS for port 587
            if ($smtp_port == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // STARTTLS
            }
            $mail->Port       = $smtp_port;
            
            // Enable verbose debug output to diagnose authentication issues
            $mail->SMTPDebug = 2;
            $debugOutput = '';
            $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
                $debugOutput .= $str . "\n";
                @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:32', 'message' => 'SMTP Debug Output', 'data' => ['debug_line' => trim($str), 'level' => $level, 'attempt' => $attempt], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND | LOCK_EX);
            };
            
            // SSL/TLS configuration for Gmail
            // Try minimal SSL options first - Gmail may be rejecting due to SSL config
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // For port 465, try using TLS instead of SSL if SSL fails
            // Some systems have issues with SSL on port 465
            if ($smtp_port == 465) {
                // Try SMTPS (implicit SSL) first, but allow fallback
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            
            // Set shorter timeout to avoid blocking - email is sent in background
            $mail->Timeout = 10; // 10 seconds max per attempt (reduced from 60s)
            
            // Add stream context for better connection handling
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                ]
            ]);
            $mail->set('StreamOptions', $context);
            
            // #region agent log
            $encryptionType = ($smtp_port == 465) ? 'SSL' : 'STARTTLS';
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:47', 'message' => 'SMTP configured', 'data' => ['host' => $smtp_host, 'port' => $smtp_port, 'encryption' => $encryptionType, 'ssl_options_set' => true, 'username' => $smtp_user, 'password_length' => strlen($smtp_pass), 'password_first_chars' => substr($smtp_pass, 0, 4) . '...', 'timeout' => $mail->Timeout, 'attempt' => $attempt], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'post-fix', 'hypothesisId' => 'E']) . "\n", FILE_APPEND | LOCK_EX);
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
            $smtpConnected = false;
            try {
                $smtpInstance = $mail->getSMTPInstance();
                if ($smtpInstance) {
                    $smtpConnected = $smtpInstance->connected();
                }
            } catch (Exception $ex) {
                // SMTP instance not available
            }
            @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:attempt-failed', 'message' => 'Email send attempt failed', 'data' => ['to' => $to, 'error' => $mail->ErrorInfo, 'exception' => $e->getMessage(), 'exception_class' => get_class($e), 'smtp_connected' => $smtpConnected, 'attempt' => $attempt, 'maxRetries' => $maxRetries, 'willRetry' => $attempt < $maxRetries, 'error_contains_auth' => (stripos($mail->ErrorInfo, 'authentication') !== false || stripos($mail->ErrorInfo, 'login') !== false), 'error_contains_connect' => (stripos($mail->ErrorInfo, 'connect') !== false)], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'I']) . "\n", FILE_APPEND | LOCK_EX);
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
    // #region agent log
    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:all-retries-failed', 'message' => 'All email send attempts failed', 'data' => ['to' => $to, 'error' => $lastError, 'attempts' => $maxRetries, 'debug_output_length' => strlen($debugOutput ?? '')], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'J']) . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
    
    // Return detailed error for debugging (in production, return generic message)
    $errorMessage = $lastError . (isset($debugOutput) && !empty($debugOutput) ? "\n\nSMTP Debug:\n" . $debugOutput : '');
    
    // #region agent log
    @file_put_contents(__DIR__ . '/.cursor/debug.log', json_encode(['location' => 'mailer.php:return-error', 'message' => 'Returning error from sendMail', 'data' => ['error_length' => strlen($errorMessage), 'error_preview' => substr($errorMessage, 0, 200)], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'D']) . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
    
    return $errorMessage;
} 