<?php
/**
 * SOFT LOCKING MECHANISM FOR BUS BOOKING
 * Provides temporary reservations during booking process
 */

class SoftLockBookingSystem {
    private $lockTimeout = 30; // 30 seconds reservation
    
    public function createReservation($employeeId, $busNumber, $scheduleDate) {
        $lockKey = "booking_lock_{$busNumber}_{$scheduleDate}";
        $lockFile = sys_get_temp_dir() . "/{$lockKey}.lock";
        $lockData = [
            'employee_id' => $employeeId,
            'bus_number' => $busNumber,
            'schedule_date' => $scheduleDate,
            'expires_at' => time() + $this->lockTimeout,
            'created_at' => time()
        ];
        
        // Try to acquire soft lock
        $handle = fopen($lockFile, 'c+');
        if (!$handle) {
            return ['status' => 'error', 'message' => 'Cannot create reservation'];
        }
        
        if (flock($handle, LOCK_EX)) {
            // Check existing lock
            $content = stream_get_contents($handle);
            if ($content) {
                $existingLock = json_decode($content, true);
                if ($existingLock && $existingLock['expires_at'] > time()) {
                    // Lock still valid
                    if ($existingLock['employee_id'] !== $employeeId) {
                        flock($handle, LOCK_UN);
                        fclose($handle);
                        return [
                            'status' => 'error',
                            'message' => 'Bus is temporarily reserved by another user. Please try again in 30 seconds.'
                        ];
                    }
                }
            }
            
            // Create/update reservation
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($lockData));
            flock($handle, LOCK_UN);
            fclose($handle);
            
            return [
                'status' => 'success',
                'message' => 'Bus reserved for 30 seconds',
                'reservation_token' => md5($lockKey . $employeeId),
                'expires_at' => $lockData['expires_at']
            ];
        }
        
        fclose($handle);
        return ['status' => 'error', 'message' => 'Cannot acquire reservation'];
    }
    
    public function confirmBooking($employeeId, $busNumber, $scheduleDate, $reservationToken) {
        $lockKey = "booking_lock_{$busNumber}_{$scheduleDate}";
        $expectedToken = md5($lockKey . $employeeId);
        
        if ($reservationToken !== $expectedToken) {
            return ['status' => 'error', 'message' => 'Invalid reservation token'];
        }
        
        // Proceed with actual booking creation using secure file locking
        $result = $this->createBookingSecure([
            'employee_id' => $employeeId,
            'bus_number' => $busNumber,
            'schedule_date' => $scheduleDate
        ]);
        
        // Clean up reservation
        $lockFile = sys_get_temp_dir() . "/{$lockKey}.lock";
        @unlink($lockFile);
        
        return $result;
    }
    
    public function releaseReservation($employeeId, $busNumber, $scheduleDate) {
        $lockKey = "booking_lock_{$busNumber}_{$scheduleDate}";
        $lockFile = sys_get_temp_dir() . "/{$lockKey}.lock";
        
        $handle = fopen($lockFile, 'r+');
        if ($handle && flock($handle, LOCK_EX)) {
            $content = stream_get_contents($handle);
            if ($content) {
                $lockData = json_decode($content, true);
                if ($lockData && $lockData['employee_id'] === $employeeId) {
                    ftruncate($handle, 0);
                }
            }
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        
        @unlink($lockFile);
    }
    
    private function createBookingSecure($data) {
        // Use the secure file locking method from Option 1
        return createBookingSecure($data);
    }
}

// Frontend integration example
$frontendJavaScript = "
// Enhanced booking process with soft locking
async function bookBusWithReservation(busNumber) {
    const employeeId = document.getElementById('employee_id').value;
    
    try {
        // Step 1: Create reservation
        const reservation = await apiCall('create-reservation', {
            employee_id: employeeId,
            bus_number: busNumber,
            schedule_date: new Date().toISOString().split('T')[0]
        });
        
        if (reservation.status === 'error') {
            showResult(reservation.message, 'error');
            return;
        }
        
        // Step 2: Show countdown and confirm button
        showReservationCountdown(reservation.expires_at, busNumber, reservation.reservation_token);
        
    } catch (error) {
        showResult('Failed to reserve bus: ' + error.message, 'error');
    }
}

function showReservationCountdown(expiresAt, busNumber, token) {
    const timeLeft = expiresAt - Math.floor(Date.now() / 1000);
    const modalHtml = \`
        <div id='reservation-modal' style='position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; display: flex; align-items: center; justify-content: center;'>
            <div style='background: white; padding: 30px; border-radius: 10px; text-align: center; max-width: 400px;'>
                <h3>🎫 Bus Reserved!</h3>
                <p>Bus \${busNumber} is reserved for you.</p>
                <p>Time remaining: <span id='countdown'>\${timeLeft}</span> seconds</p>
                <button onclick='confirmReservation(\"\${busNumber}\", \"\${token}\")' style='background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 5px; margin: 10px; cursor: pointer;'>✅ Confirm Booking</button>
                <button onclick='cancelReservation(\"\${busNumber}\")' style='background: #dc3545; color: white; padding: 12px 24px; border: none; border-radius: 5px; margin: 10px; cursor: pointer;'>❌ Cancel</button>
            </div>
        </div>
    \`;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Countdown timer
    const countdownElement = document.getElementById('countdown');
    const timer = setInterval(() => {
        const remaining = expiresAt - Math.floor(Date.now() / 1000);
        if (remaining <= 0) {
            clearInterval(timer);
            document.getElementById('reservation-modal').remove();
            showResult('Reservation expired. Please try again.', 'error');
        } else {
            countdownElement.textContent = remaining;
        }
    }, 1000);
}

async function confirmReservation(busNumber, token) {
    const employeeId = document.getElementById('employee_id').value;
    
    try {
        const result = await apiCall('confirm-booking', {
            employee_id: employeeId,
            bus_number: busNumber,
            schedule_date: new Date().toISOString().split('T')[0],
            reservation_token: token
        });
        
        document.getElementById('reservation-modal').remove();
        
        if (result.status === 'success') {
            showResult('✅ Booking confirmed successfully!', 'success');
            await loadBookings();
        } else {
            showResult(result.message, 'error');
        }
    } catch (error) {
        showResult('Booking confirmation failed: ' + error.message, 'error');
    }
}
";
?>