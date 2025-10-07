<?php
/**
 * Email Configuration
 * SMTP settings for sending booking confirmation emails
 */

class EmailConfig {
    // SMTP Configuration
    const SMTP_HOST = 'smtpauth.intel.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'sys_github01@intel.com';
    const SMTP_PASSWORD = 'dateAug21st2025!@#$%';
    const SMTP_SECURE = 'tls'; // or 'ssl'
    const SMTP_TIMEOUT = 30;
    
    // Email Settings
    const FROM_EMAIL = 'sys_github01@intel.com';
    const FROM_NAME = 'Bus Booking System';
    const REPLY_TO_EMAIL = 'sys_github01@intel.com';
    const REPLY_TO_NAME = 'Bus Booking Support';
    
    // Email Templates
    const SUBJECT_BOOKING_CONFIRMATION = 'Bus Booking Confirmation - {BOOKING_ID}';
    const SUBJECT_BOOKING_CANCELLATION = 'Bus Booking Cancellation - {BOOKING_ID}';
    
    /**
     * Get SMTP configuration array
     */
    public static function getSmtpConfig() {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'username' => self::SMTP_USERNAME,
            'password' => self::SMTP_PASSWORD,
            'secure' => self::SMTP_SECURE,
            'timeout' => self::SMTP_TIMEOUT,
            'from_email' => self::FROM_EMAIL,
            'from_name' => self::FROM_NAME,
            'reply_to_email' => self::REPLY_TO_EMAIL,
            'reply_to_name' => self::REPLY_TO_NAME
        ];
    }
}
