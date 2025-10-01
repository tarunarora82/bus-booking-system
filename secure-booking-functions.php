<?php
/**
 * SECURE BOOKING FUNCTIONS WITH FILE LOCKING
 * Fixes race conditions in bus booking system
 */

function loadBookingsSecure() {
    global $dataPath;
    $bookingsFile = $dataPath . '/bookings.json';
    
    $handle = fopen($bookingsFile, 'c+'); // Create if not exists, don't truncate
    if (!$handle) {
        return [];
    }
    
    // EXCLUSIVE LOCK - Critical section starts here
    if (flock($handle, LOCK_EX)) {
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN); // Release lock
        fclose($handle);
        
        return $content ? json_decode($content, true) ?: [] : [];
    }
    
    fclose($handle);
    return [];
}

function saveBookingsSecure($bookings) {
    global $dataPath;
    $bookingsFile = $dataPath . '/bookings.json';
    
    $handle = fopen($bookingsFile, 'c'); // Create if not exists
    if (!$handle) {
        return false;
    }
    
    // EXCLUSIVE LOCK - Critical section starts here
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0); // Clear file
        rewind($handle);
        $result = fwrite($handle, json_encode($bookings, JSON_PRETTY_PRINT));
        flock($handle, LOCK_UN); // Release lock
        fclose($handle);
        return $result !== false;
    }
    
    fclose($handle);
    return false;
}

function createBookingSecure($data) {
    $employeeId = $data['employee_id'] ?? '';
    $busNumber = $data['bus_number'] ?? '';
    $scheduleDate = $data['schedule_date'] ?? date('Y-m-d');
    
    if (empty($employeeId) || empty($busNumber)) {
        return [
            'status' => 'error',
            'message' => 'Employee ID and Bus Number are required'
        ];
    }
    
    // ATOMIC OPERATION - File locked during entire process
    global $dataPath;
    $bookingsFile = $dataPath . '/bookings.json';
    $handle = fopen($bookingsFile, 'c+');
    
    if (!$handle) {
        return ['status' => 'error', 'message' => 'Cannot access booking system'];
    }
    
    // EXCLUSIVE LOCK - Prevents concurrent access
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return ['status' => 'error', 'message' => 'System busy, please try again'];
    }
    
    try {
        // Read current bookings
        $content = stream_get_contents($handle);
        $bookings = $content ? json_decode($content, true) ?: [] : [];
        
        // Get bus details
        $buses = getAvailableBuses();
        $selectedBus = null;
        foreach ($buses['data'] as $bus) {
            if ($bus['bus_number'] === $busNumber) {
                $selectedBus = $bus;
                break;
            }
        }
        
        if (!$selectedBus) {
            return ['status' => 'error', 'message' => 'Selected bus not found'];
        }
        
        $busSlot = $selectedBus['slot'] ?? 'unknown';
        
        // Check for existing bookings (INSIDE LOCKED SECTION)
        foreach ($bookings as $booking) {
            if ($booking['employee_id'] === $employeeId && 
                $booking['schedule_date'] === $scheduleDate &&
                $booking['status'] === 'active') {
                
                // Get existing booking slot
                foreach ($buses['data'] as $bus) {
                    if ($bus['bus_number'] === $booking['bus_number']) {
                        $existingSlot = $bus['slot'] ?? 'unknown';
                        if ($existingSlot === $busSlot) {
                            $slotName = $busSlot === 'morning' ? 'Morning' : 'Evening';
                            return [
                                'status' => 'error',
                                'message' => "You already have a {$slotName} slot booking for today."
                            ];
                        }
                        break;
                    }
                }
            }
        }
        
        // Check if bus capacity exceeded
        $busBookingCount = 0;
        foreach ($bookings as $booking) {
            if ($booking['bus_number'] === $busNumber && 
                $booking['schedule_date'] === $scheduleDate && 
                $booking['status'] === 'active') {
                $busBookingCount++;
            }
        }
        
        if ($busBookingCount >= ($selectedBus['capacity'] ?? 45)) {
            return ['status' => 'error', 'message' => 'Bus is fully booked'];
        }
        
        // Create booking (STILL INSIDE LOCKED SECTION)
        $bookingId = 'BK' . time() . rand(100, 999);
        $newBooking = [
            'id' => $bookingId,
            'employee_id' => $employeeId,
            'bus_number' => $busNumber,
            'schedule_date' => $scheduleDate,
            'slot' => $busSlot,
            'route' => $selectedBus['route'] ?? 'Route TBD',
            'departure_time' => $selectedBus['departure_time'] ?? '00:00',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $bookings[] = $newBooking;
        
        // Write back atomically
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($bookings, JSON_PRETTY_PRINT));
        
        return [
            'status' => 'success',
            'message' => 'Booking created successfully',
            'booking' => $newBooking
        ];
        
    } finally {
        // Always release lock
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
?>