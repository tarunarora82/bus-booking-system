<?php

namespace BusBooking\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Email service for sending notifications
 */
class EmailService
{
    private PHPMailer $mailer;
    private array $templates;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
        $this->loadTemplates();
    }

    private function setupMailer(): void
    {
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = SMTP_USERNAME;
        $this->mailer->Password = SMTP_PASSWORD;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = SMTP_PORT;
        $this->mailer->setFrom(SMTP_USERNAME, 'Bus Booking System');
        $this->mailer->isHTML(true);
    }

    private function loadTemplates(): void
    {
        $this->templates = [
            'booking_confirmed' => [
                'subject' => 'Booking Confirmed - Bus #{bus_number} on {date}',
                'body' => $this->getBookingConfirmationTemplate()
            ],
            'booking_cancelled' => [
                'subject' => 'Booking Cancelled - Bus #{bus_number} on {date}',
                'body' => $this->getCancellationTemplate()
            ],
            'waitlist_notification' => [
                'subject' => 'Added to Waitlist - Bus #{bus_number} on {date}',
                'body' => $this->getWaitlistTemplate()
            ],
            'waitlist_conversion' => [
                'subject' => 'Booking Confirmed from Waitlist - Bus #{bus_number} on {date}',
                'body' => $this->getWaitlistConversionTemplate()
            ]
        ];
    }

    public function sendBookingConfirmation(array $user, array $booking): bool
    {
        if (empty($user['email'])) {
            return false; // No email to send to
        }

        $variables = [
            'user_name' => $user['name'] ?: $user['worker_id'],
            'worker_id' => $user['worker_id'],
            'bus_number' => $booking['bus_number'],
            'date' => date('Y-m-d', strtotime($booking['booking_date'])),
            'departure_time' => date('H:i', strtotime($booking['departure_time'])),
            'boarding_time' => date('H:i', strtotime($booking['boarding_time'])),
            'schedule_type' => ucfirst($booking['schedule_type']),
            'booking_id' => $booking['id']
        ];

        return $this->sendEmail(
            $user['email'],
            'booking_confirmed',
            $variables
        );
    }

    public function sendCancellationConfirmation(array $user, array $booking): bool
    {
        if (empty($user['email'])) {
            return false;
        }

        $variables = [
            'user_name' => $user['name'] ?: $user['worker_id'],
            'worker_id' => $user['worker_id'],
            'bus_number' => $booking['bus_number'],
            'date' => date('Y-m-d', strtotime($booking['booking_date'])),
            'departure_time' => date('H:i', strtotime($booking['departure_time'])),
            'schedule_type' => ucfirst($booking['schedule_type']),
            'booking_id' => $booking['id']
        ];

        return $this->sendEmail(
            $user['email'],
            'booking_cancelled',
            $variables
        );
    }

    public function sendWaitlistNotification(array $user, array $schedule, string $date, int $position): bool
    {
        if (empty($user['email'])) {
            return false;
        }

        $variables = [
            'user_name' => $user['name'] ?: $user['worker_id'],
            'worker_id' => $user['worker_id'],
            'bus_number' => $schedule['bus_number'],
            'date' => $date,
            'departure_time' => date('H:i', strtotime($schedule['departure_time'])),
            'schedule_type' => ucfirst($schedule['schedule_type']),
            'position' => $position
        ];

        return $this->sendEmail(
            $user['email'],
            'waitlist_notification',
            $variables
        );
    }

    public function sendWaitlistConversionNotification(array $user, array $booking): bool
    {
        if (empty($user['email'])) {
            return false;
        }

        $variables = [
            'user_name' => $user['name'] ?: $user['worker_id'],
            'worker_id' => $user['worker_id'],
            'bus_number' => $booking['bus_number'],
            'date' => date('Y-m-d', strtotime($booking['booking_date'])),
            'departure_time' => date('H:i', strtotime($booking['departure_time'])),
            'boarding_time' => date('H:i', strtotime($booking['boarding_time'])),
            'schedule_type' => ucfirst($booking['schedule_type']),
            'booking_id' => $booking['id']
        ];

        return $this->sendEmail(
            $user['email'],
            'waitlist_conversion',
            $variables
        );
    }

    private function sendEmail(string $to, string $template, array $variables): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);

            $subject = $this->replaceVariables($this->templates[$template]['subject'], $variables);
            $body = $this->replaceVariables($this->templates[$template]['body'], $variables);

            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    private function replaceVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }

    private function getBookingConfirmationTemplate(): string
    {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #2c5aa0;">Bus Booking Confirmed</h2>
                
                <p>Dear {user_name},</p>
                
                <p>Your bus booking has been confirmed successfully!</p>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0;">Booking Details</h3>
                    <p><strong>Booking ID:</strong> {booking_id}</p>
                    <p><strong>Worker ID:</strong> {worker_id}</p>
                    <p><strong>Bus Number:</strong> {bus_number}</p>
                    <p><strong>Schedule:</strong> {schedule_type}</p>
                    <p><strong>Date:</strong> {date}</p>
                    <p><strong>Boarding Time:</strong> {boarding_time}</p>
                    <p><strong>Departure Time:</strong> {departure_time}</p>
                </div>
                
                <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
                    <h4 style="margin-top: 0; color: #856404;">Important Reminders</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>Please arrive at least 5 minutes before boarding time</li>
                        <li>You can cancel your booking up to 15 minutes before departure</li>
                        <li>This booking does not confirm your office attendance</li>
                    </ul>
                </div>
                
                <p>Thank you for using the Bus Booking System!</p>
                
                <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
                <p style="font-size: 12px; color: #666;">
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>';
    }

    private function getCancellationTemplate(): string
    {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #dc3545;">Bus Booking Cancelled</h2>
                
                <p>Dear {user_name},</p>
                
                <p>Your bus booking has been cancelled successfully.</p>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0;">Cancelled Booking Details</h3>
                    <p><strong>Booking ID:</strong> {booking_id}</p>
                    <p><strong>Worker ID:</strong> {worker_id}</p>
                    <p><strong>Bus Number:</strong> {bus_number}</p>
                    <p><strong>Schedule:</strong> {schedule_type}</p>
                    <p><strong>Date:</strong> {date}</p>
                    <p><strong>Departure Time:</strong> {departure_time}</p>
                </div>
                
                <p>You can make a new booking anytime through the Bus Booking System.</p>
                
                <p>Thank you for using the Bus Booking System!</p>
                
                <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
                <p style="font-size: 12px; color: #666;">
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>';
    }

    private function getWaitlistTemplate(): string
    {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #ffc107;">Added to Waitlist</h2>
                
                <p>Dear {user_name},</p>
                
                <p>The bus you requested is currently full, but you have been added to the waitlist.</p>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0;">Waitlist Details</h3>
                    <p><strong>Worker ID:</strong> {worker_id}</p>
                    <p><strong>Bus Number:</strong> {bus_number}</p>
                    <p><strong>Schedule:</strong> {schedule_type}</p>
                    <p><strong>Date:</strong> {date}</p>
                    <p><strong>Departure Time:</strong> {departure_time}</p>
                    <p><strong>Your Position:</strong> #{position}</p>
                </div>
                
                <div style="background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #17a2b8;">
                    <h4 style="margin-top: 0; color: #0c5460;">What happens next?</h4>
                    <p>If a seat becomes available, you will be automatically confirmed and notified via email. We will also send you a booking confirmation with all the details.</p>
                </div>
                
                <p>Thank you for using the Bus Booking System!</p>
                
                <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
                <p style="font-size: 12px; color: #666;">
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>';
    }

    private function getWaitlistConversionTemplate(): string
    {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #28a745;">Great News! Your Booking is Confirmed</h2>
                
                <p>Dear {user_name},</p>
                
                <p>A seat has become available and your waitlist entry has been converted to a confirmed booking!</p>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0;">Confirmed Booking Details</h3>
                    <p><strong>Booking ID:</strong> {booking_id}</p>
                    <p><strong>Worker ID:</strong> {worker_id}</p>
                    <p><strong>Bus Number:</strong> {bus_number}</p>
                    <p><strong>Schedule:</strong> {schedule_type}</p>
                    <p><strong>Date:</strong> {date}</p>
                    <p><strong>Boarding Time:</strong> {boarding_time}</p>
                    <p><strong>Departure Time:</strong> {departure_time}</p>
                </div>
                
                <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <h4 style="margin-top: 0; color: #155724;">Important Reminders</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>Please arrive at least 5 minutes before boarding time</li>
                        <li>You can cancel your booking up to 15 minutes before departure</li>
                        <li>This booking does not confirm your office attendance</li>
                    </ul>
                </div>
                
                <p>Thank you for using the Bus Booking System!</p>
                
                <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
                <p style="font-size: 12px; color: #666;">
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>';
    }
}