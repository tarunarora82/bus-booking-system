<?php
/**
 * Test Booking Creation with Email via working.html API
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "========================================\n";
echo "🧪 TESTING WORKING.HTML BOOKING API\n";
echo "========================================\n\n";

// Simulate the exact API call from working.html
$apiUrl = 'http://localhost/api/api/production-api.php?action=create-booking';

echo "Testing API: $apiUrl\n\n";

// Get a future date
$futureDate = date('Y-m-d', strtotime('+3 days'));

// Prepare booking data (same format as working.html)
$bookingData = [
    'employee_id' => '11453732',
    'bus_id' => '1',
    'date' => $futureDate,
    'slot' => 'evening'
];

echo "Booking Request:\n";
echo json_encode($bookingData, JSON_PRETTY_PRINT) . "\n\n";

// Send request
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($bookingData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
]);

echo "Sending request...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n\n";

if ($error) {
    echo "❌ CURL Error: $error\n";
    exit(1);
}

echo "API Response:\n";
echo "----------------------------------------\n";
echo $response . "\n";
echo "----------------------------------------\n\n";

$result = json_decode($response, true);

if ($result) {
    echo "Parsed Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    
    if (isset($result['success']) && $result['success']) {
        echo "✅ Booking Created Successfully!\n";
        
        if (isset($result['data']['booking_id'])) {
            echo "   Booking ID: {$result['data']['booking_id']}\n";
        }
        
        // Check email status
        if (isset($result['email_sent'])) {
            if ($result['email_sent']) {
                echo "   ✅ Email Sent: YES\n";
                if (isset($result['email_message'])) {
                    echo "   Email Message: {$result['email_message']}\n";
                }
                if (isset($result['email_recipient'])) {
                    echo "   Email To: {$result['email_recipient']}\n";
                }
            } else {
                echo "   ❌ Email Sent: NO\n";
                if (isset($result['email_message'])) {
                    echo "   Reason: {$result['email_message']}\n";
                }
                if (isset($result['skip_reason'])) {
                    echo "   Skip Reason: {$result['skip_reason']}\n";
                }
            }
        } else {
            echo "   ⚠️  No email status in response\n";
        }
    } else {
        echo "❌ Booking Failed\n";
        if (isset($result['message'])) {
            echo "   Error: {$result['message']}\n";
        }
    }
} else {
    echo "❌ Failed to parse JSON response\n";
}

echo "\n";
echo "========================================\n";
echo "📋 CHECK EMAIL LOG\n";
echo "========================================\n";
echo "\n";

// Check email log
$logPath = '/tmp/bus_bookings/email_log.txt';
if (file_exists($logPath)) {
    echo "Last 10 log entries:\n";
    echo "----------------------------------------\n";
    $lines = file($logPath);
    $lastLines = array_slice($lines, -10);
    foreach ($lastLines as $line) {
        echo $line;
    }
    echo "----------------------------------------\n";
} else {
    echo "⚠️  Log file not found: $logPath\n";
}

echo "\n";
?>
