<?php
/**
 * Auto SMTP Test - No interactive input required
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "========================================\n";
echo "🔌 AUTOMATIC SMTP CONNECTION TEST\n";
echo "========================================\n\n";

// Configuration
$smtpHost = 'smtpauth.intel.com';
$smtpPort = 587;
$smtpUser = 'sys_github01@intel.com';
$smtpPass = 'dateAug21st2025!@#$%';
$fromEmail = 'sys_github01@intel.com';
$fromName = 'Bus Booking System';
$toEmail = 'tarun.arora@intel.com'; // Auto-set to your email

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
    echo "\nTROUBLESHOOTING:\n";
    echo "1. Check firewall settings\n";
    echo "2. Verify you're on Intel network or VPN\n";
    echo "3. Check if proxy is required\n";
    exit(1);
}

echo "  ✓ Connected successfully\n";

// Read multi-line banner
do {
    $response = fgets($socket, 515);
    if ($response === false) break;
    
    echo "  Server: " . trim($response) . "\n";
    
    // Multi-line responses have '-' after code, single line have space
    $code = substr($response, 0, 3);
    $separator = substr($response, 3, 1);
    
    if ($separator == ' ') break; // Last line of banner
} while (true);

echo "\n";

// Helper function
function smtpCommand($socket, $command, $expectedCode, $hideCommand = false) {
    fputs($socket, $command . "\r\n");
    
    if (!$hideCommand) {
        echo "  > $command\n";
    } else {
        echo "  > [hidden]\n";
    }
    
    // Read all response lines (multi-line responses end with space after code)
    $fullResponse = '';
    $lastCode = '';
    do {
        $line = fgets($socket, 515);
        if ($line === false) break;
        
        $fullResponse .= $line;
        $lastCode = substr($line, 0, 3);
        $separator = substr($line, 3, 1);
        
        echo "  < " . trim($line) . "\n";
        
        // If separator is space, this is the last line
        if ($separator == ' ') break;
    } while (true);
    
    if ($lastCode != $expectedCode) {
        echo "  ❌ Error: Expected $expectedCode, got $lastCode\n";
        return false;
    }
    return true;
}

// Test 2: EHLO
echo "Test 2: EHLO...\n";
if (!smtpCommand($socket, "EHLO localhost", "250")) exit(1);
echo "  ✓ OK\n\n";

// Test 3: STARTTLS
echo "Test 3: STARTTLS...\n";
if (!smtpCommand($socket, "STARTTLS", "220")) exit(1);

// Enable TLS with relaxed certificate verification (for internal corporate servers)
$cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
stream_context_set_option($socket, 'ssl', 'verify_peer', false);
stream_context_set_option($socket, 'ssl', 'verify_peer_name', false);
stream_context_set_option($socket, 'ssl', 'allow_self_signed', true);

if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
    echo "  ❌ TLS failed\n";
    exit(1);
}
echo "  ✓ TLS enabled\n\n";

// Test 4: EHLO after TLS
echo "Test 4: EHLO after TLS...\n";
if (!smtpCommand($socket, "EHLO localhost", "250")) exit(1);
echo "  ✓ OK\n\n";

// Test 5: AUTH
echo "Test 5: Authentication...\n";
if (!smtpCommand($socket, "AUTH LOGIN", "334")) exit(1);
if (!smtpCommand($socket, base64_encode($smtpUser), "334", true)) exit(1);

fputs($socket, base64_encode($smtpPass) . "\r\n");
$response = fgets($socket, 515);
echo "  > [password]\n";
echo "  < " . trim($response) . "\n";

if (substr($response, 0, 3) != "235") {
    echo "  ❌ Auth failed! Response: " . trim($response) . "\n";
    exit(1);
}
echo "  ✓ Authenticated\n\n";

// Test 6: Send Email
echo "Test 6: Sending email...\n";
if (!smtpCommand($socket, "MAIL FROM:<$fromEmail>", "250")) exit(1);
if (!smtpCommand($socket, "RCPT TO:<$toEmail>", "250")) exit(1);
if (!smtpCommand($socket, "DATA", "354")) exit(1);

$subject = "Bus Booking System - SMTP Test " . date('Y-m-d H:i:s');
$message = "From: $fromName <$fromEmail>\r\n";
$message .= "To: <$toEmail>\r\n";
$message .= "Subject: $subject\r\n";
$message .= "MIME-Version: 1.0\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
$message .= "<!DOCTYPE html><html><body style='font-family:Arial;'>\n";
$message .= "<h2 style='color:#0071c5;'>✅ SMTP Test Successful!</h2>\n";
$message .= "<p>The Bus Booking System email functionality is working correctly.</p>\n";
$message .= "<hr><p><strong>Test Details:</strong></p><ul>\n";
$message .= "<li>Server: $smtpHost:$smtpPort</li>\n";
$message .= "<li>Time: " . date('Y-m-d H:i:s') . "</li>\n";
$message .= "<li>From: $fromEmail</li>\n";
$message .= "<li>To: $toEmail</li>\n";
$message .= "</ul><hr>\n";
$message .= "<p style='color:#28a745;'><strong>✓ Email system operational!</strong></p>\n";
$message .= "<p style='font-size:12px;color:#666;'>Bus Booking System - Automated Test</p>\n";
$message .= "</body></html>\r\n.\r\n";

fputs($socket, $message);
$response = fgets($socket, 515);
echo "  > [email data]\n";
echo "  < " . trim($response) . "\n";

if (substr($response, 0, 3) != "250") {
    echo "  ❌ Send failed\n";
    exit(1);
}
echo "  ✅ Email sent!\n\n";

// Cleanup
smtpCommand($socket, "QUIT", "221");
fclose($socket);

// Summary
echo "========================================\n";
echo "🎉 ALL TESTS PASSED!\n";
echo "========================================\n\n";
echo "✅ Connection: OK\n";
echo "✅ TLS: OK\n";
echo "✅ Authentication: OK\n";
echo "✅ Email Sent: OK\n\n";
echo "📧 CHECK YOUR INBOX!\n";
echo "   To: $toEmail\n";
echo "   Subject: $subject\n\n";
echo "If you don't see it:\n";
echo "1. Check SPAM/JUNK folder\n";
echo "2. Wait 1-2 minutes\n";
echo "3. Add $fromEmail to safe senders\n\n";
echo "✓ Your email system is fully operational!\n\n";
?>
