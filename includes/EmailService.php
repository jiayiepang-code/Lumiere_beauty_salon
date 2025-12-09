<?php
require_once 'config/email_config.php';
require_once 'vendor/autoload.php'; // PHPMailer via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->configureSMTP();
    }
    
    private function configureSMTP() {
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = SMTP_USERNAME;
        $this->mailer->Password = SMTP_PASSWORD;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = SMTP_PORT;
        $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    }
    
    /**
     * Send booking confirmation email immediately
     */
    public function sendConfirmationEmail($bookingData) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($bookingData['customer_email'], $bookingData['customer_name']);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Booking Confirmed - ' . $bookingData['booking_id'];
            $this->mailer->Body = $this->getConfirmationTemplate($bookingData);
            $this->mailer->AltBody = $this->getConfirmationTextTemplate($bookingData);
            
            $this->mailer->send();
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Confirmation email failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send reminder email 24h before booking
     */
    public function sendReminderEmail($bookingData) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($bookingData['customer_email'], $bookingData['customer_name']);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Reminder: Your Appointment Tomorrow at Lumière';
            $this->mailer->Body = $this->getReminderTemplate($bookingData);
            $this->mailer->AltBody = $this->getReminderTextTemplate($bookingData);
            
            $this->mailer->send();
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Reminder email failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // HTML Email Templates
    
    private function getConfirmationTemplate($data) {
        $bookingDateTime = $this->formatDateTime($data['booking_date'], $data['start_time']);
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .booking-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
        .detail-row { margin: 10px 0; }
        .label { font-weight: bold; color: #667eea; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✨ Booking Confirmed!</h1>
            <p>Thank you for choosing Lumière Beauty Salon</p>
        </div>
        <div class="content">
            <p>Dear {$data['customer_name']},</p>
            <p>Your booking has been successfully confirmed. We look forward to pampering you!</p>
            
            <div class="booking-details">
                <h3 style="margin-top: 0; color: #667eea;">📋 Booking Details</h3>
                <div class="detail-row">
                    <span class="label">Booking ID:</span> {$data['booking_id']}
                </div>
                <div class="detail-row">
                    <span class="label">Date & Time:</span> {$bookingDateTime}
                </div>
                <div class="detail-row">
                    <span class="label">Location:</span> {$data['location']}
                </div>
                <div class="detail-row">
                    <span class="label">Total Amount:</span> RM {$data['total_price']}
                </div>
            </div>
            
            <p><strong>📍 Address:</strong><br>{$data['address']}</p>
            <p><strong>📞 Contact:</strong> {$data['phone']}</p>
            
            <p style="margin-top: 30px;">
                <strong>💡 Important Notes:</strong><br>
                • Please arrive 5 minutes early<br>
                • You'll receive a reminder 24 hours before your appointment<br>
                • Need to reschedule? Contact us or manage your booking online
            </p>
            
            <center>
                <a href="https://lumiere-salon.com/my-bookings" class="button">View My Bookings</a>
            </center>
            
            <div class="footer">
                <p>This is an automated confirmation. Please do not reply to this email.</p>
                <p>&copy; 2024 Lumière Beauty Salon. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    private function getReminderTemplate($data) {
        $bookingDateTime = $this->formatDateTime($data['booking_date'], $data['start_time']);
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .reminder-box { background: #fff3cd; border-left: 4px solid #f5576c; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .booking-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .detail-row { margin: 10px 0; }
        .label { font-weight: bold; color: #f5576c; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        .button { display: inline-block; padding: 12px 30px; background: #f5576c; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏰ Appointment Reminder</h1>
            <p>Your beauty session is tomorrow!</p>
        </div>
        <div class="content">
            <p>Dear {$data['customer_name']},</p>
            
            <div class="reminder-box">
                <h3 style="margin-top: 0;">🎯 Don't Forget!</h3>
                <p style="font-size: 18px; margin: 10px 0;">
                    Your appointment at <strong>Lumière Beauty Salon</strong> is in 24 hours.
                </p>
            </div>
            
            <div class="booking-details">
                <h3 style="margin-top: 0; color: #f5576c;">📋 Appointment Details</h3>
                <div class="detail-row">
                    <span class="label">Booking ID:</span> {$data['booking_id']}
                </div>
                <div class="detail-row">
                    <span class="label">Date & Time:</span> {$bookingDateTime}
                </div>
                <div class="detail-row">
                    <span class="label">Location:</span> {$data['location']}
                </div>
            </div>
            
            <p><strong>📍 Address:</strong><br>{$data['address']}</p>
            <p><strong>📞 Need to reschedule?</strong> Call us at {$data['phone']}</p>
            
            <p style="margin-top: 30px;">
                <strong>✨ Preparation Tips:</strong><br>
                • Arrive 5 minutes early<br>
                • Bring any relevant medical information<br>
                • Wear comfortable clothing<br>
                • Remove contact lenses if getting facial treatment
            </p>
            
            <center>
                <a href="https://lumiere-salon.com/my-bookings" class="button">View Details</a>
                <a href="https://lumiere-salon.com/contact" class="button" style="background: #6c757d;">Contact Us</a>
            </center>
            
            <div class="footer">
                <p>See you soon! 💅</p>
                <p>&copy; 2024 Lumière Beauty Salon. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    // Plain text versions (for email clients that don't support HTML)
    
    private function getConfirmationTextTemplate($data) {
        $bookingDateTime = $this->formatDateTime($data['booking_date'], $data['start_time']);
        
        return <<<TEXT
BOOKING CONFIRMED - Lumière Beauty Salon

Dear {$data['customer_name']},

Your booking has been successfully confirmed!

BOOKING DETAILS:
- Booking ID: {$data['booking_id']}
- Date & Time: {$bookingDateTime}
- Location: {$data['location']}
- Total Amount: RM {$data['total_price']}

Address: {$data['address']}
Contact: {$data['phone']}

IMPORTANT NOTES:
- Please arrive 5 minutes early
- You'll receive a reminder 24 hours before your appointment
- Need to reschedule? Contact us or manage your booking online

View your bookings: https://lumiere-salon.com/my-bookings

---
This is an automated confirmation. Please do not reply to this email.
© 2024 Lumière Beauty Salon. All rights reserved.
TEXT;
    }
    
    private function getReminderTextTemplate($data) {
        $bookingDateTime = $this->formatDateTime($data['booking_date'], $data['start_time']);
        
        return <<<TEXT
APPOINTMENT REMINDER - Lumière Beauty Salon

Dear {$data['customer_name']},

Your appointment at Lumière Beauty Salon is in 24 hours!

APPOINTMENT DETAILS:
- Booking ID: {$data['booking_id']}
- Date & Time: {$bookingDateTime}
- Location: {$data['location']}

Address: {$data['address']}
Need to reschedule? Call us at {$data['phone']}

PREPARATION TIPS:
- Arrive 5 minutes early
- Bring any relevant medical information
- Wear comfortable clothing

View details: https://lumiere-salon.com/my-bookings

See you soon!
© 2025 Lumière Beauty Salon. All rights reserved.
TEXT;
    }
    
    // Helper: Format datetime for Malaysia (UTC+8)
    private function formatDateTime($date, $time) {
        // MySQL DATE + TIME to readable format
        // Example: "2025-12-25" + "14:00:00" -> "Wednesday, 25 December 2025 at 2:00 PM"
        $datetime = new DateTime("$date $time");
        return $datetime->format('l, d F Y \a\t g:i A');
    }
}
?>