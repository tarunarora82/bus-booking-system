<?php
/**
 * Email System Live Test & Diagnostic Tool
 * Tests actual email sending with detailed logging
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "========================================\n";
echo "📧 EMAIL SYSTEM LIVE TEST\n";
echo "========================================\n\n";

// Test 1: Check file exists
echo "Test 1: Checking EmailService file...\n";
$emailServicePath = __DIR__ . '/backend/EmailService.php';
if (file_exists($emailServicePath)) {
    echo "  ✓ EmailService.php found\n";
    require_once $emailServicePath;
} else {
    echo "  ✗ EmailService.php NOT found at: $emailServicePath\n";
    exit(1);
}
echo "\n";

// Test 2: Check configuration
echo "Test 2: Checking email configuration...\n";
$configPath = __DIR__ . '/backend/config/email.php';
if (file_exists($configPath)) {
    echo "  ✓ email.php config found\n";
    require_once $configPath;
    
    $config = EmailConfig::getSmtpConfig();
    echo "  ✓ SMTP Host: " . $config['host'] . "\n";
    echo "  ✓ SMTP Port: " . $config['port'] . "\n";
    echo "  ✓ From Email: " . $config['from_email'] . "\n";
} else {
    echo "  ✗ email.php config NOT found\n";
    exit(1);
}
echo "\n";

// Test 3: Initialize Email Service
echo "Test 3: Initializing EmailService...\n";
try {
    $emailService = new EmailService();
    echo "  ✓ EmailService initialized successfully\n";
} catch (Exception $e) {
    echo "  ✗ Failed to initialize: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// Test 4: Check employee data
echo "Test 4: Checking employee data...\n";
$employeeId = '11453732';
$employeeData = [
    'employee_id' => $employeeId,
    'name' => 'Tarun Arora',
    'email' => 'tarun.arora@intel.com', // Change this to your actual email
    'department' => 'Engineering'
];

echo "  Employee ID: {$employeeData['employee_id']}\n";
echo "  Name: {$employeeData['name']}\n";
echo "  Email: {$employeeData['email']}\n";

// Ask for email confirmation
echo "\n⚠️  IMPORTANT: Is '{$employeeData['email']}' your correct email? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (strtolower(trim($line)) != 'y') {
    echo "\nPlease enter your email address: ";
    $email = trim(fgets($handle));
    $employeeData['email'] = $email;
    echo "  Updated email to: {$employeeData['email']}\n";
}
fclose($handle);
echo "\n";

// Test 5: Prepare test booking data
echo "Test 5: Preparing test booking data...\n";
$bookingData = [
    'booking_id' => 'TEST_' . date('YmdHis'),
    'employee_id' => $employeeId,
    'employee_name' => $employeeData['name'],
    'bus_number' => '113A',
    'route' => 'Whitefield',
    'schedule_date' => '2025-10-07',
    'departure_time' => '16:00',
    'slot' => 'evening',
    'status' => 'confirmed',
    'created_at' => date('Y-m-d H:i:s')
];

echo "  ✓ Booking ID: {$bookingData['booking_id']}\n";
echo "  ✓ Bus: {$bookingData['bus_number']}\n";
echo "  ✓ Route: {$bookingData['route']}\n";
echo "  ✓ Date: {$bookingData['schedule_date']}\n";
echo "  ✓ Time: {$bookingData['departure_time']}\n";
echo "\n";

// Test 6: Send test email
echo "Test 6: Sending test email...\n";
echo "  Target: {$employeeData['email']}\n";
echo "  Please wait...\n\n";

try {
    $result = $emailService->sendBookingConfirmation($bookingData, $employeeData);
    
    if ($result['success']) {
        echo "  ✅ SUCCESS! Email sent successfully!\n";
        echo "  ✓ Email sent to: {$result['email']}\n";
        echo "  ✓ Message: {$result['message']}\n";
        echo "\n";
        echo "  📧 CHECK YOUR INBOX NOW!\n";
        echo "  - Check inbox for: {$employeeData['email']}\n";
        echo "  - Subject: Bus Booking Confirmation - {$bookingData['booking_id']}\n";
        echo "  - Also check SPAM/JUNK folder\n";
    } else {
        echo "  ❌ FAILED! Email not sent\n";
        echo "  ✗ Error: {$result['message']}\n";
        if (isset($result['skip_reason'])) {
            echo "  ✗ Reason: {$result['skip_reason']}\n";
        }
    }
} catch (Exception $e) {
    echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "  Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
echo "\n";

// Test 7: Check email log
echo "Test 7: Checking email activity log...\n";
$logPath = '/tmp/bus_bookings/email_log.txt';
if (file_exists($logPath)) {
    echo "  ✓ Log file found: $logPath\n";
    echo "  Last 10 log entries:\n";
    echo "  " . str_repeat("-", 60) . "\n";
    
    $logContent = file($logPath);
    $lastLines = array_slice($logContent, -10);
    foreach ($lastLines as $line) {
        echo "  " . $line;
    }
    echo "  " . str_repeat("-", 60) . "\n";
} else {
    echo "  ⚠️  Log file not found (this is normal for first run)\n";
    echo "  Expected path: $logPath\n";
}
echo "\n";

// Test 8: Network connectivity test
echo "Test 8: Testing SMTP server connectivity...\n";
echo "  Connecting to {$config['host']}:{$config['port']}...\n";

$socket = @fsockopen($config['host'], $config['port'], $errno, $errstr, 10);
if ($socket) {
    echo "  ✓ Successfully connected to SMTP server\n";
    $response = fgets($socket, 515);
    echo "  ✓ Server response: " . trim($response) . "\n";
    fclose($socket);
} else {
    echo "  ❌ FAILED to connect to SMTP server\n";
    echo "  ✗ Error: $errstr (Code: $errno)\n";
    echo "\n";
    echo "  ⚠️  POSSIBLE ISSUES:\n";
    echo "  1. Firewall blocking port 587\n";
    echo "  2. Corporate proxy required\n";
    echo "  3. SMTP server not accessible from your network\n";
    echo "\n";
    echo "  💡 TRY THIS:\n";
    echo "  - Check if you're behind a corporate proxy\n";
    echo "  - Try from Intel network if possible\n";
    echo "  - Contact IT to allow outbound SMTP on port 587\n";
}
echo "\n";

// Summary
echo "========================================\n";
echo "📊 TEST SUMMARY\n";
echo "========================================\n";
echo "EmailService: " . (class_exists('EmailService') ? "✓ OK" : "✗ FAIL") . "\n";
echo "Configuration: " . (isset($config) ? "✓ OK" : "✗ FAIL") . "\n";
echo "Employee Data: " . (!empty($employeeData['email']) ? "✓ OK" : "✗ FAIL") . "\n";
echo "Email Sent: " . (isset($result) && $result['success'] ? "✅ SUCCESS" : "❌ FAILED") . "\n";
echo "SMTP Connection: " . (isset($socket) && $socket ? "✓ OK" : "❌ FAIL") . "\n";
echo "========================================\n\n";

if (isset($result) && $result['success']) {
    echo "🎉 EMAIL SENT SUCCESSFULLY!\n";
    echo "Check your inbox: {$employeeData['email']}\n";
    echo "\nIf you don't see it:\n";
    echo "1. Check SPAM/JUNK folder\n";
    echo "2. Add sys_github01@intel.com to safe senders\n";
    echo "3. Wait a few minutes for delivery\n";
} else {
    echo "⚠️  EMAIL SENDING FAILED\n";
    echo "\nDEBUGGING STEPS:\n";
    echo "1. Check email log: cat /tmp/bus_bookings/email_log.txt\n";
    echo "2. Verify SMTP credentials in backend/config/email.php\n";
    echo "3. Test SMTP connection from your network\n";
    echo "4. Check if behind corporate proxy\n";
    echo "\nFor help, review: EMAIL_SYSTEM_DOCUMENTATION.md\n";
}
echo "\n";
?>
