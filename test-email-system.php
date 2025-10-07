<?php
/**
 * Standalone Email System Test
 * Tests the email notification functionality independently
 */

require_once __DIR__ . '/backend/EmailService.php';

echo "========================================\n";
echo "📧 EMAIL NOTIFICATION SYSTEM TEST\n";
echo "========================================\n\n";

// Test Configuration
echo "✓ SMTP Configuration:\n";
echo "  Server: " . EmailConfig::SMTP_HOST . ":" . EmailConfig::SMTP_PORT . "\n";
echo "  From: " . EmailConfig::FROM_EMAIL . "\n";
echo "  Security: TLS\n\n";

// Initialize Email Service
$emailService = new EmailService();
echo "✓ Email Service initialized\n\n";

// Test Case 1: Booking Confirmation with Valid Email
echo "TEST 1: Booking Confirmation with Valid Email\n";
echo "----------------------------------------------\n";

$bookingData1 = [
    'booking_id' => 'TEST_BK' . date('YmdHis'),
    'employee_id' => '11453732',
    'employee_name' => 'John Doe',
    'bus_number' => 'BUS001',
    'route' => 'City Center to Industrial Park',
    'schedule_date' => date('Y-m-d'),
    'departure_time' => '08:00 AM',
    'slot' => 'morning',
    'status' => 'confirmed',
    'created_at' => date('Y-m-d H:i:s')
];

$employeeData1 = [
    'employee_id' => '11453732',
    'name' => 'John Doe',
    'email' => 'john.doe@intel.com',
    'department' => 'Engineering'
];

echo "Booking ID: " . $bookingData1['booking_id'] . "\n";
echo "Employee: " . $employeeData1['name'] . " (" . $employeeData1['email'] . ")\n";
echo "Sending email...\n";

$result1 = $emailService->sendBookingConfirmation($bookingData1, $employeeData1);

if ($result1['success']) {
    echo "✅ SUCCESS: " . $result1['message'] . "\n";
    echo "   Email sent to: " . $result1['email'] . "\n";
} else {
    echo "❌ FAILED: " . $result1['message'] . "\n";
}
echo "\n";

// Test Case 2: Employee Without Email
echo "TEST 2: Employee Without Email (Error Handling)\n";
echo "------------------------------------------------\n";

$bookingData2 = [
    'booking_id' => 'TEST_BK' . (date('YmdHis') + 1),
    'employee_id' => 'NO_EMAIL_123',
    'employee_name' => 'Jane Smith',
    'bus_number' => 'BUS002',
    'route' => 'Express Route',
    'schedule_date' => date('Y-m-d'),
    'departure_time' => '06:00 PM',
    'slot' => 'evening',
    'status' => 'confirmed',
    'created_at' => date('Y-m-d H:i:s')
];

$employeeData2 = [
    'employee_id' => 'NO_EMAIL_123',
    'name' => 'Jane Smith',
    // No email field
    'department' => 'Marketing'
];

echo "Booking ID: " . $bookingData2['booking_id'] . "\n";
echo "Employee: " . $employeeData2['name'] . " (No Email)\n";
echo "Testing error handling...\n";

$result2 = $emailService->sendBookingConfirmation($bookingData2, $employeeData2);

if (!$result2['success'] && isset($result2['skip_reason'])) {
    echo "✅ ERROR HANDLING WORKS: " . $result2['message'] . "\n";
    echo "   Skip Reason: " . $result2['skip_reason'] . "\n";
} else {
    echo "❌ ERROR HANDLING FAILED\n";
}
echo "\n";

// Test Case 3: Invalid Email Address
echo "TEST 3: Invalid Email Address (Error Handling)\n";
echo "-----------------------------------------------\n";

