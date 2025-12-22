-- Email Queue Table for Reminder Emails
-- This table stores scheduled reminder emails that are sent 24 hours before bookings

CREATE TABLE IF NOT EXISTS email_queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id VARCHAR(20) NOT NULL,
    email_type ENUM('confirmation', 'reminder') NOT NULL,
    recipient_email VARCHAR(100) NOT NULL,
    scheduled_at DATETIME NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at DATETIME NULL,
    retry_count INT DEFAULT 0,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_email_queue_scheduled (scheduled_at, status),
    INDEX idx_email_queue_type_status (email_type, status),
    INDEX idx_email_queue_booking_id (booking_id),
    FOREIGN KEY (booking_id) REFERENCES booking(booking_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

