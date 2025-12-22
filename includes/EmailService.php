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
        // Use SSL for port 465, STARTTLS for port 587
        if (SMTP_PORT == 465) {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        } else {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // STARTTLS
        }
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
    
    /**
     * Send booking conflict notification (staff on leave)
     */
    public function sendBookingConflictEmail($bookingData) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($bookingData['customer_email'], $bookingData['customer_name']);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Important: Your Appointment Needs Rescheduling - Lumière';
            $this->mailer->Body = $this->getConflictTemplate($bookingData);
            $this->mailer->AltBody = $this->getConflictTextTemplate($bookingData);
            
            $this->mailer->send();
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Conflict email failed: " . $e->getMessage());
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
        body { font-family: 'Playfair Display', 'Georgia', serif, Arial, sans-serif; line-height: 1.6; color: #5c4e4b; background: #f5e9e4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
        .email-card { background: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(194,144,118,0.15); overflow: hidden; }
        .header { background: linear-gradient(135deg, #D4A574 0%, #c29076 100%); color: white; padding: 40px 30px; text-align: center; border-bottom: 3px solid #B59267; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 700; font-family: 'Playfair Display', serif; letter-spacing: 0.5px; }
        .header p { margin: 12px 0 0 0; font-size: 16px; opacity: 0.95; }
        .content { padding: 40px 32px; }
        .greeting { font-size: 16px; color: #5c4e4b; margin-bottom: 20px; }
        .intro-text { font-size: 16px; color: #5c4e4b; line-height: 1.6; margin-bottom: 32px; }
        .booking-details { background: #faf5f2; padding: 24px; border-radius: 12px; margin: 24px 0; border-left: 4px solid #D4A574; }
        .booking-details h3 { margin: 0 0 20px 0; color: #c29076; font-size: 20px; font-weight: 600; font-family: 'Playfair Display', serif; }
        .detail-row { margin: 12px 0; font-size: 15px; color: #5c4e4b; }
        .label { font-weight: 600; color: #8a766e; display: inline-block; min-width: 140px; }
        .value { color: #2d2d2d; font-weight: 500; }
        .info-section { background: #faf5f2; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e6d9d2; }
        .info-section strong { color: #c29076; display: block; margin-bottom: 8px; }
        .notes-box { background: #fff9e6; border-left: 4px solid #D4A574; padding: 20px; border-radius: 8px; margin: 24px 0; }
        .notes-box strong { color: #c29076; display: block; margin-bottom: 12px; font-size: 16px; }
        .notes-box ul { margin: 0; padding-left: 20px; color: #5c4e4b; }
        .notes-box li { margin: 8px 0; }
        .button { display: inline-block; padding: 14px 40px; background: linear-gradient(135deg, #D4A574 0%, #c29076 100%); color: #ffffff; border-radius: 30px; font-weight: 600; text-decoration: none; font-size: 16px; margin: 24px 0; box-shadow: 0 4px 12px rgba(194,144,118,0.3); }
        .button:hover { box-shadow: 0 6px 16px rgba(194,144,118,0.4); }
        .footer { text-align: center; margin-top: 32px; padding-top: 24px; border-top: 1px solid #e6d9d2; color: #8a766e; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="email-card">
            <div class="header">
                <h1>✨ Booking Confirmed!</h1>
                <p>Thank you for choosing Lumière Beauty Salon</p>
            </div>
            <div class="content">
                <p class="greeting">Dear {$data['customer_name']},</p>
                <p class="intro-text">Your booking has been successfully confirmed. We look forward to pampering you!</p>
                
                <div class="booking-details">
                    <h3>📋 Booking Details</h3>
                    <div class="detail-row">
                        <span class="label">Booking ID:</span>
                        <span class="value">{$data['booking_id']}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Date & Time:</span>
                        <span class="value">{$bookingDateTime}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Location:</span>
                        <span class="value">{$data['location']}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Total Amount:</span>
                        <span class="value" style="color: #c29076; font-size: 18px; font-weight: 700;">RM {$data['total_price']}</span>
                    </div>
                </div>
                
                <div class="info-section">
                    <strong>📍 Address:</strong>
                    <span style="color: #5c4e4b;">{$data['address']}</span>
                </div>
                
                <div class="info-section">
                    <strong>📞 Contact:</strong>
                    <span style="color: #5c4e4b;">{$data['phone']}</span>
                </div>
                
                <div class="notes-box">
                    <strong>💡 Important Notes:</strong>
                    <ul>
                        <li>Please arrive 10 minutes early</li>
                        <li>You'll receive a reminder 24 hours before your appointment</li>
                        <li>Need to reschedule? Contact us or manage your booking online</li>
                        <li>Payment will be collected at the salon</li>
                    </ul>
                </div>
                
                <div style="text-align: center;">
                    <a href="https://lumiere-salon.com/my-bookings" class="button">View My Bookings</a>
                </div>
                
                <div class="footer">
                    <p style="margin: 0 0 8px 0;">This is an automated confirmation. Please do not reply to this email.</p>
                    <p style="margin: 0;">&copy; {date('Y')} Lumière Beauty Salon. All rights reserved.</p>
                </div>
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
        body { font-family: 'Playfair Display', 'Georgia', serif, Arial, sans-serif; line-height: 1.6; color: #5c4e4b; background: #f5e9e4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
        .email-card { background: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(194,144,118,0.15); overflow: hidden; }
        .header { background: linear-gradient(135deg, #D4A574 0%, #c29076 100%); color: white; padding: 40px 30px; text-align: center; border-bottom: 3px solid #B59267; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 700; font-family: 'Playfair Display', serif; letter-spacing: 0.5px; }
        .header p { margin: 12px 0 0 0; font-size: 16px; opacity: 0.95; }
        .content { padding: 40px 32px; }
        .greeting { font-size: 16px; color: #5c4e4b; margin-bottom: 20px; }
        .reminder-box { background: #fff9e6; border-left: 4px solid #D4A574; padding: 24px; border-radius: 8px; margin: 24px 0; }
        .reminder-box h3 { margin: 0 0 12px 0; color: #c29076; font-size: 20px; font-weight: 600; font-family: 'Playfair Display', serif; }
        .reminder-box p { font-size: 18px; margin: 10px 0; color: #5c4e4b; }
        .booking-details { background: #faf5f2; padding: 24px; border-radius: 12px; margin: 24px 0; border-left: 4px solid #D4A574; }
        .booking-details h3 { margin: 0 0 20px 0; color: #c29076; font-size: 20px; font-weight: 600; font-family: 'Playfair Display', serif; }
        .detail-row { margin: 12px 0; font-size: 15px; color: #5c4e4b; }
        .label { font-weight: 600; color: #8a766e; display: inline-block; min-width: 140px; }
        .value { color: #2d2d2d; font-weight: 500; }
        .info-section { background: #faf5f2; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e6d9d2; }
        .info-section strong { color: #c29076; display: block; margin-bottom: 8px; }
        .tips-box { background: #fff9e6; border-left: 4px solid #D4A574; padding: 20px; border-radius: 8px; margin: 24px 0; }
        .tips-box strong { color: #c29076; display: block; margin-bottom: 12px; font-size: 16px; }
        .tips-box ul { margin: 0; padding-left: 20px; color: #5c4e4b; }
        .tips-box li { margin: 8px 0; }
        .button { display: inline-block; padding: 14px 40px; background: linear-gradient(135deg, #D4A574 0%, #c29076 100%); color: #ffffff; border-radius: 30px; font-weight: 600; text-decoration: none; font-size: 16px; margin: 10px 5px; box-shadow: 0 4px 12px rgba(194,144,118,0.3); }
        .button-secondary { background: linear-gradient(135deg, #8a766e 0%, #7a6a5f 100%); }
        .footer { text-align: center; margin-top: 32px; padding-top: 24px; border-top: 1px solid #e6d9d2; color: #8a766e; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="email-card">
            <div class="header">
                <h1>⏰ Appointment Reminder</h1>
                <p>Your beauty session is tomorrow!</p>
            </div>
            <div class="content">
                <p class="greeting">Dear {$data['customer_name']},</p>
                
                <div class="reminder-box">
                    <h3>🎯 Don't Forget!</h3>
                    <p>Your appointment at <strong>Lumière Beauty Salon</strong> is in 24 hours.</p>
                </div>
                
                <div class="booking-details">
                    <h3>📋 Appointment Details</h3>
                    <div class="detail-row">
                        <span class="label">Booking ID:</span>
                        <span class="value">{$data['booking_id']}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Date & Time:</span>
                        <span class="value">{$bookingDateTime}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Location:</span>
                        <span class="value">{$data['location']}</span>
                    </div>
                </div>
                
                <div class="info-section">
                    <strong>📍 Address:</strong>
                    <span style="color: #5c4e4b;">{$data['address']}</span>
                </div>
                
                <div class="info-section">
                    <strong>📞 Need to reschedule?</strong>
                    <span style="color: #5c4e4b;">Call us at {$data['phone']}</span>
                </div>
                
                <div class="tips-box">
                    <strong>✨ Preparation Tips:</strong>
                    <ul>
                        <li>Arrive 10 minutes early</li>
                        <li>Bring any relevant medical information</li>
                        <li>Wear comfortable clothing</li>
                        <li>Remove contact lenses if getting facial treatment</li>
                    </ul>
                </div>
                
                <div style="text-align: center;">
                    <a href="https://lumiere-salon.com/my-bookings" class="button">View Details</a>
                    <a href="https://lumiere-salon.com/contact" class="button button-secondary">Contact Us</a>
                </div>
                
                <div class="footer">
                    <p style="margin: 0 0 8px 0;">See you soon! 💅</p>
                    <p style="margin: 0;">&copy; {date('Y')} Lumière Beauty Salon. All rights reserved.</p>
                </div>
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
    
    private function getConflictTemplate($data) {
        $bookingDateTime = $this->formatDateTime($data['booking_date'], $data['start_time']);
        $leaveDateRange = $data['leave_dates'];
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .alert-box { background: #fff3cd; border-left: 4px solid #f5576c; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .booking-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .detail-row { margin: 10px 0; }
        .label { font-weight: bold; color: #f5576c; }
        .button { display: inline-block; padding: 12px 30px; background: #f5576c; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        .options-list { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .options-list ol { margin: 10px 0; padding-left: 25px; }
        .options-list li { margin: 8px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Appointment Update Required</h1>
            <p>Your stylist is unavailable</p>
        </div>
        <div class="content">
            <p>Dear {$data['customer_name']},</p>
            
            <div class="alert-box">
                <h3 style="margin-top: 0;">Important Notice</h3>
                <p style="font-size: 16px; margin: 10px 0;">
                    Your assigned stylist <strong>{$data['staff_name']}</strong> will be on leave during your scheduled appointment. 
                    We need to reschedule your appointment or assign you to another stylist.
                </p>
            </div>
            
            <div class="booking-details">
                <h3 style="margin-top: 0; color: #f5576c;">📋 Your Current Appointment</h3>
                <div class="detail-row">
                    <span class="label">Booking ID:</span> {$data['booking_id']}
                </div>
                <div class="detail-row">
                    <span class="label">Date & Time:</span> {$bookingDateTime}
                </div>
                <div class="detail-row">
                    <span class="label">Services:</span> {$data['services']}
                </div>
                <div class="detail-row">
                    <span class="label">Stylist:</span> {$data['staff_name']} (on leave {$leaveDateRange})
                </div>
            </div>
            
            <h3 style="color: #f5576c;">What Happens Next?</h3>
            <p>We have <strong>3 options</strong> for you:</p>
            <div class="options-list">
                <ol>
                    <li><strong>Reschedule</strong> your appointment to another date/time</li>
                    <li><strong>Reassign</strong> to another available stylist on the same date</li>
                    <li><strong>Cancel</strong> your appointment (full refund if applicable)</li>
                </ol>
            </div>
            
            <p><strong>📞 Please contact us ASAP:</strong></p>
            <p style="font-size: 18px; font-weight: bold; color: #f5576c;">
                Phone: {$data['phone']}<br>
                Email: info@lumiere.my
            </p>
            
            <p style="margin-top: 30px;">
                <strong>⏰ Action Required:</strong> Please contact us within <strong>48 hours</strong> to avoid automatic cancellation.
            </p>
            
            <center>
                <a href="https://lumiere-salon.com/my-bookings" class="button">View My Bookings</a>
                <a href="https://lumiere-salon.com/contact" class="button" style="background: #6c757d;">Contact Us</a>
            </center>
            
            <div class="footer">
                <p>We sincerely apologize for any inconvenience and appreciate your understanding.</p>
                <p>&copy; 2025 Lumière Beauty Salon. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    private function getConflictTextTemplate($data) {
        $bookingDateTime = $this->formatDateTime($data['booking_date'], $data['start_time']);
        $leaveDateRange = $data['leave_dates'];
        
        return <<<TEXT
IMPORTANT: APPOINTMENT UPDATE REQUIRED - Lumière Beauty Salon

Dear {$data['customer_name']},

IMPORTANT NOTICE:
Your assigned stylist {$data['staff_name']} will be on leave during your scheduled appointment. 
We need to reschedule your appointment or assign you to another stylist.

YOUR CURRENT APPOINTMENT:
- Booking ID: {$data['booking_id']}
- Date & Time: {$bookingDateTime}
- Services: {$data['services']}
- Stylist: {$data['staff_name']} (on leave {$leaveDateRange})

WHAT HAPPENS NEXT?
We have 3 options for you:
1. Reschedule your appointment to another date/time
2. Reassign to another available stylist on the same date
3. Cancel your appointment (full refund if applicable)

PLEASE CONTACT US ASAP:
Phone: {$data['phone']}
Email: info@lumiere.my

ACTION REQUIRED: Please contact us within 48 hours to avoid automatic cancellation.

View your bookings: https://lumiere-salon.com/my-bookings
Contact us: https://lumiere-salon.com/contact

We sincerely apologize for any inconvenience and appreciate your understanding.

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