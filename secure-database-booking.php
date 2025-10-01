<?php
/**
 * DATABASE-BASED SECURE BOOKING WITH TRANSACTIONS
 * Ultimate solution for concurrency handling
 */

class SecureBookingService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createBookingWithTransaction($employeeId, $busNumber, $scheduleDate) {
        try {
            // START TRANSACTION - ACID compliance
            $this->pdo->beginTransaction();
            
            // STEP 1: Get bus details with row-level lock
            $busStmt = $this->pdo->prepare("
                SELECT * FROM buses 
                WHERE bus_number = ? 
                FOR UPDATE
            ");
            $busStmt->execute([$busNumber]);
            $bus = $busStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bus) {
                $this->pdo->rollback();
                return ['status' => 'error', 'message' => 'Bus not found'];
            }
            
            // STEP 2: Check existing bookings with row-level lock
            $existingStmt = $this->pdo->prepare("
                SELECT b.*, bus.slot 
                FROM bookings b 
                JOIN buses bus ON b.bus_number = bus.bus_number
                WHERE b.employee_id = ? 
                AND b.schedule_date = ? 
                AND b.status = 'active'
                FOR UPDATE
            ");
            $existingStmt->execute([$employeeId, $scheduleDate]);
            $existingBookings = $existingStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check slot conflict
            foreach ($existingBookings as $existing) {
                if ($existing['slot'] === $bus['slot']) {
                    $this->pdo->rollback();
                    $slotName = $bus['slot'] === 'morning' ? 'Morning' : 'Evening';
                    return [
                        'status' => 'error',
                        'message' => "You already have a {$slotName} slot booking for today."
                    ];
                }
            }
            
            // STEP 3: Check bus capacity with accurate count
            $capacityStmt = $this->pdo->prepare("
                SELECT COUNT(*) as booked_count 
                FROM bookings 
                WHERE bus_number = ? 
                AND schedule_date = ? 
                AND status = 'active'
                FOR UPDATE
            ");
            $capacityStmt->execute([$busNumber, $scheduleDate]);
            $capacityResult = $capacityStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($capacityResult['booked_count'] >= $bus['capacity']) {
                $this->pdo->rollback();
                return ['status' => 'error', 'message' => 'Bus is fully booked'];
            }
            
            // STEP 4: Create booking
            $bookingId = 'BK' . time() . rand(100, 999);
            $insertStmt = $this->pdo->prepare("
                INSERT INTO bookings (
                    id, employee_id, bus_number, schedule_date, 
                    slot, route, departure_time, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $insertResult = $insertStmt->execute([
                $bookingId,
                $employeeId,
                $busNumber,
                $scheduleDate,
                $bus['slot'],
                $bus['route'],
                $bus['departure_time']
            ]);
            
            if (!$insertResult) {
                $this->pdo->rollback();
                return ['status' => 'error', 'message' => 'Failed to create booking'];
            }
            
            // COMMIT TRANSACTION - All or nothing
            $this->pdo->commit();
            
            return [
                'status' => 'success',
                'message' => 'Booking created successfully',
                'booking_id' => $bookingId
            ];
            
        } catch (Exception $e) {
            // ROLLBACK on any error
            $this->pdo->rollback();
            return [
                'status' => 'error',
                'message' => 'Booking failed: ' . $e->getMessage()
            ];
        }
    }
}

// SQL Schema for proper concurrency handling
$schema = "
CREATE TABLE IF NOT EXISTS bookings (
    id VARCHAR(50) PRIMARY KEY,
    employee_id VARCHAR(20) NOT NULL,
    bus_number VARCHAR(10) NOT NULL,
    schedule_date DATE NOT NULL,
    slot ENUM('morning', 'evening') NOT NULL,
    route VARCHAR(100),
    departure_time TIME,
    status ENUM('active', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Prevent double booking same slot
    UNIQUE KEY unique_employee_slot_date (employee_id, slot, schedule_date),
    
    -- Indexes for performance
    INDEX idx_bus_date_status (bus_number, schedule_date, status),
    INDEX idx_employee_date (employee_id, schedule_date)
);

CREATE TABLE IF NOT EXISTS buses (
    bus_number VARCHAR(10) PRIMARY KEY,
    route VARCHAR(100) NOT NULL,
    departure_time TIME NOT NULL,
    capacity INT DEFAULT 45,
    slot ENUM('morning', 'evening') NOT NULL
);
";
?>