<?php
/**
 * Email Service
 * Handles sending emails using PHP's built-in mail() function with SMTP configuration
 * Provides booking confirmation and cancellation email functionality
 */

require_once __DIR__ . '/config/email.php';

class EmailService {
    private $config;
    private $logFile;
    
    public function __construct() {
        $this->config = EmailConfig::getSmtpConfig();
        $this->logFile = '/tmp/bus_bookings/email_log.txt';
        $this->ensureLogDirectory();
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    /**
     * Log email activity
     */
    private function logEmail($message, $type = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$type}] {$message}\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Validate email address
     */
    private function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Send email using socket-based SMTP (supports authentication)
     */
    private function sendMail($to, $subject, $message, $headers) {
        try {
            // Connect to SMTP server
            $smtp = fsockopen($this->config['host'], $this->config['port'], $errno, $errstr, 30);
            if (!$smtp) {
                $this->logEmail("Failed to connect to SMTP server: $errstr ($errno)", 'ERROR');
                return false;
            }
            
            // Read server greeting (may be multi-line)
            do {
                $response = fgets($smtp, 515);
                if ($response === false) break;
                $code = substr($response, 0, 3);
                $separator = substr($response, 3, 1);
            } while ($separator == '-'); // Multi-line responses have '-' after code
            
            if ($code != '220') {
                $this->logEmail("SMTP Error: $response", 'ERROR');
                fclose($smtp);
                return false;
            }
            
            // Send EHLO (response may be multi-line)
            fputs($smtp, "EHLO " . $this->config['host'] . "\r\n");
            do {
                $response = fgets($smtp, 515);
                if ($response === false) break;
                $code = substr($response, 0, 3);
                $separator = substr($response, 3, 1);
            } while ($separator == '-');
            
            // Start TLS
            fputs($smtp, "STARTTLS\r\n");
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) != '220') {
                $this->logEmail("STARTTLS failed: $response", 'ERROR');
                fclose($smtp);
                return false;
            }
            
            // Enable crypto
            stream_set_blocking($smtp, true);
            stream_context_set_option($smtp, 'ssl', 'verify_peer', false);
            stream_context_set_option($smtp, 'ssl', 'verify_peer_name', false);
            