$bookingData3 = [
    'booking_id' => 'TEST_BK' . (date('YmdHis') + 2),
    'employee_id' => 'INVALID_456',
    'employee_name' => 'Bob Johnson',
    'bus_number' => 'BUS003',
    'route' => 'Night Route',
    'schedule_date' => date('Y-m-d'),
    'departure_time' => '10:00 PM',
    'slot' => 'night',
    'status' => 'confirmed',
    'created_at' => date('Y-m-d H:i:s')
];

$employeeData3 = [
    'employee_id' => 'INVALID_456',
    'name' => 'Bob Johnson',
    'email' => 'invalid-email-format',  // Invalid email
    'department' => 'Finance'
];

echo "Booking ID: " . $bookingData3['booking_id'] . "\n";
echo "Employee: " . $employeeData3['name'] . " (Invalid Email: " . $employeeData3['email'] . ")\n";
echo "Testing error handling...\n";

$result3 = $emailService->sendBookingConfirmation($bookingData3, $employeeData3);

if (!$result3['success'] && isset($result3['skip_reason'])) {
    echo "✅ ERROR HANDLING WORKS: " . $result3['message'] . "\n";
    echo "   Skip Reason: " . $result3['skip_reason'] . "\n";
} else {
    echo "❌ ERROR HANDLING FAILED\n";
}
echo "\n";

// Test Case 4: Cancellation Email
echo "TEST 4: Booking Cancellation Email\n";
echo "-----------------------------------\n";

$cancelBookingData = [
    'booking_id' => 'TEST_BK' . date('YmdHis'),
    'employee_id' => '11453732',
    'employee_name' => 'John Doe',
    'bus_number' => 'BUS001',
    'route' => 'City Center to Industrial Park',
    'schedule_date' => date('Y-m-d'),
    'departure_time' => '08:00 AM',
    'slot' => 'morning',
    'status' => 'cancelled',
    'cancelled_at' => date('Y-m-d H:i:s')
];

$cancelEmployeeData = [
    'employee_id' => '11453732',
    'name' => 'John Doe',
    'email' => 'john.doe@intel.com',
    'department' => 'Engineering'
];

echo "Booking ID: " . $cancelBookingData['booking_id'] . "\n";
echo "Employee: " . $cancelEmployeeData['name'] . " (" . $cancelEmployeeData['email'] . ")\n";
echo "Sending cancellation email...\n";

$result4 = $emailService->sendBookingCancellation($cancelBookingData, $cancelEmployeeData);

if ($result4['success']) {
    echo "✅ SUCCESS: " . $result4['message'] . "\n";
    echo "   Email sent to: " . $result4['email'] . "\n";
} else {
    echo "❌ FAILED: " . $result4['message'] . "\n";
}
echo "\n";

// View Email Log
echo "========================================\n";
echo "📋 EMAIL ACTIVITY LOG (Last 20 entries)\n";
echo "========================================\n";
$log = $emailService->getEmailLog(20);
echo $log;
echo "\n";

// Summary
echo "========================================\n";
echo "📊 TEST SUMMARY\n";
echo "========================================\n";
echo "Test 1 (Valid Email): " . ($result1['success'] ? "✅ PASSED" : "❌ FAILED") . "\n";
echo "Test 2 (Missing Email): " . (!$result2['success'] && isset($result2['skip_reason']) ? "✅ PASSED" : "❌ FAILED") . "\n";
echo "Test 3 (Invalid Email): " . (!$result3['success'] && isset($result3['skip_reason']) ? "✅ PASSED" : "❌ FAILED") . "\n";
echo "Test 4 (Cancellation): " . ($result4['success'] ? "✅ PASSED" : "❌ FAILED") . "\n";
echo "========================================\n\n";

// Instructions
echo "📝 NEXT STEPS:\n";
echo "1. Check your email inbox for test emails\n";
echo "2. Review email log at: /tmp/bus_bookings/email_log.txt\n";
echo "3. Verify email templates are formatted correctly\n";
echo "4. Test with real bookings through the API\n";
echo "5. Open test-email-notifications.html for interactive testing\n\n";

echo "✅ Email system test completed!\n";
?>
