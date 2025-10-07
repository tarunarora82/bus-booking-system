<?php
/**
 * Direct SMTP Connection Test
 * Tests raw SMTP functionality without any framework dependencies
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "========================================\n";
echo "🔌 DIRECT SMTP CONNECTION TEST\n";
echo "========================================\n\n";

// SMTP Configuration
$smtpHost = 'smtpauth.intel.com';
$smtpPort = 587;
$smtpUser = 'sys_github01@intel.com';
$smtpPass = 'dateAug21st2025!@#$%';
$fromEmail = 'sys_github01@intel.com';
$fromName = 'Bus Booking System';

// Get recipient email
echo "Enter your email address to receive test: ";
$handle = fopen("php://stdin", "r");
$toEmail = trim(fgets($handle));
fclose($handle);

if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email address!\n";
    exit(1);
}

echo "\n";
echo "Configuration:\n";
echo "  SMTP Host: $smtpHost\n";
echo "  SMTP Port: $smtpPort\n";
echo "  From: $fromEmail\n";
echo "  To: $toEmail\n";
echo "\n";

// Test 1: TCP Connection
echo "Test 1: TCP Connection...\n";
$socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 30);

if (!$socket) {
    echo "  ❌ FAILED: $errstr (Code: $errno)\n";
    echo "\n";
    echo "TROUBLESHOOTING:\n";
    echo "1. Check firewall settings\n";
    echo "2. Verify you're on Intel network or VPN\n";
    echo "3. Check if proxy is required\n";
    exit(1);
}

echo "  ✓ Connected successfully\n";
$response = fgets($socket, 515);
echo "  Server: " . trim($response) . "\n";
echo "\n";

// Helper function to send SMTP command
function smtpSend($socket, $command, $expectedCode) {
    fputs($socket, $command . "\r\n");
    $response = fgets($socket, 515);
    echo "  > $command\n";
    echo "  < " . trim($response) . "\n";
    
    $code = substr($response, 0, 3);
    if ($code != $expectedCode) {
        echo "  ❌ Unexpected response code: $code (expected: $expectedCode)\n";
        return false;
    }
    return true;
}

// Test 2: EHLO
echo "Test 2: EHLO Handshake...\n";
if (!smtpSend($socket, "EHLO localhost", "250")) {
    fclose($socket);
    exit(1);
}
echo "  ✓ EHLO successful\n\n";

// Test 3: STARTTLS
echo "Test 3: STARTTLS (Encryption)...\n";
if (!smtpSend($socket, "STARTTLS", "220")) {
    fclose($socket);
    exit(1);
}

// Enable TLS encryption
if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
    echo "  ❌ TLS encryption failed\n";
    fclose($socket);
    exit(1);
}
echo "  ✓ TLS encryption enabled\n\n";

// Test 4: EHLO after STARTTLS
echo "Test 4: EHLO after TLS...\n";
if (!smtpSend($socket, "EHLO localhost", "250")) {
    fclose($socket);
    exit(1);
}
echo "  ✓ EHLO successful\n\n";

// Test 5: AUTH LOGIN
echo "Test 5: Authentication...\n";
if (!smtpSend($socket, "AUTH LOGIN", "334")) {
    fclose($socket);
    exit(1);
}

$encodedUser = base64_encode($smtpUser);
if (!smtpSend($socket, $encodedUser, "334")) {
    fclose($socket);
    exit(1);
}

$encodedPass = base64_encode($smtpPass);
fputs($socket, $encodedPass . "\r\n");
$response = fgets($socket, 515);
echo "  > [password hidden]\n";
echo "  < " . trim($response) . "\n";

$code = substr($response, 0, 3);
if ($code != "235") {
    echo "  ❌ Authentication failed!\n";
    echo "  Check credentials in backend/config/email.php\n";
    fclose($socket);
    exit(1);
}
echo "  ✓ Authentication successful\n\n";

// Test 6: Send test email
echo "Test 6: Sending test email...\n";

// MAIL FROM
if (!smtpSend($socket, "MAIL FROM:<$fromEmail>", "250")) {
    fclose($socket);
    exit(1);
}

// RCPT TO
if (!smtpSend($socket, "RCPT TO:<$toEmail>", "250")) {
    fclose($socket);
    exit(1);
}

// DATA
if (!smtpSend($socket, "DATA", "354")) {
    fclose($socket);
    exit(1);
}

// Email content
$subject = "SMTP Test from Bus Booking System - " . date('Y-m-d H:i:s');
$message = "From: $fromName <$fromEmail>\r\n";
$message .= "To: <$toEmail>\r\n";
$message .= "Subject: $subject\r\n";
$message .= "MIME-Version: 1.0\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n";
$message .= "\r\n";
$message .= "<!DOCTYPE html>\n";
$message .= "<html>\n";
$message .= "<body style='font-family: Arial, sans-serif;'>\n";
$message .= "  <h2 style='color: #0071c5;'>✅ SMTP Test Successful!</h2>\n";
$message .= "  <p>This email confirms that the Bus Booking System can successfully send emails.</p>\n";
$message .= "  <hr>\n";
$message .= "  <p><strong>Test Details:</strong></p>\n";
$message .= "  <ul>\n";
$message .= "    <li>SMTP Server: $smtpHost:$smtpPort</li>\n";
$message .= "    <li>Test Time: " . date('Y-m-d H:i:s') . "</li>\n";
$message .= "    <li>From: $fromEmail</li>\n";
$message .= "    <li>To: $toEmail</li>\n";
$message .= "  </ul>\n";
$message .= "  <hr>\n";
$message .= "  <p style='color: #28a745;'><strong>✓ Email system is working correctly!</strong></p>\n";
$message .= "  <p style='font-size: 12px; color: #666;'>Bus Booking System - Email Test</p>\n";
$message .= "</body>\n";
$message .= "</html>\r\n";
$message .= ".\r\n";

fputs($socket, $message);
$response = fgets($socket, 515);
echo "  > [email content]\n";
echo "  < " . trim($response) . "\n";

$code = substr($response, 0, 3);
if ($code != "250") {
    echo "  ❌ Failed to send email\n";
    fclose($socket);
    exit(1);
}
echo "  ✅ Email sent successfully!\n\n";

// QUIT
echo "Test 7: Closing connection...\n";
smtpSend($socket, "QUIT", "221");
fclose($socket);
echo "  ✓ Connection closed\n\n";

// Summary
echo "========================================\n";
echo "🎉 ALL TESTS PASSED!\n";
echo "========================================\n";
echo "\n";
echo "✅ TCP Connection: OK\n";
echo "✅ TLS Encryption: OK\n";
echo "✅ Authentication: OK\n";
echo "✅ Email Sent: OK\n";
echo "\n";
echo "📧 CHECK YOUR INBOX!\n";
echo "   Email: $toEmail\n";
echo "   Subject: $subject\n";
echo "\n";
echo "If you don't see it:\n";
echo "1. Check SPAM/JUNK folder\n";
echo "2. Wait a few minutes\n";
echo "3. Add $fromEmail to safe senders\n";
echo "\n";
echo "✓ Email system is working correctly!\n";
echo "✓ You can now use the booking system with confidence!\n";
echo "\n";
?>