            // Configure SSL context for corporate SMTP servers
            stream_context_set_option($smtp, 'ssl', 'verify_peer', false);
            stream_context_set_option($smtp, 'ssl', 'verify_peer_name', false);
            stream_context_set_option($smtp, 'ssl', 'allow_self_signed', true);
            
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->logEmail("TLS encryption failed", 'ERROR');
                fclose($smtp);
                return false;
            }
            
            // Send EHLO again after TLS (response may be multi-line)
            fputs($smtp, "EHLO " . $this->config['host'] . "\r\n");
            do {
                $response = fgets($smtp, 515);
                if ($response === false) break;
                $code = substr($response, 0, 3);
                $separator = substr($response, 3, 1);
            } while ($separator == '-');
            
            // Authenticate
            fputs($smtp, "AUTH LOGIN\r\n");
            $response = fgets($smtp, 515);
            
            fputs($smtp, base64_encode($this->config['username']) . "\r\n");
            $response = fgets($smtp, 515);
            
            fputs($smtp, base64_encode($this->config['password']) . "\r\n");
            $response = fgets($smtp, 515);
            
            if (substr($response, 0, 3) != '235') {
                $this->logEmail("SMTP Authentication failed: $response", 'ERROR');
                fclose($smtp);
                return false;
            }
            
            // Send MAIL FROM
            fputs($smtp, "MAIL FROM: <" . $this->config['from_email'] . ">\r\n");
            $response = fgets($smtp, 515);
            
            // Send RCPT TO
            fputs($smtp, "RCPT TO: <$to>\r\n");
            $response = fgets($smtp, 515);
            
            // Send DATA
            fputs($smtp, "DATA\r\n");
            $response = fgets($smtp, 515);
            
            // Send email headers and body
            fputs($smtp, "Subject: $subject\r\n");
            fputs($smtp, $headers . "\r\n");
            fputs($smtp, "\r\n");
            fputs($smtp, $message . "\r\n");
            fputs($smtp, ".\r\n");
            $response = fgets($smtp, 515);
            
            // Close connection
            fputs($smtp, "QUIT\r\n");
            fclose($smtp);
            
            $this->logEmail("Email sent successfully to $to", 'SUCCESS');
            return true;
            
        } catch (Exception $e) {
            $this->logEmail("SMTP Exception: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Send booking confirmation email
     * 
     * @param array $bookingData Booking information
     * @param array $employeeData Employee information with email
     * @return array Result with success status and message
     */
    public function sendBookingConfirmation($bookingData, $employeeData) {
        try {
            // Validate employee data
            if (empty($employeeData) || !isset($employeeData['email'])) {
                $this->logEmail("No email address found for employee: {$bookingData['employee_id']}", 'WARNING');
                return [
                    'success' => false,
                    'message' => 'Email not sent - No email address available for employee',
                    'skip_reason' => 'missing_email'
                ];
            }
            
            $toEmail = $employeeData['email'];
            
            // Validate email address
            if (!$this->isValidEmail($toEmail)) {
                $this->logEmail("Invalid email address for employee: {$bookingData['employee_id']} - {$toEmail}", 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Email not sent - Invalid email address',
                    'skip_reason' => 'invalid_email'
                ];
            }
            
            // Prepare email content
            $bookingId = $bookingData['booking_id'];
            $subject = str_replace('{BOOKING_ID}', $bookingId, EmailConfig::SUBJECT_BOOKING_CONFIRMATION);
            
            // Build email body
            $message = $this->buildBookingConfirmationEmail($bookingData, $employeeData);
            
            // Build headers
            $headers = $this->buildEmailHeaders($toEmail, $employeeData['name'] ?? 'Employee');
            
            // Send email
            $sent = $this->sendMail($toEmail, $subject, $message, $headers);
            
            if ($sent) {
                $this->logEmail("Booking confirmation sent to {$toEmail} for booking {$bookingId}", 'SUCCESS');
                return [
                    'success' => true,
                    'message' => 'Booking confirmation email sent successfully',
                    'email' => $toEmail
                ];
            } else {
                $this->logEmail("Failed to send email to {$toEmail} for booking {$bookingId}", 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Failed to send email - SMTP error',
                    'email' => $toEmail
                ];
            }
            
        } catch (Exception $e) {
            $this->logEmail("Exception sending email: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Email sending failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send booking cancellation email
     * 
     * @param array $bookingData Booking information
     * @param array $employeeData Employee information with email
     * @return array Result with success status and message
     */
    public function sendBookingCancellation($bookingData, $employeeData) {
        try {
            // Validate employee data
            if (empty($employeeData) || !isset($employeeData['email'])) {
                $this->logEmail("No email address found for employee: {$bookingData['employee_id']}", 'WARNING');
                return [
                    'success' => false,
                    'message' => 'Email not sent - No email address available for employee',
                    'skip_reason' => 'missing_email'
                ];
            }
            
            $toEmail = $employeeData['email'];
            
            // Validate email address
            if (!$this->isValidEmail($toEmail)) {
                $this->logEmail("Invalid email address for employee: {$bookingData['employee_id']} - {$toEmail}", 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Email not sent - Invalid email address',
                    'skip_reason' => 'invalid_email'
                ];
            }
            
            // Prepare email content
            $bookingId = $bookingData['booking_id'];
            $subject = str_replace('{BOOKING_ID}', $bookingId, EmailConfig::SUBJECT_BOOKING_CANCELLATION);
            
            // Build email body
            $message = $this->buildBookingCancellationEmail($bookingData, $employeeData);
            
            // Build headers
            $headers = $this->buildEmailHeaders($toEmail, $employeeData['name'] ?? 'Employee');
            
            // Send email
            $sent = $this->sendMail($toEmail, $subject, $message, $headers);
            
            if ($sent) {
                $this->logEmail("Booking cancellation sent to {$toEmail} for booking {$bookingId}", 'SUCCESS');
                return [
                    'success' => true,
                    'message' => 'Booking cancellation email sent successfully',
                    'email' => $toEmail
                ];
            } else {
                $this->logEmail("Failed to send cancellation email to {$toEmail} for booking {$bookingId}", 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Failed to send email - SMTP error',
                    'email' => $toEmail
                ];
            }
            
        } catch (Exception $e) {
            $this->logEmail("Exception sending cancellation email: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Email sending failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Build email headers
     */
    private function buildEmailHeaders($toEmail, $toName) {
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "From: {$this->config['from_name']} <{$this->config['from_email']}>";
        $headers[] = "Reply-To: {$this->config['reply_to_name']} <{$this->config['reply_to_email']}>";
        $headers[] = "X-Mailer: PHP/" . phpversion();
        $headers[] = "X-Priority: 1";
        $headers[] = "Importance: High";
        
        return implode("\r\n", $headers);
    }
    
    /**
     * Build booking confirmation email HTML
     */
    private function buildBookingConfirmationEmail($bookingData, $employeeData) {
        $bookingId = htmlspecialchars($bookingData['booking_id']);
        $employeeName = htmlspecialchars($employeeData['name']);
        $employeeId = htmlspecialchars($bookingData['employee_id']);
        $busNumber = htmlspecialchars($bookingData['bus_number']);
        $route = htmlspecialchars($bookingData['route'] ?? 'Not specified');
        $scheduleDate = htmlspecialchars($bookingData['schedule_date']);
        $departureTime = htmlspecialchars($bookingData['departure_time']);
        $slot = htmlspecialchars($bookingData['slot'] ?? 'Not specified');
        $slotEmoji = $slot === 'morning' ? '🌅' : ($slot === 'evening' ? '🌆' : '🌙');
        $createdAt = htmlspecialchars($bookingData['created_at']);
        
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="margin: 0; font-size: 28px;">✅ Booking Confirmed!</h1>
        <p style="margin: 10px 0 0; font-size: 16px;">Your bus seat has been reserved</p>
    </div>
    
    <div style="background: #f9f9f9; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px;">
        <h2 style="color: #667eea; margin-top: 0;">Booking Details</h2>
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>🎫 Booking ID:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right; color: #667eea; font-weight: bold; font-size: 16px;">{$bookingId}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>👤 Employee Name:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$employeeName}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>🆔 Employee ID:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$employeeId}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>🚌 Bus Number:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$busNumber}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>📍 Route:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$route}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>📅 Date:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$scheduleDate}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>🕐 Departure Time:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$departureTime}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>{$slotEmoji} Slot:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right; text-transform: capitalize;">{$slot}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0;"><strong>⏰ Booked At:</strong></td>
                    <td style="padding: 10px 0; text-align: right;">{$createdAt}</td>
                </tr>
            </table>
        </div>
        
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <h3 style="margin: 0 0 10px 0; color: #856404;">⚠️ Important Guidelines</h3>
            <ul style="margin: 0; padding-left: 20px; color: #856404;">
                <li>Booking a slot does not confirm your physical office attendance</li>
                <li>Be seated in the shuttle 10 minutes before departure</li>
                <li>Follow your company's attendance policy independently</li>
                <li>Use your designated boarding/drop location only</li>
            </ul>
        </div>
        
        <div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <h3 style="margin: 0 0 10px 0; color: #0c5460;">🔔 Departure Timeline</h3>
            <ul style="margin: 0; padding-left: 20px; color: #0c5460;">
                <li><strong>10 minutes before:</strong> Be seated in the shuttle</li>
                <li><strong>5 minutes before:</strong> First whistle - boarding closes soon</li>
                <li><strong>On time:</strong> Final whistle - shuttle departs</li>
            </ul>
        </div>
        
        <p style="text-align: center; color: #666; font-size: 14px; margin: 20px 0;">
            If you have any questions, please contact the Bus Booking Support team.
        </p>
        
        <div style="text-align: center; padding-top: 20px; border-top: 2px solid #e0e0e0;">
            <p style="color: #999; font-size: 12px; margin: 5px 0;">This is an automated message from the Bus Booking System</p>
            <p style="color: #999; font-size: 12px; margin: 5px 0;">Please do not reply to this email</p>
        </div>
    </div>
</body>
</html>
HTML;
        
        return $html;
    }
    
    /**
     * Build booking cancellation email HTML
     */
    private function buildBookingCancellationEmail($bookingData, $employeeData) {
        $bookingId = htmlspecialchars($bookingData['booking_id']);
        $employeeName = htmlspecialchars($employeeData['name']);
        $employeeId = htmlspecialchars($bookingData['employee_id']);
        $busNumber = htmlspecialchars($bookingData['bus_number']);
        $route = htmlspecialchars($bookingData['route'] ?? 'Not specified');
        $scheduleDate = htmlspecialchars($bookingData['schedule_date']);
        $departureTime = htmlspecialchars($bookingData['departure_time']);
        $cancelledAt = htmlspecialchars($bookingData['cancelled_at'] ?? date('Y-m-d H:i:s'));
        
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Cancellation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="margin: 0; font-size: 28px;">❌ Booking Cancelled</h1>
        <p style="margin: 10px 0 0; font-size: 16px;">Your bus reservation has been cancelled</p>
    </div>
    
    <div style="background: #f9f9f9; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px;">
        <h2 style="color: #f5576c; margin-top: 0;">Cancellation Details</h2>
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>🎫 Booking ID:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right; color: #f5576c; font-weight: bold; font-size: 16px;">{$bookingId}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>👤 Employee Name:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$employeeName}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>🆔 Employee ID:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$employeeId}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>🚌 Bus Number:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$busNumber}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>📍 Route:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$route}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>📅 Date:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$scheduleDate}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;"><strong>🕐 Departure Time:</strong></td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f0f0f0; text-align: right;">{$departureTime}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0;"><strong>⏰ Cancelled At:</strong></td>
                    <td style="padding: 10px 0; text-align: right;">{$cancelledAt}</td>
                </tr>
            </table>
        </div>
        
        <div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <h3 style="margin: 0 0 10px 0; color: #0c5460;">ℹ️ What's Next?</h3>
            <p style="margin: 0; color: #0c5460;">You can make a new booking anytime through the Bus Booking System if you need transportation.</p>
        </div>
        
        <p style="text-align: center; color: #666; font-size: 14px; margin: 20px 0;">
            If you did not cancel this booking or have any questions, please contact the Bus Booking Support team immediately.
        </p>
        
        <div style="text-align: center; padding-top: 20px; border-top: 2px solid #e0e0e0;">
            <p style="color: #999; font-size: 12px; margin: 5px 0;">This is an automated message from the Bus Booking System</p>
            <p style="color: #999; font-size: 12px; margin: 5px 0;">Please do not reply to this email</p>
        </div>
    </div>
</body>
</html>
HTML;
        
        return $html;
    }
    
    /**
     * Get email log contents
     */
    public function getEmailLog($lines = 50) {
        if (!file_exists($this->logFile)) {
            return "No email log available";
        }
        
        $logContent = file($this->logFile);
        $logContent = array_slice($logContent, -$lines);
        return implode('', $logContent);
    }
}
