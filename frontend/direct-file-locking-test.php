<?php
/**
 * DIRECT FILE LOCKING TEST - Tests the locking mechanism without network calls
 */

// Include the production API functions
include 'backend/api/production-api.php';

echo "🧪 DIRECT FILE LOCKING TEST\n";
echo "Testing concurrent booking creation with file locking\n\n";

// Setup test environment
$dataPath = __DIR__ . '/backend/data';
if (!is_dir($dataPath)) {
    mkdir($dataPath, 0755, true);
}

// Clear existing bookings
$bookingsFile = $dataPath . '/bookings.json';
if (file_exists($bookingsFile)) {
    unlink($bookingsFile);
}

echo "📁 Test environment prepared\n";

// Test 1: Sequential bookings (should work)
echo "\n🎯 TEST 1: Sequential Bookings\n";

$booking1 = createBooking([
    'employee_id' => 'TEST001',
    'bus_number' => 'B001',
    'schedule_date' => date('Y-m-d')
]);

$booking2 = createBooking([
    'employee_id' => 'TEST002', 
    'bus_number' => 'B002',
    'schedule_date' => date('Y-m-d')
]);

echo "Booking 1: " . $booking1['status'] . " - " . $booking1['message'] . "\n";
echo "Booking 2: " . $booking2['status'] . " - " . $booking2['message'] . "\n";

// Test 2: Slot conflict (should be prevented)
echo "\n🎯 TEST 2: Slot Conflict Detection\n";

$booking3 = createBooking([
    'employee_id' => 'TEST001',
    'bus_number' => 'B003', // Different bus, same morning slot
    'schedule_date' => date('Y-m-d')
]);

echo "Booking 3 (slot conflict): " . $booking3['status'] . " - " . $booking3['message'] . "\n";

// Test 3: Multiple concurrent processes simulation
echo "\n🎯 TEST 3: Concurrent Process Simulation\n";

// Create multiple child processes
$pids = [];
for ($i = 1; $i <= 3; $i++) {
    $pid = pcntl_fork();
    if ($pid == 0) {
        // Child process
        $result = createBooking([
            'employee_id' => "CONCURRENT$i",
            'bus_number' => 'B004',
            'schedule_date' => date('Y-m-d')
        ]);
        
        echo "Process $i: " . $result['status'] . " - " . $result['message'] . "\n";
        exit(0);
    } else {
        $pids[] = $pid;
    }
}

// Wait for all child processes
foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
}

// Test 4: Soft locking test
echo "\n🎯 TEST 4: Soft Locking Test\n";

$reservation = createReservation([
    'employee_id' => 'SOFTLOCK001',
    'bus_number' => 'B005',
    'schedule_date' => date('Y-m-d')
]);

echo "Reservation 1: " . $reservation['status'] . " - " . $reservation['message'] . "\n";

if ($reservation['status'] === 'success') {
    // Try conflicting reservation
    $conflictReservation = createReservation([
        'employee_id' => 'SOFTLOCK002',
        'bus_number' => 'B005',
        'schedule_date' => date('Y-m-d')
    ]);
    
    echo "Conflicting reservation: " . $conflictReservation['status'] . " - " . $conflictReservation['message'] . "\n";
    
    // Confirm original reservation
    $confirmation = confirmBooking([
        'employee_id' => 'SOFTLOCK001',
        'bus_number' => 'B005',
        'schedule_date' => date('Y-m-d'),
        'reservation_token' => $reservation['reservation_token']
    ]);
    
    echo "Booking confirmation: " . $confirmation['status'] . " - " . $confirmation['message'] . "\n";
}

// Final state check
echo "\n📊 FINAL STATE CHECK\n";
$bookings = loadBookings();
$activeBookings = array_filter($bookings, function($b) {
    return $b['status'] === 'active' && $b['schedule_date'] === date('Y-m-d');
});

echo "Total active bookings today: " . count($activeBookings) . "\n";

$busGroups = [];
foreach ($activeBookings as $booking) {
    $bus = $booking['bus_number'];
    if (!isset($busGroups[$bus])) {
        $busGroups[$bus] = 0;
    }
    $busGroups[$bus]++;
}

foreach ($busGroups as $bus => $count) {
    echo "  🚌 Bus $bus: $count bookings\n";
}

echo "\n✅ Direct file locking test completed!\n";
?>