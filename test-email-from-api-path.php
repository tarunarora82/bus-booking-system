<?php
/**
 * Direct Test of Email from Production API Path
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing Email from /api/api/production-api.php path\n";
echo "==================================================\n\n";

// Change to the correct directory
chdir('/var/www/html/api/api/');

echo "1. Loading EmailService from: " . __DIR__ . "/../EmailService.php\n";
require_once __DIR__ . '/../EmailService.php';

echo "2. Creating EmailService instance...\n";
$emailService = new EmailService();

echo "3. Preparing test booking data...\n";
$bookingData = [
    'booking_id' => 'TEST_' . date('YmdHis'),
    'employee_id' => '11453732',
    'employee_name' => 'John Doe',
    'bus_number' => '113A',
    'route' => 'Whitefield',
    'schedule_date' => date('Y-m-d', strtotime('+3 days')),
    'departure_time' => '16:00',
    'slot' => 'evening',
    'status' => 'confirmed',
    'created_at' => date('Y-m-d H:i:s')
];

$employeeData = [
    'employee_id' => '11453732',
    'name' => 'Tarun Arora',
    'email' => 'tarun.arora@intel.com',
    'department' => 'Engineering'
];

echo "4. Booking Details:\n";
echo "   Booking ID: {$bookingData['booking_id']}\n";
echo "   Employee: {$employeeData['name']}\n";
echo "   Email: {$employeeData['email']}\n";
echo "   Bus: {$bookingData['bus_number']}\n";
echo "   Route: {$bookingData['route']}\n";
echo "   Date: {$bookingData['schedule_date']}\n\n";

echo "5. Attempting to send email...\n";
try {
    $result = $emailService->sendBookingConfirmation($bookingData, $employeeData);
    
    echo "\n6. Email Result:\n";
    echo "   Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "   Message: {$result['message']}\n";
    
    if (isset($result['email'])) {
        echo "   Email: {$result['email']}\n";
    }
    if (isset($result['skip_reason'])) {
        echo "   Skip Reason: {$result['skip_reason']}\n";
    }
    
    if ($result['success']) {
        echo "\n✅ SUCCESS! Email sent to {$result['email']}\n";
        echo "\n📧 CHECK YOUR INBOX!\n";
        echo "   To: {$result['email']}\n";
        echo "   Subject: Bus Booking Confirmation - {$bookingData['booking_id']}\n";
        echo "   From: Bus Booking System (sys_github01@intel.com)\n";
    } else {
        echo "\n❌ FAILED! Email not sent\n";
        echo "   Reason: {$result['message']}\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n==================================================\n";
echo "7. Checking email log...\n";
$logPath = '/tmp/bus_bookings/email_log.txt';
if (file_exists($logPath)) {
    echo "   Log file exists\n";
    echo "   Last 5 entries:\n";
    $lines = file($logPath);
    $lastLines = array_slice($lines, -5);
    foreach ($lastLines as $line) {
        echo "   " . trim($line) . "\n";
    }
} else {
    echo "   ⚠️  Log file not found\n";
}

echo "\n==================================================\n";
echo "Test complete!\n";
?>
