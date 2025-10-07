<?php
/**
 * Email System Integration Example
 * Shows how to integrate email notifications into your booking workflow
 */

// Include required files
require_once __DIR__ . '/backend/EmailService.php';

// Example 1: Complete Booking Workflow with Email
function createBookingWithEmail($employeeId, $busNumber, $scheduleDate) {
    echo "Creating booking for Employee: $employeeId, Bus: $busNumber\n";
    
    // Step 1: Validate and create booking
    $bookingData = [
        'booking_id' => 'BK' . date('Ymd') . sprintf('%04d', rand(1, 9999)),
        'employee_id' => $employeeId,
        'employee_name' => 'John Doe',
        'bus_number' => $busNumber,
        'route' => 'City Center to Industrial Park',
        'schedule_date' => $scheduleDate,
        'departure_time' => '08:00 AM',
        'slot' => 'morning',
        'status' => 'confirmed',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    echo "✓ Booking created: {$bookingData['booking_id']}\n";
    
    // Step 2: Get employee information
    $employeeData = [
        'employee_id' => $employeeId,
        'name' => 'John Doe',
        'email' => 'john.doe@intel.com',
        'department' => 'Engineering'
    ];
    
    echo "✓ Employee found: {$employeeData['name']} ({$employeeData['email']})\n";
    
    // Step 3: Send confirmation email
    $emailService = new EmailService();
    $emailResult = $emailService->sendBookingConfirmation($bookingData, $employeeData);
    
    if ($emailResult['success']) {
        echo "✓ Email sent successfully to {$emailResult['email']}\n";
    } else {
        echo "✗ Email failed: {$emailResult['message']}\n";
    }
    
    // Step 4: Return complete result
    return [
        'booking' => $bookingData,
        'email_sent' => $emailResult['success'],
        'email_message' => $emailResult['message']
    ];
}

// Example 2: Cancel Booking with Email
function cancelBookingWithEmail($employeeId, $busNumber) {
    echo "Cancelling booking for Employee: $employeeId, Bus: $busNumber\n";
    
    // Step 1: Find and cancel booking
    $bookingData = [
        'booking_id' => 'BK202510070001',
        'employee_id' => $employeeId,
        'employee_name' => 'John Doe',
        'bus_number' => $busNumber,
        'route' => 'City Center to Industrial Park',
        'schedule_date' => date('Y-m-d'),
        'departure_time' => '08:00 AM',
        'slot' => 'morning',
        'status' => 'cancelled',
        'cancelled_at' => date('Y-m-d H:i:s')
    ];
    
    echo "✓ Booking cancelled: {$bookingData['booking_id']}\n";
    
    // Step 2: Get employee information
    $employeeData = [
        'employee_id' => $employeeId,
        'name' => 'John Doe',
        'email' => 'john.doe@intel.com',
        'department' => 'Engineering'
    ];
    
    // Step 3: Send cancellation email
    $emailService = new EmailService();
    $emailResult = $emailService->sendBookingCancellation($bookingData, $employeeData);
    
    if ($emailResult['success']) {
        echo "✓ Cancellation email sent to {$emailResult['email']}\n";
    } else {
        echo "✗ Email failed: {$emailResult['message']}\n";
    }
    
    return [
        'booking' => $bookingData,
        'email_sent' => $emailResult['success'],
        'email_message' => $emailResult['message']
    ];
}

// Example 3: Handle Missing Email Gracefully
function createBookingWithoutEmail($employeeId, $busNumber, $scheduleDate) {
    echo "Creating booking for employee without email: $employeeId\n";
    
    // Booking data
    $bookingData = [
        'booking_id' => 'BK' . date('Ymd') . sprintf('%04d', rand(1, 9999)),
        'employee_id' => $employeeId,
        'employee_name' => 'Jane Smith',
        'bus_number' => $busNumber,
        'route' => 'Express Route',
        'schedule_date' => $scheduleDate,
        'departure_time' => '06:00 PM',
        'slot' => 'evening',
        'status' => 'confirmed',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    echo "✓ Booking created: {$bookingData['booking_id']}\n";
    
    // Employee without email
    $employeeData = [
        'employee_id' => $employeeId,
        'name' => 'Jane Smith',
        // No email field!
        'department' => 'Marketing'
    ];
    
    // Try to send email
    $emailService = new EmailService();
    $emailResult = $emailService->sendBookingConfirmation($bookingData, $employeeData);
    
    if (!$emailResult['success']) {
        echo "⚠ Email not sent: {$emailResult['message']}\n";
        echo "  Reason: {$emailResult['skip_reason']}\n";
        echo "✓ Booking successful despite missing email\n";
    }
    
    return [
        'booking' => $bookingData,
        'email_sent' => $emailResult['success'],
        'email_skip_reason' => $emailResult['skip_reason'] ?? null,
        'email_message' => $emailResult['message']
    ];
}

// Example 4: Retrieve Employee Email from Database/List
function getEmployeeEmail($employeeId) {
    // In real implementation, this would query your employee database
    $employees = [
        '11453732' => [
            'name' => 'John Doe',
            'email' => 'john.doe@intel.com',
            'department' => 'Engineering'
        ],
        '1234567' => [
            'name' => 'Jane Smith',
            'email' => 'jane.smith@intel.com',
            'department' => 'Marketing'
        ],
        'NO_EMAIL' => [
            'name' => 'Bob Johnson',
            // No email
            'department' => 'Finance'
        ]
    ];
    
    return $employees[$employeeId] ?? null;
}

// Example 5: Full Integration Pattern
function completeBookingWorkflow($employeeId, $busNumber, $scheduleDate) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "COMPLETE BOOKING WORKFLOW\n";
    echo str_repeat("=", 60) . "\n\n";
    
    try {
        // 1. Get employee data
        echo "Step 1: Retrieving employee data...\n";
        $employeeData = getEmployeeEmail($employeeId);
        
        if (!$employeeData) {
            throw new Exception("Employee not found: $employeeId");
        }
        
        echo "  ✓ Employee: {$employeeData['name']}\n";
        if (isset($employeeData['email'])) {
            echo "  ✓ Email: {$employeeData['email']}\n";
        } else {
            echo "  ⚠ Email: Not available\n";
        }
        echo "\n";
        
        // 2. Create booking
        echo "Step 2: Creating booking...\n";
        $bookingId = 'BK' . date('Ymd') . sprintf('%04d', rand(1, 9999));
        $bookingData = [
            'booking_id' => $bookingId,
            'employee_id' => $employeeId,
            'employee_name' => $employeeData['name'],
            'bus_number' => $busNumber,
            'route' => 'City Center to Industrial Park',
            'schedule_date' => $scheduleDate,
            'departure_time' => '08:00 AM',
            'slot' => 'morning',
            'status' => 'confirmed',
            'created_at' => date('Y-m-d H:i:s')
        ];
        echo "  ✓ Booking ID: {$bookingId}\n";
        echo "  ✓ Bus: {$busNumber}\n";
        echo "  ✓ Date: {$scheduleDate}\n\n";
        
        // 3. Send email notification
        echo "Step 3: Sending email notification...\n";
        $emailService = new EmailService();
        $emailData = array_merge($employeeData, ['employee_id' => $employeeId]);
        $emailResult = $emailService->sendBookingConfirmation($bookingData, $emailData);
        
        if ($emailResult['success']) {
            echo "  ✓ Email sent to: {$emailResult['email']}\n";
        } else {
            echo "  ⚠ Email not sent: {$emailResult['message']}\n";
            if (isset($emailResult['skip_reason'])) {
                echo "  ⚠ Reason: {$emailResult['skip_reason']}\n";
            }
        }
        echo "\n";
        
        // 4. Return result
        echo "Step 4: Workflow complete!\n";
        echo "  Status: SUCCESS\n";
        echo "  Booking ID: {$bookingId}\n";
        echo "  Email Sent: " . ($emailResult['success'] ? 'Yes' : 'No') . "\n";
        
        return [
            'success' => true,
            'booking_id' => $bookingId,
            'booking_data' => $bookingData,
            'email_sent' => $emailResult['success'],
            'email_message' => $emailResult['message']
        ];
        
    } catch (Exception $e) {
        echo "\n✗ ERROR: " . $e->getMessage() . "\n";
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Run examples
echo "\n" . str_repeat("=", 60) . "\n";
echo "EMAIL SYSTEM INTEGRATION EXAMPLES\n";
echo str_repeat("=", 60) . "\n";

// Example 1: Normal booking with email
echo "\n### Example 1: Normal Booking with Email ###\n";
$result1 = createBookingWithEmail('11453732', 'BUS001', date('Y-m-d'));
print_r($result1);

// Example 2: Cancel booking with email
echo "\n### Example 2: Cancel Booking with Email ###\n";
$result2 = cancelBookingWithEmail('11453732', 'BUS001');
print_r($result2);

// Example 3: Booking without email
echo "\n### Example 3: Booking Without Email (Error Handling) ###\n";
$result3 = createBookingWithoutEmail('NO_EMAIL', 'BUS002', date('Y-m-d'));
print_r($result3);

// Example 4: Complete workflow
echo "\n### Example 4: Complete Workflow ###\n";
$result4 = completeBookingWorkflow('11453732', 'BUS001', date('Y-m-d', strtotime('+1 day')));

echo "\n" . str_repeat("=", 60) . "\n";
echo "All examples completed!\n";
echo str_repeat("=", 60) . "\n\n";

// Show integration tips
echo "INTEGRATION TIPS:\n";
echo "==================\n\n";
echo "1. Always check if employee email exists before sending\n";
echo "2. Handle email failures gracefully - don't block booking\n";
echo "3. Log all email activity for debugging\n";
echo "4. Include unique booking ID in all emails\n";
echo "5. Provide fallback for missing/invalid emails\n";
echo "6. Test with various employee scenarios\n";
echo "7. Monitor email logs regularly\n\n";

echo "See EMAIL_SYSTEM_DOCUMENTATION.md for more details!\n";
?>
